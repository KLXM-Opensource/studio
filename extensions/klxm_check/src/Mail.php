<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * Mailserver: MX-Einträge, deren Adressen (A/AAAA), Reverse DNS (PTR) und Forward-confirmed reverse DNS (FCrDNS),
 * optional STARTTLS auf Port 25 beim ersten erreichbaren Mailserver (viele Hoster sperren ausgehend Port 25 –
 * das wird als Hinweis gemeldet, nicht als Fehler der geprüften Domain).
 */
final class Mail
{
    public const MAX_MX = 6;

    public static function check(string $domain, Dns $dns, bool $smtp = true): Result
    {
        $r = new Result('mail', lt('Mailserver (MX & PTR)'));
        $mx = $dns->mx($domain);
        if ($mx === null) {
            $r->error(lt('Die MX-Einträge ließen sich nicht abfragen (DNS-Fehler oder Zeitüberschreitung).'));
            $r->summary = lt('DNS-Abfrage fehlgeschlagen.');
            return $r;
        }
        if (count($mx) === 1 && in_array($mx[0]['host'], ['', '.'], true)) {
            $r->info(lt('„Null MX“ (RFC 7505): {host} empfängt ausdrücklich keine Mails.', ['host' => $domain]));
            $r->summary = lt('Die Domain empfängt keine Mails (Null MX).');
            $r->status = 'info';
            return $r;
        }
        if (!$mx) {
            $a = $dns->a($domain);
            $r->warn($a
                ? lt('Keine MX-Einträge – Mails werden dann direkt an die Adresse der Domain (A-Eintrag) zugestellt. Wenn die Domain Mails empfangen soll, MX eintragen; sonst „Null MX“ (MX 0 .).')
                : lt('Keine MX-Einträge und keine Adresse – die Domain kann keine Mails empfangen.'));
            $r->summary = lt('Keine Mailserver (MX) eingetragen.');
            return $r;
        }
        $rows = [];
        $ptrProblems = 0;
        $firstPublic = null;
        foreach (array_slice($mx, 0, self::MAX_MX) as $m) {
            $host = $m['host'];
            if (!Domain::validDnsName($host)) {
                $rows[] = [(string) $m['pri'], [$host, 'error'], '–', '–', [lt('ungültiger Name'), 'error']];
                $r->error(lt('MX „{host}“ ist kein gültiger Hostname.', ['host' => $host]));
                continue;
            }
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                $r->error(lt('MX „{host}“ ist eine IP-Adresse – erlaubt sind nur Hostnamen.', ['host' => $host]));
            }
            if ($cn = $dns->cname($host)) {
                $r->warn(lt('MX „{host}“ ist ein CNAME (auf {target}) – laut RFC 2181 nicht erlaubt, manche Absender stellen dann nicht zu.', ['host' => $host, 'target' => $cn]));
            }
            $ips = array_merge(array_slice($dns->a($host), 0, 2), array_slice($dns->aaaa($host), 0, 2));
            if (!$ips) {
                $rows[] = [(string) $m['pri'], $host, [lt('keine Adresse'), 'error'], '–', '–'];
                $r->error(lt('MX „{host}“ hat keine IP-Adresse – dorthin kann nichts zugestellt werden.', ['host' => $host]));
                continue;
            }
            foreach ($ips as $ip) {
                if (!IpGuard::isPublic($ip)) {
                    $rows[] = [(string) $m['pri'], $host, [IpGuard::show($ip), 'error'], '–', '–'];
                    $r->error(lt('MX „{host}“ zeigt auf eine nicht öffentliche Adresse – aus dem Internet nicht erreichbar.', ['host' => $host]));
                    continue;
                }
                $firstPublic ??= [$host, $ip];
                $f = $dns->fcrdns($ip);
                $ptr = $f['ptr'] ? implode(', ', $f['ptr']) : lt('kein PTR');
                if (!$f['ptr'] || !$f['fcrdns']) $ptrProblems++;
                $rows[] = [(string) $m['pri'], $host, $ip, [$ptr, $f['ptr'] ? null : 'warn'], [$f['fcrdns'] ? lt('bestätigt') : lt('nicht bestätigt'), $f['fcrdns'] ? 'ok' : 'warn']];
            }
        }
        if (count($mx) > self::MAX_MX) $r->info(lt('Es gibt {n} MX-Einträge – geprüft wurden die ersten {max}.', ['n' => count($mx), 'max' => self::MAX_MX]));
        $r->table(lt('Mailserver'), [lt('Priorität'), lt('Host'), lt('IP-Adresse'), lt('Reverse DNS (PTR)'), 'FCrDNS'], $rows,
            lt('FCrDNS: Der PTR-Name der IP zeigt wieder auf dieselbe IP. Das ist für ausgehende Mailserver wichtig; für reine Empfangsserver ist es nur ein Hinweis.'));
        if ($ptrProblems) {
            $r->warn(lt('{n} Adresse(n) ohne passenden Reverse-DNS (PTR/FCrDNS). Versendet dieser Server auch Mails, stufen Empfänger sie oft als Spam ein – PTR beim Hoster/Provider der IP setzen lassen.', ['n' => $ptrProblems]));
        } elseif ($rows) {
            $r->ok(lt('Alle Mailserver haben einen passenden Reverse-DNS (FCrDNS).'));
        }
        if (count($mx) === 1) $r->info(lt('Nur ein Mailserver eingetragen – fällt er aus, stellen Absender verzögert zu (sie versuchen es mehrere Tage erneut). Ein zweiter MX ist optional.'));
        $v6 = (bool) array_filter($mx, fn($m) => Domain::validDnsName($m['host']) && $dns->aaaa($m['host']));
        $r->add($v6 ? 'ok' : 'info', $v6 ? lt('Mailserver per IPv6 erreichbar.') : lt('Kein Mailserver mit IPv6-Adresse (AAAA).'));

