/**
 * Live-Aktualisierung (Core\Live): eine EventSource für alle [data-live]-Elemente der Seite.
 * Ereignis „v“ = {abo: version}. Neuere Version → Element mit data-live-src neu laden ([data-live-list] tauschen, neue
 * [data-live-id] markieren und ansagen), sonst nur „cms:live“ am Element auslösen (für eigenes JavaScript von Kits/Erweiterungen).
 * Anhalten: [data-live-pause] (Änderungen werden gesammelt und beim Fortsetzen geladen). Ausgeblendeter Tab: Verbindung zu.
 */
(() => {
  const me = document.currentScript;
  const endpoint = me?.dataset.liveEndpoint || '/api/live';
  const els = () => [...document.querySelectorAll('[data-live]')];
  if (!els().length || !('EventSource' in window)) return;
  let es = null, hideTimer = null;

  const announce = (el, n) => {
    const s = el.querySelector('[data-live-status]');
    if (!s || n < 1) return;
    s.textContent = n === 1 ? (me?.dataset.lNew1 || '1') : (me?.dataset.lNew || '{n}').replace('{n}', n);
    setTimeout(() => { if (s.textContent) s.textContent = ''; }, 6000);
  };

  const refresh = async (el) => {
    if (el.dataset.liveBusy) { el.dataset.liveAgain = '1'; return; }
    el.dataset.liveBusy = '1';
    try {
      const r = await fetch(el.dataset.liveSrc, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      const j = r.ok ? await r.json() : null;
      if (!j || typeof j.html !== 'string') return;
      const tpl = document.createElement('template');
      tpl.innerHTML = j.html;
      const fresh = tpl.content.querySelector('[data-live-list]'), cur = el.querySelector('[data-live-list]');
      if (!fresh || !cur) return;
      const seen = new Set([...cur.querySelectorAll('[data-live-id]')].map((x) => x.dataset.liveId));
      let n = 0;
      fresh.querySelectorAll('[data-live-id]').forEach((x) => { if (!seen.has(x.dataset.liveId)) { x.classList.add('is-live-new'); n++; } });
      cur.replaceChildren(...fresh.childNodes);
      [...fresh.attributes].forEach((a) => { if (a.name !== 'data-live-list') cur.setAttribute(a.name, a.value); });
      announce(el, n);
      el.dispatchEvent(new CustomEvent('cms:live-updated', { bubbles: true, detail: { added: n } }));
    } catch { /* nächste Version versucht es erneut */ } finally {
      delete el.dataset.liveBusy;
      if (el.dataset.liveAgain) { delete el.dataset.liveAgain; refresh(el); }
    }
  };

  const onVersion = (el, v) => {
    if (v <= (+el.dataset.liveV || 0)) return;
    el.dataset.liveV = v;
    el.dispatchEvent(new CustomEvent('cms:live', { bubbles: true, detail: { version: v } }));
    if (!el.dataset.liveSrc) return;
    if (el.dataset.livePaused) { el.dataset.livePending = '1'; return; }
    refresh(el);
  };

  const connect = () => {
    if (es) return;
    const tokens = [...new Set(els().map((el) => el.dataset.live))];
    es = new EventSource(endpoint + '?' + tokens.map((t) => 't=' + encodeURIComponent(t)).join('&'));
    es.addEventListener('v', (e) => {
      let d = {};
      try { d = JSON.parse(e.data); } catch { return; }
      els().forEach((el) => { if (el.dataset.live in d) onVersion(el, +d[el.dataset.live]); });
    });
  };
  const disconnect = () => { if (es) { es.close(); es = null; } };

  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-live-pause]');
    const el = b?.closest('[data-live]');
    if (!el) return;
    const paused = !el.dataset.livePaused;
    if (paused) el.dataset.livePaused = '1'; else delete el.dataset.livePaused;
    b.setAttribute('aria-pressed', String(paused));
    b.textContent = paused ? (b.dataset.lPlayShort || '▶') : (b.dataset.lPauseShort || '❚❚');
    b.setAttribute('aria-label', paused ? b.dataset.lPlay : b.dataset.lPause);
    el.classList.toggle('is-live-paused', paused);
    if (!paused && el.dataset.livePending) { delete el.dataset.livePending; refresh(el); }
  });
  document.querySelectorAll('[data-live-pause]').forEach((b) => {
    b.dataset.lPauseShort = b.textContent.trim();
    b.dataset.lPlayShort = b.dataset.lPlayText || b.textContent.trim();
    b.setAttribute('aria-label', b.dataset.lPause);
  });
  document.addEventListener('visibilitychange', () => {
    clearTimeout(hideTimer);
    if (document.hidden) hideTimer = setTimeout(disconnect, 30000); else connect();
  });
  connect();
})();
