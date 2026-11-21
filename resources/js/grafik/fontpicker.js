/*
 * Grafik-Editor – Schriftauswahl mit Vorschau (wie in Affinity/Illustrator): Eingabefeld als Kombinationsfeld (WAI-ARIA
 * Combobox mit Listbox, aria-activedescendant), jede Schrift in ihrem eigenen Schriftbild. Abschnitte: Zuletzt verwendet,
 * Favoriten (Stern, je Konto über Core\UiPrefs), Schriften der Website (selbst gehostet, Schriften-Manager – font.css ist in
 * der Verwaltung geladen), Systemschriften (als Stapel). Rechts die Schnitte der markierten Schrift mit Vorschau.
 *
 * Tastatur: Tippen filtert, ↑/↓ Schrift, → Schnitte (← zurück), Pos1/Ende, ↵ übernehmen, Esc verwerfen, ⌘/Strg+D Favorit.
 * Zeiger oder Pfeile auf einer Schrift zeigen sie sofort am ausgewählten Text (onPreview); Esc oder Klick daneben nimmt das
 * zurück (onPreview(null)), ↵/Klick übernimmt (onPick). MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { ico } from '../_icons.js';
import { MOD } from './ui.js';

/** Systemschriften (keine Datei – Desktop-Programme und Browser nehmen ihre eigene) */
export const SYSTEM_FONTS = [
  ['system-ui, sans-serif', () => t('Systemschrift')], ['sans-serif', () => t('Serifenlos (System)')], ['serif', () => t('Serifen (System)')],
  ['monospace', () => t('Festbreite (System)')], ['Georgia, serif', () => 'Georgia'], ['Arial, Helvetica, sans-serif', () => 'Arial / Helvetica'],
];
export const WEIGHTS = { 100: () => t('Hauchdünn'), 200: () => t('Extraleicht'), 300: () => t('Leicht'), 400: () => t('Normal'), 500: () => t('Mittel'), 600: () => t('Halbfett'), 700: () => t('Fett'), 800: () => t('Extrafett'), 900: () => t('Schwarz'), 1000: () => t('Extraschwarz') };

