/*
 * „Benachrichtigungen abonnieren“ auf der Website (Core\Push\Visitor, Block push_subscribe, push_subscribe() im Kit).
 * Ablauf: Erklärung steht da → Klick → Abfrage des Browsers → Abo an /api/push/subscribe (nur Zustelladresse + Thema + Sprache).
 * Derselbe Knopf bestellt wieder ab. Ohne Unterstützung (kein https, alter Browser, iPhone ohne installierte App) erscheint
 * ein kurzer Hinweis statt des Knopfs. Kein Cookie; nach dem Abo merkt sich der Browser die Themen (localStorage), um nach
 * einem Schlüsselwechsel der Website still neu zu abonnieren.
 */
import { pushSupported, iosNeedsInstall, pushExisting, pushSubscribe, pushStale, pushPost } from './_pushclient.js';

const LS = 'cms-push-topics';
const remembered = () => { try { return JSON.parse(localStorage.getItem(LS) || '[]').filter(t => typeof t === 'string'); } catch { return []; } };
const remember = list => { try { if (list.length) localStorage.setItem(LS, JSON.stringify([...new Set(list)])); else localStorage.removeItem(LS); } catch { /* privat */ } };

async function init(el) {
  const T = (() => { try { return JSON.parse(el.dataset.texts || '{}'); } catch { return {}; } })();
  const btn = el.querySelector('[data-push-btn]');
  const out = el.querySelector('[data-push-status]');
  const { topic, api, scope, sw, key, token, lang } = el.dataset;
  const say = (msg, state = '') => { if (out) out.textContent = msg || ''; el.dataset.state = state; };
  if (!btn) return;
  if (!window.isSecureContext) return say(T.insecure, 'na');
  if (iosNeedsInstall()) return say(T.ios, 'ios');
  if (!pushSupported()) return say(T.unsupported, 'na');

  let subscribed = false;
  const render = () => {
    btn.hidden = false;
    btn.textContent = subscribed ? T.unsubscribe : T.subscribe;
    btn.className = (subscribed ? el.dataset.btnOff : el.dataset.btn) + ' cms-push__btn';
    btn.setAttribute('aria-pressed', String(subscribed));
    el.classList.toggle('is-on', subscribed);
  };
  const save = async (sub, topics) => {
    let r = await pushPost(api + '/subscribe', { subscription: sub.toJSON(), topics, lang, token });
    if (r.status === 409) {
      // Endpunkt mit anderem Schlüssel gespeichert → neu abonnieren
      await sub.unsubscribe().catch(() => {});
      sub = await pushSubscribe(sw, scope, key);
      r = await pushPost(api + '/subscribe', { subscription: sub.toJSON(), topics, lang, token });
    }
    if (!r.ok) throw Object.assign(new Error('save'), { userMessage: r.error });
    return r;
  };

  // Zustand: vorhandenes Abo dieses Browsers beim Server erfragen (nur mit Endpunkt + Geheimnis)
  try {
    const sub = Notification.permission === 'granted' ? await pushExisting(scope) : null;
    if (sub && pushStale(sub, key)) {
      // Die Website hat neue Schlüssel: gemerkte Themen still neu abonnieren (Erlaubnis besteht schon – keine Abfrage)
      const topics = remembered();
      if (topics.length) {
        await sub.unsubscribe().catch(() => {});
        await save(await pushSubscribe(sw, scope, key), topics);
        subscribed = topics.includes(topic);
      }
    } else if (sub) {
      const j = sub.toJSON();
      const r = await pushPost(api + '/status', { endpoint: j.endpoint, auth: j.keys?.auth, token });
      subscribed = Array.isArray(r.topics) && r.topics.includes(topic);
      if (Array.isArray(r.topics)) remember(r.topics);
    }
  } catch { /* Zustand unbekannt → Knopf „abonnieren“ */ }
  if (!subscribed && Notification.permission === 'denied') say(T.denied, 'denied');
  else if (subscribed) say(T.on, 'on');
  render();

  btn.addEventListener('click', async () => {
    btn.disabled = true;
    say(T.busy, 'busy');
    try {
      if (!subscribed) {
        if (Notification.permission === 'default') say(T.asking, 'busy');
        const sub = await pushSubscribe(sw, scope, key);
        const r = await save(sub, [topic]);
        remember(Array.isArray(r.topics) ? r.topics : [...remembered(), topic]);
        subscribed = true;
        say(T.on, 'on');
      } else {
        const sub = await pushExisting(scope);
        if (sub) {
          const j = sub.toJSON();
          const r = await pushPost(api + '/unsubscribe', { endpoint: j.endpoint, auth: j.keys?.auth, topics: [topic], token });
          if (r.state === 'deleted') await sub.unsubscribe().catch(() => {});
          remember(Array.isArray(r.topics) ? r.topics : remembered().filter(t => t !== topic));
        }
        subscribed = false;
        say(T.off, 'off');
      }
    } catch (e) {
      say(e?.message === 'denied' ? T.denied : (e?.userMessage || T.error), e?.message === 'denied' ? 'denied' : 'error');
    }
    btn.disabled = false;
    render();
    btn.focus();
  });
}

const start = () => document.querySelectorAll('[data-cms-push]').forEach(el => { if (!el.dataset.ready) { el.dataset.ready = '1'; init(el); } });
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
// Seiten-Editor / Live-Blöcke: neu gezeichnete Blöcke
document.addEventListener('cms:block-preview', start);
