/*
 * Feldtyp „recurrence“: Regel-Editor für Wiederholungsregeln (RFC 5545 RRULE) über dem Rohfeld.
 * Unterstützt: täglich · wöchentlich (Wochentage) · monatlich (am n. Tag / am 2. Dienstag) · jährlich,
 * Intervall, Ende (nie / am Datum / nach n Terminen), Ausnahmen (EXDATE) und eine lesbare Zusammenfassung.
 * Regeln, die der Regel-Editor nicht abbildet, bleiben unter „Erweitert“ bearbeitbar.
 * Gespeichert wird „FREQ=…;…“ und optional eine Zeile „EXDATE:JJJJ-MM-TT,…“ (Server normalisiert UNTIL nach UTC).
 */
import { t } from './_i18n.js';

const DAYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];
const dayName = i => [t('Montag'), t('Dienstag'), t('Mittwoch'), t('Donnerstag'), t('Freitag'), t('Samstag'), t('Sonntag')][i];
const FREQ = { DAILY: [t('täglich'), t('Tag(e)')], WEEKLY: [t('wöchentlich'), t('Woche(n)')], MONTHLY: [t('monatlich'), t('Monat(e)')], YEARLY: [t('jährlich'), t('Jahr(e)')] };
const NTH = { 1: t('ersten'), 2: t('zweiten'), 3: t('dritten'), 4: t('vierten'), '-1': t('letzten') };
const SUPPORTED = ['FREQ', 'INTERVAL', 'BYDAY', 'BYMONTHDAY', 'UNTIL', 'COUNT', 'WKST'];
const locale = () => document.documentElement.lang || 'de';
const pad = n => String(n).padStart(2, '0');
const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const fmtDate = s => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d).toLocaleDateString(locale(), { day: '2-digit', month: '2-digit', year: 'numeric' }); };
const list = a => a.length < 2 ? (a[0] || '') : a.slice(0, -1).join(', ') + ' ' + t('und') + ' ' + a[a.length - 1];
const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

/** Rohwert → Zustand */
export function parse(raw) {
  const s = { freq: '', interval: 1, byday: [], monthMode: 'day', monthday: 0, nth: 1, nthday: 'MO', end: 'never', until: '', count: 10, exdates: [], ok: true };
  for (const line of String(raw || '').split(/\r?\n/)) {
    const l = line.trim();
    if (!l) continue;
    const ex = /^EXDATE[^:]*:(.*)$/i.exec(l);
    if (ex) { ex[1].split(',').map(x => x.trim().replace(/^(\d{4})(\d{2})(\d{2}).*$/, '$1-$2-$3')).filter(x => /^\d{4}-\d{2}-\d{2}$/.test(x)).forEach(x => s.exdates.push(x)); continue; }
    for (const part of l.replace(/^RRULE:/i, '').split(';')) {
      if (!part) continue;
      const [k, v = ''] = part.toUpperCase().split('=');
      if (!SUPPORTED.includes(k)) { s.ok = false; continue; }
      if (k === 'FREQ') { if (FREQ[v]) s.freq = v; else s.ok = false; }
      if (k === 'INTERVAL') s.interval = Math.max(1, parseInt(v, 10) || 1);
      if (k === 'COUNT') { s.end = 'count'; s.count = parseInt(v, 10) || 1; }
      if (k === 'BYMONTHDAY') { s.monthMode = 'day'; s.monthday = parseInt(v, 10) || 0; if (v.includes(',') || s.monthday < 1) s.ok = false; }
      if (k === 'UNTIL') {
        const m = /^(\d{4})(\d{2})(\d{2})(?:T(\d{2})(\d{2})(\d{2})(Z?))?$/.exec(v);
        if (!m) { s.ok = false; continue; }
        s.end = 'until';
        s.until = m[4] && m[7] ? ymd(new Date(Date.UTC(+m[1], m[2] - 1, +m[3], +m[4], +m[5], +m[6]))) : `${m[1]}-${m[2]}-${m[3]}`;
      }
      if (k === 'BYDAY') {
        const days = v.split(',');
        const nth = /^(-1|[1-4])(MO|TU|WE|TH|FR|SA|SU)$/.exec(days[0]);
        if (nth && days.length === 1) { s.monthMode = 'nth'; s.nth = nth[1]; s.nthday = nth[2]; }
        else if (days.every(d => DAYS.includes(d))) s.byday = days;
        else s.ok = false;
      }
    }
  }
  if (s.monthMode === 'nth' && s.freq !== 'MONTHLY') s.ok = false;
  if (s.byday.length && s.freq !== 'WEEKLY') s.ok = false;
  s.exdates = [...new Set(s.exdates)].sort();
  return s;
}

