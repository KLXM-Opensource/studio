/**
 * Sprecher (Voice-over) für die Tutorial-Videos – Piper TTS, lokal (tools/tutorials/narrate.py).
 *
 * Gesprochen wird genau der Untertitel eines Schritts (t.step(de, en)); speakable() liest nur Zeichen vor,
 * die eine Sprachausgabe sonst verschluckt oder falsch ausspricht (⌘K → „Befehlstaste K“, › → Pause, z. B. → „zum Beispiel“ …).
 * WAVs je Schritt liegen im Cache tools/tutorials/.narration/ (Schlüssel = Stimme + Tempo + gesprochener Text).
 *
 * Umgebung: PIPER_PYTHON (Python mit piper-tts; Standard: pipx-venv), TUT_VOICE_DE / TUT_VOICE_EN (Modell unter
 * tools/tutorials/voices/, ohne .onnx), TUT_RATE_DE / TUT_RATE_EN (length_scale, > 1 = langsamer).
 *
 * KANDIDAT – TUT_TTS=chatterbox: Chatterbox Multilingual (Resemble AI, MIT) über tools/tutorials/narrate_chatterbox.py,
 * eingebaute Standardstimme (kein Klonen), Python aus tools/tutorials/.venv-chatterbox (CHATTERBOX_PYTHON). Dann gilt
 * zusätzlich das Aussprache-Lexikon tools/tutorials/lexicon.de.json (nur gesprochener Text, Untertitel unverändert;
 * mit TUT_LEXICON=1 auch für Piper). Chatterbox versieht jede Ausgabe mit dem unhörbaren PerTh-Wasserzeichen.
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import readline from 'node:readline';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const DIR = path.dirname(fileURLToPath(import.meta.url));
export const ENGINE = process.env.TUT_TTS === 'chatterbox' ? 'chatterbox' : 'piper';
/** Chatterbox-Parameter (CB_CFG = cfg_weight, CB_TEMP = temperature, CB_EXAG = exaggeration) – Teil des Stimmen-/Cache-Namens */
// CB_REF = Referenzaufnahme zum Klonen (nur mit Einwilligung bzw. CC0, z. B. tools/tutorials/voices/ref-thorsten.wav oder ref-own.wav)
export const CB = { cfg: process.env.CB_CFG || '0.5', temp: process.env.CB_TEMP || '0.8', exag: process.env.CB_EXAG || '0.5', ref: process.env.CB_REF ? path.resolve(process.env.CB_REF) : '' };
export const VOICES = ENGINE === 'chatterbox'
  ? { de: `chatterbox-mtl-${CB.ref ? path.basename(CB.ref, '.wav') : 'default'}-c${CB.cfg}-t${CB.temp}-e${CB.exag}` }   // nur Deutsch (Trailer)
  : {
    de: process.env.TUT_VOICE_DE || 'de_DE-eva_k-x_low',
    en: process.env.TUT_VOICE_EN || 'en_US-ljspeech-high',
  };
export const RATES = { de: parseFloat(process.env.TUT_RATE_DE || '1.0'), en: parseFloat(process.env.TUT_RATE_EN || '1.0') };
const CACHE = path.join(DIR, '.narration');
const CB_SEED = parseInt(process.env.TUT_TTS_SEED || '1', 10);   // Chatterbox würfelt: fester Seed = reproduzierbar
/** Ausgewählte Takes je gesprochenem Satz (Seed), geprüft per Whisper: node tools/tutorials/voice-check.mjs --pick=N */
export const PICKS_FILE = path.join(DIR, 'chatterbox-picks.json');
// je Stimme (Name wie im Cache): { "<stimme>": { "<gesprochener Satz>": { seed, trim, … } } }
export const readPicks = () => (fs.existsSync(PICKS_FILE) ? JSON.parse(fs.readFileSync(PICKS_FILE, 'utf8')) : {});
const PICKS = ENGINE === 'chatterbox' ? readPicks()[VOICES.de] || {} : {};
const CB_PY = process.env.CHATTERBOX_PYTHON || path.join(DIR, '.venv-chatterbox/bin/python');
const PY = process.env.PIPER_PYTHON || [path.join(os.homedir(), '.local/pipx/venvs/piper-tts/bin/python'), 'python3'].find((p) => p === 'python3' || fs.existsSync(p));

