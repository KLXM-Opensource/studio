/**
 * Englischer Sprecher (Voice-over) für den Trailer – nur lokal, kein Cloud-Dienst, kein Text verlässt den Rechner.
 *
 * Welche Stimme gilt, steht in EINER Zeile in tools/trailer/voice.json ("voice": "<name>"); die Stimmen selbst sind dort
 * unter "voices" beschrieben (engine chatterbox | piper | say). Wechsel = diese Zeile ändern und
 * `node tools/trailer/trailer.mjs --cut` (nur neu schneiden; Bild wird bei längeren Sätzen gehalten).
 *
 *   chatterbox  Chatterbox Multilingual (Resemble AI, MIT) über tools/tutorials/narrate_chatterbox.py, Sprache "en";
 *               optional "ref" = Referenz zum Klonen (nur CC0/gemeinfrei oder mit Einwilligung). Jede Ausgabe trägt das
 *               unhörbare PerTh-Wasserzeichen von Resemble.
 *   piper       Piper TTS (tools/tutorials/narrate.py), Modell unter tools/tutorials/voices/<model>.onnx
 *   say         macOS-Sprachausgabe (`say -v <name>`), nur als Notlösung
 *
 * Aussprache: tools/trailer/lexicon.en.json (nur der gesprochene Text; Untertitel behalten die echte Schreibweise).
 * Cache: tools/trailer/.work/voice/<stimme>/<hash>.wav (48 kHz mono, Stille an den Enden gekürzt).
 *
 *   node tools/trailer/voice.mjs --samples[=out-dir]   Hörproben der Kandidaten (samples in voice.json) als .m4a
 *   node tools/trailer/voice.mjs --say="Text"          einen Satz mit der gewählten Stimme → Pfad der WAV
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import crypto from 'node:crypto';
import readline from 'node:readline';
import { spawn, execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(DIR, '../..');
const TUT = path.join(ROOT, 'tools/tutorials');
const FFMPEG = process.env.FFMPEG || (fs.existsSync('/opt/homebrew/bin/ffmpeg') ? '/opt/homebrew/bin/ffmpeg' : 'ffmpeg');
const FFPROBE = FFMPEG.replace(/ffmpeg$/, 'ffprobe');
export const CONFIG = JSON.parse(fs.readFileSync(path.join(DIR, 'voice.json'), 'utf8'));
const CACHE = path.join(DIR, '.work/voice');
const SR = 48000;

/** Beschreibung einer Stimme (Name aus voice.json, Standard: die gewählte) */
export function voiceDef(name = process.env.TRAILER_VOICE || CONFIG.voice) {
  const v = CONFIG.voices[name];
  if (!v) throw new Error(`Stimme „${name}“ fehlt in tools/trailer/voice.json`);
  return { name, ...v };
}

// ---------------------------------------------------------------- Text → gesprochener Text
const LEXF = path.join(DIR, 'lexicon.en.json');
const LEX = fs.existsSync(LEXF) ? JSON.parse(fs.readFileSync(LEXF, 'utf8')).terms || {} : {};
const LEXRE = Object.keys(LEX).length
  ? new RegExp(`(?<![\\p{L}\\p{N}-])(${Object.keys(LEX).sort((a, b) => b.length - a.length).map((k) => k.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})(?![\\p{L}\\p{N}-])`, 'gu')
  : null;
