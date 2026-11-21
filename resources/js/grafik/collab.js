/*
 * Grafik-Editor – Zusammenarbeit (Ausbaustufe 5): Fassungen vergleichen und wiederherstellen, „Verwendet auf“, Präsenz.
 *   Fassungen (graphic_versions): Liste, Vergleich nebeneinander oder überblendet (Regler, Tastatur), „Wiederherstellen“ = ein Schritt
 *     im Protokoll (Rückgängig holt den Stand davor zurück; gespeichert wird erst mit Speichern).
 *   Verwendet auf: Seiten, Blöcke, Einträge (Media::usages) mit Link und Vorschau der Seite (verkleinert, lädt erst auf Wunsch).
 *   Präsenz: Core\Presence (Typ „graphic“, id = ref) – wer die Grafik gerade bearbeitet, erscheint oben; hat jemand inzwischen
 *     gespeichert, fragt Speichern nach (wie bei Seiten). MIT
 */
import { t } from '../_i18n.js';
import { esc, csrf } from '../_html.js';
import { ico } from '../_icons.js';
import { toast } from '../_toast.js';
import { h, modal } from './ui.js';

const when = s => { const d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? String(s) : d.toLocaleString(document.documentElement.lang || 'de', { dateStyle: 'medium', timeStyle: 'short' }); };
const svgUrl = svg => 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);

export class Collab {
  constructor(ed) {
    this.ed = ed;
    this.opened = Math.floor(Date.now() / 1000);
    this.here = [];
    this.last = null;
    this.el = h(`<div class="ge-collab" role="group" aria-label="${esc(t('Zusammenarbeit'))}">
      <span class="ge-collab__who" data-who aria-live="polite"></span>
      <button type="button" class="ge-ib" data-act="versions" aria-label="${esc(t('Fassungen vergleichen'))}" title="${esc(t('Frühere Fassungen vergleichen und wiederherstellen'))}">${ico('clock-counter-clockwise')}</button>
      <button type="button" class="ge-ib" data-act="usage" aria-label="${esc(t('Verwendet auf'))}" title="${esc(t('Verwendet auf – wo ist die Grafik eingebunden?'))}">${ico('link')}</button>
    </div>`);
    ed.$('.ge-doctitle').after(this.el);
    this.el.querySelector('[data-act=versions]').addEventListener('click', () => this.versions());
    this.el.querySelector('[data-act=usage]').addEventListener('click', () => this.usage());
    ed.on('save', () => { this.opened = Math.floor(Date.now() / 1000); this.update(); this.beat(); });
    this.update();
    this.beat();
    this.timer = setInterval(() => { if (document.visibilityState === 'visible') this.beat(); }, 15000);
    this.bye = () => this.leave();
    window.addEventListener('pagehide', this.bye);
  }

  get ref() { return this.ed.doc.ref || ''; }

  update() {
    const has = !!this.ref;
    this.el.hidden = !has;
    this.el.querySelector('[data-act=versions]').disabled = !(this.ed.doc.versions || []).length;
  }

