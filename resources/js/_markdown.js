/*
 * Markdown importieren (Core, Teil von admin.js; window.CMSAdmin.Markdown).
 *
 *  - Rich-Text-Felder (_rte.js): Einfügen von reinem Text, der eindeutig nach Markdown aussieht (looksLike), wird umgewandelt
 *    (Hinweis „Als Text einfügen“ macht es rückgängig, ⌘/Strg+Z ebenso); Menü „⋯ → Markdown einfügen …“ öffnet openImport().
 *  - Seiten-Editor (editor.js): Menü „⋯ → Markdown importieren …“ der Werkzeugleiste – Text oder .md-Datei, Vorschau,
 *    ein Textblock oder ein Block je Hauptüberschrift (sections()).
 *
 * Unterstützt: # … ###### (→ h2–h4, relativ zur obersten Ebene; ein einzelnes # am Anfang gilt als Titel und wird ebenfalls h2),
 * Setext-Überschriften (===/---), Absätze, Listen - * + und 1. (verschachtelt per Einrückung), Aufgaben „- [ ]“/„- [x]“
 * (→ <ul class="check">), > Zitat, **fett** oder __fett__, *kursiv* oder _kursiv_, ~~durchgestrichen~~ (nur Text), [Text](Adresse),
 * <https://…>, nackte https-Adressen, `Code` und ```Code-Blöcke``` (als Text – <code> ist nicht in der Whitelist),
 * harte Umbrüche (zwei Leerzeichen oder \ am Zeilenende), \-Maskierungen.
 * Nicht übernommen: Bilder (→ Redaktionsnotiz „[# Bild: alt – adresse #]“, keine fremden Bilder), Tabellen (Zeilen als Text
 * + Notiz, Rich-Text kennt keine Tabellen), HTML (bleibt sichtbarer Text), YAML-Vorspann (entfernt; title → result.title), Linien.
 *
 * Ergebnis nur mit Tags/Klassen der Rich-Text-Whitelist (Core\Sanitizer): p, h2–h4, ul/ol/li, ul.check, blockquote, b, i, a, br.
 * Alles Übrige wird maskiert; der Server säubert beim Speichern ohnehin noch einmal (Fields::sanitize → Sanitizer::block/inline).
 */
