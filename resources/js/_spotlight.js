/*
 * Spotlight-Suche (⌘K / Strg+K): Aktionen, Seiten, Datensätze, Medien, Einstellungen.
 * ↑↓ auswählen · ↵ öffnen · ⌘↵ alternativ (z. B. auf der Website ansehen) · esc schließen
 */
import { t } from './_i18n.js';
import { layerBox, pathClosest, ui } from './_shadow.js';
import { ico } from './_icons.js';
const d = document;
const $ = (s, c = d) => c.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);
// Symbole kommen aus dem Sprite (Core\Icons; der Server liefert je Treffer „ico“) – ohne Namen: Dokument
const svg = () => ico('file-text');

let dlg, input, list, items = [], active = 0, timer, ctrl;
const endpoint = () => (ui('[data-search-endpoint]')?.dataset.searchEndpoint) || '/admin/api/search';

function build() {
  const box = layerBox();   // Website: Ebene im Shadow DOM (Kit-CSS wirkt nicht hinein), Verwaltung: body
  box.insertAdjacentHTML('beforeend', `<dialog class="sl" aria-label="${t('Suche')}">
    <div class="sl-box" role="combobox" aria-expanded="true" aria-haspopup="listbox" aria-owns="sl-list">
      <label class="sl-field">${ico('magnifying-glass')}<input type="search" placeholder="${t('Suchen – Seiten, Beiträge, Medien, Einstellungen …')}" aria-label="${t('Suchen')}" aria-controls="sl-list" autocomplete="off" spellcheck="false"></label>
      <div class="sl-list" id="sl-list" role="listbox"></div>
      <footer class="sl-foot"><span><kbd>↑</kbd><kbd>↓</kbd> ${t('auswählen')}</span><span><kbd>↵</kbd> ${t('öffnen')}</span><span><kbd>${isMac ? '⌘' : 'Strg'}</kbd><kbd>↵</kbd> ${t('Alternative')}</span><span><kbd>esc</kbd> ${t('schließen')}</span></footer>
    </div></dialog>`);
  dlg = box.lastElementChild; input = $('input', dlg); list = $('.sl-list', dlg);
  dlg.addEventListener('click', e => { if (e.target === dlg) dlg.close(); });
  input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 110); });
  input.addEventListener('keydown', e => {
    if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
    else if (e.key === 'Enter') { e.preventDefault(); go(items[active], e.metaKey || e.ctrlKey); }
  });
  list.addEventListener('mousemove', e => { const el = e.target.closest('[data-i]'); if (el && +el.dataset.i !== active) { active = +el.dataset.i; mark(); } });
  list.addEventListener('click', e => { const el = e.target.closest('[data-i]'); if (el) { e.preventDefault(); go(items[+el.dataset.i], e.metaKey || e.ctrlKey); } });
}

function hl(text, q) {
  let h = esc(text);
  for (const w of q.trim().split(/\s+/).filter(Boolean)) {
    h = h.replace(new RegExp('(' + w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'), '<mark>$1</mark>');
  }
  return h;
}

async function load() {
  const q = input.value.trim();
  ctrl?.abort(); ctrl = new AbortController();
  try {
    const r = await fetch(endpoint() + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, signal: ctrl.signal, credentials: 'same-origin' });
    const data = await r.json();
    items = []; let html = '';
    for (const g of data.groups) {
      html += `<div class="sl-group" role="presentation">${esc(g.label)}</div>`;
      for (const it of g.items) {
        const i = items.push(it) - 1;
        // Symbol: Vorschaubild, Symbol aus dem Sprite (ico, vom Server aufgelöst), altes Zeichen, sonst gezeichnetes Symbol
        const icon = it.thumb ? `<img src="${esc(it.thumb)}" alt="">` : it.ico ? ico(it.ico) : it.glyph ? `<b>${esc(it.glyph)}</b>` : svg(it.icon);
        html += `<a class="sl-item" role="option" id="sl-${i}" data-i="${i}" href="${esc(it.url)}"><span class="sl-icon">${icon}</span>
          <span class="sl-text"><span class="sl-title">${hl(it.title, q)}</span><span class="sl-sub">${esc(it.sub || '')}</span></span>
          <span class="sl-hint">${it.alt ? `<kbd>${isMac ? '⌘' : 'Strg'}↵</kbd> ` : ''}<kbd>↵</kbd></span></a>`;
      }
    }
    list.innerHTML = html || `<p class="sl-empty">${esc(t('Keine Treffer für „{q}“.', { q }))}</p>`;
    active = 0; mark();
  } catch (e) { if (e.name !== 'AbortError') list.innerHTML = `<p class="sl-empty">${t('Suche nicht verfügbar.')}</p>`; }
}

function mark() {
  list.querySelectorAll('.sl-item').forEach(el => el.classList.toggle('is-active', +el.dataset.i === active));
  const el = $(`#sl-${active}`, list);
  if (el) { el.scrollIntoView({ block: 'nearest' }); input.setAttribute('aria-activedescendant', el.id); }
}
function move(n) { if (!items.length) return; active = (active + n + items.length) % items.length; mark(); }
function go(it, alt) {
  if (!it) return;
  // „Assistent fragen …“ (Core\AI\Assistant): Chat-Fenster öffnen statt zu navigieren, sofern es geladen werden kann
  if (it.cmd === 'assistant' && !alt && window.cmsAssistant) { dlg.close(); window.cmsAssistant.open({ q: it.q || '' }); return; }
  const url = alt && it.alt ? it.alt : it.url;
  if (alt && it.alt) window.open(url, '_blank', 'noopener'); else location.href = url;
}

export function openSpotlight() {
  if (!dlg) build();
  if (dlg.open) return;
  dlg.showModal(); input.value = ''; input.focus(); load();
}

d.addEventListener('keydown', e => {
  if ((e.metaKey || e.ctrlKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === 'k') { e.preventDefault(); dlg?.open ? dlg.close() : openSpotlight(); }
});
d.addEventListener('click', e => { if (pathClosest(e, '[data-spotlight]')) { e.preventDefault(); openSpotlight(); } });

// Tastenkürzel-Hinweis passend zum System
[...d.querySelectorAll('[data-kbd]'), ...(d.querySelector('.cms-bar-host')?.shadowRoot?.querySelectorAll('[data-kbd]') || [])].forEach(k => { k.textContent = isMac ? '⌘K' : t('Strg') + ' K'; });
