#!/usr/bin/env bash
# PreToolUse (Bash): blockiert riskante Server-Befehle. Deploy: deploy/deploy.sh bzw. git archive HEAD mit festen Pfaden.
cmd=$(jq -r '.tool_input.command // ""')
deny() { jq -n --arg r "$1" '{hookSpecificOutput:{hookEventName:"PreToolUse",permissionDecision:"deny",permissionDecisionReason:$r}}'; exit 0; }
[[ "$cmd" =~ (ssh|scp|rsync|sftp)[[:space:]] ]] || exit 0
# Dateilisten aus git status für einen Upload (landen schnell zu viele Dateien auf dem Server)
if [[ "$cmd" =~ git[[:space:]]+status ]] && [[ "$cmd" =~ tar[[:space:]] ]]; then
  deny "Kein Upload aus git-status-Listen. Erst committen, dann deploy/deploy.sh oder git archive HEAD mit festen Pfaden."
fi
# Konfiguration und Laufzeitdaten zum Server
if [[ "$cmd" =~ (tar[[:space:]]+c|rsync|scp) ]] && [[ "$cmd" =~ (config/config\.local\.php|config/sites|[[:space:]]storage/|[[:space:]]config/) ]] \
   && ! [[ "$cmd" =~ git[[:space:]]+archive ]] && ! [[ "$cmd" =~ storage/backups ]]; then
  deny "config/ und storage/ gehen nie auf einen Server (Schlüssel, Datenbanken). Code nur per deploy/deploy.sh."
fi
# Inhalte auf Servern neu aufsetzen
if [[ "$cmd" =~ klxm:seed|site:reset ]]; then
  deny "Kein Seed auf Servern – dort pflegt die Redaktion live. Nur mit ausdrücklichem Auftrag des Nutzers."
fi
exit 0