import { t } from './_i18n.js';

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const LIST = /^( *)([-*+]|\d{1,9}[.)])(?: +|$)(.*)$/;
const FENCE = /^ {0,3}(`{3,}|~{3,})/;
const HEAD = /^ {0,3}(#{1,6})(?:[ \t]+(.*?))?(?:[ \t]+#+)?[ \t]*$/;
const HR = /^ {0,3}([-*_])(?:[ \t]*\1){2,}[ \t]*$/;
const TABLE_SEP = /^ *\|? *:?-{2,}:? *(\| *:?-{2,}:? *)*\|? *$/;
const indentOf = l => l.match(/^ */)[0].length;
const isBlank = l => !l.trim();

// ------------------------------------------------------------------ Adressen (wie Core\Sanitizer::safeHref)
/** https/http (→ https), mailto:, tel:, #anker, /pfad, „www.…“ – sonst null (Link bleibt Text) */
export function safeHref(href) {
  let h = String(href || '').trim().replace(/^<|>$/g, '');
  if (!h || /[\s<>"]/.test(h)) return null;
  if (/^www\.[^/]+\.[a-z]{2,}/i.test(h)) h = 'https://' + h;
  if (/^#[\w-]*$/.test(h) || /^\/(?!\/)/.test(h)) return h;
  if (/^(mailto|tel):./i.test(h)) return h;
  if (/^https?:\/\/[^/]+/i.test(h)) return h.replace(/^http:/i, 'https:');
  return null;
}
const external = h => /^https:\/\//i.test(h) && (() => { try { return new URL(h).host !== location.host; } catch { return true; } })();
/** Redaktionsnotiz (Core\EditorNotes): „#]“ im Inhalt würde die Notiz beenden */
const note = s => `[# ${String(s).replace(/#\]/g, '# ]').replace(/\s+/g, ' ').trim()} #]`;

// ------------------------------------------------------------------ Erkennen
/**
 * Sieht reiner Text eindeutig nach Markdown aus? Starke Zeichen (eines reicht): Überschrift „# …“ am Zeilenanfang, Code-Zaun,
 * [Text](Adresse), **fett**, Aufgabenliste, Tabelle, Bild. Schwache (zwei nötig): ≥ 2 Listenzeilen, > Zitat, *kursiv*, _kursiv_,
 * `Code`. Normale Sätze mit Bindestrich-Aufzählung oder einem Sternchen bleiben so reiner Text.
 */
export function looksLike(text) {
  const s = String(text || '').replace(/\r\n?/g, '\n');
  if (s.trim().length < 3) return false;
  const strong = [
    /^ {0,3}#{1,6}[ \t]+\S/m, /^ {0,3}(```|~~~)/m, /\[[^\]\n]+\]\((https?:\/\/|mailto:|tel:|\/|#|www\.)[^)\s]*\)/,
    /(\*\*|__)(?=\S)[^\n]*?\S\1/, /^\s*[-*+][ \t]+\[[ xX]\][ \t]/m, /!\[[^\]\n]*\]\([^)\s]+\)/,
  ].filter(rx => rx.test(s)).length;
  const table = /^ *\|.*\|/m.test(s) && s.split('\n').some(l => l.includes('|') && TABLE_SEP.test(l) && l.includes('-'));
  const lists = s.split('\n').filter(l => /^\s*([-*+]|\d{1,3}[.)])[ \t]+\S/.test(l)).length;
  const weak = [lists >= 2, /^ {0,3}>[ \t]?\S/m.test(s), /(^|[\s(])\*(?=[^\s*])[^*\n]*[^\s*]\*(?=[\s).,;:!?]|$)/m.test(s),
    /(^|[\s(])_(?=[^\s_])[^_\n]*[^\s_]_(?=[\s).,;:!?]|$)/m.test(s), /`[^`\n]+`/.test(s)].filter(Boolean).length;
  return strong + (table ? 1 : 0) >= 1 || weak >= 2;
}
/** HTML in der Zwischenablage ohne echte Formatierung (Code-Editoren: nur div/span/pre/br mit style) → wie reiner Text behandeln */
export const htmlIsPlain = html => !html || !/<(p|h[1-6]|ul|ol|li|b|strong|em|i|a|table|blockquote|img)\b/i.test(html.replace(/<(meta|style)\b[\s\S]*?(<\/style>|>)/gi, ''));

// ------------------------------------------------------------------ Zerlegen (Blöcke)
/** Vorspann „---\nschlüssel: wert\n---“ am Anfang: entfernen, title merken */
function frontMatter(src) {
  const m = src.match(/^---[ \t]*\n([\s\S]{0,4000}?)\n(?:---|\.\.\.)[ \t]*(?:\n|$)/);
  if (!m || !m[1].split('\n').every(l => !l.trim() || /^[\w-]+\s*:/.test(l) || /^\s+/.test(l) || /^\s*-\s/.test(l))) return { body: src, title: '' };
  const tm = m[1].match(/^title\s*:\s*(.+)$/m);
  return { body: src.slice(m[0].length), title: tm ? tm[1].trim().replace(/^(["'])(.*)\1$/, '$2') : '' };
}

/** Zeilen → Knoten: {t:'h',level,text} {t:'p',lines} {t:'list',ordered,start,items:[{task,checked,nodes}]} {t:'quote',nodes} {t:'code',lines} {t:'table',rows} */
function blocks(lines) {
  const out = [];
  let para = null;
  const flush = () => { if (para) out.push({ t: 'p', lines: para }); para = null; };
  for (let i = 0; i < lines.length;) {
    const l = lines[i];
    if (isBlank(l)) { flush(); i++; continue; }
    const f = l.match(FENCE);
    if (f) {
      flush();
      const body = [], ind = indentOf(l);
      for (i++; i < lines.length && !(lines[i].trim().startsWith(f[1][0].repeat(3)) && lines[i].trim().replace(/[`~]/g, '') === ''); i++) body.push(lines[i].slice(Math.min(ind, indentOf(lines[i]))));
      i++;
      out.push({ t: 'code', lines: body });
      continue;
    }
    // Setext: Absatz, darunter === oder ---
    if (para && /^ {0,3}(=+|-+)[ \t]*$/.test(l)) {
      out.push({ t: 'h', level: l.trim()[0] === '=' ? 1 : 2, text: para.join(' ').trim() });
      para = null; i++; continue;
    }
    const h = l.match(HEAD);
    if (h) { flush(); out.push({ t: 'h', level: h[1].length, text: (h[2] || '').trim() }); i++; continue; }
    if (HR.test(l)) { flush(); i++; continue; }
    if (/^ {0,3}>/.test(l)) {
      flush();
      const q = [];
      for (; i < lines.length && !isBlank(lines[i]) && (/^ {0,3}>/.test(lines[i]) || q.length); i++) q.push(lines[i].replace(/^ {0,3}> ?/, ''));
      out.push({ t: 'quote', nodes: blocks(q) });
      continue;
    }
    if (l.includes('|') && i + 1 < lines.length && TABLE_SEP.test(lines[i + 1]) && lines[i + 1].includes('-')) {
      flush();
      const rows = [];
      for (; i < lines.length && lines[i].includes('|') && !isBlank(lines[i]); i++) {
        if (TABLE_SEP.test(lines[i]) && rows.length === 1) continue;
        rows.push(lines[i].trim().replace(/^\||\|$/g, '').split(/(?<!\\)\|/).map(c => c.trim()));
      }
      out.push({ t: 'table', rows });
      continue;
    }
    if (LIST.test(l) && !(para && /^ *\d/.test(l) && !/^ *1[.)]/.test(l))) {   // „2019. …“ mitten im Absatz ist keine Liste
      flush();
      const [node, next] = list(lines, i);
      out.push(node); i = next;
      continue;
    }
    (para ||= []).push(l);
    i++;
  }
  flush();
  return out;
}

