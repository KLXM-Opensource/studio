/*
 * Push-Abo im Browser (Core\Push) – gemeinsam für die Verwaltung (_push.js) und die Website (push.js).
 * Eigener Service Worker /push-sw.js mit eigenem Bereich ({base}/push-sw/): steuert keine Seiten und speichert nichts.
 */
const d = document;

/** Kann dieser Browser Web Push? (sicherer Kontext: https oder localhost) */
export const pushSupported = () => window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

/** iPhone/iPad ohne installierte Web-App: Push erst nach „Zum Home-Bildschirm“ */
export const iosNeedsInstall = () => {
  const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const standalone = navigator.standalone === true || window.matchMedia?.('(display-mode: standalone)').matches;
  return ios && !standalone && !('PushManager' in window);
};

export const b64uToBytes = s => {
  const b = atob(s.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - s.length % 4) % 4));
  return Uint8Array.from(b, c => c.charCodeAt(0));
};

const sameKey = (a, b) => {
  if (!a) return false;
  const x = new Uint8Array(a);
  return x.length === b.length && x.every((v, i) => v === b[i]);
};

/** Registrierung des Push-Service-Workers (vorhandene wiederverwenden), aktiv */
export async function pushRegistration(sw, scope, create = true) {
  const want = new URL(scope, location.href).href;
  let reg = (await navigator.serviceWorker.getRegistrations()).find(r => r.scope === want);
  if (!reg && !create) return null;
  if (!reg) reg = await navigator.serviceWorker.register(sw, { scope });
  if (reg.active) return reg;
  const w = reg.installing || reg.waiting;
  if (!w) return reg;
  await new Promise(res => {
    const done = () => { if (w.state === 'activated' || w.state === 'redundant') res(); };
    w.addEventListener('statechange', done);
    done();
  });
  return reg;
}

/** Bestehendes Abo (ohne neue Registrierung) – null, wenn keins */
export async function pushExisting(scope) {
  if (!pushSupported()) return null;
  const reg = await pushRegistration('', scope, false);
  return reg ? reg.pushManager.getSubscription() : null;
}

/** Hat das Abo einen anderen Server-Schlüssel (VAPID neu erzeugt)? */
export const pushStale = (sub, key) => !!sub && !!sub.options?.applicationServerKey && !sameKey(sub.options.applicationServerKey, b64uToBytes(key));

/**
 * Abo anlegen bzw. das vorhandene liefern. Fragt den Browser nur, wenn noch keine Erlaubnis besteht – also nur nach einem Klick
 * aufrufen. → PushSubscription; wirft 'denied' bei Ablehnung.
 */
export async function pushSubscribe(sw, scope, key) {
  if (Notification.permission === 'default') {
    const p = await Notification.requestPermission();
    if (p !== 'granted') throw new Error('denied');
  }
  if (Notification.permission !== 'granted') throw new Error('denied');
  const reg = await pushRegistration(sw, scope);
  let sub = await reg.pushManager.getSubscription();
  if (sub && pushStale(sub, key)) { await sub.unsubscribe().catch(() => {}); sub = null; }
  return sub || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64uToBytes(key) });
}

/** JSON an den Server (gleiche Website) */
export async function pushPost(url, body, headers = {}) {
  const res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...headers }, body: JSON.stringify(body) });
  let data = {};
  try { data = await res.json(); } catch { /* keine JSON-Antwort */ }
  return { status: res.status, ...data };
}

export const pushQs = (s, c = d) => c.querySelector(s);
