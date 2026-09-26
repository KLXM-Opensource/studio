/*
 * Symbolauswahl (Feldtyp „icon“, Symbol einer Datentabelle): durchsuchbares Raster nach Themen.
 *
 * Server-Markup (Core\Icons::picker): <span class="icp" data-icon-picker [data-suggest="id-des-namensfelds"] [data-optional]>
 *   <input class="icp__input" id="…" name="…" value="calendar"> <span class="icp__preview">…</span></span>
 * Ohne JavaScript bleibt das Textfeld (Symbolname). Mit JavaScript: Knopf mit Vorschau → Auswahlfenster.
 *
 * Tastatur: Knopf öffnet (Enter/Leertaste/↓) · Suchfeld: tippen filtert (deutsch + englisch), ↓ ins Raster ·
 * Raster: ←/→ vorheriges/nächstes, ↑/↓ Zeile, Pos1/Ende, Enter/Leertaste übernehmen, Buchstaben → Suche · Esc schließt.
 *
 * Themen: nur die aktivierten Symbolbereiche (data-icons-topics, Grundeinstellungen); „Weitere Bereiche anzeigen“ blendet
 * die übrigen ein. Die Symbole eines Themas werden erst eingesetzt, wenn es ins Bild scrollt (lädt dessen kleines Sprite).
 */
import { t } from './_i18n.js';
import { ico, icoOrGlyph, icoLazy, lazyIcons, enabledTopics, loadCatalog, NAME } from './_icons.js';

const d = document;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const norm = s => String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ß/g, 'ss');
const EN = () => (d.documentElement.lang || '').toLowerCase().startsWith('en');
const RECENT = 'cms.icons.recent';
let seq = 0, open = null;

function recent() { try { return JSON.parse(localStorage.getItem(RECENT) || '[]').filter(n => NAME.test(n)).slice(0, 12); } catch { return []; } }
function remember(n) { try { localStorage.setItem(RECENT, JSON.stringify([n, ...recent().filter(x => x !== n)].slice(0, 12))); } catch { /* privat */ } }

/** Bezeichnung in der Sprache der Oberfläche */
const label = (cat, n) => (cat.icons[n] ? cat.icons[n][EN() ? 1 : 0] : n);

/** Treffer für eine Suche (alle Wörter müssen passen), sortiert nach Güte */
function search(cat, q) {
  const words = norm(q).split(/[^a-z0-9]+/).filter(Boolean);
  if (!words.length) return [];
  const out = [];
  for (const [n, [de, en, kw, tags = '']] of Object.entries(cat.icons)) {
    // Bezeichnung (Sprache der Oberfläche) vor Suchbegriffen vor Bezeichnung der anderen Sprache vor Tags (Phosphor)
    const [l1, l2] = EN() ? [en, de] : [de, en];
    const lab = norm(l1), lab2 = ' ' + norm(l2), key = ' ' + norm(kw) + ' ', tag = ' ' + norm(tags) + ' ';
    let s = 0;
    for (const w of words) {
      if (lab.startsWith(w)) s += 8;
      else if ((' ' + lab).includes(' ' + w)) s += 6;
      else if (key.includes(' ' + w)) s += 4;
      else if (lab2.includes(' ' + w)) s += 3;
      else if (tag.includes(' ' + w)) s += 1.5;
      else if (lab.includes(w) || key.includes(w) || lab2.includes(w)) s += 1;
      else if (tag.includes(w)) s += .5;
      else { s = 0; break; }
    }
    if (s) out.push([s, n]);
  }
  return out.sort((a, b) => b[0] - a[0] || a[1].localeCompare(b[1])).map(x => x[1]);
}

/** Vorschläge passend zum Namen (z. B. „Termine“ → Kalender, Uhr …) */
function suggest(cat, text) {
  const words = norm(text).split(/[^a-z0-9]+/).filter(w => w.length >= 3);
  const score = {};
  for (const [n, [de, en, kw]] of Object.entries(cat.icons)) {
    const hay = ' ' + norm(`${de} ${en} ${kw}`) + ' ';   // ohne englische Tags: Vorschläge bleiben treffsicher
    const toks = hay.split(' ').filter(k => k.length >= 5);
    for (const w of words) {
      const stem = w.length > 5 ? w.slice(0, -2) : w;
      if (hay.includes(' ' + w)) score[n] = (score[n] || 0) + 3;
      else if (hay.includes(' ' + stem)) score[n] = (score[n] || 0) + 2;
      else if (toks.some(k => w.includes(k))) score[n] = (score[n] || 0) + 2;   // Komposita: „Trainingstermine“ → termin, training
    }
  }
  return Object.entries(score).sort((a, b) => b[1] - a[1]).slice(0, 12).map(x => x[0]);
}

