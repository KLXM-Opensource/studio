#!/usr/bin/env python3
"""
Narration CANDIDATE – Chatterbox Multilingual TTS (Resemble AI, MIT), fully local. Dev tool only, nothing is shipped.

Same JSON-lines protocol as narrate.py, so tools/tutorials/speech.mjs can switch engines (TUT_TTS=chatterbox):
  stdin : {"id": 1, "lang": "de", "text": "…", "out": "/abs/path.wav", "seed": 1}
  stdout: {"id": 1, "dur": 3.21, "sr": 24000}        or {"id": 1, "error": "…"}

Voice: the model's BUILT-IN default voice (conds.pt) unless --ref is given. --ref = a German reference recording
(10–20 s, WAV mono, 24 kHz) to clone – ONLY with documented consent of the speaker (own voice) or a recording released
for any use (Thorsten-Voice CC0 subset, see THIRD-PARTY-NOTICES 5.1). The built-in voice is an English speaker and
keeps an English accent in German; a German reference removes it.
Output carries Resemble's PerTh watermark (imperceptible), which Chatterbox applies to every generated clip.

Install (once):  python3.12 -m venv tools/tutorials/.venv-chatterbox && tools/tutorials/.venv-chatterbox/bin/pip install chatterbox-tts==0.1.7
Weights are downloaded on first run into the Hugging Face cache (~/.cache/huggingface, repo ResembleAI/chatterbox).
Device: Apple Silicon MPS when available, else CPU (--device overrides).
"""
import argparse
import json
import sys
import wave

import numpy as np
import torch


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument('--device', default='')
    ap.add_argument('--cfg', type=float, default=0.0, help='cfg_weight (0 = no accent transfer from the built-in voice)')
    ap.add_argument('--exaggeration', type=float, default=0.5)
    ap.add_argument('--temperature', type=float, default=0.7)
    ap.add_argument('--ref', default='', help='reference WAV to clone (consented / CC0 only)')
    ap.add_argument('--gap', type=float, default=0.3)  # accepted for CLI compatibility with narrate.py
    ap.add_argument('--voice', action='append', default=[])
    ap.add_argument('--length', action='append', default=[])
    a = ap.parse_args()

    from chatterbox.mtl_tts import ChatterboxMultilingualTTS

    device = a.device or ('mps' if torch.backends.mps.is_available() else 'cpu')
    model = ChatterboxMultilingualTTS.from_pretrained(device=device)
    sr = model.sr
    if a.ref:
        model.prepare_conditionals(a.ref, exaggeration=a.exaggeration)
    print(json.dumps({'ready': True, 'device': device, 'sr': sr, 'ref': a.ref or None}), flush=True)

    for line in sys.stdin:
        line = line.strip()
        if not line:
            continue
        req = json.loads(line)
        try:
            torch.manual_seed(int(req.get('seed', 1)))
            wav = model.generate(req['text'], language_id=req['lang'], exaggeration=a.exaggeration,
                                 cfg_weight=a.cfg, temperature=a.temperature)
            audio = wav.squeeze(0).numpy().astype(np.float32)
            audio = np.clip(audio, -1.0, 1.0)
            pcm = (audio * 32767).astype(np.int16)
            # Trim silence at both ends (threshold ~ -40 dBFS), keep 60 ms
            loud = np.flatnonzero(np.abs(pcm.astype(np.int32)) > 330)
            if loud.size:
                pad = int(sr * 0.06)
                pcm = pcm[max(0, loud[0] - pad): min(pcm.size, loud[-1] + pad)]
            with wave.open(req['out'], 'wb') as w:
                w.setnchannels(1)
                w.setsampwidth(2)
                w.setframerate(sr)
                w.writeframes(pcm.tobytes())
            print(json.dumps({'id': req['id'], 'dur': pcm.size / sr, 'sr': sr}), flush=True)
        except Exception as e:  # report and keep serving
            print(json.dumps({'id': req.get('id'), 'error': f'{type(e).__name__}: {e}'}), flush=True)


if __name__ == '__main__':
    main()
