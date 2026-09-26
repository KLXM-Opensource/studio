<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * SPF (RFC 7208): Eintrag holen, zerlegen, include/redirect rekursiv auflösen, DNS-Lookups zählen (Grenze 10, §4.6.4),
 * leere Antworten („void lookups“, Grenze 2), Hinweise je Mechanismus – plus Optimierung eines eingefügten Eintrags.
 * Es wird keine Absender-IP bewertet, sondern der Eintrag selbst geprüft.
 */
final class Spf
{
    public const MAX_LOOKUPS = 10;
    public const MAX_VOIDS = 2;
    public const MECHANISMS = ['all', 'include', 'a', 'mx', 'ptr', 'ip4', 'ip6', 'exists'];
    /** Mechanismen/Modifikatoren, die einen DNS-Lookup kosten */
    public const LOOKUP = ['include', 'a', 'mx', 'ptr', 'exists', 'redirect'];

    public static function isSpf(string $txt): bool
    {
        return (bool) preg_match('~^v=spf1(\s|$)~i', trim($txt));
    }

    /**
     * Eintrag zerlegen. @return array{terms: list<array{raw: string, q: string, name: string, kind: string, value: string, c4: ?int, c6: ?int, macro: bool, error: ?string}>, errors: list<string>}
     */
    public static function parse(string $record): array
    {
        $record = trim($record);
        $errors = [];
        $parts = preg_split('~\s+~', $record, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$parts || strtolower($parts[0]) !== 'v=spf1') {
            $errors[] = lt('Der Eintrag muss mit „v=spf1“ beginnen.');
        } else {
            array_shift($parts);
        }
        $terms = [];
        foreach ($parts as $raw) {
            $t = ['raw' => $raw, 'q' => '+', 'name' => '', 'kind' => 'mech', 'value' => '', 'c4' => null, 'c6' => null, 'macro' => str_contains($raw, '%'), 'error' => null];
            if (preg_match('~^([a-z][a-z0-9_.-]*)=(.*)$~i', $raw, $m)) {
                $t['kind'] = 'mod';
                $t['name'] = strtolower($m[1]);
                $t['value'] = $m[2];
                if (in_array($t['name'], ['redirect', 'exp'], true) && $t['value'] === '') $t['error'] = lt('„{term}“ braucht einen Domainnamen.', ['term' => $raw]);
                $terms[] = $t;
                continue;
            }
            $q = $raw[0];
            if (in_array($q, ['+', '-', '~', '?'], true)) {
                $t['q'] = $q;
                $rest = substr($raw, 1);
            } else {
                $rest = $raw;
            }
            if (!preg_match('~^([a-z0-9]+)(.*)$~i', $rest, $m)) {
                $t['error'] = lt('„{term}“ ist kein gültiger SPF-Ausdruck.', ['term' => $raw]);
                $terms[] = $t + ['kind' => 'mech'];
                continue;
            }
            $t['name'] = strtolower($m[1]);
            $arg = $m[2];
            if (!in_array($t['name'], self::MECHANISMS, true)) {
                $t['error'] = lt('Unbekannter Mechanismus „{term}“ – ungültiger Eintrag (permerror).', ['term' => $raw]);
                $terms[] = $t;
                continue;
            }
            switch ($t['name']) {
                case 'all':
                    if ($arg !== '') $t['error'] = lt('„all“ hat keine Angaben – „{term}“ ist ungültig.', ['term' => $raw]);
                    break;
                case 'ip4':
                case 'ip6':
                    if (!preg_match('~^:([^/]+)(?:/(\d{1,3}))?$~', $arg, $a)) {
                        $t['error'] = lt('„{term}“: Adresse fehlt.', ['term' => $raw]);
                        break;
                    }
                    $t['value'] = $a[1];
                    $cidr = isset($a[2]) ? (int) $a[2] : null;
                    $v4 = $t['name'] === 'ip4';
                    if (!filter_var($a[1], FILTER_VALIDATE_IP, $v4 ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6)) {
                        $t['error'] = lt('„{term}“: keine gültige {kind}-Adresse.', ['term' => $raw, 'kind' => $v4 ? 'IPv4' : 'IPv6']);
                    } elseif ($cidr !== null && $cidr > ($v4 ? 32 : 128)) {
                        $t['error'] = lt('„{term}“: Präfixlänge zu groß.', ['term' => $raw]);
                    }
                    $t[$v4 ? 'c4' : 'c6'] = $cidr;
                    break;
                case 'a':
                case 'mx':
                    if (!preg_match('~^(?::([^/]+))?(?:/(\d{1,2}))?(?://(\d{1,3}))?$~', $arg, $a)) {
                        $t['error'] = lt('„{term}“ ist kein gültiger SPF-Ausdruck.', ['term' => $raw]);
                        break;
                    }
                    $t['value'] = strtolower($a[1] ?? '');
                    $t['c4'] = isset($a[2]) && $a[2] !== '' ? (int) $a[2] : null;
                    $t['c6'] = isset($a[3]) && $a[3] !== '' ? (int) $a[3] : null;
                    if (($t['c4'] ?? 0) > 32 || ($t['c6'] ?? 0) > 128) $t['error'] = lt('„{term}“: Präfixlänge zu groß.', ['term' => $raw]);
                    break;
                case 'ptr':
                    $t['value'] = strtolower(ltrim($arg, ':'));
                    break;
                case 'include':
                case 'exists':
                    if (!str_starts_with($arg, ':') || strlen($arg) < 2) {
                        $t['error'] = lt('„{term}“ braucht einen Domainnamen.', ['term' => $raw]);
                        break;
                    }
                    $t['value'] = strtolower(substr($arg, 1));
                    break;
            }
            if ($t['error'] === null && $t['value'] !== '' && !$t['macro'] && in_array($t['name'], ['a', 'mx', 'ptr', 'include', 'exists'], true) && !Domain::validDnsName($t['value'])) {
                $t['error'] = lt('„{term}“: „{host}“ ist kein gültiger Domainname.', ['term' => $raw, 'host' => $t['value']]);
            }
            $terms[] = $t;
        }
        foreach ($terms as $t) if ($t['error']) $errors[] = $t['error'];
        return ['terms' => $terms, 'errors' => $errors];
    }