export function initIconPickers(scope = d) {
  scope.querySelectorAll('[data-icon-picker]').forEach(enhance);
}

function enhance(box) {
  if (box._icp) return;
  const input = box.querySelector('.icp__input');
  if (!input) return;
  box._icp = true;
  const id = input.id || 'icp-' + (++seq);
  input.removeAttribute('id');
  input.type = 'hidden';
  box.querySelector('.icp__preview')?.remove();
  const btn = d.createElement('button');
  btn.type = 'button'; btn.className = 'icp__btn'; btn.id = id;
  btn.setAttribute('aria-haspopup', 'dialog'); btn.setAttribute('aria-expanded', 'false');
  box.prepend(btn);
  const paint = async () => {
    const v = input.value.trim();
    btn.innerHTML = `<span class="icp__cur">${v ? icoOrGlyph(v) : '<span class="icp__none"></span>'}</span><span class="icp__name">${esc(v || t('ohne Symbol'))}</span><span class="icp__chev" aria-hidden="true"></span>`;
    const cat = await loadCatalog();
    if (cat.icons[v]) btn.querySelector('.icp__name').textContent = label(cat, v);
    btn.setAttribute('aria-label', `${t('Symbol wählen')}: ${cat.icons[v] ? label(cat, v) : (v || t('ohne Symbol'))}`);
  };
  paint();
  btn.addEventListener('click', () => (box.classList.contains('is-open') ? close(box) : openPicker(box, input, btn, paint)));
  btn.addEventListener('keydown', e => { if (e.key === 'ArrowDown' && !box.classList.contains('is-open')) { e.preventDefault(); openPicker(box, input, btn, paint); } });
}

function close(box, focusBtn = true) {
  const pop = box.querySelector('.icp__pop');
  if (pop) pop.remove();
  box.classList.remove('is-open');
  const btn = box.querySelector('.icp__btn');
  btn?.setAttribute('aria-expanded', 'false');
  if (focusBtn) btn?.focus();
  if (open === box) open = null;
}

