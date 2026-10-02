/**
 * Daten → Externe Quellen → Zuordnung (app/Admin/views/data/source.php, Core\Sources):
 *   - „Auswählen“ je Zeile: Felder der Quelle (Bedeutung, Pfad, Beispiel) mit Filter, per Tastatur bedienbar
 *   - „Beispiel: …“ unter jeder Zeile und Probeabruf live (POST do=probe → JSON, ohne erneuten Abruf der Quelle)
 *   - Erklärung der gewählten Umwandlung; „Erweitert“ öffnet sich bei Umwandlungen mit Option (Vorlage, Kürzen …)
 *   - Fokus nach dem Neuladen auf den Abschnitt, um den es ging (data-autofocus)
 * Ohne JavaScript bleibt alles bedienbar (Formular-Knöpfe „Vorschau laden“, „Zuordnung vorschlagen“, „Probeabruf“).
 */
import { t } from './_i18n.js';


export function initSources() {
  const form = document.querySelector('[data-src-form]');
  if (!form) return;
  const auto = form.querySelector('[data-autofocus]');
  if (auto) { auto.focus({ preventScroll: true }); auto.scrollIntoView({ block: 'start' }); }

  let fields = [];
  try { fields = JSON.parse(document.getElementById('src-fields-data')?.textContent || '[]'); } catch { fields = []; }

  // ---------------------------------------------------------------- Umwandlung: Erklärung + „Erweitert“
  form.addEventListener('change', e => {
    const sel = e.target.closest('[data-src-tx]');
    if (!sel) return;
    const row = sel.closest('tr');
    const help = row?.querySelector('[data-src-txhelp]');
    if (help) help.textContent = sel.selectedOptions[0]?.dataset.help || '';
    if (['template', 'truncate', 'para'].includes(sel.value)) {
      const adv = row?.querySelector('.src-adv');
      if (adv && !adv.open) adv.open = true;
    }
  });

  // ---------------------------------------------------------------- Auswahl der Felder
  const pop = document.createElement('div');
  pop.className = 'src-picker';
  pop.hidden = true;
  pop.setAttribute('role', 'dialog');
  pop.setAttribute('aria-label', t('Feld der Quelle wählen'));
  pop.innerHTML = '<label class="src-picker__label" for="src-picker-q"></label><input id="src-picker-q" type="search" autocomplete="off" spellcheck="false">'
    + '<ul class="src-picker__list" role="list"></ul><p class="src-picker__hint"></p>';
  pop.querySelector('label').textContent = t('Felder filtern');
  pop.querySelector('.src-picker__hint').textContent = t('Pfeiltasten wählen, Enter übernimmt, Esc schließt.');
  document.body.appendChild(pop);
  const q = pop.querySelector('input');
  const list = pop.querySelector('ul');
  let trigger = null, target = null;

  const render = () => {
    const s = q.value.trim().toLowerCase();
    list.textContent = '';
    const hits = fields.filter(f => !s || f.path.toLowerCase().includes(s) || (f.label || '').toLowerCase().includes(s) || (f.sample || '').toLowerCase().includes(s));
    for (const f of hits) {
      const li = document.createElement('li');
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'src-picker__opt';
      b.dataset.path = f.path;
      if (target && target.value.split('|').map(x => x.trim()).includes(f.path)) b.setAttribute('aria-current', 'true');
      const l = document.createElement('span'); l.className = 'src-picker__name'; l.textContent = f.label || f.path;
      const c = document.createElement('code'); c.textContent = f.path;
      const ex = document.createElement('span'); ex.className = 'src-picker__sample'; ex.textContent = f.sample || t('(leer)');
      b.append(l, c, ex);
      li.appendChild(b);
      list.appendChild(li);
    }
    if (!hits.length) { const li = document.createElement('li'); li.className = 'src-picker__none'; li.textContent = t('Keine passenden Felder.'); list.appendChild(li); }
  };
  const place = () => {
    const r = (target.closest('.src-pathbox') || target).getBoundingClientRect();
    const w = Math.min(520, window.innerWidth - 24);
    pop.style.width = w + 'px';
    pop.style.left = Math.max(12, Math.min(r.left + window.scrollX, window.scrollX + window.innerWidth - w - 12)) + 'px';
    pop.style.top = (r.bottom + window.scrollY + 4) + 'px';
  };
  const close = (refocus = true) => {
    if (pop.hidden) return;
    pop.hidden = true;
    trigger?.setAttribute('aria-expanded', 'false');
    if (refocus) trigger?.focus();
    trigger = target = null;
  };
  const open = btn => {
    if (trigger === btn) { close(); return; }
    close(false);
    trigger = btn;
    target = document.getElementById(btn.dataset.srcPick);
    if (!target) return;
    q.value = '';
    render();
    pop.hidden = false;
    place();
    btn.setAttribute('aria-expanded', 'true');
    q.focus();
  };
  const choose = path => {
    const inp = target;
    close(false);
    if (!inp) return;
    inp.value = path;
    inp.dispatchEvent(new Event('input', { bubbles: true }));
    inp.dispatchEvent(new Event('change', { bubbles: true }));
    inp.focus();
  };
  const opts = () => [...list.querySelectorAll('.src-picker__opt')];

  form.addEventListener('click', e => {
    const b = e.target.closest('[data-src-pick]');
    if (b) { e.preventDefault(); open(b); }
  });
  q.addEventListener('input', render);
  pop.addEventListener('click', e => { const o = e.target.closest('.src-picker__opt'); if (o) choose(o.dataset.path); });
  pop.addEventListener('keydown', e => {
    const all = opts();
    const i = all.indexOf(document.activeElement);
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (e.key === 'ArrowDown') { e.preventDefault(); (all[i + 1] || all[0])?.focus(); return; }
    if (e.key === 'ArrowUp') { e.preventDefault(); i <= 0 ? q.focus() : all[i - 1].focus(); return; }
    if (e.key === 'Home' && i >= 0) { e.preventDefault(); all[0]?.focus(); return; }
    if (e.key === 'End' && i >= 0) { e.preventDefault(); all[all.length - 1]?.focus(); return; }
    if (e.key === 'Enter' && document.activeElement === q) { e.preventDefault(); if (all.length) choose(all[0].dataset.path); return; }
    if (e.key === 'Tab') { close(false); }
  });
  document.addEventListener('mousedown', e => { if (!pop.hidden && !pop.contains(e.target) && !e.target.closest('[data-src-pick]')) close(false); });
  window.addEventListener('resize', () => { if (!pop.hidden) place(); });

  // ---------------------------------------------------------------- Beispiel je Zeile + Probeabruf (live)
  const out = form.querySelector('[data-src-dry-out]');
  if (!form.querySelector('[data-src-row]')) return;
  let timer = 0, seq = 0;
  const probe = async () => {
    const my = ++seq;
    const fd = new FormData(form);
    fd.set('do', 'probe');
    let res;
    try {
      const r = await fetch(form.action, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-CSRF-Token': fd.get('_csrf') || '' } });
      res = await r.json();
    } catch { return; }
    if (my !== seq || !res) return;
    if (!res.ok) { if (out) { out.textContent = ''; const p = document.createElement('p'); p.className = 'src-empty'; p.textContent = res.message || ''; out.appendChild(p); } return; }
    for (const [name, ex] of Object.entries(res.fields || {})) {
      const el = form.querySelector(`[data-src-ex="${CSS.escape(name)}"]`);
      if (!el) continue;
      el.className = 'src-ex is-' + ex.state;
      el.textContent = '';
      if (ex.state === 'ok') { const k = document.createElement('span'); k.className = 'src-ex__k'; k.textContent = t('Beispiel:'); el.append(k, ' ' + ex.text); }
      else el.textContent = ex.text;
    }
    const miss = new Set(res.missing || []);
    form.querySelectorAll('[data-src-row]').forEach(tr => tr.classList.toggle('is-missing', miss.has(tr.dataset.srcRow)));
    const mb = document.getElementById('src-missing');
    if (mb) { mb.hidden = !miss.size; const s = mb.querySelector('[data-src-missing]'); if (s) s.textContent = res.missing_text || ''; }
    if (out) renderDry(out, res.items || []);
  };
  const later = () => { clearTimeout(timer); timer = setTimeout(probe, 450); };
  form.addEventListener('input', e => { if (e.target.closest('.src-map, #src-id, #src-slug')) later(); });
  form.addEventListener('change', e => { if (e.target.closest('.src-map, #src-id, #src-slug')) later(); });
  form.querySelector('[data-src-dry]')?.addEventListener('click', async e => {
    e.preventDefault();
    clearTimeout(timer);
    await probe();
    document.getElementById('probeabruf')?.focus();
  });
}

/** Probeabruf wie in source.php (Feld → Wert, Fehler hervorgehoben) – nur Text, kein HTML aus der Quelle */
function renderDry(out, items) {
  const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };
  out.textContent = '';
  items.forEach((m, i) => {
    const a = el('article', 'src-item' + (m.errors?.length ? ' is-error' : ''));
    const h = el('h4', null, m.title || t('Eintrag {n}', { n: i + 1 }));
    const sm = el('small', 'adm-muted', ' ID ');
    sm.appendChild(el('code', null, (m.ext_id || '').slice(0, 70)));
    h.append(' ', sm);
    a.appendChild(h);
    if (m.errors?.length) { const ul = el('ul', 'src-errs'); m.errors.forEach(w => ul.appendChild(el('li', null, '⚠ ' + w))); a.appendChild(ul); }
    const dl = el('dl', 'src-dl');
    const fields = Object.values(m.fields || {});
    for (const fd of fields) {
      const d = el('div', fd.error ? 'is-error' : (fd.empty ? 'is-empty' : ''));
      d.appendChild(el('dt', null, fd.label));
      const dd = el('dd');
      if (fd.media) { dd.appendChild(el('code', null, String(fd.value).slice(0, 90))); if (fd.alt) dd.append(' ', el('small', 'adm-muted', 'Alt: ' + fd.alt)); }
      else if (fd.value === '') dd.appendChild(el('span', 'adm-muted', fd.error ? t('leer – Pflichtfeld') : t('leer')));
      else dd.textContent = String(fd.value).length > 300 ? String(fd.value).slice(0, 299) + '…' : fd.value;
      d.appendChild(dd);
      dl.appendChild(d);
    }
    if (!fields.length) { const d = el('div'); d.append(el('dt', null, t('Ergebnis')), el('dd', 'adm-muted', t('Kein Feld zugeordnet.'))); dl.appendChild(d); }
    a.appendChild(dl);
    if (m.warnings?.length) { const ul = el('ul', 'src-warn'); m.warnings.forEach(w => ul.appendChild(el('li', null, '⚠ ' + w))); a.appendChild(ul); }
    out.appendChild(a);
  });
}
