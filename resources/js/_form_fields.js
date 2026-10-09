/*
 * „Felder bearbeiten“ im Seiten-Editor (Teil von admin.js, window.CMSAdmin.formFields): Seitenleiste mit den Feldern einer
 * Datentabelle und den Formular-Einstellungen – für Blöcke mit Formular (Block „Formular (Datentabelle)“, Formulare in Kit-Blöcken).
 * Formular vom Server (app/Views/formfields-panel.php), gespeichert über Admin\FormFieldsController → Core\Data\SchemaPanel –
 * gleiche Prüfung wie im Tabellen-Designer (Tables::validate, Bestätigung beim Löschen von Feldern mit Inhalten).
 * Gleicher Rahmen wie „Eintrag bearbeiten“ (_entry_edit.js): eigenes Shadow DOM, admin.shadow.css + entry-panel.css.
 *
 *   CMSAdmin.formFields.open(endpoint, trigger, { onSaved(res) })   – onSaved: Vorschau der Blöcke neu laden (editor.js)
 *
 * Tastatur: Esc schließt (bzw. bricht „Feld entfernen?“ ab), Strg/⌘+S speichert; ↑/↓/✕ je Feld, Ansage der neuen Position.
 */
import { addRoot, setUiCss, barHost } from './_shadow.js';
import { ico } from './_icons.js';
import { confirmDiscard } from './_bar.js';

const d = document;
const T = {
  close: 'Schließen', cancel: 'Abbrechen', saving: 'Speichere …', saved: 'Gespeichert {time} – das Formular auf der Seite ist aktualisiert.',
  error: 'Fehler beim Speichern', failed: 'Laden fehlgeschlagen: {error}', session: 'Sitzung abgelaufen.',
  discardTitle: 'Änderungen verwerfen?', discardBody: '', keep: 'Weiter bearbeiten', discardBtn: 'Verwerfen', saveExit: 'Speichern & schließen',
  moved: '„{label}“ ist jetzt an Position {n} von {total}.', removed: 'Feld „{label}“ entfernt – wird beim Speichern gelöscht.',
  added: 'Feld „{label}“ hinzugefügt.', noname: 'ohne Namen',
};
const tx = (k, p = {}) => Object.entries(p).reduce((s, [a, b]) => s.replaceAll('{' + a + '}', String(b)), T[k] ?? k);
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
// Kurzname aus der Bezeichnung – wie im Tabellen-Designer (admin.js) und Tables::normName
const slug = v => v.toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^(\d)/, 'f_$1').slice(0, 40);

let host = null, root = null, el = null, trigger = null, url = '', dirty = false, busy = false, hooks = {}, csrfToken = '';
const q = s => root.querySelector(s);
const qa = (s, c = root) => [...c.querySelectorAll(s)];
const csrf = () => csrfToken || d.getElementById('adm-csrf')?.value || '';

async function request(u, { method = 'GET', form } = {}) {
  const r = await fetch(u, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': csrf() }, body: form });
  if (r.status === 401 || (r.redirected && /\/admin\/login/.test(r.url))) throw new Error(T.session);
  const res = await r.json().catch(() => ({ ok: false, error: 'Fehler ' + r.status }));
  if (res.texts) Object.assign(T, res.texts);
  if (res.csrf) csrfToken = res.csrf;
  return res;
}

function build(res) {
  host = d.createElement('div');
  host.id = 'cms-ffpanel-host';
  host.className = 'adm-ui';
  host.hidden = true;
  root = addRoot(host.attachShadow({ mode: 'open' }));
  root.innerHTML = `${(res.shadowCss || []).map(h => `<link rel="stylesheet" href="${esc(h)}">`).join('')}
    <aside class="ep" role="dialog" aria-labelledby="ff-title" aria-describedby="ff-sub" tabindex="-1">
      <div class="ep__head"><div class="ep__titles"><p class="ep__eyebrow" id="ff-sub"></p><h2 id="ff-title" tabindex="-1"></h2></div>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ff-close></button></div>
      <div class="ep__content"></div><p class="adm-sr" role="status" aria-live="polite" data-ff-live></p></aside>`;
  el = q('.ep');
  d.body.append(host);
  el.addEventListener('click', onClick);
  el.addEventListener('input', onInput);
  el.addEventListener('change', onChange);
  el.addEventListener('keydown', onKey);
  return Promise.all(qa('link').map(l => new Promise(r => { l.onload = l.onerror = r; })));
}

const list = () => q('[data-ff-fields]');
const rows = () => qa('[data-ff-field]', list());
const labelOf = li => li.querySelector('[data-ff-label]')?.value.trim() || T.noname;
function say(msg) { const l = q('[data-ff-live]'); l.textContent = ''; setTimeout(() => { l.textContent = msg; }, 30); }
function touch() { if (!dirty) { dirty = true; closeLabel(); } }
function closeLabel() { const b = q('.ep__head [data-ff-close]'); if (b) b.textContent = dirty ? T.cancel : T.close; }

/** Namen fields[i][…] in DOM-Reihenfolge; Pfeile am Anfang/Ende deaktivieren; Beschriftungen der Werkzeuge */
function renumber() {
  const all = rows();
  all.forEach((li, i) => {
    qa('[name^="fields["]', li).forEach(inp => { inp.name = inp.name.replace(/^fields\[[^\]]*\]/, `fields[${i}]`); });
    li.querySelector('[data-ff-move="-1"]').disabled = i === 0;
    li.querySelector('[data-ff-move="1"]').disabled = i === all.length - 1;
  });
}
function toolLabels(li) {
  const l = labelOf(li);
  for (const b of qa('[data-ff-move],[data-ff-remove]', li)) {
    if (!b.dataset.tpl) b.dataset.tpl = b.getAttribute('aria-label').replace(/„[^“]*“/, '„{label}“');
    b.setAttribute('aria-label', b.dataset.tpl.replace('{label}', l));
  }
}
/** Typ gewechselt: passende Zusatzangaben zeigen (verborgene werden nicht gesendet), Hinweis bei bestehenden Feldern */
function sync(li) {
  const type = li.querySelector('[data-ff-type]').value;
  li.dataset.type = type;
  qa('[data-show-for]', li).forEach(x => {
    const on = x.dataset.showFor.split(' ').includes(type);
    x.hidden = !on;
    qa('textarea,input,select,fieldset', x).forEach(i => { i.disabled = !on; });
  });
  qa('[data-hide-for]', li).forEach(x => { x.hidden = x.dataset.hideFor.split(' ').includes(type); });   // Abschnitt/Freitext: ohne Pflicht, Breite …
  const note = li.querySelector('[data-ff-typenote]');
  if (note) note.hidden = !li.dataset.origType || li.dataset.origType === type;
}
function uniqueName(base, self) {
  const taken = new Set(rows().filter(r => r !== self).map(r => r.querySelector('[data-ff-name]').value));
  let n = base || 'feld', i = 2;
  while (taken.has(n)) n = `${base}_${i++}`;
  return n;
}