    /** Beschreibung eines Ausdrucks für Einsteiger */
    public static function explain(array $t, string $domain): string
    {
        $host = $t['value'] !== '' ? $t['value'] : ($domain !== '' ? $domain : lt('der Domain'));
        $cidr = ($t['c4'] !== null ? '/' . $t['c4'] : '') . ($t['c6'] !== null ? ' //' . $t['c6'] : '');
        if ($t['kind'] === 'mod') {
            return match ($t['name']) {
                'redirect' => lt('Verwendet den SPF-Eintrag von {host}, wenn nichts anderes passt (ersetzt „all“).', ['host' => $t['value']]),
                'exp' => lt('Erklärungstext für abgelehnte Mails steht bei {host}.', ['host' => $t['value']]),
                default => lt('Unbekannter Modifikator – wird von Empfängern ignoriert.'),
            };
        }
        return match ($t['name']) {
            'all' => match ($t['q']) {
                '-' => lt('Alle anderen Server: ablehnen (Fail) – streng.'),
                '~' => lt('Alle anderen Server: verdächtig markieren (SoftFail) – üblich und sicher zusammen mit DMARC.'),
                '?' => lt('Alle anderen Server: keine Aussage (Neutral) – schützt praktisch nicht.'),
                default => lt('Alle Server der Welt dürfen senden (Pass) – macht SPF wirkungslos.'),
            },
            'include' => lt('Übernimmt die erlaubten Server aus dem SPF-Eintrag von {host}.', ['host' => $host]),
            'a' => lt('Erlaubt die IP-Adressen (A/AAAA) von {host}{cidr}.', ['host' => $host, 'cidr' => $cidr]),
            'mx' => lt('Erlaubt die Mailserver (MX) von {host}{cidr}.', ['host' => $host, 'cidr' => $cidr]),
            'ip4' => lt('Erlaubt die IPv4-Adresse bzw. das Netz {ip}.', ['ip' => $t['value'] . ($t['c4'] !== null ? '/' . $t['c4'] : '')]),
            'ip6' => lt('Erlaubt die IPv6-Adresse bzw. das Netz {ip}.', ['ip' => $t['value'] . ($t['c6'] !== null ? '/' . $t['c6'] : '')]),
            'ptr' => lt('Veraltet: erlaubt Server, deren Reverse-DNS auf {host} endet – langsam, unzuverlässig, nicht verwenden.', ['host' => $host]),
            'exists' => lt('Erlaubt, wenn {host} einen A-Eintrag hat (meist mit Makros, von Anbietern genutzt).', ['host' => $host]),
            default => '',
        };
    }

