/*
 * Feldtyp „iban“ in der Verwaltung: Vierergruppen beim Tippen und ein Prüfhinweis (Land, Länge, Prüfziffer ISO 13616).
 * Die verbindliche Prüfung macht der Server (Core\Iban).
 */
import { t } from './_i18n.js';

// Länge je Land (SEPA-Raum) – wie Core\Iban::LENGTHS
const LEN = Object.fromEntries('AD24 AL28 AT20 BE16 BG22 CH21 CY28 CZ24 DE22 DK18 EE20 ES24 FI18 FR27 GB22 GI23 GR27 HR21 HU28 IE22 IS26 IT27 LI21 LT20 LU20 LV21 MC27 MD24 ME22 MK19 MT31 NL18 NO15 PL28 PT25 RO24 SE24 SI19 SK24 SM27 VA22'
  .split(' ').map(x => [x.slice(0, 2), +x.slice(2)]));

export const norm = v => v.replace(/[\s.-]+/g, '').toUpperCase();
export const format = v => norm(v).replace(/(.{4})(?!$)/g, '$1 ');

/** '' (leer) | 'ok' | 'short' | 'country' | 'length' | 'checksum' | 'format' */
export function check(v) {
  const s = norm(v);
  if (s.length < 2) return '';
  if (!/^[A-Z]{2}(\d(\d[A-Z0-9]*)?)?$/.test(s)) return 'format';
  const len = LEN[s.slice(0, 2)];
  if (!len) return 'country';
  if (s.length < len) return 'short';
  if (s.length > len) return 'length';
  let r = 0;
  for (const c of s.slice(4) + s.slice(0, 4)) for (const d of (/\d/.test(c) ? c : String(c.charCodeAt(0) - 55))) r = (r * 10 + +d) % 97;
  return r === 1 ? 'ok' : 'checksum';
}

export function initIban(scope = document) {
  scope.querySelectorAll('input[data-iban]').forEach(inp => {
    if (inp._iban || inp.closest('.dff')) return; inp._iban = true;   // Website-Formulare formatieren selbst (dataform.js)
    const hint = document.createElement('p');
    hint.className = 'f-help f-iban-hint'; hint.id = inp.id + '-iban'; hint.setAttribute('aria-live', 'polite');
    inp.after(hint);
    inp.setAttribute('aria-describedby', ((inp.getAttribute('aria-describedby') || '') + ' ' + hint.id).trim());
    const msg = {
      ok: t('✓ IBAN ist gültig.'),
      short: t('Noch {n} Zeichen.'),
      country: t('Unbekannter Ländercode – nur IBANs aus dem SEPA-Raum.'),
      length: t('Zu lang für eine IBAN aus {country} ({n} Zeichen).'),
      checksum: t('Prüfziffer stimmt nicht – bitte auf Tippfehler prüfen.'),
      format: t('Nur Buchstaben und Ziffern, beginnend mit dem Ländercode (z. B. DE).'),
    };
    const upd = (live) => {
      const s = norm(inp.value), res = check(inp.value), len = LEN[s.slice(0, 2)] || 0;
      // Beim Tippen: formatieren und Cursor hinter demselben Zeichen halten
      if (live) {
        const pos = norm(inp.value.slice(0, inp.selectionStart || 0)).length;
        inp.value = format(inp.value);
        let p = 0, seen = 0;
        while (p < inp.value.length && seen < pos) { if (inp.value[p] !== ' ') seen++; p++; }
        inp.setSelectionRange?.(p, p);
      }
      hint.textContent = res ? (msg[res] || '').replace('{n}', res === 'short' ? len - s.length : len).replace('{country}', s.slice(0, 2)) : '';
      hint.classList.toggle('is-ok', res === 'ok');
      hint.classList.toggle('is-bad', !!res && res !== 'ok' && res !== 'short');
    };
    inp.addEventListener('input', () => upd(true));
    upd(false);
  });
}