/** Liste ab Zeile i: Einträge gleicher Art und Ebene; eingerückte Zeilen gehören zum Eintrag (verschachtelte Listen, Absätze) */
function list(lines, i) {
  const m0 = lines[i].match(LIST), ordered = /\d/.test(m0[2]), base = m0[1].length;
  const node = { t: 'list', ordered, start: ordered ? parseInt(m0[2], 10) : 1, items: [] };
  while (i < lines.length) {
    const m = lines[i].match(LIST);
    if (!m || /\d/.test(m[2]) !== ordered || m[1].length > base + 1 || m[1].length < base) break;
    const content = m[0].length - m[3].length, sub = Math.min(content, base + 2);
    const body = [m[3]];
    for (i++; i < lines.length; i++) {
      const l = lines[i];
      if (isBlank(l)) {
        let j = i + 1;
        while (j < lines.length && isBlank(lines[j])) j++;
        if (j < lines.length && indentOf(lines[j]) >= sub) { body.push(''); continue; }
        break;
      }
      if (indentOf(l) >= sub) { body.push(l.slice(Math.min(indentOf(l), content))); continue; }
      if (LIST.test(l) || HEAD.test(l) || FENCE.test(l) || /^ {0,3}>/.test(l) || HR.test(l)) break;
      if (body.at(-1) === '') break;
      body.push(l.trim());   // fortgesetzte Zeile ohne Einrückung
    }
    let task = null;
    const tm = body[0].match(/^\[([ xX])\][ \t]+/);
    if (tm && !ordered) { task = tm[1] !== ' '; body[0] = body[0].slice(tm[0].length); }
    node.items.push({ task: tm && !ordered, checked: task, nodes: blocks(body) });
    // Leerzeilen zwischen Einträgen derselben Liste
    let j = i;
    while (j < lines.length && isBlank(lines[j])) j++;
    const n = j < lines.length && lines[j].match(LIST);
    if (j > i && n && n[1].length >= base && n[1].length <= base + 1 && /\d/.test(n[2]) === ordered) i = j;
  }
  return [node, i];
}