    public static function qualifierLabel(string $q): string
    {
        return match ($q) { '-' => 'Fail', '~' => 'SoftFail', '?' => 'Neutral', default => 'Pass' };
    }

    // ------------------------------------------------------------------ Rekursive Auswertung

    /**
     * Eintrag einer Domain (oder vorgegebener Text) auswerten. Ergebnis in $w: lookups, voids, nodes (Baum), errors, warnings,
     * ip4/ip6 (erlaubte Netze aus ip4:/ip6: und aufgelösten a/mx – für Optimierung und Zusammenfassung).
     */
    public static function walk(Dns $dns, string $domain, ?string $record, SpfWalk $w, int $depth = 0): void
    {
        if ($depth > 10) {
            $w->errors[] = lt('Zu tief verschachtelte include-Kette (mehr als 10 Ebenen).');
            return;
        }
        if ($domain !== '') $w->visited[$domain] = true;
        if ($record === null) {
            $txt = $dns->txt($domain);
            if ($txt === null) {
                $w->errors[] = lt('DNS-Abfrage für {host} fehlgeschlagen (Zeitüberschreitung oder Serverfehler).', ['host' => $domain]);
                return;
            }
            $spf = array_values(array_filter($txt, [self::class, 'isSpf']));
            if (!$spf) {
                if ($depth > 0) {
                    $w->voids++;
                    $w->errors[] = lt('{host} (per include eingebunden) hat keinen SPF-Eintrag – das ist ein permanenter Fehler (permerror).', ['host' => $domain]);
                }
                return;
            }
            if (count($spf) > 1) {
                $w->errors[] = lt('{host} hat {n} SPF-Einträge – erlaubt ist genau einer (permerror).', ['host' => $domain, 'n' => count($spf)]);
            }
            $record = $spf[0];
        }
        if ($depth === 0) $w->record = $record;
        $parsed = self::parse($record);
        foreach ($parsed['errors'] as $e) $w->errors[] = $depth ? $domain . ': ' . $e : $e;
        $hasAll = (bool) array_filter($parsed['terms'], fn($t) => $t['kind'] === 'mech' && $t['name'] === 'all');
        $seen = [];
        foreach ($parsed['terms'] as $t) {
            $node = ['depth' => $depth, 'domain' => $domain, 'term' => $t['raw'], 'q' => $t['q'], 'name' => $t['name'], 'kind' => $t['kind'],
                'text' => $t['error'] ?? self::explain($t, $domain), 'lookup' => false, 'level' => $t['error'] ? 'error' : null, 'detail' => ''];
            $key = strtolower($t['raw']);
            if (isset($seen[$key]) && $t['kind'] === 'mech') {
                $w->warnings[] = lt('„{term}“ steht doppelt im Eintrag von {host}.', ['term' => $t['raw'], 'host' => $domain]);
            }
            $seen[$key] = true;
            if ($t['error']) {
                $w->nodes[] = $node;
                continue;
            }
            $counts = in_array($t['name'], self::LOOKUP, true) && !($t['kind'] === 'mod' && $t['name'] !== 'redirect');
            if ($t['kind'] === 'mod' && $t['name'] === 'redirect' && $hasAll) {
                $counts = false;
                $node['text'] .= ' ' . lt('Wird ignoriert, weil der Eintrag „all“ enthält.');
            }
            if ($counts) {
                $w->lookups++;
                $node['lookup'] = true;
                $node['n'] = $w->lookups;
            }
            $target = $t['value'] !== '' ? $t['value'] : $domain;
            if ($target === '' && in_array($t['name'], ['a', 'mx', 'ptr'], true)) {
                $node['detail'] = lt('Ohne Domain nicht auswertbar – gib die Domain mit an.');
                $w->nodes[] = $node;
                continue;
            }
            if ($t['macro']) {
                $node['detail'] = lt('Enthält Makros – wird erst beim Empfang einer Mail ausgewertet.');
                $w->nodes[] = $node;
                continue;
            }
            switch ($t['kind'] === 'mod' ? 'mod:' . $t['name'] : $t['name']) {
                case 'include':
                case 'mod:redirect':
                    $w->nodes[] = $node;
                    if ($t['kind'] === 'mod' && $hasAll) break;
                    if (isset($w->visited[$target])) {
                        $w->errors[] = lt('Schleife: {host} wird mehrfach eingebunden.', ['host' => $target]);
                        break;
                    }
                    if ($w->lookups > self::MAX_LOOKUPS + 5 || $dns->expired()) break;   // Grenze deutlich überschritten: nicht weiter abfragen
                    self::walk($dns, $target, null, $w, $depth + 1);
                    unset($w->visited[$target]);
                    break;
                case 'a':
                    $ips = array_merge($dns->a($target), $dns->aaaa($target));
                    if (!$ips) {
                        $w->voids++;
                        $node['detail'] = lt('Keine Adresse gefunden (leere Abfrage).');
                        $node['level'] = 'warn';
                    } else {
                        $node['detail'] = implode(', ', array_map([IpGuard::class, 'show'], array_slice($ips, 0, 6))) . (count($ips) > 6 ? ' …' : '');
                        foreach ($ips as $ip) $w->addNet($ip, str_contains($ip, ':') ? $t['c6'] : $t['c4'], $depth, $t['raw']);
                    }
                    $w->nodes[] = $node;
                    break;
                case 'mx':
                    $mx = $dns->mx($target) ?? [];
                    if (!$mx) {
                        $w->voids++;
                        $node['detail'] = lt('Keine Mailserver (MX) gefunden (leere Abfrage).');
                        $node['level'] = 'warn';
                    } else {
                        if (count($mx) > 10) $w->errors[] = lt('„{term}“: mehr als 10 MX-Einträge – ungültig (permerror).', ['term' => $t['raw']]);
                        $hosts = [];
                        foreach (array_slice($mx, 0, 10) as $m) {
                            if ($m['host'] === '' || $m['host'] === '.') continue;
                            $hosts[] = $m['host'];
                            foreach (array_merge($dns->a($m['host']), $dns->aaaa($m['host'])) as $ip) $w->addNet($ip, str_contains($ip, ':') ? $t['c6'] : $t['c4'], $depth, $t['raw']);
                        }
                        $node['detail'] = implode(', ', $hosts);
                    }
                    $w->nodes[] = $node;
                    break;
                case 'ptr':
                    $node['level'] = 'warn';
                    $w->warnings[] = lt('„ptr“ ist veraltet (RFC 7208) und wird von großen Anbietern teils ignoriert – besser ip4/ip6 oder a verwenden.');
                    $w->nodes[] = $node;
                    break;
                case 'exists':
                    if (!$dns->a($target)) $w->voids++;
                    $w->nodes[] = $node;
                    break;
                case 'ip4':
                case 'ip6':
                    $w->addNet($t['value'], $t['name'] === 'ip4' ? $t['c4'] : $t['c6'], $depth, $t['raw']);
                    $node['detail'] = IpGuard::isPublic($t['value']) ? '' : lt('Nicht öffentliche Adresse – im Internet wirkungslos.');
                    if ($node['detail'] !== '') $node['level'] = 'warn';
                    $w->nodes[] = $node;
                    break;
                case 'all':
                    if ($depth === 0) $w->all = $t['q'];
                    $w->nodes[] = $node;
                    break;
                default:
                    $w->nodes[] = $node;
            }
        }
        if ($depth === 0 && !$hasAll) {
            $redirect = array_filter($parsed['terms'], fn($t) => $t['kind'] === 'mod' && $t['name'] === 'redirect');
            $w->all = $redirect ? 'redirect' : null;
        }
    }