        // STARTTLS auf Port 25 (erster öffentlicher Mailserver)
        if ($smtp && $firstPublic) {
            [$host, $ip] = $firstPublic;
            $hs = Tls::handshake($host, $ip, 25, 'smtp', false);
            if ($hs['ok'] && ($leaf = $hs['chain'][0] ?? null)) {
                $c = Tls::certInfo($leaf, $host);
                $r->ok(lt('{host} bietet STARTTLS an ({p}, {cipher}).', ['host' => $host, 'p' => $hs['protocol'], 'cipher' => $hs['cipher']]));
                if (!$c['match']) $r->info(lt('Das Zertifikat von {host} passt nicht zum MX-Namen – bei Mailservern üblich und meist unkritisch; für MTA-STS/DANE muss es passen.', ['host' => $host]));
                if ($c['days'] < 0) $r->warn(lt('Das Zertifikat von {host} ist abgelaufen.', ['host' => $host]));
                elseif ($c['days'] < 21) $r->warn(lt('Das Zertifikat von {host} läuft in {n} Tag(en) ab.', ['host' => $host, 'n' => $c['days']]));
                $r->kv(lt('TLS des Mailservers (Port 25)'), [
                    ['Host', $host], [lt('Protokoll'), $hs['protocol']], [lt('Verschlüsselung'), $hs['cipher']],
                    [lt('Zertifikat für'), $c['cn'] . ($c['sans'] ? ' (' . implode(', ', array_slice($c['sans'], 0, 5)) . ')' : '')],
                    [lt('Aussteller'), $c['issuerName']], [lt('Gültig bis'), $c['to'] ? date_local($c['to']) : ''],
                ]);
            } elseif ($hs['stage'] === 'nostarttls') {
                $r->error(lt('{host} bietet kein STARTTLS an – Mails an dich werden unverschlüsselt übertragen.', ['host' => $host]));
            } elseif ($hs['stage'] === 'connect') {
                $r->info(lt('Port 25 von {host} ist von hier aus nicht erreichbar – oft sperrt der Hoster dieses Prüf-Servers ausgehende Verbindungen auf Port 25. Kein Fehler deiner Domain; TLS lässt sich im SSL/TLS-Checker auch über Port 465/587 prüfen.', ['host' => $host]));
            } else {
                $r->warn(lt('STARTTLS bei {host} fehlgeschlagen: {msg}', ['host' => $host, 'msg' => $hs['error']]));
            }
        }
        $r->summary = match ($r->worst()) {
            'error' => lt('Probleme bei den Mailservern gefunden.'),
            'warn' => lt('Mailserver erreichbar, mit Hinweisen.'),
            default => lt('{n} Mailserver, sauber eingerichtet.', ['n' => count($mx)]),
        };
        $r->data = ['mx' => count($mx)];
        return $r;
    }
}
