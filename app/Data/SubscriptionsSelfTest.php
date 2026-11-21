<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Data;

use Core\Http\Request;

/**
 * php bin/console subscriptions:selftest – Abonnieren per App (Core\Data\Subscriptions) mit vorübergehenden Tabellen:
 *
 *  - Token: Format, nur Hash gespeichert, Kürzel, redact() für Pfade/Protokolle, 404-Protokoll überspringt Token-Pfade, Fehlerantworten ohne Token
 *  - Kalender: Umfang „nur öffentlich“ = veröffentlichte Termine ohne „Nie öffentlich“/„Nur Website“; „intern“ = auch Entwürfe und
 *    interne Angaben, „Nur Website“ nie; Antwort über die Route mit Cache-Control private/no-store und noindex
 *  - Kontakte: öffentlich nur freigegebene Kontakte/Angaben; intern auch nicht freigegebene Kontakte und private Nummern, Vita (Nur Website) nie –
 *    auch während einer Website-Anfrage (Exposure::visibleSql greift dann nicht)
 *  - verify(): falsche Tabelle, Ablauf (410), Widerruf, Konto ohne Recht, Rate-Limit je Token und je IP (429)
 *  - Erweiterung dav (falls geladen): Abo-Token als Passwort → nur lesen, nur die gebundene Tabelle
 * Alles wird danach gelöscht.
 */
