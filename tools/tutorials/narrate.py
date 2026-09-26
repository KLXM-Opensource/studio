#!/usr/bin/env python3
"""
Narration for the tutorial videos (tools/tutorials/record.mjs) – Piper TTS, fully local.

Runs as a small JSON-lines server so each voice is loaded only once:
  stdin : {"id": 1, "lang": "de", "text": "…", "out": "/abs/path.wav"}
  stdout: {"id": 1, "dur": 3.21, "sr": 16000}        or {"id": 1, "error": "…"}

Voices (ONNX + .onnx.json) come from --voice LANG=/path/model.onnx, speaking rate from --length LANG=1.0.
Needs the Python that has `piper-tts` installed (e.g. the pipx venv: ~/.local/pipx/venvs/piper-tts/bin/python).
Sentences are joined with --gap seconds of silence; leading/trailing silence is trimmed to 60 ms.
"""
import argparse
import json
import sys
import unicodedata
import wave

import numpy as np
from piper import PiperVoice
from piper.config import SynthesisConfig


def recompose(voice: PiperVoice) -> None:
    """piper-tts >= 1.3 splits phonemes into NFD (ç → c + U+0327). Older models (e.g. eva_k) only know the
    composed symbol – without this the German ich-sound becomes a “c”. Merge a combining mark into the
    previous phoneme whenever the model knows the composed form but not the mark."""
    known = voice.config.phoneme_id_map
    original = voice.phonemize

    def phonemize(text):
        out = []
        for sentence in original(text):
            merged = []
            for p in sentence:
                composed = unicodedata.normalize('NFC', merged[-1] + p) if merged else None
                if p not in known and composed in known:
                    merged[-1] = composed
                else:
                    merged.append(p)
            out.append(merged)
        return out

    voice.phonemize = phonemize


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument('--voice', action='append', default=[], help='LANG=/path/model.onnx')
    ap.add_argument('--length', action='append', default=[], help='LANG=length_scale')
    ap.add_argument('--gap', type=float, default=0.3, help='silence between sentences (s)')
    a = ap.parse_args()
    voices = {k: PiperVoice.load(v) for k, v in (x.split('=', 1) for x in a.voice)}
    for v in voices.values():
        recompose(v)
    lengths = {k: float(v) for k, v in (x.split('=', 1) for x in a.length)}
    print(json.dumps({'ready': True, 'voices': {k: v.config.sample_rate for k, v in voices.items()}}), flush=True)

    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue
        req = json.loads(line)
        try:
            voice = voices[req['lang']]
            sr = voice.config.sample_rate
            cfg = SynthesisConfig(length_scale=lengths.get(req['lang']))
            gap = np.zeros(int(sr * a.gap), dtype=np.int16)
            parts = []
            for chunk in voice.synthesize(req['text'], syn_config=cfg):
                if parts:
                    parts.append(gap)
                parts.append(chunk.audio_int16_array)
            audio = np.concatenate(parts) if parts else np.zeros(0, dtype=np.int16)
            # Trim silence at both ends (threshold ~ -40 dBFS), keep 60 ms
            loud = np.flatnonzero(np.abs(audio.astype(np.int32)) > 330)
            if loud.size:
                pad = int(sr * 0.06)
                audio = audio[max(0, loud[0] - pad): min(audio.size, loud[-1] + pad)]
            with wave.open(req['out'], 'wb') as w:
                w.setnchannels(1)
                w.setsampwidth(2)
                w.setframerate(sr)
                w.writeframes(audio.tobytes())
            print(json.dumps({'id': req['id'], 'dur': audio.size / sr, 'sr': sr}), flush=True)
        except Exception as e:  # report and keep serving
            print(json.dumps({'id': req.get('id'), 'error': f'{type(e).__name__}: {e}'}), flush=True)


if __name__ == '__main__':
    main()
