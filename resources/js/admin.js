/*
 * Admin-Oberfläche (Core, Theme-unabhängig)
 * Reiter · Repeater · Rich-Text · Medienauswahl · Bestätigungen · Link-Vorschläge
 * Wird auch vom Inline-Editor genutzt (window.CMSAdmin).
 */
import { initBar, layer, layerBox, listen, pathTarget, ui, uiAll, openDialog, deepActive, shadowFor, addRoot, setUiCss, pathClosest, inPath, IN_ADMIN } from './_shadow.js';
import './_media.js';
import { openSpotlight } from './_spotlight.js';
import { t } from './_i18n.js';
import { initGeo } from './_geo.js';
import { initRRule } from './_rrule.js';
import { conditions } from './_conditions.js';
import { initIban } from './_iban.js';
import { initRuleBuilder } from './_schema_rules.js';
import { initRepeaterCollapse, initSettingsPreview } from './_settings.js';
import { initDesign } from './_design.js';
import { drill } from './_drill.js';
import { initDrawer } from './_drawer.js';
import { initFavorites } from './_favorites.js';
import { initSupport } from './_support.js';
import { initNetwork } from './_network.js';
import { initReview } from './_review.js';   // Prüf-Ebene „Eingereicht“ (Core\Review)
import { initRedirects } from './_redirects.js';   // Administration → Weiterleitungen (Core\Redirects)
import { initEntryEdit } from './_entry_edit.js';
import { initToolbar, bar_ } from './_bar.js';   // Redaktions-Werkzeugleiste: Menüs, Status, Modus, Abbrechen
import { initAi } from './_ai.js';   // KI-Assistent (Core\AI) – ohne Konfiguration #cms-ai wirkungslos
import { initIconPickers, initIconGallery } from './_iconpicker.js';
import { ico } from './_icons.js';   // Symbolauswahl (Feldtyp „icon“, Tabellensymbol)
import { initAccent } from './_accent.js';   // Konto → Akzentfarbe: Live-Vorschau (Core\Accent)
import { initFeatures } from './_features.js';   // Funktionen & Erweiterungen: Sicherheitsbestätigung (Core\Features)
import { initFonts } from './_fonts.js';   // Grundeinstellungen → Schriften: Proben von der eigenen Domain (Core\Fonts)
import { initBlockBuilder } from './_blockbuilder.js';   // Verwaltung → Blöcke: Block-Baukasten (Core\Blocks\Custom)
import { Rich } from './_rte.js';   // Formatierungsleiste: Stile, Farben, Marker, Link, Menüs, Tastatur
import { pickLink, openLinkPicker, initLinkFields } from './_links.js';   // Linkauswahl (Rich-Text und Feldtyp „link“)
import { initAssistant } from './_assistant.js';
import { initDelivery } from './_delivery.js';   // Eingang → Zustellung der Anfragen (Core\Data\Delivery)   // Assistent-Chat der Redaktion (Core\AI\Assistant) – lädt assistant.mjs erst beim Öffnen

const d = document;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const csrf = () => $('#adm-csrf')?.value || window.CMS_CSRF || '';

// ------------------------------------------------------------ Website: Werkzeugleiste im Shadow DOM (_shadow.js, Verhalten: _bar.js)
initBar();
initToolbar();

// ------------------------------------------------------------ Schmale Bildschirme: Seitenleiste als Schublade (_drawer.js)
initDrawer();

// ------------------------------------------------------------ Assistent-Chat (_assistant.js → assistant.mjs)
if (IN_ADMIN) initAssistant();

// ------------------------------------------------------------ Hilfe-Abschnitt der Seitenleiste: Zustand merken (nur dieser Browser)
{
  const box = $('[data-helpbox]');
  if (box) {
    try { if (!box.open && localStorage.getItem('adm.helpbox') === '1') box.open = true; } catch {}
    box.addEventListener('toggle', () => { try { localStorage.setItem('adm.helpbox', box.open ? '1' : '0'); } catch {} });
  }
}
// ------------------------------------------------------------ Bereichsnavigation (Daten): serverseitig in der Seitenleiste
{
  const box = $('.adm-side > .adm-drill');
  if (box) drill({ box, panel: $('[data-drill-panel]', box), home: $('#main'), title: box.dataset.drillTitle, back: box.dataset.drillBack, area: box.dataset.drillArea });
}

// ------------------------------------------------------------ Favoriten: Stern auf jeder Seite + Abschnitt in der Seitenleiste (_favorites.js)
initFavorites(csrf);

// ------------------------------------------------------------ Support & Wissensdatenbank: Melden, Vorschläge, Bildschirmfotos (_support.js)
initSupport(csrf);
// Netzwerk-Übersicht: Filter und Suche (_network.js)
initNetwork();
// Eingereicht: Sammelauswahl (_review.js)
initReview();
initRedirects();
// Konto → Akzentfarbe (_accent.js)
initAccent();
// Grundeinstellungen → Schriften (_fonts.js)
initFonts();
initFeatures();
initDelivery();