// ------------------------------------------------------------------ Inline
/** Inline-Markdown → HTML (maskiert). notes: Sammelstelle für Bilder */
function inline(s, notes, depth = 0) {
  const keep = [];
  const hold = html => { keep.push(html); return '\x01' + (keep.length - 1) + '\x02'; };
  s = String(s).replace(/[\x01\x02]/g, '');
  s = s.replace(/\\([\\`*_{}[\]()#+\-.!|>~<"'])/g, (m, c) => hold(esc(c)));           // \* → *
  s = s.replace(/(`+)([^`]|[^`][\s\S]*?[^`])\1(?!`)/g, (m, f, c) => hold(esc(c.trim())));   // `Code` als Text
  s = s.replace(/!\[([^\]\n]*)\]\(\s*<?([^)\s>]*)>?(?:\s+["'][^"']*["'])?\s*\)/g, (m, alt, url) => {
    notes.images++;
    return hold(esc(note(t('Bild: {alt} – {url}', { alt: alt.trim() || t('ohne Beschreibung'), url }))));
  });
  s = s.replace(/\[((?:[^\][\n]|\[[^\]\n]*\])+)\]\(\s*<?([^)\s>]*)>?(?:\s+["']([^"'\n]*)["'])?\s*\)/g, (m, label, url, title) => {
    const href = safeHref(url);
    const txt = depth ? esc(label) : inline(label, notes, depth + 1);
    if (!href) return hold(txt + (url ? esc(' (' + url + ')') : ''));
    return hold(`<a href="${esc(href)}"${title ? ` title="${esc(title)}"` : ''}${external(href) ? ' target="_blank" rel="noopener"' : ''}>${txt}</a>`);
  });
  s = s.replace(/<((?:https?:\/\/|mailto:)[^>\s]+)>/gi, (m, u) => { const h = safeHref(u); return h ? hold(`<a href="${esc(h)}"${external(h) ? ' target="_blank" rel="noopener"' : ''}>${esc(u.replace(/^mailto:/i, ''))}</a>`) : m; });
  if (!depth) s = s.replace(/\bhttps?:\/\/[^\s<>"\x01\x02]+[^\s<>"\x01\x02.,;:!?)]/gi, u => { const h = safeHref(u); return h ? hold(`<a href="${esc(h)}"${external(h) ? ' target="_blank" rel="noopener"' : ''}>${esc(u)}</a>`) : u; });
  let html = esc(s);
  html = html.replace(/(\*\*|__)(?=\S)([\s\S]*?\S)\1/g, '<b>$2</b>');
  html = html.replace(/(^|[^\w*])\*(?=[^\s*])([^*\n]*?[^\s*])\*(?![\w*])/g, '$1<i>$2</i>');
  html = html.replace(/(^|[^\w])_(?=[^\s_])([^_\n]*?[^\s_])_(?!\w)/g, '$1<i>$2</i>');
  html = html.replace(/~~(?=\S)([^~\n]*?\S)~~/g, '$1');
  return html.replace(/\x01(\d+)\x02/g, (m, n) => keep[+n] ?? '');
}
/** Absatzzeilen: weiche Umbrüche = Leerzeichen, harte (2 Leerzeichen / \ am Ende) = <br> */
function lineHtml(lines, notes) {
  return lines.map((l, i) => {
    const last = i === lines.length - 1, hard = !last && (/ {2,}$/.test(l) || /\\$/.test(l));
    const text = inline(hard && /\\$/.test(l) ? l.slice(0, -1) : l.trim(), notes);
    return text + (last ? '' : hard ? '<br>' : ' ');
  }).join('').trim();
}

