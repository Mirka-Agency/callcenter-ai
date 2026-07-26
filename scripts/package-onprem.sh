#!/usr/bin/env bash
# Build a transfer zip for bare-metal on-prem install.
# Prefers git archive (tracked files only — no vendor/node_modules/.env).
#
# Usage:
#   ./scripts/package-onprem.sh
#
# Commit on-prem changes before packaging so they are included.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

STAMP="$(date +%Y%m%d)"
OUT_DIR="${ROOT}/dist"
OUT_ZIP="${OUT_DIR}/avayar-onprem-${STAMP}.zip"

mkdir -p "${OUT_DIR}"
rm -f "${OUT_ZIP}"

if ! command -v git >/dev/null || ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  echo "error: git repository required (script uses git archive)." >&2
  exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "warning: you have uncommitted changes; they will NOT be in the zip." >&2
  echo "         commit (or stash) first if those files are needed on the server." >&2
fi

# Flat archive (unzip into /opt/callcenter)
git archive --format=zip -o "${OUT_ZIP}" HEAD

echo "Created: ${OUT_ZIP}"
echo "Size:    $(du -h "${OUT_ZIP}" | awk '{print $1}')"
echo "Commit:  $(git rev-parse --short HEAD)"
echo
echo "Next:"
echo "  scp ${OUT_ZIP} user@SERVER_IP:/tmp/"
echo "  # on server:"
echo "  sudo mkdir -p /opt/callcenter && sudo unzip /tmp/$(basename "${OUT_ZIP}") -d /opt/callcenter"
echo "  cd /opt/callcenter && cp .env.onprem.example .env"
echo "  # edit .env — set ONPREM_ADMIN_PASSWORD (this IS the /admin login password)"
echo "  # then follow docs/on-prem.md from section 3"
