#!/bin/sh
# Piper-Stimmen für die Tutorial-Sprecher laden (nicht im Repository, nicht unter public/).
# Quelle: https://huggingface.co/rhasspy/piper-voices (MIT), feste Revision; Prüfung gegen voices/SHA256SUMS.
#   sh tools/tutorials/fetch-voices.sh
# Lizenzen der Stimmen: THIRD-PARTY-NOTICES.md (Abschnitt „Tutorial narration“).
set -eu
cd "$(dirname "$0")/voices"
REV=c10ece1aade47bb51c153c893d14e5bf8e5b7117
BASE="https://huggingface.co/rhasspy/piper-voices/resolve/$REV"
for p in de/de_DE/eva_k/x_low/de_DE-eva_k-x_low en/en_US/ljspeech/high/en_US-ljspeech-high; do
  n=$(basename "$p")
  for ext in onnx onnx.json; do [ -f "$n.$ext" ] || curl -fL --retry 3 -o "$n.$ext" "$BASE/$p.$ext"; done
  [ -f "$n.MODEL_CARD" ] || curl -fsL -o "$n.MODEL_CARD" "$BASE/$(dirname "$p")/MODEL_CARD"
done
shasum -a 256 -c SHA256SUMS
