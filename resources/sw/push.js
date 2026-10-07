/*
 * Push-Benachrichtigungen (KLXM Studio, Core\Push) – als /push-sw.js eigener Service Worker (Bereich {base}/push-sw/, steuert
 * keine Seiten, speichert nichts) und angehängt an /sw.js der installierten App.
 *  - push: Mitteilung anzeigen (title, body, icon, tag, url). „quiet“ = Pfad der Verwaltung: nicht anzeigen, solange dort ein
 *    Fenster sichtbar ist (der Chat zeigt dann eigene Hinweise – keine doppelten Mitteilungen).
 *  - notificationclick: Adresse öffnen bzw. ein offenes Fenster mit derselben Adresse nach vorn holen – nur auf dieser Website
 *    (fremde Ursprünge werden auf den eigenen umgeschrieben).
 *  - pushsubscriptionchange: neues Abo an den Server melden (altes Abo weist sich mit Endpunkt + Auth-Geheimnis aus).
 */
const PUSH_KEY = __PUSH_KEY__;
const PUSH_RENEW = __PUSH_RENEW__;

const pushTarget = raw => {
  try {
    const u = new URL(raw || '/', self.location.origin);
    if (u.origin === self.location.origin && /^https?:$/.test(u.protocol)) return u;
    return new URL(u.pathname + u.search + u.hash, self.location.origin);
  } catch {
    return new URL('/', self.location.origin);
  }
};

self.addEventListener('push', e => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch { d = { body: e.data ? e.data.text() : '' }; }
  e.waitUntil((async () => {
    if (d.quiet) {
      const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
      const quiet = String(d.quiet);
      if (wins.some(w => w.visibilityState === 'visible' && new URL(w.url).pathname.startsWith(quiet))) return;
    }
    const opts = { body: String(d.body || ''), data: { url: pushTarget(d.url).href } };
    if (d.icon) opts.icon = String(d.icon);
    if (d.image) opts.image = String(d.image);
    if (d.tag) { opts.tag = String(d.tag); opts.renotify = true; }
    if (d.lang) opts.lang = String(d.lang);
    if (d.ts) opts.timestamp = Number(d.ts) * 1000;
    await self.registration.showNotification(String(d.title || self.location.host), opts);
  })());
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const target = pushTarget(e.notification.data && e.notification.data.url);
  e.waitUntil((async () => {
    const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const same = wins.find(w => w.url === target.href);
    if (same && 'focus' in same) return same.focus();
    if (self.clients.openWindow) return self.clients.openWindow(target.href);
  })());
});

self.addEventListener('pushsubscriptionchange', e => {
  if (!PUSH_KEY || !PUSH_RENEW) return;
  e.waitUntil((async () => {
    const key = Uint8Array.from(atob(PUSH_KEY.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - PUSH_KEY.length % 4) % 4)), c => c.charCodeAt(0));
    const sub = e.newSubscription || await self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
    await fetch(PUSH_RENEW, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ old: e.oldSubscription ? e.oldSubscription.toJSON() : null, subscription: sub.toJSON() }) });
  })());
});