final class SubscriptionsSelfTest
{
    private const MOBILE = '0171 5550142';
    private const VITA = 'Vita-Nur-Website-Abo';
    private const NOTE = 'Interne-Notiz-Abo';
    private const HINT = 'Hinweis-Nur-Website-Abo';

    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = \Core\Testing\Check::eq($ok, $fails, 'Abonnieren – ');
        $prevReq = app()->request;
        $made = [];
        $tokens = [];
        Subscriptions::forceForTest(true);
        try {
            $user = app()->db->fetch("SELECT id, role FROM users WHERE (disabled IS NULL OR disabled = 0) AND role = 'admin' ORDER BY id LIMIT 1")
                ?? app()->db->fetch('SELECT id, role FROM users ORDER BY id LIMIT 1');
            $uid = $user ? (int) $user['id'] : null;

            // ---------------------------------------------------------- Kalender
            $ch = 'selftest_abo_' . bin2hex(random_bytes(3));
            [$cdef, $cerr] = Tables::validate(['name' => 'Selbsttest Abo-Termine', 'handle' => $ch, 'fields' => [
                ['label' => 'Titel', 'type' => 'text', 'required' => 1], ['label' => 'Beginn', 'type' => 'datetime'],
                ['label' => 'Notiz', 'name' => 'notiz', 'type' => 'textarea', 'private' => 1],
                ['label' => 'Hinweis', 'name' => 'hinweis', 'type' => 'text', 'exposure' => ['mode' => 'always', 'web' => 1, 'vcard' => 0, 'ics' => 0]],
            ], 'settings' => ['route' => '', 'workflow' => 1, 'calendar' => ['enabled' => true, 'start' => 'beginn', 'description' => 'notiz', 'location' => 'hinweis', 'feed' => false]]]);
            $eq('Termin-Tabelle gültig', $cerr, []);
            Tables::create($cdef);
            $made[] = $ch;
            $ct = Tables::find($ch) ?? throw new \RuntimeException('Termin-Tabelle fehlt');
            $day = date('Y-m-d', strtotime('+3 days'));
            Entries::save($ct, null, ['titel' => 'Offenes Treffen', 'beginn' => "$day 18:00", 'notiz' => self::NOTE, 'hinweis' => self::HINT, 'status' => 'published']);
            Entries::save($ct, null, ['titel' => 'Vorstandssitzung Entwurf', 'beginn' => "$day 20:00", 'status' => 'draft']);
            $eq('Art des Abos', Subscriptions::kind($ct), 'calendar');

            $pub = Subscriptions::calendarBody($ct, 'public');
            $int = Subscriptions::calendarBody($ct, 'internal');
            $eq('Kalender öffentlich: nur veröffentlichte Termine, ohne interne Notiz und „Nur Website“',
                [substr_count($pub, 'BEGIN:VEVENT'), str_contains($pub, 'Entwurf'), str_contains($pub, self::NOTE), str_contains($pub, self::HINT)], [1, false, false, false]);
            $eq('Kalender intern: auch Entwürfe und interne Notiz, „Nur Website“ nie',
                [substr_count($int, 'BEGIN:VEVENT'), str_contains($int, 'Entwurf'), str_contains($int, self::NOTE), str_contains($int, self::HINT)], [2, true, true, false]);

            // Token: Format, Hash, Kürzel
            $a = Subscriptions::create($ct, 'Selbsttest iPhone', 'internal', $uid);
            $tokens[] = $a['id'];
            $row = app()->db->fetch('SELECT * FROM subscription_tokens WHERE id = ?', [$a['id']]) ?? [];
            $eq('Token-Format', Subscriptions::looksLike($a['token']) && !Subscriptions::looksLike($a['token'] . 'x') && !Subscriptions::looksLike('abo-' . str_repeat('l', 24)), true);
            $eq('Nur Hash gespeichert', [in_array($a['token'], array_map('strval', $row), true), $row['token_hash'] ?? null, $row['prefix'] ?? null],
                [false, hash('sha256', $a['token']), substr($a['token'], 4, 4)]);
            $eq('Liste ohne Hash', array_key_exists('token_hash', Subscriptions::forTable($ch)[0] ?? []), false);

            // Keine Tokens in Protokollen und Fehlerantworten
            $path = "/kalender/$ch/abo/{$a['token']}.ics";
            $eq('Sicherheitsprotokoll kürzt den Token', [str_contains(Subscriptions::redact($path), $a['token']), str_contains(Subscriptions::redact($path), 'abo-' . substr($a['token'], 4, 4) . '...')], [false, true]);
            $noLog = (string) (new \ReflectionClassConstant(\Core\Redirects\Redirects::class, 'NO_LOG'))->getValue();
            $eq('404-Protokoll überspringt Token-Pfade, normale Pfade nicht', [(bool) preg_match($noLog, $path), (bool) preg_match($noLog, '/verein/abo/termine')], [true, false]);
            $eq('Fehlerantworten ohne Token, nicht zwischenspeichern', [str_contains(Subscriptions::error(404)->body, 'abo-'), Subscriptions::error(410)->status, Subscriptions::error(429)->headers['Retry-After'] ?? null],
                [false, 410, (string) Subscriptions::RATE_WINDOW]);

            if ($uid !== null) {
                // Route: Antwort mit Token
                app()->request = new Request('GET', $path, [], [], [], []);
                $res = (new \Core\Http\Controllers\SubscriptionController())->calendar(app()->request, $ch, $a['token']);
                $eq('Route Kalender intern: 200, Entwurf enthalten', [$res->status, substr_count($res->body, 'BEGIN:VEVENT')], [200, 2]);
                $eq('Antwort mit Token: privat, kein Cache, noindex, kein Referrer',
                    [$res->headers['Cache-Control'] ?? null, str_contains((string) ($res->headers['X-Robots-Tag'] ?? ''), 'noindex'), $res->headers['Referrer-Policy'] ?? null, str_starts_with((string) ($res->headers['Content-Type'] ?? ''), 'text/calendar')],
                    ['private, no-store, max-age=0', true, 'no-referrer', true]);
                $used = app()->db->fetchValue('SELECT last_used_at FROM subscription_tokens WHERE id = ?', [$a['id']]);
                $eq('„Zuletzt genutzt“ gesetzt', $used !== null && $used !== '', true);
                $eq('Token einer anderen Tabelle passt nicht', (new \Core\Http\Controllers\SubscriptionController())->calendar(app()->request, 'andere_tabelle', $a['token'])->status, 404);
                $eq('Kalender-Token nicht als Kontakte-Abo', (new \Core\Http\Controllers\SubscriptionController())->contacts(app()->request, $ch, $a['token'])->status, 404);
                $eq('Unbekannter Token', Subscriptions::verify('abo-' . str_repeat('a', 24), $ch)['status'], 404);

                // Rate-Limit je Token und je IP
                $lim = new \Core\RateLimiter(app()->db);
                for ($i = 0; $i < Subscriptions::RATE_TOKEN; $i++) $lim->hit('abo:' . $a['id']);
                $eq('Rate-Limit je Token', Subscriptions::verify($a['token'], $ch)['status'], 429);
                $lim->clear('abo:' . $a['id']);
                $eq('Nach dem Fenster wieder frei', Subscriptions::verify($a['token'], $ch)['status'], 200);
                $ipKey = 'abo-ip:' . hash_hmac('sha256', \Core\SecurityLog::clientIp(), app()->key());
                for ($i = 0; $i < Subscriptions::RATE_IP_FAIL; $i++) $lim->hit($ipKey);
                $eq('Rate-Limit je IP nach Fehlversuchen', Subscriptions::verify($a['token'], $ch)['status'], 429);
                $lim->clear($ipKey);
                $lim->clear('abo:' . $a['id']);

                // Ablauf und Widerruf
                app()->db->update('subscription_tokens', ['expires_at' => date('Y-m-d', strtotime('-1 day'))], 'id = :id', ['id' => $a['id']]);
                $eq('Abgelaufen → 410', Subscriptions::verify($a['token'], $ch)['status'], 410);
                app()->db->update('subscription_tokens', ['expires_at' => date('Y-m-d')], 'id = :id', ['id' => $a['id']]);
                $eq('Gilt bis einschließlich Ablauftag', Subscriptions::verify($a['token'], $ch)['status'], 200);
                $eq('Widerruf', [Subscriptions::revoke($ch, $a['id']), Subscriptions::verify($a['token'], $ch)['status']], [true, 404]);
                // Konto ohne Recht bzw. gelöscht: Token gilt nicht
                $orphan = Subscriptions::create($ct, 'Ohne Konto', 'public', 999999999);
                $tokens[] = $orphan['id'];
                $eq('Konto gelöscht → ungültig', Subscriptions::verify($orphan['token'], $ch)['status'], 404);
                $lim->clear($ipKey);
            }

            // ---------------------------------------------------------- Kontakte
            $kh = 'selftest_abk_' . bin2hex(random_bytes(3));
            $def = \Core\Http\Controllers\Admin\DataController::presetDef('contacts');
            $def['handle'] = $kh;
            $def['name'] = 'Selbsttest Abo-Kontakte';
            $def['settings']['route'] = '';
            $def['fields'][] = ['label' => 'Vita', 'name' => 'vita', 'type' => 'richtext', 'exposure' => ['mode' => 'always', 'web' => 1, 'vcard' => 0, 'ics' => 0]];
            [$kdef, $kerr] = Tables::validate($def);
            $eq('Adressbuch gültig', $kerr, []);
            Tables::create($kdef);
            $made[] = $kh;
            $kt = Tables::find($kh) ?? throw new \RuntimeException('Adressbuch fehlt');
            Entries::save($kt, null, ['vorname' => 'Erika', 'nachname' => 'Musterfrau', 'oeffentlich' => true, 'status' => 'published', 'vita' => '<p>' . self::VITA . '</p>',
                'telefon' => [['label' => 'work', 'value' => '02841 5550100', 'public' => '1'], ['label' => 'mobile', 'value' => self::MOBILE, 'public' => '0']]]);
            Entries::save($kt, null, ['vorname' => 'Intern', 'nachname' => 'Musterkontakt', 'status' => 'published']);
            $digits = fn(string $s) => str_replace([' ', '-', '/'], '', $s);
            $vp = Subscriptions::contactsBody($kt, 'public');
            $vi = Subscriptions::contactsBody($kt, 'internal');
            $eq('Kontakte öffentlich: nur freigegebene, ohne Mobilnummer und Vita',
                [substr_count($vp, 'BEGIN:VCARD'), str_contains($vp, 'Musterkontakt'), str_contains($digits($vp), '5550142'), str_contains($vp, self::VITA)], [1, false, false, false]);
            $eq('Kontakte intern: alle, mit Mobilnummer, Vita nie',
                [substr_count($vi, 'BEGIN:VCARD'), str_contains($vi, 'Musterkontakt'), str_contains($digits($vi), '5550142'), str_contains($vi, self::VITA)], [2, true, true, false]);
            $eq('Öffentliche Kontakte-Datei möglich (Funktion an, Tabelle öffentlich)', Subscriptions::publicContacts($kt), true);

            if ($uid !== null) {
                $k = Subscriptions::create($kt, 'Selbsttest Adressbuch', 'internal', $uid);
                $kp = Subscriptions::create($kt, 'Selbsttest öffentlich', 'public', $uid);
                $tokens[] = $k['id'];
                $tokens[] = $kp['id'];
                // Während einer Website-Anfrage filtert Entries::query Kontakte ohne Freigabe – „intern“ muss sie trotzdem liefern
                app()->request = new Request('GET', "/vcard/$kh/abo/{$k['token']}.vcf", [], [], [], []);
                $r1 = (new \Core\Http\Controllers\SubscriptionController())->contacts(app()->request, $kh, $k['token']);
                $r2 = (new \Core\Http\Controllers\SubscriptionController())->contacts(app()->request, $kh, $kp['token']);
                $eq('Route Kontakte: intern 2, öffentlich 1 (Website-Anfrage)', [$r1->status, substr_count($r1->body, 'BEGIN:VCARD'), $r2->status, substr_count($r2->body, 'BEGIN:VCARD')], [200, 2, 200, 1]);
                $eq('Website-Filter danach wieder aktiv', Subscriptions::inInternal(), false);
                $r3 = (new \Core\Http\Controllers\SubscriptionController())->contactsPublic(new Request('GET', "/vcard/$kh.vcf", [], [], [], []), $kh);
                $eq('Öffentliche Kontakte-Datei: 1 Kontakt, öffentlich zwischenspeicherbar', [$r3->status, substr_count($r3->body, 'BEGIN:VCARD'), $r3->headers['Cache-Control'] ?? null], [200, 1, 'public, max-age=900']);

                if (class_exists(\Klxm\Dav\Dav::class)) {
                    $prevUser = \Klxm\Dav\Dav::$user;
                    $eq('CardDAV: Abo-Token als Passwort', \Klxm\Dav\Dav::login('abo', $kp['token']), true);
                    $eq('CardDAV: nur die gebundene Tabelle, nur lesen', [\Klxm\Dav\Dav::canSee($kt), \Klxm\Dav\Dav::canSee($ct), \Klxm\Dav\Dav::canWrite($kt)], [true, false, false]);
                    $eq('CardDAV „nur öffentlich“: nicht freigegebener Kontakt fehlt',
                        array_map(fn($e) => \Klxm\Dav\Dav::aboAllows($kt, $e), Entries::query($kt, ['status' => 'all', 'internal' => true, 'sort' => 'id', 'dir' => 'asc'])), [true, false]);
                    $eq('CardDAV: Kalender-Token gilt nicht als Adressbuch', \Klxm\Dav\Dav::login('abo', Subscriptions::create($ct, 'x', 'public', $uid)['token']), false);
                    \Klxm\Dav\Dav::$user = $prevUser;
                }
            }
        } catch (\Throwable $x) {
            $fails[] = 'Abonnieren – Ausnahme: ' . $x->getMessage() . ' (' . basename($x->getFile()) . ':' . $x->getLine() . ')';
        } finally {
            Subscriptions::forceForTest(false);
            app()->request = $prevReq;
            foreach ($made as $mh) {
                app()->db->query('DELETE FROM subscription_tokens WHERE table_handle = ?', [$mh]);
                if ($x = Tables::find($mh)) Tables::delete($x);
            }
            Tables::flush();
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