    /** Domain prüfen → Ergebnis für den Abschnitt „SPF“ */
    public static function check(string $domain, Dns $dns): Result
    {
        $r = new Result('spf', lt('SPF'));
        $txt = $dns->txt($domain);
        if ($txt === null) {
            $r->error(lt('Die TXT-Einträge von {host} ließen sich nicht abfragen (DNS-Fehler oder Zeitüberschreitung).', ['host' => $domain]));
            $r->summary = lt('DNS-Abfrage fehlgeschlagen.');
            return $r;
        }
        $spf = array_values(array_filter($txt, [self::class, 'isSpf']));
        if (!$spf) {
            $r->error(lt('Kein SPF-Eintrag gefunden. Ohne SPF kann jeder Server Mails im Namen von {host} verschicken – und viele Empfänger sortieren deine Mails eher aus.', ['host' => $domain]));
            $r->summary = lt('Kein SPF-Eintrag vorhanden.');
            $old = array_filter($txt, fn($x) => stripos($x, 'spf') !== false);
            if ($old) $r->warn(lt('Es gibt TXT-Einträge mit „spf“, aber keiner beginnt mit „v=spf1“ – vermutlich ein Tippfehler.'));
            $r->info(lt('Nutze den SPF-Generator, um einen passenden Eintrag zu erstellen.'));
            $r->data = ['has' => false];
            return $r;
        }
        return self::report($r, $domain, $spf[0], $dns, count($spf));
    }

