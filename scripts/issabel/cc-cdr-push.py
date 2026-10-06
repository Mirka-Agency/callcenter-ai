#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""Send every finished Issabel call to the app, one event per linkedid.

Answered calls with a recording already arrive from the CRM webhook and
cc-mixmon-post.sh. This feed adds everything else (no answer, busy, internal,
IVR) so the app's total matches the PBX. The app ignores a CDR event for a
call it already has, so nothing sent by the other sources is overwritten.

Install (as root):
  install -o asterisk -g asterisk -m 0755 cc-cdr-push.py /usr/local/bin/cc-cdr-push.py
  echo 'CC_WEBHOOK_URL=http://APP/webhooks/voip/TOKEN' > /etc/sysconfig/cc-cdr-push
  echo '* * * * * asterisk . /etc/sysconfig/cc-cdr-push; export CC_WEBHOOK_URL; /usr/local/bin/cc-cdr-push.py' > /etc/cron.d/cc-cdr-push

Python 2.7 compatible (Issabel 4).
"""
from __future__ import print_function

import json
import os
import re
import subprocess
import sys
import time

try:
    from urllib2 import Request, urlopen
except ImportError:  # pragma: no cover
    from urllib.request import Request, urlopen

WEBHOOK_URL = os.environ.get('CC_WEBHOOK_URL', '')
PUBLIC_BASE = os.environ.get('CC_RECORDINGS_PUBLIC_BASE', 'http://192.168.2.16/mirka-call-recordings').rstrip('/')
SPOOL_ROOT = '/var/spool/asterisk/monitor'
STATE_DIR = os.environ.get('CC_STATE_DIR', '/var/lib/cc-cdr-push')
LOG = '/tmp/cc-cdr-push.log'
OUTBOUND_PREFIX = os.environ.get('CC_OUTBOUND_PREFIX', '9')
# Wait until the CRM webhook and cc-mixmon-post.sh (up to ~60s after hangup) have posted.
SETTLE_SECONDS = int(os.environ.get('CC_SETTLE_SECONDS', '300'))
LOOKBACK_HOURS = int(os.environ.get('CC_LOOKBACK_HOURS', '24'))
DRY_RUN = os.environ.get('CC_DRY_RUN') == '1'

EXT_RE = re.compile(r'^(?:SIP|PJSIP|IAX2)/(\d{2,6})-')
LOCAL_EXT_RE = re.compile(r'^Local/(\d{2,6})@')


def log(message):
    with open(LOG, 'a') as handle:
        handle.write('%s %s\n' % (time.strftime('%Y-%m-%d %H:%M:%S'), message))


def db_credentials():
    values = {}
    with open('/etc/amportal.conf') as handle:
        for line in handle:
            match = re.match(r'^(AMPDBUSER|AMPDBPASS)=(.*)$', line.strip())
            if match:
                values[match.group(1)] = match.group(2).strip().strip('"').strip("'")
    return values.get('AMPDBUSER', ''), values.get('AMPDBPASS', '')


def fetch_rows():
    user, password = db_credentials()
    sql = (
        "SELECT uniqueid, linkedid, UNIX_TIMESTAMP(calldate), src, dst, channel, dstchannel, "
        "disposition, duration, billsec, IFNULL(recordingfile, '') "
        "FROM cdr WHERE calldate >= NOW() - INTERVAL %d HOUR ORDER BY calldate, sequence" % LOOKBACK_HOURS
    )
    env = dict(os.environ, MYSQL_PWD=password)
    output = subprocess.check_output(
        ['mysql', '-N', '-B', '-u', user, 'asteriskcdrdb', '-e', sql], env=env,
    )
    if not isinstance(output, str):
        output = output.decode('utf-8', 'replace')
    rows = []
    for line in output.splitlines():
        parts = line.split('\t')
        if len(parts) < 11:
            continue
        rows.append({
            'uniqueid': parts[0], 'linkedid': parts[1] or parts[0], 'ts': int(float(parts[2] or 0)),
            'src': parts[3], 'dst': parts[4], 'channel': parts[5], 'dstchannel': parts[6],
            'disposition': parts[7], 'duration': int(parts[8] or 0), 'billsec': int(parts[9] or 0),
            'recordingfile': parts[10],
        })
    return rows


def extension(channel):
    match = EXT_RE.match(channel or '') or LOCAL_EXT_RE.match(channel or '')
    return match.group(1) if match else None


def is_trunk(channel):
    return bool(channel) and not channel.startswith('Local/') and extension(channel) is None


def recording_url(name, ts):
    if not name:
        return None
    base = os.path.basename(name)
    day = time.strftime('%Y/%m/%d', time.localtime(ts))
    for rel in (day + '/' + base, base):
        path = os.path.join(SPOOL_ROOT, rel)
        if os.path.isfile(path) and os.path.getsize(path) >= 1024:
            return PUBLIC_BASE + '/' + rel
    return None


def strip_prefix(number):
    if OUTBOUND_PREFIX and number.startswith(OUTBOUND_PREFIX) and len(number) > 5:
        return number[len(OUTBOUND_PREFIX):]
    return number


def build_payload(linkedid, legs):
    first = next((leg for leg in legs if leg['uniqueid'] == linkedid), legs[0])
    answered = [leg for leg in legs if leg['disposition'] == 'ANSWERED' and leg['billsec'] > 0]
    agent_answered = [leg for leg in answered if extension(leg['dstchannel']) or extension(leg['channel'])]
    talk = max(agent_answered or answered, key=lambda leg: leg['billsec']) if answered else None

    caller_ext = extension(first['channel'])
    to_trunk = next((leg for leg in legs if is_trunk(leg['dstchannel'])), None)

    if is_trunk(first['channel']):
        direction = 'inbound'
        source, destination = first['src'], first['dst']
        agent = None
        if talk is not None:
            agent = extension(talk['dstchannel']) or extension(talk['channel'])
        if agent is None:
            agent = next((extension(leg['dstchannel']) for leg in legs if EXT_RE.match(leg['dstchannel'] or '')), None)
    elif to_trunk is not None:
        direction = 'outbound'
        agent = caller_ext or next((extension(leg['channel']) for leg in legs if EXT_RE.match(leg['channel'] or '')), None)
        source = agent or first['src']
        destination = strip_prefix(to_trunk['dst'] or first['dst'])
    else:
        direction = 'outbound'
        agent = caller_ext or first['src']
        source = agent
        destination = extension(first['dstchannel']) or first['dst']

    if talk is not None:
        status = 'ANSWERED'
    else:
        status = {'BUSY': 'BUSY', 'FAILED': 'FAILED', 'CONGESTION': 'FAILED'}.get(legs[-1]['disposition'], 'NO ANSWER')

    url = None
    if talk is not None:
        url = recording_url(talk['recordingfile'], talk['ts']) or next(
            (recording_url(leg['recordingfile'], leg['ts']) for leg in legs if leg['recordingfile']), None,
        )

    payload = {
        'event': 'call.ended' if talk is not None else 'call.missed',
        'source': 'cdr',
        'call_id': linkedid,
        'direction': direction,
        'from': source,
        'to': destination,
        'status': status,
        'disposition': status,
        'duration': talk['billsec'] if talk else 0,
        'billsec': talk['billsec'] if talk else 0,
        'started_at': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime(min(leg['ts'] for leg in legs))),
        'extension': agent,
    }
    if url:
        payload['recording_url'] = url
    return payload


def load_sent(path):
    if not os.path.isfile(path):
        return set()
    with open(path) as handle:
        return set(line.strip() for line in handle if line.strip())


def prune_sent(path, sent):
    cutoff = time.time() - (LOOKBACK_HOURS + 24) * 3600
    keep = [item for item in sent if float(item.split('.')[0] or 0) >= cutoff]
    tmp = path + '.tmp'
    with open(tmp, 'w') as handle:
        handle.write('\n'.join(sorted(keep)) + ('\n' if keep else ''))
    os.rename(tmp, path)


def post(payload):
    request = Request(WEBHOOK_URL, json.dumps(payload).encode('utf-8'), {'Content-Type': 'application/json'})
    response = urlopen(request, timeout=10)
    return response.getcode()


def main():
    if not DRY_RUN and (not WEBHOOK_URL or 'REPLACE_TOKEN' in WEBHOOK_URL):
        log('missing CC_WEBHOOK_URL')
        return 1

    if not os.path.isdir(STATE_DIR):
        os.makedirs(STATE_DIR)
    sent_path = os.path.join(STATE_DIR, 'sent.txt')
    sent = load_sent(sent_path)

    groups = {}
    for row in fetch_rows():
        groups.setdefault(row['linkedid'], []).append(row)

    now = time.time()
    posted = failed = 0
    with open(sent_path, 'a') as sent_file:
        for linkedid, legs in sorted(groups.items(), key=lambda item: item[1][0]['ts']):
            if linkedid in sent:
                continue
            if max(leg['ts'] + leg['duration'] for leg in legs) > now - SETTLE_SECONDS:
                continue
            payload = build_payload(linkedid, legs)
            if DRY_RUN:
                print(json.dumps(payload, sort_keys=True))
                continue
            try:
                code = post(payload)
            except Exception as error:  # noqa: BLE001
                failed += 1
                log('error %s %s' % (linkedid, error))
                continue
            if 200 <= code < 300:
                sent_file.write(linkedid + '\n')
                sent.add(linkedid)
                posted += 1
            else:
                failed += 1
                log('http %s %s' % (code, linkedid))

    if posted or failed:
        log('posted=%d failed=%d' % (posted, failed))
    prune_sent(sent_path, sent)
    return 0


if __name__ == '__main__':
    sys.exit(main())
