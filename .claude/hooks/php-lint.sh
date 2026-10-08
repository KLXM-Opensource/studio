#!/usr/bin/env bash
# PostToolUse (Edit|Write): geänderte PHP-Datei sofort mit php -l prüfen; Fehler gehen an Claude zurück.
f=$(jq -r '.tool_input.file_path // .tool_response.filePath // ""')
[[ "$f" == *.php && -f "$f" ]] || exit 0
out=$(php -l "$f" 2>&1) && exit 0
jq -n --arg r "PHP-Syntaxfehler: $out" '{decision:"block",reason:$r}'
exit 0
