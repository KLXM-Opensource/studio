/*
 * Sichtbarkeit im Formular (Core\Data\Exposure, Core\Fields::renderExpose, app/Admin/views/data/_visibility.php):
 *  - Merkmal „Öffentlich · Website, vCard“: Kurzform der angehakten Ziele aktuell halten (Zustand selbst zeigt CSS per :has),
 *  - Menüs schließen bei Klick daneben und mit Escape (Fokus zurück aufs Merkmal),
 *  - Formularkopf „Auf der Website: …“ und Hinweis „nicht auf der Website“ beim Tippen aktualisieren.
 * Den Anfangsstand rendert der Server – ohne JavaScript fehlt nur die Aktualisierung. Gleiche Regeln wie Exposure::webSummary():
 * ausgefüllte Felder, die auf der Website stehen; Mehrfachfelder je freigegebener Angabe „Telefon (Mobil)“; keine Ja/Nein-Felder.
 */
const MARK = '.f-exp__mark';

function filled(box) {
  if (box.querySelector(':scope > .f.f--bool')) return false;
  for (const el of box.querySelectorAll('input[name],select[name],textarea[name]')) {
    if (el.closest(MARK) || el.disabled) continue;
    if (el.type === 'hidden') { if (el.closest('.media-field') && el.value.trim() !== '') return true; continue; }
    if (el.type === 'checkbox' || el.type === 'radio') { if (el.checked) return true; continue; }
    if (el.value.trim() !== '') return true;
  }
  return [...box.querySelectorAll('.rte-area')].some(a => a.textContent.trim() !== '');
}

function rowValue(row) {
  const v = row.querySelector('[name$="[value]"]');
  if (v) return v.value.trim();
  return ['street', 'zip', 'city', 'country'].map(k => row.querySelector(`[name$="[${k}]"]`)?.value.trim() || '').join(' ').trim();
}

function rowLabel(row) {
  const sel = row.querySelector('select[name$="[label]"]');
  if (!sel) return '';
  if (sel.value === 'custom') return row.querySelector('[name$="[label_custom]"]')?.value.trim() || '';
  return sel.selectedOptions[0]?.text || '';
}

function webOn(box) {
  if (box.dataset.web !== undefined) return box.dataset.web === '1';
  return !!box.querySelector('[data-expose-target="web"]:checked');
}

function update(form) {
  // Kurzform der Ziele an „Redaktion entscheidet“
  form.querySelectorAll('.f-exp--editor[data-exp-short]').forEach(box => {
    const to = box.querySelector('[data-exp-to]');
    if (!to) return;
    const on = [...box.querySelectorAll('[data-expose-target]:checked')].map(c => c.dataset.short);
    to.textContent = on.length ? ' · ' + on.join(', ') : '';
  });
  const sum = form.querySelector('[data-pubsum]');
  if (!sum) return;
  const parts = [];
  form.querySelectorAll('.f-exp[data-expose]').forEach(box => {
    if (box.closest('[hidden]') || box.querySelector(':scope > .f[hidden]') || !webOn(box)) return;
    const label = box.dataset.expLabel || '';
    if (box.hasAttribute('data-exp-multi')) {
      box.querySelectorAll('.rep-row').forEach(row => {
        const v = row.querySelector('[data-expose-item]:checked')?.value || '0';
        if (v === '0' || rowValue(row) === '') return;
        const bits = [rowLabel(row), v === 'city' ? sum.dataset.city : ''].filter(Boolean);
        parts.push(label + (bits.length ? ' (' + bits.join(', ') + ')' : ''));
      });
    } else if (filled(box)) parts.push(label);
  });
  const list = sum.querySelector('[data-pubsum-list]');
  list.textContent = parts.length ? parts.join(', ') : list.dataset.none;
  // Eintrag nicht auf der Website: Kontakt ohne „Auf der Website zeigen“ bzw. Entwurf
  const ef = sum.dataset.entryField ? form.querySelector(`input[type=checkbox][name="${sum.dataset.entryField}"]`) : null;
  const hidden = ef ? !ef.checked : false;
  const st = form.querySelector('input[name=status]:checked');
  const draft = st ? st.value === 'draft' : sum.dataset.draft === '1';
  const off = hidden || draft;
  sum.classList.toggle('is-off', off);
  sum.querySelector('[data-pubsum-off]').hidden = !off;
  sum.querySelector('[data-why=hidden]').hidden = !hidden;
  sum.querySelector('[data-why=draft]').hidden = !draft;
  sum.querySelector('[data-k=on]').hidden = off;
  sum.querySelector('[data-k=off]').hidden = !off;
}

let global = false;
export function initExposure(scope = document) {
  const forms = new Set();
  scope.querySelectorAll?.('[data-pubsum], .f-exp[data-expose]').forEach(n => { const f = n.closest('form'); if (f) forms.add(f); });
  forms.forEach(form => {
    if (form._exp) return;
    form._exp = true;
    const later = () => requestAnimationFrame(() => update(form));
    form.addEventListener('input', later);
    form.addEventListener('change', later);
    update(form);
  });
  if (global) return;
  global = true;
  // Menüs schließen: Klick daneben (auch aus dem Shadow DOM der Seitenleiste, composedPath) und Escape
  document.addEventListener('click', e => {
    const path = e.composedPath();
    const root = path.find(n => n instanceof ShadowRoot) || document;
    root.querySelectorAll('details.f-exp__menu[open]').forEach(m => { if (!path.includes(m)) m.open = false; });
  }, true);
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    const m = e.composedPath().find(n => n instanceof Element && n.matches('details.f-exp__menu[open]'));
    if (!m) return;
    e.stopPropagation();
    m.open = false;
    m.querySelector('summary')?.focus();
  }, true);
}
