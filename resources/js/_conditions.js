/*
 * Bedingungen der Datentabellen im Browser – Spiegel von Core\Data\Rules (Verwaltung und öffentliches Formular).
 * cfg = {feld: {v: [oder, [[feld, op, wert], …]], r: […], q: 0|1}}  (v = anzeigen wenn, r = Pflicht wenn, q = immer Pflicht)
 * Ausgeblendete Felder: Container `hidden`, Eingaben `disabled` (werden nicht gesendet); ihr Wert zählt als leer.
 */
const lc = s => String(s).toLowerCase();
const num = /^-?\d+(\.\d+)?$/;
const cmp = (a, b) => num.test(a) && num.test(b) ? a - b : lc(a) < lc(b) ? -1 : +(lc(a) > lc(b));

export function test(v, op, x) {
  const arr = Array.isArray(v), empty = arr ? !v.length : v === '';
  if (op === 'filled') return !empty;
  if (op === 'empty') return empty;
  if (op === '=' || op === '!=') return (arr ? v.includes(x) : lc(v) === lc(x)) === (op === '=');
  if (op === 'contains') return arr ? v.includes(x) : lc(v).includes(lc(x));
  if (empty || arr) return false;
  const c = cmp(v, x);
  return op === '>' ? c > 0 : c < 0;
}

const group = ([any, rules], get) => {
  for (const [f, op, x] of rules) {
    const ok = test(get(f), op, x);
    if (ok === !!any) return ok;
  }
  return !any;
};

/** Bedingungen an ein Formular binden; prefix „f“ = Felder heißen f[name] (Verwaltung), '' = name (Website) */
export function conditions(form, cfg, prefix = '') {
  const names = Object.keys(cfg);
  if (!names.length) return;
  const els = n => {
    const k = prefix ? `${prefix}[${n}]` : n;
    return [...form.querySelectorAll(`[name="${k}"],[name^="${k}["]`)];   // auch Mehrfachauswahl k[] und Gruppen k[0][unterfeld]
  };
  const read = n => {
    const list = els(n), box = list.filter(e => e.type === 'checkbox');
    // Wiederholbare Gruppe: nur „ausgefüllt“ (mindestens ein Wert in irgendeiner Zeile) oder leer
    if (list[0]?.closest('[data-group],.rep')) return list.some(e => e.type !== 'hidden' && (e.type === 'checkbox' ? e.checked : e.value.trim())) ? '1' : '';
    if (box.length) {
      const on = box.filter(e => e.checked).map(e => e.value);
      return box[0].name.endsWith('[]') ? on : on.length ? '1' : '';
    }
    if (list[0]?.type === 'radio') return list.find(e => e.checked)?.value || '';
    const e = list.find(e => e.type !== 'hidden') || list[0];
    if (!e) return '';
    return e.hasAttribute('data-iban') ? e.value.replace(/\s/g, '').toUpperCase() : e.value.trim().replace(/^(\d{4}-\d\d-\d\d)T/, '$1 ');
  };
  const run = () => {
    const vis = {};
    const get = n => vis[n] === false ? '' : read(n);
    for (let i = 0, ch = 1; ch && i <= names.length; i++) {
      ch = 0;
      for (const n of names) {
        if (!cfg[n].v) continue;
        const ok = group(cfg[n].v, get);
        if (ok !== (vis[n] !== false)) { vis[n] = ok; ch = 1; }
      }
    }
    for (const n of names) {
      const box = els(n)[0]?.closest('[data-cf],.f');
      if (!box) continue;
      const hide = vis[n] === false;
      box.hidden = hide;
      box.querySelectorAll('input,select,textarea,button').forEach(e => {
        if (hide && !e.disabled) { e.disabled = true; e.dataset.cd = 1; }
        else if (!hide && e.dataset.cd) { e.disabled = false; delete e.dataset.cd; }
      });
      if (cfg[n].r) {
        const req = !hide && (!!cfg[n].q || group(cfg[n].r, get));
        box.classList.toggle('is-req', req);
        box.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(e => {
          if (!e.name.endsWith('[]') && !e.name.includes('][')) { e.required = req; req ? e.setAttribute('aria-required', 'true') : e.removeAttribute('aria-required'); }
        });
      }
    }
  };
  form.addEventListener('input', run);
  form.addEventListener('change', run);
  run();
}