// ------------------------------------------------------------ Reiter (mit #hash)
$$('[data-tabs]').forEach(form => {
  const tabs = $$('[role=tab]', form), hidden = form.elements._tab;
  const select = (id, focus) => {
    tabs.forEach(t => {
      const on = t.dataset.tab === id;
      t.setAttribute('aria-selected', on); t.tabIndex = on ? 0 : -1;
      d.getElementById(t.getAttribute('aria-controls')).hidden = !on;
      if (on && focus) t.focus();
    });
    if (hidden) hidden.value = id;
    history.replaceState(null, '', '#' + id);
    d.dispatchEvent(new Event('adm:location'));   // Favoriten-Stern: Reiter gehört zur Adresse
  };
  tabs.forEach((t, i) => {
    t.addEventListener('click', () => select(t.dataset.tab));
    t.addEventListener('keydown', e => {
      const k = { ArrowRight: 1, ArrowLeft: -1 }[e.key];
      if (k) select(tabs[(i + k + tabs.length) % tabs.length].dataset.tab, true);
    });
  });
  const hash = location.hash.slice(1);
  if (hash && tabs.some(t => t.dataset.tab === hash)) select(hash);
  // Beim Absenden: ersten Reiter mit Fehler öffnen
  const errTab = tabs.find(t => $('.adm-dot', t));
  if (errTab) select(errTab.dataset.tab);
});

