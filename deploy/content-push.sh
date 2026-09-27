#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Lokal geänderte Seiten zurück auf das Ziel spielen (Gegenstück zu deploy/content-pull.sh).
#
#   deploy/content-push.sh <ziel> [website-key] [--publish] [--dry-run] [--yes]
#
# Nur Seiten, die sich seit dem Holen lokal geändert haben. Live wird je Seite geprüft: Hat dort seitdem jemand
# gearbeitet (oder liegt ein Entwurf), wird NICHTS übernommen (Konflikt) – neu holen und wiederholen.
# Übernahme als Entwurf mit Version „Content-Sync“ (rückgängig über Versionen); --publish veröffentlicht sofort.
# Neue Seiten und neu hochgeladene Medien gehen mit (live neue IDs); Pool-Dateien als Verweis, wenn der Pool live existiert.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail
cd "$(dirname "$0")/.."
TARGET="${1:?Aufruf: deploy/content-push.sh <ziel> [website-key] [--publish] [--dry-run] [--yes]}"; shift
SITE=default; [[ "${1:-}" != --* && -n "${1:-}" ]] && { SITE="$1"; shift; }
PUBLISH=""; DRY=""; YES=""
for a in "$@"; do case "$a" in --publish) PUBLISH="--publish";; --dry-run) DRY=1;; --yes) YES=1;; *) echo "Unbekannt: $a" >&2; exit 1;; esac; done
# shellcheck disable=SC1090
source "deploy/targets/$TARGET.env"
APP="${APP_DIR:-$BASE/current}"; RPHP="${PHP:-php}"; SSH="${SSH_CMD:-ssh}"; SCP="${SCP_CMD:-scp}"
LPHP="${LOCAL_PHP:-php}"; LSITE="${LOCAL_SITE:-$SITE}"   # LOCAL_SITE: anderer Website-Key in dieser Installation

OUT="${TMPDIR:-/tmp}/content-sync-$SITE-$$.json"
echo "▸ Geänderte Seiten (lokal, $SITE)"
$LPHP bin/console content:export --site="$LSITE" --out="$OUT"
[[ -f "$OUT" ]] || exit 0
REMOTE="/tmp/$(basename "$OUT")"
$SCP ${SSH_OPTS:-} "$OUT" "$SSH_TARGET:$REMOTE"
echo "▸ Prüfen auf $TARGET"
if ! $SSH ${SSH_OPTS:-} "$SSH_TARGET" "cd '$APP' && $RPHP bin/console content:import '$REMOTE' --dry-run $PUBLISH --site='$SITE'"; then
  $SSH ${SSH_OPTS:-} "$SSH_TARGET" "rm -f '$REMOTE'"; rm -f "$OUT"; exit 2
fi
if [[ -n "$DRY" ]]; then $SSH ${SSH_OPTS:-} "$SSH_TARGET" "rm -f '$REMOTE'"; rm -f "$OUT"; exit 0; fi
if [[ -z "$YES" ]]; then read -r -p "Übernehmen? [j/N] " ok; [[ "$ok" == [jJyY]* ]] || { $SSH ${SSH_OPTS:-} "$SSH_TARGET" "rm -f '$REMOTE'"; rm -f "$OUT"; exit 1; }; fi
echo "▸ Übernehmen auf $TARGET"
$SSH ${SSH_OPTS:-} "$SSH_TARGET" "cd '$APP' && $RPHP bin/console content:import '$REMOTE' $PUBLISH --site='$SITE'; rc=\$?; rm -f '$REMOTE'; exit \$rc"
# Nächster Abgleich baut auf dem neuen Live-Stand auf
$LPHP bin/console content:snapshot --pushed="$OUT" $PUBLISH --site="$LSITE" >/dev/null
rm -f "$OUT"
echo "✓ Fertig."
