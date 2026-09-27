import { t } from './_i18n.js';
import { ask } from './_bar.js';   // gestaltete Rückfrage statt window.confirm()
/*
 * Untertitel, Kapitel und Transkripte für Videos/Audio (KLXM Studio, Core\MediaTracks)
 *  - Finder rechts (Informationen): Spuren und Transkripte je Sprache, hochladen (.vtt/.srt), neu schreiben, löschen, herunterladen
 *  - Editor: Cue-Liste mit Zeiten + Text, Vorschau im Video (TextTrack-API, ohne blob:), Tastatur: Alt+Enter neuer Untertitel,
 *    Alt+P Abspielen/Pause, Alt+↑/↓ vorheriger/nächster, Strg/⌘+S speichern
 *  - KI: Transkription (whisper.cpp / OpenAI-kompatibel) und Übersetzung als Hintergrund-Auftrag mit Stand (Polling)
 *  - Bereich „KLXM Ai → Untertitel“: [data-cap-queue]
 * Entwürfe (KI) erscheinen erst nach „Geprüft – veröffentlichen“ auf der Website.
 */
const d = document;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const COMMON = { de: 'Deutsch', en: 'English', fr: 'Français', es: 'Español', it: 'Italiano', nl: 'Nederlands', pl: 'Polski', tr: 'Türkçe', ar: 'العربية', uk: 'Українська', ru: 'Русский' };

/** Zeit in ms → „00:01:02.500“ (kurz: „01:02.500“) */
export const stamp = (ms, short = false) => {
  ms = Math.max(0, Math.round(ms || 0));
  const h = Math.floor(ms / 3600000), m = Math.floor(ms % 3600000 / 60000), s = Math.floor(ms % 60000 / 1000), f = ms % 1000;
  const p = (n, l = 2) => String(n).padStart(l, '0');
  return (short && !h ? '' : p(h) + ':') + p(m) + ':' + p(s) + '.' + p(f, 3);
};
/** „1:02.5“, „00:01:02,500“, „62.5“ → ms (null bei Unsinn) */
export const parseStamp = v => {
  const x = String(v).trim().replace(',', '.');
  const mm = x.match(/^(?:(\d{1,3}):)?(?:(\d{1,2}):)?(\d{1,5})(?:\.(\d{1,3}))?$/);
  if (!mm) return null;
  let [, a, b, s, f] = mm;
  let h = 0, m = 0;
  if (a !== undefined && b !== undefined) { h = +a; m = +b; } else if (a !== undefined) m = +a;
  return ((h * 60 + m) * 60 + +s) * 1000 + +(f || '0').padEnd(3, '0');
};
const plain = s => String(s).replace(/<[^>]+>/g, '').replace(/&amp;/g, '&');

let H = null;   // Helfer aus _media.js: { http, base, box, toast }
const langName = (code, meta) => meta?.langs?.[code] || COMMON[code] || code.toUpperCase();
const kindName = (k, meta) => meta?.kinds?.[k] || k;

function dialog(id, cls, label) {
  const box = H.box();
  let dlg = box.querySelector('#' + id);
  if (!dlg) { box.insertAdjacentHTML('beforeend', `<dialog id="${id}" class="adm-dialog ${cls}" aria-labelledby="${id}-h"></dialog>`); dlg = box.querySelector('#' + id); }
  dlg.dataset.label = label;
  return dlg;
}

const api = (id, path = '', opt) => H.http(`${H.base}/api/media/${id}/tracks${path}`, opt);
const post = (url, json) => H.http(url, { method: 'POST', json });

