/*
 * Formular (Datentabelle) auf der Website – ohne Abhängigkeiten.
 * Bedingungen (anzeigen / Pflicht wenn), Proof-of-Work des Spamschutzes (wie die Online-Anfragen), Absenden ohne Neuladen
 * mit Fehlerübersicht und Fehlern am Feld. Ohne JavaScript sendet das Formular klassisch an /formular/{tabelle}.
 */
import { conditions } from './_conditions.js';
import { groups } from './_group.js';
import { legalDialogs } from './_legal.js';

legalDialogs();   // „Datenschutzhinweise“ im Dialog statt neuem Tab

const enc = new TextEncoder();
async function solve(token, bits) {
  if (!bits || !crypto.subtle) return '';
  const full = bits >> 3, mask = 0xff << (8 - (bits & 7)) & 0xff;
  for (let n = 0; ; n += 256) {
    const res = await Promise.all(Array.from({ length: 256 }, (_, i) => crypto.subtle.digest('SHA-256', enc.encode(token + ':' + (n + i)))));
    for (let i = 0; i < 256; i++) {
      const b = new Uint8Array(res[i]);
      if (b.subarray(0, full).every(x => !x) && !(b[full] & mask)) return String(n + i);
    }
  }
}

document.querySelectorAll('form[data-dff]').forEach(form => {
  const el = form.elements, q = s => form.querySelector(s), sum = q('[data-dff-summary]'), btn = q('[type=submit]');
  const done = form.nextElementSibling;
  let ready;
  groups(form);
  conditions(form, JSON.parse(form.dataset.cond || '{}'));
  const prep = () => ready ||= (async () => {
    let token = el._token.value, diff = +(el._difficulty?.value || 0);
    if (!token) {
      const c = await (await fetch(form.action + '/challenge', { headers: { Accept: 'application/json' }, credentials: 'omit' })).json();
      token = el._token.value = c.token; diff = c.difficulty;
    }
    el._pow.value = await solve(token, diff);
  })();
  form.addEventListener('focusin', prep, { once: true });
  // IBAN in Vierergruppen
  form.addEventListener('change', e => {
    const t = e.target;
    if (t.hasAttribute('data-iban')) t.value = t.value.replace(/\s+/g, '').toUpperCase().replace(/(.{4})(?!$)/g, '$1 ');
  });
  const show = (msg, errors = {}) => {
    form.querySelectorAll('.dff-err').forEach(p => { p.hidden = true; p.textContent = ''; });
    form.querySelectorAll('[aria-invalid]').forEach(i => i.removeAttribute('aria-invalid'));
    const ul = sum.querySelector('ul');
    ul.textContent = '';
    for (const [n, m] of Object.entries(errors)) {
      const box = q(`[data-cf="${n}"],[data-ef="${n}"]`), p =box?.querySelector('.dff-err'), input = box?.querySelector('input,select,textarea');
      if (!p) continue;
      p.textContent = m; p.hidden = false;
      input?.setAttribute('aria-invalid', 'true');
      ul.insertAdjacentHTML('beforeend', '<li><a></a></li>');
      Object.assign(ul.lastChild.firstChild, { href: '#' + (input?.id || box.id), textContent: m });
    }
    sum.firstChild.textContent = msg;
    sum.hidden = false;
    sum.focus();
  };
  form.addEventListener('submit', async e => {
    e.preventDefault();
    btn.disabled = true; btn.setAttribute('aria-busy', 'true');
    try {
      await prep();
      const res = await (await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'omit' })).json();
      if (res.ok) {
        form.hidden = true;
        done.firstChild.textContent = form.dataset.success || res.message;
        done.hidden = false; done.focus();
        return;
      }
      show(res.message || form.dataset.check, res.errors);
      if (!res.errors) { ready = null; el._token.value = ''; }   // Token verbraucht oder abgelaufen → neues holen
    } catch {
      show(form.dataset.failed);
    } finally { btn.disabled = false; btn.removeAttribute('aria-busy'); }
  });
});