// ------------------------------------------------------------------ Ausgabe
/** Überschriften-Ebenen → Tags: oberste Ebene = erstes erlaubtes Tag (h2), darunter h3, h4 … */
function headingMap(nodes, allowed) {
  const hs = nodes.filter(n => n.t === 'h');
  if (!hs.length || !allowed.length) return () => allowed[0] || '';
  let base = Math.min(...hs.map(h => h.level));
  // Genau ein # ganz am Anfang und weitere Überschriften: das ist der Titel – er wird h2 wie die oberste Gliederungsebene
  const ones = hs.filter(h => h.level === 1);
  if (base === 1 && ones.length === 1 && nodes[0] === ones[0] && hs.length > 1) base = Math.min(...hs.filter(h => h.level > 1).map(h => h.level));
  return level => allowed[Math.min(Math.max(0, level - base), allowed.length - 1)];
}

function renderList(n, notes, ctx) {
  const tag = n.ordered ? 'ol' : 'ul';
  // Nur reine Aufgabenlisten werden Häkchen-Listen; gemischte behalten ☐/☑ als Text (Erledigt-Stand geht sonst verloren)
  const check = !n.ordered && n.items.every(it => it.task);
  const items = n.items.map(it => {
    const parts = [], sub = [];
    if (it.task && !check) parts.push(it.checked ? '☑' : '☐');
    for (const c of it.nodes) {
      if (c.t === 'list') sub.push(renderList(c, notes, ctx));
      else parts.push(inlineOf(c, notes, ctx));
    }
    const mark = parts[0] === '☑' || parts[0] === '☐' ? parts.shift() + ' ' : '';
    return '<li>' + mark + parts.filter(Boolean).join('<br>') + sub.join('') + '</li>';
  }).join('');
  return `<${tag}${check ? ' class="check"' : ''}>${items}</${tag}>`;
}
/** Knoten als Inline-Text (in Listenpunkten, Zitaten, Inline-Feldern) */
function inlineOf(n, notes, ctx) {
  if (n.t === 'p') return lineHtml(n.lines, notes);
  if (n.t === 'h') return n.text ? '<b>' + inline(n.text, notes) + '</b>' : '';
  if (n.t === 'code') return n.lines.map(esc).join('<br>');
  if (n.t === 'quote') return n.nodes.map(c => inlineOf(c, notes, ctx)).filter(Boolean).join('<br>');
  if (n.t === 'table') { notes.tables++; return esc(note(t('Tabelle aus Markdown – bitte als Liste oder eigenen Block umsetzen'))) + '<br>' + n.rows.map(r => r.map(c => inline(c, notes)).join(' | ')).join('<br>'); }
  if (n.t === 'list') {
    return n.items.map((it, i) => (n.ordered ? (n.start + i) + '. ' : it.task ? (it.checked ? '☑ ' : '☐ ') : '• ') + it.nodes.map(c => inlineOf(c, notes, ctx)).filter(Boolean).join('<br>')).join('<br>');
  }
  return '';
}
function renderBlock(n, notes, ctx) {
  switch (n.t) {
    case 'h': { const tag = ctx.hmap(n.level); return !n.text ? '' : tag ? `<${tag}>${inline(n.text, notes)}</${tag}>` : `<p><b>${inline(n.text, notes)}</b></p>`; }
    case 'p': return '<p>' + lineHtml(n.lines, notes) + '</p>';
    case 'list': return renderList(n, notes, ctx);
    case 'quote': {
      const inner = n.nodes.map(c => c.t === 'p' ? '<p>' + lineHtml(c.lines, notes) + '</p>' : c.t === 'list' ? renderList(c, notes, ctx) : '<p>' + inlineOf(c, notes, ctx) + '</p>').join('');
      return inner ? '<blockquote>' + inner + '</blockquote>' : '';
    }
    case 'code': return n.lines.length ? '<p>' + n.lines.map(esc).join('<br>') + '</p>' : '';
    case 'table': return '<p>' + inlineOf(n, notes, ctx) + '</p>';
  }
  return '';
}

