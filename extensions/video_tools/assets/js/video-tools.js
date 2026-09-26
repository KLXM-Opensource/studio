// SPDX-License-Identifier: MIT
/*
 * Video-Werkzeuge in der Mediathek (Erweiterung video_tools) – hängt sich über window.CMSMedia.extend() an den Finder:
 *  - Raster/Liste: Poster statt Dateisymbol, animierte Vorschau beim Überfahren, Fortschrittsring laufender Aufträge
 *  - Informationen rechts: Analyse (Codec, Auflösung, fps, Bitrate, Dauer, Ton, faststart, Lautheit), Punktzahl, Empfehlungen,
 *    Aktionen (Optimieren, Schneiden, Poster), Versionen/Herkunft, Aufträge der Datei
 *  - Dialoge: Optimieren (Presets, neue Version oder Ersetzen), Schneiden (In/Out, Tastatur I/O/J/K/L/Leertaste, Schleife,
 *    mehrere Ausschnitte, verlustfrei oder präzise), Poster (Standbild wählen; ohne ffmpeg aus dem Browser), Aufträge
 *  - „Prüfen“: Sammelaktion „Alle optimieren“; Mehrfachauswahl: „n Videos optimieren“
 * Keine Inline-Skripte; Anfragen mit CSRF über CMSMedia.ui.http. Texte: t() (Übersetzung aus lang/{locale}.php der Erweiterung).
 */
