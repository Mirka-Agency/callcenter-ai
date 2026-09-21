#!/bin/bash
# MixMonitor post-process for Issabel.
# 1) Shrink WAV for AI without changing the filename/URL.
# 2) POST call.ended to the app with the real dated recording URL.
# Runs as asterisk. Never delete the original on failure.

FILE="${1:-}"
LOG=/tmp/cc-mixmon-post.log
WEBHOOK_URL="${CC_WEBHOOK_URL:-http://192.168.2.165/webhooks/voip/REPLACE_TOKEN}"
PUBLIC_BASE="${CC_RECORDINGS_PUBLIC_BASE:-http://192.168.2.16/mirka-call-recordings}"
SPOOL_ROOT="/var/spool/asterisk/monitor"

if [ -z "$FILE" ]; then
    echo "$(date '+%F %T') missing-filename" >> "$LOG"
    exit 0
fi

if [ ! -f "$FILE" ]; then
    echo "$(date '+%F %T') missing-file $FILE" >> "$LOG"
    exit 0
fi

SIZE=$(stat -c%s "$FILE" 2>/dev/null || echo 0)
if [ "$SIZE" -lt 1024 ]; then
    echo "$(date '+%F %T') skip-small $SIZE $FILE" >> "$LOG"
    exit 0
fi

TMP="${FILE}.ulaw.wav"
if /usr/bin/sox "$FILE" -e u-law "$TMP" 2>>"$LOG"; then
    NEWSIZE=$(stat -c%s "$TMP" 2>/dev/null || echo 0)
    if [ "$NEWSIZE" -gt 500 ]; then
        mv -f "$TMP" "$FILE"
        echo "$(date '+%F %T') ok $SIZE->$NEWSIZE $FILE" >> "$LOG"
        SIZE="$NEWSIZE"
    else
        rm -f "$TMP"
        echo "$(date '+%F %T') sox-tiny keep-original $FILE" >> "$LOG"
    fi
else
    rm -f "$TMP"
    echo "$(date '+%F %T') sox-failed keep-original $FILE" >> "$LOG"
fi

NAME=$(basename "$FILE")
REL="${FILE#$SPOOL_ROOT/}"
RECORDING_URL="${PUBLIC_BASE}/${REL}"

DIRECTION=""
FROM=""
TO=""
EXTENSION=""
UNIQUEID=""

if [[ "$NAME" =~ ^out-(.+)-([0-9]{2,6})-([0-9]{8})-([0-9]{6})-([0-9]+\.[0-9]+)\.(wav|mp3)$ ]]; then
    DIRECTION="outbound"
    TO="${BASH_REMATCH[1]}"
    EXTENSION="${BASH_REMATCH[2]}"
    FROM="$EXTENSION"
    UNIQUEID="${BASH_REMATCH[5]}"
elif [[ "$NAME" =~ ^q-([0-9]+)-(.+)-([0-9]{8})-([0-9]{6})-([0-9]+\.[0-9]+)\.(wav|mp3)$ ]]; then
    DIRECTION="inbound"
    TO="${BASH_REMATCH[1]}"
    FROM="${BASH_REMATCH[2]}"
    UNIQUEID="${BASH_REMATCH[5]}"
elif [[ "$NAME" =~ ^exten-([0-9]{2,6})-(.+)-([0-9]{8})-([0-9]{6})-([0-9]+\.[0-9]+)\.(wav|mp3)$ ]]; then
    EXTENSION="${BASH_REMATCH[1]}"
    OTHER="${BASH_REMATCH[2]}"
    UNIQUEID="${BASH_REMATCH[5]}"
    if [ ${#OTHER} -lt 5 ]; then
        echo "$(date '+%F %T') skip-internal $NAME" >> "$LOG"
        exit 0
    fi
    DIRECTION="inbound"
    FROM="$OTHER"
    TO="$EXTENSION"
else
    echo "$(date '+%F %T') skip-unparsed $NAME" >> "$LOG"
    exit 0
fi

DURATION=0
if [ -x /usr/bin/soxi ]; then
    DURATION=$(/usr/bin/soxi -D "$FILE" 2>/dev/null | awk '{printf "%d", $1}')
fi

PAYLOAD=$(/usr/bin/printf '{"event":"call.ended","call_id":"%s","direction":"%s","from":"%s","to":"%s","status":"ANSWER","duration":%s,"extension":"%s","recording_url":"%s"}' \
    "$UNIQUEID" "$DIRECTION" "$FROM" "$TO" "${DURATION:-0}" "$EXTENSION" "$RECORDING_URL")

HTTP=$(/usr/bin/curl -sS -o /tmp/cc-mixmon-webhook.body -w '%{http_code}' --max-time 5 \
    -X POST -H 'Content-Type: application/json' \
    -d "$PAYLOAD" "$WEBHOOK_URL" 2>>"$LOG" || echo 000)

echo "$(date '+%F %T') webhook $HTTP $UNIQUEID $NAME" >> "$LOG"
exit 0