/** Erste Familie eines Stapels ohne Anführungszeichen */
export const firstFamily = v => String(v || '').split(',')[0].trim().replace(/^["']|["']$/g, '');
const quote = f => /[\s\d]/.test(f) && !/^["']/.test(f) ? `'${f}'` : f;

/**
 * Alle wählbaren Schriften: [{ key (= Stapel), family, label, site, variable, axes, styles: [{w, i}] }]
 * Schriften der Website: aus boot.fontFiles (Schnitte und Achsen der Dateien) und den Schriften des Designs (boot.fonts).
 */
export function fontItems(boot) {
  const files = boot.fontFiles || {};
  const names = [...new Set([...Object.keys(files), ...(boot.fonts || []).filter(f => !/^(system-ui|sans-serif|serif|monospace)$/.test(f))])].sort((a, b) => a.localeCompare(b));
  const out = names.map(fam => {
    const f = files[fam];
    const cat = f?.category || (/serif$/i.test(fam) && !/sans/i.test(fam) ? 'serif' : 'sans-serif');
    const generic = cat === 'serif' ? 'serif' : cat === 'monospace' ? 'monospace' : 'sans-serif';
    const variable = !!f?.files.some(x => x.weight === null);
    const wr = f?.axes?.wght;
    let weights;
    if (!f || variable) {
      const lo = wr ? wr.min : 100, hi = wr ? wr.max : 900;
      weights = Object.keys(WEIGHTS).map(Number).filter(w => w >= lo && w <= hi);
    } else weights = [...new Set(f.files.map(x => x.weight))].sort((a, b) => a - b);
    const italics = !f || f.files.some(x => x.style === 'italic');
    const styles = [];
    for (const w of weights) { styles.push({ w, i: false }); if (italics && (!f || f.files.some(x => x.style === 'italic' && (x.weight === null || x.weight === w)))) styles.push({ w, i: true }); }
    return { key: `${quote(fam)}, ${generic}`, family: fam, label: fam, site: true, variable, axes: f?.axes || null, hasFile: !!f, styles };
  });
  for (const [stack, label] of SYSTEM_FONTS) {
    out.push({ key: stack, family: firstFamily(stack), label: label(), site: false, variable: false, axes: null, hasFile: false,
      styles: [300, 400, 700].flatMap(w => [{ w, i: false }, { w, i: true }]) });
  }
  return out;
}

/** Passender Eintrag zu einem font-family-Wert (Stapel oder einzelne Familie) */
export function itemFor(items, value) {
  const v = String(value || '').trim();
  if (!v) return null;
  const ff = firstFamily(v).toLowerCase();
  return items.find(i => i.key === v) || items.find(i => i.site && i.family.toLowerCase() === ff)
    || items.find(i => !i.site && firstFamily(i.key).toLowerCase() === ff) || null;
}

export const styleLabel = s => (WEIGHTS[s.w]?.() || String(s.w)) + (s.i ? ' ' + t('kursiv') : '');

let seq = 0;

/**
 * Schriftauswahl bauen. opts: { label, prefs: () => ({fontFav, fontRecent}), setPref(key, value), onPreview(props|null),
 * onPick({ 'font-family', 'font-weight'?, 'font-style'? }), compact } → { el, set(value), close(), input }
 */
export function fontPicker(ed, opts = {}) {
  const id = `${ed.uid}-fp${++seq}`;
  const items = fontItems(ed.boot);
  const el = document.createElement('div');
  el.className = 'ge-fp' + (opts.compact ? ' ge-fp--compact' : '');
  el.innerHTML = `<input class="ge-in ge-fp__in" role="combobox" aria-expanded="false" aria-controls="${id}-list" aria-autocomplete="list" aria-haspopup="listbox"
      aria-label="${esc(opts.label || t('Schrift'))}" autocomplete="off" spellcheck="false" aria-describedby="${id}-hint"><span class="ge-fp__caret" aria-hidden="true">${ico('caret-down')}</span>
    <span class="adm-sr" id="${id}-hint">${esc(t('Tippen filtert, Pfeil nach rechts zeigt die Schnitte, {mod}D merkt als Favorit.', { mod: MOD }))}</span>`;
  const input = el.querySelector('input');
  let value = '', pop = null, active = null, col = 'fam', styleActive = 0, query = '', previewing = false;

  const prefs = () => opts.prefs?.() || { fontFav: [], fontRecent: [] };
  const itemByKey = k => items.find(i => i.key === k);
  const cur = () => itemFor(items, value);

  function paintInput() {
    const it = cur();
    input.value = it ? it.label : firstFamily(value);
    input.style.fontFamily = it ? it.key : value;
  }

  /** Abschnitte nach Filter: [{id, label, list: [item]}] */
  function sections() {
    const q = query.trim().toLowerCase();
    const match = i => !q || i.label.toLowerCase().includes(q) || i.family.toLowerCase().includes(q);
    const p = prefs();
    const recent = p.fontRecent.map(itemByKey).filter(Boolean).filter(match);
    const favs = p.fontFav.map(itemByKey).filter(Boolean).filter(match);
    return [
      { id: 'recent', label: t('Zuletzt verwendet'), list: q ? [] : recent.slice(0, 5) },
      { id: 'fav', label: t('Favoriten'), list: favs },
      { id: 'site', label: t('Schriften der Website'), list: items.filter(i => i.site && match(i)) },
      { id: 'sys', label: t('Systemschriften'), list: items.filter(i => !i.site && match(i)) },
    ].filter(s => s.list.length);
  }

  /** Optionen in Reihenfolge (eine Schrift kann in mehreren Abschnitten stehen: id je Abschnitt) */
  let flat = [];
  function render() {
    const favs = new Set(prefs().fontFav);
    const secs = sections();
    const was = flat.find(f => f.oid === active);
    flat = [];
    const list = pop.querySelector('.ge-fp__fams');
    list.innerHTML = secs.map(s => `<div role="group" class="ge-fp__grp" aria-label="${esc(s.label)}" data-label="${esc(s.label)}">${s.list.map(it => {
      const oid = `${id}-o${flat.length}`;
      flat.push({ oid, it, sec: s.id });
      const fav = favs.has(it.key);
      const meta = it.site ? (it.variable ? t('variabel') : t('{n} Schnitte', { n: it.styles.length })) : t('System');
      return `<div role="option" id="${oid}" class="ge-fp__opt" aria-selected="false" data-k="${esc(it.key)}"${it.key === cur()?.key ? ' data-current="1"' : ''} aria-label="${esc(it.label + (fav ? ', ' + t('Favorit') : '') + ', ' + meta)}">`
        + `<span class="ge-fp__name" style="font-family:${esc(it.key)}">${esc(it.label)}</span><span class="ge-fp__meta">${esc(meta)}</span>`
        + `<span class="ge-fp__star${fav ? ' is-on' : ''}" data-star title="${esc(fav ? t('Aus den Favoriten entfernen') : t('Als Favorit merken'))} (${MOD}D)" aria-hidden="true">${ico('star')}</span></div>`;
    }).join('')}</div>`).join('');
    pop.querySelector('.ge-fp__empty').hidden = flat.length > 0;
    // markierte Schrift bleibt markiert (gleicher Abschnitt, sonst irgendwo); sonst Treffer bzw. aktuelle Schrift
    const keep = was && (flat.find(f => f.it === was.it && f.sec === was.sec) || flat.find(f => f.it === was.it));
    if (keep) active = keep.oid;
    else {
      const c = cur();
      active = query.trim() ? flat[0]?.oid || null : (flat.find(f => f.it.key === c?.key && f.sec !== 'recent')?.oid || flat[0]?.oid || null);
    }
    markActive(false);
  }

  const activeItem = () => flat.find(f => f.oid === active)?.it || null;

  function renderStyles() {
    const it = activeItem();
    const box = pop.querySelector('.ge-fp__styles');
    if (!it) { box.innerHTML = ''; box.setAttribute('aria-label', t('Schnitte')); return; }
    box.setAttribute('aria-label', t('Schnitte von {font}', { font: it.label }));
    box.innerHTML = it.styles.map((s, i) => `<div role="option" id="${id}-s${i}" class="ge-fp__opt ge-fp__sty" aria-selected="${col === 'sty' && i === styleActive}" data-i="${i}"
      ><span class="ge-fp__name" style="font-family:${esc(it.key)};font-weight:${s.w};font-style:${s.i ? 'italic' : 'normal'}">${esc(styleLabel(s))}</span><span class="ge-fp__meta">${s.w}</span></div>`).join('');
  }

  function markActive(preview = true) {
    if (!pop) return;
    pop.querySelectorAll('.ge-fp__fams [role=option]').forEach(o => o.setAttribute('aria-selected', String(o.id === active)));
    renderStyles();
    const node = col === 'sty' ? pop.querySelector(`#${id}-s${styleActive}`) : active && pop.querySelector('#' + active);
    if (node) { input.setAttribute('aria-activedescendant', node.id); node.scrollIntoView({ block: 'nearest' }); } else input.removeAttribute('aria-activedescendant');
    pop.querySelector('.ge-fp__fams').classList.toggle('is-dim', col === 'sty');
    if (preview) doPreview();
  }

  function propsFor(it, s = null) {
    const p = { 'font-family': it.key };
    if (s) { p['font-weight'] = s.w === 400 ? null : String(s.w); p['font-style'] = s.i ? 'italic' : null; }
    return p;
  }
  let raf = 0;
  function doPreview() {
    const it = activeItem();
    if (!it || !opts.onPreview) return;
    const s = col === 'sty' ? it.styles[styleActive] : null;
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(() => { previewing = true; opts.onPreview(propsFor(it, s)); });
  }
  function revert() {
    cancelAnimationFrame(raf);
    if (previewing) { previewing = false; opts.onPreview?.(null); }
  }

  function open() {
    if (pop) return;
    query = '';
    col = 'fam';
    pop = document.createElement('div');
    pop.className = 'ge-fp__pop';
    pop.innerHTML = `<div class="ge-fp__cols"><div class="ge-fp__fams" role="listbox" id="${id}-list" aria-label="${esc(t('Schriften'))}"></div>
      <div class="ge-fp__styles" role="listbox" id="${id}-styles" aria-label="${esc(t('Schnitte'))}"></div></div>
      <p class="ge-fp__empty" hidden>${esc(t('Keine Schrift gefunden.'))}</p>
      <p class="ge-fp__foot" aria-hidden="true">${esc(t('↑↓ Schrift · → Schnitt · ↵ übernehmen · Esc zurück · {mod}D Favorit', { mod: MOD }))}</p>`;
    const host = ed.el;
    host.append(pop);
    place();
    input.setAttribute('aria-expanded', 'true');
    input.setAttribute('aria-controls', `${id}-list ${id}-styles`);
    active = null; flat = [];
    render();
    pop.addEventListener('pointerdown', e => e.preventDefault());   // Fokus bleibt im Eingabefeld
    pop.addEventListener('pointermove', e => {
      const o = e.target.closest('[role=option]');
      if (!o) return;
      if (o.classList.contains('ge-fp__sty')) { if (col !== 'sty' || styleActive !== +o.dataset.i) { col = 'sty'; styleActive = +o.dataset.i; markActive(); } }
      else if (o.id !== active || col !== 'fam') { col = 'fam'; active = o.id; styleActive = 0; markActive(); }
    });
    pop.addEventListener('pointerleave', () => { revert(); });
    pop.addEventListener('click', e => {
      const star = e.target.closest('[data-star]');
      const o = e.target.closest('[role=option]');
      if (star && o) { toggleFav(flat.find(f => f.oid === o.id)?.it); return; }
      if (!o) return;
      if (o.classList.contains('ge-fp__sty')) { col = 'sty'; styleActive = +o.dataset.i; } else { col = 'fam'; active = o.id; }
      pick();
    });
    setTimeout(() => document.addEventListener('pointerdown', outside, true));
  }
  function outside(e) { if (pop && !pop.contains(e.target) && !el.contains(e.target)) close(true); }

  function place() {
    const r = input.getBoundingClientRect(), rr = ed.el.getBoundingClientRect();
    const w = Math.min(460, rr.width - 16);
    const below = rr.bottom - r.bottom - 12, above = r.top - rr.top - 12;
    const up = below < 260 && above > below;
    const hgt = Math.max(160, Math.min(420, (up ? above : below) - 40));   // 40 = Fußzeile der Liste
    pop.style.width = w + 'px';
    pop.style.left = Math.max(8, Math.min(r.left - rr.left, rr.width - w - 8)) + 'px';
    pop.style.setProperty('--fp-h', hgt + 'px');
    if (up) { pop.style.bottom = (rr.bottom - r.top + 4) + 'px'; pop.style.top = ''; } else { pop.style.top = (r.bottom - rr.top + 4) + 'px'; pop.style.bottom = ''; }
  }

  function close(revertIt = true) {
    if (!pop) return;
    if (revertIt) revert();
    document.removeEventListener('pointerdown', outside, true);
    pop.remove(); pop = null;
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', `${id}-list`);
    input.removeAttribute('aria-activedescendant');
    paintInput();
  }

  function pick() {
    const it = activeItem();
    if (!it) return;
    const s = col === 'sty' ? it.styles[styleActive] : null;
    cancelAnimationFrame(raf);
    // Vorschau zurücknehmen, dann als ein Schritt im Protokoll übernehmen
    if (previewing) { previewing = false; opts.onPreview?.(null); }
    value = it.key;
    const p = prefs();
    opts.setPref?.('fontRecent', [it.key, ...p.fontRecent.filter(k => k !== it.key)].slice(0, 8));
    close(false);
    opts.onPick?.(propsFor(it, s));
  }

  function toggleFav(it) {
    if (!it) return;
    const p = prefs();
    const on = p.fontFav.includes(it.key);
    opts.setPref?.('fontFav', on ? p.fontFav.filter(k => k !== it.key) : [...p.fontFav, it.key]);
    if (pop) render();
    ed.announce?.(on ? t('„{font}“ aus den Favoriten entfernt', { font: it.label }) : t('„{font}“ als Favorit gemerkt', { font: it.label }));
  }

  function move(d) {
    if (col === 'sty') {
      const n = activeItem()?.styles.length || 0;
      if (!n) return;
      styleActive = d === 'home' ? 0 : d === 'end' ? n - 1 : (styleActive + d + n) % n;
    } else {
      if (!flat.length) return;
      const i = flat.findIndex(f => f.oid === active);
      const j = d === 'home' ? 0 : d === 'end' ? flat.length - 1 : i < 0 ? 0 : (i + d + flat.length) % flat.length;
      active = flat[j].oid;
      styleActive = 0;
    }
    markActive();
  }

  input.addEventListener('keydown', e => {
    e.stopPropagation();   // Kürzel des Editors (V, T …) gelten hier nicht
    const mod = e.metaKey || e.ctrlKey;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (!pop) { open(); if (e.altKey) return; }
      if (e.altKey && e.key === 'ArrowUp') { close(true); return; }
      move(e.key === 'ArrowDown' ? 1 : -1);
      return;
    }
    if (!pop) {
      if (e.key === 'Enter' || (e.altKey && e.key === 'ArrowDown')) { e.preventDefault(); open(); }
      return;
    }
    if (e.key === 'ArrowRight' && col === 'fam' && input.selectionStart === input.value.length && activeItem()?.styles.length) {
      e.preventDefault(); col = 'sty'; styleActive = Math.max(0, activeItem().styles.findIndex(s => s.w === 400 && !s.i)); markActive(); return;
    }
    if (e.key === 'ArrowLeft' && col === 'sty') { e.preventDefault(); col = 'fam'; markActive(); return; }
    if (e.key === 'Home' && mod) { e.preventDefault(); move('home'); return; }
    if (e.key === 'End' && mod) { e.preventDefault(); move('end'); return; }
    if (e.key === 'PageDown' || e.key === 'PageUp') { e.preventDefault(); for (let k = 0; k < 6; k++) move(e.key === 'PageDown' ? 1 : -1); return; }
    if (e.key === 'Enter') { e.preventDefault(); pick(); return; }
    if (e.key === 'Escape') { e.preventDefault(); close(true); return; }
    if (e.key === 'Tab') { close(true); return; }
    if (mod && e.key.toLowerCase() === 'd') { e.preventDefault(); toggleFav(activeItem()); }
  });
  input.addEventListener('input', () => {
    if (!pop) open();
    query = input.value;
    col = 'fam';
    active = null;
    flat = [];
    render();
    // erster Treffer wird gleich gezeigt (Vorschau beim Tippen)
    if (flat.length) doPreview(); else revert();
  });
  input.addEventListener('focus', () => input.select());
  // Klick: Liste öffnen und ganzen Namen markieren (Tippen ersetzt ihn) – auch, wenn das Feld schon den Fokus hat
  input.addEventListener('mousedown', e => { if (!pop && document.activeElement === input) { e.preventDefault(); open(); input.select(); setTimeout(() => input.select(), 30); } });
  input.addEventListener('click', () => { if (!pop) { open(); input.select(); } });
  el.querySelector('.ge-fp__caret').addEventListener('pointerdown', e => { e.preventDefault(); input.focus(); if (pop) close(true); else open(); });
  input.addEventListener('blur', () => { if (pop) close(true); });

  return {
    el, input,
    set(v) { value = v || ''; if (!pop) paintInput(); },
    close,
    get isOpen() { return !!pop; },
    items,
  };
}
