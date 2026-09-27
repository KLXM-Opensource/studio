/*
 * Einladung annehmen (/admin/einladung/{token}, InviteController, Core\Invites):
 *   [data-invite]        Formular: Name, Passwort (Anzeigen/Verbergen, Stärke-Hinweis), Absenden = mit Passwort
 *   [data-inv-pk]        Passkey erstellen (nur mit Passkey-fähigem Browser sichtbar) – sendet Name und ein ggf. eingegebenes
 *                        Passwort mit; das Konto entsteht erst, wenn der Passkey gespeichert ist.
 * Ohne JavaScript funktioniert der Weg mit Passwort. CSRF: Header X-CSRF-Token.
 */
import { t } from './_i18n.js';
import { supported, createOptions, serialize, post, message, say, csrfOf } from './_webauthn.js';

const form = document.querySelector('[data-invite]');

/** Grobe Einschätzung (0–4) – nur Hinweis; verbindlich ist die Prüfung auf dem Server (mind. 12 Zeichen) */
function strength(pw) {
  if (pw.length < 12) return 0;
  const classes = [/[a-z]/, /[A-Z]/, /\d/, /[^\w\s]/, /\s/].filter(r => r.test(pw)).length;
  const repeats = /(.)\1{3,}/.test(pw) || /^(?:1234|abcd|qwer|pass)/i.test(pw);
  let s = 1 + (pw.length >= 16 ? 1 : 0) + (pw.length >= 22 ? 1 : 0) + (classes >= 3 ? 1 : 0);
  if (repeats) s = Math.max(1, s - 2);
  return Math.min(4, s);
}

function initPassword(f) {
  const pw = f.querySelector('#inv-pw');
  const pw2 = f.querySelector('#inv-pw2');
  const reveal = f.querySelector('[data-inv-reveal]');
  const meter = f.querySelector('[data-inv-meter]');
  const hint = f.querySelector('[data-inv-strength]');
  if (!pw) return;
  const base = hint?.textContent || '';
  if (reveal) {
    reveal.hidden = false;
    reveal.addEventListener('click', () => {
      const show = pw.type === 'password';
      for (const el of [pw, pw2]) if (el) el.type = show ? 'text' : 'password';
      reveal.setAttribute('aria-pressed', String(show));
      reveal.textContent = show ? t('Verbergen') : t('Anzeigen');
    });
  }
  const labels = [t('Zu kurz – mindestens 12 Zeichen.'), t('Stärke: ausreichend'), t('Stärke: gut'), t('Stärke: sehr gut'), t('Stärke: ausgezeichnet')];
  pw.addEventListener('input', () => {
    const v = pw.value;
    if (meter) {
      meter.hidden = v === '';
      const s = strength(v);
      meter.dataset.level = String(s);
      meter.firstElementChild.style.width = (v === '' ? 0 : Math.max(8, s * 25)) + '%';
    }
    if (hint) hint.textContent = v === '' ? base : labels[strength(v)];
  });
}

function initPasskey(f, box) {
  if (!supported()) return;
  box.hidden = false;
  const btn = box.querySelector('[data-inv-pk-btn]');
  const msg = box.querySelector('[data-pk-msg]');
  const url = f.dataset.invite;
  const field = name => f.elements[name]?.value || '';
  btn.addEventListener('click', async () => {
    const name = f.elements.name;
    if (!name.value.trim()) {
      name.focus();
      say(msg, t('Bitte geben Sie Ihren Namen an.'));
      return;
    }
    if (f.dataset.pwRequired === '1' && !field('password')) {
      f.elements.password.focus();
      say(msg, t('Bitte legen Sie ein Passwort fest.'));
      return;
    }
    const csrf = csrfOf(f);
    const data = { name: name.value, password: field('password'), password2: field('password2') };
    btn.disabled = true;
    say(msg, t('Bitte am Gerät bestätigen (Fingerabdruck, Gesicht, PIN oder Sicherheitsschlüssel) …'), true);
    try {
      const o = await post(url + '/passkey/options', csrf, data);
      const cred = await navigator.credentials.create({ publicKey: createOptions(o.publicKey) });
      say(msg, t('Konto wird eingerichtet …'), true);
      const res = await post(url + '/passkey', csrf, { ...data, credential: serialize(cred) });
      location.assign(res.redirect);
    } catch (e) {
      btn.disabled = false;
      say(msg, message(e));
      if (e.redirect) setTimeout(() => location.assign(e.redirect), 1500);
    }
  });
}

if (form) {
  initPassword(form);
  if (form.dataset.passkey === '1') form.querySelectorAll('[data-inv-pk]').forEach(box => initPasskey(form, box));
}
