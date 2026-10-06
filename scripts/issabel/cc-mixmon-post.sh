#!/bin/bash
# MixMonitor post-process for Issabel.
# Install in /etc/asterisk/extensions_custom.conf [globals]:
#   MIXMON_POST=/usr/local/bin/cc-mixmon-post.sh ^{MIXMONITOR_FILENAME}
# then: asterisk -rx 'dialplan reload'
#
# Posts answered outbound calls to the app. Inbound calls already arrive from the
# CRM webhook with the answering extension; set CC_POST_INBOUND=1 only when no CRM sends them.
# The call outcome (answered / busy / no answer) and talk time come from the CDR row.
# Runs as asterisk. Recordings are never deleted.

FILE="${1:-}"
LOG=/tmp/cc-mixmon-post.log
WEBHOOK_URL="${CC_WEBHOOK_URL:-http://192.168.2.165/webhooks/voip/REPLACE_TOKEN}"
PUBLIC_BASE="${CC_RECORDINGS_PUBLIC_BASE:-http://192.168.2.16/mirka-call-recordings}"
SPOOL_ROOT="/var/spool/asterisk/monitor"
CDR_WAIT_SECONDS="${CC_CDR_WAIT_SECONDS:-60}"

log() {
    echo "$(date '+%F %T') $*" >> "$LOG"
}

if [ -z "$FILE" ]; then
    log "missing-filename (MIXMON_POST must pass ^{MIXMONITOR_FILENAME})"
    exit 0
fi

NAME=$(basename "$FILE")
DIRECTION=""
FROM=""
TO=""
EXTENSION=""
UNIQUEID=""

if [[ "$NAME" =~ ^out-(.+)-([0-9]{2,6})-([0-9]{8})-([0-9]{6})-([0-9]+\.[0-9]+)\.(wav|WAV|mp3|gsm)$ ]]; then
    DIRECTION="outbound"
    TO="${BASH_REMATCH[1]}"
    EXTENSION="${BASH_REMATCH[2]}"
    FROM="$EXTENSION"
    UNIQUEID="${BASH_REMATCH[5]}"
    if [ ${#TO} -lt 5 ]; then
        log "skip-internal $NAME"
        exit 0
    fi
    # Outbound routes dial 9 + number; the customer number follows the prefix.
    TO="${TO#${CC_OUTBOUND_PREFIX-9}}"
elif [[ "$NAME" =~ ^q-([0-9]+)-(.+)-([0-9]{8})-([0-9]{6})-([0-9]+\.[0-9]+)\.(wav|WAV|mp3|gsm)$ ]]; then
    DIRECTION="inbound"
    TO="${BASH_REMATCH[1]}"
    FROM="${BASH_REMATCH[2]}"
    UNIQUEID="${BASH_REMATCH[5]}"
elif [[ "$NAME" =~ ^exten-([0-9]{2,6})-(.+)-([0-9]{8})-([0-9]{6})-([0-9]+\.[0-9]+)\.(wav|WAV|mp3|gsm)$ ]]; then
    EXTENSION="${BASH_REMATCH[1]}"
    OTHER="${BASH_REMATCH[2]}"
    UNIQUEID="${BASH_REMATCH[5]}"
    if [ ${#OTHER} -lt 5 ]; then
        log "skip-internal $NAME"
        exit 0
    fi
    DIRECTION="inbound"
    FROM="$OTHER"
    TO="$EXTENSION"
else
    log "skip-unparsed $NAME"
    exit 0
fi

if [ "$DIRECTION" = "inbound" ] && [ "${CC_POST_INBOUND:-0}" != "1" ]; then
    log "skip-inbound-crm $NAME"
    exit 0
fi

post_call() {
    local row disposition billsec started waited=0

    # The CDR row is written at hangup, sometimes after MixMonitor has finished.
    while :; do
        if [ -r /etc/amportal.conf ]; then
            # shellcheck disable=SC1090
            . <(grep -E '^AMPDB(USER|PASS)=' /etc/amportal.conf)
            row=$(mysql -N -B -u"$AMPDBUSER" -p"$AMPDBPASS" asteriskcdrdb -e \
                "SELECT disposition, billsec, UNIX_TIMESTAMP(calldate) FROM cdr WHERE uniqueid = '$UNIQUEID' ORDER BY (disposition = 'ANSWERED') DESC, billsec DESC LIMIT 1" 2>/dev/null)
        fi
        [ -n "$row" ] && break
        if [ "$waited" -ge "$CDR_WAIT_SECONDS" ]; then
            log "skip-no-cdr $UNIQUEID $NAME"
            return
        fi
        sleep 5
        waited=$((waited + 5))
    done

    disposition=$(printf '%s' "$row" | cut -f1)
    billsec=$(printf '%s' "$row" | cut -f2)
    started=$(date -u -d "@$(printf '%s' "$row" | cut -f3)" '+%Y-%m-%dT%H:%M:%SZ')

    if [ "$disposition" != "ANSWERED" ] || [ "${billsec:-0}" -le 0 ]; then
        log "skip-not-connected $disposition $UNIQUEID $NAME"
        return
    fi

    local rel="${FILE#$SPOOL_ROOT/}"
    local recording_url="${PUBLIC_BASE}/${rel}"
    local payload http

    payload=$(/usr/bin/printf '{"event":"call.ended","call_id":"%s","direction":"%s","from":"%s","to":"%s","status":"ANSWERED","duration":%s,"started_at":"%s","extension":"%s","recording_url":"%s"}' \
        "$UNIQUEID" "$DIRECTION" "$FROM" "$TO" "$billsec" "$started" "$EXTENSION" "$recording_url")

    http=$(/usr/bin/curl -sS -o /tmp/cc-mixmon-webhook.body -w '%{http_code}' --max-time 10 \
        -X POST -H 'Content-Type: application/json' \
        -d "$payload" "$WEBHOOK_URL" 2>>"$LOG" || echo 000)

    log "webhook $http $UNIQUEID $NAME"
}

if [ "${CC_FOREGROUND:-0}" = "1" ]; then
    post_call
else
    post_call </dev/null >/dev/null 2>&1 &
fi
exit 0
