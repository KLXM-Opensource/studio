/*
 * Block-Designer (Verwaltung → Blöcke, Core\Blocks\Custom): Feld-Editor, Code-Editoren mit Zeilennummern,
 * Platzhalter-Palette, Beispieldaten (Formular vom Server), JSON-LD-Zuordnung, Live-Vorschau (_preview.js),
 * Prüfung von Vorlage und CSS und Vorschläge von KLXM Ai (füllen nur das Formular – gespeichert wird von Hand).
 */
import { t } from './_i18n.js';
import { livePreview } from './_preview.js';
import { ask } from './_bar.js';
import { ico } from './_icons.js';

const d = document;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const slug = s => String(s || '').toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
  .replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^(\d)/, 'f_$1').slice(0, 40);
const debounce = (fn, ms) => { let tm; return (...a) => { clearTimeout(tm); tm = setTimeout(() => fn(...a), ms); }; };

export function initBlockBuilder() {
  const wrap = $('[data-cb]');
  if (!wrap) return;
  const cfg = JSON.parse(wrap.dataset.cbConfig || '{}');
  const form = $('[data-cb-form]', wrap);
  const csrf = () => form.elements._csrf?.value || '';
  const fieldsIn = $('[data-cb-fieldsjson]', form), jsonldIn = $('[data-cb-jsonldjson]', form);
  const list = $('[data-cb-fields]', form);
  const ftpl = $('[data-cb-field-tpl]'), stpl = $('[data-cb-sub-tpl]');
  const tpl = $('[data-cb-code="template"]', form), css = $('[data-cb-code="css"]', form);
  let fields = [];
  try { fields = JSON.parse(fieldsIn.value || '[]'); } catch { fields = []; }
  const locked = new Set(cfg.published ? fields.map(f => f.name) : []);   // Kurznamen freigegebener Felder: Inhalte der Seiten hängen daran
  let dirty = false;
  const touch = () => { dirty = true; };
  addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  // ---------------------------------------------------------------- Feld-Editor
  const typeOpts = (sub, cur) => Object.entries(cfg.types).filter(([k]) => !(sub && k === 'repeater'))
    .map(([k, v]) => `<option value="${k}"${k === cur ? ' selected' : ''}>${esc(v.label)}</option>`).join('');
  const optsText = o => o && typeof o === 'object' ? Object.entries(o).map(([k, v]) => (slug(v) === k ? v : `${k}=${v}`)).join('\n') : String(o || '');

  function subRow(sf) {
    const li = stpl.content.firstElementChild.cloneNode(true);
    $('[data-s=label]', li).value = sf.label || '';
    const nm = $('[data-s=name]', li);
    nm.value = sf.name || '';
    if (sf.name) nm.dataset.touched = '1';
    $('[data-s=type]', li).innerHTML = typeOpts(true, sf.type || 'text');
    $('[data-s=required]', li).checked = !!sf.required;
    $('[data-s=half]', li).checked = sf.width === 'half';
    $('[data-s=options]', li).value = optsText(sf.options);
    $('[data-sshow]', li).hidden = (sf.type || 'text') !== 'select';
    return li;
  }

  function fieldRow(f) {
    const li = ftpl.content.firstElementChild.cloneNode(true);
    const type = f.type || 'text';
    li.dataset.type = type;
    $('[data-p=label]', li).value = f.label || '';
    const nm = $('[data-p=name]', li);
    nm.value = f.name || '';
    if (f.name) nm.dataset.touched = '1';
    if (locked.has(f.name)) { nm.readOnly = true; nm.title = t('Kurznamen freigegebener Felder sind fest – die Inhalte der Seiten hängen daran.'); }
    $('[data-p=type]', li).innerHTML = typeOpts(false, type);
    $('[data-p=required]', li).checked = !!f.required;
    $('[data-p=half]', li).checked = f.width === 'half';
    $('[data-p=options]', li).value = optsText(f.options);
    $('[data-p=max]', li).value = f.max || '';
    $('[data-p=help]', li).value = f.help || '';
    $('[data-p=item_label]', li).value = f.item_label || '';
    $('[data-p=max_items]', li).value = f.max_items || '';
    (f.fields || []).forEach(sf => $('[data-cb-subs]', li).append(subRow(sf)));
    syncRow(li);
    return li;
  }

  function syncRow(li) {
    const type = $('[data-p=type]', li).value;
    li.dataset.type = type;
    const ic = cfg.types[type];
    $('[data-cb-ficon]', li).innerHTML = ic?.ico ? ico(ic.ico) : '';
    $$('[data-show]', li).forEach(x => { x.hidden = !x.dataset.show.split(' ').includes(type); });
    if (type === 'repeater' && !$('[data-cb-sub]', li)) $('[data-cb-subs]', li).append(subRow({ type: 'text' }));
  }

  function readFields() {
    return $$(':scope > [data-cb-field]', list).map(li => {
      const g = p => $(`[data-p=${p}]`, li);
      const f = { name: g('name').value.trim(), label: g('label').value.trim(), type: g('type').value };
      if (g('required').checked) f.required = true;
      if (g('half').checked) f.width = 'half';
      if (g('help').value.trim()) f.help = g('help').value.trim();
      if (f.type === 'select') f.options = g('options').value;
      if (['text', 'textarea', 'inline'].includes(f.type) && +g('max').value > 0) f.max = +g('max').value;
      if (f.type === 'repeater') {
        f.item_label = g('item_label').value.trim();
        if (+g('max_items').value > 0) f.max_items = +g('max_items').value;
        f.fields = $$('[data-cb-sub]', li).map(s => {
          const q = p => $(`[data-s=${p}]`, s);
          const sf = { name: q('name').value.trim(), label: q('label').value.trim(), type: q('type').value };
          if (q('required').checked) sf.required = true;
          if (q('half').checked) sf.width = 'half';
          if (sf.type === 'select') sf.options = q('options').value;
          return sf;
        }).filter(sf => sf.label || sf.name);
      }
      return f;
    }).filter(f => f.label || f.name);
  }

  function renderFields() {
    list.replaceChildren(...fields.map(fieldRow));
  }

  const fieldsChanged = debounce(() => {
    fields = readFields();
    fieldsIn.value = JSON.stringify(fields);
    renderPalette();
    renderJsonld();
    loadSample();
    changed();
  }, 400);

  list.addEventListener('input', e => {
    const li = e.target.closest('[data-cb-field]'), sub = e.target.closest('[data-cb-sub]');
    if (sub && e.target.matches('[data-s=label]')) { const nm = $('[data-s=name]', sub); if (!nm.dataset.touched) nm.value = slug(e.target.value); }
    else if (li && !sub && e.target.matches('[data-p=label]')) { const nm = $('[data-p=name]', li); if (!nm.dataset.touched && !nm.readOnly) nm.value = slug(e.target.value); }
    if (e.target.matches('[data-p=name],[data-s=name]')) e.target.dataset.touched = '1';
    touch(); fieldsChanged();
  });
  list.addEventListener('change', e => {
    if (e.target.matches('[data-p=type]')) syncRow(e.target.closest('[data-cb-field]'));
    if (e.target.matches('[data-s=type]')) $('[data-sshow]', e.target.closest('[data-cb-sub]')).hidden = e.target.value !== 'select';
    touch(); fieldsChanged();
  });
  list.addEventListener('click', async e => {
    const b = e.target.closest('button');
    if (!b) return;
    const li = b.closest('[data-cb-field]'), sub = b.closest('[data-cb-sub]');
    if (b.matches('[data-cb-subadd]')) { const s = subRow({ type: 'text' }); $('[data-cb-subs]', li).append(s); $('[data-s=label]', s).focus(); }
    else if (b.matches('[data-cb-subremove]')) { const nx = sub.nextElementSibling || sub.previousElementSibling; sub.remove(); (nx ? $('[data-s=label]', nx) : $('[data-cb-subadd]', li)).focus(); }
    else if (b.matches('[data-cb-submove]')) { const to = +b.dataset.cbSubmove < 0 ? sub.previousElementSibling : sub.nextElementSibling?.nextElementSibling; sub.parentNode.insertBefore(sub, to || (+b.dataset.cbSubmove < 0 ? sub.parentNode.firstChild : null)); b.focus(); }
    else if (b.matches('[data-cb-move]')) { const to = +b.dataset.cbMove < 0 ? li.previousElementSibling : li.nextElementSibling?.nextElementSibling; list.insertBefore(li, to || (+b.dataset.cbMove < 0 ? list.firstChild : null)); b.focus(); }
    else if (b.matches('[data-cb-remove]')) {
      const name = $('[data-p=label]', li).value || t('ohne Namen');
      if (!await ask({ title: t('Feld „{name}“ entfernen?', { name }), body: locked.has($('[data-p=name]', li).value) ? t('Seiten, die den Block verwenden, verlieren nach der nächsten Freigabe die Inhalte dieses Felds.') : '', ok: t('Entfernen') })) return;
      const nx = li.nextElementSibling || li.previousElementSibling;
      li.remove();
      (nx ? $('[data-p=label]', nx) : $('[data-cb-add]', form)).focus();
    } else return;
    touch(); fieldsChanged();
  });
  $$('[data-cb-add]', form).forEach(b => b.addEventListener('click', () => {
    const li = fieldRow({ type: b.dataset.cbAdd, label: '', name: '' });
    list.append(li);
    $('[data-p=label]', li).focus();
    touch(); fieldsChanged();
  }));
  renderFields();

  // ---------------------------------------------------------------- Code-Editoren: Zeilennummern, Fehlerzeile
  const errLine = { template: 0, css: 0 };
  function gutter(ta) {
    const g = $('[data-cb-gutter]', ta.closest('[data-cb-editor]'));
    const n = ta.value.split('\n').length;
    const bad = errLine[ta.dataset.cbCode] || 0;
    g.innerHTML = Array.from({ length: n }, (_, i) => i + 1 === bad ? `<b class="is-err">${i + 1}</b>` : String(i + 1)).join('\n');
    g.scrollTop = ta.scrollTop;
  }
  [tpl, css].forEach(ta => {
    ta.addEventListener('input', () => { gutter(ta); touch(); changed(); });
    ta.addEventListener('scroll', () => { $('[data-cb-gutter]', ta.closest('[data-cb-editor]')).scrollTop = ta.scrollTop; });
    gutter(ta);
  });
  const goLine = (ta, line) => {
    const lines = ta.value.split('\n');
    const pos = lines.slice(0, line - 1).reduce((a, l) => a + l.length + 1, 0);
    ta.focus();
    ta.setSelectionRange(pos, pos + (lines[line - 1] || '').length);
  };

  // ---------------------------------------------------------------- Palette: Platzhalter einfügen
  const snippet = (f, v = f.name) => ({
    text: `{{ ${v} }}`, textarea: `{{ ${v} | nl2br }}`, richtext: `<div class="text">{{ ${v} | rich }}</div>`, inline: `{{ ${v} | inline }}`,
    media: `{{ ${v} | image('(min-width: 800px) 50vw, 100vw', '4:3') }}`, file: `<a href="{{ ${v} | file }}">{{ ${v} | filename }}</a>`,
    link: `<a class="btn" href="{{ ${v} | link }}">{{ 'Mehr erfahren' | lt }}</a>`, select: `{{ ${v} }}`, bool: `{% if ${v} %}…{% endif %}`,
    number: `{{ ${v} | number }}`, date: `<time datetime="{{ ${v} }}">{{ ${v} | date('long') }}</time>`, color: `{{ ${v} }}`, icon: `{{ ${v} | icon }}`,
  })[f.type] || `{{ ${v} }}`;
  function insert(text) {
    const s = tpl.selectionStart ?? tpl.value.length, e = tpl.selectionEnd ?? s;
    tpl.focus();
    tpl.setRangeText(text, s, e, 'end');
    tpl.dispatchEvent(new Event('input', { bubbles: true }));
  }
  function renderPalette() {
    const pal = $('[data-cb-palette]', form);
    if (!pal) return;
    const btn = (label, code, cls = '') => `<button type="button" class="cb-chip${cls}" data-code="${esc(code)}" title="${esc(code)}">${esc(label)}</button>`;
    let h = '';
    for (const f of fields) {
      if (f.type === 'repeater') {
        const v = slug(f.item_label || 'eintrag').replace(/_\d+$/, '') || 'eintrag';
        const inner = (f.fields || []).map(sf => '    ' + (sf.type === 'text' ? `<h3>{{ ${v}.${sf.name} }}</h3>` : snippet(sf, `${v}.${sf.name}`))).join('\n');
        h += btn(`${f.label} (${t('Liste')})`, `<ul class="list" role="list">\n{% for ${v} in ${f.name} %}\n  <li class="list__item">\n${inner}\n  </li>\n{% endfor %}\n</ul>`, ' cb-chip--list');
      } else if (f.name === 'title') {
        h += btn(f.label, `<h2 id="{{ block.title_id }}">{{ title }}</h2>`);
      } else h += btn(f.label, snippet(f));
    }
    h += '<span class="cb-chip-sep" aria-hidden="true"></span>'
      + btn('{% if %}', '{% if feld %}\n  …\n{% endif %}', ' cb-chip--code') + btn('{% for %}', '{% for eintrag in liste %}\n  …\n{% endfor %}', ' cb-chip--code')
      + btn('loop.index', '{{ loop.index }}', ' cb-chip--code') + btn('lt', "{{ 'Text' | lt }}", ' cb-chip--code');
    pal.innerHTML = h;
  }
  $('[data-cb-palette]', form)?.addEventListener('click', e => { const b = e.target.closest('[data-code]'); if (b) insert(b.dataset.code); });
  renderPalette();

  // ---------------------------------------------------------------- Beispieldaten (Formular des Kerns, CMSAdmin.init)
  const sampleBox = $('[data-cb-sample]', form);
  async function loadSample(reset = false, sampleJson = null) {
    if (!sampleBox) return;
    const fd = new FormData();
    fd.set('_csrf', csrf());
    fd.set('fields_json', fieldsIn.value);
    if (reset) fd.set('reset', '1');
    if (sampleJson) fd.set('sample_json', JSON.stringify(sampleJson));
    else for (const [k, v] of new FormData(form)) if (k.startsWith('sample[')) fd.append(k, v);
    try {
      const r = await fetch(cfg.sample, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const j = await r.json();
      if (!j.ok) return;
      sampleBox.innerHTML = j.html;
      window.CMSAdmin?.init(sampleBox);
      changed();
    } catch {}
  }
  sampleBox?.addEventListener('input', () => { touch(); changed(); });
  sampleBox?.addEventListener('change', () => { touch(); changed(); });
  $('[data-cb-sample-reset]', form)?.addEventListener('click', () => { touch(); loadSample(true); });
  loadSample();

  // ---------------------------------------------------------------- JSON-LD-Zuordnung
  let jsonld = {};
  try { jsonld = JSON.parse(jsonldIn.value || '{}'); } catch { jsonld = {}; }
  const jui = $('[data-cb-jsonld-ui]', form);
  const sel = (name, opts, cur, label, empty = '') => `<div class="f f--half"><label for="cb-jl-${name}">${esc(label)}</label><select id="cb-jl-${name}" data-jl="${name}">`
    + (empty ? `<option value="">${esc(empty)}</option>` : '') + opts.map(([k, l]) => `<option value="${esc(k)}"${k === cur ? ' selected' : ''}>${esc(l)}</option>`).join('') + '</select></div>';
  function renderJsonld() {
    if (!jui) return;
    const reps = fields.filter(f => f.type === 'repeater');
    const type = jsonld.type || 'none';
    let h = sel('type', [['none', t('Keine')], ['faq', t('Fragen & Antworten (FAQPage)')], ['item', t('Einträge eines Typs (z. B. Leistung, Person)')]], type, t('Art'));
    if (type === 'faq') {
      const rep = reps.find(f => f.name === jsonld.items) || reps[0];
      const subs = (rep?.fields || []).map(s => [s.name, s.label]);
      h += sel('items', reps.map(f => [f.name, f.label]), rep?.name, t('Liste'))
        + sel('question', subs, jsonld.question, t('Frage')) + sel('answer', subs, jsonld.answer, t('Antwort'));
      if (!reps.length) h += `<p class="f-help">${esc(t('Dafür braucht der Block eine Liste (wiederholbar).'))}</p>`;
    } else if (type === 'item') {
      h += sel('schema', cfg.schemas.map(s => [s, s]), jsonld.schema || 'Service', t('schema.org-Typ'))
        + sel('list', reps.map(f => [f.name, f.label]), jsonld.list || '', t('Je Eintrag einer Liste'), t('– der Block selbst –'));
      const src = jsonld.list ? (reps.find(f => f.name === jsonld.list)?.fields || []) : fields.filter(f => f.type !== 'repeater');
      for (const p of cfg.props) h += sel('p-' + p, src.map(s => [s.name, s.label]), jsonld.props?.[p] || '', p, t('– nicht zuordnen –'));
    }
    jui.innerHTML = h;
  }
  function readJsonld() {
    const g = n => $(`[data-jl="${n}"]`, jui)?.value || '';
    const type = g('type');
    const out = { type };
    if (type === 'faq') Object.assign(out, { items: g('items'), question: g('question'), answer: g('answer') });
    if (type === 'item') {
      Object.assign(out, { schema: g('schema'), list: g('list'), props: {} });
      for (const p of cfg.props) if (g('p-' + p)) out.props[p] = g('p-' + p);
    }
    return out;
  }
  jui?.addEventListener('change', e => {
    jsonld = readJsonld();
    if (e.target.matches('[data-jl=type],[data-jl=items],[data-jl=list]')) renderJsonld();
    jsonld = readJsonld();
    jsonldIn.value = JSON.stringify(jsonld);
    touch();
  });
  renderJsonld();

  // ---------------------------------------------------------------- Prüfung & Vorschau
  let bg = '', dark = false;
  const body = () => {
    fieldsIn.value = JSON.stringify(readFields());
    const fd = new FormData(form);
    fd.set('_bg', bg);
    fd.set('_dark', dark ? '1' : '');
    fd.set('_key', cfg.key || $('[data-cb-key]', form)?.value || '');
    return fd;
  };
  function report(j) {
    const errs = j.errors || {};
    for (const el of $$('[data-cb-err]', form)) {
      const m = errs[el.dataset.cbErr];
      el.hidden = !m;
      el.textContent = m || '';
    }
    errLine.template = +errs.template_line || 0;
    errLine.css = +errs.css_line || 0;
    gutter(tpl); gutter(css);
    const dot = (id, on) => { const x = $(`[data-cb-tabdot="${id}"]`, form); if (x) x.hidden = !on; };
    dot('felder', !!errs.fields); dot('vorlage', !!errs.template); dot('css', !!errs.css);
    const n = Object.keys(errs).filter(k => !k.endsWith('_line')).length;
    const chk = $('[data-cb-check]', form);
    chk.className = 'cb-check ' + (n ? 'is-fail' : 'is-ok');
    chk.textContent = n ? (n === 1 ? t('1 Fehler') : t('{n} Fehler', { n })) : t('Vorlage und CSS in Ordnung');
    const w = $('[data-cb-warnings]');
    if (w) { w.hidden = !(j.warnings || []).length; w.innerHTML = `<b>${esc(t('Hinweise:'))}</b> ` + esc((j.warnings || []).join(' ')); }
  }
  // Fehlermeldung anklicken → Zeile markieren
  $$('[data-cb-err]', form).forEach(el => el.addEventListener('click', () => {
    const k = el.dataset.cbErr;
    if (k === 'template' && errLine.template) goLine(tpl, errLine.template);
    if (k === 'css' && errLine.css) goLine(css, errLine.css);
  }));
  const pv = livePreview({ wrap, endpoint: cfg.preview, body, key: 'cb', onData: report, openDefault: innerWidth >= 1400 });
  const check = debounce(async () => {
    if (pv.isOpen()) return;
    try {
      const r = await fetch(cfg.preview, { method: 'POST', body: body(), credentials: 'same-origin', headers: { Accept: 'application/json' } });
      report(await r.json());
    } catch {}
  }, 900);
  function changed() { if (pv.isOpen()) pv.later(); else check(); }
  form.addEventListener('input', e => { if (!e.target.closest('[data-cb-fields],[data-cb-sample],[data-cb-code]')) { touch(); changed(); } });
  $('[data-cb-pvbg]', wrap)?.addEventListener('change', e => { bg = e.target.value; pv.refresh(); });
  $$('[data-cb-scheme]', wrap).forEach(b => b.addEventListener('click', () => {
    dark = b.dataset.cbScheme === 'dark';
    $$('[data-cb-scheme]', wrap).forEach(x => x.setAttribute('aria-pressed', String(x === b)));
    pv.refresh();
  }));
  check();

  // Schlüssel aus der Bezeichnung (nur neue Blöcke)
  const keyIn = $('[data-cb-key]', form), labelIn = $('[data-cb-label]', form);
  if (cfg.isNew && keyIn) {
    keyIn.addEventListener('input', () => { keyIn.dataset.touched = '1'; });
    labelIn.addEventListener('input', () => { if (!keyIn.dataset.touched) keyIn.value = slug(labelIn.value).slice(0, 31); });
  }

  // ---------------------------------------------------------------- Speichern / Freigeben
  $('[data-cb-publish]', form)?.addEventListener('click', () => { $('[data-cb-action]', form).value = 'publish'; });
  $('[data-cb-save]', form)?.addEventListener('click', () => { $('[data-cb-action]', form).value = ''; });
  form.addEventListener('submit', () => {
    fieldsIn.value = JSON.stringify(readFields());
    jsonldIn.value = JSON.stringify(jui ? readJsonld() : jsonld);
    dirty = false;
  });

  // ---------------------------------------------------------------- KLXM Ai: Vorschlag übernehmen (nur ins Formular)
  const aiBox = $('[data-cb-aibox]');
  const aiGo = $('[data-cb-aigo]', aiBox || d), aiState = $('[data-cb-aistate]', aiBox || d);
  aiGo?.addEventListener('click', async () => {
    const desc = $('[data-cb-aidesc]', aiBox).value.trim();
    if (desc.length < 8) { aiState.textContent = t('Bitte beschreiben Sie kurz, was der Block zeigen soll.'); $('[data-cb-aidesc]', aiBox).focus(); return; }
    if (dirty && !await ask({ title: t('Formular mit dem Vorschlag überschreiben?'), body: t('Ungespeicherte Änderungen gehen verloren.'), ok: t('Überschreiben') })) return;
    aiGo.disabled = true;
    aiState.textContent = t('{brand} entwirft den Block … (das kann eine Minute dauern)', { brand: 'KLXM Ai' });
    const fd = new FormData();
    fd.set('_csrf', csrf());
    fd.set('description', desc);
    try {
      const r = await fetch(cfg.ai, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const j = await r.json();
      if (!j.ok) { aiState.textContent = j.error || t('Die KI-Anfrage ist fehlgeschlagen. Bitte später erneut versuchen.'); return; }
      apply(j.def);
      const n = Object.keys(j.errors || {}).filter(k => !k.endsWith('_line')).length;
      aiState.textContent = n ? t('Vorschlag übernommen – mit {n} Fehler(n). Bitte prüfen und korrigieren, dann speichern.', { n })
        : t('Vorschlag übernommen – bitte prüfen, in der Vorschau testen und speichern.');
      report(j);
      if (!pv.isOpen() && innerWidth > 900) pv.open(true, { remember: false });
    } catch { aiState.textContent = t('Die KI-Anfrage ist fehlgeschlagen. Bitte später erneut versuchen.'); }
    finally { aiGo.disabled = false; }
  });
  function apply(def) {
    const el = form.elements;
    el.label.value = def.label || '';
    if (cfg.isNew && keyIn) { keyIn.value = def.key || ''; keyIn.dataset.touched = '1'; }
    if (el.icon) { el.icon.value = def.icon || 'package'; el.icon.dispatchEvent(new Event('input', { bubbles: true })); }
    el.group.value = def.group || '';
    el.description.value = def.description || '';
    el['settings[help]'].value = def.settings?.help || '';
    tpl.value = def.template || ''; gutter(tpl);
    css.value = def.css || ''; gutter(css);
    $$('[data-cb-beh]', form).forEach(c => { c.checked = (def.behaviours || []).includes(c.value); });
    fields = def.fields || [];
    fieldsIn.value = JSON.stringify(fields);
    renderFields(); renderPalette();
    jsonld = def.jsonld || { type: 'none' };
    jsonldIn.value = JSON.stringify(jsonld);
    renderJsonld();
    $('[data-cb-aiflag]', form).value = '1';
    loadSample(false, def.sample || null);
    touch();
  }
}
