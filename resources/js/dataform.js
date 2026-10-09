/*
 * Formular (Datentabelle) auf der Website – ohne Abhängigkeiten.
 * Bedingungen (anzeigen / Pflicht wenn), Proof-of-Work des Spamschutzes (wie die Online-Anfragen), Absenden ohne Neuladen
 * mit Fehlerübersicht und Fehlern am Feld. Ohne JavaScript sendet das Formular klassisch an /formular/{tabelle}.
 * Nach dem Absenden: Ereignis „dff:sent“ (detail = Antwort des Servers) am Formular – für Erweiterungen mit eigenem Formular.
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
    // Datei: Größe sofort prüfen (Typ und Größe prüft der Server ohnehin am Inhalt)
    if (t.type === 'file' && t.dataset.maxBytes) {
      const p = t.closest('[data-cf]')?.querySelector('.dff-err'), big = [...t.files].some(f => f.size > +t.dataset.maxBytes);
      if (p) { p.textContent = big ? t.dataset.tooLarge : ''; p.hidden = !big; }
      big ? t.setAttribute('aria-invalid', 'true') : t.removeAttribute('aria-invalid');
    }
  });
  // Kompakte Fehleranzeige, wenn das Kit sie per CSS einschaltet (resources/css/_dataform-errors.css):
  // Feldnamen als Chips, schwebender Knopf „Noch n offen · Nächstes ↓“, Zähler sinkt beim Ausfüllen.
  const compact = getComputedStyle(form).getPropertyValue('--dff-errors').trim() === 'compact';
  const T = JSON.parse(form.dataset.texts || '{}'), fmt = (s, n) => (s || '').replace('{n}', n);
  let open = [], next, sumSeen = false, formSeen = false;
  const nameOf = box => {
    const l = box.querySelector('label,legend')?.cloneNode(true);
    l?.querySelectorAll('.dff-req,input').forEach(x => x.remove());
    return l?.textContent.replace(/\s+/g, ' ').trim() || '';
  };
  const filled = box => {
    const ins = [...box.querySelectorAll('input:not([type=hidden]),select,textarea')];
    const checks = ins.filter(i => i.type === 'checkbox' || i.type === 'radio');
    return checks.length ? checks.some(i => i.checked) : ins.every(i => i.type === 'file' ? i.files.length : i.value.trim() !== '');
  };
  const update = () => {
    const n = open.length;
    if (compact) sum.firstChild.textContent = n ? fmt(n === 1 ? T.one : T.many, n) : T.done;
    if (!next) return;
    next.firstChild.textContent = fmt(T.open, n) + ' · ' + T.next + ' ↓';
    next.hidden = !n || sumSeen || !formSeen;
  };
  if (compact && 'IntersectionObserver' in window) {
    next = Object.assign(document.createElement('button'), { type: 'button', className: (btn?.className || '') + ' dff-next', hidden: true });
    next.append('');
    form.append(next);
    new IntersectionObserver(es => es.forEach(x => { x.target === sum ? sumSeen = x.isIntersecting : formSeen = x.isIntersecting; }) || update()).observe(sum);
    new IntersectionObserver(es => { formSeen = es[0].isIntersecting; update(); }).observe(form);
    next.addEventListener('click', () => {
      if (!open.length) return;
      // nach dem Feld mit Fokus, sonst das erste unterhalb des oberen Fensterrands; am Ende wieder von vorn
      const pos = form.contains(document.activeElement) && document.activeElement !== next ? document.activeElement : null;
      const i = open.findIndex(o => pos ? pos.compareDocumentPosition(o.box) & Node.DOCUMENT_POSITION_FOLLOWING && !o.box.contains(pos) : o.box.getBoundingClientRect().top > 8);
      const o = open[i < 0 ? 0 : i];
      o.input?.focus({ preventScroll: true });
      o.box.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    });
    // Ausgefüllt → Fehler am Feld und Chip verschwinden (der Server prüft beim nächsten Absenden ohnehin alles)
    form.addEventListener('change', e => {
      const o = open.find(o => o.box.contains(e.target));
      if (!o || (o.req && !filled(o.box))) return;
      o.box.querySelector('.dff-err').hidden = true;
      o.box.querySelectorAll('[aria-invalid]').forEach(i => i.removeAttribute('aria-invalid'));
      o.li.remove();
      open = open.filter(x => x !== o);
      const more = sum.querySelector('.dff-more button'), rest = open.length - 3;
      if (more) rest > 0 ? more.textContent = fmt(T.more, rest) : more.parentNode.remove();
      update();
    });
  }
  const show = (msg, errors = {}) => {
    form.querySelectorAll('.dff-err').forEach(p => { p.hidden = true; p.textContent = ''; });
    form.querySelectorAll('[aria-invalid]').forEach(i => i.removeAttribute('aria-invalid'));
    const ul = sum.querySelector('ul');
    ul.textContent = '';
    ul.className = compact ? 'dff-chips' : '';
    open = [];
    for (const [n, m] of Object.entries(errors)) {
      const box = q(`[data-cf="${n}"],[data-ef="${n}"]`), p =box?.querySelector('.dff-err'), input = box?.querySelector('input,select,textarea');
      if (!p) continue;
      p.textContent = m; p.hidden = false;
      input?.setAttribute('aria-invalid', 'true');
      ul.insertAdjacentHTML('beforeend', '<li><a></a></li>');
      const li = ul.lastChild;
      Object.assign(li.firstChild, { href: '#' + (input?.id || box.id), textContent: compact && nameOf(box) || m, title: m });
      open.push({ box, input, li, req: box.matches('.dff-f--req,.is-req') });
    }
    if (compact && open.length > 3) {
      ul.classList.add('is-short');
      ul.insertAdjacentHTML('beforeend', '<li class="dff-more"><button type="button"></button></li>');
      const b = ul.lastChild.firstChild;
      b.textContent = fmt(T.more, open.length - 3);
      b.addEventListener('click', () => { ul.classList.remove('is-short'); ul.querySelector('li:nth-child(4) a')?.focus(); });
    }
    sum.firstChild.textContent = msg;
    if (open.length) update();
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
        form.dispatchEvent(new CustomEvent('dff:sent', { bubbles: true, detail: res }));   // z. B. Buchungskalender: Verfügbarkeit neu laden
        return;
      }
      show(res.message || form.dataset.check, res.errors);
      if (!res.errors) { ready = null; el._token.value = ''; }   // Token verbraucht oder abgelaufen → neues holen
    } catch {
      show(form.dataset.failed);
    } finally { btn.disabled = false; btn.removeAttribute('aria-busy'); }
  });
});