/**
 * Markdown zerlegen: { nodes, title, notes }. title aus dem YAML-Vorspann.
 */
export function parse(src) {
  let s = String(src || '').replace(/^﻿/, '').replace(/\r\n?/g, '\n').replace(/\t/g, '    ').replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
  const fm = frontMatter(s);
  return { nodes: blocks(fm.body.split('\n')), title: fm.title };
}

/**
 * Markdown → Rich-Text-HTML. opts.mode: 'rich' (Standard) | 'inline' (nur b/i/a/br); opts.headings: erlaubte Überschriften
 * (Standard h2, h3, h4 – h1 ist der Seitentitel). Ergebnis: { html, title, images, tables }.
 */
export function toHtml(src, opts = {}) {
  const { nodes, title } = parse(src);
  return { ...renderNodes(nodes, opts), title };
}
function renderNodes(nodes, opts = {}) {
  const notes = { images: 0, tables: 0 };
  const allowed = opts.headings || ['h2', 'h3', 'h4'];
  const ctx = { hmap: opts.hmap || headingMap(nodes, allowed) };
  const html = opts.mode === 'inline'
    ? nodes.map(n => inlineOf(n, notes, ctx)).filter(Boolean).join('<br>')
    : nodes.map(n => renderBlock(n, notes, ctx)).join('');
  return { html, ...notes };
}

/**
 * Für „Markdown importieren“: in Abschnitte teilen – vor jeder Überschrift der obersten Ebene (wird h2) beginnt ein neuer
 * Abschnitt. split=false: ein Abschnitt. Ergebnis: { parts: [{ heading, html }], title, images, tables }.
 */
