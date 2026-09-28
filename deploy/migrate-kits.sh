#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Einmalige Umstellung einer bestehenden Installation: themes/ → kits/, public/themes/ → public/kits/
# (Kern ab 1.0.0 mit Core\Kit – liest kits/ und fällt auf themes/ zurück).
#
#   deploy/migrate-kits.sh --local [ordner]          in dieser bzw. der angegebenen Installation
#   deploy/migrate-kits.sh <ziel>                    auf dem Server aus deploy/targets/<ziel>.env (APP_DIR bzw. $BASE/current)
#   … --dry-run                                      nur anzeigen, was passieren würde
#   … --no-symlink                                   ohne Übergangs-Links themes → kits (sonst: Links bleiben stehen)
#
# Ablauf (idempotent – ein zweiter Aufruf ändert nichts):
#   1. Sicherung: storage/backups/themes-<zeit>.tar.gz mit themes/ und public/themes/ (nur echte Ordner, keine Links)
#   2. Verschieben, wenn kits/ noch fehlt: themes → kits, public/themes → public/kits.
#      Gibt es kits/ schon (neuer Code ausgerollt), werden nur Kits verschoben, die dort fehlen (z. B. eigene Kits);
#      ältere Fassungen gleichnamiger Kits landen in storage/backups/kits-alt-<zeit>/ (außerhalb des Webroots).
#   3. Übergangs-Links themes → kits und public/themes → kits, damit ein älterer Code-Stand bis zum Deploy weiterläuft.
#      Der neue Kern braucht sie nicht (Rückfall in Core\Kit, alte Adressen /themes/… leitet public/index.php um).
#   4. php bin/console cache:clear --all und health
#
# Danach (bzw. zusätzlich) die öffentlichen Dateien nach /assets/ umstellen: php bin/console assets:migrate (Core\PublicPaths).
# Bei Deploys mit Releases (deploy/deploy.sh) ist nichts zu tun: jedes Release enthält kits/ aus dem Repository.
# Nötig ist das Skript nur für Installationen, die Code direkt in den Ordner hochladen (z. B. studio.klxm.de, APP_DIR).
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail
cd "$(dirname "$0")/.."

DRY=0; LINKS=1; MODE=""; DIR=""; TARGET=""
for a in "$@"; do
  case "$a" in
    --dry-run) DRY=1 ;;
    --no-symlink) LINKS=0 ;;
    --local) MODE=local ;;
    -*) echo "Unbekannte Option: $a"; exit 1 ;;
    *) if [[ "$MODE" == "local" ]]; then DIR="$a"; else TARGET="$a"; fi ;;
  esac
done
[[ -z "$MODE" && -z "$TARGET" ]] && { echo "Aufruf: deploy/migrate-kits.sh --local [ordner] | <ziel> [--dry-run] [--no-symlink]"; exit 1; }

# Der eigentliche Ablauf – läuft lokal oder per SSH auf dem Server (APP, PHP, DRY, LINKS als Variablen)
read -r -d '' SCRIPT <<'SH' || true
set -euo pipefail
cd "$APP"
run() { if [ "$DRY" = 1 ]; then echo "  (dry-run) $*"; else eval "$@"; fi; }
[ -f app/bootstrap.php ] || { echo "Keine Installation in $APP"; exit 1; }
echo "▸ Installation: $APP"
TS=$(date +%Y%m%d-%H%M%S)
SAVE=""
[ -d themes ] && [ ! -L themes ] && SAVE="$SAVE themes"
[ -d public/themes ] && [ ! -L public/themes ] && SAVE="$SAVE public/themes"
if [ -n "$SAVE" ]; then
  echo "▸ Sicherung: storage/backups/themes-$TS.tar.gz ($SAVE)"
  run "mkdir -p storage/backups && tar -czf storage/backups/themes-$TS.tar.gz --exclude='*/node_modules' $SAVE"
fi
move() {   # move <alt> <neu>: ganzen Ordner verschieben oder – wenn <neu> schon existiert – fehlende Unterordner
  local old="$1" new="$2"
  [ -d "$old" ] && [ ! -L "$old" ] || return 0
  if [ ! -e "$new" ]; then
    echo "▸ $old → $new"; run "mv '$old' '$new'"
  else
    local alt="storage/backups/kits-alt-$TS/$(echo "$old" | tr '/' '-')"   # außerhalb des Webroots
    for d in "$old"/*/; do
      d="${d%/}"; [ -d "$d" ] || continue; n=$(basename "$d")
      if [ -e "$new/$n" ]; then echo "  $d: liegt schon in $new/ – alte Fassung nach $alt/"; run "mkdir -p '$alt' && mv '$d' '$alt/'";
      else echo "▸ $d → $new/$n"; run "mv '$d' '$new/$n'"; fi
    done
    run "rmdir '$old' 2>/dev/null || true"
  fi
}
move themes kits
move public/themes public/kits
if [ "$LINKS" = 1 ]; then
  [ -e themes ] || { echo "▸ Übergangs-Link themes → kits"; run "ln -s kits themes"; }
  # Nur solange public/kits noch existiert (vor bin/console assets:migrate) – danach gehören Kit-Assets nach public/assets/kits
  [ -e public/themes ] || [ ! -d public/kits ] || { echo "▸ Übergangs-Link public/themes → kits"; run "ln -s kits public/themes"; }
fi
if [ "$DRY" = 1 ]; then echo "(dry-run) cache:clear --all, health"; exit 0; fi
$PHP bin/console cache:clear --all >/dev/null && echo "▸ Seiten-Cache geleert"
$PHP bin/console health --all | grep -E '✗|!|FEHLER|OK' || true
echo "✓ Kits liegen unter kits/"
[ -d public/kits ] || [ -d public/extensions ] || [ -d public/fonts ] && echo "Hinweis: öffentliche Dateien noch ganz oben in public/ – nächster Schritt: $PHP bin/console assets:migrate (siehe deploy/README.md)" || true
SH

if [[ "$MODE" == "local" ]]; then
  APP="$(cd "${DIR:-.}" && pwd)" PHP="${PHP:-php}" DRY="$DRY" LINKS="$LINKS" bash -c "$SCRIPT"
else
  ENVFILE="deploy/targets/$TARGET.env"
  [[ -f "$ENVFILE" ]] || { echo "Fehlt: $ENVFILE"; exit 1; }
  # shellcheck disable=SC1090
  source "$ENVFILE"
  APP="${APP_DIR:-$BASE/current}"; SSH="${SSH_CMD:-ssh}"
  $SSH ${SSH_OPTS:-} "$SSH_TARGET" "APP='$APP' PHP='${PHP:-php}' DRY='$DRY' LINKS='$LINKS' bash -s" <<< "$SCRIPT"
fi
