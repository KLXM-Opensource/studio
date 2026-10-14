/**
 * Live-Aktualisierung (Core\Live): eine EventSource für alle [data-live]-Elemente der Seite.
 * Ereignis „v“ = {abo: version}. Neuere Version → Element mit data-live-src neu laden ([data-live-list] tauschen, neue
 * [data-live-id] markieren und ansagen), sonst nur „cms:live“ am Element auslösen (für eigenes JavaScript von Kits/Erweiterungen).
 * Anhalten: [data-live-pause] (Änderungen werden gesammelt und beim Fortsetzen geladen). Ausgeblendeter Tab: Verbindung zu.
 *
 * Neu laden auf Aktion (Core\Live::region): [data-live-region] mit data-live-src. Links [data-live-nav] und GET-Formulare
 * [data-live-nav] holen den Block mit ihren Parametern neu und tauschen ihn aus (pushState); Eingabefelder [data-live-input]
 * nach einer Pause (data-live-delay, Standard 600 ms; ab data-live-min Zeichen, Standard 2; replaceState). Zurück/Vor lädt passend nach. Fokus bleibt im Feld bzw.
 * geht an den Block, Ansage über eine Statuszeile (aria-live). Fehler → normaler Seitenaufruf.
 */
(() => {
  const me = document.currentScript;
  const endpoint = me?.dataset.liveEndpoint || '/api/live';
  const els = () => [...document.querySelectorAll('[data-live]')];
  cmsLiveRegions(me);
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

// ---------------------------------------------------------------- Neu laden auf Aktion (Core\Live::region)
function cmsLiveRegions(me) {
  if (!document.querySelector('[data-live-region]') || !window.fetch || !history.pushState) return;
  let ctrl = null, timer = 0, sr = null, lastSearch = location.search;
  const say = (msg) => {
    if (!sr) { sr = document.createElement('div'); sr.className = 'cms-live-sr'; sr.setAttribute('role', 'status'); sr.setAttribute('aria-live', 'polite'); document.body.append(sr); }
    sr.textContent = '';
    setTimeout(() => { sr.textContent = msg; }, 60);
  };
  const regionOf = (el) => el.closest('[data-live-region]');
  const load = async (region, target, how, focusRegion) => {
    const u = new URL(target, location.href);
    if (u.origin !== location.origin) { location.href = u.href; return; }
    const src = new URL(region.dataset.liveSrc, location.href);
    u.searchParams.forEach((v, k) => { if (k !== 'p' && k !== 'b') src.searchParams.append(k, v); });
    ctrl?.abort();
    ctrl = new AbortController();
    region.setAttribute('aria-busy', 'true');
    try {
      const r = await fetch(src, { headers: { Accept: 'application/json' }, signal: ctrl.signal });
      const j = r.ok ? await r.json() : null;
      const tpl = document.createElement('template');
      tpl.innerHTML = j && typeof j.html === 'string' ? j.html : '';
      const fresh = tpl.content.querySelector('[data-live-region="' + CSS.escape(region.dataset.liveRegion) + '"]');
      if (!fresh) { location.href = u.href; return; }
      const act = document.activeElement, keep = region.contains(act) && act.id ? act.id : '';
      const sel = keep && typeof act.selectionStart === 'number' ? [act.selectionStart, act.selectionEnd] : null;
      region.replaceWith(fresh);
      const url = u.pathname + u.search + u.hash;
      if (how === 'push') history.pushState({ cmsLive: 1 }, '', url);
      else if (how === 'replace') history.replaceState({ cmsLive: 1 }, '', url);
      lastSearch = location.search;
      // Einblenden beim Scrollen der Kits ([data-reveal] → is-in): Nachgeladenes sofort zeigen, sonst bliebe es unsichtbar
      [fresh, ...fresh.querySelectorAll('[data-reveal]')].forEach((x) => { if (x.matches('[data-reveal]')) x.classList.add('is-in'); });
      fresh.dispatchEvent(new CustomEvent('cms:content-replaced', { bubbles: true }));
      const again = keep && document.getElementById(keep);
      if (again) { again.focus({ preventScroll: true }); if (sel) try { again.setSelectionRange(sel[0], sel[1]); } catch { /* kein Textfeld */ } }
      else if (focusRegion) {
        fresh.setAttribute('tabindex', '-1');
        fresh.focus({ preventScroll: true });
        if (fresh.getBoundingClientRect().top < 0) fresh.scrollIntoView({ block: 'start' });
      }
      say(fresh.querySelector('[data-live-count]')?.textContent.trim() || me?.dataset.lUpdated || '');
    } catch (e) {
      if (e.name !== 'AbortError') location.href = u.href;
    } finally {
      region.removeAttribute('aria-busy');
    }
  };
  const formUrl = (f) => {
    const u = new URL(f.getAttribute('action') || location.href, location.href);
    u.search = new URLSearchParams([...new FormData(f)].filter(([, v]) => typeof v === 'string' && v !== '')).toString();
    return u.href;
  };
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-live-nav]');
    const region = a && regionOf(a);
    if (!region || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || a.target === '_blank') return;
    e.preventDefault();
    load(region, a.href, 'push', true);
  });
  document.addEventListener('submit', (e) => {
    const f = e.target.closest('form[data-live-nav]');
    const region = f && regionOf(f);
    if (!region || (f.method || 'get').toLowerCase() !== 'get') return;
    e.preventDefault();
    clearTimeout(timer);
    load(region, formUrl(f), 'push', false);
  });
  document.addEventListener('input', (e) => {
    const inp = e.target.closest?.('[data-live-input]');
    const f = inp?.form, region = inp && regionOf(inp);
    if (!f || !region || !f.matches('[data-live-nav]')) return;
    clearTimeout(timer);
    // Erst tippen lassen: Pause (data-live-delay, Standard 600 ms), mindestens 2 Zeichen – Leeren lädt sofort die volle Liste
    const v = inp.value.trim();
    if (v.length > 0 && v.length < (+inp.dataset.liveMin || 2)) return;
    timer = setTimeout(() => load(regionOf(document.getElementById(inp.id) || inp) || region, formUrl(f), 'replace', false), v === '' ? 0 : (+inp.dataset.liveDelay || 600));
  });
  addEventListener('popstate', () => {
    if (location.search === lastSearch) return;   // nur Sprungmarke geändert
    document.querySelectorAll('[data-live-region]').forEach((region) => load(region, location.href, 'none', false));
  });
}