async function openPicker(box, input, btn, paint) {
  if (open && open !== box) close(open, false);
  open = box;
  const cat = await loadCatalog();
  const uid = 'icp' + (++seq);
  box.classList.add('is-open');
  btn.setAttribute('aria-expanded', 'true');
  const pop = d.createElement('div');
  pop.className = 'icp__pop';
  pop.setAttribute('role', 'dialog');
  pop.setAttribute('aria-label', t('Symbol wählen'));
  pop.innerHTML = `<div class="icp__head"><label class="icp__search">${ico('magnifying-glass')}<input type="search" class="icp__q" placeholder="${esc(t('Suchen, z. B. Kalender, Team, Arzt …'))}" aria-label="${esc(t('Symbole durchsuchen'))}" aria-controls="${uid}-list" autocomplete="off" spellcheck="false"></label></div>
    <div class="icp__list" id="${uid}-list" role="listbox" aria-label="${esc(t('Symbole'))}"></div>
    <p class="icp__foot" hidden><button type="button" class="icp__more"></button></p>
    <p class="icp__status" aria-live="polite"></p>`;
  box.append(pop);
  // Nach unten kein Platz (z. B. unten im Fenster): nach oben öffnen
  const r = box.getBoundingClientRect();
  box.classList.toggle('is-up', r.bottom + 380 > innerHeight && r.top > 400);
  // Rechts kein Platz (z. B. schmale Seitenspalte): nach links verschieben (CSSOM – CSP-konform)
  const pr = pop.getBoundingClientRect(), over = pr.right - (d.documentElement.clientWidth - 12);
  if (over > 0) pop.style.left = -Math.min(over, r.left - 12) + 'px';
  const q = pop.querySelector('.icp__q'), list = pop.querySelector('.icp__list'), status = pop.querySelector('.icp__status');
  const foot = pop.querySelector('.icp__foot'), more = pop.querySelector('.icp__more');
  const cur = input.value.trim();
  const optional = box.hasAttribute('data-optional');
  // Symbolbereiche: aktivierte Themen (null = alle); showAll nach „Weitere Bereiche anzeigen“
  const on = enabledTopics();
  let showAll = !on, io = null;
  const topicsShown = () => cat.topics.filter(tp => showAll || on.includes(tp.key));
  const allowed = () => new Set(topicsShown().flatMap(tp => tp.icons));

  // Themen-Gruppen (gi = Zahl): Platzhalter, Symbole erst beim Hineinscrollen (lazyIcons)
  const opt = (n, gi) => `<div role="option" class="icp__opt" id="${uid}-${gi}-${esc(n || '_none')}" data-name="${esc(n)}" tabindex="-1" aria-selected="${n === cur}" title="${esc(n ? label(cat, n) : t('Ohne Symbol'))}">${n ? (typeof gi === 'number' ? icoLazy(n) : ico(n)) : `<span class="icp__none" aria-hidden="true"></span>`}<span class="sr-only">${esc(n ? label(cat, n) : t('Ohne Symbol'))}</span></div>`;
  const group = (title, names, gi) => names.length ? `<div role="group" class="icp__group" aria-labelledby="${uid}-g${gi}"><p class="icp__gh" id="${uid}-g${gi}">${esc(title)}</p><div class="icp__grid">${names.map(n => opt(n, gi)).join('')}</div></div>` : '';

  const render = () => {
    const query = q.value.trim();
    let html = '', count = 0, hidden = 0;
    if (query) {
      let hits = search(cat, query);
      if (!showAll) { const ok = allowed(), all = hits.length; hits = hits.filter(n => ok.has(n)); hidden = all - hits.length; }
      count = hits.length;
      html = hits.length ? group(t('Treffer'), hits, 'h') : `<p class="icp__empty">${esc(t('Keine Symbole für „{q}“.', { q: query }))}</p>`;
    } else {
      const sugSrc = box.dataset.suggest ? (box.getRootNode().getElementById?.(box.dataset.suggest) || d.getElementById(box.dataset.suggest)) : null;
      const sug = sugSrc?.value ? suggest(cat, sugSrc.value) : [];
      const rec = recent().filter(n => cat.icons[n]);
      if (optional) html += group(t('Auswahl'), [''], 'o');
      html += group(t('Vorschläge'), sug, 's') + group(t('Zuletzt verwendet'), rec, 'r');
      cat.topics.forEach((tp, i) => { if (showAll || on.includes(tp.key)) html += group(EN() ? tp.en : tp.de, tp.icons, i); });
      count = allowed().size;
      hidden = showAll ? 0 : cat.topics.length - topicsShown().length;
    }
    list.innerHTML = html;
    io?.disconnect();
    io = lazyIcons(list, '.icp__group', list);
    foot.hidden = !hidden;
    more.textContent = query ? t('Weitere Bereiche anzeigen ({n} Treffer)', { n: hidden }) : t('Weitere Bereiche anzeigen ({n})', { n: hidden });
    status.textContent = query ? (count === 1 ? t('1 Symbol') : t('{n} Symbole', { n: count })) : '';
    const sel = list.querySelector('[aria-selected=true]') || list.querySelector('[role=option]');
    setActive(sel, false);
  };
  const opts = () => [...list.querySelectorAll('[role=option]')];
  const setActive = (el, focus = true) => {
    list.querySelectorAll('[tabindex="0"]').forEach(x => x.tabIndex = -1);
    if (!el) return;
    el.tabIndex = 0;
    if (focus) { el.focus(); el.scrollIntoView({ block: 'nearest' }); }
  };
  const choose = el => {
    if (!el) return;
    const n = el.dataset.name;
    input.value = n;
    if (n) remember(n);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    paint();
    close(box);
  };
  // Nächste Option senkrecht (über Gruppen hinweg): nächste Zeile, nächstgelegene Spalte
  const vertical = (el, dir) => {
    const a = el.getBoundingClientRect(), cx = a.left + a.width / 2;
    let best = null, bestRow = null;
    for (const o of opts()) {
      const b = o.getBoundingClientRect();
      if (dir > 0 ? b.top <= a.top + 2 : b.top >= a.top - 2) continue;
      if (bestRow === null || (dir > 0 ? b.top < bestRow - 2 : b.top > bestRow + 2)) { bestRow = b.top; best = o; }
      else if (Math.abs(b.top - bestRow) <= 2 && Math.abs(b.left + b.width / 2 - cx) < Math.abs(best.getBoundingClientRect().left + best.getBoundingClientRect().width / 2 - cx)) best = o;
    }
    return best;
  };

  q.addEventListener('input', render);
  more.addEventListener('click', () => { showAll = true; render(); q.focus(); });
  q.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown' || (e.key === 'Enter' && q.value.trim())) {
      e.preventDefault();
      const first = list.querySelector('[tabindex="0"]') || opts()[0];
      if (e.key === 'Enter' && q.value.trim()) choose(opts()[0]); else setActive(first);
    }
  });
  list.addEventListener('keydown', e => {
    const el = e.target.closest('[role=option]');
    if (!el) return;
    const all = opts(), i = all.indexOf(el);
    let next = null;
    switch (e.key) {
      case 'ArrowRight': next = all[i + 1]; break;
      case 'ArrowLeft': next = all[i - 1]; break;
      case 'ArrowDown': next = vertical(el, 1); break;
      case 'ArrowUp': next = vertical(el, -1); if (!next) { e.preventDefault(); q.focus(); return; } break;
      case 'Home': next = all[0]; break;
      case 'End': next = all[all.length - 1]; break;
      case 'Enter': case ' ': e.preventDefault(); choose(el); return;
      default:
        // Buchstaben: weiter in der Suche tippen
        if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) { q.focus(); return; }
        return;
    }
    e.preventDefault();
    if (next) setActive(next);
  });
  list.addEventListener('click', e => choose(e.target.closest('[role=option]')));
  pop.addEventListener('keydown', e => {
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(box); }
    // Tab bleibt im Fenster: Suche → Raster → „Weitere Bereiche“ (falls sichtbar) → Suche
    if (e.key === 'Tab') {
      const stops = [q, list, ...(foot.hidden ? [] : [more])];
      const at = e.target === more ? 2 : e.target.closest('.icp__list') ? 1 : 0;
      const next = stops[(at + (e.shiftKey ? stops.length - 1 : 1)) % stops.length];
      e.preventDefault();
      if (next === list) setActive(list.querySelector('[tabindex="0"]') || opts()[0]); else next.focus();
    }
  });
  render();
  q.focus();
}