// ------------------------------------------------------------ Repeater
let uid = Date.now();
function initRepeaters(scope = d) {
  $$('.rep', scope).forEach(rep => {
    if (rep._init) return; rep._init = true;
    const items = $('.rep-items', rep), tpl = $(':scope > template', rep);
    rep.addEventListener('click', e => {
      const b = e.target.closest('[data-rep]');
      if (!b || b.closest('.rep') !== rep) return;
      const act = b.dataset.rep, item = b.closest('.rep-item');
      if (act === 'add') {
        const html = tpl.innerHTML.replaceAll('__i__', 'n' + (uid++));
        items.insertAdjacentHTML('beforeend', html);
        const added = items.lastElementChild;
        initRepeaters(added); initRte(added); initMedia(added); initIconPickers(added); initLinkFields(added);
        $('input,select,textarea,[contenteditable]', added)?.focus();
      } else if (act === 'remove') {
        bar_.ask({ title: t('Eintrag entfernen?'), ok: t('Entfernen') }).then(ok => { if (!ok) return; const nx = item.nextElementSibling || item.previousElementSibling; item.remove(); rep.dispatchEvent(new Event('input', { bubbles: true })); ($('[data-rep=remove]', nx || rep) || $('[data-rep=add]', rep))?.focus(); });
      } else if (act === 'up' && item.previousElementSibling) {
        item.parentNode.insertBefore(item, item.previousElementSibling); b.focus();
        rep.dispatchEvent(new Event('input', { bubbles: true }));
      } else if (act === 'down' && item.nextElementSibling) {
        item.parentNode.insertBefore(item.nextElementSibling, item); b.focus();
        rep.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
    // Titel des Eintrags live aktualisieren
    rep.addEventListener('input', e => {
      const item = e.target.closest('.rep-item');
      if (!item || item.closest('.rep') !== rep) return;
      const first = $('.rep-fields input:not([type=hidden]),.rep-fields select', item);
      if (first && e.target === first) $('.rep-title', item).textContent = first.tagName === 'SELECT' ? first.selectedOptions[0]?.text : first.value || '…';
    });
  });
}

// ------------------------------------------------------------ Rich-Text (Whitelist wird serverseitig erzwungen)
// Gemeinsame Logik aller Formatierungsleisten (Stile, Farben, Marker, Link, Menüs, Tastatur): _rte.js
// Genutzt vom Textfeld in Formularen/Seitenleisten UND von der schwebenden Leiste beim Inline-Bearbeiten (editor.js, _entry_edit.js).

function initRte(scope = d) {
  $$('.rte', scope).forEach(rte => {
    if (rte._init) return; rte._init = true;
    const area = $('.rte-area', rte), input = $('input[type=hidden]', rte), bar = $('.rte-bar', rte);
    const mode = rte.dataset.mode === 'inline' ? 'inline' : 'rich';
    bar.innerHTML = Rich.barHtml(mode);
    const sync = () => { input.value = area.innerHTML.trim() === '<br>' ? '' : area.innerHTML; input.dispatchEvent(new Event('input', { bubbles: true })); };
    area.addEventListener('input', sync);
    Rich.bindKeys(area, mode);
    Rich.mount(bar, () => area);
    Rich.state(area, bar);
    area.addEventListener('keyup', () => Rich.state(area, bar));
  });
}

let linksLoaded = false;
function loadLinks() {
  const dl = $('#cms-links', layerBox()) || $('#cms-links');
  if (!dl || !dl.dataset.endpoint || linksLoaded) return;
  linksLoaded = true;
  fetch(dl.dataset.endpoint, { headers: { Accept: 'application/json' } }).then(r => r.json()).then(list => {
    dl.innerHTML = list.map(l => `<option value="${esc(l.value)}">${esc(l.label)}</option>`).join('');
  }).catch(() => {});
}
d.addEventListener('focusin', e => { if (pathTarget(e)?.matches('[list=cms-links]')) loadLinks(); });

function esc(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); }

// ------------------------------------------------------------ Medienauswahl (Mediathek-Modul, _media.js)
const openMediaPicker = (kind = 'image') => window.CMSMedia.pick(kind);

function initMedia(scope = d) {
  $$('.media-field', scope).forEach(mf => {
    if (mf._init) return; mf._init = true;
    const input = $('input[type=hidden]', mf), prev = $('.media-field-preview', mf);
    window.CMSMedia?.lazyThumbs?.(prev);   // Video ohne Vorschaubild (Fields::mediaPreview): beim Sichtbarwerden erzeugen
    $('[data-media-pick]', mf).addEventListener('click', async () => {
      const m = await openMediaPicker(mf.dataset.accept);
      if (!m) return;
      input.value = m.id;
      // Videos: Poster/Vorschaubild, sonst (mit ffmpeg) Platzhalter, der das Vorschaubild nachlädt (Core\VideoThumbs)
      prev.innerHTML = m.thumb ? `<img src="${esc(m.thumb)}" alt="" width="120"><span>${esc(m.alt || m.display)}</span>`
        : m.kind === 'video' && m.thumb_gen ? window.CMSMedia.vthumbHtml(m, 'media-vthumb') + `<span>${esc(m.display)} · ${esc(m.size)}</span>`
        : `<span class="media-file">${esc(m.display)} · ${esc(m.size)}</span>`;
      window.CMSMedia.lazyThumbs(prev);
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
    $('[data-media-clear]', mf).addEventListener('click', () => {
      input.value = ''; prev.innerHTML = '<span class="media-empty">Keine Datei gewählt</span>';
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
  });
}

// ------------------------------------------------------------ Farbfelder: Farbwähler ↔ Hex-Eingabe
d.addEventListener('input', e => {
  const t = pathTarget(e);   // auch in Schatten-Wurzeln (Seitenleisten auf der Website)
  if (!t) return;
  if (t.matches('[data-color-none]')) { const txt = t.getRootNode().getElementById(t.dataset.colorNone), sw = t.closest('.f-color'); const pick = sw.querySelector('[data-color-for]'); txt.value = t.checked ? 'transparent' : pick.value.toUpperCase(); sw.classList.toggle('is-transparent', t.checked); txt.dispatchEvent(new Event('input', { bubbles: true })); }
  if (t.matches('[data-color-for]')) { const none = t.closest('.f-color').querySelector('[data-color-none]'); if (none) { none.checked = false; t.closest('.f-color').classList.remove('is-transparent'); } }
  if (t.matches('[data-color-for]')) { const txt = t.getRootNode().getElementById(t.dataset.colorFor); txt.value = t.value.toUpperCase(); txt.dispatchEvent(new Event('input', { bubbles: true })); }
  else if (t.matches('.f-color input[type=text]') && /^#[0-9a-f]{6}$/i.test(t.value)) t.previousElementSibling.value = t.value;
});

// ------------------------------------------------------------ App-Icon: Live-Vorschau + passende Felder zeigen
const aiBox = $('[data-icon-preview]');
if (aiBox) {
  const panel = aiBox.closest('[role=tabpanel]'), form = aiBox.closest('form');
  const field = n => { n = n.replace(/^sys_/, 'sys.'); return form.querySelector(`[name="f[${n}]"]:not([type=hidden])`) || form.querySelector(`[name="f[${n}]"]`); };
  const wrap = n => field(n)?.closest('.f');
  const toggle = () => {
    const img = field('sys_icon_mode').value === 'image';
    ['sys_icon_text', 'sys_icon_shape', 'sys_icon_fg', 'sys_icon_dot', 'sys_icon_dot_color'].forEach(n => { const w = wrap(n); if (w) w.hidden = img && n !== 'sys_icon_shape'; });
    const iw = wrap('sys_icon_image'); if (iw) iw.hidden = !img;
    const dot = form.querySelector('input[type=checkbox][name="f[sys.icon_dot]"]');
    const dc = wrap('sys_icon_dot_color'); if (dc && !img) dc.hidden = !dot?.checked;
  };
  let t, busy = 0;
  const refresh = async () => {
    const n = ++busy;
    const fd = new FormData(form);
    try {
      const r = await fetch(aiBox.dataset.iconPreview, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-CSRF-Token': csrf() } });
      const data = await r.json();
      if (n !== busy) return;
      $$('[data-ai]', aiBox).forEach(i => { i.src = data[i.dataset.ai]; });
      const name = field('sys_pwa_name')?.value || data.info.name, short = field('sys_pwa_short_name')?.value || data.info.short_name;
      $$('[data-ai-name]', aiBox).forEach(e => (e.textContent = name));
      $$('[data-ai-short]', aiBox).forEach(e => (e.textContent = short));
      $('[data-ai-splash]', aiBox).style.background = field('sys_pwa_bg')?.value || '#FFFFFF';
      $('[data-ai-bar]', aiBox).style.background = field('sys_pwa_theme')?.value || data.info.theme_color;
    } catch {}
  };
  panel.addEventListener('input', () => { toggle(); clearTimeout(t); t = setTimeout(refresh, 250); });
  panel.addEventListener('change', () => { toggle(); clearTimeout(t); t = setTimeout(refresh, 100); });
  toggle(); refresh();
}

// ------------------------------------------------------------ Bestätigungen, Kopieren, Zeichenzähler
listen('submit', e => {   // „submit“ verlässt keine Schatten-Wurzel: listen() hängt sich auch dort an
  // Gestaltete Rückfrage (_bar.js ask) statt window.confirm(); ohne JavaScript sendet das Formular direkt.
  // Bestätigen-Knopf trägt die Beschriftung des Auslösers („Löschen“, „Widerrufen“ …), sonst data-confirm-ok.
  const f = e.target.closest('[data-confirm]');
  const sub = e.submitter?.dataset.confirm;
  const msg = sub || f?.dataset.confirm;
  if (!msg || e.target.dataset.confirmed) return;
  e.preventDefault();
  const src = e.submitter || f;
  const label = (src?.dataset.confirmOk || f?.dataset.confirmOk || e.submitter?.getAttribute('aria-label') || e.submitter?.textContent || '').replace(/[…\s]+$/, '').replace(/\s+/g, ' ').trim();
  const form = e.target, by = e.submitter;
  bar_.ask({ title: msg, ok: label.length && label.length < 40 ? label : undefined }).then(ok => {
    if (!ok) return;
    form.dataset.confirmed = '1';
    try { form.requestSubmit(by && by.form === form ? by : undefined); } catch { form.submit(); }
    delete form.dataset.confirmed;
  });
});
d.addEventListener('click', e => {
  const c = e.target.closest('[data-copy]');
  if (c) navigator.clipboard.writeText($(c.dataset.copy).textContent.trim()).then(() => { c.textContent = 'Kopiert ✓'; });
  // Druckansicht eines einzelnen Eintrags (Anfragen): nur im Browser, keine Datei auf dem Server
  const p = e.target.closest('[data-print]');
  const target = p && $(p.dataset.print);
  if (target) {
    const done = () => { d.body.classList.remove('is-print-one'); target.classList.remove('is-print'); removeEventListener('afterprint', done); };
    d.body.classList.add('is-print-one'); target.classList.add('is-print');
    addEventListener('afterprint', done);
    print();
  }
});
function initCounters(scope = d) {
  $$('[data-max]', scope).forEach(el => {
    if (el._init) return; el._init = true;
    const max = +el.dataset.max, out = d.createElement('span');
    out.className = 'f-count'; el.after(out);
    const upd = () => { const n = el.value.length; out.textContent = `${n}/${max}`; out.classList.toggle('is-over', n > max); };
    el.addEventListener('input', upd); upd();
  });
}

// ------------------------------------------------------------ Dokumentation: Inhaltsverzeichnis + Drucken
const toc = $('[data-doc-toc]');
if (toc && 'IntersectionObserver' in window) {
  const links = $$('a[href^="#"]', toc);
  const io = new IntersectionObserver(es => es.forEach(en => {
    if (!en.isIntersecting) return;
    links.forEach(a => a.classList.toggle('is-active', a.getAttribute('href') === '#' + en.target.id));
  }), { rootMargin: '0px 0px -70% 0px' });
  links.forEach(a => { const s = d.getElementById(a.getAttribute('href').slice(1)); if (s) io.observe(s); });
}
d.addEventListener('click', e => { if (e.target.closest('[data-print]')) print(); });

// ------------------------------------------------------------ Datenblöcke: Feldauswahl passend zur gewählten Tabelle
function initDataFields(scope = d) {
  $$('[data-datatable]', scope).forEach(sel => {
    if (sel._init) return; sel._init = true;
    const form = sel.closest('form, .cms-drawer, .adm-fields') || scope;
    const apply = () => {
      const t = sel.value;
      $$('[data-datafields] [data-table]', form).forEach(l => { l.hidden = l.dataset.table !== t; if (l.hidden) $('input', l).checked = false; });
      $$('[data-datafield]', form).forEach(s => {
        $$('optgroup', s).forEach(g => { g.hidden = g.dataset.table !== t; g.disabled = g.hidden; });
        if (s.selectedOptions[0]?.parentElement?.disabled) s.value = '';
      });
    };
    sel.addEventListener('change', () => { apply(); sel.dispatchEvent(new Event('input', { bubbles: true })); });
    apply();
  });
}

// ------------------------------------------------------------ Daten: Tabellen-Baukasten
const schema = $('[data-schema]');
if (schema) {
  const list = $('[data-fields]', schema), tpl = $('[data-field-template]', schema);
  const slug = v => v.toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').replace(/^(\d)/, 'f_$1').slice(0, 40);
  const renumber = () => $$('[data-field]', list).forEach((li, i) => $$('[name^="fields["]', li).forEach(inp => { inp.name = inp.name.replace(/^fields\[[^\]]*\]/, `fields[${i}]`); }));
  const sync = li => {
    const type = $('[data-type]', li).value;
    li.dataset.type = type;
    $$('[data-show-for]', li).forEach(x => { x.hidden = !x.dataset.showFor.split(' ').includes(type); });
    const opt = $('[data-type] option:checked', li);
    const icons = JSON.parse(schema.dataset.icons || '{}');
    $('[data-type-icon]', li).innerHTML = icons[type] || '';   // Symbol (SVG aus dem Sprite) des Feldtyps
  };
  schema.dataset.icons = JSON.stringify(Object.fromEntries($$('[data-add-field]', schema).map(b => [b.dataset.addField, (b.querySelector('.ico')?.outerHTML || '')])));
  $$('[data-field]', list).forEach(sync);
  schema.addEventListener('input', e => {
    const li = e.target.closest('[data-field]'); if (!li) return;
    if (e.target.matches('[data-label]')) { const n = $('[data-name]', li); if (!n.dataset.locked && !n.dataset.touched) n.value = slug(e.target.value); }
    if (e.target.matches('[data-name]')) e.target.dataset.touched = '1';
  });
  schema.addEventListener('change', e => {
    const li = e.target.closest('[data-field]');
    if (li && e.target.matches('[data-type]')) { sync(li); if (li.dataset.type === 'group' && !$('[data-sub]', li)) addSub(li); }
    if (e.target.matches('[data-sub-type]')) $('[data-sub-opts]', e.target.closest('[data-sub]')).hidden = e.target.value !== 'select';
  });
  // Wiederholbare Gruppe: Unterfelder hinzufügen, verschieben, entfernen (Kurzname folgt der Bezeichnung, bis er geändert wird)
  let subUid = 0;
  const addSub = li => {
    const list2 = $('[data-subs]', li);
    list2.insertAdjacentHTML('beforeend', $('[data-sub-template]', li).innerHTML.replaceAll('__s__', 'n' + subUid++));
    $('[data-sub-label]', list2.lastElementChild).focus();
  };
  schema.addEventListener('input', e => {
    const sub = e.target.closest('[data-sub]');
    if (sub && e.target.matches('[data-sub-label]')) { const nm = $('[data-sub-name]', sub); if (!nm.dataset.touched) nm.value = slug(e.target.value); }
    if (sub && e.target.matches('[data-sub-name]')) e.target.dataset.touched = '1';
  });
  schema.addEventListener('click', e => {
    const sb = e.target.closest('[data-sub-add],[data-sub-move],[data-sub-remove]');
    if (sb) {
      const sub = sb.closest('[data-sub]');
      if (sb.matches('[data-sub-add]')) addSub(sb.closest('[data-field]'));
      else if (sb.matches('[data-sub-remove]')) { const nx = sub.nextElementSibling || sub.previousElementSibling, fl = sub.closest('[data-field]'); sub.remove(); (nx ? $('[data-sub-label]', nx) : $('[data-sub-add]', fl)).focus(); }
      else { const to = +sb.dataset.subMove < 0 ? sub.previousElementSibling : sub.nextElementSibling?.nextElementSibling; if (+sb.dataset.subMove < 0 ? to : sub.nextElementSibling) sub.parentNode.insertBefore(sub, to || null); sb.focus(); }
      return;
    }
    const add = e.target.closest('[data-add-field]');
    if (add) {
      const html = tpl.innerHTML.replaceAll('__i__', String($$('[data-field]', list).length));
      list.insertAdjacentHTML('beforeend', html);
      const li = list.lastElementChild;
      $('[data-type]', li).value = add.dataset.addField; sync(li);
      if (add.dataset.addField === 'group') addSub(li);
      $('[data-label]', li).value = add.textContent.trim().replace(/^\S+\s/, '');
      $('[data-name]', li).value = slug($('[data-label]', li).value);
      $('[data-label]', li).focus(); $('[data-label]', li).select();
      renumber(); return;
    }
    const mv = e.target.closest('[data-move]');
    if (mv) {
      const li = mv.closest('[data-field]');
      const to = +mv.dataset.move < 0 ? li.previousElementSibling : li.nextElementSibling?.nextElementSibling;
      if (+mv.dataset.move < 0 && to) list.insertBefore(li, to); else if (+mv.dataset.move > 0) list.insertBefore(li, to || null);
      renumber(); mv.focus(); return;
    }
    const rm = e.target.closest('[data-remove-field]');
    if (rm) { const li = rm.closest('[data-field]'); bar_.ask({ title: 'Feld „' + ($('[data-label]', li).value || 'ohne Namen') + '“ entfernen? Beim Speichern werden seine Inhalte gelöscht.', ok: t('Entfernen') }).then(ok => { if (ok) { li.remove(); renumber(); } }); }
  });
  // Bedingungen je Feld (anzeigen wenn · Pflicht wenn · Vergleich)
  initRuleBuilder(schema, list, slug);
}

// Daten: Eintrag bearbeiten – Felder abhängig von anderen Feldern ein-/ausblenden (Core\Data\Rules)
$$('form[data-conditions]').forEach(f => conditions(f, JSON.parse(f.dataset.conditions || '{}'), 'f'));

// ------------------------------------------------------------ Daten: Einträge (Sammelaktionen, Reihenfolge ziehen)
const entries = $('[data-entries]');
if (entries) {
  const endpoint = entries.dataset.entries, bulk = $('[data-bulk]', entries);
  const checked = () => $$('[data-check]:checked', entries).map(c => +c.value);
  const upd = () => { const n = checked().length; bulk.hidden = !n; $('[data-selcount]', entries).textContent = `${n} ausgewählt`; };
  const post = body => fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify(body) }).then(r => r.json());
  entries.addEventListener('change', e => {
    if (e.target.matches('[data-checkall]')) $$('[data-check]', entries).forEach(c => { c.checked = e.target.checked; });
    upd();
  });
  entries.addEventListener('click', async e => {
    const b = e.target.closest('[data-bulk-action]'); if (!b) return;
    const ids = checked();
    if (b.dataset.bulkAction === 'delete' && !(await bar_.ask({ title: `${ids.length} Einträge endgültig löschen?`, ok: t('Löschen') }))) return;
    await post({ action: b.dataset.bulkAction, ids }); location.reload();
  });
  // Zeile anklicken = bearbeiten (außer auf Links/Checkboxen)
  entries.addEventListener('dblclick', e => { const tr = e.target.closest('tr[data-id]'); if (tr && !e.target.closest('a,input')) $('.dt-c-title a', tr).click(); });
  const tbody = $('.is-sortable [data-rows]', entries);
  if (tbody) {
    let drag = null;
    tbody.addEventListener('dragstart', e => { drag = e.target.closest('tr'); drag.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; });
    tbody.addEventListener('dragover', e => {
      e.preventDefault();
      const tr = e.target.closest('tr'); if (!tr || tr === drag) return;
      const r = tr.getBoundingClientRect();
      tbody.insertBefore(drag, e.clientY > r.top + r.height / 2 ? tr.nextSibling : tr);
    });
    tbody.addEventListener('dragend', async () => {
      drag.classList.remove('is-drag'); drag = null;
      await post({ action: 'reorder', ids: $$('tr[data-id]', tbody).map(t => +t.dataset.id) });
    });
  }
}

// ------------------------------------------------------------ Seitenbaum (Finder-Listenansicht)
const pt = $('[data-pagetree]');
if (pt) {
  const tree = $('.pt-tree', pt), base = pt.dataset.base, msg = $('[data-pt-msg]', pt);
  const KEY = 'mycms-pt-collapsed';
  const collapsed = new Set((() => { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch { return []; } })());
  const save = () => { try { localStorage.setItem(KEY, JSON.stringify([...collapsed])); } catch {} };
  const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify(body || {}) }).then(r => r.json());
  const note = t => { msg.textContent = t; clearTimeout(msg._t); msg._t = setTimeout(() => (msg.textContent = ''), 3000); };
  const nodes = () => $$('.pt-node', tree).filter(n => n.offsetParent !== null);
  const setExp = (n, open) => { if (!n.hasAttribute('aria-expanded')) return; n.setAttribute('aria-expanded', open); open ? collapsed.delete(n.dataset.id) : collapsed.add(n.dataset.id); save(); };
  $$('.pt-node[aria-expanded]', tree).forEach(n => { if (collapsed.has(n.dataset.id)) n.setAttribute('aria-expanded', 'false'); });
  let active = null;
  const select = n => {
    if (!n) return;
    $$('.pt-node[aria-selected=true]', tree).forEach(x => x.setAttribute('aria-selected', 'false'));
    n.setAttribute('aria-selected', 'true'); active = n;
    tree.setAttribute('aria-activedescendant', n.id);
    $('.pt-row', n).scrollIntoView({ block: 'nearest' });
  };
  const open = n => { location.href = n.dataset.url + '?edit=1'; };

  tree.addEventListener('click', e => {
    const n = e.target.closest('.pt-node'); if (!n) return;
    if (e.target.closest('[data-toggle]')) { setExp(n, n.getAttribute('aria-expanded') !== 'true'); return; }
    if (e.target.closest('[data-menu]')) return;
    if (e.target.closest('[data-more]')) { select(n); const r = e.target.getBoundingClientRect(); menu(n, r.left - 160, r.bottom + 4); return; }
    if (e.target.closest('.pt-title')) { e.preventDefault(); }
    select(n); tree.focus();
  });
  tree.addEventListener('dblclick', e => { const n = e.target.closest('.pt-node'); if (n && !e.target.closest('[data-menu],[data-toggle],[data-more]')) open(n); });
  tree.addEventListener('contextmenu', e => { const n = e.target.closest('.pt-node'); if (!n) return; e.preventDefault(); select(n); menu(n, e.clientX, e.clientY); });
  tree.addEventListener('keydown', e => {
    const list = nodes(), i = list.indexOf(active);
    if (e.key === 'ArrowDown') { e.preventDefault(); select(list[Math.min(list.length - 1, i + 1)] || list[0]); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); select(list[Math.max(0, i - 1)] || list[0]); }
    else if (e.key === 'ArrowRight' && active) { e.preventDefault(); if (active.getAttribute('aria-expanded') === 'false') setExp(active, true); else if (active.hasAttribute('aria-expanded')) select($('.pt-node', active)); }
    else if (e.key === 'ArrowLeft' && active) { e.preventDefault(); if (active.getAttribute('aria-expanded') === 'true') setExp(active, false); else select(active.parentElement.closest('.pt-node')); }
    else if (e.key === 'Enter' && active) { e.preventDefault(); open(active); }
    else if ((e.key === 'ContextMenu' || (e.shiftKey && e.key === 'F10')) && active) { e.preventDefault(); const r = $('.pt-row', active).getBoundingClientRect(); menu(active, r.left + 40, r.bottom); }
  });
  tree.addEventListener('focus', () => { if (!active) select(nodes()[0]); });

  // Im Menü an/aus
  tree.addEventListener('change', async e => {
    const c = e.target.closest('[data-menu]'); if (!c) return;
    const n = c.closest('.pt-node');
    await post(`${base}/${n.dataset.id}/quick`, { menu: c.checked ? 1 : 0 });
    note(`„${n.dataset.title}“ ${c.checked ? 'erscheint jetzt im Menü' : 'ist nicht mehr im Menü'}`);
  });

  // Filter
  $('[data-filter]', pt).addEventListener('input', e => {
    const q = e.target.value.trim().toLowerCase();
    $$('.pt-node', tree).forEach(n => { n.classList.toggle('pt-hit', q && n.dataset.title.toLowerCase().includes(q)); });
    tree.classList.toggle('is-filtering', !!q);
    if (q) $$('.pt-node.pt-hit', tree).forEach(n => { let p = n.parentElement.closest('.pt-node'); while (p) { p.classList.add('pt-hit-parent'); p.setAttribute('aria-expanded', 'true'); p = p.parentElement.closest('.pt-node'); } });
    else $$('.pt-hit-parent', tree).forEach(n => n.classList.remove('pt-hit-parent'));
  });
  $('[data-expand-all]', pt).onclick = () => $$('.pt-node[aria-expanded]', tree).forEach(n => setExp(n, true));
  $('[data-collapse-all]', pt).onclick = () => $$('.pt-node[aria-expanded]', tree).forEach(n => setExp(n, false));

  // Ziehen: Mitte = Unterseite, oberes/unteres Viertel = davor/dahinter
  let drag = null, drop = null;
  const clear = () => $$('.pt-drop-in,.pt-drop-before,.pt-drop-after', tree).forEach(x => x.classList.remove('pt-drop-in', 'pt-drop-before', 'pt-drop-after'));
  tree.addEventListener('dragstart', e => {
    const n = e.target.closest('.pt-node'); if (!n || n.dataset.home === '1') return e.preventDefault();
    drag = n; n.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', n.dataset.id);
  });
  tree.addEventListener('dragover', e => {
    if (!drag) return;
    const row = e.target.closest('.pt-row'); if (!row) return;
    const n = row.parentElement;
    if (n === drag || drag.contains(n)) { clear(); drop = null; return; }
    e.preventDefault();
    const r = row.getBoundingClientRect(), y = (e.clientY - r.top) / r.height;
    const pos = n.dataset.home === '1' ? 'after' : y < .28 ? 'before' : y > .72 ? 'after' : 'in';
    clear(); row.classList.add('pt-drop-' + pos); drop = { n, pos };
  });
  tree.addEventListener('dragleave', e => { if (!tree.contains(e.relatedTarget)) clear(); });
  tree.addEventListener('drop', async e => {
    e.preventDefault(); clear();
    if (!drag || !drop) return;
    const { n, pos } = drop;
    let parent, index;
    if (pos === 'in') {
      parent = +n.dataset.id; index = $$(':scope > ul > .pt-node', n).length;
    } else {
      parent = +n.dataset.parent || null;
      const sibs = $$(':scope > .pt-node', n.parentElement).filter(x => x !== drag);
      index = sibs.indexOf(n) + (pos === 'after' ? 1 : 0);
    }
    const res = await post(`${base}/${drag.dataset.id}/move`, { parent_id: parent, index });
    if (!res.ok) { alert(res.error || 'Verschieben nicht möglich.'); return; }
    location.reload();
  });
  tree.addEventListener('dragend', () => { drag?.classList.remove('is-drag'); drag = null; clear(); });

  // Kontextmenü
  let mEl = null;
  const closeMenu = () => { mEl?.remove(); mEl = null; };
  const menu = (n, x, y) => {
    closeMenu();
    const home = n.dataset.home === '1', id = n.dataset.id;
    const items = [
      ['Inhalte bearbeiten', () => open(n)],
      ['Seiteneinstellungen …', () => { location.href = `${base}/${id}`; }],
      ['Ansehen ↗', () => window.open(n.dataset.url, '_blank', 'noopener')],
      ['-'],
      ['Neue Unterseite …', () => { location.href = `${base}/new?parent=${id}&lang=${pt.dataset.lang}`; }],
      ...Object.entries(JSON.parse(pt.dataset.languages || '{}')).filter(([code]) => code !== pt.dataset.lang).map(([code, label]) =>
        [(n.dataset.langs || '').split(',').includes(code) ? `${label}: Übersetzung öffnen` : `Übersetzung anlegen: ${label}`, async () => {
          const res = await post(`${base}/${id}/translate`, { lang: code });
          if (res.ok) location.href = res.url; else alert(res.error);
        }]),
      ...(home ? [] : [['Duplizieren', async () => { await post(`${base}/${id}/duplicate`); location.reload(); }]]),
      ...(n.dataset.dirty === '1' ? [['Änderungen veröffentlichen', async () => { await post(`${base}/${id}/publish`); location.reload(); }]] : []),
      // Erweiterungen (Extension::pageList): [Beschriftung, Adresse]
      ...(n.dataset.extActions ? [['-'], ...JSON.parse(n.dataset.extActions).map(([l, href]) => [l, () => { location.href = href; }])] : []),
      ...(home ? [] : [['-'], ['Löschen …', async () => {
        const kids = $$('.pt-node', n).length;
        if (!(await bar_.ask({ title: `„${n.dataset.title}“ löschen?${kids ? `\n\n${kids} Unterseite(n) rücken eine Ebene nach oben.` : ''}`, ok: t('Löschen') }))) return;
        await post(`${base}/${id}/delete`); location.reload();
      }, true]]),
    ];
    mEl = d.createElement('div'); mEl.className = 'fx-menu'; mEl.setAttribute('role', 'menu');
    mEl.innerHTML = items.map(([l, , danger], i) => l === '-' ? '<hr>' : `<button type="button" role="menuitem" data-i="${i}"${danger ? ' class="is-danger"' : ''}>${esc(l)}</button>`).join('');
    d.body.append(mEl);
    const r = mEl.getBoundingClientRect();
    mEl.style.left = Math.max(8, Math.min(x, innerWidth - r.width - 8)) + 'px'; mEl.style.top = Math.min(y, innerHeight - r.height - 8) + 'px';
    mEl.addEventListener('click', e => { const b = e.target.closest('[data-i]'); if (b) { closeMenu(); items[+b.dataset.i][1](); } });
    mEl.addEventListener('keydown', e => {
      const bs = $$('button', mEl), i = bs.indexOf(d.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); bs[(i + 1) % bs.length].focus(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); bs[(i - 1 + bs.length) % bs.length].focus(); }
      if (e.key === 'Escape') { closeMenu(); tree.focus(); }
    });
    $('button', mEl).focus();
    setTimeout(() => d.addEventListener('pointerdown', ev => { if (mEl && !mEl.contains(ev.target)) closeMenu(); }, { once: true }));
  };
}

// ------------------------------------------------------------ Verknüpfungsfelder: suchen und neue Einträge direkt anlegen
function initRelations(scope = d) {
  const create = async (url, singular) => {
    const title = prompt(`Neu anlegen (${singular}):`);
    if (!title?.trim()) return null;
    const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify({ title: title.trim() }) });
    const res = await r.json();
    if (!res.ok) { alert(res.error || 'Anlegen nicht möglich.'); return null; }
    return res;
  };
  $$('fieldset[data-relation]', scope).forEach(fs => {
    if (fs._init) return; fs._init = true;
    const box = $('.f-multi', fs);
    const tools = d.createElement('div'); tools.className = 'f-reltools';
    tools.innerHTML = `<input type="search" placeholder="Filtern …" aria-label="Auswahl filtern"><button type="button" class="adm-btn adm-btn--small adm-btn--ghost">+ ${esc(fs.dataset.singular)} neu</button>`;
    fs.insertBefore(tools, box);
    const labels = () => $$('.f-check', box);
    $('input', tools).addEventListener('input', e => { const q = e.target.value.toLowerCase(); labels().forEach(l => { l.hidden = q && !l.textContent.toLowerCase().includes(q) && !$('input', l).checked; }); });
    $('button', tools).addEventListener('click', async () => {
      const res = await create(fs.dataset.relation, fs.dataset.singular); if (!res) return;
      let cb = $(`input[value="${res.id}"]`, box);
      if (!cb) { box.insertAdjacentHTML('beforeend', `<label class="f-check"><input type="checkbox" name="${esc(fs.dataset.name)}" value="${res.id}"> <span>${esc(res.title)}</span></label>`); cb = $(`input[value="${res.id}"]`, box); }
      cb.checked = true; cb.dispatchEvent(new Event('change', { bubbles: true }));
    });
    if (labels().length < 8) $('input', tools).hidden = true;
  });
  $$('.f-relselect[data-relation]', scope).forEach(w => {
    if (w._init) return; w._init = true;
    const sel = $('select', w);
    w.insertAdjacentHTML('beforeend', `<button type="button" class="adm-btn adm-btn--small adm-btn--ghost">+ ${esc(w.dataset.singular)} neu</button>`);
    $('button', w).addEventListener('click', async () => {
      const res = await create(w.dataset.relation, w.dataset.singular); if (!res) return;
      if (!$(`option[value="${res.id}"]`, sel)) sel.insertAdjacentHTML('beforeend', `<option value="${res.id}">${esc(res.title)}</option>`);
      sel.value = String(res.id); sel.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });
}

// ------------------------------------------------------------ Feldbindung (Detailseiten-Vorlagen)
function initBinding(scope = d) {
  $$('[data-bindwrap]', scope).forEach(w => {
    if (w._init) return; w._init = true;
    const btn = $('[data-bind-toggle]', w), sel = $('[data-bind-select]', w);
    const sync = () => { const on = !!sel.value; w.classList.toggle('is-bound', on); btn.setAttribute('aria-pressed', on); };
    btn.addEventListener('click', () => {
      if (w.classList.contains('is-bound') || w.classList.contains('is-choosing')) {
        w.classList.remove('is-choosing');
        if (sel.value) { sel.value = ''; sel.dispatchEvent(new Event('change', { bubbles: true })); }
        sync(); return;
      }
      w.classList.add('is-choosing');
      const first = [...sel.options].find(o => o.value);
      if (first && sel.options.length === 2) { sel.value = first.value; sel.dispatchEvent(new Event('change', { bubbles: true })); }
      sync(); sel.focus();
    });
    sel.addEventListener('change', () => { if (!sel.value) w.classList.remove('is-choosing'); sync(); });
  });
}

listen('change', e => {
  if (e.target.matches('[data-entry-switch]')) location.href = e.target.value;
  if (e.target.matches('[data-autosubmit]')) e.target.form.submit();
  // Tabellen-Baukasten: Feldzuordnung des Kalenders nur bei „Als Kalender nutzen“ (siehe unten: Klick auf Kalendertag)
  if (e.target.matches('[data-cal-toggle]')) { const m = e.target.closest('[data-cal-settings]')?.querySelector('[data-cal-map]'); if (m) m.hidden = !e.target.checked; }
});

// Einträge als Kalender: Klick auf die freie Fläche eines Tages → „Neuer Termin“ an diesem Tag (Tastatur: „+“-Link im Tag)
d.addEventListener('click', e => {
  const day = e.target.closest('[data-cal-day]');
  if (day && !e.target.closest('a,button')) day.querySelector('[data-cal-add]')?.click();
});

function init(scope = d) { initIban(scope); initGeo(scope); initRRule(scope); initBinding(scope); initRelations(scope); initRepeaters(scope); initRepeaterCollapse(scope); initRte(scope); initLinkFields(scope); initMedia(scope); initCounters(scope); initDataFields(scope); initIconPickers(scope); initIconGallery(scope); initAi(scope); /* KI-Assistent */ }
init();
initSettingsPreview();
initDesign();
initBlockBuilder();
window.CMSAdmin = { init, openMediaPicker, pickLink, openLinkPicker, esc, Rich, openSpotlight, t, ico, bar: bar_,
  // Shadow-DOM-Helfer für editor.js (eigenes Bündel) – eine gemeinsame Ebene
  shadow: { layer, layerBox, ui, uiAll, openDialog, deepActive, shadowFor, addRoot, setUiCss, pathTarget, pathClosest, inPath, listen, IN_ADMIN } };
// Einträge auf der Website bearbeiten (Stift in Datenlisten, Seitenleiste, Felder direkt im Text – _entry_edit.js)
initEntryEdit();
