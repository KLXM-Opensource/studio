// Redaktionsnotizen (Core\\EditorNotes): Im Bearbeiten-Modus erscheint „[# … #]“ als nicht bearbeitbarer Hinweis „Notiz: …“
// (.cms-note). Ein Klick darauf fragt: „Als Text übernehmen“ (die Notiz wird normaler Text, die Hervorhebung verschwindet) oder
// „Notiz löschen“. Danach ein input-Ereignis am Feld – Seiten- und Eintrags-Editor übernehmen die Änderung wie getippten Text.
import { layerBox, deepActive } from './_shadow.js';
import { t } from './_i18n.js';

const d = document;
let dlg = null;
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

function pick(text) {
  if (!dlg) {
    dlg = d.createElement('dialog');
    dlg.className = 'adm-dialog adm-dialog--small cms-confirm';
    dlg.setAttribute('aria-labelledby', 'cms-note-t');
    layerBox().append(dlg);
  }
  dlg.innerHTML = `<h2 id="cms-note-t">${esc(t('Redaktionsnotiz'))}</h2>
    <p class="cms-note-dlg__text">${esc(text)}</p>
    <p>${esc(t('Notizen sieht nur die Redaktion. Als Text übernehmen macht daraus normalen, sichtbaren Text.'))}</p>
    <div class="cms-confirm__foot"><span class="cms-confirm__right">
      <button type="button" class="adm-btn" data-r="" autofocus>${esc(t('Abbrechen'))}</button>
      <button type="button" class="adm-btn adm-btn--danger" data-r="delete">${esc(t('Notiz löschen'))}</button>
      <button type="button" class="adm-btn adm-btn--primary" data-r="text">${esc(t('Als Text übernehmen'))}</button>
    </span></div>`;
  const before = deepActive();
  return new Promise(res => {
    const done = r => { dlg.close(); res(r); };
    dlg.querySelectorAll('[data-r]').forEach(b => b.addEventListener('click', () => done(b.dataset.r || null)));
    dlg.addEventListener('cancel', e => { e.preventDefault(); done(null); }, { once: true });
    dlg.addEventListener('close', () => { if (before && before.isConnected) before.focus?.({ preventScroll: true }); }, { once: true });
    dlg.showModal();
  });
}

d.addEventListener('click', async e => {
  const note = e.target.closest?.('.cms-note[data-cms-note]');
  if (!note) return;
  const field = note.closest('[contenteditable="true"],[contenteditable="plaintext-only"]');
  if (!field) return;
  e.preventDefault();
  e.stopPropagation();
  const r = await pick(note.dataset.cmsNote || '');
  if (!r || !note.isConnected) return;
  if (r === 'text') note.replaceWith(d.createTextNode(note.dataset.cmsNote || ''));
  else note.remove();
  field.dispatchEvent(new Event('input', { bubbles: true }));
  field.focus({ preventScroll: true });
}, true);
