<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * Hosting & DNS: Adressen der Domain und von www (A/AAAA), Nameserver (NS), SOA, CAA, Reverse DNS der Web-Adressen,
 * DNSSEC (DS-Eintrag in der übergeordneten Zone und AD-Bit des Resolvers, sofern ermittelbar) und IPv6-Bereitschaft.
 * Nicht öffentliche Adressen aus DNS-Antworten werden nie angezeigt (keine internen Netze preisgeben).
 */
final class Hosting
{
    public static function check(string $domain, Dns $dns): Result
    {
        $r = new Result('hosting', lt('Hosting & DNS'));
        $a = $dns->a($domain);
        $aaaa = $dns->aaaa($domain);
        $www = 'www.' . $domain;
        $wwwA = str_starts_with($domain, 'www.') ? [] : $dns->a($www);
        $wwwAAAA = str_starts_with($domain, 'www.') ? [] : $dns->aaaa($www);
        $ns = $dns->ns($domain);
        $soa = $dns->soa($domain);
        // NS/SOA gelten für die Zone – bei Subdomains ggf. die der übergeordneten Domain
        $zone = $domain;
        if (!$ns && !$soa && ($org = Domain::orgDomain($domain)) !== $domain) {
            $zone = $org;
            $ns = $dns->ns($org);
            $soa = $dns->soa($org);
        }
        if (!$a && !$aaaa && !$ns && !$soa && !$dns->mx($domain)) {
            $r->error(lt('Zu {host} gibt es keine DNS-Einträge – existiert die Domain?', ['host' => $domain]));
            $r->summary = lt('Domain nicht gefunden.');
            return $r;
        }
        $private = array_filter(array_merge($a, $aaaa), fn($ip) => !IpGuard::isPublic($ip));
        if ($private) $r->warn(lt('Die Domain zeigt (auch) auf nicht öffentliche Adressen – aus dem Internet nicht erreichbar. Die Adressen werden hier nicht angezeigt.'));
        if (!$a && !$aaaa) $r->info(lt('{host} hat keine Web-Adresse (A/AAAA).', ['host' => $domain]));

        // Reverse DNS der Web-Adressen
        $ptrRows = [];
        foreach (array_slice(array_merge($a, $aaaa), 0, 4) as $ip) {
            if (!IpGuard::isPublic($ip)) continue;
            $ptr = $dns->ptr($ip);
            $ptrRows[] = [$ip, $ptr ? implode(', ', $ptr) : [lt('kein PTR'), 'info']];
        }

        $r->kv(lt('Adressen'), [
            ['A (IPv4)', implode(', ', array_map([IpGuard::class, 'show'], $a)) ?: '–'],
            ['AAAA (IPv6)', implode(', ', array_map([IpGuard::class, 'show'], $aaaa)) ?: '–', $aaaa ? 'ok' : 'info'],
            [$www, str_starts_with($domain, 'www.') ? '' : (implode(', ', array_map([IpGuard::class, 'show'], array_merge($wwwA, $wwwAAAA))) ?: lt('nicht vorhanden'))],
        ]);
        if ($ptrRows) $r->table(lt('Reverse DNS der Web-Adressen'), [lt('IP-Adresse'), 'PTR'], $ptrRows);

        // Nameserver und Anbieter-Hinweis
        $providers = array_values(array_unique(array_map(fn($h) => Domain::orgDomain($h), $ns)));
        if ($ns) {
            if (count($ns) < 2) $r->warn(lt('Nur ein Nameserver – empfohlen sind mindestens zwei (Ausfallsicherheit).'));
            else $r->ok(lt('{n} Nameserver eingetragen.', ['n' => count($ns)]));
        }
        $nsV6 = (bool) array_filter(array_slice($ns, 0, 4), fn($h) => $dns->aaaa($h));
        $soaRows = [];
        if ($soa) {
            $soaRows = [
                [lt('Primärer Nameserver'), (string) ($soa['mname'] ?? '')],
                [lt('Verantwortlich'), self::rname((string) ($soa['rname'] ?? ''))],
                [lt('Seriennummer'), (string) ($soa['serial'] ?? '')],
                ['Refresh / Retry / Expire', ($soa['refresh'] ?? '') . ' / ' . ($soa['retry'] ?? '') . ' / ' . ($soa['expire'] ?? '') . ' s'],
                [lt('Negativ-TTL'), ($soa['minimum-ttl'] ?? '') . ' s'],
            ];
        }
        $r->kv(lt('DNS-Zone {zone}', ['zone' => $zone]), array_merge([
            [lt('Nameserver'), implode(', ', $ns) ?: '–'],
            [lt('DNS-Anbieter (aus den Nameservern)'), implode(', ', $providers)],
        ], $soaRows));

        // CAA
        $caa = $dns->caa($zone);
        if ($caa) {
            $issuers = array_map(fn($c) => $c['tag'] . ' ' . $c['value'], $caa);
            $r->ok(lt('CAA-Eintrag vorhanden: nur diese Stellen dürfen Zertifikate ausstellen ({list}).', ['list' => implode(', ', array_slice($issuers, 0, 6))]));
        } else {
            $r->info(lt('Kein CAA-Eintrag – jede Zertifizierungsstelle darf Zertifikate für die Domain ausstellen. Optional: z. B. „0 issue \"letsencrypt.org\"“.'));
        }

        // DNSSEC
        $sec = DnsWire::dnssec($zone);
        if ($sec === null) {
            $dnssec = lt('nicht ermittelbar');
        } elseif ($sec['ds']) {
            $dnssec = $sec['ad'] ? lt('aktiv und vom Resolver bestätigt') : lt('aktiv (DS-Eintrag vorhanden)');
            $r->ok(lt('DNSSEC ist aktiv – DNS-Antworten sind signiert.'));
        } else {
            $dnssec = lt('nicht aktiv');
            $r->info(lt('DNSSEC ist nicht aktiv. Es schützt vor gefälschten DNS-Antworten und lässt sich bei vielen Registraren per Klick einschalten.'));
        }

        // IPv6-Bereitschaft
        $mx = $dns->mx($domain) ?? [];
        $mxV6 = (bool) array_filter(array_slice($mx, 0, 4), fn($m) => Domain::validDnsName($m['host']) && $dns->aaaa($m['host']));
        $v6 = [lt('Website') => (bool) ($aaaa || $wwwAAAA), lt('Mail') => $mx ? $mxV6 : null, lt('Nameserver') => $ns ? $nsV6 : null];
        $r->kv(lt('Sicherheit & IPv6'), [
            ['DNSSEC', $dnssec, $sec === null ? null : ($sec['ds'] ? 'ok' : 'info')],
            ['CAA', $caa ? implode(', ', array_map(fn($c) => $c['tag'] . ' ' . $c['value'], array_slice($caa, 0, 6))) : lt('nicht gesetzt'), $caa ? 'ok' : 'info'],
            ...array_map(fn($k, $v) => ['IPv6 ' . $k, $v === null ? lt('nicht zutreffend') : ($v ? lt('ja') : lt('nein')), $v === null ? null : ($v ? 'ok' : 'info')], array_keys($v6), $v6),
        ]);
        $ready = array_filter($v6, fn($v) => $v !== null);
        if ($ready && !in_array(false, $ready, true)) $r->ok(lt('IPv6-bereit: Website, Mail und Nameserver haben IPv6-Adressen.'));
        elseif (!$v6[lt('Website')]) $r->info(lt('Die Website hat keine IPv6-Adresse (AAAA) – noch kein Muss, aber zeitgemäß.'));

        $r->summary = $a || $aaaa
            ? lt('{n} Web-Adresse(n), {ns} Nameserver.', ['n' => count($a) + count($aaaa), 'ns' => count($ns)])
            : lt('Keine Web-Adresse, {ns} Nameserver.', ['ns' => count($ns)]);
        return $r;
    }

    /** SOA-rname „hostmaster.example.com“ → „hostmaster@example.com“ */
    private static function rname(string $r): string
    {
        $r = rtrim($r, '.');
        $pos = strpos($r, '.');
        return $pos === false ? $r : substr($r, 0, $pos) . '@' . substr($r, $pos + 1);
    }
}
