/*
 * Push-Benachrichtigungen der Verwaltung (Core\Push):
 *  - Konto → Benachrichtigungen ([data-push-account]): Schalter „Mitteilungen auf diesem Gerät“ (Browser-Abfrage erst beim
 *    Einschalten), „dieses Gerät“ in der Geräteliste markieren, Schalter je Ereignis sofort speichern.
 *  - Jede Seite (#cms-push-cfg, nur mit angemeldetem Gerät): einmal am Tag das Abo auffrischen – nach neuen Schlüsseln der
 *    Installation still neu abonnieren (die Erlaubnis besteht schon, keine Abfrage).
 */
import { pushSupported, iosNeedsInstall, pushExisting, pushSubscribe, pushStale, pushPost } from './_pushclient.js';
import { t } from './_i18n.js';
import { toast, toastNext } from './_toast.js';

const d = document;
const hex = async s => [...new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(s)))].map(b => b.toString(16).padStart(2, '0')).join('');

async function register(cfg, csrf) {
  let sub = await pushSubscribe(cfg.sw, cfg.scope, cfg.key);
  const send = s => pushPost(cfg.subscribe, { subscription: s.toJSON() }, { 'X-CSRF-Token': csrf() });
  let r = await send(sub);
  if (r.status === 409) {
    await sub.unsubscribe().catch(() => {});
    sub = await pushSubscribe(cfg.sw, cfg.scope, cfg.key);
    r = await send(sub);
  }
  if (!r.ok) throw new Error(r.error || t('Das Gerät konnte nicht angemeldet werden.'));
  return sub;
}

function account(box, csrf) {
  const cfg = { ...box.dataset };
  const sw = box.querySelector('[data-push-device]');
  const hint = box.querySelector('[data-push-hint]');
  const hintText = box.querySelector('[data-push-hint-text]');
  const say = msg => { if (hintText) hintText.textContent = msg || ''; if (hint) hint.hidden = !msg; };
  const blocked = t('Mitteilungen sind für diese Website im Browser blockiert. Erlauben Sie sie in den Website-Einstellungen des Browsers und laden Sie die Seite neu.');

  // Schalter je Ereignis: sofort speichern
  const form = box.querySelector('[data-push-events]');
  if (form) {
    // Mit JavaScript speichert jeder Schalter sofort (wie in den Systemeinstellungen) – die Zeile „Speichern“ entfällt
    const saveRow = form.querySelector('[data-push-save]');
    if (saveRow) saveRow.hidden = true;
    const live = d.getElementById('adm-live');
    form.addEventListener('change', async () => {
      const res = await fetch(form.action, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams(new FormData(form)),
        headers: { Accept: 'application/json', 'X-CSRF-Token': csrf() } }).catch(() => null);
      const ok = !!res && res.ok;
      if (live) live.textContent = ok ? t('Gespeichert.') : '';
      if (!ok) toast(t('Nicht gespeichert – bitte erneut versuchen.'), 'error');
    });
  }

  if (!pushSupported()) {
    say(iosNeedsInstall()
      ? t('Auf iPhone und iPad: die Website über „Teilen“ → „Zum Home-Bildschirm“ hinzufügen und die Verwaltung von dort öffnen – dann lassen sich Mitteilungen einschalten.')
      : t('Dieser Browser unterstützt keine Push-Mitteilungen (oder die Verbindung ist nicht sicher).'));
    return;
  }
  const rows = [...box.querySelectorAll('[data-push-hash]')];
  const mark = async sub => {
    const h = sub ? await hex(sub.endpoint) : '';
    let mine = false;
    rows.forEach(r => { const me = h !== '' && r.dataset.pushHash === h; r.querySelector('[data-push-this]')?.toggleAttribute('hidden', !me); mine ||= me; });
    return mine;
  };
  (async () => {
    let on = false;
    try {
      const sub = Notification.permission === 'granted' ? await pushExisting(cfg.scope) : null;
      on = !!sub && !pushStale(sub, cfg.key) && await mark(sub);
    } catch { /* unbekannt */ }
    sw.checked = on;
    sw.disabled = false;
    if (Notification.permission === 'denied') say(blocked);
  })();

  sw.addEventListener('change', async () => {
    sw.disabled = true;
    say('');
    try {
      if (sw.checked) {
        await register(cfg, csrf);
        toastNext(t('Mitteilungen auf diesem Gerät sind eingeschaltet.'));
      } else {
        const sub = await pushExisting(cfg.scope);
        if (sub) {
          const r = await pushPost(cfg.unsubscribe, { endpoint: sub.endpoint }, { 'X-CSRF-Token': csrf() });
          if (r.state !== 'unlinked') await sub.unsubscribe().catch(() => {});
        }
        toastNext(t('Mitteilungen auf diesem Gerät sind ausgeschaltet.'));
      }
      location.reload();
    } catch (e) {
      sw.checked = !sw.checked;
      sw.disabled = false;
      say(e?.message === 'denied' ? blocked : t('Das hat nicht geklappt: {error}', { error: e?.message || '?' }));
    }
  });
}

/** Einmal am Tag: Abo dieses Geräts beim Server auffrischen bzw. nach neuen Schlüsseln neu abonnieren */
async function refresh(cfgEl, csrf) {
  if (!pushSupported() || Notification.permission !== 'granted') return;
  const k = 'adm.push.sync';
  try { if (Number(localStorage.getItem(k) || 0) > Date.now() - 86400000) return; } catch { return; }
  const cfg = { ...cfgEl.dataset };
  try {
    const sub = await pushExisting(cfg.scope);
    if (!sub) return;
    if (pushStale(sub, cfg.key)) { await sub.unsubscribe().catch(() => {}); await register(cfg, csrf); }
    else await pushPost(cfg.subscribe, { subscription: sub.toJSON(), refresh: true }, { 'X-CSRF-Token': csrf() });
    localStorage.setItem(k, String(Date.now()));
  } catch { /* nächstes Mal */ }
}

export function initPush(csrf) {
  const box = d.querySelector('[data-push-account]');
  if (box) { account(box, csrf); return; }
  const cfg = d.getElementById('cms-push-cfg');
  if (cfg) refresh(cfg, csrf);
}