function onClick(e) {
  const b = e.target.closest('button');
  if (!b || busy) return;
  const li = b.closest('[data-ff-field]');
  if (b.matches('[data-ff-close]')) close();
  else if (b.matches('[data-ff-save]')) save();
  else if (b.matches('[data-ff-add]')) add(b.dataset.ffAdd, b.dataset.label || b.textContent.trim());
  else if (b.matches('[data-ff-move]')) move(li, +b.dataset.ffMove, b);
  else if (b.matches('[data-ff-remove]')) askRemove(li, true);
  else if (b.matches('[data-ff-remove-no]')) askRemove(li, false);
  else if (b.matches('[data-ff-remove-yes]')) remove(li);
}
function onInput(e) {
  touch();
  const li = e.target.closest('[data-ff-field]');
  if (!li) return;
  if (e.target.matches('[data-ff-label]')) {
    const n = li.querySelector('[data-ff-name]');
    if (!n.dataset.locked && !n.dataset.touched) n.value = uniqueName(slug(e.target.value), li);
    toolLabels(li);
  }
  if (e.target.matches('[data-ff-name]')) e.target.dataset.touched = '1';
}
function onChange(e) {
  touch();
  const li = e.target.closest('[data-ff-field]');
  if (li && e.target.matches('[data-ff-type]')) sync(li);
}
function onKey(e) {
  if ((e.metaKey || e.ctrlKey) && e.key === 's') { e.preventDefault(); e.stopPropagation(); save(); return; }
  if (e.key !== 'Escape') return;
  e.preventDefault(); e.stopPropagation();
  const li = e.target.closest?.('[data-ff-field]');
  if (li && !li.querySelector('[data-ff-confirm]').hidden) askRemove(li, false);
  else close();
}

function add(type, label) {
  const n = rows().length;
  list().insertAdjacentHTML('beforeend', q('[data-ff-template]').innerHTML.replaceAll('__i__', String(n)));
  const li = list().lastElementChild;
  li.querySelector('[data-ff-type]').value = type;
  li.querySelector('[data-ff-label]').value = label;
  li.querySelector('[data-ff-name]').value = uniqueName(slug(label), li);
  sync(li); renumber(); toolLabels(li); touch();
  const inp = li.querySelector('[data-ff-label]');
  inp.focus(); inp.select();
  li.scrollIntoView({ block: 'nearest' });
  say(tx('added', { label }));
}
function move(li, dir, btn) {
  const to = dir < 0 ? li.previousElementSibling : li.nextElementSibling?.nextElementSibling;
  if (dir < 0 && !li.previousElementSibling) return;
  if (dir > 0 && !li.nextElementSibling) return;
  list().insertBefore(li, to || null);
  renumber(); touch();
  li.classList.remove('is-moved'); void li.offsetWidth; li.classList.add('is-moved');
  (btn.disabled ? li.querySelector(`[data-ff-move="${-dir}"]`) : btn).focus();
  li.scrollIntoView({ block: 'nearest' });
  const all = rows();
  say(tx('moved', { label: labelOf(li), n: all.indexOf(li) + 1, total: all.length }));
}
function askRemove(li, on) {
  const box = li.querySelector('[data-ff-confirm]');
  box.hidden = !on;
  li.classList.toggle('is-removing', on);
  (on ? box.querySelector('[data-ff-remove-yes]') : li.querySelector('[data-ff-remove]')).focus();
}
function remove(li) {
  const label = labelOf(li);
  const next = li.nextElementSibling || li.previousElementSibling;
  li.remove();
  renumber(); touch();
  (next ? next.querySelector('[data-ff-label]') : q('[data-ff-add]'))?.focus();
  say(tx('removed', { label }));
}

