/*
 * WebAuthn-Hilfen für Passkeys (resources/js/passkey.js, resources/js/invite.js): Optionen und Antworten als base64url,
 * JSON-Aufrufe mit CSRF-Header, verständliche Fehlermeldungen. Server: Core\Passkeys.
 */
import { t } from './_i18n.js';

export const supported = () => !!(window.PublicKeyCredential && navigator.credentials && window.isSecureContext);

export const b64u = buf => {
  const bytes = new Uint8Array(buf);
  let s = '';
  for (let i = 0; i < bytes.length; i++) s += String.fromCharCode(bytes[i]);
  return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
};
export const unb64u = str => {
  const s = str.replace(/-/g, '+').replace(/_/g, '/');
  const bin = atob(s + '==='.slice((s.length + 3) % 4));
  const out = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
  return out.buffer;
};

export function createOptions(o) {
  return { ...o, challenge: unb64u(o.challenge), user: { ...o.user, id: unb64u(o.user.id) },
    excludeCredentials: (o.excludeCredentials || []).map(c => ({ ...c, id: unb64u(c.id) })) };
}
export function getOptions(o) {
  const r = { ...o, challenge: unb64u(o.challenge) };
  if (o.allowCredentials) r.allowCredentials = o.allowCredentials.map(c => ({ ...c, id: unb64u(c.id) }));
  return r;
}

export function serialize(cred) {
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

export async function post(url, csrf, body) {
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
export function message(e) {
  if (!e) return t('Das hat nicht geklappt.');
  if (e.name === 'NotAllowedError') return t('Abgebrochen oder Zeit abgelaufen. Bitte erneut versuchen.');
  if (e.name === 'InvalidStateError') return t('Dieses Gerät hat für Ihr Konto bereits einen Passkey.');
  if (e.name === 'SecurityError') return t('Passkeys funktionieren nur unter der Adresse der Website mit HTTPS.');
  if (e.name === 'NotSupportedError') return t('Dieses Gerät bzw. dieser Browser unterstützt keine Passkeys.');
  if (e.name === 'AbortError') return '';
  return e.message || t('Das hat nicht geklappt.');
}

export function say(box, text, ok = false) {
  if (!box) return;
  box.hidden = !text;
  box.textContent = text;
  box.classList.toggle('adm-flash--error', !ok);
  box.classList.toggle('adm-flash--success', ok);
}

export const csrfOf = el => el.querySelector('input[name=_csrf]')?.value || document.querySelector('input[name=_csrf]')?.value || '';