/** Zustand → Rohwert */
export function build(s) {
  if (!s.freq) return '';
  const p = [`FREQ=${s.freq}`];
  if (s.interval > 1) p.push(`INTERVAL=${s.interval}`);
  if (s.freq === 'WEEKLY' && s.byday.length) p.push(`BYDAY=${DAYS.filter(d => s.byday.includes(d)).join(',')}`);
  if (s.freq === 'MONTHLY') {
    if (s.monthMode === 'nth') p.push(`BYDAY=${s.nth}${s.nthday}`);
    else if (s.monthday) p.push(`BYMONTHDAY=${s.monthday}`);
  }
  if (s.end === 'until' && s.until) p.push(`UNTIL=${s.until.replaceAll('-', '')}`);
  if (s.end === 'count') p.push(`COUNT=${Math.max(1, Math.min(1000, s.count || 1))}`);
  return p.join(';') + (s.exdates.length ? '\nEXDATE:' + s.exdates.join(',') : '');
}

/** Lesbare Zusammenfassung, z. B. „Jeden Montag und Mittwoch bis 31.12.2026“ */
export function summary(s, start) {
  if (!s.freq) return t('Keine Wiederholung – einmaliger Termin.');
  if (!s.ok) return t('Eigene Regel (siehe „Erweitert“).');
  const n = s.interval;
  let out;
  if (s.freq === 'DAILY') out = n > 1 ? t('Alle {n} Tage', { n }) : t('Täglich');
  else if (s.freq === 'WEEKLY') {
    const days = (s.byday.length ? s.byday : start ? [DAYS[(start.getDay() + 6) % 7]] : []).map(d => dayName(DAYS.indexOf(d)));
    out = n > 1 ? t('Alle {n} Wochen am {days}', { n, days: list(days) }) : (days.length ? t('Jeden {days}', { days: list(days) }) : t('Wöchentlich'));
  } else if (s.freq === 'MONTHLY') {
    const which = s.monthMode === 'nth' ? t('am {nth} {day}', { nth: NTH[s.nth], day: dayName(DAYS.indexOf(s.nthday)) })
      : t('am {d}. Tag', { d: s.monthday || (start ? start.getDate() : '…') });
    out = (n > 1 ? t('Alle {n} Monate', { n }) : t('Jeden Monat')) + ' ' + which;
  } else {
    out = n > 1 ? t('Alle {n} Jahre', { n }) : t('Jedes Jahr');
    if (start) out += ' ' + t('am {date}', { date: start.toLocaleDateString(locale(), { day: 'numeric', month: 'long' }) });
  }
  if (s.end === 'until' && s.until) out += ' ' + t('bis {date}', { date: fmtDate(s.until) });
  if (s.end === 'count') out += ', ' + t('{n}-mal', { n: s.count });
  if (s.exdates.length) out += ' ' + t('(außer {dates})', { dates: s.exdates.map(fmtDate).join(', ') });
  return out;
}

