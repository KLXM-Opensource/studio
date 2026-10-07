/*
 * Push-Abos auf der Website (Core\Push\Visitor):
 *  - [data-cms-push]       Block „Benachrichtigungen abonnieren“ bzw. push_subscribe(): ein Kanal (Knopf an/aus) oder mehrere
 *                          Kanäle (Kästchen; „Auswahl speichern“, „Alle abbestellen“) – zeigt das bestehende Abo dieses Browsers
 *  - [data-cms-push-site]  Banner und schwebende Glocke mit Auswahl-Fenster (Einstellung „Auf der Website“)
 * Ablauf: Erklärung steht da → Klick → Abfrage des Browsers → Abo an /api/push/subscribe (Zustelladresse + Kanäle + Sprache).
 * Kein Cookie. Nach dem Abo merkt sich der Browser die Kanäle (localStorage, für einen stillen Neu-Abschluss nach einem
 * Schlüsselwechsel), „Nein, danke“ beim Banner ebenso. Ohne Unterstützung (kein https, alter Browser, iPhone ohne installierte App)
 * erscheint ein Hinweis statt des Knopfs; Banner und Glocke bleiben dann aus.
 */
import { pushSupported, iosNeedsInstall, pushExisting, pushSubscribe, pushStale, pushPost } from './_pushclient.js';

const d = document;
const LS = 'cms-push-topics';
const LS_BANNER = 'cms-push-banner';
const store = {
  get(k, def) { try { const v = localStorage.getItem(k); return v === null ? def : JSON.parse(v); } catch { return def; } },
  set(k, v) { try { if (v === null || (Array.isArray(v) && !v.length)) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); } catch { /* privat */ } },
};
const remembered = () => (store.get(LS, []) || []).filter(t => typeof t === 'string');
const listeners = new Set();
let current = null;   // abonnierte Kanäle dieses Browsers (null = unbekannt)
const setCurrent = list => { current = list; store.set(LS, list); listeners.forEach(fn => fn(list)); };

/** Zustand einmal je Seite erfragen (alle Formulare und die Glocke teilen ihn) */
let probe = null;
function state(cfg) {
  probe ??= (async () => {
    try {
      const sub = Notification.permission === 'granted' ? await pushExisting(cfg.scope) : null;
      if (sub && pushStale(sub, cfg.key)) {
        // Die Website hat neue Schlüssel: gemerkte Kanäle still neu abonnieren (Erlaubnis besteht schon – keine Abfrage)
        const topics = remembered();
        await sub.unsubscribe().catch(() => {});
        if (topics.length) {
          const fresh = await pushSubscribe(cfg.sw, cfg.scope, cfg.key);
          const r = await pushPost(cfg.api + '/subscribe', { subscription: fresh.toJSON(), topics, lang: cfg.lang, token: cfg.token });
          return r.ok ? (r.topics || topics) : [];
        }
        return [];
      }
      if (!sub) return [];
      const j = sub.toJSON();
      const r = await pushPost(cfg.api + '/status', { endpoint: j.endpoint, auth: j.keys?.auth, token: cfg.token });
      return Array.isArray(r.topics) ? r.topics : [];
    } catch { return []; }
  })().then(list => { setCurrent(list); return list; });
  return probe;
}

async function save(cfg, topics, offered) {
  let sub = await pushSubscribe(cfg.sw, cfg.scope, cfg.key);
  const send = s => pushPost(cfg.api + '/subscribe', { subscription: s.toJSON(), topics, offered, lang: cfg.lang, token: cfg.token });
  let r = await send(sub);
  if (r.status === 409) {
    // Endpunkt mit anderem Schlüssel gespeichert → neu abonnieren
    await sub.unsubscribe().catch(() => {});
    sub = await pushSubscribe(cfg.sw, cfg.scope, cfg.key);
    r = await send(sub);
  }
  if (!r.ok) throw Object.assign(new Error('save'), { userMessage: r.error });
  if (r.state === 'deleted') await sub.unsubscribe().catch(() => {});
  return Array.isArray(r.topics) ? r.topics : topics;
}