/** Untertitel → gesprochener Text: Lexikon (Engine-spezifische Umschrift möglich: { "chatterbox": "…", "piper": "…", "*": "…" }) */
export function speakable(text, engine) {
  let s = text.replace(/[„“”"«»]/g, '').replace(/\s[–—]\s/g, ', ').replace(/\s*…\s*/g, ' ').replace(/\s*·\s*/g, ', ');
  if (LEXRE) s = s.replace(LEXRE, (m) => { const t = LEX[m]; return typeof t === 'string' ? t : (t[engine] ?? t['*'] ?? m); });
  s = s.replace(/\s{2,}/g, ' ').trim();
  if (!/[.!?]$/.test(s)) s += '.';
  return s;
}

// ---------------------------------------------------------------- Hilfen
const dur = (f) => parseFloat(execFileSync(FFPROBE, ['-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', f]).toString());
/** Beliebige Audiodatei → 48 kHz mono 16 bit, Stille an den Enden gekürzt (−45 dB, 60 ms Rest) */
function finish(src, out) {
  const trim = 'silenceremove=start_periods=1:start_threshold=-45dB:start_silence=0.06,areverse,silenceremove=start_periods=1:start_threshold=-45dB:start_silence=0.08,areverse';
  execFileSync(FFMPEG, ['-y', '-v', 'error', '-i', src, '-af', trim, '-ar', String(SR), '-ac', '1', '-c:a', 'pcm_s16le', out]);
}

/** JSON-Zeilen-Server (narrate.py / narrate_chatterbox.py) */
class Server {
  constructor(cmd, args) { Object.assign(this, { cmd, args, proc: null, pending: new Map(), seq: 0 }); }
  start() {
    if (this.proc) return this.ready;
    this.proc = spawn(this.cmd, this.args, { stdio: ['pipe', 'pipe', process.env.TRAILER_TTS_DEBUG ? 'inherit' : 'ignore'] });
    this.proc.on('exit', (c) => { for (const p of this.pending.values()) p.reject(new Error('TTS beendet (' + c + ')')); this.pending.clear(); this.proc = null; });
    this.ready = new Promise((res, rej) => {
      readline.createInterface({ input: this.proc.stdout }).on('line', (l) => {
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
  async run(req) {
    await this.start();
    const id = ++this.seq;
    return new Promise((resolve, reject) => { this.pending.set(id, { resolve, reject }); this.proc.stdin.write(JSON.stringify({ id, ...req }) + '\n'); });
  }
  stop() { this.proc?.stdin.end(); }
}

export class Voice {
  constructor(name) {
    this.def = voiceDef(name);
    this.name = this.def.name;
    this.dir = path.join(CACHE, this.name);
    fs.mkdirSync(this.dir, { recursive: true });
  }
  /** Nachweis für Metadaten/Abspann */
  get credit() { return this.def.credit || this.name; }
  server() {
    if (this.srv) return this.srv;
    const d = this.def;
    if (d.engine === 'chatterbox') {
      const py = process.env.CHATTERBOX_PYTHON || path.join(TUT, '.venv-chatterbox/bin/python');
      if (!fs.existsSync(py)) throw new Error('Chatterbox fehlt: tools/tutorials/.venv-chatterbox (siehe tools/tutorials/narrate_chatterbox.py)');
      const a = [path.join(TUT, 'narrate_chatterbox.py'), '--cfg', String(d.cfg ?? 0.5), '--temperature', String(d.temperature ?? 0.7), '--exaggeration', String(d.exaggeration ?? 0.5)];
      if (d.ref) {
        const ref = path.resolve(ROOT, d.ref);
        // Referenz fehlt: aus einer gemeinfreien Piper-Stimme erzeugen ("refFrom": { "piper": "<modell>", "text": "…" })
        if (!fs.existsSync(ref) && d.refFrom?.piper) {
          const piper = process.env.PIPER_BIN || path.join(os.homedir(), '.local/bin/piper');
          const raw = ref + '.raw.wav';
          execFileSync(piper, ['-m', path.join(TUT, 'voices', d.refFrom.piper + '.onnx'), '-f', raw], { input: d.refFrom.text, stdio: ['pipe', 'ignore', 'ignore'] });
          execFileSync(FFMPEG, ['-y', '-v', 'error', '-i', raw, '-ar', '24000', '-ac', '1', ref]);
          fs.rmSync(raw, { force: true });
        }
        a.push('--ref', ref);
      }
      this.srv = new Server(py, a);
    } else if (d.engine === 'piper') {
      const py = process.env.PIPER_PYTHON || [path.join(os.homedir(), '.local/pipx/venvs/piper-tts/bin/python'), 'python3'].find((p) => p === 'python3' || fs.existsSync(p));
      const model = path.join(TUT, 'voices', d.model + '.onnx');
      if (!fs.existsSync(model)) throw new Error('Piper-Stimme fehlt: ' + path.relative(ROOT, model));
      this.srv = new Server(py, [path.join(TUT, 'narrate.py'), '--voice', `en=${model}`, '--length', `en=${d.length ?? 1}`, '--gap', '0.25']);
    }
    return this.srv;
  }
  key(spoken, seed) { return crypto.createHash('sha1').update(JSON.stringify([this.def, spoken, seed])).digest('hex').slice(0, 16); }
  /** Satz sprechen → { file, dur, spoken } (Cache). seed: nur Chatterbox (Take); Standard aus voice.json "picks" bzw. 1 */
  async say(text, seed) {
    const spoken = speakable(text, this.def.engine);
    seed ??= CONFIG.picks?.[this.name]?.[spoken] ?? this.def.seed ?? 1;
    const file = path.join(this.dir, this.key(spoken, seed) + '.wav');
    if (fs.existsSync(file)) return { file, dur: dur(file), spoken, seed };
    const raw = file + '.raw' + (this.def.engine === 'say' ? '.aiff' : '.wav');
    if (this.def.engine === 'say') {
      execFileSync('say', ['-v', this.def.say, ...(this.def.rate ? ['-r', String(this.def.rate)] : []), '-o', raw, spoken]);
    } else {
      await this.server().run({ lang: 'en', text: spoken, out: raw, seed });
    }
    finish(raw, file);
    fs.rmSync(raw, { force: true });
    return { file, dur: dur(file), spoken, seed };
  }
  /**
   * Besten Take wählen (nur Chatterbox, würfelt): Seeds nacheinander erzeugen, mit whisper.cpp prüfen, erster Take mit
   * ≥ 92 % Wortübereinstimmung und plausibler Sprechdauer gewinnt; gemerkt in voice.json "picks" (reproduzierbar).
   * Andere Engines: say() unverändert.
   */
  async best(text, { tries = 6 } = {}) {
    if (this.def.engine !== 'chatterbox') return this.say(text);
    const spoken = speakable(text, this.def.engine);
    const known = CONFIG.picks?.[this.name]?.[spoken];
    if (known) return this.say(text, known);
    let top = null;
    for (let seed = 1; seed <= tries; seed++) {
      const r = await this.say(text, seed);
      const heard = transcribe(r.file);
      const score = match(text, heard);
      const words = text.split(/\s+/).length, rate = words / r.dur;   // Wörter/s: 1,6–3,6 plausibel
      const ok = score >= 0.92 && rate > 1.6 && rate < 3.6;
      if (process.env.TRAILER_DEBUG) console.log(`   take ${seed}: ${(score * 100).toFixed(0)} % ${rate.toFixed(2)} w/s – ${heard}`);
      if (!top || score - Math.abs(rate - 2.6) * 0.02 > top.q) top = { ...r, q: score - Math.abs(rate - 2.6) * 0.02, score, heard };
      if (ok) { top = { ...r, score, heard }; break; }
    }
    CONFIG.picks ||= {}; (CONFIG.picks[this.name] ||= {})[spoken] = top.seed;
    fs.writeFileSync(path.join(DIR, 'voice.json'), JSON.stringify(CONFIG, null, 1) + '\n');
    if (top.score < 0.92) console.warn(`   ⚠ Sprecher: bester Take ${Math.round(top.score * 100)} % – „${text}“ gehört als „${top.heard}“`);
    return top;
  }
  stop() { this.srv?.stop(); }
}

// ---------------------------------------------------------------- Prüfung per whisper.cpp (lokal)
const WHISPER = process.env.WHISPER_CLI || 'whisper-cli';
const WMODEL = process.env.WHISPER_MODEL || path.resolve(ROOT, '../httpdocs-test/storage/ai/models/ggml-large-v3-turbo-q5_0.bin');
export function transcribe(file) {
  const tmp = file + '.16k.wav';
  execFileSync(FFMPEG, ['-y', '-v', 'error', '-i', file, '-ar', '16000', '-ac', '1', tmp]);
  try { return execFileSync(WHISPER, ['-m', WMODEL, '-l', 'en', '-nt', '-np', '-f', tmp], { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim(); }
  finally { fs.rmSync(tmp, { force: true }); }
}
/** Wörter normalisieren: klein, ohne Satzzeichen, Buchstabenfolgen zusammen (K L X M → klxm), „Ai“/„AI“ ignorieren */
const words = (s) => {
  const w = s.toLowerCase().replace(/[’']/g, '').replace(/[-‐–—]/g, ' ').replace(/[^\p{L}\p{N}\s]/gu, ' ').split(/\s+/).filter(Boolean);
  const out = [];
  let run = false;
  for (const x of w) { const one = x.length === 1; if (one && run) out[out.length - 1] += x; else out.push(x); run = one; }
  return out.map((x) => x.replace(/^klxm(ai|ii|i)$/, 'klxm').replace(/^(klxn|klxmm)$/, 'klxm')).filter((x) => !['ai', 'ii', 'i', 'a'].includes(x));
};
export function match(ref, hyp) {
  // Zeichen-LCS über die normalisierten Wörter ohne Leerzeichen (Komposita wie „Cross Media“ = „Crossmedia“ zählen gleich)
  // Markenname muss als K-L-X-M erkannt sein
  if (/KLXM/.test(ref) && !words(hyp).includes('klxm')) return Math.min(0.9, match(ref.replace(/KLXM/g, ''), hyp.replace(/k\s*l\s*\S*/i, '')));
  const a = words(ref).join(''), b = words(hyp).join('');
  let prev = new Array(b.length + 1).fill(0);
  for (let i = 1; i <= a.length; i++) {
    const cur = new Array(b.length + 1).fill(0);
    for (let j = 1; j <= b.length; j++) cur[j] = a[i - 1] === b[j - 1] ? prev[j - 1] + 1 : Math.max(prev[j], cur[j - 1]);
    prev = cur;
  }
  return a.length ? (2 * prev[b.length]) / (a.length + b.length) : 1;
}

/** Tonspur: WAVs (48 kHz mono) an ihren Zeitpunkten, Länge = total (Sekunden) */
export function buildTrack(segments, total, out) {
  const pcm = new Int16Array(Math.ceil(total * SR));
  for (const s of segments) {
    const b = fs.readFileSync(s.file);
    const o = b.indexOf('data') + 8;
    const w = new Int16Array(b.buffer.slice(b.byteOffset + o, b.byteOffset + b.length - ((b.length - o) & 1)));
    const at = Math.round(s.t * SR);
    pcm.set(w.subarray(0, Math.max(0, pcm.length - at)), at);
  }
  const h = Buffer.alloc(44);
  h.write('RIFF', 0); h.writeUInt32LE(36 + pcm.length * 2, 4); h.write('WAVE', 8); h.write('fmt ', 12);
  h.writeUInt32LE(16, 16); h.writeUInt16LE(1, 20); h.writeUInt16LE(1, 22); h.writeUInt32LE(SR, 24); h.writeUInt32LE(SR * 2, 28);
  h.writeUInt16LE(2, 32); h.writeUInt16LE(16, 34); h.write('data', 36); h.writeUInt32LE(pcm.length * 2, 40);
  fs.writeFileSync(out, Buffer.concat([h, Buffer.from(pcm.buffer)]));
}

// ---------------------------------------------------------------- Kommandozeile
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const args = process.argv.slice(2);
  const sArg = args.find((a) => a.startsWith('--samples'));
  const one = args.find((a) => a.startsWith('--say='));
  if (one) {
    const v = new Voice();
    console.log(JSON.stringify(await v.say(one.slice(6))));
    v.stop();
  } else if (sArg) {
    const out = path.resolve(sArg.includes('=') ? sArg.split('=')[1] : path.join(ROOT, '../../klxm-studio-website/site-tools/trailer-voice-samples'));
    fs.mkdirSync(out, { recursive: true });
    for (const name of CONFIG.samples) {
      const v = new Voice(name);
      const parts = [];
      for (const t of CONFIG.sampleText) parts.push(await v.best(t));
      v.stop();
      // Sätze mit 0,45 s Pause, −16 LUFS, AAC
      const list = path.join(v.dir, 'sample.txt');
      const gap = path.join(v.dir, 'gap.wav');
      execFileSync(FFMPEG, ['-y', '-v', 'error', '-f', 'lavfi', '-i', `anullsrc=r=${SR}:cl=mono`, '-t', '0.45', '-c:a', 'pcm_s16le', gap]);
      fs.writeFileSync(list, parts.flatMap((p, i) => [...(i ? [`file '${gap}'`] : []), `file '${p.file}'`]).join('\n') + '\n');
      const f = path.join(out, `${name}.m4a`);
      execFileSync(FFMPEG, ['-y', '-v', 'error', '-f', 'concat', '-safe', '0', '-i', list, '-af', 'loudnorm=I=-16:TP=-1.5:LRA=11', '-ar', String(SR), '-c:a', 'aac', '-b:a', '128k',
        '-metadata', `title=KLXM Studio trailer – voice sample „${name}“`, '-metadata', `comment=${v.credit}`, f]);
      console.log(`${name.padEnd(22)} ${dur(f).toFixed(1)} s  ${f}`);
    }
  } else {
    console.log('node tools/trailer/voice.mjs --samples[=ordner] | --say="Text"');
  }
}