  // ================================================================= Präsenz
  async beat() {
    const url = this.ed.boot.urls.presence;
    if (!url || !this.ref) return;
    try {
      const r = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() },
        body: JSON.stringify({ type: 'graphic', id: this.ref, label: this.ed.doc.title || this.ed.doc.name || t('Grafik'), url: location.pathname + location.search }) });
      if (!r.ok) return;
      const j = await r.json();
      this.here = (j.here || []).filter(p => !p.self);
      this.last = j.last || null;
      this.renderWho();
    } catch { /* ohne Verbindung: nichts anzeigen */ }
  }

  leave() {
    const url = this.ed.boot.urls.presence;
    if (!url || !this.ref) return;
    try { fetch(url, { method: 'POST', keepalive: true, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify({ type: 'graphic', id: this.ref, leave: 1 }) }); } catch { /* egal */ }
  }

  renderWho() {
    const box = this.el.querySelector('[data-who]');
    if (!this.here.length) { box.innerHTML = ''; return; }
    const names = this.here.map(p => p.name).join(', ');
    box.innerHTML = `<span class="ge-collab__pill" title="${esc(t('{names} bearbeitet gerade diese Grafik', { names }))}">${this.here.slice(0, 3).map(p => p.avatar
      ? `<img src="${esc(p.avatar)}" alt="" class="ge-collab__av">` : `<span class="ge-collab__av" aria-hidden="true">${esc(p.initials || '?')}</span>`).join('')}<span>${esc(this.here.length === 1 ? t('{names} bearbeitet gerade', { names }) : t('{names} bearbeiten gerade', { names }))}</span></span>`;
  }

  /** Vor dem Speichern: hat jemand anderes inzwischen gespeichert? */
  async confirmSave() {
    if (!this.ref || !this.ed.boot.urls.presence) return true;
    await this.beat();
    const l = this.last;
    if (!l || l.self || !l.at || l.at <= this.opened) return true;
    return !!(await modal(this.ed.el, { title: t('Inzwischen von {name} gespeichert', { name: l.name || t('jemand anderem') }), ok: t('Trotzdem speichern'),
      body: `<p>${esc(t('Seit Sie die Grafik geöffnet haben, hat {name} sie gespeichert. Wenn Sie jetzt speichern, ersetzt das diesen Stand – er bleibt als frühere Fassung erhalten (Fassungen vergleichen).', { name: l.name || t('jemand anderes') }))}</p>` }));
  }

  destroy() {
    clearInterval(this.timer);
    window.removeEventListener('pagehide', this.bye);
    this.leave();
  }

  // ================================================================= Fassungen vergleichen
  async versions(pick = null) {
    const ed = this.ed, vers = ed.doc.versions || [];
    if (!vers.length) { toast(t('Noch keine früheren Fassungen – sie entstehen mit jedem Speichern.'), 'info'); return; }
    const current = ed.getSvg();
    let sel = pick ?? vers[0].id, mode = 'side', cache = {};
    const load = async id => cache[id] ||= (await ed.api('GET', ed.boot.urls.version + '?ref=' + encodeURIComponent(this.ref) + '&id=' + id))?.svg || null;
    const r = await modal(ed.el, {
      title: t('Fassungen vergleichen'), ok: t('Diese Fassung wiederherstellen'), wide: true,
      body: `<div class="ge-vc">
        <ul class="ge-vc__list" role="listbox" aria-label="${esc(t('Frühere Fassungen'))}">${vers.map((v, i) => `<li role="option" tabindex="${v.id === sel ? 0 : -1}" aria-selected="${v.id === sel}" data-ver="${v.id}"><strong>${esc(when(v.created_at))}</strong>${v.user ? `<span>${esc(t('ersetzt von {name}', { name: v.user }))}</span>` : ''}${i === 0 ? `<span>${esc(t('zuletzt gespeichert davor'))}</span>` : ''}</li>`).join('')}</ul>
        <div class="ge-vc__main">
          <div class="ge-vc__modes" role="radiogroup" aria-label="${esc(t('Ansicht'))}"><button type="button" role="radio" aria-checked="true" data-mode="side">${esc(t('Nebeneinander'))}</button><button type="button" role="radio" aria-checked="false" tabindex="-1" data-mode="over">${esc(t('Überblenden'))}</button></div>
          <div class="ge-vc__side" data-side><figure><figcaption data-vcap></figcaption><div class="ge-vc__img"><img alt="" data-vimg></div></figure><figure><figcaption>${esc(t('Aktuell im Editor'))}</figcaption><div class="ge-vc__img"><img alt="${esc(t('Aktueller Stand'))}" src="${svgUrl(current)}"></div></figure></div>
          <div class="ge-vc__over" data-over hidden><div class="ge-vc__stack"><img alt="${esc(t('Aktueller Stand'))}" src="${svgUrl(current)}"><img alt="" data-vimg2 class="ge-vc__top"><span class="ge-vc__line" aria-hidden="true"></span></div>
            <label class="ge-lbl ge-vc__slider"><span data-vcap2></span><input type="range" class="ge-range" min="0" max="100" step="1" value="50" data-x aria-label="${esc(t('Überblenden: frühere Fassung links, aktueller Stand rechts'))}"><span>${esc(t('Aktuell'))}</span></label></div>
        </div></div>`,
      onOpen: (d, finish) => {
        const show = async () => {
          const svg = await load(sel);
          const v = vers.find(x => x.id === sel);
          const cap = t('Fassung vom {date}', { date: when(v.created_at) });
          d.querySelector('[data-vcap]').textContent = cap;
          d.querySelector('[data-vcap2]').textContent = when(v.created_at);
          for (const img of d.querySelectorAll('[data-vimg],[data-vimg2]')) { img.src = svg ? svgUrl(svg) : ''; img.alt = cap; }
        };
        d.querySelectorAll('[data-ver]').forEach(li => {
          const go = () => { sel = +li.dataset.ver; d.querySelectorAll('[data-ver]').forEach(x => { x.setAttribute('aria-selected', String(x === li)); x.tabIndex = x === li ? 0 : -1; }); show(); };
          li.addEventListener('click', go);
          li.addEventListener('keydown', e => {
            const all = [...d.querySelectorAll('[data-ver]')], i = all.indexOf(li);
            const k = e.key === 'ArrowDown' ? 1 : e.key === 'ArrowUp' ? -1 : 0;
            if (k) { e.preventDefault(); const n = all[(i + k + all.length) % all.length]; n.focus(); n.click(); }
            if (e.key === 'Enter') { e.preventDefault(); go(); finish({ id: sel }); }
          });
        });
        const modes = [...d.querySelectorAll('[data-mode]')];
        const setMode = (b, focus) => { mode = b.dataset.mode; modes.forEach(x => { x.setAttribute('aria-checked', String(x === b)); x.tabIndex = x === b ? 0 : -1; }); d.querySelector('[data-side]').hidden = mode !== 'side'; d.querySelector('[data-over]').hidden = mode !== 'over'; if (focus) b.focus(); };
        modes.forEach((b, i) => {
          b.addEventListener('click', () => setMode(b));
          b.addEventListener('keydown', e => { const k = ['ArrowRight', 'ArrowDown'].includes(e.key) ? 1 : ['ArrowLeft', 'ArrowUp'].includes(e.key) ? -1 : 0; if (k) { e.preventDefault(); setMode(modes[(i + k + modes.length) % modes.length], true); } });
        });
        const x = d.querySelector('[data-x]'), stack = d.querySelector('.ge-vc__stack');
        const setX = () => stack.style.setProperty('--x', x.value + '%');
        x.addEventListener('input', setX); setX();
        x.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });   // Enter im Regler stellt nichts wieder her
        // Klick/Ziehen im Bild setzt die Trennlinie
        stack.addEventListener('pointerdown', e => {
          const mv = ev => { const r = stack.getBoundingClientRect(); x.value = String(Math.round(Math.max(0, Math.min(1, (ev.clientX - r.left) / r.width)) * 100)); setX(); };
          mv(e); stack.setPointerCapture?.(e.pointerId);
          const up = () => { stack.removeEventListener('pointermove', mv); stack.removeEventListener('pointerup', up); };
          stack.addEventListener('pointermove', mv); stack.addEventListener('pointerup', up);
        });
        show();
        d.querySelector(`[data-ver="${sel}"]`)?.focus();
      },
    });
    if (!r) return;
    const svg = await load(r.id ?? sel);
    if (!svg) return;
    const v = vers.find(x => x.id === (r.id ?? sel));
    ed.load(svg);
    ed.commit(t('Fassung vom {date} wiederhergestellt', { date: when(v?.created_at || '') }));
    toast(t('Fassung wiederhergestellt – gespeichert wird sie erst mit „Speichern“. Rückgängig holt den Stand davor zurück.'));
  }

  // ================================================================= Verwendet auf
  async usage() {
    const ed = this.ed;
    const res = await ed.api('GET', ed.boot.urls.usage + '?ref=' + encodeURIComponent(this.ref));
    if (!res) return;
    const items = res.items || [];
    await modal(ed.el, {
      title: t('Verwendet auf'), ok: '', wide: true,
      body: items.length ? `<p class="f-help">${esc(t('Speichern ändert die Grafik überall, wo sie eingebunden ist ({n} Stellen).', { n: items.length }))}</p>
        <ul class="ge-use">${items.map((u, i) => `<li><div class="ge-use__row"><span>${esc(u.label)}</span>
          ${u.url ? `<a class="adm-btn adm-btn--small" href="${esc(u.url)}" target="_blank" rel="noopener">${ico('arrow-square-out')} ${esc(t('Öffnen'))}</a>` : ''}
          ${u.preview ? `<button type="button" class="adm-btn adm-btn--small" data-prev="${i}" aria-expanded="false">${ico('eye')} ${esc(t('Vorschau'))}</button>` : ''}</div>
          ${u.preview ? `<div class="ge-use__prev" data-prevbox="${i}" hidden></div>` : ''}</li>`).join('')}</ul>`
        : `<p>${esc(t('Die Grafik ist noch nirgends eingebunden.'))}</p>`,
      onOpen: d => {
        d.querySelectorAll('[data-prev]').forEach(b => b.addEventListener('click', () => {
          const box = d.querySelector(`[data-prevbox="${b.dataset.prev}"]`);
          const open = box.hidden;
          box.hidden = !open;
          b.setAttribute('aria-expanded', String(open));
          if (open && !box.firstChild) {
            const u = items[+b.dataset.prev];
            box.innerHTML = `<div class="ge-use__frame"><iframe src="${esc(u.preview)}" title="${esc(t('Vorschau: {name}', { name: u.label }))}" loading="lazy" tabindex="-1"></iframe></div>`;
          }
        }));
      },
    });
  }
}
