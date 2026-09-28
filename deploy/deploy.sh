#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Deploy mit Releases und atomarem Umschalten (Plesk oder jeder SSH-Server).
#
#   deploy/deploy.sh staging                 Stand bauen, hochladen, migrieren, prüfen, umschalten
#   deploy/deploy.sh production              dito (vorher automatische Sicherung aller Websites)
#   deploy/deploy.sh production --rollback   auf das vorherige Release zurückschalten
#
# Ziel-Konfiguration: deploy/targets/<ziel>.env (Vorlage: deploy/targets/example.env)
# Aufbau auf dem Server (<BASE> = z. B. /var/www/vhosts/kunde.de):
#   <BASE>/releases/<zeit>-<rev>/   vollständiger Code-Stand (inkl. vendor, gebauter Assets)
#   <BASE>/shared/                  bleibt bei jedem Deploy: config/config.local.php, config/sites/,
#                                   storage/ (inkl. storage/shared = geteilte Datentabellen, storage/pools),
#                                   public/media/, public/sites/, public/pools/ (Uploads) und public/fonts/
#                                   (installierte Schriften – im Release verlinkt als public/assets/fonts/installed)
#   <BASE>/current -> releases/…    Dokumentstamm in Plesk: current/public
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail
cd "$(dirname "$0")/.."

TARGET="${1:-}"; MODE="${2:-}"
[[ -z "$TARGET" ]] && { echo "Aufruf: deploy/deploy.sh <staging|production> [--rollback]"; exit 1; }
ENVFILE="deploy/targets/$TARGET.env"
[[ -f "$ENVFILE" ]] || { echo "Fehlt: $ENVFILE (Vorlage: deploy/targets/example.env)"; exit 1; }
# shellcheck disable=SC1090
source "$ENVFILE"
: "${SSH_TARGET:?SSH_TARGET fehlt}" "${BASE:?BASE fehlt}" "${URL:?URL fehlt}"
PHP="${PHP:-php}"; KEEP="${KEEP:-5}"; SITES="${SITES:---all}"
# SSH_TARGET="local" = Ziel auf demselben Rechner (Tests, Server ohne CI)
if [[ "$SSH_TARGET" == "local" ]]; then
  remote() { bash -c "set -euo pipefail; cd '$BASE'; $*"; }; DEST="$BASE"
else
  remote() { ssh ${SSH_OPTS:-} "$SSH_TARGET" "set -euo pipefail; cd '$BASE'; $*"; }; DEST="$SSH_TARGET:$BASE"
fi

