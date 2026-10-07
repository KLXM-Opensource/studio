/*
 * Verwaltung → Mitteilungen → Verfassen (app/Admin/views/push/compose.php): Vorschau Telefon/Computer live, erreichbare Geräte
 * für die gewählten Empfänger (GET /admin/api/push/reach), Zeitpunkt, Fokus auf die Bestätigung.
 */
import { t } from './_i18n.js';

const d = document;

export function initPushAdmin() {
  const form = d.querySelector('[data-pm-compose]');
  if (!form) return;
  const $$ = s => [...form.querySelectorAll(s)];
  const title = form.querySelector('[data-pm-title]');
  const body = form.querySelector('[data-pm-body]');
  const imgBox = form.querySelector('[data-pm-image]');
  const preview = () => {
    $$('[data-pm-p-title]').forEach(el => { el.textContent = title.value.trim() || t('Titel der Mitteilung'); });
    $$('[data-pm-p-body]').forEach(el => { el.textContent = body.value.trim(); });
    const src = imgBox?.querySelector('.media-field-preview img')?.getAttribute('src') || '';
    const hasImg = !!imgBox?.querySelector('input[type=hidden]')?.value && src;
    $$('[data-pm-p-image]').forEach(el => { el.hidden = !hasImg; if (hasImg) el.src = src; });
  };
  form.addEventListener('input', preview);
  // Bildauswahl (Mediathek) ändert das versteckte Feld und die Vorschau ohne input-Ereignis
  if (imgBox) new MutationObserver(preview).observe(imgBox, { subtree: true, childList: true, attributes: true, attributeFilter: ['src', 'value'] });
  preview();

  // Erreichbare Geräte
  const out = form.querySelector('[data-pm-reach]');
  const peopleN = form.querySelector('[data-pm-people-n]');
  let timer = 0, seq = 0;
  const reach = () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      const q = new URLSearchParams();
      $$('input[name="m[topics][]"]:checked').forEach(i => q.append('topics[]', i.value));
      $$('input[name="m[roles][]"]:checked').forEach(i => q.append('roles[]', i.value));
      const users = $$('input[name="m[users][]"]:checked');
      users.forEach(i => q.append('users[]', i.value));
      if (peopleN) peopleN.textContent = users.length ? `(${users.length})` : '';
      if (![...q.keys()].length) { out.textContent = t('Bitte mindestens einen Kanal oder eine Person bzw. Rolle wählen.'); return; }
      const my = ++seq;
      try {
        const r = await (await fetch(form.dataset.pmReachUrl + '?' + q, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })).json();
        if (my !== seq || !r.ok) return;
        out.textContent = form.dataset.pmTReach.replace('{total}', r.total).replace('{visitors}', r.visitors).replace('{staff}', r.staff).replace('{people}', r.people)
          + (r.blocked ? ' ' + t('Testumgebung: Besucher erhalten keine Mitteilung.') : '');
      } catch { /* später erneut */ }
    }, 250);
  };
  form.addEventListener('change', e => { if (e.target.closest('[data-pm-targets]')) reach(); });
  reach();

  // Zeitpunkt: Datum wählen schaltet auf „Planen“
  const at = form.querySelector('[data-pm-at]');
  at?.addEventListener('input', () => { const later = form.querySelector('[data-pm-when][value=later]'); if (later && at.value) later.checked = true; });

  form.querySelector('[data-pm-confirm]')?.focus();
}
