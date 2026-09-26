// SPDX-License-Identifier: MIT
/*
 * Kalkulation & Angebote (Erweiterung „kalkulation“) – Verwaltung.
 *  - Editor (#kx-app): Positionen als Tabelle, Live-Summen (einmalig, monatlich, jährlich, USt, brutto), interne Sicht
 *    (Fremdkosten, Stunden, Marge, effektiver Stundensatz) und Kundenansicht, Katalog-Dialog, Grundlage je Kalkulation,
 *    automatisches Speichern (JSON, Stand-Prüfung gegen gleichzeitiges Bearbeiten), Tastatur-Workflow.
 *  - Übrige Seiten: Zahlenfelder prüfen/formatieren, Zeilen im Katalog und bei den Stundensätzen ergänzen, Rückfragen.
 * Rechenregeln = src/Engine.php (bei Änderungen beide Seiten anpassen). Keine Inline-Skripte (CSP 'self').
 * Texte: t(Text) – Übersetzung aus lang/{locale}.php der Erweiterung (Wörterbuch #cms-i18n im Layout).
 */
(() => {
  const d = document;
  const $ = (s, c = d) => c.querySelector(s);
  const $$ = (s, c = d) => [...c.querySelectorAll(s)];
  let dict = null;
  const t = (text, params = {}) => {
    if (dict === null) { try { dict = JSON.parse($('#cms-i18n')?.textContent || '{}'); } catch { dict = {}; } }
    let out = dict[text] ?? text;
    for (const [k, v] of Object.entries(params)) out = out.replaceAll('{' + k + '}', String(v));
    return out;
  };
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

  // ------------------------------------------------------------ Zahlen (Regeln wie src/Num.php)
  /** „1.234,5“ | „1234.5“ | „1.234“ → Zahl; leer → null; ungültig → NaN */
  const parseNum = v => {
    if (typeof v === 'number') return Number.isFinite(v) ? v : NaN;
    let s = String(v ?? '').replace(/[\s  €%]/g, '');
    if (s === '') return null;
    if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
    else if (/^-?\d{1,3}(\.\d{3})+$/.test(s)) s = s.replace(/\./g, '');
    return /^-?\d+(\.\d+)?$/.test(s) ? parseFloat(s) : NaN;
  };
  const nf = {};
  const fmt = (v, dec = 2) => (nf[dec] ??= new Intl.NumberFormat('de-DE', { minimumFractionDigits: dec, maximumFractionDigits: dec })).format(v);
  const nfIn = new Intl.NumberFormat('de-DE', { maximumFractionDigits: 4 });
  const fmtIn = v => v === null || v === undefined || Number.isNaN(v) ? '' : nfIn.format(v);
  const SYM = { EUR: '€', CHF: 'CHF', USD: 'US-$', GBP: '£' };
  const r2 = v => { const s = v < 0 ? -1 : 1; return s * Math.floor(Math.abs(v) * 100 + 0.5 + 1e-7) / 100; };

  // ------------------------------------------------------------ Allgemein (alle Seiten der Erweiterung)
  const checkNum = inp => {
    const v = parseNum(inp.value);
    const bad = Number.isNaN(v);
    inp.setAttribute('aria-invalid', bad ? 'true' : 'false');
    if (!bad && v !== null && inp.dataset.fmt !== 'raw') inp.value = fmtIn(v);
    return !bad;
  };
  d.addEventListener('change', e => {
    const el = e.target;
    if (el.matches('[data-kx-autosubmit]')) el.form?.submit();
    if (el.matches('form:not([data-kx-live-form]) [data-num]')) checkNum(el);
    if (el.matches('[data-kx-bill]')) billState(el.closest('[data-kx-item]'));
  });
  d.addEventListener('submit', e => {
    const f = e.target;
    if (f.matches('[data-kx-confirm]') && !window.confirm(f.dataset.kxConfirm)) e.preventDefault();
    if (f.matches('[data-kx-checknum]')) {
      const bad = $$('[data-num]', f).filter(i => !checkNum(i));
      if (bad.length) { e.preventDefault(); bad[0].focus(); }
    }
  });
  d.addEventListener('click', e => {
    const b = e.target.closest('button');
    if (!b) return;
    if (b.matches('[data-kx-print]')) { window.print(); return; }
    if (b.matches('[data-kx-add-row]')) {
      // Neue Zeile aus <template> (Stundensätze in den Einstellungen, Pakete im Katalog)
      const tpl = $('#' + b.dataset.kxAddRow);
      const list = $(b.dataset.kxTarget);
      if (!tpl || !list) return;
      const n = Date.now() % 1e7;
      const html = tpl.innerHTML.replaceAll('__N__', 'n' + n);
      list.insertAdjacentHTML('beforeend', html);
      const row = list.lastElementChild;
      if (row?.matches('[data-kx-item]')) billState(row);
      row?.querySelector('input:not([type=hidden]),textarea')?.focus();
    }
    if (b.matches('[data-kx-move]')) {
      const row = b.closest('[data-kx-item]');
      if (!row) return;
      const sib = b.dataset.kxMove === 'up' ? row.previousElementSibling : row.nextElementSibling;
      if (sib?.matches('[data-kx-item]')) { b.dataset.kxMove === 'up' ? sib.before(row) : sib.after(row); b.focus(); }
    }
  });
  /** Katalog: Felder für einmalig/monatlich je nach Abrechnung ein- bzw. ausgrauen */
  function billState(row) {
    if (!row) return;
    const bill = $('[data-kx-bill]', row)?.value || 'both';
    for (const [sel, on] of [['[data-kx-part=once]', bill !== 'monthly'], ['[data-kx-part=monthly]', bill !== 'once']]) {
      $$(sel, row).forEach(p => { p.classList.toggle('is-off', !on); });
    }
  }
  $$('[data-kx-item]').forEach(billState);

  // ------------------------------------------------------------ Editor
  const app = $('#kx-app');
  if (!app) return;
  const boot = JSON.parse($('#kx-boot').textContent || '{}');
  const csrf = () => $('#adm-csrf')?.value || '';
  const src = boot.calc;
  const DOC = ['title', 'customer', 'address', 'contact', 'project', 'valid_until', 'intro', 'payment', 'validity', 'term', 'closing', 'note',
    'discount_once', 'discount_monthly', 'number', 'status', 'calc_date'];
  const calc = { params: structuredClone(src.params), positions: structuredClone(src.positions) };
  for (const k of DOC) calc[k] = src[k] ?? '';
  let rev = src.rev;
  const bad = new Map();          // ungültige Eingaben: Schlüssel → { el, label }
  let dirty = false, saving = false, timer = 0, conflict = false, lastRun = null;
  const state = $('[data-kx-state]');
  const tableBox = $('[data-kx-table]');
  const preview = $('[data-kx-preview]');
  const phone = matchMedia('(max-width:640px)');   // Telefon: nur Kundenansicht (lesen)
  phone.addEventListener?.('change', () => update());
  const pid = () => 'p' + Math.random().toString(36).slice(2, 12).padEnd(10, '0');
  const cur = () => calc.params.currency || 'EUR';
  const money = v => v === null || v === undefined ? '–' : fmt(v) + ' ' + (SYM[cur()] || cur());
  const rateKeys = () => Object.keys(calc.params.rates || {});
  const rateLabel = k => k === 'fixed' ? t('Festpreis') : (calc.params.rates?.[k]?.label || k);
  const num = v => v === null || v === undefined || v === '' ? null : (typeof v === 'number' ? v : (Number.isNaN(parseNum(v)) ? null : parseNum(v)));

  // ---------------------------------------------------------- Rechnen (= Engine::run)
  function roundUnit(v, p) {
    const step = Number(p.round_step || 0);
    if (step <= 0) return r2(v);
    const x = v / step;
    return r2((p.round_mode === 'up' ? Math.ceil(x - 1e-9) : Math.floor(x + 0.5 + 1e-9)) * step);
  }
  function line(pos, p) {
    const warn = [];
    const qty = num(pos.qty), amount = num(pos.amount), cost = num(pos.cost) ?? 0, disc = num(pos.discount) ?? 0;
    const name = (pos.name || '').trim() || t('Position');
    let labour, hours, rate = null;
    if (pos.basis === 'fixed') { labour = amount ?? 0; hours = num(pos.hours_internal) ?? 0; }
    else {
      const r = p.rates?.[pos.basis];
      rate = r ? num(r.rate) : null;
      hours = amount ?? 0;
      if (!r) warn.push(t('„{name}“: Der Stundensatz dieser Position gibt es in der Grundlage nicht mehr.', { name }));
      else if (rate === null && amount !== null) warn.push(t('Stundensatz „{rate}“ fehlt in der Grundlage – Positionen damit ergeben 0.', { rate: r.label || pos.basis }));
      labour = hours * (rate ?? 0);
    }
    const bufF = pos.buffer ? 1 + (num(p.buffer) ?? 0) / 100 : 1;
    const third = cost * (1 + (num(p.markup) ?? 0) / 100);
    const unit = roundUnit(labour * bufF + third, p);
    const q = qty ?? 0;
    const total = r2(q * unit * (1 - disc / 100));
    return { kind: pos.kind === 'monthly' ? 'monthly' : 'once', unit_price: unit, total, counted: !pos.optional && !pos.alternative,
      labour: r2(labour), buffer: r2(labour * (bufF - 1)), third: r2(third), rate, hours, cost_total: r2(q * cost), hours_total: q * hours, warnings: warn };
  }
  function run() {
    const p = calc.params;
    const lines = {}, warn = new Set();
    const sum = { once: 0, monthly: 0 }, int = { once: { cost: 0, hours: 0 }, monthly: { cost: 0, hours: 0 } };
    for (const pos of calc.positions) {
      const l = line(pos, p);
      lines[pos.id] = l;
      l.warnings.forEach(w => warn.add(w));
      if (!l.counted) continue;
      sum[l.kind] += l.total; int[l.kind].cost += l.cost_total; int[l.kind].hours += l.hours_total;
    }
    const vat = num(p.vat) ?? 0, costRate = num(p.cost_rate);
    const tt = {};
    for (const k of ['once', 'monthly']) {
      const s = r2(sum[k]), dPct = num(calc['discount_' + k]) ?? 0, disc = r2(s * dPct / 100), net = r2(s - disc), v = r2(net * vat / 100);
      const cost = r2(int[k].cost), hours = Math.round(int[k].hours * 1e4) / 1e4, staff = costRate !== null ? r2(hours * costRate) : 0;
      const margin = r2(net - cost - staff);
      tt[k] = { sum: s, discount_pct: dPct, discount: disc, net, vat: v, gross: r2(net + v), cost, hours, staff, margin,
        margin_pct: net !== 0 ? Math.round(margin / net * 10000) / 100 : null, eff_rate: hours > 0 ? r2((net - cost) / hours) : null };
    }
    tt.yearly = { net: r2(tt.monthly.net * 12), vat: r2(tt.monthly.vat * 12), gross: r2(tt.monthly.gross * 12) };
    tt.first_year = { net: r2(tt.once.net + tt.yearly.net), gross: r2(tt.once.gross + tt.yearly.gross), margin: r2(tt.once.margin + tt.monthly.margin * 12) };
    tt.vat_pct = vat;
    return { lines, totals: tt, warnings: [...warn] };
  }

  // ---------------------------------------------------------- Tabelle der Positionen
  const COLS = 11;
  function basisOptions(sel) {
    return rateKeys().map(k => `<option value="${esc(k)}"${k === sel ? ' selected' : ''}>${esc(rateLabel(k))}</option>`).join('')
      + `<option value="fixed"${sel === 'fixed' ? ' selected' : ''}>${esc(t('Festpreis'))}</option>`
      + (sel !== 'fixed' && !rateKeys().includes(sel) ? `<option value="${esc(sel)}" selected>${esc(sel)} (${esc(t('fehlt'))})</option>` : '');
  }
  function rowHtml(pos, i, open) {
    const n = i + 1, id = esc(pos.id), fixed = pos.basis === 'fixed';
    const lab = f => esc(t('{field}, Position {n}', { field: f, n }));
    const numIn = (k, field, extra = '') => `<input class="kx-in kx-in--num" data-k="${k}" inputmode="decimal" autocomplete="off" value="${esc(fmtIn(num(pos[k])))}" aria-label="${lab(field)}"${extra}>`;
    return `<tbody class="kx-p${pos.optional || pos.alternative ? ' is-extra' : ''}" data-id="${id}">
<tr class="kx-row">
  <th scope="row" class="kx-c-pos"><span class="kx-posn">${n}</span></th>
  <td class="kx-c-name"><input class="kx-in kx-in--name" data-k="name" value="${esc(pos.name)}" maxlength="200" aria-label="${lab(t('Bezeichnung'))}" placeholder="${esc(t('Bezeichnung'))}">
    <div class="kx-sub" data-sub></div></td>
  <td class="kx-c-kind"><select class="kx-in" data-k="kind" aria-label="${lab(t('Art'))}"><option value="once"${pos.kind !== 'monthly' ? ' selected' : ''}>${esc(t('einmalig'))}</option><option value="monthly"${pos.kind === 'monthly' ? ' selected' : ''}>${esc(t('monatlich'))}</option></select></td>
  <td class="kx-c-num">${numIn('qty', t('Menge'))}</td>
  <td class="kx-c-unit"><input class="kx-in" data-k="unit" list="kx-units" value="${esc(pos.unit)}" maxlength="40" aria-label="${lab(t('Einheit'))}"></td>
  <td class="kx-c-basis kx-int"><select class="kx-in" data-k="basis" aria-label="${lab(t('Preisbasis'))}">${basisOptions(pos.basis)}</select></td>
  <td class="kx-c-num kx-int">${numIn('amount', fixed ? t('Festpreis je Einheit') : t('Stunden je Einheit'), ` placeholder="${esc(fixed ? SYM[cur()] || cur() : t('Std.'))}"`)}</td>
  <td class="kx-c-num kx-int">${numIn('cost', t('Fremdkosten je Einheit (Einkauf)'))}</td>
  <td class="kx-c-out"><output data-o="unit"></output></td>
  <td class="kx-c-out kx-c-total"><output data-o="total"></output></td>
  <td class="kx-c-act"><button type="button" class="icon-btn" data-a="more" aria-expanded="${open ? 'true' : 'false'}" aria-controls="kx-more-${id}" aria-label="${esc(t('Details zu Position {n}', { n }))}" title="${esc(t('Details'))}">⋯</button>
    <button type="button" class="icon-btn icon-btn--danger" data-a="del" aria-label="${esc(t('Position {n} entfernen', { n }))}" title="${esc(t('Entfernen'))}">✕</button></td>
</tr>
<tr class="kx-more" id="kx-more-${id}"${open ? '' : ' hidden'}><td colspan="${COLS}">
  <div class="kx-more__grid">
    <div class="f"><label for="g-${id}">${esc(t('Gruppe / Leistung'))}</label><input id="g-${id}" class="kx-in" data-k="group" value="${esc(pos.group)}" maxlength="120"></div>
    <div class="f kx-more__desc"><label for="d-${id}">${esc(t('Beschreibung (erscheint im Angebot)'))}</label><textarea id="d-${id}" class="kx-in" data-k="desc" rows="2">${esc(pos.desc)}</textarea></div>
    <div class="f kx-more__disc"><label for="r-${id}">${esc(t('Rabatt %'))}</label><input id="r-${id}" class="kx-in kx-in--num" data-k="discount" inputmode="decimal" autocomplete="off" value="${esc(fmtIn(num(pos.discount)))}"></div>
    <div class="f kx-int"${fixed ? '' : ' hidden'} data-fixed-only><label for="h-${id}">${esc(t('Std. intern je Einheit'))}</label><input id="h-${id}" class="kx-in kx-in--num" data-k="hours_internal" inputmode="decimal" value="${esc(fmtIn(num(pos.hours_internal)))}"><span class="f-help">${esc(t('nur für Marge und effektiven Stundensatz'))}</span></div>
    <div class="f kx-int kx-more__note"><label for="n-${id}">${esc(t('Interne Notiz'))}</label><textarea id="n-${id}" class="kx-in" data-k="note" rows="2" data-kia-off>${esc(pos.note)}</textarea></div>
    <fieldset class="kx-flags"><legend class="sr-only">${esc(t('Optionen'))}</legend>
      <label class="f-check kx-int"><input type="checkbox" data-k="buffer"${pos.buffer ? ' checked' : ''}> ${esc(t('Projektpuffer'))} <span class="adm-muted" data-bufpct></span></label>
      <label class="f-check"><input type="checkbox" data-k="optional"${pos.optional ? ' checked' : ''}> ${esc(t('Optional (nicht in der Summe)'))}</label>
      <label class="f-check"><input type="checkbox" data-k="alternative"${pos.alternative ? ' checked' : ''}> ${esc(t('Alternative (nicht in der Summe)'))}</label>
    </fieldset>
    <div class="kx-more__tools">
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-a="up"${i === 0 ? ' disabled' : ''}>↑ ${esc(t('nach oben'))}</button>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-a="down"${i === calc.positions.length - 1 ? ' disabled' : ''}>↓ ${esc(t('nach unten'))}</button>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-a="dup">${esc(t('Duplizieren'))}</button>
    </div>
    <p class="kx-more__calc kx-int adm-muted" data-o="explain"></p>
  </div>
</td></tr>
</tbody>`;
  }
  function renderTable(focus = null) {
    const open = new Set($$('.kx-more:not([hidden])', tableBox).map(r => r.closest('tbody').dataset.id));
    const head = `<thead><tr>
<th scope="col" class="kx-c-pos">${esc(t('Pos.'))}</th><th scope="col">${esc(t('Leistung'))}</th><th scope="col">${esc(t('Art'))}</th>
<th scope="col" class="kx-num">${esc(t('Menge'))}</th><th scope="col">${esc(t('Einheit'))}</th><th scope="col" class="kx-int">${esc(t('Preisbasis'))}</th>
<th scope="col" class="kx-num kx-int">${esc(t('Std. bzw. Preis'))}<span class="kx-th2">${esc(t('je Einheit'))}</span></th>
<th scope="col" class="kx-num kx-int">${esc(t('Fremdkosten'))}<span class="kx-th2">${esc(t('Einkauf je Einh.'))}</span></th>
<th scope="col" class="kx-num">${esc(t('Einzelpreis'))}</th><th scope="col" class="kx-num">${esc(t('Gesamt'))}</th>
<th scope="col"><span class="sr-only">${esc(t('Aktionen'))}</span></th></tr></thead>`;
    const body = calc.positions.length ? calc.positions.map((p, i) => rowHtml(p, i, open.has(p.id))).join('')
      : `<tbody><tr><td colspan="${COLS}" class="kx-emptyrow">${esc(t('Noch keine Positionen – „Aus Katalog …“ oder „+ Position“.'))}</td></tr></tbody>`;
    tableBox.innerHTML = `<table class="kx-table"><caption class="sr-only">${esc(t('Positionen der Kalkulation'))}</caption>${head}${body}</table>`;
    bad.forEach((v, k) => { if (!d.contains(v.el)) bad.delete(k); });
    update();
    if (focus) {
      const el = $(`tbody[data-id="${focus.id}"] [data-k="${focus.k || 'name'}"]`, tableBox);
      if (el) { el.focus(); if (el.select && focus.select !== false) el.select(); }
    }
  }
  const posOf = el => calc.positions.findIndex(p => p.id === el.closest('tbody[data-id]')?.dataset.id);

  // ---------------------------------------------------------- Ausgabe: Zeilen, Summen, Kundenansicht
  function update() {
    const r = lastRun = run();
    const p = calc.params;
    for (const tb of $$('tbody[data-id]', tableBox)) {
      const pos = calc.positions.find(x => x.id === tb.dataset.id);
      const l = r.lines[tb.dataset.id];
      if (!pos || !l) continue;
      $('[data-o=unit]', tb).textContent = money(l.unit_price);
      $('[data-o=total]', tb).textContent = l.counted ? money(l.total) : '(' + money(l.total) + ')';
      tb.classList.toggle('is-extra', !l.counted);
      tb.classList.toggle('is-warn', l.warnings.length > 0);
      const badges = [];
      if (pos.group) badges.push(`<span class="kx-grp">${esc(pos.group)}</span>`);
      if (pos.optional) badges.push(`<span class="adm-badge adm-badge--muted">${esc(t('optional'))}</span>`);
      if (pos.alternative) badges.push(`<span class="adm-badge adm-badge--muted">${esc(t('Alternative'))}</span>`);
      if (num(pos.discount)) badges.push(`<span class="adm-badge adm-badge--muted">${esc(t('Rabatt'))} ${esc(fmtIn(num(pos.discount)))} %</span>`);
      if (pos.buffer) badges.push(`<span class="adm-badge adm-badge--muted kx-int">${esc(t('Puffer'))}</span>`);
      if (pos.desc) badges.push(`<span class="kx-desc-dot" title="${esc(pos.desc)}">${esc(t('mit Beschreibung'))}</span>`);
      if (l.warnings.length) badges.push(`<span class="adm-badge adm-badge--adm-warn" title="${esc(l.warnings.join(' '))}">${esc(t('Satz fehlt'))}</span>`);
      $('[data-sub]', tb).innerHTML = badges.join(' ');
      const bp = $('[data-bufpct]', tb);
      if (bp) bp.textContent = '(' + fmtIn(num(p.buffer) ?? 0) + ' %)';
      const ex = $('[data-o=explain]', tb);
      if (ex) {
        const parts = [];
        if (pos.basis === 'fixed') parts.push(t('Festpreis {v}', { v: money(num(pos.amount) ?? 0) }));
        else parts.push(t('{h} Std. × {rate} = {v}', { h: fmtIn(num(pos.amount) ?? 0), rate: l.rate === null ? '?' : money(l.rate), v: money(l.labour) }));
        if (pos.buffer) parts.push(t('Puffer {v}', { v: money(l.buffer) }));
        if (num(pos.cost)) parts.push(t('Fremdleistung {cost} + {pct} % = {v}', { cost: money(num(pos.cost)), pct: fmtIn(num(p.markup) ?? 0), v: money(l.third) }));
        parts.push(t('Einzelpreis {v}', { v: money(l.unit_price) }));
        if (l.cost_total) parts.push(t('Einkauf gesamt {v}', { v: money(l.cost_total) }));
        if (l.hours_total) parts.push(t('{h} Std. gesamt', { h: fmtIn(l.hours_total) }));
        ex.textContent = parts.join(' · ');
      }
    }
    renderTotals(r);
    if (app.dataset.view === 'customer' || phone.matches) renderPreview(r);
    $('[data-kx-basis-sum]').textContent = rateKeys().map(k => rateLabel(k) + ' ' + (num(p.rates[k].rate) === null ? '–' : money(num(p.rates[k].rate)))).join(' · ')
      + ' · ' + t('Aufschlag {v} %', { v: fmtIn(num(p.markup) ?? 0) }) + ' · ' + t('Puffer {v} %', { v: fmtIn(num(p.buffer) ?? 0) }) + ' · ' + t('USt {v} %', { v: fmtIn(num(p.vat) ?? 0) });
  }
  let liveTimer = 0;
  function renderTotals(r) {
    const T = r.totals;
    const hasM = T.monthly.sum !== 0 || calc.positions.some(p => p.kind === 'monthly');
    const hasO = T.once.sum !== 0 || calc.positions.some(p => p.kind !== 'monthly') || !hasM;
    const cols = [hasO && 'once', hasM && 'monthly'].filter(Boolean);
    const H = { once: t('Einmalig'), monthly: t('Monatlich') };
    const row = (label, f, cls = '') => `<tr class="${cls}"><th scope="row">${label}</th>${cols.map(k => `<td>${f(T[k], k)}</td>`).join('')}</tr>`;
    let h = `<table class="kx-tot"><caption class="sr-only">${esc(t('Summen'))}</caption><thead><tr><td></td>${cols.map(k => `<th scope="col">${esc(H[k])}</th>`).join('')}</tr></thead><tbody>`;
    h += row(esc(t('Summe Positionen')), x => money(x.sum));
    if (cols.some(k => T[k].discount)) h += row(esc(t('Nachlass')), x => x.discount ? '−' + money(x.discount) + ` <small>(${fmtIn(x.discount_pct)} %)</small>` : '–');
    h += row(esc(t('Netto')), x => `<strong>${money(x.net)}</strong>`, 'kx-tot__net');
    h += row(esc(t('USt {v} %', { v: fmtIn(T.vat_pct) })), x => money(x.vat));
    h += row(esc(t('Brutto')), x => money(x.gross), 'kx-tot__gross');
    h += '</tbody></table>';
    if (hasM) {
      h += `<dl class="kx-dl"><div><dt>${esc(t('Jährlich (12 × monatlich) netto'))}</dt><dd>${money(T.yearly.net)}</dd></div>
<div><dt>${esc(t('Jährlich brutto'))}</dt><dd>${money(T.yearly.gross)}</dd></div>
${hasO ? `<div class="kx-dl__strong"><dt>${esc(t('Erstes Jahr gesamt netto'))}</dt><dd>${money(T.first_year.net)}</dd></div>` : ''}</dl>`;
    }
    $('[data-kx-totals]').innerHTML = h;
    // intern
    const pct = v => v === null ? '–' : fmt(v, 1) + ' %';
    let g = `<table class="kx-tot"><caption class="sr-only">${esc(t('Intern: Kosten & Marge'))}</caption><thead><tr><td></td>${cols.map(k => `<th scope="col">${esc(H[k])}</th>`).join('')}</tr></thead><tbody>`;
    g += row(esc(t('Umsatz netto')), x => money(x.net));
    g += row(esc(t('Fremdkosten (Einkauf)')), x => x.cost ? '−' + money(x.cost) : '–');
    g += row(esc(t('Stunden')), x => x.hours ? fmt(x.hours, 2).replace(/,00$/, '') + ' ' + esc(t('Std.')) : '–');
    if (num(calc.params.cost_rate) !== null) g += row(esc(t('Selbstkosten Stunden')), x => x.staff ? '−' + money(x.staff) : '–');
    g += row(esc(t('Marge')), x => `<strong class="${x.margin < 0 ? 'kx-neg' : ''}">${money(x.margin)}</strong>`, 'kx-tot__net');
    g += row(esc(t('Marge in %')), x => `<span class="${x.margin_pct !== null && x.margin_pct < 0 ? 'kx-neg' : ''}">${pct(x.margin_pct)}</span>`);
    g += row(esc(t('Effektiver Stundensatz')), x => x.eff_rate === null ? '–' : money(x.eff_rate));
    g += '</tbody></table>';
    if (hasM && hasO) g += `<dl class="kx-dl"><div class="kx-dl__strong"><dt>${esc(t('Marge erstes Jahr'))}</dt><dd>${money(T.first_year.margin)}</dd></div></dl>`;
    g += `<p class="f-help">${esc(num(calc.params.cost_rate) === null ? t('Marge = Umsatz − Fremdkosten (ohne eigene Stunden). Mit „Selbstkosten je Stunde“ in der Grundlage auch nach Personalkosten.') : t('Marge = Umsatz − Fremdkosten − Stunden × Selbstkosten.'))} ${esc(t('Effektiver Stundensatz = (Umsatz − Fremdkosten) ÷ Stunden.'))}</p>`;
    if (r.warnings.length) g = `<ul class="kx-warn" role="list">${r.warnings.map(w => `<li>${esc(w)}</li>`).join('')}</ul>` + g;
    $('[data-kx-internal]').innerHTML = g;
    $('[data-kx-mini]').innerHTML = `<span>${esc(t('Einmalig'))} <b>${money(T.once.net)}</b></span><span>${esc(t('Monatlich'))} <b>${money(T.monthly.net)}</b></span><span class="kx-int">${esc(t('Marge'))} <b class="${T.once.margin + T.monthly.margin < 0 ? 'kx-neg' : ''}">${pct(T.once.net + T.monthly.net ? (T.once.margin + T.monthly.margin) / (T.once.net + T.monthly.net) * 100 : null)}</b></span>`;
    clearTimeout(liveTimer);
    liveTimer = setTimeout(() => {
      $('[data-kx-live]').textContent = t('Summen aktualisiert: einmalig netto {a}, monatlich netto {b}.', { a: money(T.once.net), b: money(T.monthly.net) });
    }, 900);
  }
  function renderPreview(r) {
    const T = r.totals;
    const groups = [['once', t('Einmalige Leistungen')], ['monthly', t('Monatliche Leistungen')]];
    let n = 0, h = '';
    for (const [k, label] of groups) {
      const items = calc.positions.filter(p => (p.kind === 'monthly' ? 'monthly' : 'once') === k);
      if (!items.length) continue;
      h += `<table class="kx-ctable"><caption>${esc(label)}</caption><thead><tr><th scope="col">${esc(t('Pos.'))}</th><th scope="col">${esc(t('Leistung'))}</th><th scope="col" class="kx-num">${esc(t('Menge'))}</th><th scope="col" class="kx-num">${esc(k === 'monthly' ? t('Einzelpreis mtl.') : t('Einzelpreis'))}</th><th scope="col" class="kx-num">${esc(k === 'monthly' ? t('Gesamt mtl.') : t('Gesamt'))}</th></tr></thead><tbody>`;
      for (const p of items) {
        const l = r.lines[p.id]; n++;
        const flag = p.alternative ? t('Alternative') : (p.optional ? t('optional') : '');
        h += `<tr class="${l.counted ? '' : 'is-extra'}"><td>${n}</td><td>${p.group ? `<span class="kx-grp">${esc(p.group)}</span>` : ''}<strong>${esc(p.name || t('Position'))}</strong>${flag ? ` <em>(${esc(flag)})</em>` : ''}${p.desc ? `<div class="kx-cdesc">${esc(p.desc)}</div>` : ''}</td>
<td class="kx-num">${esc(fmtIn(num(p.qty)))} ${esc(p.unit)}</td><td class="kx-num">${money(l.unit_price)}${num(p.discount) ? `<br><small>−${fmtIn(num(p.discount))} %</small>` : ''}</td><td class="kx-num">${l.counted ? money(l.total) : '(' + money(l.total) + ')'}</td></tr>`;
      }
      const x = T[k];
      h += `</tbody><tfoot>${x.discount ? `<tr><th colspan="4" scope="row">${esc(t('Summe'))}</th><td class="kx-num">${money(x.sum)}</td></tr><tr><th colspan="4" scope="row">${esc(t('Nachlass {v} %', { v: fmtIn(x.discount_pct) }))}</th><td class="kx-num">−${money(x.discount)}</td></tr>` : ''}
<tr class="kx-tot__net"><th colspan="4" scope="row">${esc(k === 'monthly' ? t('Monatlich netto') : t('Netto'))}</th><td class="kx-num">${money(x.net)}</td></tr>
<tr><th colspan="4" scope="row">${esc(t('USt {v} %', { v: fmtIn(T.vat_pct) }))}</th><td class="kx-num">${money(x.vat)}</td></tr>
<tr><th colspan="4" scope="row">${esc(k === 'monthly' ? t('Monatlich brutto') : t('Brutto'))}</th><td class="kx-num">${money(x.gross)}</td></tr>
${k === 'monthly' ? `<tr><th colspan="4" scope="row">${esc(t('Jährlich netto (12 Monate)'))}</th><td class="kx-num">${money(T.yearly.net)}</td></tr>` : ''}</tfoot></table>`;
    }
    preview.innerHTML = h || `<p class="adm-muted kx-emptyrow">${esc(t('Noch keine Positionen.'))}</p>`;
  }

  // ---------------------------------------------------------- Eingaben
  function markDirty() {
    dirty = true;
    setState('dirty');
    clearTimeout(timer);
    timer = setTimeout(save, 1500);
  }
  function setField(el) {
    const k = el.dataset.k;
    const i = posOf(el);
    if (i < 0 || !k) return;
    const pos = calc.positions[i];
    if (el.type === 'checkbox') { pos[k] = el.checked; update(); markDirty(); return; }
    if (el.classList.contains('kx-in--num')) {
      const v = parseNum(el.value);
      const key = pos.id + ':' + k;
      if (Number.isNaN(v)) { bad.set(key, { el, label: el.getAttribute('aria-label') || el.labels?.[0]?.textContent || k }); el.setAttribute('aria-invalid', 'true'); pos[k] = null; }
      else { bad.delete(key); el.removeAttribute('aria-invalid'); pos[k] = v; }
    } else pos[k] = el.value;
    if (k === 'basis') {
      // Stunden ↔ Festpreis: Bedeutung des Betrags wechselt – Zeile neu aufbauen, Fokus bleibt
      renderTable({ id: pos.id, k: 'basis', select: false });
    } else update();
    markDirty();
  }
  tableBox.addEventListener('input', e => { if (e.target.matches('.kx-in:not(select)')) setField(e.target); });
  tableBox.addEventListener('change', e => {
    const el = e.target;
    if (el.matches('select.kx-in, input[type=checkbox]')) setField(el);
    else if (el.matches('.kx-in--num') && el.getAttribute('aria-invalid') !== 'true') { const v = parseNum(el.value); if (v !== null) el.value = fmtIn(v); }
  });
  tableBox.addEventListener('click', e => {
    const b = e.target.closest('button[data-a]');
    if (!b) return;
    const i = posOf(b);
    if (i < 0) return;
    const pos = calc.positions[i];
    switch (b.dataset.a) {
      case 'more': {
        const tr = $('#kx-more-' + CSS.escape(pos.id));
        const open = tr.hidden;
        tr.hidden = !open;
        b.setAttribute('aria-expanded', String(open));
        if (open) $('[data-k=group]', tr)?.focus();
        return;
      }
      case 'del': return removeAt(i);
      case 'up': case 'down': return move(i, b.dataset.a === 'up' ? -1 : 1, 'more');
      case 'dup': {
        const copy = { ...structuredClone(pos), id: pid() };
        calc.positions.splice(i + 1, 0, copy);
        renderTable({ id: copy.id });
        markDirty();
        announce(t('Position dupliziert.'));
      }
    }
  });
  let undo = null;
  function removeAt(i) {
    const [gone] = calc.positions.splice(i, 1);
    undo = { pos: gone, i };
    const next = calc.positions[Math.min(i, calc.positions.length - 1)];
    renderTable(next ? { id: next.id, select: false } : null);
    if (!next) $('[data-kx-add]').focus();
    markDirty();
    showUndo(t('„{name}“ entfernt.', { name: gone.name || t('Position') }));
  }
  function move(i, dir, focusK) {
    const j = i + dir;
    if (j < 0 || j >= calc.positions.length) return;
    [calc.positions[i], calc.positions[j]] = [calc.positions[j], calc.positions[i]];
    const id = calc.positions[j].id;
    renderTable();
    const tb = $(`tbody[data-id="${id}"]`, tableBox);
    if (focusK === 'more') {
      $('.kx-more', tb).hidden = false;
      $('[data-a=more]', tb).setAttribute('aria-expanded', 'true');
      $(`[data-a=${dir < 0 ? 'up' : 'down'}]`, tb)?.focus();
    } else $(`[data-k="${focusK}"]`, tb)?.focus();
    markDirty();
    announce(t('Position verschoben auf {n}.', { n: j + 1 }));
  }
  function newPosition(after = calc.positions.length - 1, base = {}) {
    const prev = calc.positions[after] || calc.positions[calc.positions.length - 1];
    const p = calc.params;
    const pos = { id: pid(), kind: prev?.kind || 'once', group: '', name: '', desc: '', qty: 1, unit: t('pauschal'),
      basis: prev?.basis || p.rate_once || rateKeys()[0] || 'fixed', amount: null, hours_internal: null, cost: null, discount: null,
      buffer: false, optional: false, alternative: false, note: '', src: '', ...base };
    calc.positions.splice(after + 1, 0, pos);
    return pos;
  }
  $('[data-kx-add]').addEventListener('click', () => { const pos = newPosition(); renderTable({ id: pos.id }); markDirty(); });

  // Tastatur: Enter = nächste Zeile bzw. neue Position, Strg+Enter = neue Position, Alt+↑/↓ = verschieben, Strg+S = speichern
  app.addEventListener('keydown', e => {
    const el = e.target;
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      const i = el.closest ? posOf(el) : -1;
      const pos = newPosition(i >= 0 ? i : calc.positions.length - 1);
      renderTable({ id: pos.id });
      markDirty();
      return;
    }
    const row = el.closest?.('.kx-row');
    if (!row) return;
    const i = posOf(el);
    if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) { e.preventDefault(); move(i, e.key === 'ArrowUp' ? -1 : 1, el.dataset.k || 'name'); return; }
    if (e.key === 'Enter' && !e.ctrlKey && !e.metaKey && !e.altKey && el.matches('input, select')) {
      e.preventDefault();
      const k = el.dataset.k || 'name';
      const j = e.shiftKey ? i - 1 : i + 1;
      if (j >= calc.positions.length) {
        const pos = newPosition(i);
        renderTable({ id: pos.id });
        markDirty();
        announce(t('Neue Position {n}.', { n: j + 1 }));
      } else if (j >= 0) {
        const n = $(`tbody[data-id="${calc.positions[j].id}"] [data-k="${k}"]`, tableBox);
        n?.focus(); n?.select?.();
      }
    }
  });
  d.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); clearTimeout(timer); save(); }
  });

  // Kopf-, Grundlage- und Textfelder (data-f)
  const setPath = (path, v) => { const ks = path.split('.'); let o = calc; while (ks.length > 1) o = o[ks.shift()]; o[ks[0]] = v; };
  $$('[data-f]', app).forEach(el => {
    const path = el.dataset.f;
    const on = () => {
      let v = el.value;
      if (el.matches('[data-num]')) {
        const n = parseNum(v);
        if (Number.isNaN(n)) { bad.set('f:' + path, { el, label: el.labels?.[0]?.textContent || path }); el.setAttribute('aria-invalid', 'true'); v = null; }
        else { bad.delete('f:' + path); el.removeAttribute('aria-invalid'); v = n; }
      }
      if (path === 'params.round_step') v = Number(v);
      setPath(path, v);
      if (path === 'title') $('[data-kx-title]').textContent = v || t('(ohne Titel)');
      if (path === 'number') $('[data-kx-number]').textContent = v;
      if (path === 'params.currency') renderTable(); else update();
      markDirty();
      if (path === 'status') { clearTimeout(timer); save(); }
    };
    el.addEventListener(el.tagName === 'SELECT' || el.type === 'date' ? 'change' : 'input', on);
    if (el.matches('[data-num]')) el.addEventListener('change', () => { const n = parseNum(el.value); if (n !== null && !Number.isNaN(n)) el.value = fmtIn(n); });
  });
  $('[data-kx-snippet]')?.addEventListener('change', e => {
    const list = JSON.parse($('#kx-snippets')?.textContent || '[]');
    const txt = list[Number(e.target.value)];
    if (e.target.value === '' || !txt) return;
    const ta = $('#kx-intro');
    ta.value = (ta.value.trim() ? ta.value.trim() + '\n\n' : '') + txt;
    ta.dispatchEvent(new Event('input'));
    e.target.value = '';
    ta.focus();
  });

  // Sätze der Grundlage (je Kalkulation)
  function renderRates() {
    const box = $('[data-kx-rates]');
    box.innerHTML = `<table class="adm-table kx-rates"><caption class="sr-only">${esc(t('Stundensätze'))}</caption><thead><tr><th scope="col">${esc(t('Stundensatz'))}</th><th scope="col" class="kx-num">${esc(t('je Stunde netto'))}</th></tr></thead><tbody>`
      + rateKeys().map(k => `<tr><th scope="row">${esc(rateLabel(k))}</th><td class="kx-num"><input class="kx-in kx-in--num" data-rate="${esc(k)}" inputmode="decimal" value="${esc(fmtIn(num(calc.params.rates[k].rate)))}" aria-label="${esc(t('Stundensatz {label}', { label: rateLabel(k) }))}"></td></tr>`).join('')
      + '</tbody></table>';
  }
  $('[data-kx-rates]').addEventListener('input', e => {
    const el = e.target.closest('[data-rate]');
    if (!el) return;
    const n = parseNum(el.value), key = 'r:' + el.dataset.rate;
    if (Number.isNaN(n)) { bad.set(key, { el, label: el.getAttribute('aria-label') }); el.setAttribute('aria-invalid', 'true'); calc.params.rates[el.dataset.rate].rate = null; }
    else { bad.delete(key); el.removeAttribute('aria-invalid'); calc.params.rates[el.dataset.rate].rate = n; }
    update();
    markDirty();
  });
  $('[data-kx-reload-basis]').addEventListener('click', () => {
    if (!window.confirm(t('Sätze, Aufschläge, USt und Rundung aus den Einstellungen übernehmen? Die Preise dieser Kalkulation können sich ändern.'))) return;
    calc.params = structuredClone(boot.defaults);
    for (const el of $$('[data-f^="params."]', app)) {
      const v = calc.params[el.dataset.f.slice(7)];
      el.value = el.matches('[data-num]') ? fmtIn(num(v)) : (el.dataset.f === 'params.round_step' ? String(Number(v)) : v);
      el.removeAttribute('aria-invalid');
    }
    [...bad.keys()].filter(k => k.startsWith('r:') || k.startsWith('f:params')).forEach(k => bad.delete(k));
    renderRates();
    renderTable();
    markDirty();
    announce(t('Grundlage aus den Einstellungen übernommen.'));
  });

  // Ansicht: intern | Kunde
  $$('[data-kx-view]').forEach(b => b.addEventListener('click', () => {
    const v = b.dataset.kxView;
    app.dataset.view = v;
    $$('[data-kx-view]').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
    tableBox.hidden = v === 'customer';
    preview.hidden = v !== 'customer';
    if (v === 'customer') renderPreview(lastRun || run());
    try { localStorage.setItem('kx-view', v); } catch { /* ohne Speicher */ }
  }));

  // ---------------------------------------------------------- Katalog-Dialog
  const dlg = $('#kx-catalog');
  const catList = $('[data-kx-cat-list]');
  const catQ = $('[data-kx-cat-q]');
  const billLabel = { both: t('Einrichtung + monatlich'), once: t('einmalig'), monthly: t('monatlich') };
  function renderCatalog() {
    const q = catQ.value.trim().toLowerCase();
    let h = '';
    for (const s of boot.catalog) {
      const items = (s.items || []).filter(it => !q || (s.name + ' ' + it.name + ' ' + (it.desc || '')).toLowerCase().includes(q));
      if (!items.length) continue;
      h += `<section class="kx-cat__s"><h3>${esc(s.name)}</h3><ul role="list">` + items.map(it => `<li><button type="button" class="kx-cat__item" data-s="${esc(s.id)}" data-i="${esc(it.id)}">
<span class="kx-cat__name">${esc(it.name)}</span><span class="kx-cat__meta">${esc(billLabel[it.bill] || '')}${it.unit ? ' · ' + esc(it.unit) : ''}${it.example ? ` · <span class="adm-badge adm-badge--draft">${esc(t('Beispiel'))}</span>` : ''}</span>
${it.desc ? `<span class="kx-cat__desc">${esc(it.desc)}</span>` : ''}</button></li>`).join('') + '</ul></section>';
    }
    catList.innerHTML = h || `<p class="adm-muted">${esc(boot.catalog.length ? t('Nichts gefunden.') : t('Der Katalog ist leer.'))}</p>`;
  }
  $('[data-kx-catalog]').addEventListener('click', () => { renderCatalog(); dlg.showModal(); catQ.focus(); });
  $('[data-kx-close]', dlg).addEventListener('click', () => dlg.close());
  dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
  catQ.addEventListener('input', renderCatalog);
  catQ.addEventListener('keydown', e => { if (e.key === 'ArrowDown' || e.key === 'Enter') { e.preventDefault(); $('.kx-cat__item', catList)?.focus(); } });
  catList.addEventListener('keydown', e => {
    if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
    const all = $$('.kx-cat__item', catList), i = all.indexOf(d.activeElement);
    e.preventDefault();
    if (e.key === 'ArrowUp' && i <= 0) catQ.focus(); else all[Math.max(0, Math.min(all.length - 1, i + (e.key === 'ArrowDown' ? 1 : -1)))]?.focus();
  });
  catList.addEventListener('click', e => {
    const b = e.target.closest('.kx-cat__item');
    if (!b) return;
    const s = boot.catalog.find(x => x.id === b.dataset.s), it = s?.items.find(x => x.id === b.dataset.i);
    if (!it) return;
    const p = calc.params, keys = rateKeys();
    const pick = (want, dflt) => want === 'fixed' || keys.includes(want) ? want : (keys.includes(dflt) ? dflt : (keys[0] || 'fixed'));
    const both = it.bill === 'both';
    let last = null;
    if (it.bill !== 'monthly') last = newPosition(calc.positions.length - 1, { kind: 'once', group: s.name, name: it.name + (both ? ' – ' + t('Einrichtung') : ''), desc: it.desc || '',
      unit: both ? t('pauschal') : (it.unit || t('pauschal')), basis: pick(it.once_basis, p.rate_once), amount: it.once_amount ?? null, cost: it.once_cost ?? null, buffer: !!it.buffer, src: it.id });
    if (it.bill !== 'once') last = newPosition(calc.positions.length - 1, { kind: 'monthly', group: s.name, name: it.name + (both ? ' – ' + t('Betrieb') : ''), desc: it.desc || '',
      unit: it.unit || t('Monat'), basis: pick(it.monthly_basis, p.rate_monthly), amount: it.monthly_amount ?? null, cost: it.monthly_cost ?? null, buffer: false, src: it.id });
    renderTable();
    markDirty();
    b.classList.add('is-added');
    announce(t('„{name}“ hinzugefügt ({n} Positionen).', { name: it.name, n: calc.positions.length }));
    if (last) $(`tbody[data-id="${last.id}"]`, tableBox)?.scrollIntoView({ block: 'nearest' });
  });

  // ---------------------------------------------------------- Speichern
  function setState(s, extra = '') {
    state.dataset.state = s;
    const txt = { dirty: t('Ungespeichert'), saving: t('Speichert …'), saved: t('Gespeichert {time}', { time: extra }), error: extra, invalid: extra, conflict: extra }[s] ?? '';
    state.innerHTML = esc(txt) + (s === 'conflict' ? ` <button type="button" class="adm-btn adm-btn--small" data-kx-reload>${esc(t('Neu laden'))}</button> <button type="button" class="adm-btn adm-btn--small adm-btn--danger" data-kx-force>${esc(t('Trotzdem speichern'))}</button>` : '')
      + (s === 'invalid' ? ` <button type="button" class="adm-btn adm-btn--small" data-kx-goto>${esc(t('Zum Feld'))}</button>` : '');
  }
  state.addEventListener('click', e => {
    if (e.target.matches('[data-kx-reload]')) { dirty = false; location.reload(); }
    if (e.target.matches('[data-kx-force]')) save(true);
    if (e.target.matches('[data-kx-goto]')) { const f = [...bad.values()][0]?.el; if (f) { const more = f.closest('.kx-more'); if (more) more.hidden = false; f.closest('details')?.setAttribute('open', ''); f.focus(); } }
  });
  function payload() {
    const out = { params: calc.params, positions: calc.positions };
    for (const k of DOC) out[k] = calc[k];
    return out;
  }
  let pending = null;
  async function save(force = false) {
    clearTimeout(timer);
    if (saving) { pending = force; return; }
    if (!dirty && !force) return true;
    if (conflict && !force) return false;
    if (bad.size) {
      const first = [...bad.values()][0];
      setState('invalid', t('Nicht gespeichert: ungültige Zahl bei „{label}“.', { label: first.label }));
      return false;
    }
    saving = true;
    setState('saving');
    const sentAt = JSON.stringify(payload());
    try {
      const res = await fetch(boot.urls.save, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() },
        body: JSON.stringify({ rev, force: force === true, calc: JSON.parse(sentAt) }) });
      const j = await res.json().catch(() => ({ ok: false, error: t('Antwort des Servers nicht lesbar ({code}).', { code: res.status }) }));
      if (j.ok) {
        rev = j.rev;
        conflict = false;
        if (JSON.stringify(payload()) === sentAt) { dirty = false; setState('saved', j.updated_at); } else setState('dirty');
        if (j.number !== undefined && calc.number !== j.number) { calc.number = j.number; $('#kx-number').value = j.number; $('[data-kx-number]').textContent = j.number; }
        return true;
      }
      if (j.conflict) { conflict = true; setState('conflict', j.error); return false; }
      setState('error', j.error || t('Speichern fehlgeschlagen.'));
      return false;
    } catch {
      setState('error', t('Keine Verbindung – Änderungen sind noch nicht gespeichert.'));
      return false;
    } finally {
      saving = false;
      if (pending !== null) { const f = pending; pending = null; save(f); }
      else if (dirty && !conflict && !bad.size && state.dataset.state === 'dirty') timer = setTimeout(save, 1500);
    }
  }
  $('[data-kx-save]').addEventListener('click', () => save(dirty ? false : true));
  window.addEventListener('beforeunload', e => { if (dirty || saving) { e.preventDefault(); e.returnValue = ''; } });
  // Angebot/CSV/Stand sichern: vorher speichern, damit der Server den aktuellen Stand hat
  $('[data-kx-offer]').addEventListener('click', async e => {
    if (!dirty) return;
    e.preventDefault();
    const w = window.open('about:blank', '_blank');
    if (await save()) { if (w) w.location = e.currentTarget.href; else location.href = e.currentTarget.href; } else w?.close();
  });
  $$('[data-kx-dl]').forEach(a => a.addEventListener('click', async e => {
    if (!dirty) return;
    e.preventDefault();
    if (await save()) location.href = a.href;
  }));
  $('[data-kx-needsave]')?.closest('form').addEventListener('submit', async e => {
    if (!dirty) return;
    e.preventDefault();
    if (await save()) { dirty = false; e.target.submit(); }
  });

  // ---------------------------------------------------------- Meldungen
  const live = $('[data-kx-live]');
  function announce(msg) { live.textContent = ''; setTimeout(() => { live.textContent = msg; }, 30); }
  let undoTimer = 0;
  function showUndo(msg) {
    let box = $('.kx-undo');
    if (!box) { box = d.createElement('div'); box.className = 'kx-undo'; box.setAttribute('role', 'status'); $('.kx-savebar').prepend(box); }
    box.innerHTML = `${esc(msg)} <button type="button" class="adm-btn adm-btn--small">${esc(t('Rückgängig'))}</button>`;
    box.hidden = false;
    $('button', box).onclick = () => {
      if (!undo) return;
      calc.positions.splice(Math.min(undo.i, calc.positions.length), 0, undo.pos);
      renderTable({ id: undo.pos.id });
      undo = null; box.hidden = true; markDirty();
    };
    clearTimeout(undoTimer);
    undoTimer = setTimeout(() => { box.hidden = true; undo = null; }, 10000);
  }

  // ---------------------------------------------------------- Start
  renderRates();
  renderTable();
  setState('saved', boot.user ? '' : '');
  state.textContent = t('Gespeichert');
  try { if (localStorage.getItem('kx-view') === 'customer') $('[data-kx-view=customer]').click(); } catch { /* ohne Speicher */ }
  if (!calc.positions.length) $('[data-kx-catalog]').focus({ preventScroll: true });
})();