export function sections(src, split = true, opts = {}) {
  const { nodes, title } = parse(src);
  const allowed = opts.headings || ['h2', 'h3', 'h4'];
  const hmap = headingMap(nodes, allowed);
  const groups = [];
  let cur = null;
  for (const n of nodes) {
    if (!cur || (split && n.t === 'h' && hmap(n.level) === allowed[0] && cur.nodes.length)) groups.push(cur = { nodes: [] });
    cur.nodes.push(n);
  }
  const total = { images: 0, tables: 0 };
  const parts = groups.map(g => {
    const r = renderNodes(g.nodes, { ...opts, hmap });
    total.images += r.images; total.tables += r.tables;
    const h = g.nodes.find(n => n.t === 'h');
    return { heading: h ? h.text.replace(/[*_`[\]]/g, '') : '', html: r.html };
  }).filter(p => p.html);
  return { parts, title, ...total };
}

// ------------------------------------------------------------------ Dialog (Rich-Text-Feld und Seiten-Editor)
let dlg = null;
/**
 * Dialog „Markdown einfügen/importieren“ in der CMS-Ebene (<dialog> modal: Fokus bleibt im Dialog, Esc schließt, danach
 * zurück zum Auslöser). opts: { box, title, intro, ok, page: bool (Datei, Aufteilen, Position), positions: [[wert, text]],
 * position, block: Name des Textblocks, disabled: Meldung (Import nicht möglich), mode: 'rich'|'inline' }.
 * Ergebnis: Promise<{ text, split, position } | null>.
 */
export function openImport(opts = {}) {
  const box = opts.box || document.body;
  dlg?.remove();
  dlg = document.createElement('dialog');
  dlg.className = 'adm-dialog mdi';
  dlg.setAttribute('aria-labelledby', 'cms-mdi-t');
  const page = !!opts.page;
  dlg.innerHTML = `<form method="dialog" class="mdi__form" novalidate>
    <div class="adm-dialog__head"><h2 id="cms-mdi-t">${esc(opts.title || t('Markdown einfügen'))}</h2>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-mdi-cancel>${esc(t('Abbrechen'))}</button></div>
    <p class="f-help mdi__intro" id="cms-mdi-intro">${esc(opts.intro || t('Markdown-Text hier einfügen – Überschriften (#), Listen (-, 1.), **fett**, *kursiv*, [Links](https://…) und > Zitate werden übernommen.'))}</p>
    ${opts.disabled ? `<p class="mdi__error" role="alert">${esc(opts.disabled)}</p>` : ''}
    <div class="mdi__grid">
      <div class="mdi__in">
        <div class="f"><label for="cms-mdi-src">${esc(t('Markdown-Text'))}</label>
          <textarea id="cms-mdi-src" rows="${page ? 14 : 10}" spellcheck="false" autocomplete="off" aria-describedby="cms-mdi-intro" data-mdi-src></textarea></div>
        ${page ? `<div class="f mdi__file"><label for="cms-mdi-file">${esc(t('… oder Datei wählen (.md, .txt)'))}</label>
          <input type="file" id="cms-mdi-file" accept=".md,.markdown,.mdown,.txt,text/markdown,text/plain" data-mdi-file aria-describedby="cms-mdi-file-msg">
          <p class="f-help" id="cms-mdi-file-msg" data-mdi-file-msg aria-live="polite"></p></div>
        <fieldset class="mdi__split"><legend>${esc(t('Aufteilen'))}</legend>
          <label class="f-check"><input type="radio" name="mdi-split" value="one" checked> <span>${esc(t('Als ein Textblock'))}</span></label>
          <label class="f-check"><input type="radio" name="mdi-split" value="h2"> <span>${esc(t('Bei jeder ##-Überschrift einen neuen Textblock'))}</span></label></fieldset>
        ${opts.positions?.length ? `<div class="f"><label for="cms-mdi-pos">${esc(t('Einfügen'))}</label><select id="cms-mdi-pos" data-mdi-pos>${opts.positions.map(([v, l]) => `<option value="${esc(v)}"${String(v) === String(opts.position) ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select></div>` : ''}` : ''}
      </div>
      <section class="mdi__prev" aria-labelledby="cms-mdi-pt">
        <h3 id="cms-mdi-pt">${esc(t('Vorschau'))}</h3>
        <p class="mdi__sum" data-mdi-sum aria-live="polite"></p>
        <div class="mdi__out" data-mdi-out></div>
      </section>
    </div>
    <div class="adm-row mdi__actions">
      <button type="submit" class="adm-btn adm-btn--primary" data-mdi-ok${opts.disabled ? ' disabled' : ''}>${esc(opts.ok || t('Einfügen'))}</button>
      <button type="button" class="adm-btn adm-btn--ghost" data-mdi-cancel>${esc(t('Abbrechen'))}</button>
    </div>
  </form>`;
  box.append(dlg);
  const q = s => dlg.querySelector(s);
  const src = q('[data-mdi-src]'), out = q('[data-mdi-out]'), sum = q('[data-mdi-sum]');
  const split = () => page && q('[name="mdi-split"]:checked')?.value === 'h2';
  let timer;
  const preview = () => {
    const text = src.value;
    if (!text.trim()) { out.innerHTML = `<p class="mdi__empty">${esc(t('Noch kein Text.'))}</p>`; sum.textContent = ''; return; }
    const r = sections(text, split(), { mode: opts.mode });
    const html = opts.mode === 'inline' ? r.parts.map(p => p.html).join('<br>') : null;
    out.innerHTML = html !== null ? `<div class="mdi__blk">${html}</div>`
      : r.parts.map((p, i) => `<article class="mdi__blk">${page ? `<p class="mdi__bl">${esc(t('Textblock {n}', { n: i + 1 }))}${opts.block ? ' · ' + esc(opts.block) : ''}</p>` : ''}${p.html}</article>`).join('');
    const bits = [];
    if (page) bits.push(r.parts.length === 1 ? t('1 Textblock') : t('{n} Textblöcke', { n: r.parts.length }));
    if (r.images) bits.push(r.images === 1 ? t('1 Bild als Notiz') : t('{n} Bilder als Notiz', { n: r.images }));
    if (r.tables) bits.push(r.tables === 1 ? t('1 Tabelle als Text') : t('{n} Tabellen als Text', { n: r.tables }));
    if (r.title) bits.push(t('Titel im Vorspann („{title}“) wird nicht übernommen', { title: r.title }));
    sum.textContent = bits.join(' · ');
  };
  src.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(preview, 150); });
  dlg.querySelectorAll('[name="mdi-split"]').forEach(r => r.addEventListener('change', preview));
  // .md-Datei lesen (FileReader, nur im Browser – nichts wird hochgeladen)
  q('[data-mdi-file]')?.addEventListener('change', e => {
    const f = e.target.files?.[0], msg = q('[data-mdi-file-msg]');
    if (!f) return;
    if (f.size > 1024 * 1024) { msg.textContent = t('Die Datei ist zu groß (höchstens 1 MB).'); return; }
    if (f.type && !/^text\/|markdown/.test(f.type)) { msg.textContent = t('Bitte eine Textdatei (.md oder .txt) wählen.'); return; }
    const rd = new FileReader();
    rd.onload = () => { src.value = String(rd.result || ''); msg.textContent = t('„{name}“ geladen.', { name: f.name }); preview(); };
    rd.onerror = () => { msg.textContent = t('Die Datei konnte nicht gelesen werden.'); };
    rd.readAsText(f, 'utf-8');
  });
  const before = opts.returnFocus || null;
  preview();
  return new Promise(res => {
    let result = null;
    dlg.querySelectorAll('[data-mdi-cancel]').forEach(b => b.addEventListener('click', () => dlg.close()));
    q('form').addEventListener('submit', e => {
      e.preventDefault();
      if (opts.disabled) return;
      if (!src.value.trim()) { src.focus(); sum.textContent = t('Bitte zuerst Markdown-Text einfügen oder eine Datei wählen.'); return; }
      result = { text: src.value, split: split(), position: q('[data-mdi-pos]')?.value ?? null };
      dlg.close();
    });
    dlg.addEventListener('close', () => {
      res(result);
      dlg.remove(); dlg = null;
      if (!result && before?.isConnected) before.focus?.({ preventScroll: true });
    }, { once: true });
    dlg.showModal();
    src.focus();
  });
}

// ------------------------------------------------------------------ Hinweis nach dem Einfügen (nicht modal)
let tip = null, tipTimer = 0;
/**
 * Kleiner Hinweis unten im Fenster: „Markdown umgewandelt · Als Text einfügen · ×“ (role="status", schließt nach 10 s).
 * onPlain() macht die Umwandlung rückgängig. box: CMS-Ebene.
 */
export function pasteTip(box, onPlain) {
  tip?.remove(); clearTimeout(tipTimer);
  tip = document.createElement('div');
  tip.className = 'mdi-tip';
  tip.setAttribute('role', 'status');
  tip.innerHTML = `<span>${esc(t('Markdown in Formatierung umgewandelt.'))}</span>
    <button type="button" class="mdi-tip__btn" data-plain>${esc(t('Als Text einfügen'))}</button>
    <button type="button" class="mdi-tip__x" data-x aria-label="${esc(t('Hinweis schließen'))}">×</button>`;
  // Klick soll die Schreibmarke im Text lassen (Rückgängig wirkt auf das bearbeitete Feld)
  tip.addEventListener('mousedown', e => e.preventDefault());
  tip.querySelector('[data-plain]').addEventListener('click', () => { const x = tip; tip = null; x.remove(); onPlain(); });
  tip.querySelector('[data-x]').addEventListener('click', () => { tip?.remove(); tip = null; });
  box.append(tip);
  tipTimer = setTimeout(() => { tip?.remove(); tip = null; }, 10000);
}
export const hideTip = () => { tip?.remove(); tip = null; };
