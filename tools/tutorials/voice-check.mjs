#!/usr/bin/env node
/**
 * Sprecher-Prüfung: jeden Trailer-Satz erzeugen (bzw. aus dem Cache), mit dem lokalen whisper.cpp transkribieren und
 * Wort für Wort mit dem Untertitel vergleichen (Übereinstimmung in %, LCS über normalisierte Wörter).
 *
 *   node tools/tutorials/voice-check.mjs                         Piper (wie im aktuellen Trailer, Tempo 0.88)
 *   TUT_TTS=chatterbox node tools/tutorials/voice-check.mjs      Kandidat Chatterbox (+ lexicon.de.json)
 *   Optionen: --json=<datei> (Ergebnis speichern), --text="Satz" (einzelne Sätze statt Trailer, mehrfach möglich)
 *
 * Umgebung: WHISPER_CLI (Standard whisper-cli), WHISPER_MODEL (Standard: ggml-large-v3-turbo-q5_0.bin der Testkopie).
 * Nur Entwicklungswerkzeug – nichts davon wird ausgeliefert.
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(DIR, '../..');
const args = process.argv.slice(2);
const opt = (k) => args.filter((a) => a.startsWith(`--${k}=`)).map((a) => a.slice(k.length + 3));
if (!process.env.TUT_TTS) process.env.TUT_RATE_DE ||= '0.88';   // Piper-Tempo des Trailers
const sp = await import('./speech.mjs');
const FFMPEG = fs.existsSync('/opt/homebrew/bin/ffmpeg') ? '/opt/homebrew/bin/ffmpeg' : 'ffmpeg';
const WHISPER = process.env.WHISPER_CLI || 'whisper-cli';
const MODEL = process.env.WHISPER_MODEL || path.resolve(ROOT, '../httpdocs-test/storage/ai/models/ggml-large-v3-turbo-q5_0.bin');

// Trailer-Sätze (Deutsch) aus tools/trailer/trailer.mjs
let lines = opt('text');
if (!lines.length) {
  const src = fs.readFileSync(path.join(ROOT, 'tools/trailer/trailer.mjs'), 'utf8');
  lines = [...src.matchAll(/voice:\s*\['((?:[^'\\]|\\.)*)'/g)].map((m) => m[1].replace(/\\'/g, "'"));
}

// Normalisierung: Kleinbuchstaben, Satzzeichen weg, Bindestrich-Komposita zusammen (Zwei-Faktor = Zweifaktor), Einzelbuchstaben-Folgen zusammenziehen (K L X M → klxm)
const norm = (s) => {
  const w = s.toLowerCase().replace(/(\p{L})[‐-―-](?=\p{L})/gu, '$1').replace(/[^\p{L}\p{N}\s]/gu, ' ').split(/\s+/).filter(Boolean);
  const out = [];
  let run = false;   // letzter Eintrag besteht nur aus Einzelbuchstaben
  for (const x of w) {
    const single = x.length === 1 && /\p{L}/u.test(x);
    if (single && run) out[out.length - 1] += x; else out.push(x);
    run = single ? true : false;
  }
  return out;
};
// Umschriften aus dem Lexikon zurück auf den Begriff abbilden (Whisper schreibt manchmal, was gesprochen wurde)
const lexFile = path.join(DIR, 'lexicon.de.json');
const LEXJ = fs.existsSync(lexFile) ? JSON.parse(fs.readFileSync(lexFile, 'utf8')) : {};
const LEX = LEXJ.terms || {};
const HEARD = Object.entries(LEXJ.heard || {}).flatMap(([k, list]) => list.map((v) => [v, k]));   // gehörte Schreibweisen → Begriff
const aliases = [...Object.entries(LEX), ...HEARD.map(([v, k]) => [k, v])].map(([k, v]) => [norm(v).join(' '), norm(k).join(' ')]).filter(([a, b]) => a !== b).sort((a, b) => b[0].length - a[0].length);
// Wörter mit Zeiten: { w, from, to } (Sekunden). Umschriften/gehörte Schreibweisen → Begriff, nur wenn der Begriff im Untertitel steht
// (sonst würde z. B. „Zwei-Faktor“ zu „2FA“); zusammengefasste Wörter übernehmen Anfang/Ende der Teile.
function unalias(hyp, ref) {
  const r = ' ' + ref.join(' ') + ' ';
  let out = hyp.slice();
  for (const [a, b] of aliases) {
    if (!r.includes(' ' + b + ' ') || r.includes(' ' + a + ' ')) continue;
    const aw = a.split(' '), bw = b.split(' ');
    for (let i = 0; i + aw.length <= out.length; i++) {
      if (aw.every((x, k) => out[i + k].w === x)) {
        const from = out[i].from, to = out[i + aw.length - 1].to;
        out.splice(i, aw.length, ...bw.map((w) => ({ w, from, to })));
        i += bw.length - 1;
      }
    }
  }
  return out;
}
/** Übereinstimmung Untertitel ↔ Transkript: match = F1 aus Treffern und Genauigkeit (keine zusätzlichen/erfundenen Wörter) */
function score(ref, hypWords) {
  const a = norm(ref), b = unalias(hypWords, a);
  const d = Array.from({ length: a.length + 1 }, () => new Array(b.length + 1).fill(0));
  for (let i = 1; i <= a.length; i++) for (let j = 1; j <= b.length; j++) d[i][j] = a[i - 1] === b[j - 1].w ? d[i - 1][j - 1] + 1 : Math.max(d[i - 1][j], d[i][j - 1]);
  const miss = [], extra = [];
  let lastHit = -1;
  for (let i = a.length, j = b.length; i > 0 || j > 0;) {
    if (i > 0 && j > 0 && a[i - 1] === b[j - 1].w) { if (lastHit < 0) lastHit = j - 1; i--; j--; } else if (j > 0 && (i === 0 || d[i][j - 1] >= d[i - 1][j])) { extra.unshift(b[j - 1].w); j--; } else { miss.unshift(a[i - 1]); i--; }
  }
  const l = d[a.length][b.length];
  return { pct: Math.round((200 * l) / (a.length + b.length || 1)), recall: Math.round((100 * l) / (a.length || 1)), miss, extra, last: b[lastHit], next: b[lastHit + 1] };
}
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'voicecheck-'));
/** whisper.cpp mit Token-Zeitstempeln → { text, words: [{ w, from, to }] } */
function transcribe(wav) {
  const w16 = path.join(tmp, 'in.16k.wav'), base = path.join(tmp, 'out');
  execFileSync(FFMPEG, ['-loglevel', 'error', '-y', '-i', wav, '-ar', '16000', '-ac', '1', w16]);
  execFileSync(WHISPER, ['-m', MODEL, '-l', 'de', '-ml', '1', '-oj', '-of', base, '-np', '-f', w16], { stdio: 'ignore' });
  const segs = JSON.parse(fs.readFileSync(base + '.json', 'utf8')).transcription || [];
  const raw = [];
  for (const sg of segs) {
    const t = sg.text || '', from = sg.offsets.from / 1000, to = sg.offsets.to / 1000;
    if (!t.trim()) continue;
    if (/^\s/.test(t) || !raw.length) raw.push({ t: t.trim(), from, to }); else { raw.at(-1).t += t; raw.at(-1).to = to; }
  }
  const text = raw.map((r) => r.t).join(' ').replace(/\s+([.,!?;:])/g, '$1').trim();
  const words = [];
  let run = false;
  for (const r of raw) for (const w of norm(r.t)) {
    const single = w.length === 1 && /\p{L}/u.test(w);
    if (single && run) { words.at(-1).w += w; words.at(-1).to = r.to; } else words.push({ w, from: r.from, to: r.to });
    run = single;
  }
  return { text, words };
}
/**
 * Schnittpunkt nach dem letzten erkannten Wort des Untertitels: erste Pause (≥ 0,25 s unter −40 dBFS) nach dessen Beginn,
 * spätestens beim Beginn eines erfundenen Anhangs („… Fertig. Fertig.“, „Um, and that's …“); danach Stille bis auf 60 ms kürzen.
 */