async function drop(cfg, topics) {
  const sub = await pushExisting(cfg.scope);
  if (!sub) return [];
  const j = sub.toJSON();
  const r = await pushPost(cfg.api + '/unsubscribe', { endpoint: j.endpoint, auth: j.keys?.auth, topics, token: cfg.token });
  if (r.state === 'deleted') await sub.unsubscribe().catch(() => {});
  return Array.isArray(r.topics) ? r.topics : [];
}

function init(el) {
  const T = (() => { try { return JSON.parse(el.dataset.texts || '{}'); } catch { return {}; } })();
  const cfg = { ...el.dataset };
  const btn = el.querySelector('[data-push-btn]');
  const off = el.querySelector('[data-push-off]');
  const out = el.querySelector('[data-push-status]');
  const boxes = [...el.querySelectorAll('[data-push-ch]')];
  const offered = (() => { try { return JSON.parse(cfg.topics || '[]'); } catch { return [cfg.topic]; } })();
  const multi = boxes.length > 0;
  const say = (msg, st = '') => { if (out) out.textContent = msg || ''; el.dataset.state = st; };
  if (!btn) return;
  if (!window.isSecureContext) return say(T.insecure, 'na');
  if (iosNeedsInstall()) { boxes.forEach(b => { b.disabled = true; }); return say(T.ios, 'ios'); }
  if (!pushSupported()) { boxes.forEach(b => { b.disabled = true; }); return say(T.unsupported, 'na'); }

  let mine = [];   // abonnierte Kanäle aus diesem Angebot
  const chosen = () => boxes.filter(b => b.checked).map(b => b.value);
  const render = () => {
    btn.hidden = false;
    if (multi) {
      btn.textContent = mine.length ? T.save : T.subscribe;
      btn.className = el.dataset.btn + ' cms-push__btn';
      if (off) off.hidden = !mine.length;
      const same = chosen().length === mine.length && chosen().every(t => mine.includes(t));
      btn.disabled = !chosen().length && !mine.length || (mine.length > 0 && same);
    } else {
      const on = mine.length > 0;
      btn.textContent = on ? T.unsubscribe : T.subscribe;
      btn.className = (on ? el.dataset.btnOff : el.dataset.btn) + ' cms-push__btn';
      btn.setAttribute('aria-pressed', String(on));
    }
    el.classList.toggle('is-on', mine.length > 0);
  };
  const apply = list => {
    mine = offered.filter(t => list.includes(t));
    boxes.forEach(b => { b.checked = mine.includes(b.value); });
    render();
  };
  listeners.add(list => { apply(list); if (mine.length && !el.dataset.state) say(T.on, 'on'); });
  boxes.forEach(b => b.addEventListener('change', () => { render(); if (el.dataset.state === 'error') say(''); }));
  render();
  state(cfg).then(list => {
    apply(list);
    if (mine.length) say(T.on, 'on');
    else if (Notification.permission === 'denied') say(T.denied, 'denied');
  });

  const run = async fn => {
    btn.disabled = true;
    if (off) off.disabled = true;
    say(T.busy, 'busy');
    try { await fn(); } catch (e) {
      say(e?.message === 'denied' ? T.denied : (e?.userMessage || T.error), e?.message === 'denied' ? 'denied' : 'error');
    }
    if (off) off.disabled = false;
    render();
  };
  btn.addEventListener('click', () => run(async () => {
    if (!multi && mine.length) {
      setCurrent(await drop(cfg, offered));
      say(T.off, 'off');
      return;
    }
    const pick = multi ? chosen() : offered;
    if (!pick.length && !mine.length) { say(T.pick, 'error'); return; }
    if (Notification.permission === 'default') say(T.asking, 'busy');
    const list = pick.length ? await save(cfg, pick, multi ? offered : undefined) : await drop(cfg, offered);
    setCurrent(list);
    say(pick.length ? (multi ? T.saved : T.on) : T.off, pick.length ? 'on' : 'off');
    el.dispatchEvent(new CustomEvent('cms:push-saved', { bubbles: true, detail: { topics: list } }));
  }));
  off?.addEventListener('click', () => run(async () => {
    setCurrent(await drop(cfg, offered));
    say(T.off, 'off');
  }));
}