// ============================================================ Finder: Abschnitt „Untertitel & Transkript“
export function captionsPanel(finder, m, helpers) {
  H = helpers;
  if (!['video', 'audio'].includes(m.kind)) return;
  const sec = d.createElement('section');
  sec.className = 'fx-i-sec cap-sec';
  sec.setAttribute('aria-labelledby', 'cap-h-' + m.id);
  sec.innerHTML = `<h3 id="cap-h-${m.id}">${esc(m.kind === 'audio' ? t('Transkript & Textspuren') : t('Untertitel & Transkript'))}</h3><p class="fx-i-hint" data-cap-body>${esc(t('Lädt …'))}</p>`;
  const anchor = $('.fx-editbtn', finder.$info);
  anchor ? anchor.before(sec) : finder.$info.append(sec);
  const token = finder._infoToken;
  let poll = null;
  const load = async () => {
    const st = await api(m.id);
    if (token !== finder._infoToken || !sec.isConnected) return;
    draw(st);
  };
  const draw = st => {
    const ro = finder.ro || !st.can_edit;
    const tracks = st.tracks, trs = Object.entries(st.transcripts || {});
    const active = (st.jobs || []).filter(j => j.status === 'queued' || j.status === 'running');
    const last = (st.jobs || []).find(j => j.status === 'failed');
    const badge = s => s === 'published' ? `<span class="adm-badge">${esc(t('veröffentlicht'))}</span>` : `<span class="adm-badge adm-badge--adm-warn">${esc(t('Entwurf'))}</span>`;
    sec.innerHTML = `<h3 id="cap-h-${m.id}">${esc(m.kind === 'audio' ? t('Transkript & Textspuren') : t('Untertitel & Transkript'))}</h3>
      ${!tracks.length && !trs.length ? `<p class="fx-i-warn cap-miss"${m.kind === 'video' && finder.$info.querySelector('input[name=decorative]')?.checked ? ' hidden' : ''}>${esc(m.kind === 'audio' ? t('Noch kein Transkript – nötig für Barrierefreiheit.') : t('Noch keine Untertitel – nötig für Barrierefreiheit.'))}</p>` : ''}
      ${tracks.length ? `<ul class="cap-list">${tracks.map(x => `<li class="cap-row${x.status !== 'published' ? ' is-draft' : ''}">
          <span class="cap-lang" aria-hidden="true">${esc(x.lang.toUpperCase())}</span>
          <span class="cap-main"><b>${esc(x.display)}</b><small>${esc(kindName(x.kind, st))} · ${x.cue_count} ${esc(t('Einträge'))}${x.note ? ` · ${esc(x.note)}` : ''}</small>${badge(x.status)}</span>
          <span class="cap-acts"><button type="button" class="adm-btn adm-btn--small${x.status !== 'published' && !ro ? ' adm-btn--primary' : ''}" data-cap-edit="${x.id}">${esc(ro ? t('Ansehen') : x.status !== 'published' ? t('Prüfen') : t('Bearbeiten'))}</button>
            <button type="button" class="cap-more" data-cap-more="${x.id}" aria-label="${esc(t('Weitere Aktionen für {name}', { name: x.display }))}" aria-haspopup="menu">⋯</button></span></li>`).join('')}</ul>` : ''}
      ${trs.length ? `<ul class="cap-list cap-list--tr">${trs.map(([l, x]) => `<li class="cap-row${x.status !== 'published' ? ' is-draft' : ''}">
          <span class="cap-lang" aria-hidden="true">${esc(l.toUpperCase())}</span>
          <span class="cap-main"><b>${esc(t('Transkript'))} · ${esc(langName(l, st))}</b><small>${esc(t('{n} Zeichen', { n: x.text.length }))}</small>${badge(x.status)}</span>
          <span class="cap-acts"><button type="button" class="adm-btn adm-btn--small" data-cap-tr="${esc(l)}">${esc(ro ? t('Ansehen') : t('Bearbeiten'))}</button></span></li>`).join('')}</ul>` : ''}
      ${active.map(j => `<div class="cap-job" data-cap-jobid="${j.id}" role="status">
          <span>${esc(j.type === 'translate' ? t('KI übersetzt nach {lang} …', { lang: langName(j.target, st) }) : t('KI transkribiert …'))}
            ${j.status === 'queued' ? esc(t('wartet (Position {n})', { n: j.position })) : j.progress + ' %'}</span>
          <progress max="100" value="${j.progress}" aria-label="${esc(t('Fortschritt'))}"></progress>
          <button type="button" class="adm-link" data-cap-cancel="${j.id}">${esc(t('Abbrechen'))}</button></div>`).join('')}
      ${last && !active.length && Date.now() - new Date(String(last.finished_at).replace(' ', 'T')).getTime() < 3600e3 ? `<p class="fx-i-state is-err">${esc(t('Letzter KI-Auftrag: {msg}', { msg: last.message }))}</p>` : ''}
      ${ro ? '' : `<div class="cap-tools">
        <button type="button" class="adm-btn adm-btn--small" data-cap-upload>${esc(t('Hochladen (.vtt/.srt)'))}</button>
        <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-cap-new>${esc(t('Neu schreiben'))}</button>
        <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-cap-tr="">+ ${esc(t('Transkript'))}</button></div>
        ${st.ai.transcribe && !active.some(j => j.type === 'transcribe') ? `<div class="cap-ai"><label for="cap-ail-${m.id}">${esc(t('Sprache der Tonspur'))}</label>
          <select id="cap-ail-${m.id}" data-cap-ailang><option value="auto">${esc(t('automatisch erkennen'))}</option>${Object.entries(st.langs).map(([c, l]) => `<option value="${esc(c)}"${(st.ai.language === c) ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select>
          <button type="button" class="adm-btn adm-btn--small kia-btn" data-cap-ai><span class="kia-spark" aria-hidden="true">✦</span> ${esc(t('Mit KI transkribieren'))}</button>
          <p class="fx-i-hint">${esc(st.ai.external ? t('Die Tonspur geht an einen externen Dienst. Ergebnis: Entwurf zur Prüfung.') : t('Läuft auf diesem Server im Hintergrund. Ergebnis: Entwurf zur Prüfung.'))}</p></div>` : ''}`}`;
    const pick = (st.ai.language && st.ai.language !== 'auto') ? $('[data-cap-ailang]', sec) : null;
    if (pick && !st.langs[st.ai.language]) pick.value = 'auto';
    bind(st, ro);
    clearTimeout(poll);
    if (active.length) poll = setTimeout(async () => {
      if (!sec.isConnected || token !== finder._infoToken) return;
      const r = await H.http(`${H.base}/api/media-jobs?ids=${active.map(j => j.id).join(',')}`).catch(() => null);
      if (!r) return;
      if (r.jobs.some(j => j.status !== 'queued' && j.status !== 'running')) {
        const done = r.jobs.find(j => j.status === 'done');
        if (done) H.toast(t('KI-Entwurf fertig – bitte prüfen'));
        finder.load(m.id);
        return;
      }
      draw({ ...st, jobs: r.jobs.concat((st.jobs || []).filter(j => !r.jobs.some(x => x.id === j.id))) });
    }, 2000);
  };
  const bind = (st, ro) => {
    $$('[data-cap-edit]', sec).forEach(b => b.onclick = () => openEditor(m, +b.dataset.capEdit, st, ro, () => { finder.load(m.id); }));
    $('[data-cap-new]', sec)?.addEventListener('click', () => openEditor(m, 0, st, false, () => finder.load(m.id)));
    $$('[data-cap-tr]', sec).forEach(b => b.onclick = () => openTranscript(m, b.dataset.capTr, st, ro, load));
    $('[data-cap-upload]', sec)?.addEventListener('click', () => uploadDialog(m, st, load));
    $$('[data-cap-cancel]', sec).forEach(b => b.onclick = async () => { await post(`${H.base}/api/media-jobs/${b.dataset.capCancel}/cancel`, {}); load(); });
    $('[data-cap-ai]', sec)?.addEventListener('click', async e => {
      const b = e.currentTarget; b.disabled = true;
      try { draw(await post(`${H.base}/api/media/${m.id}/transcribe`, { lang: $('[data-cap-ailang]', sec).value })); H.toast(t('Transkription gestartet')); }
      catch (ex) { b.disabled = false; H.toast(ex.message); }
    });
    $$('[data-cap-more]', sec).forEach(b => b.onclick = () => {
      const x = st.tracks.find(y => y.id === +b.dataset.capMore), r = b.getBoundingClientRect();
      const e = [[t('Herunterladen (.vtt)'), () => { location.href = `${H.base}/api/media/${m.id}/tracks/${x.id}/download${finder.pool ? '?pool=' + encodeURIComponent(finder.pool) : ''}`; }]];
      if (!ro && x.status === 'published' && ['subtitles', 'captions'].includes(x.kind)) e.push([t('Als Transkript übernehmen'), async () => {
        try { draw(await post(`${H.base}/api/media/${m.id}/transcript`, { from_track: x.id, publish: 1 })); H.toast(t('Transkript aktualisiert')); } catch (ex) { H.toast(ex.message); }
      }]);
      if (!ro && st.ai.translate && x.kind !== 'descriptions') e.push([t('Mit KI übersetzen …'), () => translateDialog(m, x, st, draw)]);
      if (!ro) e.push(['-'], [t('Löschen'), async () => {
        if (!(await ask({ title: t('„{name}“ löschen?', { name: x.display }), ok: t('Löschen') }))) return;
        try { draw(await post(`${H.base}/api/media/${m.id}/tracks/${x.id}/delete`, {})); } catch (ex) { H.toast(ex.message); }
      }, true]);
      finder.menu(r.left, r.bottom + 4, e);
    });
  };
  load().catch(ex => { const b = $('[data-cap-body]', sec); if (b) b.textContent = ex.message; });
}

// ============================================================ Hochladen (.vtt / .srt)
function uploadDialog(m, st, done) {
  const i = d.createElement('input');
  i.type = 'file'; i.accept = '.vtt,.srt,text/vtt';
  i.onchange = () => {
    const file = i.files[0]; if (!file) return;
    const dlg = dialog('cap-up', 'adm-dialog--small', t('Untertitel hochladen'));
    const guess = (file.name.match(/[._-]([a-z]{2})(?:[._-][a-z]+)?\.(?:vtt|srt)$/i) || [])[1]?.toLowerCase();
    dlg.innerHTML = `<form method="dialog" class="cap-form">
      <h2 id="cap-up-h">${esc(t('Untertitel hochladen'))}</h2>
      <p class="adm-muted">${esc(file.name)}</p>
      ${fieldsHtml(st, { kind: 'subtitles', lang: guess && (st.langs[guess] || COMMON[guess]) ? guess : st.default_lang, label: '' })}
      <label class="f-check"><input type="checkbox" name="publish" checked> <span>${esc(t('Geprüft – sofort auf der Website zeigen'))}</span></label>
      <p class="f-error" role="alert" hidden></p>
      <div class="adm-row cap-foot"><button type="button" class="adm-btn adm-btn--ghost" data-x>${esc(t('Abbrechen'))}</button><button type="submit" class="adm-btn adm-btn--primary">${esc(t('Hochladen'))}</button></div></form>`;
    const f = $('form', dlg), err = $('.f-error', dlg);
    $('[data-x]', dlg).onclick = () => dlg.close();
    f.onsubmit = async e => {
      e.preventDefault();
      const fd = new FormData();
      fd.append('file', file); fd.append('kind', f.kind.value); fd.append('lang', langValue(f)); fd.append('label', f.label.value); fd.append('publish', f.publish.checked ? '1' : '0');
      fd.append('_csrf', $('#adm-csrf')?.value || '');
      try { await H.http(`${H.base}/api/media/${m.id}/tracks`, { method: 'POST', body: fd }); dlg.close(); H.toast(t('Untertitel hochgeladen')); done(); }
      catch (ex) { err.textContent = ex.message; err.hidden = false; }
    };
    dlg.showModal();
    f.kind.focus();
  };
  i.click();
}

/** Felder Art, Sprache, Bezeichnung */
function fieldsHtml(st, x, prefix = 'cap') {
  const langs = { ...COMMON, ...st.langs };
  const known = x.lang in langs;
  return `<div class="cap-fields">
    <div class="f"><label for="${prefix}-k">${esc(t('Art'))}</label><select id="${prefix}-k" name="kind">${Object.entries(st.kinds).map(([k, l]) => `<option value="${k}"${x.kind === k ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select></div>
    <div class="f"><label for="${prefix}-l">${esc(t('Sprache'))}</label><select id="${prefix}-l" name="lang">${Object.entries(langs).map(([c, l]) => `<option value="${esc(c)}"${x.lang === c ? ' selected' : ''}>${esc(l)}</option>`).join('')}
      <option value="_"${known ? '' : ' selected'}>${esc(t('andere …'))}</option></select>
      <input name="lang_other" aria-label="${esc(t('Sprachkürzel, z. B. fa oder pt-BR'))}" placeholder="${esc(t('Kürzel, z. B. fa'))}" value="${known ? '' : esc(x.lang)}" maxlength="12"${known ? ' hidden' : ''}></div>
    <div class="f"><label for="${prefix}-b">${esc(t('Bezeichnung im Player'))}</label><input id="${prefix}-b" name="label" value="${esc(x.label || '')}" maxlength="80" placeholder="${esc(t('automatisch, z. B. „Deutsch“'))}"></div></div>`;
}
const langValue = f => f.lang.value === '_' ? f.lang_other.value.trim() : f.lang.value;
function bindLang(f) {
  f.lang.addEventListener('change', () => { f.lang_other.hidden = f.lang.value !== '_'; if (!f.lang_other.hidden) f.lang_other.focus(); });
}

// ============================================================ Übersetzen (KI)
function translateDialog(m, x, st, draw) {
  const dlg = dialog('cap-tl', 'adm-dialog--small', t('Untertitel übersetzen'));
  const langs = Object.entries({ ...st.langs, ...COMMON }).filter(([c]) => c !== x.lang);
  dlg.innerHTML = `<form method="dialog"><h2 id="cap-tl-h">${esc(t('Untertitel übersetzen'))}</h2>
    <p class="adm-muted">${esc(t('Die Text-KI übersetzt „{name}“ Eintrag für Eintrag, die Zeiten bleiben gleich. Ergebnis: Entwurf zur Prüfung.', { name: x.display }))}</p>
    <div class="f"><label for="cap-tl-l">${esc(t('Zielsprache'))}</label><select id="cap-tl-l" name="target">${langs.map(([c, l]) => `<option value="${esc(c)}">${esc(l)}</option>`).join('')}</select></div>
    <p class="f-error" role="alert" hidden></p>
    <div class="adm-row cap-foot"><button type="button" class="adm-btn adm-btn--ghost" data-x>${esc(t('Abbrechen'))}</button><button type="submit" class="adm-btn adm-btn--primary kia-btn"><span class="kia-spark" aria-hidden="true">✦</span> ${esc(t('Übersetzen'))}</button></div></form>`;
  const f = $('form', dlg), err = $('.f-error', dlg);
  $('[data-x]', dlg).onclick = () => dlg.close();
  f.onsubmit = async e => {
    e.preventDefault();
    try { draw(await post(`${H.base}/api/media/${m.id}/tracks/${x.id}/translate`, { target: f.target.value })); dlg.close(); H.toast(t('Übersetzung gestartet')); }
    catch (ex) { err.textContent = ex.message; err.hidden = false; }
  };
  dlg.showModal();
  f.target.focus();
}

// ============================================================ Transkript
async function openTranscript(m, lang, st, ro, done) {
  const dlg = dialog('cap-tr', 'adm-dialog--wide', t('Transkript'));
  const trs = st.transcripts || {};
  lang ||= Object.keys(st.langs).find(l => !trs[l]) || st.default_lang;
  const cur = trs[lang] || { text: '', status: 'draft' };
  const pubTracks = st.tracks.filter(x => ['subtitles', 'captions'].includes(x.kind));
  dlg.innerHTML = `<form method="dialog" class="cap-form">
    <div class="adm-dialog__head"><h2 id="cap-tr-h">${esc(t('Transkript'))} – ${esc(m.display)}</h2><button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-x>${esc(t('Schließen'))}</button></div>
    <p class="adm-muted">${esc(t('Der vollständige Text zum Mitlesen (Barrierefreiheit, WCAG 1.2.1/1.2.3) – erscheint unter dem Video als „Transkript anzeigen“ und wird von der Website-Suche gefunden. Gern mit Sprecherinnen/Sprechern und wichtigen Geräuschen oder Bildinhalten.'))}</p>
    <div class="cap-fields"><div class="f"><label for="cap-tr-l">${esc(t('Sprache'))}</label><select id="cap-tr-l" name="lang">${Object.entries({ ...st.langs, ...Object.fromEntries(Object.keys(trs).map(l => [l, langName(l, st)])) }).map(([c, l]) => `<option value="${esc(c)}"${c === lang ? ' selected' : ''}>${esc(l)}${trs[c] ? ' ✓' : ''}</option>`).join('')}</select></div>
      ${pubTracks.length && !ro ? `<div class="f"><label for="cap-tr-s">${esc(t('Aus Untertiteln übernehmen'))}</label><span class="adm-row"><select id="cap-tr-s" name="src">${pubTracks.map(x => `<option value="${x.id}">${esc(x.display)}${x.status !== 'published' ? ' (' + esc(t('Entwurf')) + ')' : ''}</option>`).join('')}</select><button type="button" class="adm-btn adm-btn--small" data-from>${esc(t('Übernehmen'))}</button></span></div>` : ''}</div>
    ${cur.status === 'draft' && cur.text ? `<p class="adm-flash adm-flash--info cap-note">${esc(t('Entwurf (z. B. von der KI) – bitte lesen und korrigieren, dann „Geprüft – veröffentlichen“.'))}</p>` : ''}
    <div class="f"><label for="cap-tr-t">${esc(t('Text'))}</label><textarea id="cap-tr-t" name="text" rows="16" ${ro ? 'readonly' : ''}>${esc(cur.text)}</textarea>
      <p class="f-help">${esc(t('Absätze durch eine Leerzeile trennen. Leer speichern = Transkript entfernen.'))}</p></div>
    <p class="f-error" role="alert" hidden></p>
    ${ro ? '' : `<div class="adm-row cap-foot"><button type="submit" class="adm-btn" data-draft>${esc(t('Als Entwurf speichern'))}</button><button type="submit" class="adm-btn adm-btn--primary" data-pub>${esc(t('Geprüft – veröffentlichen'))}</button></div>`}</form>`;
  const f = $('form', dlg), err = $('.f-error', dlg);
  $('[data-x]', dlg).onclick = () => dlg.close();
  f.lang.onchange = () => { dlg.close(); openTranscript(m, f.lang.value, st, ro, done); };
  $('[data-from]', dlg)?.addEventListener('click', async () => {
    const r = await api(m.id, '/' + f.src.value);
    if (f.text.value.trim() && !(await ask({ title: t('Vorhandenen Text ersetzen?'), ok: t('Ersetzen'), danger: false }))) return;
    const cues = r.track.cues; let out = '', last = null;
    for (const c of cues) { const x = plain(c.text).replace(/\s+/g, ' ').trim(); if (!x) continue; if (last !== null && c.start - last >= 2000) out += '\n\n'; else if (out) out += ' '; out += x; last = c.end; }
    f.text.value = out; f.text.focus();
  });
  let publish = false;
  $('[data-pub]', dlg)?.addEventListener('click', () => { publish = true; });
  $('[data-draft]', dlg)?.addEventListener('click', () => { publish = false; });
  f.onsubmit = async e => {
    e.preventDefault(); if (ro) return;
    try { await post(`${H.base}/api/media/${m.id}/transcript`, { lang: f.lang.value, text: f.text.value, publish: publish ? 1 : 0 }); dlg.close(); H.toast(t('Transkript gespeichert')); done(); }
    catch (ex) { err.textContent = ex.message; err.hidden = false; }
  };
  dlg.showModal();
  f.text.focus();
}

// ============================================================ Untertitel-Editor
async function openEditor(m, trackId, st, ro, done) {
  let x = trackId ? (await api(m.id, '/' + trackId)).track : { id: 0, kind: 'subtitles', lang: st.default_lang, label: '', status: 'draft', cues: [], note: '', source: 'editor' };
  const dlg = dialog('cap-ed', 'adm-dialog--wide cap-ed', t('Untertitel bearbeiten'));
  let cues = x.cues.map(c => ({ ...c })), dirty = false;
  const isAudio = m.kind === 'audio';
  dlg.innerHTML = `<form class="cap-form" novalidate>
    <div class="adm-dialog__head"><h2 id="cap-ed-h">${esc(ro ? t('Untertitel ansehen') : x.id ? t('Untertitel bearbeiten') : t('Untertitel schreiben'))} <small>${esc(m.display)}</small></h2>
      <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-x>${esc(t('Schließen'))}</button></div>
    ${x.status !== 'published' && x.source !== 'editor' ? `<p class="adm-flash adm-flash--info cap-note"><strong>${esc(x.note || t('Entwurf – bitte prüfen'))}</strong><br>${esc(t('Bitte vollständig ansehen bzw. anhören und korrigieren: Namen, Fachbegriffe, Zahlen, Zeiten. Erst „Geprüft – veröffentlichen“ zeigt die Untertitel auf der Website.'))}</p>` : ''}
    ${fieldsHtml(st, x, 'cap-ed')}
    <div class="cap-grid">
      <div class="cap-player">
        ${isAudio ? `<audio src="${esc(m.url)}" controls preload="metadata"></audio>` : `<video src="${esc(m.url)}" controls preload="metadata" playsinline></video>`}
        <p class="cap-now" aria-live="off"><span>${esc(t('Zeit'))}: <b data-now>00:00.000</b></span>
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-back aria-label="${esc(t('2 Sekunden zurück'))}">↺ 2 s</button>
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-play>${esc(t('Abspielen/Pause'))}</button></p>
        <p class="f-help">${esc(t('Tastatur: Alt+Enter neuer Untertitel · Alt+P Abspielen/Pause · Alt+↑/↓ vorheriger/nächster · Strg/⌘+S speichern'))}</p>
        <p class="f-help">${esc(t('Gut lesbar: höchstens 2 Zeilen à 42 Zeichen, mindestens 1 Sekunde sichtbar. Geräusche in eckigen Klammern, z. B. [Musik], Sprecherwechsel mit „–“.'))}</p>
      </div>
      <div class="cap-edit">
        <div class="cap-listhead"><span>${esc(t('Einträge'))}: <b data-count>${cues.length}</b></span>
          ${ro ? '' : `<button type="button" class="adm-btn adm-btn--small" data-add>+ ${esc(t('Untertitel an aktueller Zeit'))}</button>`}</div>
        <ol class="cap-cues" data-cues aria-label="${esc(t('Untertitel'))}"></ol>
      </div>
    </div>
    <p class="f-error" role="alert" hidden></p>
    <div class="adm-row cap-foot"><span class="fx-i-state" data-state aria-live="polite"></span>
      ${ro ? '' : x.status === 'published'
        ? `<button type="button" class="adm-btn adm-btn--ghost" data-unpub>${esc(t('Zurückziehen (Entwurf)'))}</button><button type="submit" class="adm-btn adm-btn--primary" data-save="published">${esc(t('Speichern'))}</button>`
        : `<button type="submit" class="adm-btn" data-save="draft">${esc(t('Entwurf speichern'))}</button><button type="submit" class="adm-btn adm-btn--primary" data-save="published">${esc(t('Geprüft – veröffentlichen'))}</button>`}</div>
  </form>`;
  const f = $('form', dlg), list = $('[data-cues]', dlg), player = $(isAudio ? 'audio' : 'video', dlg), err = $('.f-error', dlg), state = $('[data-state]', dlg);
  bindLang(f);
  if (ro) $$('input,select,textarea', f).forEach(el => { el.disabled = true; });
  // Vorschau: TextTrack mit den aktuellen Cues (ohne Datei/Blob – CSP bleibt streng)
  let track = null;
  try { track = player.addTextTrack('subtitles', t('Vorschau'), x.lang); track.mode = 'showing'; } catch {}
  const preview = () => {
    if (!track || !window.VTTCue) return;
    [...(track.cues || [])].forEach(c => track.removeCue(c));
    for (const c of cues) if (c.end > c.start && c.text.trim()) { try { track.addCue(new VTTCue(c.start / 1000, c.end / 1000, c.text)); } catch {} }
  };
  const row = (c, i) => `<li class="cap-cue" data-i="${i}">
      <span class="cap-no" aria-hidden="true">${i + 1}</span>
      <span class="cap-times">
        <label><span class="adm-sr">${esc(t('Beginn Untertitel {n}', { n: i + 1 }))}</span><input data-f="start" value="${stamp(c.start, true)}" inputmode="decimal" spellcheck="false"></label>
        <label><span class="adm-sr">${esc(t('Ende Untertitel {n}', { n: i + 1 }))}</span><input data-f="end" value="${stamp(c.end, true)}" inputmode="decimal" spellcheck="false"></label>
      </span>
      <label class="cap-text"><span class="adm-sr">${esc(t('Text Untertitel {n}', { n: i + 1 }))}</span><textarea data-f="text" rows="2">${esc(c.text)}</textarea></label>
      <span class="cap-cbtn">
        <button type="button" data-seek title="${esc(t('Ab hier abspielen'))}" aria-label="${esc(t('Untertitel {n} abspielen', { n: i + 1 }))}">▶</button>
        ${ro ? '' : `<button type="button" data-setstart title="${esc(t('Beginn = aktuelle Zeit'))}" aria-label="${esc(t('Beginn von Untertitel {n} = aktuelle Zeit', { n: i + 1 }))}">⇤</button>
        <button type="button" data-setend title="${esc(t('Ende = aktuelle Zeit'))}" aria-label="${esc(t('Ende von Untertitel {n} = aktuelle Zeit', { n: i + 1 }))}">⇥</button>
        <button type="button" data-del title="${esc(t('Löschen'))}" aria-label="${esc(t('Untertitel {n} löschen', { n: i + 1 }))}">✕</button>`}
      </span></li>`;
  const render = (focusI = null, field = 'text') => {
    list.innerHTML = cues.map(row).join('') || `<li class="cap-empty">${esc(t('Noch keine Einträge. Video abspielen und „+ Untertitel an aktueller Zeit“ wählen.'))}</li>`;
    $('[data-count]', dlg).textContent = cues.length;
    if (ro) $$('input,textarea', list).forEach(el => { el.readOnly = true; });
    if (focusI !== null) $(`[data-i="${focusI}"] [data-f="${field}"]`, list)?.focus();
    preview();
  };
  const now = () => Math.round((player.currentTime || 0) * 1000);
  const touched = () => { dirty = true; state.textContent = ''; };
  const add = (after = null) => {
    const at = after !== null ? (cues[after]?.end ?? now()) : now();
    const c = { start: at, end: at + 2500, text: '', settings: '' };
    const i = after !== null ? after + 1 : cues.findIndex(y => y.start > at);
    const pos = i < 0 ? cues.length : i;
    cues.splice(pos, 0, c); touched(); render(pos);
  };
  list.addEventListener('input', e => {
    const li = e.target.closest('[data-i]'); if (!li) return;
    const c = cues[+li.dataset.i], k = e.target.dataset.f;
    if (k === 'text') c.text = e.target.value;
    else { const v = parseStamp(e.target.value); e.target.setAttribute('aria-invalid', v === null ? 'true' : 'false'); if (v !== null) c[k] = v; }
    touched(); clearTimeout(list._p); list._p = setTimeout(preview, 300);
  });
  list.addEventListener('change', e => { if (e.target.dataset.f && e.target.dataset.f !== 'text') { const li = e.target.closest('[data-i]'), c = cues[+li.dataset.i]; e.target.value = stamp(c[e.target.dataset.f], true); } });
  list.addEventListener('click', e => {
    const b = e.target.closest('button'), li = e.target.closest('[data-i]'); if (!b || !li) return;
    const i = +li.dataset.i, c = cues[i];
    if (b.matches('[data-seek]')) { player.currentTime = c.start / 1000; player.play?.().catch(() => {}); }
    if (b.matches('[data-setstart]')) { c.start = now(); if (c.end <= c.start) c.end = c.start + 2000; touched(); render(i, 'start'); }
    if (b.matches('[data-setend]')) { c.end = Math.max(c.start + 200, now()); touched(); render(i, 'end'); }
    if (b.matches('[data-del]')) { cues.splice(i, 1); touched(); render(Math.min(i, cues.length - 1)); }
  });
  list.addEventListener('focusin', e => {
    const li = e.target.closest('[data-i]'); if (!li || !player.paused) return;
    const c = cues[+li.dataset.i]; if (c && Math.abs(player.currentTime * 1000 - c.start) > 50) player.currentTime = c.start / 1000;
  });
  // Aktueller Untertitel beim Abspielen hervorheben
  player.addEventListener('timeupdate', () => {
    const ms = now(); $('[data-now]', dlg).textContent = stamp(ms, true);
    $$('.cap-cue', list).forEach(li => { const c = cues[+li.dataset.i]; li.classList.toggle('is-now', !!c && ms >= c.start && ms < c.end); });
  });
  $('[data-add]', dlg)?.addEventListener('click', () => add());
  $('[data-back]', dlg).onclick = () => { player.currentTime = Math.max(0, player.currentTime - 2); };
  $('[data-play]', dlg).onclick = () => player.paused ? player.play().catch(() => {}) : player.pause();
  dlg.onkeydown = e => {
    if (e.altKey && e.code === 'KeyP') { e.preventDefault(); player.paused ? player.play().catch(() => {}) : player.pause(); }
    const li = e.target.closest?.('[data-i]');
    if (li && e.altKey && e.key === 'Enter' && !ro) { e.preventDefault(); add(+li.dataset.i); }
    if (li && e.altKey && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { e.preventDefault(); $(`[data-i="${+li.dataset.i + (e.key === 'ArrowDown' ? 1 : -1)}"] [data-f="${e.target.dataset.f || 'text'}"]`, list)?.focus(); }
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's' && !ro) { e.preventDefault(); save(x.status === 'published' ? 'published' : 'draft'); }
  };
  ['kind', 'lang', 'label', 'lang_other'].forEach(n => f[n]?.addEventListener('input', touched));
  const close = async () => { if (!dirty || await ask({ title: t('Ungespeicherte Änderungen verwerfen?'), ok: t('Verwerfen') })) { dirty = false; player.pause?.(); dlg.close(); } };
  $('[data-x]', dlg).onclick = close;
  dlg.oncancel = e => { e.preventDefault(); close(); };
  const save = async status => {
    err.hidden = true;
    const bad = cues.findIndex(c => !(c.end > c.start));
    if (bad >= 0) { err.textContent = t('Untertitel {n}: Das Ende muss nach dem Beginn liegen.', { n: bad + 1 }); err.hidden = false; $(`[data-i="${bad}"] [data-f="end"]`, list)?.focus(); return; }
    if (status === 'published' && !cues.some(c => c.text.trim())) { err.textContent = t('Leere Untertitel können nicht veröffentlicht werden.'); err.hidden = false; return; }
    const lang = langValue(f);
    const body = { kind: f.kind.value, lang, label: f.label.value, status, cues: cues.map(c => ({ start: c.start, end: c.end, text: c.text, settings: c.settings || '' })) };
    state.className = 'fx-i-state'; state.textContent = t('Speichert …');
    try {
      const r = x.id ? await post(`${H.base}/api/media/${m.id}/tracks/${x.id}`, body) : await post(`${H.base}/api/media/${m.id}/tracks`, body);
      x = { ...x, ...r.track }; dirty = false;
      if (status === 'published') { dlg.close(); H.toast(t('Untertitel veröffentlicht')); done(); return; }
      state.textContent = '✓ ' + t('Gespeichert');
      if (r.track.cues) { cues = r.track.cues.map(c => ({ ...c })); render(); }
      done(false);
    } catch (ex) { state.textContent = ''; err.textContent = ex.message; err.hidden = false; }
  };
  f.addEventListener('submit', e => { e.preventDefault(); save(e.submitter?.dataset.save || (x.status === 'published' ? 'published' : 'draft')); });
  $('[data-unpub]', dlg)?.addEventListener('click', async () => { if (await ask({ title: t('Untertitel von der Website nehmen und als Entwurf behalten?'), ok: t('Als Entwurf'), danger: false })) save('draft').then(() => { dlg.close(); done(); }); });
  render();
  dlg.showModal();
  (cues.length ? $('[data-f="text"]', list) : $('[data-add]', dlg) || f.kind).focus();
}

// ============================================================ Bereich „KLXM Ai → Untertitel“
export function initCaptionsQueue(helpers) {
  const root = $('[data-cap-queue]'); if (!root) return;
  H = helpers;
  const labels = { queued: t('Wartet'), running: t('Läuft'), done: t('Fertig'), failed: t('Fehler'), canceled: t('Abgebrochen') };
  root.addEventListener('click', async e => {
    const b = e.target.closest('[data-cap-transcribe]'); if (!b) return;
    const li = b.closest('[data-cap-media]');
    b.disabled = true;
    try {
      await post(`${H.base}/api/media/${li.dataset.capMedia}/transcribe`, { lang: $('[data-cap-lang]', li).value });
      b.textContent = t('Gestartet – Liste unten'); H.toast(t('Transkription gestartet'));
      setTimeout(() => location.reload(), 900);
    } catch (ex) { b.disabled = false; H.toast(ex.message); }
  });
  const tick = async () => {
    const rows = $$('[data-cap-job]', root).filter(r => ['queued', 'running'].includes(r.dataset.status));
    if (!rows.length) return;
    const r = await H.http(`${H.base}/api/media-jobs?ids=${rows.map(x => x.dataset.capJob).join(',')}`).catch(() => null);
    for (const j of r?.jobs || []) {
      const tr = $(`[data-cap-job="${j.id}"]`, root); if (!tr) continue;
      if (tr.dataset.status !== j.status && !['queued', 'running'].includes(j.status)) { location.reload(); return; }
      tr.dataset.status = j.status;
      $('[data-cap-status]', tr).textContent = labels[j.status] + (j.status === 'queued' ? ' · ' + t('Position {n}', { n: j.position }) : j.status === 'running' ? ` · ${j.progress} %` : '');
    }
    setTimeout(tick, 2500);
  };
  setTimeout(tick, 2500);
}