# ── Rollback ────────────────────────────────────────────────────────────────
if [[ "$MODE" == "--rollback" ]]; then
  remote "cur=\$(readlink current); prev=\$(ls -1d releases/* | sort | grep -B1 \"\$cur\" | head -1);
          [ \"\$prev\" != \"\$cur\" ] || { echo 'Kein älteres Release.'; exit 1; };
          ln -sfn \"\$prev\" current.tmp && { mv -Tf current.tmp current 2>/dev/null || { rm -f current && mv current.tmp current; }; }; echo \"Zurück auf \$prev\""
  exit 0
fi

REV="$(git rev-parse --short HEAD 2>/dev/null || echo local)"
REL="releases/$(date +%Y%m%d-%H%M%S)-$REV"
echo "▸ Ziel: $TARGET ($SSH_TARGET:$BASE) · Release $REL"

# ── 1. Bauen (lokal bzw. im CI) ─────────────────────────────────────────────
if [[ "${SKIP_BUILD:-0}" != "1" ]]; then
  echo "▸ Build"
  composer install --no-dev --optimize-autoloader --no-interaction --quiet
  (cd tools && pnpm install --frozen-lockfile --silent && pnpm run build >/dev/null)
fi

# ── 2. Hochladen ────────────────────────────────────────────────────────────
echo "▸ Upload"
remote "mkdir -p releases shared/config/sites shared/storage shared/storage/shared shared/public/media shared/public/sites shared/public/pools shared/public/fonts"
RSH=(); [[ "$SSH_TARGET" != "local" ]] && RSH=(-e "ssh ${SSH_OPTS:-}")
rsync -az --delete ${RSYNC_OPTS:-} ${RSH[@]+"${RSH[@]}"} \
  --exclude '.git' --exclude '.github' --exclude 'deploy/targets/*.env' \
  --exclude '/tools' --exclude 'kits/*/node_modules' --exclude 'themes/*/node_modules' --exclude 'extensions/*/node_modules' \
  --exclude '/storage' --exclude '/config/config.local.php' --exclude '/config/sites' \
  --exclude '/public/media' --exclude '/public/sites' --exclude '/public/pools' --exclude '/public/fonts' --exclude '/public/assets/fonts/installed' \
  --exclude '/public/assets/tutorials' --exclude '/public/assets/trailer' \
  ./ "$DEST/$REL/"

# ── 3. Gemeinsame Daten verlinken ───────────────────────────────────────────
remote "cd '$REL';
  ln -sfn '$BASE/shared/storage' storage;
  ln -sfn '$BASE/shared/config/sites' config/sites;
  if [ ! -f '$BASE/shared/config/config.local.php' ]; then $PHP bin/console setup:token >/dev/null; mv config/config.local.php '$BASE/shared/config/config.local.php'; echo '  config.local.php neu angelegt (shared/config)'; fi;
  ln -sfn '$BASE/shared/config/config.local.php' config/config.local.php;
  ln -sfn '$BASE/shared/public/media' public/media;
  ln -sfn '$BASE/shared/public/sites' public/sites;
  ln -sfn '$BASE/shared/public/pools' public/pools;
  mkdir -p public/assets/fonts; ln -sfn '$BASE/shared/public/fonts' public/assets/fonts/installed"

# ── 4. Sichern (production), migrieren, prüfen – erst dann umschalten ───────
if [[ "$TARGET" == "production" && "${SKIP_BACKUP:-0}" != "1" ]]; then
  echo "▸ Sicherung"; remote "cd '$REL' && $PHP bin/console site:backup $SITES >/dev/null && $PHP bin/console pool:backup --all >/dev/null && $PHP bin/console shared:backup --all >/dev/null"
fi
echo "▸ Migration & Prüfung"
remote "cd '$REL' && $PHP bin/console migrate $SITES && $PHP bin/console extensions:publish >/dev/null && $PHP bin/console health $SITES" \
  || { echo "✗ Prüfung fehlgeschlagen – Live-Stand bleibt unverändert ($REL wird entfernt)."; remote "rm -rf '$REL'"; exit 1; }

# ── 5. Umschalten (atomar) + Caches ─────────────────────────────────────────
echo "▸ Umschalten"
remote "ln -sfn '$REL' current.tmp && { mv -Tf current.tmp current 2>/dev/null || { rm -f current && mv current.tmp current; }; } && cd current && $PHP bin/console cache:clear --all >/dev/null"
# PHP-FPM neu laden, damit OPcache/Realpath-Cache sofort den neuen Stand nutzen (optional, z. B. RELOAD_CMD="sudo systemctl reload plesk-php84-fpm")
[[ -n "${RELOAD_CMD:-}" ]] && remote "$RELOAD_CMD" || true

# ── 6. Rauchtest + Aufräumen ────────────────────────────────────────────────
code=$(curl -s -o /dev/null -w '%{http_code}' ${CURL_OPTS:-} "$URL/health" || true)
if [[ "$code" != "200" ]]; then
  echo "✗ $URL/health antwortet $code – Rollback."; "$0" "$TARGET" --rollback; exit 1
fi
remote "ls -1dt releases/* | tail -n +$((KEEP + 1)) | while read -r d; do rm -rf \"\$d\"; done"
echo "✓ $TARGET ist auf $REL ($URL)"