(() => {
  const M = window.CMSMedia;
  if (!M || typeof M.extend !== 'function') return;
  const { t, ask, esc, http, toast, inBox, base } = M.ui;
  const d = document;
  const $ = (s, c = d) => c.querySelector(s);
  const $$ = (s, c = d) => [...c.querySelectorAll(s)];
  const API = base + '/api/video-tools';
  const me = d.currentScript?.src || '';
  const SPRITE = me ? me.replace(/js\/video-tools\.js(\?.*)?$/, 'img/icons.svg$1') : '';
  const OWN = new Set(['film-strip', 'scissors', 'play', 'pause', 'speaker-slash', 'speaker-high', 'x-circle', 'stop', 'arrow-line-left', 'arrow-line-right',
    'repeat', 'skip-back', 'skip-forward', 'queue', 'frame-corners', 'gauge', 'monitor', 'archive', 'lightning', 'camera', 'terminal-window']);
  const ico = (n, cls = '') => OWN.has(n) && SPRITE
    ? `<svg class="ico${cls ? ' ' + esc(cls) : ''}" aria-hidden="true" focusable="false" width="1em" height="1em" fill="currentColor"><use href="${esc(SPRITE)}#i-${esc(n)}"/></svg>`
    : M.ui.ico(n, cls);

  // ------------------------------------------------------------ Hilfen
  const pad = (n, l = 2) => String(n).padStart(l, '0');
  /** ms → „01:02.500“ bzw. „1:01:02.500“ */
  const clock = (ms, frac = true) => {
    ms = Math.max(0, Math.round(ms || 0));
    const h = Math.floor(ms / 3600000), m = Math.floor(ms % 3600000 / 60000), s = Math.floor(ms % 60000 / 1000);
    return (h ? h + ':' + pad(m) : pad(m)) + ':' + pad(s) + (frac ? '.' + pad(ms % 1000, 3) : '');
  };
  /** „1:02.5“, „62.5“, „00:01:02,500“ → ms (null bei Unsinn) */
  const parseClock = v => {
    const x = String(v).trim().replace(',', '.');
    const mm = x.match(/^(?:(\d{1,2}):)?(?:(\d{1,2}):)?(\d{1,5})(?:\.(\d{1,3}))?$/);
    if (!mm) return null;
    const [, a, b, s, f] = mm;
    let h = 0, m = 0;
    if (a !== undefined && b !== undefined) { h = +a; m = +b; } else if (a !== undefined) m = +a;
    return ((h * 60 + m) * 60 + +s) * 1000 + +(f || '0').padEnd(3, '0');
  };
  const mb = b => b >= 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
  const mbit = b => (b / 1e6).toFixed(1).replace('.', ',') + ' Mbit/s';
  const live = text => {
    let el = $('#vt-live');
    if (!el) { el = d.createElement('div'); el.id = 'vt-live'; el.className = 'adm-sr'; el.setAttribute('aria-live', 'polite'); d.body.append(el); }
    el.textContent = ''; setTimeout(() => { el.textContent = text; }, 30);
  };
  const post = (url, json) => http(url, { method: 'POST', json: json || {} });
  const dialog = (id, cls, label) => {
    const dlg = inBox(id, `<dialog id="${id}" class="adm-dialog vt-dlg ${cls}" aria-labelledby="${id}-h"></dialog>`);
    dlg.dataset.label = label || '';
    return dlg;
  };
  const STATUS = () => ({ queued: t('wartet'), running: t('läuft'), done: t('fertig'), failed: t('fehlgeschlagen'), canceled: t('abgebrochen') });
  const TYPE_ICON = { optimize: 'gauge', trim: 'scissors', poster: 'image', preview: 'film-strip' };

  // Fortschrittsring (Raster, Liste, Aufträge) – Anteil per Eigenschaft --p (Inline-Stil nur in der Verwaltung, CSP erlaubt)
  const ring = (job, small = false) => {
    // Kein endloser Kreisel: wartet ein Auftrag seit Minuten ohne Arbeiter, ein ruhiges Uhr-Symbol mit Hinweis
    if (job.waiting) {
      const label = `${job.label || t('Auftrag')}: ${t('wartet auf Hintergrunddienst')} – ${t('Cron „video:work“ bzw. php_cli prüfen (Verwaltung → Video-Werkzeuge).')}`;
      return `<span class="vt-ring vt-ring--wait${small ? ' vt-ring--s' : ''}" role="img" aria-label="${esc(label)}" title="${esc(label)}">${ico('clock')}</span>`;
    }
    const p = job.status === 'queued' ? 0 : job.progress;
    const label = `${job.label || t('Auftrag')}: ${job.status === 'queued' ? t('wartet') : p + ' %'}`;
    return `<span class="vt-ring${small ? ' vt-ring--s' : ''}${job.status === 'queued' ? ' is-queued' : ''}" style="--p:${p}" role="img" aria-label="${esc(label)}" title="${esc(label)}"><b>${job.status === 'queued' ? '…' : p}</b></span>`;
  };

  // ------------------------------------------------------------ Zustand
  const info = new Map();            // Medien-ID → Antwort von /media/{id}
  let finder = null;                 // Mediathek (library)
  let open = [];                     // offene Aufträge (letzter Stand)
  let pollT = null, pollFast = 0;
  const previewAsked = new Set();

  const loadInfo = async (id, refresh = false) => {
    const r = await http(`${API}/media/${id}${refresh ? '?refresh=1' : ''}`);
    info.set(id, r);
    return r;
  };

  // ------------------------------------------------------------ Aufträge beobachten
  function poll(soon = false) {
    if (soon) pollFast = Date.now() + 15000;
    else if (pollT) return;   // bereits geplant – nicht bei jedem Neuladen der Liste verschieben
    clearTimeout(pollT);
    pollT = setTimeout(tick, soon ? 800 : 2500);
  }
  async function tick() {
    pollT = null;
    let r;
    try { r = await http(`${API}/jobs?open=1&limit=60`); } catch { return poll(); }
    const before = new Map(open.map(j => [j.id, j]));
    open = r.jobs;
    const nowIds = new Set(open.map(j => j.id));
    const finished = [...before.keys()].filter(id => !nowIds.has(id));
    updateRings();
    updateJobsButton(r.open);
    if (finished.length) {
      const fin = (await http(`${API}/jobs?ids=${finished.join(',')}`).catch(() => ({ jobs: [] }))).jobs;
      for (const j of fin) {
        if (j.type === 'preview') continue;
        const msg = j.status === 'done' ? t('Fertig: {msg}', { msg: j.message }) : j.status === 'canceled' ? t('Abgebrochen: {label}', { label: j.label }) : t('Fehlgeschlagen: {msg}', { msg: j.message });
        toast(msg); live(msg);
      }
      fin.forEach(j => { info.delete(j.media_id); if (j.result_id) info.delete(j.result_id); });
      if (finder?.root.isConnected) finder.load(finder.active);
    }
    refreshJobsDialog();
    refreshPanelJobs();
    if (open.length || Date.now() < pollFast) poll();
  }
  function updateRings() {
    if (!finder?.root.isConnected) return;
    const byMedia = new Map();
    open.forEach(j => { if (!byMedia.has(j.media_id)) byMedia.set(j.media_id, j); });
    $$('[data-id]', finder.$items).forEach(el => {
      const j = byMedia.get(+el.dataset.id), cur = $('.vt-ring', el);
      if (!j) { cur?.remove(); return; }
      const html = ring(j, finder.view === 'list');
      if (cur) cur.outerHTML = html; else ($('.fx-thumb', el) || $('.fx-mini', el))?.insertAdjacentHTML('beforeend', html);
    });
  }
  function updateJobsButton(n) {
    const b = finder && $('[data-vt-jobs]', finder.root);
    if (!b) return;
    const c = $('.vt-count', b);
    c.textContent = n || '';
    c.hidden = !n;
    b.setAttribute('aria-label', n ? t('Video-Aufträge ({n} offen)', { n }) : t('Video-Aufträge'));
    b.classList.toggle('is-busy', !!n);
  }

  // ------------------------------------------------------------ Panel „Video-Werkzeuge“ (Informationen rechts)
  let panelCtx = null;   // { sec, m, finder, token }
  function panel(f, m) {
    if (m.kind !== 'video' || !m.ext?.video_tools) return;
    const sec = d.createElement('section');
    sec.className = 'fx-i-sec vt-sec';
    sec.setAttribute('aria-labelledby', 'vt-h-' + m.id);
    sec.innerHTML = `<h3 id="vt-h-${m.id}">${ico('film-strip')} ${esc(t('Video-Werkzeuge'))}</h3><p class="fx-i-hint" aria-busy="true">${esc(t('Analysiert …'))}</p>`;
    const anchor = $('.fx-editbtn', f.$info);
    anchor ? anchor.before(sec) : f.$info.append(sec);
    const token = f._infoToken;
    panelCtx = { sec, m, finder: f, token };
    loadInfo(m.id).then(r => { if (sec.isConnected && token === f._infoToken) drawPanel(sec, m, r, f); })
      .catch(ex => { const p = $('.fx-i-hint', sec); if (p) { p.textContent = ex.message; p.removeAttribute('aria-busy'); } });
  }

  function facts(r) {
    const i = r.info || {}, v = (i.streams || []).find(s => s.type === 'video'), a = (i.streams || []).filter(s => s.type === 'audio');
    const rows = [];
    if (v) {
      rows.push([t('Codec'), `${String(v.codec || '?').toUpperCase()}${v.profile ? ' · ' + esc(v.profile) : ''}${v.pix_fmt ? ' · ' + esc(v.pix_fmt) : ''}`]);
      if (v.width) rows.push([t('Auflösung'), `${v.width} × ${v.height}${v.rotation ? ` (${t('gedreht')} ${v.rotation}°)` : ''}`]);
      if (v.fps) rows.push([t('Bildrate'), String(v.fps).replace('.', ',') + ' fps']);
      if (v.bitrate || i.bitrate) rows.push([t('Bitrate'), mbit(v.bitrate || i.bitrate) + (v.bitrate ? '' : ' ' + t('(gesamt)'))]);
    }
    if (i.duration) rows.push([t('Dauer'), clock(i.duration * 1000, false)]);
    rows.push([t('Ton'), a.length ? a.map(x => `${String(x.codec).toUpperCase()}${x.channels ? ' · ' + (x.channels === 1 ? 'Mono' : x.channels === 2 ? 'Stereo' : x.channels + ' ' + t('Kanäle')) : ''}${x.bitrate ? ' · ' + Math.round(x.bitrate / 1000) + ' kbit/s' : ''}`).join('<br>') : esc(t('keine Tonspur'))]);
    rows.push([t('Größe'), mb(r.bytes)]);
    rows.push(['faststart', i.faststart == null ? '–' : i.faststart ? `<span class="vt-yes">${M.ui.ico('check')} ${esc(t('ja'))}</span>` : `<span class="vt-no">${M.ui.ico('warning')} ${esc(t('nein'))}</span>`]);
    if (r.loud) rows.push([t('Lautheit'), `${String(r.loud.lufs).replace('.', ',').replace('-', '−')} LUFS${r.loud.peak != null ? ' · ' + t('Spitze') + ' ' + String(r.loud.peak).replace('.', ',').replace('-', '−') + ' dBFS' : ''}`]);
    return rows.map(([k, v]) => `<dt>${esc(k)}</dt><dd>${v}</dd>`).join('');
  }

  function scoreHtml(r) {
    const s = r.assess.score, ok = r.assess.optimized;
    return `<div class="vt-score${ok ? ' is-ok' : s < 60 ? ' is-bad' : ' is-mid'}">
      <span class="vt-gauge" style="--p:${s}" role="img" aria-label="${esc(t('Punktzahl {n} von 100', { n: s }))}"><b>${s}</b></span>
      <span><strong>${esc(ok ? t('Für das Web optimiert') : t('Nicht für das Web optimiert'))}</strong>
      <small>${esc(r.info.source === 'ffprobe' ? t('Analyse mit ffprobe') : t('Analyse aus dem Dateikopf (ohne ffmpeg)'))}</small></span></div>`;
  }

  function jobRows(jobs, admin, withMedia = false) {
    return jobs.map(j => {
      const openJ = j.status === 'queued' || j.status === 'running';
      return `<li class="vt-job is-${j.status}" data-job="${j.id}">
        <span class="vt-job__ico">${openJ ? ring(j, true) : ico(TYPE_ICON[j.type] || 'gauge')}</span>
        <span class="vt-job__main"><b>${esc(j.label)}${withMedia ? ` <span class="vt-job__media">· ${esc(j.media)}</span>` : ''}</b>
          <small>${esc(j.waiting ? t('wartet auf Hintergrunddienst') : STATUS()[j.status] || j.status)}${j.waiting ? ' · ' + esc(t('Cron „video:work“ bzw. php_cli prüfen (Verwaltung → Video-Werkzeuge).')) : ''}${j.status === 'queued' && !j.waiting && j.position ? ' · ' + esc(t('Position {n}', { n: j.position })) : ''}${j.status === 'running' ? ' · ' + j.progress + ' %' : ''}${j.message ? ' · ' + esc(j.message) : ''}</small>
          ${j.status === 'running' ? `<progress max="100" value="${j.progress}" aria-label="${esc(t('Fortschritt'))}"></progress>` : ''}</span>
        <span class="vt-job__acts">
          ${openJ ? `<button type="button" class="adm-link" data-vt-cancel="${j.id}">${esc(t('Abbrechen'))}</button>` : ''}
          ${j.status === 'failed' || j.status === 'canceled' ? `<button type="button" class="adm-link" data-vt-retry="${j.id}">${esc(t('Wiederholen'))}</button>` : ''}
          ${j.result_id ? `<button type="button" class="adm-link" data-vt-show="${j.result_id}">${esc(t('Anzeigen'))}</button>` : ''}
          ${admin && j.has_log ? `<button type="button" class="adm-link" data-vt-log="${j.id}">${esc(t('Protokoll'))}</button>` : ''}
        </span></li>`;
    }).join('');
  }

  function bindJobActions(root, after) {
    root.addEventListener('click', async e => {
      const c = e.target.closest('[data-vt-cancel]'), rt = e.target.closest('[data-vt-retry]'), sh = e.target.closest('[data-vt-show]'), lg = e.target.closest('[data-vt-log]');
      try {
        if (c) { await post(`${API}/jobs/${c.dataset.vtCancel}/cancel`); toast(t('Abbruch angefordert')); live(t('Abbruch angefordert')); poll(true); after?.(); }
        if (rt) { await post(`${API}/jobs/${rt.dataset.vtRetry}/retry`); toast(t('Erneut eingereiht')); poll(true); after?.(); }
        if (sh) { const id = +sh.dataset.vtShow; $('#vt-jobs')?.open && $('#vt-jobs').close(); finder?.load(id); }
        if (lg) showLog(+lg.dataset.vtLog);
      } catch (ex) { toast(ex.message); }
    });
  }

  function drawPanel(sec, m, r, f) {
    const can = r.can, items = r.assess.items || [], rel = r.relations || {};
    const openJobs = (r.jobs || []).filter(j => j.status === 'queued' || j.status === 'running');
    const recent = (r.jobs || []).filter(j => !(j.status === 'queued' || j.status === 'running') && j.type !== 'preview').slice(0, 3);
    const lvl = { ok: 'check-circle', info: 'info', warn: 'warning', err: 'warning' };
    sec.innerHTML = `<h3 id="vt-h-${m.id}">${ico('film-strip')} ${esc(t('Video-Werkzeuge'))}</h3>
      ${scoreHtml(r)}
      <ul class="vt-recs">${items.map(it => `<li class="is-${it.level}">${M.ui.ico(lvl[it.level] || 'info')}<span>${esc(it.text)}</span>${it.preset && can.process && !f.ro ? `<button type="button" class="adm-link" data-vt-fix="${esc(it.preset)}">${esc(t('Beheben …'))}</button>` : ''}</li>`).join('')}</ul>
      <dl class="vt-facts">${facts(r)}</dl>
      ${can.loudness && !r.loud && (r.info.streams || []).some(s => s.type === 'audio') ? `<button type="button" class="adm-link vt-loud" data-vt-loud>${ico('speaker-high')} ${esc(t('Lautheit messen (EBU R128)'))}</button>` : ''}
      ${can.process ? '' : `<p class="vt-note">${M.ui.ico('info')} <span>${esc(t('ffmpeg ist auf diesem Server nicht verfügbar – nur Analyse aus dem Dateikopf. Poster lassen sich trotzdem aus dem Browser setzen.'))} <a href="${esc(can.status)}">${esc(t('Status & Einrichtung'))}</a></span></p>`}
      ${f.ro ? '' : `<div class="vt-actions">
        <button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-vt-opt ${can.process ? '' : 'disabled'}>${ico('gauge')} ${esc(t('Für Web optimieren …'))}</button>
        <button type="button" class="adm-btn adm-btn--small" data-vt-trim ${can.process ? '' : 'disabled'}>${ico('scissors')} ${esc(t('Schneiden …'))}</button>
        <button type="button" class="adm-btn adm-btn--small" data-vt-poster>${ico('frame-corners')} ${esc(t('Poster …'))}</button></div>`}
      ${r.poster ? `<div class="vt-posterrow"><img src="${esc(r.poster.thumb)}" alt="" width="96" height="54"><span><b>${esc(t('Poster'))}</b><small>${r.poster.at != null ? esc(t('Standbild bei {at}', { at: clock(r.poster.at, false) })) : esc(t('Standbild'))} · ${esc(t('wird von Video-Blöcken ohne eigenes Vorschaubild verwendet'))}</small></span>${f.ro ? '' : `<button type="button" class="adm-link" data-vt-posterclear>${esc(t('Entfernen'))}</button>`}</div>` : ''}
      ${rel.source ? `<p class="vt-rel">${ico('film-strip')} ${esc(rel.source.kind === 'clip' ? t('Ausschnitt von') : t('Version von'))} <button type="button" class="adm-link" data-vt-goto="${rel.source.id}">${esc(rel.source.display)}</button></p>` : ''}
      ${rel.derived?.length ? `<div class="vt-rel"><b>${esc(t('Versionen & Ausschnitte'))}</b><ul>${rel.derived.map(x => `<li><button type="button" class="adm-link" data-vt-goto="${x.id}">${esc(x.display)}</button> <small>${esc(x.size)}${x.bytes < r.bytes ? ' · −' + Math.round((1 - x.bytes / r.bytes) * 100) + ' %' : ''}</small></li>`).join('')}</ul></div>` : ''}
      <ul class="vt-jobs" data-vt-joblist aria-live="polite">${jobRows([...openJobs, ...recent], can.admin)}</ul>
      <button type="button" class="adm-link vt-alljobs" data-vt-alljobs-open>${esc(t('Alle Video-Aufträge'))}</button>`;
    const fix = (preset) => optimizeDialog(m, r, f, preset);
    $('[data-vt-opt]', sec)?.addEventListener('click', () => fix(null));
    $$('[data-vt-fix]', sec).forEach(b => b.addEventListener('click', () => fix(b.dataset.vtFix)));
    $('[data-vt-trim]', sec)?.addEventListener('click', () => trimDialog(m, r, f));
    $('[data-vt-poster]', sec)?.addEventListener('click', () => posterDialog(m, r, f));
    $('[data-vt-posterclear]', sec)?.addEventListener('click', async () => {
      if (!(await ask({ title: t('Poster entfernen?'), body: t('Das Bild wird gelöscht, sofern es nirgends sonst verwendet wird.'), ok: t('Entfernen') }))) return;
      await post(`${API}/media/${m.id}/poster/clear`); info.delete(m.id); f.load(m.id);
    });
    $('[data-vt-loud]', sec)?.addEventListener('click', async e => {
      const b = e.currentTarget; b.disabled = true; b.setAttribute('aria-busy', 'true'); b.lastChild.textContent = ' ' + t('Misst …');
      try { const nr = await post(`${API}/media/${m.id}/loudness`); info.set(m.id, nr); if (sec.isConnected) drawPanel(sec, m, nr, f); live(t('Lautheit gemessen')); }
      catch (ex) { toast(ex.message); b.disabled = false; }
    });
    $$('[data-vt-goto]', sec).forEach(b => b.addEventListener('click', () => f.load(+b.dataset.vtGoto)));
    $('[data-vt-alljobs-open]', sec)?.addEventListener('click', jobsDialog);
    bindJobActions(sec, () => loadInfo(m.id).then(nr => sec.isConnected && drawPanel(sec, m, nr, f)));
    if (openJobs.length) poll();
  }

  function refreshPanelJobs() {
    const c = panelCtx;
    if (!c || !c.sec.isConnected || c.token !== c.finder._infoToken) return;
    const list = $('[data-vt-joblist]', c.sec);
    if (!list) return;
    const mine = open.filter(j => j.media_id === c.m.id);
    const had = $$('.vt-job.is-queued, .vt-job.is-running', list).length;
    if (!mine.length && !had) return;
    loadInfo(c.m.id).then(r => { if (c.sec.isConnected && c.token === c.finder._infoToken) drawPanel(c.sec, c.m, r, c.finder); }).catch(() => {});
  }

  // ------------------------------------------------------------ Optimieren
  function optimizeDialog(m, r, f, preset = null, ids = null) {
    const dlg = dialog('vt-opt', 'vt-dlg--opt', t('Für Web optimieren'));
    const many = ids && ids.length > 1;
    const presets = r.presets.filter(p => p.available);
    // Empfehlung: ein Preset, das möglichst alles behebt (Neukodierung schließt faststart ein)
    const recs = (r.assess?.items || []).filter(i => i.preset).map(i => i.preset);
    const rec = preset || ['web1080', 'web720', 'mobile540'].find(p => recs.includes(p)) || recs[0] || 'web1080';
    dlg.innerHTML = `<form method="dialog" class="vt-form">
      <h2 id="vt-opt-h">${ico('gauge')} ${esc(many ? t('{n} Videos optimieren', { n: ids.length }) : t('Für Web optimieren'))}</h2>
      <p class="adm-muted">${esc(many ? t('Für jedes Video entsteht ein eigener Auftrag. Die Originale bleiben erhalten.') : m.display + ' · ' + mb(r.bytes))}</p>
      <fieldset class="vt-presets"><legend>${esc(t('Preset'))}</legend>
        ${presets.map(p => `<label class="vt-preset"><input type="radio" name="preset" value="${esc(p.key)}"${p.key === rec ? ' checked' : ''}>
          <span class="vt-preset__ico">${ico(p.icon)}</span><span class="vt-preset__txt"><b>${esc(p.label)}${p.lossless ? ` <em class="adm-badge adm-badge--muted">${esc(t('verlustfrei'))}</em>` : ''}${p.key === rec && !preset && !many ? ` <em class="adm-badge">${esc(t('empfohlen'))}</em>` : ''}</b><small>${esc(p.description)}</small></span></label>`).join('')}
      </fieldset>
      ${many ? '' : `<fieldset class="vt-mode"><legend>${esc(t('Ergebnis'))}</legend>
        <label class="f-check"><input type="radio" name="mode" value="new" checked> <span>${esc(t('Als neue Version anlegen (verknüpft mit dem Original, Angaben und Untertitel werden übernommen)'))}</span></label>
        <label class="f-check"><input type="radio" name="mode" value="replace"${r.can.replace ? '' : ' disabled'}> <span>${esc(t('Original ersetzen – ID, Verwendungen und Untertitel bleiben'))}${r.can.replace ? '' : ' · ' + esc(t('braucht „Medien löschen“'))}</span></label>
        <label class="f-check vt-del"><input type="checkbox" name="delete_original"${r.can.replace ? '' : ' disabled'}> <span>${esc(t('Original danach löschen (nur wenn es nirgends verwendet wird)'))}</span></label></fieldset>`}
      ${r.can.admin && !many ? `<details class="vt-cmd"><summary>${ico('terminal-window')} ${esc(t('Befehl ansehen (nur lesen)'))}</summary><pre tabindex="0" data-vt-cmd></pre></details>` : ''}
      <p class="f-error" role="alert" hidden></p>
      <div class="adm-row vt-foot"><button type="button" class="adm-btn adm-btn--ghost" data-x>${esc(t('Abbrechen'))}</button>
        <button type="submit" class="adm-btn adm-btn--primary">${esc(many ? t('{n} Aufträge starten', { n: ids.length }) : t('Im Hintergrund starten'))}</button></div></form>`;
    const form = $('form', dlg), err = $('.f-error', dlg);
    const sync = () => {
      const p = presets.find(x => x.key === form.preset.value);
      const cmd = $('[data-vt-cmd]', dlg);
      if (cmd) cmd.textContent = p?.command || '';
      if (form.mode) {
        const webm = form.preset.value === 'webm';
        form.mode[1].disabled = !r.can.replace || webm;
        if (webm && form.mode.value === 'replace') form.mode[0].checked = true;
        form.delete_original.disabled = !r.can.replace || form.mode.value !== 'new';
        if (form.delete_original.disabled) form.delete_original.checked = false;
      }
    };
    form.addEventListener('change', sync); sync();
    $('[data-x]', dlg).onclick = () => dlg.close();
    form.onsubmit = async e => {
      e.preventDefault(); err.hidden = true;
      const btn = $('[type=submit]', form); btn.disabled = true;
      try {
        if (many) {
          const res = await post(`${API}/bulk`, { preset: form.preset.value, ids });
          toast(t('{n} Aufträge eingereiht', { n: res.queued }) + (res.skipped?.length ? ' · ' + t('{n} übersprungen', { n: res.skipped.length }) : ''));
        } else {
          if (form.mode.value === 'replace' && !(await ask({ title: t('Original „{name}“ ersetzen?', { name: m.display }), body: t('Die bisherige Datei wird nach erfolgreicher Umwandlung überschrieben. Verwendungen, Alt-Text und Untertitel bleiben erhalten.'), ok: t('Ersetzen'), danger: false }))) { btn.disabled = false; return; }
          if (form.delete_original.checked && !(await ask({ title: t('Original danach löschen?'), body: t('Nur wenn es nirgends verwendet wird – sonst bleibt es erhalten.'), ok: t('Ja, löschen') }))) { btn.disabled = false; return; }
          await post(`${API}/media/${m.id}/optimize`, { preset: form.preset.value, mode: form.mode.value, delete_original: form.delete_original.checked ? 1 : 0 });
          toast(t('Optimierung gestartet – läuft im Hintergrund'));
        }
        live(t('Auftrag gestartet'));
        dlg.close(); info.delete(m?.id); poll(true); f.load(f.active);
      } catch (ex) { err.textContent = ex.message; err.hidden = false; btn.disabled = false; }
    };
    dlg.showModal();
    $('input[name=preset]:checked', dlg)?.focus();
  }

  // ------------------------------------------------------------ Player mit Leiste (Schneiden, Poster)
  function player(host, url, opts = {}) {
    host.innerHTML = `<div class="vt-player">
      <video src="${esc(url)}" preload="auto" playsinline ${opts.muted ? 'muted' : ''} crossorigin="anonymous"></video>
      <div class="vt-scrub">
        <div class="vt-track" aria-hidden="true"><span class="vt-range"></span><span class="vt-kf"></span><span class="vt-head"></span></div>
        <input type="range" min="0" max="1000" step="1" value="0" aria-label="${esc(t('Position im Video'))}" data-vt-pos>
      </div>
      <div class="vt-time"><span data-vt-now>00:00.000</span> / <span data-vt-dur>–</span></div></div>`;
    const v = $('video', host), pos = $('[data-vt-pos]', host), now = $('[data-vt-now]', host), durEl = $('[data-vt-dur]', host);
    const range = $('.vt-range', host), head = $('.vt-head', host), kf = $('.vt-kf', host);
    const st = { dur: 0, inMs: null, outMs: null, loop: false, stopAt: null };
    const pct = ms => st.dur ? Math.max(0, Math.min(100, ms / st.dur * 100)) : 0;
    const draw = () => {
      const ms = v.currentTime * 1000;
      now.textContent = clock(ms);
      pos.value = st.dur ? Math.round(ms / st.dur * 1000) : 0;
      pos.setAttribute('aria-valuetext', clock(ms, false));
      head.style.left = pct(ms) + '%';
      if (st.inMs != null || st.outMs != null) {
        const a = st.inMs ?? 0, b = st.outMs ?? st.dur;
        range.style.left = pct(a) + '%'; range.style.width = Math.max(0, pct(b) - pct(a)) + '%'; range.hidden = false;
      } else range.hidden = true;
    };
    v.addEventListener('loadedmetadata', () => { st.dur = (v.duration || 0) * 1000; durEl.textContent = clock(st.dur); draw(); opts.onReady?.(st); });
    v.addEventListener('timeupdate', () => {
      const ms = v.currentTime * 1000;
      if (st.stopAt != null && ms >= st.stopAt) {
        if (st.loop && st.inMs != null) v.currentTime = st.inMs / 1000; else { v.pause(); st.stopAt = null; }
      }
      draw();
    });
    v.addEventListener('seeked', draw);
    pos.addEventListener('input', () => { if (st.dur) v.currentTime = pos.value / 1000 * st.dur / 1000; });
    const api = {
      v, st, draw,
      seek: ms => { v.currentTime = Math.max(0, Math.min(st.dur, ms)) / 1000; },
      toggle: () => { v.paused ? v.play().catch(() => {}) : v.pause(); },
      playRange: () => { if (st.inMs == null) return; st.stopAt = st.outMs ?? st.dur; v.currentTime = st.inMs / 1000; v.play().catch(() => {}); },
      keyframe: ms => { if (ms == null) { kf.hidden = true; return; } kf.hidden = false; kf.style.left = pct(ms) + '%'; },
    };
    // Tastatur (Dialog): Leertaste/K Abspielen, J/L ±5 s, ←/→ Einzelbild (⇧ ±1 s), I/O In/Out
    (opts.keys || host).addEventListener('keydown', e => {
      if (e.target.matches('input:not([type=range]):not([type=checkbox]):not([type=radio]),textarea,select')) return;
      const k = e.key.toLowerCase();
      if (k === ' ' && e.target.matches('button,input,summary,a')) return;   // Leertaste auf Knöpfen bleibt „klicken“
      const frame = 1000 / (opts.fps || 25);
      if (k === ' ' || k === 'k') { e.preventDefault(); if (k === 'k') v.pause(); else api.toggle(); }
      else if (k === 'j') { e.preventDefault(); api.seek(v.currentTime * 1000 - 5000); }
      else if (k === 'l') { e.preventDefault(); api.seek(v.currentTime * 1000 + 5000); }
      else if (e.key === 'ArrowLeft' && !e.target.matches('input[type=range]')) { e.preventDefault(); v.pause(); api.seek(v.currentTime * 1000 - (e.shiftKey ? 1000 : frame)); }
      else if (e.key === 'ArrowRight' && !e.target.matches('input[type=range]')) { e.preventDefault(); v.pause(); api.seek(v.currentTime * 1000 + (e.shiftKey ? 1000 : frame)); }
      else if (k === 'i' && opts.onIn) { e.preventDefault(); opts.onIn(v.currentTime * 1000); }
      else if (k === 'o' && opts.onOut) { e.preventDefault(); opts.onOut(v.currentTime * 1000); }
    }, { signal: opts.signal });
    return api;
  }

  // ------------------------------------------------------------ Schneiden
  function trimDialog(m, r, f) {
    const dlg = dialog('vt-trim', 'vt-dlg--wide', t('Schneiden'));
    const ac = new AbortController();   // Tastatur-Handler am (wiederverwendeten) Dialog beim Schließen entfernen
    const v0 = (r.info.streams || []).find(s => s.type === 'video');
    const clips = [];
    dlg.innerHTML = `<div class="vt-trim" tabindex="-1">
      <div class="vt-head-row"><h2 id="vt-trim-h">${ico('scissors')} ${esc(t('Schneiden'))} <span>${esc(m.display)}</span></h2>
        <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-x>${esc(t('Schließen'))}</button></div>
      <div class="vt-trim__grid">
        <div class="vt-trim__main">
          <div data-vt-player></div>
          <div class="vt-ctrl" role="group" aria-label="${esc(t('Wiedergabe'))}">
            <button type="button" class="vt-icon" data-vt-back aria-label="${esc(t('5 Sekunden zurück (J)'))}" title="J">${ico('skip-back')}</button>
            <button type="button" class="vt-icon vt-icon--main" data-vt-play aria-label="${esc(t('Abspielen/Pause (Leertaste)'))}" title="${esc(t('Leertaste'))}">${ico('play')}</button>
            <button type="button" class="vt-icon" data-vt-fwd aria-label="${esc(t('5 Sekunden vor (L)'))}" title="L">${ico('skip-forward')}</button>
            <span class="vt-sep"></span>
            <button type="button" class="adm-btn adm-btn--small" data-vt-in title="I">${ico('arrow-line-left')} ${esc(t('Anfang setzen'))} <kbd>I</kbd></button>
            <button type="button" class="adm-btn adm-btn--small" data-vt-out title="O">${esc(t('Ende setzen'))} <kbd>O</kbd> ${ico('arrow-line-right')}</button>
            <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-vt-test>${ico('play')} ${esc(t('Bereich testen'))}</button>
            <label class="f-check vt-loop"><input type="checkbox" data-vt-loop> <span>${ico('repeat')} ${esc(t('Schleife'))}</span></label>
          </div>
          <div class="vt-inout">
            <label>${esc(t('Anfang'))}<input data-vt-inval inputmode="decimal" placeholder="00:00.000" aria-describedby="vt-kfhint"></label>
            <label>${esc(t('Ende'))}<input data-vt-outval inputmode="decimal" placeholder="00:00.000"></label>
            <span class="vt-len" data-vt-len aria-live="polite"></span>
            <button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-vt-add>+ ${esc(t('Ausschnitt hinzufügen'))}</button>
          </div>
          <p class="fx-i-hint" id="vt-kfhint" data-vt-kf></p>
          <p class="fx-i-hint vt-keys">${esc(t('Tastatur: I/O Anfang/Ende · Leertaste Abspielen · J/L ±5 s · K Pause · ←/→ Einzelbild (⇧ ±1 s)'))}</p>
        </div>
        <aside class="vt-trim__side">
          <h3>${esc(t('Ausschnitte'))}</h3>
          <ol class="vt-clips" data-vt-clips></ol>
          <fieldset class="vt-mode"><legend>${esc(t('Schnitt'))}</legend>
            <label class="f-check"><input type="radio" name="vt-precise" value="0" checked> <span><b>${esc(t('Verlustfrei (schnell)'))}</b><small>${esc(t('Ohne Neukodierung; beginnt am Keyframe vor dem Anfang.'))}</small></span></label>
            <label class="f-check"><input type="radio" name="vt-precise" value="1"> <span><b>${esc(t('Präzise (neu kodiert)'))}</b><small>${esc(t('Bildgenau, dauert länger.'))}</small></span></label></fieldset>
          ${r.captions ? `<p class="vt-note">${M.ui.ico('article')} <span>${esc(t('{n} Untertitel-Spur(en) werden passend zugeschnitten und verschoben.', { n: r.captions }))}</span></p>` : ''}
          <p class="f-error" role="alert" hidden></p>
          <button type="button" class="adm-btn adm-btn--primary adm-btn--block" data-vt-go disabled>${esc(t('Ausschnitte erstellen'))}</button>
          <p class="fx-i-hint">${esc(t('Jeder Ausschnitt wird eine neue Datei „… (Ausschnitt 00:12–00:34)“. Das Original bleibt unverändert.'))}</p>
        </aside>
      </div></div>`;
    const wrap = $('.vt-trim', dlg), err = $('.f-error', dlg);
    const inV = $('[data-vt-inval]', dlg), outV = $('[data-vt-outval]', dlg), len = $('[data-vt-len]', dlg), kfHint = $('[data-vt-kf]', dlg), go = $('[data-vt-go]', dlg);
    let kfT = null;
    const P = player($('[data-vt-player]', dlg), r.stream || m.url, {
      fps: v0?.fps, keys: dlg, signal: ac.signal, onIn: ms => setIn(ms), onOut: ms => setOut(ms),
      onReady: st => { if (st.inMs == null) { st.inMs = 0; st.outMs = st.dur; } syncFields(); },
    });
    const setIn = ms => { P.st.inMs = Math.round(ms); if (P.st.outMs != null && P.st.outMs <= P.st.inMs) P.st.outMs = null; syncFields(); live(t('Anfang {t}', { t: clock(ms) })); };
    const setOut = ms => { P.st.outMs = Math.round(ms); if (P.st.inMs != null && P.st.inMs >= P.st.outMs) P.st.inMs = 0; syncFields(); live(t('Ende {t}', { t: clock(ms) })); };
    const syncFields = () => {
      const { inMs, outMs } = P.st;
      inV.value = inMs != null ? clock(inMs) : ''; outV.value = outMs != null ? clock(outMs) : '';
      const l = inMs != null && outMs != null ? outMs - inMs : 0;
      len.textContent = l > 0 ? t('Länge {t}', { t: clock(l) }) : '';
      P.draw();
      clearTimeout(kfT);
      kfT = setTimeout(async () => {
        if (!inMs) { kfHint.textContent = ''; P.keyframe(null); return; }
        try {
          const k = await http(`${API}/media/${m.id}/keyframe?at=${inMs}`);
          if (k.keyframe == null) { kfHint.textContent = ''; P.keyframe(null); return; }
          P.keyframe(k.keyframe);
          const diff = inMs - k.keyframe;
          kfHint.textContent = diff < 40 ? t('Anfang liegt auf einem Keyframe – verlustfreier Schnitt ist bildgenau.')
            : t('Verlustfrei beginnt der Schnitt am Keyframe {k} ({s} s früher). Für einen bildgenauen Anfang „Präzise“ wählen.', { k: clock(k.keyframe), s: (diff / 1000).toFixed(2).replace('.', ',') });
        } catch { kfHint.textContent = ''; }
      }, 350);
    };
    const drawClips = () => {
      $('[data-vt-clips]', dlg).innerHTML = clips.map((c, i) => `<li><span>${esc(clock(c.from, false))}–${esc(clock(c.to, false))} <small>(${esc(clock(c.to - c.from, false))})</small></span>
        <button type="button" class="vt-icon vt-icon--s" data-vt-play-clip="${i}" aria-label="${esc(t('Ausschnitt {n} abspielen', { n: i + 1 }))}">${ico('play')}</button>
        <button type="button" class="vt-icon vt-icon--s" data-vt-rm="${i}" aria-label="${esc(t('Ausschnitt {n} entfernen', { n: i + 1 }))}">${M.ui.ico('x')}</button></li>`).join('')
        || `<li class="vt-empty">${esc(t('Noch keiner – Anfang und Ende setzen, dann „Ausschnitt hinzufügen“.'))}</li>`;
      go.disabled = !clips.length;
      go.textContent = clips.length > 1 ? t('{n} Ausschnitte erstellen', { n: clips.length }) : t('Ausschnitt erstellen');
    };
    const parseField = (el, set) => { const ms = parseClock(el.value); if (ms == null) { el.setAttribute('aria-invalid', 'true'); return; } el.removeAttribute('aria-invalid'); set(Math.min(ms, P.st.dur || ms)); P.seek(ms); };
    inV.addEventListener('change', () => parseField(inV, setIn));
    outV.addEventListener('change', () => parseField(outV, setOut));
    $('[data-vt-in]', dlg).onclick = () => setIn(P.v.currentTime * 1000);
    $('[data-vt-out]', dlg).onclick = () => setOut(P.v.currentTime * 1000);
    $('[data-vt-back]', dlg).onclick = () => P.seek(P.v.currentTime * 1000 - 5000);
    $('[data-vt-fwd]', dlg).onclick = () => P.seek(P.v.currentTime * 1000 + 5000);
    const playBtn = $('[data-vt-play]', dlg);
    playBtn.onclick = () => P.toggle();
    P.v.addEventListener('play', () => { playBtn.innerHTML = ico('pause'); });
    P.v.addEventListener('pause', () => { playBtn.innerHTML = ico('play'); });
    $('[data-vt-test]', dlg).onclick = () => P.playRange();
    $('[data-vt-loop]', dlg).onchange = e => { P.st.loop = e.target.checked; if (P.st.loop) P.playRange(); };
    $('[data-vt-add]', dlg).onclick = () => {
      const { inMs, outMs } = P.st;
      if (inMs == null || outMs == null || outMs - inMs < 300) { err.textContent = t('Bitte Anfang und Ende setzen (mindestens 0,3 Sekunden).'); err.hidden = false; return; }
      err.hidden = true;
      clips.push({ from: inMs, to: outMs });
      drawClips(); live(t('Ausschnitt {n} hinzugefügt', { n: clips.length }));
    };
    $('[data-vt-clips]', dlg).addEventListener('click', e => {
      const rm = e.target.closest('[data-vt-rm]'), pl = e.target.closest('[data-vt-play-clip]');
      if (rm) { clips.splice(+rm.dataset.vtRm, 1); drawClips(); }
      if (pl) { const c = clips[+pl.dataset.vtPlayClip]; P.st.inMs = c.from; P.st.outMs = c.to; syncFields(); P.playRange(); }
    });
    go.onclick = async () => {
      go.disabled = true; err.hidden = true;
      try {
        const precise = $('[name=vt-precise]:checked', dlg).value === '1';
        const res = await post(`${API}/media/${m.id}/trim`, { clips, precise: precise ? 1 : 0 });
        toast(t('{n} Ausschnitt(e) werden erstellt – im Hintergrund', { n: res.jobs.length }) + (res.error ? ' · ' + res.error : ''));
        live(t('Auftrag gestartet'));
        dlg.close(); info.delete(m.id); poll(true); f.load(f.active);
      } catch (ex) { err.textContent = ex.message; err.hidden = false; go.disabled = false; }
    };
    $('[data-x]', dlg).onclick = () => dlg.close();
    dlg.addEventListener('close', () => { ac.abort(); P.v.pause(); P.v.removeAttribute('src'); P.v.load(); }, { once: true });
    drawClips();
    dlg.showModal();
    wrap.focus();
  }

  // ------------------------------------------------------------ Poster
  function posterDialog(m, r, f) {
    const dlg = dialog('vt-poster', 'vt-dlg--mid', t('Poster wählen'));
    const ac = new AbortController();
    dlg.innerHTML = `<div class="vt-trim" tabindex="-1">
      <div class="vt-head-row"><h2 id="vt-poster-h">${ico('frame-corners')} ${esc(t('Poster wählen'))} <span>${esc(m.display)}</span></h2>
        <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-x>${esc(t('Schließen'))}</button></div>
      <div data-vt-player></div>
      <div class="vt-ctrl">
        <button type="button" class="vt-icon vt-icon--main" data-vt-play aria-label="${esc(t('Abspielen/Pause (Leertaste)'))}">${ico('play')}</button>
        <span class="fx-i-hint">${esc(t('Zum gewünschten Bild spulen (←/→ Einzelbild), dann übernehmen.'))}</span>
        <span class="vt-sep"></span>
        <button type="button" class="adm-btn adm-btn--primary adm-btn--small" data-vt-take>${ico('camera')} ${esc(t('Dieses Bild als Poster'))}</button>
      </div>
      <p class="fx-i-hint">${esc(r.can.process ? t('Das Standbild wird in voller Auflösung mit ffmpeg erzeugt und als Bild in der Mediathek abgelegt (dekorativ). Video-Blöcke ohne eigenes Vorschaubild zeigen es automatisch.')
        : t('Ohne ffmpeg nimmt der Browser das Standbild auf. Es wird als Bild in der Mediathek abgelegt (dekorativ). Video-Blöcke ohne eigenes Vorschaubild zeigen es automatisch.'))}</p>
      <p class="f-error" role="alert" hidden></p></div>`;
    const P = player($('[data-vt-player]', dlg), r.stream || m.url, { muted: true, keys: dlg, signal: ac.signal, fps: (r.info.streams || []).find(s => s.type === 'video')?.fps,
      onReady: () => { const at = r.poster?.at ?? Math.min(P.st.dur * 0.1, 3000); P.seek(at); } });
    const playBtn = $('[data-vt-play]', dlg), err = $('.f-error', dlg);
    playBtn.onclick = () => P.toggle();
    P.v.addEventListener('play', () => { playBtn.innerHTML = ico('pause'); });
    P.v.addEventListener('pause', () => { playBtn.innerHTML = ico('play'); });
    $('[data-vt-take]', dlg).onclick = async e => {
      const b = e.currentTarget; b.disabled = true; err.hidden = true;
      const at = Math.round(P.v.currentTime * 1000);
      try {
        if (r.can.process) {
          await post(`${API}/media/${m.id}/poster`, { at });
          toast(t('Poster wird erzeugt …'));
        } else {
          P.v.pause();
          const c = d.createElement('canvas');
          c.width = P.v.videoWidth; c.height = P.v.videoHeight;
          c.getContext('2d').drawImage(P.v, 0, 0);
          const blob = await new Promise(res => c.toBlob(res, 'image/jpeg', 0.92));
          if (!blob) throw new Error(t('Standbild konnte nicht aufgenommen werden.'));
          const fd = new FormData();
          fd.append('image', blob, 'poster.jpg'); fd.append('at', at); fd.append('_csrf', $('#adm-csrf')?.value || '');
          await http(`${API}/media/${m.id}/poster`, { method: 'POST', body: fd });
          toast(t('Poster gesetzt'));
        }
        live(t('Poster gesetzt'));
        dlg.close(); info.delete(m.id); poll(true); f.load(m.id);
      } catch (ex) { err.textContent = ex.message; err.hidden = false; b.disabled = false; }
    };
    $('[data-x]', dlg).onclick = () => dlg.close();
    dlg.addEventListener('close', () => { ac.abort(); P.v.pause(); P.v.removeAttribute('src'); P.v.load(); }, { once: true });
    dlg.showModal();
    $('.vt-trim', dlg).focus();
  }

  // ------------------------------------------------------------ Aufträge (Dialog)
  async function jobsDialog() {
    const dlg = dialog('vt-jobs', 'vt-dlg--mid', t('Video-Aufträge'));
    dlg.innerHTML = `<div class="vt-head-row"><h2 id="vt-jobs-h">${ico('queue')} ${esc(t('Video-Aufträge'))}</h2>
      <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-x>${esc(t('Schließen'))}</button></div>
      <p class="fx-i-hint">${esc(t('Aufträge laufen auf dem Server weiter – Sie können den Browser schließen. Ergebnisse erscheinen in der Mediathek.'))}</p>
      <ul class="vt-jobs vt-jobs--full" data-vt-alljobs aria-live="polite"><li class="fx-i-hint">${esc(t('Lädt …'))}</li></ul>`;
    $('[data-x]', dlg).onclick = () => dlg.close();
    bindJobActions($('[data-vt-alljobs]', dlg), () => refreshJobsDialog(true));
    dlg.showModal();
    refreshJobsDialog(true);
  }
  async function refreshJobsDialog(force = false) {
    const dlg = $('#vt-jobs') || M.ui.box().querySelector('#vt-jobs');
    if (!dlg?.open) return;
    const list = $('[data-vt-alljobs]', dlg);
    const r = await http(`${API}/jobs?limit=40`).catch(() => null);
    if (!r || !list.isConnected) return;
    // Fertige animierte Vorschauen sind Nebensache – nur offene/fehlgeschlagene zeigen
    list.innerHTML = jobRows(r.jobs.filter(j => j.type !== 'preview' || j.status !== 'done'), r.admin, true) || `<li class="fx-i-hint">${esc(t('Noch keine Aufträge.'))}</li>`;
    if (force && r.open) poll();
  }
  async function showLog(id) {
    const dlg = dialog('vt-log', 'vt-dlg--mid', t('Protokoll'));
    dlg.innerHTML = `<div class="vt-head-row"><h2 id="vt-log-h">${ico('terminal-window')} ${esc(t('Protokoll'))}</h2><button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-x>${esc(t('Schließen'))}</button></div><pre class="vt-logpre" tabindex="0">${esc(t('Lädt …'))}</pre>`;
    $('[data-x]', dlg).onclick = () => dlg.close();
    dlg.showModal();
    try { const r = await http(`${API}/jobs/${id}/log`); $('.vt-logpre', dlg).textContent = r.label + '\n\n' + r.log; } catch (ex) { $('.vt-logpre', dlg).textContent = ex.message; }
  }

  // ------------------------------------------------------------ Sammelaktionen
  const presetOptions = (sel = 'web1080') => [['web1080', t('Web 1080p')], ['web720', t('Web 720p')], ['mobile540', t('Mobil 540p')], ['faststart', t('Nur faststart')]]
    .map(([k, l]) => `<option value="${k}"${k === sel ? ' selected' : ''}>${esc(l)}</option>`).join('');

  function summary(f, el) {
    if (!el) return;
    const src = f.src, check = src.type === 'check' && src.value.startsWith('x:video_') ? src.value.slice(2) : null;
    const videos = f.items.filter(m => m.kind === 'video' && m.ext?.video_tools);
    if (!videos.length || (!check && !(src.type === 'kind' && src.value === 'video'))) return;
    const box = d.createElement('section');
    box.className = 'vt-bulk';
    const noPoster = check === 'video_noposter';
    box.innerHTML = `<h3>${ico('film-strip')} ${esc(t('Video-Werkzeuge'))}</h3>
      ${noPoster ? `<p>${esc(t('{n} Videos ohne Poster. Poster automatisch aus dem Bild bei 10 % der Laufzeit erzeugen?', { n: videos.length }))}</p>
        <button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-vt-bulkposter>${ico('frame-corners')} ${esc(t('Poster für alle erzeugen'))}</button>`
      : `<label for="vt-bulkp">${esc(t('Alle optimieren mit Preset'))}</label>
        <div class="fx-inline"><select id="vt-bulkp">${presetOptions(check === 'video_unoptimized' ? 'web1080' : 'faststart')}</select>
        <button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-vt-bulk>${esc(t('Alle optimieren ({n})', { n: videos.length }))}</button></div>
        <p class="fx-i-hint">${esc(t('Je Video ein Auftrag im Hintergrund; es entstehen neue Versionen, Originale bleiben. „Nur faststart“ überspringt Videos, die es schon haben.'))}</p>`}
      <button type="button" class="adm-link" data-vt-alljobs-open>${esc(t('Aufträge ansehen'))}</button>`;
    el.append(box);
    $('[data-vt-bulk]', box)?.addEventListener('click', async e => {
      const preset = $('#vt-bulkp', box).value;
      if (!(await ask({ title: t('{n} Videos mit „{preset}“ optimieren?', { n: videos.length, preset: $('#vt-bulkp', box).selectedOptions[0].text }), body: t('Die Aufträge laufen nacheinander im Hintergrund.'), ok: t('Starten'), danger: false }))) return;
      e.target.disabled = true;
      try {
        const r = await post(`${API}/bulk`, { preset, ids: videos.map(m => m.id) });
        toast(t('{n} Aufträge eingereiht', { n: r.queued }) + (r.skipped?.length ? ' · ' + t('{n} übersprungen', { n: r.skipped.length }) : ''));
        live(t('{n} Aufträge eingereiht', { n: r.queued })); poll(true); f.load();
      } catch (ex) { toast(ex.message); e.target.disabled = false; }
    });
    $('[data-vt-bulkposter]', box)?.addEventListener('click', async e => {
      e.target.disabled = true;
      let n = 0;
      for (const m of videos) {
        try { const r = info.get(m.id) || await loadInfo(m.id); await post(`${API}/media/${m.id}/poster`, { at: Math.round((r.info.duration || 0) * 100) }); n++; } catch {}
      }
      toast(t('{n} Aufträge eingereiht', { n })); poll(true); f.load();
    });
    $('[data-vt-alljobs-open]', box).addEventListener('click', jobsDialog);
  }

  function multi(f, items, el) {
    const vids = items.filter(m => m.kind === 'video' && m.ext?.video_tools);
    if (!vids.length) return;
    const sec = d.createElement('section');
    sec.className = 'fx-i-sec vt-sec';
    sec.innerHTML = `<h3>${ico('film-strip')} ${esc(t('Video-Werkzeuge'))}</h3>
      <button type="button" class="adm-btn adm-btn--small" data-vt-multi>${ico('gauge')} ${esc(t('{n} Videos optimieren …', { n: vids.length }))}</button>`;
    const acts = $('.fx-i-actions', el);
    acts ? acts.before(sec) : el.append(sec);
    $('[data-vt-multi]', sec).onclick = async () => {
      const r = info.get(vids[0].id) || await loadInfo(vids[0].id);
      optimizeDialog(null, { ...r, assess: {}, bytes: 0 }, f, null, vids.map(m => m.id));
    };
  }

  function menu(f, ids, one) {
    if (f.ro) return [];
    if (one?.kind === 'video' && one.ext?.video_tools) {
      const withInfo = fn => async () => { try { fn(info.get(one.id) || await loadInfo(one.id)); } catch (ex) { toast(ex.message); } };
      return [
        [t('Für Web optimieren …'), withInfo(r => r.can.process ? optimizeDialog(one, r, f) : toast(t('ffmpeg ist auf diesem Server nicht verfügbar.')))],
        [t('Schneiden …'), withInfo(r => r.can.process ? trimDialog(one, r, f) : toast(t('ffmpeg ist auf diesem Server nicht verfügbar.')))],
        [t('Poster wählen …'), withInfo(r => posterDialog(one, r, f))],
      ];
    }
    const vids = ids.map(id => f.byId(id)).filter(m => m?.kind === 'video' && m.ext?.video_tools);
    if (vids.length > 1) return [[t('{n} Videos optimieren …', { n: vids.length }), async () => { const r = await loadInfo(vids[0].id); optimizeDialog(null, { ...r, assess: {}, bytes: 0 }, f, null, vids.map(m => m.id)); }]];
    return [];
  }

  async function quickLook(f, m, ql) {
    if (m.kind !== 'video' || !m.ext?.video_tools) return;
    const foot = $('footer', ql);
    if (!foot) return;
    try {
      const r = info.get(m.id) || await loadInfo(m.id);
      if (!foot.isConnected) return;
      const v = (r.info.streams || []).find(s => s.type === 'video');
      foot.insertAdjacentHTML('beforeend', ` · <span class="vt-ql${r.assess.optimized ? ' is-ok' : ''}">${ico('gauge')} ${r.assess.score}/100${v ? ` · ${String(v.codec).toUpperCase()} ${v.width}×${v.height}${v.fps ? ' · ' + String(v.fps).replace('.', ',') + ' fps' : ''}` : ''} · faststart ${r.info.faststart ? t('ja') : t('nein')}</span>`);
    } catch {}
  }

  // Raster: Poster statt Dateisymbol, Ring für laufende Aufträge, animierte Vorschau
  function badge(m, f) {
    const x = m.kind === 'video' ? m.ext?.video_tools : null;
    if (!x) return '';
    let html = '';
    if (x.poster) html += `<img class="vt-thumb" src="${esc(x.poster)}" alt="" loading="lazy" draggable="false">`;
    if (x.preview) html += `<video class="vt-anim" data-src="${esc(x.preview)}" muted loop playsinline preload="none" aria-hidden="true" tabindex="-1"></video>`;
    if (x.optimized === false && !x.job) html += `<span class="vt-flag" title="${esc(t('Nicht für das Web optimiert'))}">${ico('gauge')}<span class="adm-sr">${esc(t('Nicht für das Web optimiert'))}</span></span>`;
    if (x.job) html += ring({ ...x.job, label: t('Auftrag') }, f.view === 'list');
    return html;
  }

  function loaded(f, data) {
    if (f.mode !== 'library') return;
    finder = f;
    // Knopf „Aufträge“ in der Werkzeugleiste (einmal)
    if (!$('[data-vt-jobs]', f.root)) {
      const b = d.createElement('button');
      b.type = 'button'; b.className = 'fx-tbtn vt-jobsbtn'; b.dataset.vtJobs = '';
      b.innerHTML = `${ico('queue')}<span class="vt-count" hidden></span>`;
      b.setAttribute('aria-label', t('Video-Aufträge')); b.title = t('Video-Aufträge');
      b.addEventListener('click', jobsDialog);
      $('[data-infotoggle]', f.root)?.before(b);
      // Animierte Vorschau beim Überfahren/Fokus (nur mit Bewegung erlaubt)
      const motion = !matchMedia('(prefers-reduced-motion: reduce)').matches;
      const play = e => { const v = motion && $('.vt-anim', e.target.closest?.('[data-id]') || d.createElement('i')); if (v) { if (!v.src) v.src = v.dataset.src; v.play().catch(() => {}); v.classList.add('is-on'); } };
      const stop = e => { const v = $('.vt-anim', e.target.closest?.('[data-id]') || d.createElement('i')); if (v) { v.pause(); v.classList.remove('is-on'); } };
      f.$items.addEventListener('mouseover', play);
      f.$items.addEventListener('mouseout', e => { if (!e.relatedTarget || !e.target.closest('[data-id]')?.contains(e.relatedTarget)) stop(e); });
    }
    const vids = (data.items || []).filter(m => m.kind === 'video' && m.ext?.video_tools);
    open = vids.filter(m => m.ext.video_tools.job).map(m => ({ ...m.ext.video_tools.job, media_id: m.id }));
    if (open.length) poll(); else tick();
    // Animierte Vorschau bei Bedarf erzeugen (je Seitenaufruf höchstens einmal je Datei)
    const need = vids.filter(m => !m.ext.video_tools.preview && !m.ext.video_tools.job && !previewAsked.has(m.id)).slice(0, 6).map(m => m.id);
    if (need.length) { need.forEach(id => previewAsked.add(id)); post(`${API}/previews`, { ids: need }).then(r => { if (r.queued) poll(true); }).catch(() => {}); }
  }

  M.extend({ name: 'video_tools', loaded, badge, panel: (f, m) => panel(f, m), multi, summary, menu, quickLook });
})();
