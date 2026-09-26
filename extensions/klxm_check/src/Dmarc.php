<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * DMARC (RFC 7489, mit Tags aus DMARCbis): _dmarc-Eintrag holen, Tags prüfen, Empfehlungen, Freigabe externer Berichts-
 * Empfänger (<domain>._report._dmarc.<empfänger-domain>, RFC 7489 §7.1).
 */
final class Dmarc
{
    public const POLICIES = ['none', 'quarantine', 'reject'];
    private const KNOWN = ['v', 'p', 'sp', 'np', 'pct', 'rua', 'ruf', 'adkim', 'aspf', 'fo', 'rf', 'ri', 't', 'psd'];

    public static function isDmarc(string $txt): bool
    {
        return (bool) preg_match('~^\s*v\s*=\s*dmarc1\s*(;|$)~i', $txt);
    }

    /** @return array{tags: array<string, string>, errors: list<string>, warnings: list<string>, rua: list<string>, ruf: list<string>} */
    public static function parse(string $record): array
    {
        $tags = $errors = $warnings = [];
        $parts = array_values(array_filter(array_map('trim', explode(';', trim($record))), fn($x) => $x !== ''));
        foreach ($parts as $i => $p) {
            if (!str_contains($p, '=')) {
                $errors[] = lt('„{tag}“ ist kein gültiges Tag (Form: name=wert).', ['tag' => $p]);
                continue;
            }
            [$k, $v] = array_map('trim', explode('=', $p, 2));
            $k = strtolower($k);
            if ($i === 0 && $k !== 'v') $errors[] = lt('Der Eintrag muss mit „v=DMARC1“ beginnen.');
            if (isset($tags[$k])) $warnings[] = lt('Tag „{tag}“ kommt doppelt vor – nur das erste zählt.', ['tag' => $k]);
            $tags[$k] ??= $v;
            if (!in_array($k, self::KNOWN, true)) $warnings[] = lt('Unbekanntes Tag „{tag}“ – wird ignoriert.', ['tag' => $k]);
        }
        if (($tags['v'] ?? '') !== 'DMARC1') {
            if (isset($tags['v']) && strcasecmp($tags['v'], 'DMARC1') === 0) $warnings[] = lt('„v=DMARC1“ muss genau so geschrieben werden (Großbuchstaben).');
            elseif (!$errors) $errors[] = lt('Der Eintrag muss mit „v=DMARC1“ beginnen.');
        }
        if (!isset($tags['p'])) {
            $errors[] = lt('Das Pflicht-Tag „p“ (Richtlinie) fehlt.');
        } elseif (!in_array(strtolower($tags['p']), self::POLICIES, true)) {
            $errors[] = lt('„p={v}“ ist ungültig – erlaubt: none, quarantine, reject.', ['v' => $tags['p']]);
        }
        foreach (['sp', 'np'] as $k) {
            if (isset($tags[$k]) && !in_array(strtolower($tags[$k]), self::POLICIES, true)) $errors[] = lt('„{tag}={v}“ ist ungültig – erlaubt: none, quarantine, reject.', ['tag' => $k, 'v' => $tags[$k]]);
        }
        if (isset($tags['pct']) && (!ctype_digit($tags['pct']) || (int) $tags['pct'] > 100)) $errors[] = lt('„pct“ muss eine Zahl von 0 bis 100 sein.');
        foreach (['adkim', 'aspf'] as $k) {
            if (isset($tags[$k]) && !in_array(strtolower($tags[$k]), ['r', 's'], true)) $errors[] = lt('„{tag}“ muss „r“ (relaxed) oder „s“ (strict) sein.', ['tag' => $k]);
        }
        if (isset($tags['fo'])) {
            foreach (explode(':', $tags['fo']) as $o) {
                if (!in_array(trim($o), ['0', '1', 'd', 's'], true)) $errors[] = lt('„fo={v}“ ist ungültig – erlaubt: 0, 1, d, s (mit „:“ getrennt).', ['v' => $tags['fo']]);
            }
        }
        if (isset($tags['ri']) && !ctype_digit($tags['ri'])) $errors[] = lt('„ri“ muss eine Zahl (Sekunden) sein.');
        $rua = self::uris($tags['rua'] ?? '', 'rua', $errors);
        $ruf = self::uris($tags['ruf'] ?? '', 'ruf', $errors);
        return ['tags' => $tags, 'errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings)), 'rua' => $rua, 'ruf' => $ruf];
    }

    /** mailto:-Adressen einer rua/ruf-Liste (Größenangabe „!10m“ wird abgeschnitten) */
    private static function uris(string $list, string $tag, array &$errors): array
    {
        $out = [];
        foreach (array_filter(array_map('trim', explode(',', $list))) as $u) {
            $addr = preg_replace('~!\d+[kmgt]?$~i', '', $u) ?? $u;
            if (!preg_match('~^mailto:(.+)$~i', $addr, $m)) {
                $errors[] = lt('„{tag}“: „{v}“ muss mit „mailto:“ beginnen.', ['tag' => $tag, 'v' => $u]);
                continue;
            }
            $mail = rawurldecode($m[1]);
            if (!preg_match('~^[^@\s]+@([a-z0-9.-]+\.[a-z]{2,}|xn--[a-z0-9.-]+)$~i', $mail)) {
                $errors[] = lt('„{tag}“: „{v}“ ist keine gültige E-Mail-Adresse.', ['tag' => $tag, 'v' => $mail]);
                continue;
            }
            $out[] = strtolower($mail);
        }
        return $out;
    }

    public static function check(string $domain, Dns $dns): Result
    {
        $r = new Result('dmarc', lt('DMARC'));
        $txt = $dns->txt('_dmarc.' . $domain);
        if ($txt === null) {
            $r->error(lt('Der DMARC-Eintrag ließ sich nicht abfragen (DNS-Fehler oder Zeitüberschreitung).'));
            $r->summary = lt('DNS-Abfrage fehlgeschlagen.');
            return $r;
        }
        $recs = array_values(array_filter($txt, [self::class, 'isDmarc']));
        $policyDomain = $domain;
        $inherited = false;
        if (!$recs) {
            $org = Domain::orgDomain($domain);
            if ($org !== $domain) {
                $parent = array_values(array_filter($dns->txt('_dmarc.' . $org) ?? [], [self::class, 'isDmarc']));
                if ($parent) {
                    $recs = $parent;
                    $policyDomain = $org;
                    $inherited = true;
                }
            }
        }
        if (!$recs) {
            $r->error(lt('Kein DMARC-Eintrag unter _dmarc.{host}. Ohne DMARC können Betrüger deine Domain leichter fälschen; Google, Yahoo und Microsoft verlangen DMARC für Massenversender.', ['host' => $domain]));
            $r->info(lt('Starte mit „v=DMARC1; p=none; rua=mailto:…“ und werte die Berichte aus – der DMARC-Generator hilft dabei.'));
            $r->summary = lt('Kein DMARC-Eintrag vorhanden.');
            $r->data = ['has' => false];
            return $r;
        }
        if (count($recs) > 1) $r->error(lt('{n} DMARC-Einträge gefunden – erlaubt ist genau einer. Empfänger ignorieren DMARC dann ganz.', ['n' => count($recs)]));
        if ($inherited) {
            $r->info(lt('{host} hat keinen eigenen Eintrag – es gilt der Eintrag der Organisations-Domain {org}.', ['host' => $domain, 'org' => $policyDomain]));
        }
        return self::report($r, $recs[0], $policyDomain, $dns, $inherited);
    }

    public static function analyse(string $record, ?string $domain, Dns $dns): Result
    {
        $r = new Result('dmarc-analyse', lt('DMARC-Analyse'));
        $record = trim(preg_replace('~\s+~', ' ', trim($record, " \t\n\r\"'")) ?? '');
        if ($record === '') throw new CheckException(lt('Bitte füge einen DMARC-Eintrag ein.'));
        if (strlen($record) > 2048) throw new CheckException(lt('Die Eingabe ist zu lang.'));
        return self::report($r, $record, $domain, $dns, false);
    }

    private static function report(Result $r, string $record, ?string $domain, Dns $dns, bool $inherited): Result
    {
        $p = self::parse($record);
        $t = $p['tags'];
        $r->code(lt('Eintrag'), $record);
        foreach ($p['errors'] as $e) $r->error($e);
        foreach ($p['warnings'] as $e) $r->warn($e);
        $policy = strtolower($t['p'] ?? '');
        $sp = strtolower($t['sp'] ?? $policy);
        $eff = $inherited ? $sp : $policy;
        $pct = isset($t['pct']) && ctype_digit($t['pct']) ? (int) $t['pct'] : 100;
        match ($eff) {
            'none' => $r->warn(lt('Richtlinie „none“: Es wird nur beobachtet, gefälschte Mails werden noch zugestellt. Nach 2–4 Wochen Berichte-Auswertung auf „quarantine“ und dann „reject“ erhöhen.')),
            'quarantine' => $r->ok(lt('Richtlinie „quarantine“: gefälschte Mails landen im Spam. Nächster Schritt: „reject“.')),
            'reject' => $r->ok(lt('Richtlinie „reject“: gefälschte Mails werden abgewiesen – der beste Schutz.')),
            default => null,
        };
        if ($pct < 100 && $eff !== 'none') $r->info(lt('„pct={n}“: Die Richtlinie gilt nur für {n} % der auffälligen Mails – für den vollen Schutz auf 100 erhöhen.', ['n' => $pct]));
        if (!$inherited && isset($t['sp']) && array_search($sp, self::POLICIES, true) < array_search($policy, self::POLICIES, true)) {
            $r->info(lt('Für Subdomains gilt eine schwächere Richtlinie (sp={sp}) als für die Domain selbst (p={p}).', ['sp' => $sp, 'p' => $policy]));
        }
        if (!$p['rua']) {
            $r->warn(lt('Keine Berichtsadresse (rua) – du erfährst nicht, wer in deinem Namen sendet. Ergänze „rua=mailto:…“.'));
        } else {
            $r->ok(lt('Sammelberichte (rua) gehen an: {list}.', ['list' => implode(', ', $p['rua'])]));
        }
        if ($p['ruf']) $r->info(lt('Fehlerberichte (ruf) verschicken nur wenige Anbieter; sie können personenbezogene Daten enthalten.'));
        if (strtolower($t['adkim'] ?? 'r') === 's' || strtolower($t['aspf'] ?? 'r') === 's') {
            $r->info(lt('Strenges Alignment (s): Absender-Domain und SPF/DKIM-Domain müssen exakt gleich sein – Mails über Subdomains oder Dienstleister können dann scheitern.'));
        }
        if (strtolower($t['t'] ?? '') === 'y') $r->info(lt('„t=y“ (Testmodus): Empfänger wenden die Richtlinie nur abgeschwächt an.'));

        // Freigabe externer Berichts-Empfänger
        $authRows = [];
        if ($domain) {
            foreach (array_unique(array_merge($p['rua'], $p['ruf'])) as $mail) {
                $rd = substr($mail, strrpos($mail, '@') + 1);
                if (Domain::orgDomain($rd) === Domain::orgDomain($domain)) continue;
                $name = $domain . '._report._dmarc.' . $rd;
                $ok = (bool) array_filter($dns->txt($name) ?? [], [self::class, 'isDmarc']);
                $authRows[] = [$rd, [$ok ? lt('freigegeben') : lt('nicht freigegeben'), $ok ? 'ok' : 'warn'], $name];
                if (!$ok) $r->warn(lt('{host} hat den Empfang von Berichten für {domain} nicht freigegeben (TXT „v=DMARC1“ unter {name} fehlt) – viele Anbieter schicken dann keine Berichte.', ['host' => $rd, 'domain' => $domain, 'name' => $name]));
            }
        }
        $explain = [
            ['p', ($t['p'] ?? '–') . ' — ' . lt('Richtlinie für die Domain'), null],
            ['sp', isset($t['sp']) ? $t['sp'] . ' — ' . lt('Richtlinie für Subdomains') : '', null],
            ['np', isset($t['np']) ? $t['np'] . ' — ' . lt('Richtlinie für nicht existierende Subdomains') : '', null],
            ['pct', $pct . ' % — ' . lt('Anteil der Mails, für die die Richtlinie gilt'), null],
            ['rua', $p['rua'] ? implode(', ', $p['rua']) . ' — ' . lt('Sammelberichte (täglich, XML)') : '', null],
            ['ruf', $p['ruf'] ? implode(', ', $p['ruf']) . ' — ' . lt('Fehlerberichte einzelner Mails') : '', null],
            ['adkim', strtolower($t['adkim'] ?? 'r') === 's' ? lt('strict (exakt)') : lt('relaxed (Subdomains zählen mit)'), null],
            ['aspf', strtolower($t['aspf'] ?? 'r') === 's' ? lt('strict (exakt)') : lt('relaxed (Subdomains zählen mit)'), null],
            ['fo', isset($t['fo']) ? $t['fo'] . ' — ' . lt('Wann Fehlerberichte entstehen') : '', null],
            ['ri', isset($t['ri']) ? $t['ri'] . ' s — ' . lt('Berichtsintervall') : '', null],
        ];
        $r->kv(lt('Tags'), $explain);
        if ($authRows) $r->table(lt('Externe Berichts-Empfänger'), [lt('Empfänger-Domain'), lt('Status'), lt('Geprüfter Eintrag')], $authRows);
        $r->summary = match (true) {
            (bool) $p['errors'] => lt('DMARC vorhanden, aber fehlerhaft.'),
            $eff === 'reject' => lt('DMARC schützt vollständig (reject).'),
            $eff === 'quarantine' => lt('DMARC schützt (quarantine).'),
            default => lt('DMARC vorhanden, beobachtet nur (none).'),
        };
        $r->data = ['has' => true, 'policy' => $eff, 'pct' => $pct, 'rua' => (bool) $p['rua']];
        return $r;
    }
}