// ---------------------------------------------------------------- Text → gesprochener Text
const W = {
  de: { cmd: 'Befehlstaste', ctrl: 'Steuerung', plus: 'plus', enter: 'Eingabetaste', slash: 'Schrägstrich', dot: 'Punkt', and: 'und', or: 'oder', eq: 'gleich', each: 'zu je', arrows: 'den Pfeil-Knöpfen', esc: 'Escape' },
  en: { cmd: 'Command', ctrl: 'Control', plus: 'plus', enter: 'Enter', slash: 'slash', dot: 'dot', and: 'and', or: 'or', eq: 'equals', each: 'of', arrows: 'the arrow buttons', esc: 'Escape' },
};
/** Buchstabieren, wo die Stimme sonst ein Wort daraus macht */
const SPELL = ['KLXM', 'KI', 'AI', 'SEO', 'API', 'MCP', 'SSO', 'PDF', 'PDFs', 'CSS', 'EN', 'AA', 'AAA', 'SRT', 'VTT', 'PHP', 'CPP', 'SH', 'CC', '2FA'];
const spell = (w) => (w === 'PDFs' ? 'P D F s' : w === '2FA' ? '2 F A' : w === 'AAA' ? 'triple A' : w === 'AA' ? 'double A' : w.split('').join(' '));

/** Aussprache-Lexikon (lexicon.<lang>.json): Begriff → Umschrift, ganze Wörter, längere Begriffe zuerst */
const LEX = {};
function lexicon(lang) {
  if (lang in LEX) return LEX[lang];
  const f = path.join(DIR, `lexicon.${lang}.json`);
  const terms = fs.existsSync(f) ? JSON.parse(fs.readFileSync(f, 'utf8')).terms || {} : {};
  const keys = Object.keys(terms).sort((a, b) => b.length - a.length);
  const esc = (k) => k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  return (LEX[lang] = keys.length ? { terms, re: new RegExp(`(?<![\\p{L}\\p{N}-])(${keys.map(esc).join('|')})(?![\\p{L}\\p{N}-])`, 'gu') } : null);
}
export const useLexicon = () => ENGINE === 'chatterbox' || process.env.TUT_LEXICON === '1';
export function applyLexicon(s, lang) {
  const lx = lexicon(lang);
  return lx ? s.replace(lx.re, (m) => lx.terms[m]) : s;
}

