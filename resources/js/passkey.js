/*
 * Passkeys (WebAuthn) – nur auf Anmeldung, zweitem Anmeldeschritt, Konto und Einrichtung geladen (Core\Passkeys, PasskeyController).
 *   [data-pk-login]   Anmeldeseite: „Mit Passkey anmelden“ + Autofill (conditional UI, autocomplete="username webauthn")
 *   [data-pk-2fa]     Zweiter Schritt nach dem Passwort: Passkey bestätigen
 *   [data-pk-add]     Konto/Einrichtung: Passkey hinzufügen (Name → navigator.credentials.create)
 * Binärwerte als base64url; Server-Antworten JSON ({publicKey} bzw. {ok, redirect} oder {error}). CSRF: Header X-CSRF-Token.
 */
import { t } from './_i18n.js';

const d = document;
const supported = () => !!(window.PublicKeyCredential && navigator.credentials && window.isSecureContext);

const b64u = buf => {
  const bytes = new Uint8Array(buf);
  let s = '';
  for (let i = 0; i < bytes.length; i++) s += String.fromCharCode(bytes[i]);
  return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
};
const unb64u = str => {
  const s = str.replace(/-/g, '+').replace(/_/g, '/');
  const bin = atob(s + '==='.slice((s.length + 3) % 4));
  const out = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
  return out.buffer;
};

function createOptions(o) {
  return { ...o, challenge: unb64u(o.challenge), user: { ...o.user, id: unb64u(o.user.id) },
    excludeCredentials: (o.excludeCredentials || []).map(c => ({ ...c, id: unb64u(c.id) })) };
}
function getOptions(o) {
  const r = { ...o, challenge: unb64u(o.challenge) };
  if (o.allowCredentials) r.allowCredentials = o.allowCredentials.map(c => ({ ...c, id: unb64u(c.id) }));
  return r;
}

function serialize(cred) {
  const r = cred.response;
  const out = { id: cred.id, rawId: b64u(cred.rawId), type: cred.type, response: { clientDataJSON: b64u(r.clientDataJSON) } };
  if (r.attestationObject) {
    out.response.attestationObject = b64u(r.attestationObject);
    out.response.transports = typeof r.getTransports === 'function' ? r.getTransports() : [];
  }
  if (r.authenticatorData) {
    out.response.authenticatorData = b64u(r.authenticatorData);
    out.response.signature = b64u(r.signature);
    out.response.userHandle = r.userHandle ? b64u(r.userHandle) : null;
  }
  return out;
}

async function post(url, csrf, body) {
  const res = await fetch(url, { method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify(body || {}) });
  let data = null;
  try { data = await res.json(); } catch { data = null; }
  if (!data) throw new Error(t('Keine gültige Antwort vom Server. Bitte Seite neu laden.'));
  if (!res.ok || data.error) {
    const e = new Error(data.error || t('Das hat nicht geklappt.'));
    e.redirect = data.redirect;
    throw e;
  }
  return data;
}

/** Fehlermeldungen der Browser in verständliche Sätze */
function message(e) {
  if (!e) return t('Das hat nicht geklappt.');
  if (e.name === 'NotAllowedError') return t('Abgebrochen oder Zeit abgelaufen. Bitte erneut versuchen.');
  if (e.name === 'InvalidStateError') return t('Dieses Gerät hat für Ihr Konto bereits einen Passkey.');
  if (e.name === 'SecurityError') return t('Passkeys funktionieren nur unter der Adresse der Website mit HTTPS.');
  if (e.name === 'NotSupportedError') return t('Dieses Gerät bzw. dieser Browser unterstützt keine Passkeys.');
  if (e.name === 'AbortError') return '';
  return e.message || t('Das hat nicht geklappt.');
}

function say(box, text, ok = false) {
  if (!box) return;
  box.hidden = !text;
  box.textContent = text;
  box.classList.toggle('adm-flash--error', !ok);
  box.classList.toggle('adm-flash--success', ok);
}

const csrfOf = el => el.querySelector('input[name=_csrf]')?.value || d.querySelector('input[name=_csrf]')?.value || '';

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

d.querySelectorAll('[data-pk-login]').forEach(initLogin);
d.querySelectorAll('[data-pk-2fa]').forEach(init2fa);
d.querySelectorAll('[data-pk-add]').forEach(initAdd);
