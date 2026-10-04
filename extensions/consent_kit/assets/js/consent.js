/*!
 * KLXM Studio – Consent-Kit (Kern) · MIT
 * Teile portiert aus FriendsOfREDAXO/consent_kit (MIT, © KLXM Crossmedia GmbH)
 *
 * Läuft nur auf Websites mit aktivem, einwilligungspflichtigem Dienst (sonst bindet der Server nichts ein).
 * Klein gehalten: Zustand, Laden der Dienste, Consent Mode, GPC, Schnittstelle für Themes. Die Oberfläche
 * (Hinweis, Einstellungen, Platzhalter) liegt in consent-ui.mjs und wird nur bei Bedarf nachgeladen.
 * CSP: kein Inline-Code – Dienste kommen als Dateien von der eigenen Domain (/consent/js/…) oder als externe Skripte,
 * deren Hosts der Server erst nach Einwilligung in die CSP aufnimmt (deshalb ggf. einmal neu laden).
 */
(() => {
  const d = document, w = window;
  const node = d.getElementById('cms-consent-config');
  if (!node || w.cmsConsent) return;
  let cfg;
  try { cfg = JSON.parse(node.textContent || '{}'); } catch { return; }

  const services = new Map(), optional = [];
  for (const g of cfg.groups) for (const s of g.services) {
    s.required = g.required; s.group = g.key;
    services.set(s.key, s);
    if (!g.required) optional.push(s);
  }
  const gpc = cfg.gpc !== 'ignore' && navigator.globalPrivacyControl === true;
  const sleep = ms => new Promise(r => setTimeout(r, ms));
  const store = (fn, fb) => { try { return fn(); } catch { return fb; } };

  const readCookie = () => {
    const m = d.cookie.match(new RegExp('(?:^|; )' + cfg.cookie + '=([^;]*)'));
    if (!m) return null;
    try {
      const s = JSON.parse(decodeURIComponent(m[1]));
      return s && typeof s.id === 'string' && s.e === cfg.epoch ? { a: {}, r: {}, ...s } : null;
    } catch { return null; }
  };
  let state = cfg.preview ? null : readCookie();

  const has = k => {
    const s = services.get(k);
    if (!s) return false;
    return s.required || (!!state && state.a[k] === s.h);
  };
  const accepted = () => optional.filter(s => has(s.key)).map(s => s.key);
  const rejected = () => optional.filter(s => !has(s.key)).map(s => s.key);
  /** Dienste, zu denen (in ihrer aktuellen Fassung) keine Entscheidung vorliegt */
  const needsDecision = () => optional.some(s => !state || (state.a[s.key] !== s.h && state.r[s.key] !== s.h));
  /** Einwilligungen beim Laden der Seite = Stand der CSP dieser Antwort */
  const atLoad = new Set(accepted());

  // ---------------------------------------------------------------- Google Consent Mode v2 (Basic Mode)
  const gcm = cfg.gcm;
  if (gcm) {
    w.dataLayer = w.dataLayer || [];
    w.gtag = w.gtag || function () { w.dataLayer.push(arguments); };
    const def = { security_storage: 'granted', wait_for_update: gcm.wait };
    for (const k of ['ad_storage', 'ad_user_data', 'ad_personalization', 'analytics_storage', 'functionality_storage', 'personalization_storage']) def[k] = 'denied';
    w.gtag('consent', 'default', def);
    if (gcm.redaction) w.gtag('set', 'ads_data_redaction', true);
    if (gcm.passthrough) w.gtag('set', 'url_passthrough', true);
  }
  const gcmUpdate = () => {
    if (!gcm || !state) return;
    const u = {};
    for (const s of optional) for (const sig of s.gcm || []) {
      if (has(s.key)) u[sig] = 'granted';
      else if (!(sig in u)) u[sig] = 'denied';
    }
    if (Object.keys(u).length) w.gtag('consent', 'update', u);
  };

  // ---------------------------------------------------------------- Laden
  const codeUrl = (k, part) => cfg.code + k + '.' + part + '.js?l=' + encodeURIComponent(cfg.lang) + '&v=' + ((services.get(k) || {}).v || cfg.v);
  /** Script einfügen; wartet auf onload, außer bei async (Reihenfolge wie im Code des Anbieters) */
  const script = (src, attrs = {}, where = 'head') => new Promise(res => {
    const s = d.createElement('script');
    for (const [n, v] of Object.entries(attrs)) s.setAttribute(n, v);
    if (!('async' in attrs)) s.async = false;
    s.onload = s.onerror = () => res();
    s.src = src;
    (where === 'body' ? d.body : d.head).append(s);
    if ('async' in attrs) res();
  });
  const run = async s => {
    for (const where of ['head', 'body']) for (const st of s[where] || []) {
      if ('c' in st) await script(codeUrl(s.key, st.c), {}, where);
      else if (st.s) await script(st.s, st.a || {}, where);
      else if (st.h) {
        const t = d.createElement('template');
        t.innerHTML = st.h;
        t.content.querySelectorAll('script').forEach(x => x.remove());
        (where === 'body' ? d.body : d.head).append(t.content);
      }
    }
    if (s.acc) await script(codeUrl(s.key, 'a'));
  };
  const loaded = new Set();
  let queue = Promise.resolve();
  const apply = () => {
    gcmUpdate();
    for (const s of services.values()) {
      if (s.required && !s.head?.length && !s.body?.length && !s.acc) continue;
      if (!has(s.key) || loaded.has(s.key)) continue;
      loaded.add(s.key);
      queue = queue.then(() => run(s)).catch(e => console.error('[consent-kit] ' + s.key, e));
    }
    activate();
  };
  /** <script type="text/plain" data-consent="dienst" data-src="/datei.js"> – inline geht unter CSP nicht (nur data-src) */
  const activate = (root = d) => {
    for (const b of root.querySelectorAll('script[type="text/plain"][data-consent]')) {
      if (!has(b.dataset.consent)) continue;
      if (!b.dataset.src) { console.warn('[consent-kit] Inline-Script ohne data-src wird wegen der CSP nicht ausgeführt:', b); b.removeAttribute('data-consent'); continue; }
      const s = d.createElement('script');
      for (const a of b.attributes) if (!['type', 'data-consent', 'data-src', 'data-type'].includes(a.name)) s.setAttribute(a.name, a.value);
      if (b.dataset.type) s.type = b.dataset.type;
      if (!b.hasAttribute('async')) s.async = false;
      s.src = b.dataset.src;
      b.replaceWith(s);
    }
  };

  const emit = (name, action = null) => {
    const detail = { accepted: accepted(), rejected: rejected(), action };
    d.dispatchEvent(new CustomEvent('consentkit:' + name, { detail }));
    // Themes (2-Klick-Videos) erst nach dem aktuellen Klick informieren
    if (name === 'change') setTimeout(() => d.dispatchEvent(new CustomEvent('cms:consent', { detail })));
    if (gcm && Array.isArray(w.dataLayer)) w.dataLayer.push({ event: 'consentkit_' + name, consentkit: detail });
  };

  // ---------------------------------------------------------------- Oberfläche (nachgeladen)
  let uiP = null;
  const ui = () => (uiP ||= import(cfg.ui).then(m => m.mount(api)).catch(e => { console.error('[consent-kit] ui', e); uiP = null; return null; }));
  const dismissed = () => store(() => sessionStorage.getItem(cfg.dismissKey) === String(cfg.rev), false);
  const autoRejected = () => gpc && cfg.gpc === 'reject' && !state;

  const embedKey = p => (cfg.embeds || {})[p] || null;
  const api = {
    has, accepted,
    /** Einwilligung für einzelne Dienste erteilen wie „… immer erlauben“ am Platzhalter (Protokoll: embed) – für eigene
     *  2-Klick-Lösungen ohne <consent-embed>. Unbekannte und notwendige Schlüssel werden ignoriert; false, wenn keiner bleibt. */
    accept(keys) {
      const add = [].concat(keys).filter(k => optional.some(s => s.key === k));
      if (!add.length) return false;
      if (add.some(k => !has(k))) ui().then(u => u && u.decide([...new Set([...accepted(), ...add])], 'embed'));
      return true;
    },
    open: (view = 'settings') => ui().then(u => u && u.open(view)),
    withdraw: () => ui().then(u => u && u.withdraw()),
    reset: () => ui().then(u => u && u.reset()),
    onChange: fn => d.addEventListener('consentkit:change', e => fn(e.detail)),
    /** Themes (2-Klick-Videos): true/false = verwaltet, null = Anbieter hat hier keinen Dienst (Theme entscheidet selbst) */
    embed: p => { const k = embedKey(p); return k ? has(k) : null; },
    allowEmbed(p, on) {
      const k = embedKey(p);
      if (!k) return false;
      ui().then(u => {
        if (!u) return;
        const set = new Set(accepted());
        on ? set.add(k) : set.delete(k);
        u.decide([...set], on ? 'embed' : 'custom');
      });
      return true;
    },
    // intern für consent-ui.mjs
    _: { cfg, services, optional, needsDecision, dismissed, gpc, autoRejected, apply, emit, script, codeUrl, store, loaded, atLoad, sleep,
      state: () => state, setState: v => { state = v; },
      dismiss: () => { if (needsDecision()) store(() => sessionStorage.setItem(cfg.dismissKey, String(cfg.rev))); } },
  };
  w.cmsConsent = api;
  w.ConsentKit = w.ConsentKit || api;   // gleiche Schnittstelle wie das REDAXO-AddOn

  // Einstellungen öffnen: Link im Fußbereich (#cookie-einstellungen), data-consent-open, Klassen des AddOns
  d.addEventListener('click', e => {
    const o = e.target.closest?.('a[href="#cookie-einstellungen"], a[href="#cookie-settings"], a[href="#consent-kit"], [data-consent-open], [data-consent-kit-open], .consent-kit-open');
    if (!o) return;
    e.preventDefault();
    api.open('settings');
  });

  /* Hinweis beim Seitenaufruf (openMode): never = nur auf Zuruf; on_demand = nur, wenn auf der Seite ein <consent-embed>
     eines bekannten, noch nicht erlaubten Dienstes steht – es sei denn, ein optionaler Dienst bringt eigenen Code mit
     (head/body/js_accept/Ereignisse), der sonst nie starten würde. Vorschau: nie unterdrücken. */
  const loadsOnConsent = s => !!((s.head && s.head.length) || (s.body && s.body.length) || s.acc);
  const blockedEmbed = () => [...d.querySelectorAll('consent-embed[service]')].some(el => { const k = el.getAttribute('service') || ''; return services.has(k) && !has(k); });
  const suppressed = () => {
    const mode = cfg.openMode;
    if (cfg.preview || (mode !== 'on_demand' && mode !== 'never')) return false;   // unbekannt = always (sichere Seite)
    if (mode === 'never') return true;
    if (optional.some(loadsOnConsent)) return false;
    return !blockedEmbed();
  };

  const start = async () => {
    if (cfg.def && !cfg.preview) await script(codeUrl('_', 'd'));
    apply();
    emit('ready');
    new MutationObserver(() => activate()).observe(d.body, { childList: true, subtree: true });
    const need = optional.length > 0 && needsDecision() && !autoRejected() && (!dismissed() || cfg.preview) && !suppressed();
    if (need || cfg.trigger || d.querySelector('consent-embed')) {
      const u = await ui();
      if (u && need) u.open('banner');
    }
  };
  d.readyState === 'loading' ? d.addEventListener('DOMContentLoaded', start) : start();
})();