export function speakable(text, lang) {
  const w = W[lang] || W.en;
  let s = ' ' + text.replace(/[„“”"«»]/g, '') + ' ';
  // Tastenkürzel
  s = s.replace(/⌘\s?([A-Z])/g, `${w.cmd} $1`).replace(/\b(Strg|Ctrl)\+([A-Z])/g, `${w.ctrl} $2`);
  s = s.replace(/↵/g, w.enter).replace(/\bEsc\b/g, w.esc).replace(/⇤\s*⇥/g, w.arrows);
  // Abkürzungen
  if (lang === 'de') s = s.replace(/z\. B\./g, 'zum Beispiel').replace(/bzw\./g, 'beziehungsweise').replace(/\sà\s/g, ` ${w.each} `).replace(/\bJa\/Nein\b/g, 'Ja oder Nein').replace(/Vorher\/Nachher/g, 'Vorher und Nachher');
  else s = s.replace(/\be\.g\./g, 'for example').replace(/\byes\/no\b/g, 'yes or no').replace(/before\/after/g, 'before and after');
  s = s.replace(/\bAA\/AAA\b/g, `AA ${w.or} AAA`);
  // Dateien, Pfade, Befehle: theme.php, whisper.cpp, deploy.sh, .vtt/.srt, config/sites/{website}.php, /admin, site:create
  s = s.replace(/\{(\w+)\}/g, '$1');
  s = s.replace(/\(\.(vtt)\/\.(srt)\)/gi, (_, a, b) => `(${a.toUpperCase()} ${w.or} ${b.toUpperCase()})`);
  s = s.replace(/\b([\w-]+)\.(php|cpp|sh)\b/g, (_, n, e) => `${n} ${w.dot} ${e.toUpperCase()}`);
  s = s.replace(/\b([a-z]+):([a-z]+)\b/g, '$1 $2');
  s = s.replace(/(^|[\s(])\/(?=\w)/g, `$1${w.slash} `).replace(/(\w)\/(?=\w)/g, `$1 ${w.slash} `).replace(/(\w)\/(?=[\s),.])/g, `$1 ${w.slash}`);
  // Zeichen
  s = s.replace(/\s\+\s?(?=\S)/g, ` ${w.plus} `).replace(/(\w)\+(\w)/g, `$1 ${w.plus} $2`);
  s = s.replace(/\s=\s/g, ` ${w.eq} `).replace(/&/g, w.and);
  s = s.replace(/[☆✓✦▾⋯]/g, ' ');
  s = s.replace(/\s*›\s*/g, ', ').replace(/\s*→\s*/g, ', ').replace(/\s[–—]\s/g, ', ').replace(/\s*…\s*/g, ' ');
  s = s.replace(/\s*\(\s*/g, ', ').replace(/\s*\)\s*(?=[.,:;!?])/g, '').replace(/\s*\)\s*/g, ', ');
  // Aussprache-Lexikon (Kandidat): vor dem Buchstabieren, damit Umschriften Vorrang haben
  if (useLexicon()) s = applyLexicon(s, lang);
  // Produktnamen: „KLXM Ai“ wie „K L X M A I“ sprechen
  s = s.replace(/\bKLXM Ai\b/g, 'KLXM AI');
  // Akronyme buchstabieren
  s = s.replace(/(?<![\w-])(KLXM|KI|AI|SEO|API|MCP|SSO|PDFs|PDF|CSS|EN|AAA|AA|SRT|VTT|PHP|CPP|SH|2FA)(?![\w])/g, (m) => (SPELL.includes(m) ? spell(m) : m));
  // Aufräumen
  s = s.replace(/\s+,/g, ',').replace(/,\s*,/g, ',').replace(/:\s*,/g, ':').replace(/,\s*([.:;!?])/g, '$1').replace(/\s{2,}/g, ' ').trim();
  s = s.replace(/^[,\s]+/, '').replace(/,$/, '.');
  if (!/[.!?]$/.test(s)) s += '.';
  return s;
}

// ---------------------------------------------------------------- WAV
export function readWav(file) {
  const b = fs.readFileSync(file);
  let o = 12, sr = 22050, data = null;
  while (o + 8 <= b.length) {
    const id = b.toString('ascii', o, o + 4), len = b.readUInt32LE(o + 4);
    if (id === 'fmt ') sr = b.readUInt32LE(o + 12);
    if (id === 'data') { data = b.subarray(o + 8, o + 8 + len); break; }
    o += 8 + len + (len & 1);
  }
  const pcm = new Int16Array(data.buffer.slice(data.byteOffset, data.byteOffset + data.length));
  return { sr, pcm, dur: pcm.length / sr };
}
export function writeWav(file, pcm, sr) {
  const h = Buffer.alloc(44);
  h.write('RIFF', 0); h.writeUInt32LE(36 + pcm.length * 2, 4); h.write('WAVE', 8); h.write('fmt ', 12);
  h.writeUInt32LE(16, 16); h.writeUInt16LE(1, 20); h.writeUInt16LE(1, 22); h.writeUInt32LE(sr, 24); h.writeUInt32LE(sr * 2, 28);
  h.writeUInt16LE(2, 32); h.writeUInt16LE(16, 34); h.write('data', 36); h.writeUInt32LE(pcm.length * 2, 40);
  fs.writeFileSync(file, Buffer.concat([h, Buffer.from(pcm.buffer, pcm.byteOffset, pcm.byteLength)]));
}

/** WAV nach `sec` Sekunden abschneiden (20 ms Ausblenden) → neue Dauer */
export function trimWav(src, dst, sec) {
  const { sr, pcm } = readWav(src);
  const n = Math.min(pcm.length, Math.round(sec * sr)), out = pcm.slice(0, n), f = Math.min(n, Math.round(sr * 0.02));
  for (let i = 0; i < f; i++) out[n - f + i] = Math.round(out[n - f + i] * (1 - i / f));
  writeWav(dst, out, sr);
  return n / sr;
}

/** Tonspur: Schritt-WAVs an ihren Zeitstempeln (Sekunden im fertigen Video), dazwischen Stille; Länge = Video */
export function buildTrack(segments, total, out) {
  const sr = segments.length ? readWav(segments[0].file).sr : 16000;
  const pcm = new Int16Array(Math.ceil(total * sr));
  const placed = [];
  for (const s of segments) {
    const w = readWav(s.file);
    if (w.sr !== sr) throw new Error('Unterschiedliche Abtastraten in einer Tonspur');
    const at = Math.round(s.t * sr);
    pcm.set(w.pcm.subarray(0, Math.max(0, pcm.length - at)), at);
    placed.push({ t: s.t, dur: w.dur });
  }
  writeWav(out, pcm, sr);
  return placed;
}

// ---------------------------------------------------------------- Piper-Prozess + Cache
export class Narrator {
  constructor() {
    this.engine = ENGINE;
    if (ENGINE === 'chatterbox') {
      if (!fs.existsSync(CB_PY)) throw new Error('Chatterbox fehlt: tools/tutorials/.venv-chatterbox (siehe narrate_chatterbox.py)');
      this.models = {}; this.proc = null; this.pending = new Map(); this.seq = 0;
      return;
    }
    this.models = Object.fromEntries(Object.entries(VOICES).map(([l, v]) => [l, path.join(DIR, 'voices', v + '.onnx')]));
    for (const [l, m] of Object.entries(this.models)) if (!fs.existsSync(m)) throw new Error(`Stimme fehlt (${l}): ${path.relative(DIR, m)} – sh tools/tutorials/fetch-voices.sh`);
    this.proc = null; this.pending = new Map(); this.seq = 0;
  }
  seed(text, seed) { return seed ?? PICKS[text]?.seed ?? CB_SEED; }
  key(lang, text, seed) { return crypto.createHash('sha1').update([VOICES[lang], ENGINE === 'chatterbox' ? this.seed(text, seed) : RATES[lang], text].join('\n')).digest('hex').slice(0, 16); }
  file(lang, text, seed) { return path.join(CACHE, VOICES[lang], this.key(lang, text, seed) + '.wav'); }
  async start() {
    if (this.proc) return this.ready;
    const cb = ENGINE === 'chatterbox';
    const args = [path.join(DIR, cb ? 'narrate_chatterbox.py' : 'narrate.py'), '--gap', '0.3'];
    if (cb) args.push('--cfg', CB.cfg, '--temperature', CB.temp, '--exaggeration', CB.exag, ...(CB.ref ? ['--ref', CB.ref] : []));
    for (const [l, m] of Object.entries(this.models)) args.push('--voice', `${l}=${m}`, '--length', `${l}=${RATES[l]}`);
    // Chatterbox: Fortschrittsbalken/Warnungen nicht durchreichen (stderr ignorieren), Fehler kommen als JSON
    this.proc = spawn(cb ? CB_PY : PY, args, { stdio: ['pipe', 'pipe', cb && !process.env.TUT_TTS_DEBUG ? 'ignore' : 'inherit'] });
    this.proc.on('exit', (c) => { for (const p of this.pending.values()) p.reject(new Error('Piper beendet (' + c + ')')); this.pending.clear(); this.proc = null; });
    this.ready = new Promise((res, rej) => {
      const rl = readline.createInterface({ input: this.proc.stdout });
      rl.on('line', (l) => {
        let m; try { m = JSON.parse(l); } catch { return; }
        if (m.ready) return res();
        const p = this.pending.get(m.id); if (!p) return;
        this.pending.delete(m.id);
        m.error ? p.reject(new Error(m.error)) : p.resolve(m);
      });
      this.proc.on('error', rej);
    });
    return this.ready;
  }
  /** → { file, dur, spoken } (aus dem Cache oder neu erzeugt) */
  /**
   * seed: nur Chatterbox – bestimmten Take erzwingen (sonst chatterbox-picks.json bzw. TUT_TTS_SEED).
   * trim: Schnitt in Sekunden (Standard: aus chatterbox-picks.json, wenn der Take dort gewählt ist; null = ungeschnitten).
   */
  async say(lang, caption, seed, trim) {
    const spoken = speakable(caption, lang);
    const pick = PICKS[spoken];
    if (trim === undefined) trim = pick?.trim && this.seed(spoken, seed) === pick.seed ? pick.trim : null;
    const r = await this.sayRaw(lang, spoken, seed);
    if (!trim) return r;
    const cut = r.file.replace(/\.wav$/, `.t${Math.round(trim * 1000)}.wav`);
    const dur = fs.existsSync(cut) ? readWav(cut).dur : trimWav(r.file, cut, trim);
    return { file: cut, dur, spoken };
  }
  async sayRaw(lang, spoken, seed) {
    const file = this.file(lang, spoken, seed);
    if (fs.existsSync(file)) return { file, dur: readWav(file).dur, spoken };
    fs.mkdirSync(path.dirname(file), { recursive: true });
    await this.start();
    const id = ++this.seq;
    const r = await new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      this.proc.stdin.write(JSON.stringify({ id, lang, text: spoken, out: file + '.tmp', seed: this.seed(spoken, seed) }) + '\n');
    });
    fs.renameSync(file + '.tmp', file);
    return { file, dur: r.dur, spoken };
  }
  /** Untertitel aus flows.mjs vorab erzeugen (nur feste Texte), damit die Aufnahme nicht auf Piper wartet */
  async warm(src) {
    const re = /t\.step\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'((?:[^'\\]|\\.)*)'/g;
    const jobs = [];
    for (let m; (m = re.exec(src));) for (const [lang, txt] of [['de', m[1]], ['en', m[2]]]) jobs.push(this.say(lang, txt.replace(/\\'/g, "'")));
    return (await Promise.all(jobs)).length;
  }
  stop() { this.proc?.stdin.end(); }
}
