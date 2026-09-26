#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Live-Inhalte holen: Datenbank + Medien einer Website vom Ziel in DIESE Installation (lokal/Staging)
# und den Stand merken – danach lokal bearbeiten und mit deploy/content-push.sh zurückspielen.
#
#   deploy/content-pull.sh <ziel> [website-key]     z. B. deploy/content-pull.sh production default
#
# ACHTUNG: überschreibt Inhalte, Medien und Benutzerkonten dieser Installation (Konfiguration bleibt).
# Ziel: deploy/targets/<ziel>.env (SSH_TARGET, BASE, PHP; APP_DIR, wenn nicht $BASE/current;
# SSH_CMD/SCP_CMD, z. B. "sshpass -e ssh" bei Passwort-Anmeldung).
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail
cd "$(dirname "$0")/.."
TARGET="${1:?Aufruf: deploy/content-pull.sh <ziel> [website-key]}"; SITE="${2:-default}"
# shellcheck disable=SC1090
source "deploy/targets/$TARGET.env"
APP="${APP_DIR:-$BASE/current}"; RPHP="${PHP:-php}"; SSH="${SSH_CMD:-ssh}"; SCP="${SCP_CMD:-scp}"
LPHP="${LOCAL_PHP:-php}"; LSITE="${LOCAL_SITE:-$SITE}"   # LOCAL_SITE: anderer Website-Key in dieser Installation

echo "▸ Sicherung auf $TARGET ($SITE)"
FILE=$($SSH ${SSH_OPTS:-} "$SSH_TARGET" "cd '$APP' && $RPHP bin/console site:backup --site='$SITE'" | tail -1)
NAME=$(basename "$FILE"); TMP="${TMPDIR:-/tmp}/$NAME"
echo "▸ Übertragen: $NAME"
$SCP ${SSH_OPTS:-} "$SSH_TARGET:$FILE" "$TMP"
echo "▸ Einspielen (lokal, $SITE)"
$LPHP bin/console site:restore "$TMP" --force --site="$LSITE"
$LPHP bin/console migrate --site="$LSITE" >/dev/null
$LPHP bin/console content:snapshot --site="$LSITE"
rm -f "$TMP"
echo "✓ Lokal = Live-Stand von $SITE. Bearbeiten, dann: deploy/content-push.sh $TARGET $SITE"