function head(res) {
  q('.ep__eyebrow').innerHTML = (res.ico ? ico(res.ico) + ' ' : '') + esc(res.table || '');
  q('#ff-title').textContent = res.title || '';
  closeLabel();
}
function mount(res) {
  q('.ep__content').innerHTML = res.html;
  rows().forEach(li => { sync(li); toolLabels(li); });
  renumber();
  let c = d.getElementById('adm-csrf');
  if (!c) { c = d.createElement('input'); c.type = 'hidden'; c.id = 'adm-csrf'; d.body.append(c); }
  if (res.csrf) c.value = res.csrf;
}
function place() {
  const bar = barHost();
  host.style.top = (bar ? Math.max(0, Math.round(bar.getBoundingClientRect().bottom)) : 0) + 'px';
}

async function open(endpoint, from, opts = {}) {
  if (host && !host.hidden && dirty && url !== endpoint) {
    const r = await confirmDiscard({ title: T.discardTitle, body: T.discardBody, note: '', keep: T.keep, discard: T.discardBtn, save: '' });
    if (r !== 'discard') return;
  }
  trigger = from; url = endpoint; dirty = false; hooks = opts;
  from?.setAttribute('aria-busy', 'true');
  let res;
  try { res = await request(endpoint); } catch (e) { res = { ok: false, error: e.message }; }
  from?.removeAttribute('aria-busy');
  if (url !== endpoint) return;
  if (!res.html) { alert(tx('failed', { error: res.error || '' })); url = ''; return; }
  if (res.uiCss) setUiCss(res.uiCss);
  if (!host) await build(res);
  if (res.appearance) host.dataset.theme = res.appearance; else delete host.dataset.theme;
  for (const [k, v] of Object.entries({ accent: res.accent?.accent, side: res.accent?.side })) { if (v) host.dataset[k] = v; else delete host.dataset[k]; }
  head(res);
  mount(res);
  host.hidden = false; place();
  d.body.classList.add('has-epanel');
  (q('[data-ff-label]') || q('#ff-title')).focus({ preventScroll: true });
}

async function close(force = false) {
  if (!host || host.hidden) return;
  if (dirty && !force) {
    const r = await confirmDiscard({ title: T.discardTitle, body: T.discardBody, note: '', keep: T.keep, discard: T.discardBtn, save: T.saveExit });
    if (r === 'save') { if (await save()) close(true); return; }
    if (r !== 'discard') return;
  }
  host.hidden = true; dirty = false; url = '';
  q('.ep__content').innerHTML = '';
  d.body.classList.remove('has-epanel');
  if (trigger?.isConnected) trigger.focus({ preventScroll: true });
}

/** Speichern; true bei Erfolg. Fehler: Leiste mit Hinweisen neu, Fokus auf die Fehlerübersicht */
async function save() {
  const form = q('[data-ff-form]');
  if (!form || busy) return false;
  const btns = qa('.cms-epanel__foot button, .ep__head button');
  busy = true; btns.forEach(b => { b.disabled = true; });
  el.setAttribute('aria-busy', 'true');
  say(T.saving);
  let res;
  try { res = await request(url, { method: 'POST', form: new FormData(form) }); } catch (e) { res = { ok: false, error: e.message }; }
  busy = false; el.removeAttribute('aria-busy');
  btns.forEach(b => { b.disabled = false; });
  if (!res.html) { say(T.error); alert(T.error + ': ' + (res.error || '')); return false; }
  const top = q('.cms-epanel__body')?.scrollTop || 0;
  head(res); mount(res);
  if (!res.ok) {
    dirty = true; closeLabel();
    const box = q('[data-ff-errors]');
    box?.focus(); box?.scrollIntoView({ block: 'start' });
    return false;
  }
  dirty = false; closeLabel();
  const body = q('.cms-epanel__body');
  if (body) body.scrollTop = top;
  q('[data-ff-save]')?.focus({ preventScroll: true });
  say(tx('saved', { time: res.saved_at || '' }));
  try { hooks.onSaved?.(res); } catch (e) { console.error(e); }
  return true;
}

export const formFields = { open, close, get isOpen() { return !!host && !host.hidden; } };