/** Banner und Glocke */
function site(root) {
  const panelWidget = root.querySelector('[data-cms-push]');
  if (!panelWidget || !window.isSecureContext || !pushSupported()) return;   // ohne Unterstützung bleibt alles aus
  const cfg = { ...panelWidget.dataset };
  const banner = root.querySelector('[data-push-banner]');
  const bell = root.querySelector('[data-push-bell]');
  const panel = root.querySelector('[data-push-panel]');
  const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  let opener = null;
  root.hidden = false;
  // Besucher-Chat auf derselben Seite: Glocke darüber setzen
  const chat = d.querySelector('[data-cms-chat]');
  if (chat && (chat.dataset.pos === 'left') === (root.dataset.bellPos === 'left')) root.classList.add('is-lift');

  const open = from => {
    opener = from || null;
    if (banner && !banner.hidden) banner.hidden = true;   // Banner weicht dem Auswahl-Fenster (ohne „Nein, danke“ zu merken)
    panel.hidden = false;
    bell?.setAttribute('aria-expanded', 'true');
    root.classList.add('is-open');
    (panel.querySelector('[data-push-ch]:not([disabled])') || panel.querySelector('[data-push-btn]:not([hidden])') || panel.querySelector('[data-push-close]')).focus();
  };
  const close = () => {
    if (panel.hidden) return;
    panel.hidden = true;
    bell?.setAttribute('aria-expanded', 'false');
    root.classList.remove('is-open');
    (opener && !opener.hidden ? opener : bell)?.focus();
  };
  panel.querySelector('[data-push-close]')?.addEventListener('click', close);
  d.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden) { e.preventDefault(); close(); } });
  d.addEventListener('click', e => { if (!panel.hidden && !panel.contains(e.target) && !e.target.closest('[data-push-bell],[data-push-open]')) close(); });
  if (bell) {
    bell.hidden = false;
    bell.addEventListener('click', () => (panel.hidden ? open(bell) : close()));
  }
  const hideBanner = remember => {
    if (!banner || banner.hidden) return;
    banner.hidden = true;
    if (remember) store.set(LS_BANNER, Date.now());
  };
  banner?.querySelector('[data-push-dismiss]')?.addEventListener('click', () => { hideBanner(true); bell?.focus(); });
  banner?.querySelector('[data-push-open]')?.addEventListener('click', e => { hideBanner(false); open(bell || e.currentTarget); });
  panelWidget.addEventListener('cms:push-saved', () => { hideBanner(true); setTimeout(close, 1600); });
  listeners.add(list => bell?.classList.toggle('is-on', list.length > 0));

  // Banner: nicht gleich beim ersten Anzeigen der Seite – nach der Wartezeit, auf Wunsch erst ab der zweiten Seite (Aufruf von dieser
  // Website, erkannt am Referrer – ohne Zähler im Browser), nie wenn schon abonniert, gesperrt oder „Nein, danke“ (90 Tage)
  if (banner) {
    const dismissed = Number(store.get(LS_BANNER, 0)) > Date.now() - 90 * 86400000;
    let fromHere = false;
    try { fromHere = d.referrer !== '' && new URL(d.referrer).origin === location.origin; } catch { /* kein Referrer */ }
    if (!dismissed && Notification.permission !== 'denied' && (root.dataset.second !== '1' || fromHere)) {
      const show = () => state(cfg).then(list => {
        if (list.length || !panel.hidden) return;
        banner.hidden = false;
        if (!reduce) banner.classList.add('is-in');
      });
      const wait = Math.max(0, Number(root.dataset.delay) || 0) * 1000;
      const go = () => setTimeout(show, wait);
      if (d.readyState === 'complete') go(); else window.addEventListener('load', go, { once: true });
    }
  }
}

const start = () => {
  d.querySelectorAll('[data-cms-push]').forEach(el => { if (!el.dataset.ready) { el.dataset.ready = '1'; init(el); } });
  d.querySelectorAll('[data-cms-push-site]').forEach(el => { if (!el.dataset.ready) { el.dataset.ready = '1'; site(el); } });
};
if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', start); else start();
// Seiten-Editor / Live-Blöcke: neu gezeichnete Blöcke
d.addEventListener('cms:block-preview', start);
