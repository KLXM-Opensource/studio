/*
 * Passkeys (WebAuthn) – nur auf Anmeldung, zweitem Anmeldeschritt, Konto und Einrichtung geladen (Core\Passkeys, PasskeyController).
 *   [data-pk-login]   Anmeldeseite: „Mit Passkey anmelden“ + Autofill (conditional UI, autocomplete="username webauthn")
 *   [data-pk-2fa]     Zweiter Schritt nach dem Passwort: Passkey bestätigen
 *   [data-pk-add]     Konto/Einrichtung: Passkey hinzufügen (Name → navigator.credentials.create)
 *   [data-pk-reauth]  Konto: mit Passkey bestätigen statt mit dem Passwort (gilt 15 Minuten; z. B. Konten ohne Passwort)
 * Binärwerte als base64url; Server-Antworten JSON ({publicKey} bzw. {ok, redirect} oder {error}). CSRF: Header X-CSRF-Token.
 */
import { t } from './_i18n.js';
import { supported, createOptions, getOptions, serialize, post, message, say, csrfOf } from './_webauthn.js';

const d = document;

// ------------------------------------------------------------ Anmeldeseite: ohne Passwort + Autofill
function initLogin(box) {
  if (!supported()) return;
  box.hidden = false;
  const btn = box.querySelector('[data-pk-login-btn]');
  const msg = box.querySelector('[data-pk-msg]');
  const csrf = csrfOf(box.closest('form') || d);
  const url = box.dataset.pkLogin;
  const next = box.dataset.next || '';
  let ctrl = null;

  const run = async conditional => {
    ctrl?.abort();
    const mine = new AbortController();
    ctrl = mine;
    let picked = false;
    try {
      const o = await post(url + '/options', csrf);
      const req = { publicKey: getOptions(o.publicKey), signal: mine.signal };
      if (conditional) req.mediation = 'conditional';
      if (!conditional) { btn.disabled = true; say(msg, ''); }
      const cred = await navigator.credentials.get(req);
      if (!cred) return;
      picked = true;
      btn.disabled = true;
      say(msg, t('Anmeldung läuft …'), true);
      const res = await post(url, csrf, { credential: serialize(cred), next });
      location.assign(res.redirect);
    } catch (e) {
      if (mine.signal.aborted && e.name === 'AbortError') return;
      // Autofill (conditional UI) läuft still im Hintergrund: Abbruch, fehlende Unterstützung o. Ä. nicht anzeigen – nur Fehler nach der Auswahl eines Passkeys
      if (conditional && !picked) return;
      btn.disabled = false;
      say(msg, message(e));
      if (e.redirect) location.assign(e.redirect);
    }
  };
  btn.addEventListener('click', () => run(false));
  // Autofill: Passkeys erscheinen in der Vorschlagsliste des E-Mail-Felds (autocomplete="username webauthn")
  if (PublicKeyCredential.isConditionalMediationAvailable) {
    PublicKeyCredential.isConditionalMediationAvailable().then(ok => { if (ok) run(true); }).catch(() => {});
  }
}

// ------------------------------------------------------------ Zweiter Schritt: Passkey bestätigen
function init2fa(box) {
  const btn = box.querySelector('[data-pk-2fa-btn]');
  const msg = box.querySelector('[data-pk-msg]');
  if (!supported()) {
    say(msg, t('Dieser Browser unterstützt keine Passkeys. Bitte den Code aus der App oder einen Wiederherstellungscode verwenden.'));
    if (btn) btn.disabled = true;
    return;
  }
  const csrf = csrfOf(box);
  const url = box.getAttribute('data-pk-2fa');
  const go = async () => {
    btn.disabled = true;
    say(msg, '');
    try {
      const o = await post(url + '/options', csrf);
      const cred = await navigator.credentials.get({ publicKey: getOptions(o.publicKey) });
      say(msg, t('Anmeldung läuft …'), true);
      const res = await post(url, csrf, { credential: serialize(cred) });
      location.assign(res.redirect);
    } catch (e) {
      btn.disabled = false;
      say(msg, message(e));
      if (e.redirect) setTimeout(() => location.assign(e.redirect), 1500);
    }
  };
  btn.addEventListener('click', go);
  if (box.dataset.auto === '1') go();
}

// ------------------------------------------------------------ Konto: Passkey hinzufügen
function initAdd(form) {
  const msg = form.querySelector('[data-pk-msg]');
  const btn = form.querySelector('button[type=submit]');
  if (!supported()) {
    say(msg, t('Dieser Browser unterstützt keine Passkeys.'));
    if (btn) btn.disabled = true;
    return;
  }
  form.addEventListener('submit', async ev => {
    ev.preventDefault();
    const csrf = csrfOf(form);
    const url = form.dataset.pkAdd;
    const name = form.elements.name?.value || '';
    btn.disabled = true;
    say(msg, t('Bitte am Gerät bestätigen (Fingerabdruck, Gesicht, PIN oder Sicherheitsschlüssel) …'), true);
    try {
      const o = await post(url + '/options', csrf, { name });
      const cred = await navigator.credentials.create({ publicKey: createOptions(o.publicKey) });
      const res = await post(url, csrf, { credential: serialize(cred) });
      location.assign(res.redirect);
    } catch (e) {
      btn.disabled = false;
      say(msg, message(e));
    }
  });
}

// ------------------------------------------------------------ Konto: mit Passkey bestätigen (statt Passwort)
function initReauth(box) {
  const btn = box.querySelector('[data-pk-reauth-btn]');
  const msg = box.querySelector('[data-pk-msg]');
  if (!supported()) return;   // ohne Passkey-Unterstützung bleibt nur das Passwort (bzw. neu anmelden)
  box.hidden = false;
  const csrf = csrfOf(box);
  const url = box.dataset.pkReauth;
  btn.addEventListener('click', async () => {
    btn.disabled = true;
    say(msg, t('Bitte am Gerät bestätigen (Fingerabdruck, Gesicht, PIN oder Sicherheitsschlüssel) …'), true);
    try {
      const o = await post(url + '/options', csrf);
      const cred = await navigator.credentials.get({ publicKey: getOptions(o.publicKey) });
      const res = await post(url, csrf, { credential: serialize(cred), back: box.dataset.back || '' });
      location.assign(res.redirect);
    } catch (e) {
      btn.disabled = false;
      say(msg, message(e));
    }
  });
}

// Einmal je Seite, auch wenn das Skript mehrfach eingebunden ist (Konto: Anmeldedaten und Passkeys)
if (!window.__klxmPasskeys) {
  window.__klxmPasskeys = true;
  d.querySelectorAll('[data-pk-login]').forEach(initLogin);
  d.querySelectorAll('[data-pk-2fa]').forEach(init2fa);
  d.querySelectorAll('[data-pk-add]').forEach(initAdd);
  d.querySelectorAll('[data-pk-reauth]').forEach(initReauth);
}