let uid = 0;
export function initRRule(scope = document) {
  scope.querySelectorAll('[data-rrule]:not([data-rrule-ready])').forEach(box => {
    box.dataset.rruleReady = '1';
    const raw = box.querySelector('[data-rrule-raw]'), ui = box.querySelector('[data-rrule-ui]');
    if (!raw || !ui) return;
    const form = box.closest('form') || document;
    const startEl = box.dataset.start ? form.querySelector(`[name="${CSS.escape(box.dataset.start)}"]`) : null;
    const start = () => { const v = startEl?.value || ''; const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(v); return m ? new Date(+m[1], m[2] - 1, +m[3]) : null; };
    const id = 'rr' + (++uid);
    const opt = (v, l, sel) => `<option value="${v}"${sel ? ' selected' : ''}>${esc(l)}</option>`;
    ui.innerHTML = `
      <div class="rr-row">
        <div class="rr-f"><label for="${id}-freq">${esc(t('Wiederholen'))}</label>
          <select id="${id}-freq" data-rr="freq">${opt('', t('nicht (einmalig)'))}${Object.entries(FREQ).map(([k, [l]]) => opt(k, l)).join('')}</select></div>
        <div class="rr-f" data-rr-when="any"><label for="${id}-int">${esc(t('Intervall: alle'))}</label>
          <span class="rr-inline"><input id="${id}-int" type="number" min="1" max="99" inputmode="numeric" data-rr="interval"> <span data-rr-unit></span></span></div>
      </div>
      <fieldset class="rr-set" data-rr-when="WEEKLY"><legend>${esc(t('An diesen Tagen'))}</legend><div class="rr-days">
        ${DAYS.map((d, i) => `<label class="rr-day"><input type="checkbox" value="${d}" data-rr="byday"><span><abbr title="${esc(dayName(i))}">${esc(dayName(i).slice(0, 2))}</abbr></span></label>`).join('')}
      </div></fieldset>
      <fieldset class="rr-set" data-rr-when="MONTHLY"><legend>${esc(t('Im Monat'))}</legend>
        <label class="f-check"><input type="radio" name="${id}-mm" value="day" data-rr="monthMode"> <span>${esc(t('am Tag'))}</span></label>
        <input type="number" min="1" max="31" inputmode="numeric" data-rr="monthday" aria-label="${esc(t('Tag im Monat'))}" class="rr-num">
        <label class="f-check"><input type="radio" name="${id}-mm" value="nth" data-rr="monthMode"> <span>${esc(t('am'))}</span></label>
        <select data-rr="nth" aria-label="${esc(t('Welche Woche'))}">${Object.entries(NTH).map(([k, l]) => opt(k, l)).join('')}</select>
        <select data-rr="nthday" aria-label="${esc(t('Wochentag'))}">${DAYS.map((d, i) => opt(d, dayName(i))).join('')}</select>
      </fieldset>
      <fieldset class="rr-set" data-rr-when="any"><legend>${esc(t('Endet'))}</legend>
        <label class="f-check"><input type="radio" name="${id}-end" value="never" data-rr="end"> <span>${esc(t('nie'))}</span></label>
        <span class="rr-inline"><label class="f-check"><input type="radio" name="${id}-end" value="until" data-rr="end"> <span>${esc(t('am'))}</span></label>
          <input type="date" data-rr="until" aria-label="${esc(t('Enddatum der Wiederholung'))}"></span>
        <span class="rr-inline"><label class="f-check"><input type="radio" name="${id}-end" value="count" data-rr="end"> <span>${esc(t('nach'))}</span></label>
          <input type="number" min="1" max="1000" inputmode="numeric" data-rr="count" aria-label="${esc(t('Anzahl der Termine'))}" class="rr-num"> ${esc(t('Terminen'))}</span>
      </fieldset>
      <fieldset class="rr-set" data-rr-when="any"><legend>${esc(t('Ausnahmen (fällt aus am)'))}</legend>
        <ul class="rr-ex" data-rr-ex role="list"></ul>
        <span class="rr-inline"><input type="date" data-rr-exnew aria-label="${esc(t('Datum der Ausnahme'))}">
          <button type="button" class="adm-btn adm-btn--small" data-rr-exadd>${esc(t('Ausnahme hinzufügen'))}</button></span>
      </fieldset>
      <p class="rr-note" data-rr-note hidden>${esc(t('Diese Regel lässt sich nur unter „Erweitert“ bearbeiten.'))}</p>
      <p class="rr-summary" data-rr-summary aria-live="polite"></p>`;
    ui.hidden = false;
    const q = sel => ui.querySelector(sel), qa = sel => [...ui.querySelectorAll(sel)];
    let s = parse(raw.value);

    const render = () => {
      const on = s.ok;
      q('[data-rr=freq]').value = s.freq;
      q('[data-rr=interval]').value = s.interval;
      q('[data-rr-unit]').textContent = FREQ[s.freq]?.[1] || '';
      qa('[data-rr=byday]').forEach(c => { c.checked = s.byday.includes(c.value); });
      qa('[data-rr=monthMode]').forEach(r => { r.checked = r.value === s.monthMode; });
      q('[data-rr=monthday]').value = s.monthday || '';
      q('[data-rr=nth]').value = String(s.nth);
      q('[data-rr=nthday]').value = s.nthday;
      qa('[data-rr=end]').forEach(r => { r.checked = r.value === s.end; });
      q('[data-rr=until]').value = s.until;
      q('[data-rr=count]').value = s.count;
      q('[data-rr=until]').disabled = s.end !== 'until';
      q('[data-rr=count]').disabled = s.end !== 'count';
      q('[data-rr=monthday]').disabled = s.monthMode !== 'day';
      q('[data-rr=nth]').disabled = q('[data-rr=nthday]').disabled = s.monthMode !== 'nth';
      qa('[data-rr-when]').forEach(el => { const w = el.dataset.rrWhen; el.hidden = !on || !s.freq || (w !== 'any' && w !== s.freq); });
      q('[data-rr=freq]').disabled = !on;
      q('[data-rr-note]').hidden = on;
      q('[data-rr-ex]').innerHTML = s.exdates.map(x => `<li><span>${esc(fmtDate(x))}</span> <button type="button" class="icon-btn" data-rr-exdel="${x}" aria-label="${esc(t('Ausnahme {date} entfernen', { date: fmtDate(x) }))}">✕</button></li>`).join('');
      q('[data-rr-summary]').textContent = summary(s, start());
    };
    const write = () => { raw.value = build(s); raw.dispatchEvent(new Event('input', { bubbles: true })); render(); };
    // Sinnvolle Vorgaben aus dem Beginn (Wochentag, Tag im Monat)
    const defaults = () => {
      const d = start();
      if (s.freq === 'WEEKLY' && !s.byday.length && d) s.byday = [DAYS[(d.getDay() + 6) % 7]];
      if (s.freq === 'MONTHLY' && !s.monthday && d) {
        s.monthday = d.getDate();
        s.nthday = DAYS[(d.getDay() + 6) % 7];
        s.nth = d.getDate() + 7 > new Date(d.getFullYear(), d.getMonth() + 1, 0).getDate() ? '-1' : String(Math.ceil(d.getDate() / 7));
      }
    };

    ui.addEventListener('change', e => {
      const k = e.target.dataset.rr;
      if (!k) return;
      if (k === 'freq') { s.freq = e.target.value; defaults(); }
      else if (k === 'interval') s.interval = Math.max(1, Math.min(99, parseInt(e.target.value, 10) || 1));
      else if (k === 'byday') s.byday = qa('[data-rr=byday]').filter(c => c.checked).map(c => c.value);
      else if (k === 'monthMode' || k === 'end') s[k] = e.target.value;
      else if (k === 'monthday') s.monthday = Math.max(1, Math.min(31, parseInt(e.target.value, 10) || 1));
      else if (k === 'count') s.count = Math.max(1, Math.min(1000, parseInt(e.target.value, 10) || 1));
      else s[k] = e.target.value;
      if (k === 'end' && s.end === 'until' && !s.until) { const d = start() || new Date(); s.until = ymd(new Date(d.getFullYear(), 11, 31)); }
      write();
    });
    ui.addEventListener('click', e => {
      const del = e.target.closest('[data-rr-exdel]');
      if (del) { s.exdates = s.exdates.filter(x => x !== del.dataset.rrExdel); write(); q('[data-rr-exnew]').focus(); return; }
      if (e.target.closest('[data-rr-exadd]')) {
        const v = q('[data-rr-exnew]').value;
        if (/^\d{4}-\d{2}-\d{2}$/.test(v) && !s.exdates.includes(v)) { s.exdates = [...s.exdates, v].sort(); write(); }
        q('[data-rr-exnew]').value = '';
        q('[data-rr-exnew]').focus();
      }
    });
    ui.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('[data-rr-exnew]')) { e.preventDefault(); q('[data-rr-exadd]').click(); } });
    // Rohfeld von Hand geändert → Regel-Editor nachziehen
    raw.addEventListener('input', e => { if (e.isTrusted) { s = parse(raw.value); render(); } });
    startEl?.addEventListener('change', render);
    render();
  });
}
