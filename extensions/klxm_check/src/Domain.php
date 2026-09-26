<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * Eingaben der Besucher prüfen: Domain bzw. Hostname (Domain-Analyse, SSL/TLS-Checker).
 *
 *   - Bequem: „https://www.Beispiel.de/pfad?x“ → „www.beispiel.de“, „info@beispiel.de“ → „beispiel.de“, Punkt am Ende entfällt
 *   - IDN: „bücher.de“ → „xn--bcher-kva.de“ (intl, sonst eingebauter Punycode-Umwandler)
 *   - Streng: höchstens 253 Zeichen, Labels 1–63 Zeichen aus a–z, 0–9, Bindestrich (nicht am Rand), mindestens zwei Labels,
 *     Endung nicht nur aus Ziffern; keine IP-Adressen, keine Ports, keine reservierten/internen Namen
 *     (localhost, .local, .internal, .test, .invalid, .arpa, .home.arpa …)
 * IP-Adressen sind bewusst auch im SSL/TLS-Checker nicht erlaubt: Zertifikate gehören zu Namen (SNI, Namensprüfung), und
 * ohne IP-Eingaben bleibt die Angriffsfläche für Anfragen ins interne Netz klein.
 */
final class Domain
{
    public const MAX_INPUT = 300;

    /** Endungen und Namen, die nie ins öffentliche DNS gehören (RFC 2606, 6761, 6762, 8375 und gängige interne Endungen) */
    private const BLOCKED_SUFFIXES = ['localhost', 'local', 'internal', 'intranet', 'lan', 'home', 'corp', 'private', 'test', 'invalid', 'arpa', 'onion', 'localdomain', 'home.arpa'];

    /** @throws CheckException */
    public static function normalize(string $input): string
    {
        $s = trim($input);
        if ($s === '') throw new CheckException(lt('Bitte gib eine Domain ein.'));
        if (strlen($s) > self::MAX_INPUT) throw new CheckException(lt('Die Eingabe ist zu lang.'));
        if (preg_match('~[\x00-\x1f\x7f\s]~u', $s)) throw new CheckException(lt('Bitte nur eine Domain ohne Leerzeichen eingeben.'));
        // Schema und Pfad entfernen (Adresse aus der Adresszeile eingefügt)
        $s = (string) preg_replace('~^[a-z][a-z0-9+.-]*://~i', '', $s);
        $s = (string) preg_replace('~[/?#\\\\].*$~s', '', $s);
        // E-Mail-Adresse → Domain; „user@host“ aus Adressen ebenso (nur der Teil nach dem letzten @ zählt)
        if (str_contains($s, '@')) $s = substr($s, strrpos($s, '@') + 1);
        if (str_starts_with($s, '[') || filter_var(trim($s, '[]'), FILTER_VALIDATE_IP)) {
            throw new CheckException(lt('Bitte einen Domainnamen statt einer IP-Adresse eingeben.'));
        }
        if (str_contains($s, ':')) throw new CheckException(lt('Bitte die Domain ohne Port eingeben.'));
        $s = rtrim($s, '.');
        $s = self::toAscii($s);
        self::assertHostname($s);
        return $s;
    }

    /** Unicode → ASCII (Punycode), Kleinschreibung. @throws CheckException */
    public static function toAscii(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        if (preg_match('~^[\x00-\x7f]*$~', $s)) return $s;
        if (!mb_check_encoding($s, 'UTF-8')) throw new CheckException(lt('Die Domain enthält ungültige Zeichen.'));
        // Punkte in anderen Schriften (。．｡) wie „.“ behandeln
        $s = str_replace(["\u{3002}", "\u{FF0E}", "\u{FF61}"], '.', $s);
        if (function_exists('idn_to_ascii')) {
            $out = idn_to_ascii($s, IDNA_NONTRANSITIONAL_TO_ASCII | IDNA_CHECK_BIDI | IDNA_CHECK_CONTEXTJ, INTL_IDNA_VARIANT_UTS46, $info);
            if (!is_string($out) || $out === '' || !empty($info['errors'])) throw new CheckException(lt('Die Domain enthält ungültige Zeichen.'));
            return strtolower($out);
        }
        $labels = [];
        foreach (explode('.', $s) as $l) {
            if (!preg_match('~^[\p{L}\p{M}\p{N}-]+$~u', $l)) throw new CheckException(lt('Die Domain enthält ungültige Zeichen.'));
            $labels[] = Punycode::label($l);
        }
        return implode('.', $labels);
    }

    /** Syntax eines öffentlichen Hostnamens (ASCII). @throws CheckException */
    public static function assertHostname(string $h): void
    {
        if ($h === '' || strlen($h) > 253) throw new CheckException(lt('Das ist keine gültige Domain.'));
        $labels = explode('.', $h);
        if (count($labels) < 2) throw new CheckException(lt('Bitte die vollständige Domain mit Endung eingeben (z. B. beispiel.de).'));
        foreach ($labels as $l) {
            if (!preg_match('~^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$~', $l)) throw new CheckException(lt('Das ist keine gültige Domain.'));
            // „ab--cd“ nur als ACE-Präfix (xn--) erlaubt
            if (strlen($l) >= 4 && substr($l, 2, 2) === '--' && !str_starts_with($l, 'xn--')) throw new CheckException(lt('Das ist keine gültige Domain.'));
        }
        $tld = end($labels);
        if (ctype_digit($tld)) throw new CheckException(lt('Bitte einen Domainnamen statt einer IP-Adresse eingeben.'));
        foreach (self::BLOCKED_SUFFIXES as $suf) {
            if ($h === $suf || str_ends_with($h, '.' . $suf)) throw new CheckException(lt('Interne oder reservierte Namen (z. B. localhost, .local, .test) werden nicht geprüft.'));
        }
    }

    /** Hostname aus DNS-Daten (MX, NS, include:) – wie assertHostname, erlaubt aber Unterstriche (_spf, _dmarc) */
    public static function validDnsName(string $h): bool
    {
        $h = strtolower(rtrim($h, '.'));
        if ($h === '' || strlen($h) > 253 || !str_contains($h, '.')) return false;
        foreach (explode('.', $h) as $l) {
            if (!preg_match('~^[a-z0-9_]([a-z0-9_-]{0,61}[a-z0-9_])?$~', $l)) return false;
        }
        return true;
    }

    /** Näherung der Organisations-Domain ohne Public Suffix List: letzte zwei Labels (bzw. drei bei co.uk, com.au …) */
    public static function orgDomain(string $h): string
    {
        $l = explode('.', strtolower(rtrim($h, '.')));
        $n = count($l);
        if ($n <= 2) return implode('.', $l);
        $sld = $l[$n - 2];
        $two = in_array($sld, ['co', 'com', 'net', 'org', 'gov', 'ac', 'edu', 'or', 'ne', 'go', 'gv', 'ltd', 'plc', 'sch', 'nhs', 'police', 'mil'], true) && strlen($l[$n - 1]) === 2;
        return implode('.', array_slice($l, $two ? -3 : -2));
    }

    /** Anzeige: Punycode zusätzlich als Unicode (sofern intl vorhanden) */
    public static function display(string $ascii): string
    {
        if (!str_contains($ascii, 'xn--') || !function_exists('idn_to_utf8')) return $ascii;
        $u = idn_to_utf8($ascii, IDNA_NONTRANSITIONAL_TO_UNICODE, INTL_IDNA_VARIANT_UTS46);
        return is_string($u) && $u !== '' ? $u : $ascii;
    }
}