function cutPoint(file, sc) {
  const { sr, pcm, dur } = sp.readWav(file);
  if (!sc.last) return null;
  const win = Math.round(sr * 0.01), thr = 330, need = 25;
  const rms = (k) => { let q = 0; for (let i = k * win; i < Math.min(pcm.length, (k + 1) * win); i++) q += pcm[i] * pcm[i]; return Math.sqrt(q / win); };
  const n = Math.floor(pcm.length / win);
  let cut = dur;
  for (let k = Math.floor(sc.last.from / 0.01) + 1, quiet = 0; k < n; k++) {
    quiet = rms(k) < thr ? quiet + 1 : 0;
    if (quiet >= need) { cut = (k - need + 1) * 0.01; break; }
  }
  if (sc.next && sc.next.from > sc.last.from && sc.next.from < cut) cut = sc.next.from;
  let k = Math.min(n - 1, Math.floor(cut / 0.01));
  while (k > 0 && rms(k) < thr) k--;
  cut = Math.min(dur, (k + 1) * 0.01 + 0.06);
  return cut < dur - 0.05 ? +cut.toFixed(2) : null;
}

const narr = new sp.Narrator();
const rows = [];
// --pick=N (nur Chatterbox): bis zu N Takes (Seeds 1…N) je Satz; jeder Take wird nach dem letzten erkannten Wort geschnitten
// (erfundene Anhänge/Nachgeräusche weg) und erneut transkribiert. Bester Take nach Übereinstimmung, bei Gleichstand der kürzere.
// Ergebnis (Seed + Schnitt) in chatterbox-picks.json – der Narrator übernimmt beides für Trailer und Tutorials.
const pickN = sp.ENGINE === 'chatterbox' ? parseInt(opt('pick')[0] || '0', 10) : 0;
const allPicks = sp.readPicks(), picks = (allPicks[sp.VOICES.de] ||= {});
for (const caption of lines) {
  let best = null;
  for (let seed = 1; seed <= Math.max(1, pickN); seed++) {
    const r = await narr.say('de', caption, pickN ? seed : undefined, pickN ? null : undefined);
    let tr = transcribe(r.file), sc = score(caption, tr.words), file = r.file, dur = r.dur, trim = null;
    if (pickN) {
      trim = cutPoint(r.file, sc);
      if (trim) {
        file = path.join(tmp, `take-${seed}.wav`);
        dur = sp.trimWav(r.file, file, trim);
        const tr2 = transcribe(file), sc2 = score(caption, tr2.words);
        if (sc2.pct >= sc.pct) { tr = tr2; sc = sc2; } else { trim = null; file = r.file; dur = r.dur; }
      }
    }
    const row = { caption, spoken: r.spoken, heard: tr.text, match: sc.pct, recall: sc.recall, missing: sc.miss, extra: sc.extra, dur: +dur.toFixed(2), rawDur: +r.dur.toFixed(2), trim, seed: pickN ? seed : undefined };
    if (pickN) process.stdout.write(`   take ${seed}: ${sc.pct} % ${r.dur.toFixed(2)}${trim ? ' → ' + dur.toFixed(2) : ''} s  ${tr.text}\n`);
    if (!best || row.match > best.match || (row.match === best.match && row.dur < best.dur)) best = row;
    if (best.match === 100) break;
  }
  rows.push(best);
  if (pickN) picks[best.spoken] = { seed: best.seed, trim: best.trim, match: best.match, heard: best.heard };
  const note = [best.missing.length && 'fehlt: ' + best.missing.join(', '), best.extra.length && 'zusätzlich: ' + best.extra.join(', ')].filter(Boolean).join(' · ');
  console.log(`${String(best.match).padStart(3)} %  ${best.dur.toFixed(2).padStart(5)} s  ${caption}  →  ${best.heard}${note ? '   [' + note + ']' : ''}`);
  if (pickN) fs.writeFileSync(sp.PICKS_FILE, JSON.stringify(allPicks, null, 1) + '\n');
}
narr.stop();
const avg = rows.reduce((s, r) => s + r.match, 0) / rows.length;
console.log(`Ø ${avg.toFixed(1)} % · ${sp.VOICES.de} · ${rows.filter((r) => r.match === 100).length}/${rows.length} Sätze vollständig · Summe ${rows.reduce((s, r) => s + r.dur, 0).toFixed(1)} s`);
for (const f of opt('json')) fs.writeFileSync(f, JSON.stringify({ voice: sp.VOICES.de, engine: sp.ENGINE, average: +avg.toFixed(1), rows }, null, 1));
fs.rmSync(tmp, { recursive: true, force: true });