    /** Eingefügten Eintrag prüfen (Generator-Tab), optional mit Domain für die Optimierung */
    public static function analyse(string $record, ?string $domain, Dns $dns): Result
    {
        $r = new Result('spf-analyse', lt('SPF-Analyse'));
        $record = trim(preg_replace('~\s+~', ' ', trim($record, " \t\n\r\"'")) ?? '');
        if ($record === '') throw new CheckException(lt('Bitte füge einen SPF-Eintrag ein.'));
        if (strlen($record) > 2048) throw new CheckException(lt('Die Eingabe ist zu lang.'));
        return self::report($r, $domain ?? '', $record, $dns, 1, true);
    }

    private static function report(Result $r, string $domain, string $record, Dns $dns, int $count, bool $pasted = false): Result
    {
        $w = new SpfWalk();
        if ($count > 1) $r->error(lt('{n} SPF-Einträge gefunden – erlaubt ist genau einer. Empfänger werten das als Fehler (permerror): Führe die Einträge zu einem zusammen.', ['n' => $count]));
        self::walk($dns, $domain, $record, $w);
        $r->code(lt('Eintrag'), $record);

        // Lookup-Grenze
        if ($w->lookups > self::MAX_LOOKUPS) {
            $r->error(lt('{n} DNS-Lookups – erlaubt sind höchstens 10 (RFC 7208). Empfänger werten den Eintrag als Fehler (permerror); SPF greift dann nicht.', ['n' => $w->lookups]));
        } elseif ($w->lookups >= 8) {
            $r->warn(lt('{n} von 10 DNS-Lookups – knapp an der Grenze. Ein weiterer Dienst (include) kann SPF brechen.', ['n' => $w->lookups]));
        } else {
            $r->ok(lt('{n} von 10 DNS-Lookups.', ['n' => $w->lookups]));
        }
        if ($w->voids > self::MAX_VOIDS) {
            $r->error(lt('{n} leere DNS-Abfragen (void lookups) – mehr als 2 führen zu einem Fehler (permerror).', ['n' => $w->voids]));
        } elseif ($w->voids > 0) {
            $r->warn(lt('{n} leere DNS-Abfrage(n) (void lookups) – prüfe, ob alle eingetragenen Namen noch existieren.', ['n' => $w->voids]));
        }
        foreach (array_unique($w->errors) as $e) $r->error($e);
        foreach (array_unique($w->warnings) as $e) $r->warn($e);

        match ($w->all) {
            '+' => $r->error(lt('„+all“ erlaubt jedem Server der Welt, in deinem Namen zu senden. Ersetze es durch „~all“ oder „-all“.')),
            '?' => $r->warn(lt('„?all“ (Neutral) schützt praktisch nicht. Besser „~all“ (mit DMARC) oder „-all“.')),
            '~' => $r->ok(lt('„~all“ (SoftFail): nicht erlaubte Server werden markiert – zusammen mit DMARC eine gute Wahl.')),
            '-' => $r->ok(lt('„-all“ (Fail): nicht erlaubte Server werden abgelehnt.')),
            'redirect' => $r->info(lt('Die Regel für alle anderen Server kommt über „redirect=“ aus einem anderen Eintrag.')),
            default => $r->warn(lt('Der Eintrag endet ohne „all“ – für nicht genannte Server gilt dann „Neutral“. Ergänze „~all“ oder „-all“.')),
        };
        $len = strlen($record);
        if ($len > 450) $r->warn(lt('Der Eintrag ist {n} Zeichen lang – sehr lange Einträge passen nicht mehr in eine DNS-Antwort per UDP. Kürzen lohnt sich.', ['n' => $len]));
        if (preg_match('~^v=spf1~', $record) !== 1 && preg_match('~^v=spf1~i', $record)) $r->info(lt('„v=spf1“ sollte klein geschrieben sein.'));

        // Baum der Mechanismen
        $rows = [];
        $group = [];
        // Eingebundene Einträge kompakt: aufeinanderfolgende ip4/ip6 einer Ebene als eine Zeile, deren „all“ weglassen
        $flush = function () use (&$group, &$rows): void {
            if (!$group) return;
            $n = $group[0];
            $list = array_map(fn($g) => substr($g['term'], strpos($g['term'], ':') + 1), $group);
            $rows[] = [[str_repeat('↳ ', $n['depth']) . lt('{n} × ip4/ip6', ['n' => count($group)]), null], 'Pass',
                lt('Adressen und Netze aus dem Eintrag von {host}: {list}', ['host' => $n['domain'], 'list' => implode(', ', array_slice($list, 0, 4)) . (count($list) > 4 ? ' …' : '')]), '–'];
            $group = [];
        };
        foreach ($w->nodes as $n) {
            if ($n['depth'] > 0 && in_array($n['name'], ['ip4', 'ip6'], true) && $n['kind'] === 'mech' && $n['level'] === null && $n['q'] === '+') {
                if ($group && ($group[0]['depth'] !== $n['depth'] || $group[0]['domain'] !== $n['domain'])) $flush();
                $group[] = $n;
                continue;
            }
            $flush();
            if ($n['depth'] > 0 && $n['name'] === 'all' && $n['kind'] === 'mech') continue;
            $indent = str_repeat('↳ ', $n['depth']);
            $rows[] = [
                [$indent . $n['term'], $n['level']],
                $n['kind'] === 'mech' ? self::qualifierLabel($n['q']) : lt('Modifikator'),
                $n['text'] . ($n['detail'] !== '' ? ' — ' . $n['detail'] : ''),
                $n['lookup'] ? (string) ($n['n'] ?? '') : '–',
            ];
        }
        $flush();
        $r->table(lt('Mechanismen (mit aufgelösten includes)'), [lt('Ausdruck'), lt('Ergebnis'), lt('Bedeutung'), lt('Lookup')], $rows,
            lt('Eingerückte Zeilen stammen aus eingebundenen Einträgen. Die Spalte „Lookup“ zählt die DNS-Abfragen bis zur Grenze von 10.'));

        // Optimierung
        $opt = self::optimize($record, $domain !== '' ? $domain : null, $dns);
        if ($opt['changes']) {
            $r->list(lt('Optimierungsvorschläge'), array_map(fn($c) => [$c, 'info'], $opt['changes']));
            if ($opt['record'] !== $record) $r->code(lt('Optimierter Eintrag (Vorschlag)'), $opt['record']);
        } elseif ($pasted) {
            $r->ok(lt('Keine offensichtlichen Optimierungen gefunden.'));
        }
        if ($w->lookups > self::MAX_LOOKUPS) {
            $r->info(lt('Weniger Lookups: nicht mehr genutzte Dienste (include) entfernen, eigene Server als ip4:/ip6: eintragen. „SPF-Flattening“ (alle IPs ausschreiben) hilft, muss aber bei Änderungen der Anbieter nachgepflegt werden.'));
        }

        $r->summary = match (true) {
            $w->lookups > self::MAX_LOOKUPS || $w->voids > self::MAX_VOIDS || $count > 1 || $w->errors => lt('SPF vorhanden, aber fehlerhaft.'),
            $w->all === '+' || $w->all === '?' || $w->all === null => lt('SPF vorhanden, schützt aber kaum.'),
            default => lt('SPF ist gültig ({n} von 10 Lookups).', ['n' => $w->lookups]),
        };
        $r->data = ['has' => true, 'lookups' => $w->lookups, 'voids' => $w->voids, 'all' => $w->all];
        return $r;
    }

