#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Live-Inhalte auf Staging holen (Datenbank + Medien einer Website).
#
#   deploy/sync-content.sh <website-key>          z. B. deploy/sync-content.sh default
#
# Richtung ist bewusst nur production → staging: Inhalte entstehen live,
# Staging ist zum Testen von Code, Theme und Erweiterungen.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail
cd "$(dirname "$0")/.."
SITE="${1:-default}"
# shellcheck disable=SC1091
source deploy/targets/production.env; P_SSH="$SSH_TARGET"; P_BASE="$BASE"; P_PHP="${PHP:-php}"
# shellcheck disable=SC1091
source deploy/targets/staging.env;    S_SSH="$SSH_TARGET"; S_BASE="$BASE"; S_PHP="${PHP:-php}"

echo "▸ Sicherung auf production ($SITE)"
FILE=$(ssh ${SSH_OPTS:-} "$P_SSH" "cd '$P_BASE/current' && $P_PHP bin/console site:backup --site='$SITE'" | tail -1)
NAME=$(basename "$FILE")
echo "▸ Übertragen: $NAME"
scp ${SSH_OPTS:-} "$P_SSH:$FILE" "/tmp/$NAME"
scp ${SSH_OPTS:-} "/tmp/$NAME" "$S_SSH:$S_BASE/shared/storage/$NAME"
rm -f "/tmp/$NAME"
echo "▸ Einspielen auf staging"
ssh ${SSH_OPTS:-} "$S_SSH" "cd '$S_BASE/current' && $S_PHP bin/console site:restore '$S_BASE/shared/storage/$NAME' --force --site='$SITE' && rm -f '$S_BASE/shared/storage/$NAME'"
echo "✓ Staging hat jetzt den Live-Inhalt von $SITE."
