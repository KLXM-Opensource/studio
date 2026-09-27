/*
 * Formulare „praxis“ (Rezept / Überweisung): Proof-of-Work, Prüfung je Feld, Absenden per fetch.
 * Formularseite /anfrage/…: direkt eingebunden (templates/partials/form.php). Startseite: erst beim Umdrehen der
 * Kontaktkarte nachgeladen (site.js, data-assets) – ruft dann window.praxisForm(form, true) auf.
 */
(() => {
const d = document, root = d.documentElement;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const L = JSON.parse(root.dataset.l10n || '{}');
// Wiederholbare Gruppen: eigenes Skript, erst bei Bedarf geladen
const groupSrc = d.currentScript?.src.replace('form.js', 'group.js');
let groupsLoaded = false;
const loadGroups = () => { if (groupsLoaded || !groupSrc) return; groupsLoaded = true; d.head.append(Object.assign(d.createElement('script'), { src: groupSrc })); };

const enc = new TextEncoder();
async function solve(token, bits) {
  if (!bits || !crypto.subtle) return '';
  const full = bits >> 3, rest = bits & 7, mask = rest ? 0xff << (8 - rest) & 0xff : 0;
  for (let n = 0; ; n += 256) {
    const batch = [];
    for (let i = 0; i < 256; i++) batch.push(crypto.subtle.digest('SHA-256', enc.encode(token + ':' + (n + i))));
    const res = await Promise.all(batch);
    for (let i = 0; i < 256; i++) {
      const b = new Uint8Array(res[i]);
      let ok = true;
      for (let j = 0; j < full; j++) if (b[j]) { ok = false; break; }
      if (ok && (!rest || !(b[full] & mask))) return String(n + i);
    }
  }
}

function prepareForm(form) {
  if (form._ready) return form._ready;
  const key = form.dataset.form, tokenEl = form.elements._token, powEl = form.elements._pow;
  const inline = form.elements._difficulty;
  form._ready = (async () => {
    let token = tokenEl.value, diff = inline ? +inline.value : 0;
    if (!token) {
      const r = await fetch(form.action.replace(/\/anfrage\//, '/api/form/'), { headers: { Accept: 'application/json' }, credentials: 'omit' });
      const c = await r.json();
      token = tokenEl.value = c.token; diff = c.difficulty;
    }
    powEl.value = await solve(token, diff);
  })();
  return form._ready;
}

function setError(form, name, msg) {
  // Gruppen: „medikamente.1.medikament“ → Feld medikamente[1][medikament]; „medikamente“ → Fieldset der Gruppe
  const input = form.elements[name.replace(/\.(\w+)/g, '[$1]')] || $(`[data-cf="${name}"]`, form);
  const el = input && d.getElementById(input.id + '-e');
  if (!el) return;
  el.textContent = msg || ''; el.hidden = !msg;
  msg ? input.setAttribute('aria-invalid', 'true') : input.removeAttribute('aria-invalid');
}

$$('form[data-form]').forEach(form => {
  const msg = $('.pform__msg', form), btn = $('[type=submit]', form);
  // Gruppen: auf Formularseiten sofort, in der Flip-Karte beim Umdrehen bzw. ersten Fokus
  if ($('[data-group]', form)) form.closest('[data-flipcard]') ? form.addEventListener('focusin', loadGroups, { once: true }) : loadGroups();
  form.addEventListener('focusin', () => prepareForm(form), { once: true });
  form.addEventListener('submit', async e => {
    e.preventDefault();
    msg.hidden = true;
    // Clientseitige Prüfung: Fehlermeldung je Feld, Fokus auf erstes fehlerhaftes Feld
    let first = null;
    $$('[data-group]', form).forEach(g => setError(form, g.dataset.group, ''));
    $$('input,select', form).forEach(i => {
      if (!i.name || i.name[0] === '_' || i.closest('.hp')) return;
      const bad = !i.checkValidity();
      setError(form, i.name, bad ? (i.type === 'checkbox' ? L.confirm : L.fill) : '');
      if (bad && !first) first = i;
    });
    if (first) { first.focus(); return; }
    btn.setAttribute('aria-busy', 'true'); btn.disabled = true;
    try {
      await prepareForm(form);
      const r = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'omit' });
      const res = await r.json();
      if (res.ok) {
        form.hidden = true;
        const done = form.nextElementSibling;
        done.hidden = false; done.setAttribute('tabindex', '-1'); done.focus();
        return;
      }
      Object.entries(res.errors || {}).forEach(([n, m]) => setError(form, n, m));
      const bad = $('[aria-invalid=true]', form);
      msg.textContent = res.message || L.check; msg.hidden = false;
      (bad || msg).focus?.();
      if (!res.errors) { form._ready = null; form.elements._token.value = ''; } // Token verbraucht/abgelaufen → neues holen
    } catch {
      msg.textContent = L.failed;
      msg.hidden = false;
    } finally { btn.removeAttribute('aria-busy'); btn.disabled = false; }
  });
});

// Aufruf aus site.js beim Umdrehen der Karte: Sicherheitsprüfung schon vorbereiten, Gruppen laden
window.praxisForm = form => { prepareForm(form); if ($('[data-group]', form)) loadGroups(); };
$$('.flip.is-flipped form[data-form]').forEach(window.praxisForm);
})();