    // ------------------------------------------------------------------ Optimierung

    /**
     * Vorschlag für einen aufgeräumten Eintrag. @return array{record: string, changes: list<string>}
     * Regeln: doppelte Ausdrücke weg, a:{domain} → a, mx:{domain} → mx, ip4/ip6 innerhalb eines anderen Netzes weg,
     * a/mx/a:host weg, wenn ihre Adressen schon per ip4/ip6 erlaubt sind (nur mit Domain), ptr weg, +all/?all → ~all,
     * fehlendes all → ~all, Reihenfolge ip4/ip6 → a → mx → include → exists → Modifikatoren → all (günstige Prüfungen zuerst).
     */
    public static function optimize(string $record, ?string $domain, ?Dns $dns = null): array
    {
        $p = self::parse($record);
        if ($p['errors'] && !$p['terms']) return ['record' => $record, 'changes' => []];
        $changes = [];
        $terms = [];
        $seen = [];
        foreach ($p['terms'] as $t) {
            if ($t['error']) {
                $changes[] = lt('„{term}“ entfernt (ungültig).', ['term' => $t['raw']]);
                continue;
            }
            // a:{domain} und mx:{domain} sind dasselbe wie a bzw. mx
            if ($domain && in_array($t['name'], ['a', 'mx'], true) && $t['kind'] === 'mech' && $t['value'] === $domain) {
                $changes[] = lt('„{term}“ entspricht „{short}“ (gleiche Domain).', ['term' => $t['raw'], 'short' => $t['name']]);
                $t['value'] = '';
                $t['raw'] = ($t['q'] !== '+' ? $t['q'] : '') . $t['name'] . ($t['c4'] !== null ? '/' . $t['c4'] : '') . ($t['c6'] !== null ? '//' . $t['c6'] : '');
            }
            if ($t['q'] === '+' && str_starts_with($t['raw'], '+')) $t['raw'] = substr($t['raw'], 1);
            $key = strtolower($t['raw']);
            if (isset($seen[$key])) {
                $changes[] = lt('Doppelten Ausdruck „{term}“ entfernt.', ['term' => $t['raw']]);
                continue;
            }
            $seen[$key] = true;
            if ($t['name'] === 'ptr' && $t['kind'] === 'mech') {
                $changes[] = lt('„{term}“ entfernt – veraltet. Trage die Server stattdessen mit ip4/ip6 oder a ein.', ['term' => $t['raw']]);
                continue;
            }
            $terms[] = $t;
        }
        // ip4/ip6 in einem größeren Netz desselben Eintrags
        $nets = [];
        foreach ($terms as $t) if (in_array($t['name'], ['ip4', 'ip6'], true) && $t['q'] === '+') $nets[] = [$t['value'], $t['name'] === 'ip4' ? ($t['c4'] ?? 32) : ($t['c6'] ?? 128), $t['raw']];
        $terms = array_values(array_filter($terms, function ($t) use ($nets, &$changes) {
            if (!in_array($t['name'], ['ip4', 'ip6'], true) || $t['q'] !== '+') return true;
            $bits = $t['name'] === 'ip4' ? ($t['c4'] ?? 32) : ($t['c6'] ?? 128);
            foreach ($nets as [$ip, $cidr, $raw]) {
                if ($raw !== $t['raw'] && $cidr < $bits && IpGuard::inNet((string) @inet_pton($t['value']), $ip . '/' . $cidr)) {
                    $changes[] = lt('„{term}“ entfernt – liegt schon in „{net}“.', ['term' => $t['raw'], 'net' => $raw]);
                    return false;
                }
            }
            return true;
        }));
        // a/mx, deren Adressen vollständig per ip4/ip6 erlaubt sind (nur mit DNS)
        if ($dns && $nets) {
            $terms = array_values(array_filter($terms, function ($t) use ($dns, $domain, $nets, &$changes) {
                if (!in_array($t['name'], ['a', 'mx'], true) || $t['kind'] !== 'mech' || $t['q'] !== '+' || $t['macro'] || $t['c4'] !== null || $t['c6'] !== null) return true;
                $host = $t['value'] !== '' ? $t['value'] : (string) $domain;
                if ($host === '') return true;
                $ips = [];
                if ($t['name'] === 'a') {
                    $ips = array_merge($dns->a($host), $dns->aaaa($host));
                } else {
                    foreach ($dns->mx($host) ?? [] as $m) $ips = array_merge($ips, $dns->a($m['host']), $dns->aaaa($m['host']));
                }
                if (!$ips) return true;
                foreach ($ips as $ip) {
                    $bin = (string) @inet_pton($ip);
                    $covered = false;
                    foreach ($nets as [$nip, $cidr]) if (IpGuard::inNet($bin, $nip . '/' . $cidr)) { $covered = true; break; }
                    if (!$covered) return true;
                }
                $changes[] = lt('„{term}“ entfernt – alle Adressen sind schon per ip4/ip6 erlaubt (spart einen DNS-Lookup).', ['term' => $t['raw']]);
                return false;
            }));
        }
        // all
        $all = null;
        $hasRedirect = false;
        foreach ($terms as $i => $t) {
            if ($t['name'] === 'all' && $t['kind'] === 'mech') {
                if ($all !== null) $changes[] = lt('Zweites „all“ entfernt.');
                $all ??= $t;
                unset($terms[$i]);
            }
            if ($t['kind'] === 'mod' && $t['name'] === 'redirect') $hasRedirect = true;
        }
        if ($all && in_array($all['q'], ['+', '?'], true)) {
            $changes[] = lt('„{term}“ durch „~all“ ersetzt.', ['term' => $all['raw'] === 'all' ? '+all' : $all['raw']]);
            $all = ['raw' => '~all', 'name' => 'all', 'kind' => 'mech', 'q' => '~'];
        }
        if (!$all && !$hasRedirect) {
            $changes[] = lt('„~all“ am Ende ergänzt.');
            $all = ['raw' => '~all', 'name' => 'all', 'kind' => 'mech', 'q' => '~'];
        }
        if ($all && $hasRedirect) {
            $terms = array_values(array_filter($terms, function ($t) use (&$changes) {
                if ($t['kind'] === 'mod' && $t['name'] === 'redirect') {
                    $changes[] = lt('„{term}“ entfernt – wird neben „all“ nie ausgewertet.', ['term' => $t['raw']]);
                    return false;
                }
                return true;
            }));
        }
        // Reihenfolge (stabil)
        $rank = fn($t) => $t['kind'] === 'mod' ? 6 : (['ip4' => 0, 'ip6' => 1, 'a' => 2, 'mx' => 3, 'include' => 4, 'exists' => 5][$t['name']] ?? 5);
        $before = implode(' ', array_map(fn($t) => $t['raw'], $terms));
        $sorted = array_values($terms);
        uasort($sorted, fn($a, $b) => $rank($a) <=> $rank($b));
        $sorted = array_values($sorted);
        if (implode(' ', array_map(fn($t) => $t['raw'], $sorted)) !== $before) $changes[] = lt('Reihenfolge angepasst: feste Adressen zuerst, dann Namen und includes (spart Abfragen beim Empfänger).');
        $out = 'v=spf1' . ($sorted ? ' ' . implode(' ', array_map(fn($t) => $t['raw'], $sorted)) : '') . ($all ? ' ' . $all['raw'] : '');
        return ['record' => $out, 'changes' => array_values(array_unique($changes))];
    }
}