// Klick außerhalb schließt (auch aus Schatten-Bäumen: composedPath)
d.addEventListener('pointerdown', e => {
  if (open && !(e.composedPath?.() || [e.target]).includes(open)) close(open, false);
});

/** Übersicht „Symbole“ (Handbuch & Hilfe): Filter nach Bezeichnung und Suchbegriffen (deutsch + englisch) */
export function initIconGallery(scope = d) {
  const box = scope.querySelector('[data-icon-gallery]');
  if (!box || box._init) return;
  box._init = true;
  const input = box.querySelector('[data-icon-filter]'), count = box.querySelector('[data-icon-count]'), empty = box.querySelector('[data-icon-empty]');
  const items = [...box.querySelectorAll('.icg__item')].map(li => [li, norm(li.dataset.search)]);
  const run = () => {
    const words = norm(input.value).split(/[^a-z0-9]+/).filter(Boolean);
    let n = 0;
    for (const [li, hay] of items) { const on = words.every(w => hay.includes(w)); li.hidden = !on; if (on) n++; }
    box.querySelectorAll('.icg__topic').forEach(s => { s.hidden = !s.querySelector('.icg__item:not([hidden])'); });
    empty.hidden = n > 0;
    count.textContent = words.length ? (n === 1 ? t('1 Symbol') : t('{n} Symbole', { n })) : '';
  };
  input.addEventListener('input', run);
  lazyIcons(box, '.icg__topic');   // Symbole eines Themas (und dessen Sprite) erst beim Hineinscrollen
}
