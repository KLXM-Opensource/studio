// Administration → Funktionen & Erweiterungen (/admin/funktionen, app/Admin/views/features.php):
// Sicherheitsrelevante Schalter (data-ft-risk) fragen im Dialog nach Bestätigung und Passwort – oder Passkey ([data-pk-reauth]
// mit data-stay, Ereignis „pk:reauthed“); nach einer Bestätigung in den letzten 15 Minuten (data-ft-recent) nur den Hinweis.
// Ohne JavaScript steht die Bestätigung direkt in der Zeile (data-ft-inline) und wird mitgesendet.
export function initFeatures(d = document) {
  const dlg = d.getElementById('ft-dialog');
  if (!dlg || typeof dlg.showModal !== 'function') return;
  d.querySelectorAll('form[data-ft-risk] [data-ft-inline]').forEach(el => {
    el.hidden = true;
    el.querySelectorAll('input').forEach(i => { i.required = false; });
  });
  const pw = dlg.querySelector('[data-ft-dpw]'), ok = dlg.querySelector('[data-ft-dok]'), go = dlg.querySelector('[data-ft-dgo]');
  let current = null, confirmed = 'ftRecent' in dlg.dataset;
  const upd = () => { go.disabled = !(ok.checked && (confirmed || (pw && pw.value.length > 0))); };
  pw?.addEventListener('input', upd);
  ok.addEventListener('change', upd);
  // Mit Passkey bestätigt: Passwort entfällt, Hinweis bleibt im Dialog stehen
  dlg.addEventListener('pk:reauthed', () => {
    confirmed = true;
    if (pw) pw.closest('.f').hidden = true;
    dlg.querySelector('[data-pk-reauth-btn]')?.setAttribute('hidden', '');
    dlg.querySelector('.acc-reauth__or')?.setAttribute('hidden', '');
    upd();
    ok.focus();
  });
  // Enter im Passwortfeld = Einschalten (der erste Knopf des Dialogs ist „Abbrechen“)
  pw?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); if (!go.disabled) dlg.close('ok'); } });
  d.addEventListener('submit', e => {
    const f = e.target.closest?.('form[data-ft-risk]');
    if (!f || f.dataset.ftArmed) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    current = f;
    dlg.querySelector('[data-ft-dtitle]').textContent = f.dataset.ftTitle || '';
    dlg.querySelector('[data-ft-drisk]').textContent = f.dataset.ftRisk || '';
    if (pw) pw.value = '';
    ok.checked = false;
    upd();
    dlg.returnValue = '';
    dlg.showModal();
    (confirmed || !pw ? ok : pw).focus();
  }, true);
  dlg.addEventListener('close', () => {
    const f = current;
    current = null;
    const value = pw ? pw.value : '';
    if (pw) pw.value = '';
    if (!f || dlg.returnValue !== 'ok' || (!value && !confirmed)) return;
    const fp = f.querySelector('input[name=password]');
    if (fp) { fp.value = value; fp.required = false; }
    f.querySelector('input[name=confirm]').checked = true;
    f.dataset.ftArmed = '1';
    f.submit();
  });
}
