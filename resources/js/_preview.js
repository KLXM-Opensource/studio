/*
 * Live-Vorschau-Panel (Einstellungen, Design): rechts eine skalierte Website im iframe (srcdoc),
 * Desktop/Mobil, verzögertes Neuladen bei Eingaben. Markup: .st-pv mit [data-st-frame], [data-st-stage], [data-st-state],
 * Umschalter [data-st-pvtoggle], Geräte [data-st-device]. Der Endpunkt liefert {ok, html} (+ beliebige Zusatzdaten).
 */
import { t } from './_i18n.js';

const $ = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => [...c.querySelectorAll(s)];

export function store(prefix) {
  return {
    // Schlüssel klxm-studio-{prefix}-*; mycms-{prefix}-* (historisch) wird noch gelesen und beim Schreiben entfernt
    get(k, d) { try { const v = localStorage.getItem(`klxm-studio-${prefix}-` + k) ?? localStorage.getItem(`mycms-${prefix}-` + k); return v === null ? d : JSON.parse(v); } catch { return d; } },
    set(k, v) { try { localStorage.setItem(`klxm-studio-${prefix}-` + k, JSON.stringify(v)); localStorage.removeItem(`mycms-${prefix}-` + k); } catch {} },
  };
}

/**
 * @param {object} o
 * @param {Element} o.wrap      Container mit .st-pv
 * @param {string}  o.endpoint  POST-Adresse
 * @param {() => FormData} o.body  Formulardaten je Aktualisierung
 * @param {string} [o.key]      Präfix für gespeicherte Vorlieben (offen, Gerät)
 * @param {(json) => void} [o.onData]  Zusatzdaten der Antwort
 * @param {boolean} [o.openDefault]
 */
export function livePreview({ wrap, endpoint, body, key = 'st', onData, openDefault }) {
  const pane = $('.st-pv', wrap), frame = $('[data-st-frame]', wrap), stage = $('[data-st-stage]', wrap);
  const state = $('[data-st-state]', wrap), toggle = $('[data-st-pvtoggle]', wrap) || $('[data-st-pvtoggle]');
  const st = store(key);
  let timer = null, seq = 0, device = st.get('device', 'desktop');

  const fit = () => {
    const w = device === 'mobile' ? 390 : 1280;
    const avail = stage.clientWidth || w;
    const scale = Math.min(1, avail / w);
    // Höhe wie ein echter Bildschirm (Desktop 1280 × 800, Mobil 390 × 844), höchstens Fensterhöhe – im Vollbild-Blatt per CSS
    stage.style.height = pane.classList.contains('is-sheet') ? ''
      : Math.max(260, Math.min(innerHeight - 170, Math.round((device === 'mobile' ? 844 : 800) * scale))) + 'px';
    frame.style.width = w + 'px';
    frame.style.height = Math.round((stage.clientHeight || 700) / scale) + 'px';
    frame.style.transform = `scale(${scale})`;
    frame.style.left = Math.max(0, Math.round((avail - w * scale) / 2)) + 'px';
    $$('[data-st-device]', pane).forEach(b => b.setAttribute('aria-pressed', String(b.dataset.stDevice === device)));
  };

  async function refresh() {
    if (pane.hidden) return;
    const my = ++seq;
    state.textContent = t('Aktualisiere …');
    try {
      const r = await fetch(endpoint, { method: 'POST', body: body(), credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const j = await r.json();
      if (my !== seq) return;
      if (!j.ok) { state.textContent = j.error || t('Vorschau nicht möglich.'); return; }
      const y = frame.contentWindow?.scrollY || 0;
      frame.onload = () => { try { frame.contentWindow.scrollTo(0, y); } catch {} };
      frame.srcdoc = j.html;
      state.textContent = '';
      onData?.(j);
    } catch { if (my === seq) state.textContent = t('Vorschau nicht möglich.'); }
  }
  const later = (ms = 700) => { if (pane.hidden) return; clearTimeout(timer); timer = setTimeout(refresh, ms); };

  // Schmale Bildschirme (≤ 900 px): Vorschau als Vollbild-Blatt mit „Schließen“, Esc, Fokus und `inert` für den Rest
  const narrow = matchMedia('(max-width:900px)');
  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'adm-btn adm-btn--small st-pv__close';
  close.textContent = t('Schließen');
  $('.st-pv__bar', pane)?.append(close);
  let inerted = [];
  const sheet = on => {
    on = on && narrow.matches;
    document.documentElement.classList.toggle('adm-lock', on);
    pane.classList.toggle('is-sheet', on);
    inerted.forEach(el => { el.inert = false; });
    inerted = [];
    if (on) {
      for (let el = pane; el && el !== document.body; el = el.parentElement)
        for (const sib of el.parentElement.children) if (sib !== el && sib.tagName !== 'SCRIPT' && !sib.inert) { sib.inert = true; inerted.push(sib); }
      pane.setAttribute('role', 'dialog');
      pane.setAttribute('aria-modal', 'true');
      close.focus();
    } else { pane.removeAttribute('role'); pane.removeAttribute('aria-modal'); }
  };
  const open = (on, { remember = true } = {}) => {
    const was = !pane.hidden;
    pane.hidden = !on;
    wrap.classList.toggle('has-preview', on);
    toggle?.setAttribute('aria-pressed', String(on));
    if (remember && !narrow.matches) st.set('open', on);
    sheet(on);
    if (on) { fit(); refresh(); }
    else if (was && narrow.matches) toggle?.focus();
  };
  toggle?.addEventListener('click', () => open(pane.hidden));
  close.addEventListener('click', () => open(false, { remember: false }));
  pane.addEventListener('keydown', e => { if (e.key === 'Escape' && pane.classList.contains('is-sheet')) { e.preventDefault(); open(false, { remember: false }); } });
  narrow.addEventListener('change', () => { if (!pane.hidden) narrow.matches ? open(false, { remember: false }) : sheet(false); });
  $$('[data-st-device]', pane).forEach(b => b.addEventListener('click', () => { device = b.dataset.stDevice; st.set('device', device); fit(); }));
  addEventListener('resize', () => { if (!pane.hidden) fit(); });
  if (!narrow.matches && st.get('open', openDefault ?? innerWidth >= 1500)) open(true);

  return { refresh, later, open, isOpen: () => !pane.hidden };
}
