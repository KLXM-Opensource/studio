/*
 * Kit „foto“ – Fotos im Seiten-Editor (theme.php → 'editor_js'; lädt nur im Bearbeiten-Modus, nie für Besucher).
 *
 *  - Fotos und Videos (MP4) vom Desktop auf eine Fotostrecke oder Bühne ziehen (Bilder auch auf Serie (Kopf), Bild & Text) – oder „Fotos hierher ziehen oder
 *    auswählen“ anklicken (Dateiauswahl mit Mehrfachauswahl, per Tastatur und auf dem Handy).
 *  - Hochladen über die Mediathek des Kerns (window.CMSMedia.Uploader: 1-MB-Stücke über /admin/media/chunk + /finalize,
 *    Fortschritt je Datei, Größen- und Typprüfung mit Meldung, Alt-Text-Pflicht für Bilder bzw. „dekorativ“, KI-Vorschlag).
 *    Nicht passende Dateien (z. B. PDF) lehnt die Ablagefläche mit verständlicher Meldung ab.
 *  - Jede Änderung läuft über CMSEditor.block().set() → markDirty: „Rückgängig“ (⌘Z/Strg+Z) des Editors greift.
 *  - Danach hängt das Skript die Bilder an das Feld des Blocks an (Liste „images“/„slides“) bzw. setzt das Bild („image“/„cover“)
 *    und zeichnet die Vorschau neu – über window.CMSEditor.block(node) (Kern, resources/js/editor.js). Option „Alle als Sammlung“:
 *    neue Sammlung, die Fotostrecke zeigt dann „Alle Bilder einer Sammlung“.
 *  - Reihenfolge: Bilder ziehen oder Pfeile am Bild; „Entfernen“ nimmt das Bild aus dem Block (die Datei bleibt in der Mediathek).
 *  Fehlt die Kern-Schnittstelle CMSEditor.block (ältere Version), werden die Fotos trotzdem hochgeladen; eine Meldung sagt,
 *  wie sie in der Seitenleiste übernommen werden.
 */
{
const d = document;
// Stylesheet des Dialogs (liegt neben diesem Skript, /assets/kits/{kit}/css/editor-photos.css) – im Dialog verlinkt (Shadow-DOM-Ebene)
const CSS_URL = (d.currentScript?.src || '').replace(/\/js\/editor-photos\.js.*$/, '/css/editor-photos.css');
const t = (s, v) => (window.CMSAdmin && window.CMSAdmin.t ? window.CMSAdmin.t(s, v || {}) : Object.entries(v || {}).reduce((o, [k, x]) => o.replaceAll('{' + k + '}', x), s));
const toast = (s, kind) => (window.CMSAdmin && window.CMSAdmin.toast ? window.CMSAdmin.toast(s, kind || 'ok', 4000) : null);
const api = node => (window.CMSEditor && typeof window.CMSEditor.block === 'function' ? window.CMSEditor.block(node) : null);
const IMAGE = /^image\/(jpeg|png|webp|gif)$/;
const VIDEO = /^video\/mp4$/;
/** Dateien nach Art der Ablagefläche trennen: passende und abgelehnte (mit verständlichem Grund) */
const sortFiles = (list, accept) => {
  const ok = [], bad = [];
  for (const f of list) {
    if (IMAGE.test(f.type) || (accept === 'visual' && VIDEO.test(f.type))) ok.push(f);
    else bad.push(f.name);
  }
  return { ok, bad };
};
const rejectMsg = (bad, accept) => t('„{names}“ geht hier nicht – erlaubt sind {what}.', {
  names: bad.slice(0, 3).join('“, „') + (bad.length > 3 ? ' …' : ''),
  what: accept === 'visual' ? t('Bilder (JPG, PNG, WebP, GIF) und Videos (MP4)') : t('Bilder (JPG, PNG, WebP, GIF)'),
});
const hasFiles = e => [...(e.dataTransfer?.types || [])].includes('Files');
const status = (zone, s) => { const el = zone.querySelector('.fdz__status'); if (el) el.textContent = s; };
const blockEl = zone => zone.closest('.cms-block') || zone;

/** Werte für den Block nach dem Hochladen: Liste ergänzen bzw. Bild setzen (bei mehreren Dateien ggf. Variante wechseln) */
function changesFor(zone, blk, media, collection) {
  const field = zone.dataset.field, mode = zone.dataset.mode;
  if (collection) return { source: 'collection', collection };
  if (mode === 'single') {
    if (media.length > 1 && zone.dataset.switchVariant) {
      const list = (blk.get(zone.dataset.switchField) || []).filter(x => x && x.image);
      return { variant: zone.dataset.switchVariant, [zone.dataset.switchField]: [...list, ...media.map(m => ({ image: m.id, caption: '' }))] };
    }
    return { [field]: media[0].id };
  }
  const list = (blk.get(field) || []).filter(x => x && x.image);
  return { [field]: [...list, ...media.map(m => ({ image: m.id, caption: '' }))] };
}

/** Dialog mit der Upload-Warteschlange des Kerns (Alt-Text je Bild, Fortschritt je Datei) */
async function upload(zone, list) {
  const M = window.CMSMedia;
  if (!M || !M.Uploader || !M.ui) { toast(t('Die Mediathek ist noch nicht geladen – bitte kurz warten und erneut versuchen.'), 'error'); return; }
  const single = zone.dataset.mode === 'single' && !zone.dataset.switchVariant;
  if (single && list && list.length > 1) list = list.slice(0, 1);
  const dlg = M.ui.inBox('foto-upload', '<dialog id="foto-upload" class="fx-dialog foto-up" aria-labelledby="foto-up-title"></dialog>');
  const allowCol = zone.hasAttribute('data-allow-collection');
  const pageTitle = (d.querySelector('#cms-editor-config') && JSON.parse(d.querySelector('#cms-editor-config').textContent).page?.title) || '';
  const vid = zone.dataset.accept === 'visual';
  dlg.innerHTML = `${CSS_URL ? `<link rel="stylesheet" href="${M.ui.esc(CSS_URL)}">` : ''}<div class="fx-dhead"><h2 id="foto-up-title">${M.ui.esc(single ? (vid ? t('Foto oder Video hochladen') : t('Foto hochladen')) : (vid ? t('Fotos und Videos hochladen') : t('Fotos hochladen')))}</h2>
    <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-close>${M.ui.esc(t('Schließen'))}</button></div>
    <div class="foto-up__body">
    <p class="foto-up__intro">${M.ui.esc(t('Bitte je Bild kurz beschreiben, was zu sehen ist (Alt-Text) – oder „dekorativ“ wählen. Danach „Hochladen“.'))}${vid ? ' ' + M.ui.esc(t('Videos brauchen keinen Alt-Text; große Videos werden in Stücken hochgeladen – bitte das Fenster offen lassen.')) : ''}</p>
    ${allowCol ? `<p class="foto-up__col"><label><input type="checkbox" data-as-col> ${M.ui.esc(t('Alle als Sammlung anlegen'))}</label>
      <label>${M.ui.esc(t('Name der Sammlung'))} <input type="text" data-col-name value="${M.ui.esc(pageTitle)}" maxlength="120"></label></p>` : ''}
    <div class="foto-up__host"></div>
    <p class="foto-up__status" role="status" aria-live="polite" data-up-status></p>
    <p class="foto-up__foot"><button type="button" class="adm-btn adm-btn--small" data-more>${M.ui.esc(vid ? t('Weitere Dateien auswählen') : t('Weitere Fotos auswählen'))}</button></p>
    </div>`;
  const say = s => { dlg.querySelector('[data-up-status]').textContent = s; status(zone, s); };
  const done = [];
  let colId = zone.dataset.collection ? +zone.dataset.collection : 0, colAsked = false;
  const up = new M.Uploader(dlg.querySelector('.foto-up__host'), {
    accept: zone.dataset.accept === 'visual' ? 'visual' : 'image',   // Größe und Art prüft der Uploader des Kerns mit eigener Meldung
    collection: () => colId,
    onDone: m => { if (m && m.id) done.push(m); say(t('{n} Datei(en) hochgeladen …', { n: done.length })); setTimeout(finish, 0); },
    onChange: () => finish(),
  });
  // „Alle als Sammlung“: Sammlung vor dem ersten Hochladen anlegen (Uploader fragt collection() je Datei ab)
  const startBtn = dlg.querySelector('[data-mu-start]');
  startBtn.addEventListener('click', async e => {
    const box = dlg.querySelector('[data-as-col]');
    if (!box || !box.checked || colAsked) return;
    e.stopImmediatePropagation();
    colAsked = true;
    try {
      const name = (dlg.querySelector('[data-col-name]').value || pageTitle || t('Fotostrecke')).trim();
      colId = +(await M.api.collection(name)).id;
      say(t('Sammlung „{name}“ angelegt.', { name }));
    } catch (err) { say(t('Sammlung konnte nicht angelegt werden: {msg}', { msg: err.message })); colAsked = false; return; }
    up.start();
  }, true);
  let applied = false;
  async function finish() {
    const busy = up.items.some(i => i.status === 'wait' && !i.error) || up.items.some(i => i.status === 'up');
    if (busy || applied || !done.length) return;
    applied = true;
    const blk = api(zone);
    const asCol = zone.dataset.mode === 'list' && dlg.querySelector('[data-as-col]')?.checked && colId;
    if (zone.dataset.mode === 'collection') {
      // Fotostrecke zeigt eine Sammlung: Bilder sind schon darin – nur neu zeichnen
      if (blk) await blk.set({ collection: blk.get('collection') });
    } else if (blk) {
      await blk.set(changesFor(zone, blk, done, asCol ? colId : 0));
    }
    const msg = !blk && zone.dataset.mode !== 'collection'
      ? t('{n} Datei(en) sind in der Mediathek. Diese Version des Editors kann sie noch nicht automatisch einfügen – bitte in der Seitenleiste auswählen.', { n: done.length })
      : t('{n} Datei(en) eingefügt – noch nicht gespeichert. Rückgängig: ⌘Z bzw. Strg+Z.', { n: done.length });
    toast(msg, blk ? 'ok' : 'info');
    say(msg);
    setTimeout(() => dlg.open && dlg.close(), 900);
  }
  dlg.querySelector('[data-close]').onclick = () => dlg.close();
  dlg.querySelector('[data-more]').onclick = () => up.choose();
  dlg.onclose = () => { dlg.innerHTML = ''; };
  dlg.showModal();
  if (list && list.length) up.add(list); else up.choose();
}

/** Bild verschieben/entfernen (Index im Feld des Blocks) */
async function itemAction(btn) {
  const tools = btn.closest('[data-foto-item]');
  const blk = api(tools);
  if (!blk) { toast(t('Diese Version des Editors unterstützt das noch nicht – bitte die Seitenleiste nutzen.'), 'info'); return; }
  const field = tools.dataset.field, i = +tools.dataset.fotoItem;
  const list = blk.get(field) || [];
  const act = btn.dataset.fotoAct;
  // Nachbarn mit Bild suchen (leere Einträge überspringen)
  const step = act === 'prev' ? -1 : 1;
  let j = i + step;
  while (j >= 0 && j < list.length && !(list[j] && list[j].image)) j += step;
  if (act === 'remove') list.splice(i, 1);
  else if (j >= 0 && j < list.length) [list[i], list[j]] = [list[j], list[i]];
  else return;
  await blk.set({ [field]: list });
  toast(act === 'remove' ? t('Foto aus der Fotostrecke entfernt (bleibt in der Mediathek).') : t('Reihenfolge geändert – noch nicht gespeichert.'));
}

/** Nach jedem Neuzeichnen: Knöpfe wieder per Tastatur erreichbar (der Editor setzt tabIndex = -1), Bilder ziehbar */
function setup(root) {
  setTimeout(() => {
    root.querySelectorAll('[data-foto-choose],.fdz-tool').forEach(b => { b.tabIndex = 0; });
    root.querySelectorAll('.pg__item:has([data-foto-item])').forEach(li => { li.draggable = true; });
    root.querySelectorAll('.pg__item a.ph__box,.pg__item img').forEach(el => { el.draggable = false; });
  }, 0);
}
d.addEventListener('cms:block-preview', e => setup(e.target));
d.addEventListener('DOMContentLoaded', () => setup(d));

// Klicks: Auswahl öffnen bzw. Werkzeug ausführen (Editor soll den Klick nicht als Blockauswahl verarbeiten)
d.addEventListener('click', e => {
  const choose = e.target.closest?.('[data-foto-choose]');
  const tool = e.target.closest?.('[data-foto-act]');
  if (!choose && !tool) return;
  e.preventDefault(); e.stopPropagation();
  if (choose) upload(choose.closest('[data-foto-drop]'), null);
  else if (!tool.disabled) itemAction(tool);
}, true);

// Dateien vom Desktop: auf die Ablagefläche oder irgendwo auf den Block mit Ablagefläche
const zoneFor = e => { const b = e.target.closest?.('.cms-block') || e.target.closest?.('[data-foto-drop]'); return b ? (b.matches('[data-foto-drop]') ? b : b.querySelector('[data-foto-drop]')) : null; };
let over = null;
const mark = z => { if (over && over !== z) over.classList.remove('is-over'); over = z; z?.classList.add('is-over'); };
d.addEventListener('dragover', e => {
  if (!hasFiles(e)) return;
  const z = zoneFor(e);
  if (!z) return;
  e.preventDefault(); e.stopPropagation();
  e.dataTransfer.dropEffect = 'copy';
  mark(z);
  status(z, t('Loslassen zum Hochladen'));
}, true);
d.addEventListener('dragleave', e => { if (over && !blockEl(over).contains(e.relatedTarget)) { status(over, ''); mark(null); } }, true);
d.addEventListener('drop', e => {
  if (!hasFiles(e)) return;
  const z = zoneFor(e);
  if (!z) return;
  e.preventDefault(); e.stopPropagation();
  mark(null);
  const { ok, bad } = sortFiles([...(e.dataTransfer.files || [])], z.dataset.accept);
  const msg = bad.length ? rejectMsg(bad, z.dataset.accept) : '';
  status(z, msg);
  if (msg) toast(msg, 'error');
  if (ok.length) upload(z, ok);
}, true);

// Sortieren per Ziehen innerhalb einer Fotostrecke
let drag = null;
d.addEventListener('dragstart', e => {
  const li = e.target.closest?.('.pg__item[draggable=true]');
  if (!li) return;
  e.stopPropagation();
  drag = li;
  li.classList.add('is-dragging');
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/x-foto-item', li.querySelector('[data-foto-item]').dataset.fotoItem);
}, true);
d.addEventListener('dragover', e => {
  if (!drag) return;
  const li = e.target.closest?.('.pg__item[draggable=true]');
  if (!li || li.parentElement !== drag.parentElement) return;
  e.preventDefault(); e.stopPropagation();
  const r = li.getBoundingClientRect();
  const after = e.clientX > r.left + r.width / 2;
  drag.parentElement.querySelectorAll('.is-drop-before,.is-drop-after').forEach(x => x.classList.remove('is-drop-before', 'is-drop-after'));
  if (li !== drag) li.classList.add(after ? 'is-drop-after' : 'is-drop-before');
}, true);
d.addEventListener('drop', async e => {
  if (!drag) return;
  const li = e.target.closest?.('.pg__item[draggable=true]');
  const src = drag;
  if (!li || li === src || li.parentElement !== src.parentElement) return;
  e.preventDefault(); e.stopPropagation();
  const after = li.classList.contains('is-drop-after');
  const tools = src.querySelector('[data-foto-item]');
  const blk = api(tools);
  if (!blk) { toast(t('Diese Version des Editors unterstützt das noch nicht – bitte die Seitenleiste nutzen.'), 'info'); return; }
  const field = tools.dataset.field, from = +tools.dataset.fotoItem, to0 = +li.querySelector('[data-foto-item]').dataset.fotoItem;
  const list = blk.get(field) || [];
  const [moved] = list.splice(from, 1);
  let to = to0 > from ? to0 - 1 : to0;
  if (after) to += 1;
  list.splice(to, 0, moved);
  await blk.set({ [field]: list });
  toast(t('Reihenfolge geändert – noch nicht gespeichert.'));
}, true);
d.addEventListener('dragend', () => {
  if (!drag) return;
  drag.parentElement?.querySelectorAll('.is-drop-before,.is-drop-after,.is-dragging').forEach(x => x.classList.remove('is-drop-before', 'is-drop-after', 'is-dragging'));
  drag = null;
}, true);
}
