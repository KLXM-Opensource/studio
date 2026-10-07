<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Push;

use Core\Http\Controllers\PushController;
use Core\Http\Request;

/**
 * php bin/console push:selftest – ohne Netz und ohne bleibende Änderungen:
 *  - Verschlüsselung: Testvektor aus RFC 8291 (Anhang A) Byte für Byte, Rundlauf mit Empfänger-Schlüsseln (wie ein Browser
 *    entschlüsselt), verschiedene Längen und Füllbytes, Manipulation und falsches Geheimnis werden erkannt, Höchstlänge;
 *  - Schlüssel: Erzeugen, Paar passt, ungültige Punkte; VAPID-JWT (ES256): Kopf, Angaben, Signatur prüfen, Manipulation,
 *    fremder Schlüssel, DER ↔ roh; Kopfzeile „vapid t=…, k=…“;
 *  - Endpunkte (nur https, nur bekannte Push-Dienste), Abo-Prüfung, Inhalt (gekürzt, ohne HTML/Notizen, absolute Adresse);
 *  - Ereignisse (Kern, Erweiterung über Extension::pushEvent), Einstellungen der Tabellen (Topics::validate, Filter, Titel);
 *  - Datenbank in einer Transaktion, die am Ende zurückgerollt wird: Abos, Personen-Mitteilung, Thema mit Sprache, alter
 *    Schlüssel, Grenze je Thema, Versand mit vorgetäuschtem Push-Dienst (201, 410 → Abo gelöscht, 429 → später, 400 → Fehler);
 *  - Service Worker (Handler, Platzhalter), Herkunftsprüfung der öffentlichen Schnittstelle.
 */
final class SelfTest
{
    private static int $ok = 0;
    private static array $fail = [];

    private static function eq(string $what, mixed $got, mixed $want): void
    {
        if ($got === $want) { self::$ok++; return; }
        self::$fail[] = $what . ': erwartet ' . json_encode($want, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ', erhalten ' . json_encode($got, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function run(): array
    {
        self::$ok = 0;
        self::$fail = [];
        try {
            self::crypto();
            self::jwt();
            self::endpoints();
            self::payload();
            self::events();
            self::topics();
            self::serviceWorker();
            self::database();
        } catch (\Throwable $e) {
            self::$fail[] = 'Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            Push::$force = null;
            Push::$transport = null;
            Keys::reset();
            Push::flush();
        }
        return ['ok' => self::$ok, 'fails' => self::$fail];
    }

    // ================================================================= Verschlüsselung

    private static function crypto(): void
    {
        $bin = random_bytes(37);
        self::eq('Base64url Rundlauf', WebPush::unb64u(WebPush::b64u($bin)), $bin);
        self::eq('Base64url ungültig', WebPush::unb64u('ab$c'), false);

        // RFC 8291, Anhang A
        $u = fn(string $s) => (string) WebPush::unb64u($s);
        $as = ['private' => $u('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw'), 'public' => $u('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8')];
        $uaPriv = $u('q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94');
        $uaPub = $u('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4');
        $auth = $u('BTBZMqHH6r4Tts7J_aSIgg');
        $msg = WebPush::encrypt('When I grow up, I want to be a watermelon', $uaPub, $auth, $as, $u('DGv6ra1nlYgDCS1FRnbzlw'));
        self::eq('RFC 8291 Testvektor (Nachricht Byte für Byte)', WebPush::b64u($msg),
            'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN');
        self::eq('RFC 8291 Testvektor entschlüsseln', WebPush::decrypt($msg, $uaPriv, $uaPub, $auth), 'When I grow up, I want to be a watermelon');
        self::eq('RFC 8291: Schlüsselpaare passen', [WebPush::keysMatch($as['private'], $as['public']), WebPush::keysMatch($uaPriv, $uaPub)], [true, true]);

        // Rundlauf wie mit einem echten Browser: Empfänger-Schlüssel + Auth-Geheimnis
        $ua = WebPush::generateKeys();
        $secret = random_bytes(16);
        self::eq('Schlüssel: Längen', [strlen($ua['public']), strlen($ua['private']), $ua['public'][0]], [65, 32, "\x04"]);
        self::eq('Schlüssel: Paar passt', WebPush::keysMatch($ua['private'], $ua['public']), true);
        self::eq('Schlüssel: fremdes Paar passt nicht', WebPush::keysMatch($ua['private'], WebPush::generateKeys()['public']), false);
        self::eq('Gültiger Punkt', WebPush::validPublic($ua['public']), true);
        self::eq('Ungültiger Punkt (nicht auf der Kurve)', WebPush::validPublic("\x04" . str_repeat("\x01", 64)), false);
        self::eq('Ungültiger Punkt (komprimiert/kurz)', WebPush::validPublic("\x02" . random_bytes(32)), false);
        foreach ([0, 1, 120, 1000, WebPush::MAX_PAYLOAD] as $len) {
            $plain = $len ? substr(str_repeat('Grüße aus Musterstadt – ', 200), 0, $len) : '';
            foreach ([0, 17] as $pad) {
                $body = WebPush::encrypt($plain, $ua['public'], $secret, null, null, $pad);
                self::eq("Rundlauf $len Byte, $pad Füllbytes", WebPush::decrypt($body, $ua['private'], $ua['public'], $secret), $plain);
            }
        }
        $body = WebPush::encrypt('{"title":"Test"}', $ua['public'], $secret);
        self::eq('Kopf: Salz 16 + rs 4096 + Schlüssel 65', [strlen($body) - strlen('{"title":"Test"}') - 1 - 16, unpack('N', substr($body, 16, 4))[1], ord($body[20])], [86, 4096, 65]);
        $bad = $body;
        $bad[strlen($bad) - 5] = chr(ord($bad[strlen($bad) - 5]) ^ 1);
        self::eq('Manipulierte Nachricht erkannt', WebPush::decrypt($bad, $ua['private'], $ua['public'], $secret), null);
        self::eq('Falsches Auth-Geheimnis erkannt', WebPush::decrypt($body, $ua['private'], $ua['public'], random_bytes(16)), null);
        self::eq('Zwei Nachrichten: verschiedene Wegwerf-Schlüssel', substr(WebPush::encrypt('x', $ua['public'], $secret), 21, 65) !== substr(WebPush::encrypt('x', $ua['public'], $secret), 21, 65), true);
        try {
            WebPush::encrypt(str_repeat('x', WebPush::MAX_PAYLOAD + 1), $ua['public'], $secret);
            self::eq('Zu lange Nachricht abgelehnt', false, true);
        } catch (\LengthException) {
            self::eq('Zu lange Nachricht abgelehnt', true, true);
        }
    }

    // ================================================================= VAPID

    private static function jwt(): void
    {
        $keys = WebPush::generateKeys();
        $h = WebPush::vapidHeader('https://fcm.googleapis.com/fcm/send/abc:def', 'mailto:redaktion@example.org', $keys, time() + 3600);
        self::eq('Kopfzeile „vapid t=…, k=…“', (bool) preg_match('~^vapid t=([A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+), k=([A-Za-z0-9_-]+)$~', $h, $m), true);
        self::eq('k = öffentlicher Schlüssel', WebPush::unb64u($m[2] ?? ''), $keys['public']);
        $jwt = (string) ($m[1] ?? '');
        $head = json_decode((string) WebPush::unb64u(explode('.', $jwt)[0]), true);
        self::eq('JWT-Kopf', $head, ['typ' => 'JWT', 'alg' => 'ES256']);
        $claims = WebPush::verifyJwt($jwt, $keys['public']);
        self::eq('JWT: Signatur gültig, aud = Ursprung des Dienstes', $claims['aud'] ?? null, 'https://fcm.googleapis.com');
        self::eq('JWT: sub', $claims['sub'] ?? null, 'mailto:redaktion@example.org');
        self::eq('JWT: exp in der Zukunft, ≤ 24 h', isset($claims['exp']) && $claims['exp'] > time() && $claims['exp'] <= time() + 86400, true);
        self::eq('JWT: Signatur 64 Byte (r||s)', strlen((string) WebPush::unb64u(explode('.', $jwt)[2])), 64);
        [$a, $b, $c] = explode('.', $jwt);
        $forged = $a . '.' . WebPush::b64u((string) json_encode(['aud' => 'https://fcm.googleapis.com', 'exp' => time() + 99999, 'sub' => 'mailto:x@example.org'])) . '.' . $c;
        self::eq('JWT: geänderte Angaben erkannt', WebPush::verifyJwt($forged, $keys['public']), null);
        self::eq('JWT: fremder Schlüssel erkannt', WebPush::verifyJwt($jwt, WebPush::generateKeys()['public']), null);
        // DER ↔ roh mit echten openssl-Signaturen (auch mit führenden Null-Bytes in r/s)
        $okAll = true;
        for ($i = 0; $i < 24; $i++) {
            $data = random_bytes(20);
            openssl_sign($data, $der, WebPush::privateKey($keys['private'], $keys['public']), OPENSSL_ALGO_SHA256);
            $raw = WebPush::derToRaw($der);
            $okAll = $okAll && strlen($raw) === 64 && openssl_verify($data, WebPush::rawToDer($raw), WebPush::publicKey($keys['public']), OPENSSL_ALGO_SHA256) === 1;
        }
        self::eq('DER ↔ roh (24 Signaturen)', $okAll, true);
        // Ein JWT je Ursprung: Mozilla hat einen anderen aud
        $c2 = WebPush::verifyJwt(explode(', ', substr(WebPush::vapidHeader('https://updates.push.services.mozilla.com/wpush/v2/x', 'mailto:a@example.org', $keys), 8))[0], $keys['public']);
        self::eq('JWT: aud Mozilla', $c2['aud'] ?? null, 'https://updates.push.services.mozilla.com');
    }

    // ================================================================= Endpunkte, Abo, Inhalt

    private static function endpoints(): void
    {
        foreach (['https://fcm.googleapis.com/fcm/send/abc', 'https://updates.push.services.mozilla.com/wpush/v2/gAAA', 'https://web.push.apple.com/QF3k',
            'https://wns2-db5p.notify.windows.com/w/?token=AwYAAA', 'https://fcm.googleapis.com:443/wp/x'] as $ok) {
            self::eq("Endpunkt erlaubt: $ok", Push::endpointAllowed($ok), true);
        }
        foreach (['http://fcm.googleapis.com/fcm/send/abc', 'https://evil.example/x', 'https://fcm.googleapis.com.evil.example/x', 'https://127.0.0.1/x',
            'https://localhost/x', 'https://[::1]/x', 'https://fcm.googleapis.com:8443/x', 'https://u:p@fcm.googleapis.com/x', 'ftp://fcm.googleapis.com/x',
            'https://xfcm.googleapis.com/x', 'javascript:alert(1)', 'https://fcm.googleapis.com/' . str_repeat('a', 1100)] as $bad) {
            self::eq('Endpunkt abgelehnt: ' . mb_substr($bad, 0, 60), Push::endpointAllowed($bad), false);
        }
        $ua = WebPush::generateKeys();
        $sub = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/selbsttest', 'keys' => ['p256dh' => WebPush::b64u($ua['public']), 'auth' => WebPush::b64u(random_bytes(16))]];
        self::eq('Abo gültig', is_array(Push::parse($sub)), true);
        self::eq('Abo: Auth zu kurz', Push::parse(['keys' => ['auth' => WebPush::b64u(random_bytes(8))] + $sub['keys']] + $sub), null);
        self::eq('Abo: p256dh kein Punkt', Push::parse(['keys' => ['p256dh' => WebPush::b64u(random_bytes(65))] + $sub['keys']] + $sub), null);
        self::eq('Abo: fremder Endpunkt', Push::parse(['endpoint' => 'https://evil.example/x'] + $sub), null);
        self::eq('Abo: kein Array', Push::parse('x'), null);
        self::eq('Gerät aus User-Agent', [Push::deviceLabel('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1'),
            Push::deviceLabel('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36'),
            Push::deviceLabel('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0')], ['Safari · iPhone', 'Chrome · macOS', 'Firefox · Windows']);
        self::eq('Themen normalisiert', Push::topicsString(['data:b', 'data:a', 'data:a', 'BÖSE', '']), ',data:a,data:b,');
        self::eq('Themen leer', Push::topicsString([]), null);
    }

    private static function payload(): void
    {
        $p = Push::payload(['title' => '<b>Neu</b>: Sommerfest [# intern: Datum prüfen #]', 'body' => str_repeat('Text mit <i>HTML</i> ', 40), 'url' => '/aktuelles/sommerfest',
            'tag' => 'data-news-3<script>', 'quiet' => true, 'extra' => 'nicht mitsenden']);
        self::eq('Inhalt: Titel ohne HTML und Notizen', $p['title'], 'Neu: Sommerfest');
        self::eq('Inhalt: Text gekürzt (240)', mb_strlen($p['body']) <= 240 && !str_contains($p['body'], '<'), true);
        // absolut, sobald die Website eine Adresse kennt (sonst relativ – der Service Worker löst sie auf seiner Website auf)
        self::eq('Inhalt: Adresse ' . (Push::origin() !== '' ? 'absolut' : 'relativ (keine Domain bekannt)'),
            (Push::origin() === '' || str_starts_with($p['url'], Push::origin())) && str_ends_with($p['url'], '/aktuelles/sommerfest'), true);
        self::eq('Inhalt: Tag bereinigt', $p['tag'], 'data-news-3script');
        self::eq('Inhalt: quiet = Pfad der Verwaltung', $p['quiet'], \Core\AdminPath::prefix());
        self::eq('Inhalt: nur bekannte Angaben', array_diff(array_keys($p), ['title', 'body', 'url', 'icon', 'ts', 'tag', 'quiet', 'lang']), []);
        $big = Push::payload(['title' => str_repeat('T', 500), 'body' => str_repeat('ü', 5000), 'url' => '/x']);
        self::eq('Inhalt: passt in eine Nachricht', strlen((string) json_encode($big, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) <= WebPush::MAX_PAYLOAD, true);
        self::eq('Inhalt: ohne Titel → Name der Website', Push::payload(['body' => 'x'])['title'] !== '', true);
    }

    private static function events(): void
    {
        $ev = Push::events();
        foreach (['requests.new', 'forms.entry', 'chat.direct', 'chat.mention', 'review.pending'] as $k) self::eq("Ereignis $k", isset($ev[$k]), true);
        self::eq('Ereignis requests.new braucht requests.read', $ev['requests.new']['perm'] ?? null, 'requests.read');
        $x = new \Core\Extension('qa_push', __DIR__, ['label' => 'QA']);
        $x->pushEvent('qa_push.feedback', 'Neues Feedback', 'pages.edit', ['help' => 'Hilfe', 'default' => false]);
        $e = Push::events()['qa_push.feedback'] ?? null;
        self::eq('Extension::pushEvent angemeldet', [$e['label'] ?? null, $e['perm'] ?? null, $e['default'] ?? null, $e['owner'] ?? null], ['Neues Feedback', 'pages.edit', false, 'qa_push']);
        self::eq('Sichtbar nur mit Recht', [isset(Push::visibleEvents(['role' => 'admin'])['qa_push.feedback']), isset(Push::visibleEvents(['role' => 'requests'])['qa_push.feedback'])], [true, false]);
        self::eq('Gesperrtes Konto sieht nichts', Push::userCan(['role' => 'admin', 'disabled' => 1], 'pages.edit'), false);
        Push::unregisterEvent('qa_push.feedback');
        self::eq('Ereignis abgemeldet', isset(Push::events()['qa_push.feedback']), false);
        try {
            Push::registerEvent('Böse Schlüssel', 'x');
            self::eq('Ungültiger Ereignis-Schlüssel abgelehnt', false, true);
        } catch (\InvalidArgumentException) {
            self::eq('Ungültiger Ereignis-Schlüssel abgelehnt', true, true);
        }
    }

    private static function topics(): void
    {
        $fields = [['name' => 'titel', 'type' => 'text', 'label' => 'Titel'], ['name' => 'teaser', 'type' => 'textarea', 'label' => 'Teaser'],
            ['name' => 'kategorie', 'type' => 'select', 'label' => 'Kategorie', 'options' => ['presse' => 'Presse', 'intern' => 'Intern']],
            ['name' => 'bild', 'type' => 'media', 'label' => 'Bild'], ['name' => 'tags', 'type' => 'multiselect', 'label' => 'Tags', 'options' => ['a' => 'A', 'b' => 'B']]];
        self::eq('Tabelle: Eingang ohne Abo', Topics::validate(['enabled' => '1'], $fields, ['kind' => 'inbox']), []);
        self::eq('Tabelle: aus = nichts gespeichert', Topics::validate(['enabled' => '0', 'title' => '', 'body' => '', 'filter' => ['field' => '', 'value' => '']], $fields, ['kind' => 'content']), []);
        self::eq('Tabelle: an mit Titel, Kurztext, Filter', Topics::validate(['enabled' => '1', 'title' => 'Neu: {title}', 'body' => 'teaser', 'filter' => ['field' => 'kategorie', 'value' => 'presse']], $fields, ['kind' => 'content']),
            ['enabled' => true, 'title' => 'Neu: {title}', 'body' => 'teaser', 'filter' => ['field' => 'kategorie', 'value' => 'presse']]);
        self::eq('Tabelle: Rundlauf (bool true bleibt)', Topics::validate(['enabled' => true, 'body' => '-'], $fields, ['kind' => 'content']), ['enabled' => true, 'body' => '-']);
        self::eq('Tabelle: Bildfeld nicht als Kurztext', Topics::validate(['enabled' => '1', 'body' => 'bild'], $fields, ['kind' => 'content']), ['enabled' => true]);
        $t = ['handle' => 'qa', 'name' => 'QA', 'fields' => $fields, 'settings' => ['kind' => 'content', 'title_field' => 'titel', 'route' => '', 'detail_page_id' => null, 'description_field' => '',
            'push' => ['enabled' => true, 'filter' => ['field' => 'kategorie', 'value' => 'presse']]]];
        self::eq('Filter: passt', Topics::matches($t, ['kategorie' => 'presse']), true);
        self::eq('Filter: passt nicht', Topics::matches($t, ['kategorie' => 'intern']), false);
        $t2 = $t;
        $t2['settings']['push']['filter'] = ['field' => 'tags', 'value' => 'b'];
        self::eq('Filter: Mehrfachauswahl (JSON)', [Topics::matches($t2, ['tags' => '["a","b"]']), Topics::matches($t2, ['tags' => '["a"]'])], [true, false]);
        self::eq('Thema der Tabelle', Topics::topic($t), 'data:qa');
    }

    private static function serviceWorker(): void
    {
        $sw = (string) file_get_contents(ROOT . '/resources/sw/push.js');
        foreach (["addEventListener('push'", "addEventListener('notificationclick'", "addEventListener('pushsubscriptionchange'", '__PUSH_KEY__', '__PUSH_RENEW__', 'showNotification', 'self.location.origin'] as $needle) {
            self::eq("Service Worker enthält $needle", str_contains($sw, $needle), true);
        }
        $req = fn(array $server) => new Request('POST', '/api/push/subscribe', [], [], [], $server + ['HTTP_HOST' => 'www.example.org']);
        self::eq('Herkunft: gleiche Website', PushController::sameOrigin($req(['HTTP_ORIGIN' => 'https://www.example.org', 'HTTP_SEC_FETCH_SITE' => 'same-origin'])), true);
        self::eq('Herkunft: fremde Website', PushController::sameOrigin($req(['HTTP_ORIGIN' => 'https://evil.example', 'HTTP_SEC_FETCH_SITE' => 'cross-site'])), false);
        self::eq('Herkunft: fremder Origin ohne Sec-Fetch', PushController::sameOrigin($req(['HTTP_ORIGIN' => 'https://www.example.org.evil.example'])), false);
        self::eq('Herkunft: ohne Angaben abgelehnt', PushController::sameOrigin($req([])), false);
    }

    // ================================================================= Datenbank (zurückgerollt)

    private static function database(): void
    {
        $db = app()->db;
        $vapid = WebPush::generateKeys();
        Keys::override($vapid);
        Push::$force = true;
        $db->pdo->beginTransaction();
        try {
            $uid = $db->insert('users', ['email' => 'push-selbsttest-' . bin2hex(random_bytes(3)) . '@example.org', 'name' => 'Selbsttest', 'password_hash' => 'x', 'role' => 'admin', 'created_at' => now()]);
            $ed = $db->insert('users', ['email' => 'push-selbsttest-' . bin2hex(random_bytes(3)) . '@example.org', 'name' => 'Selbsttest 2', 'password_hash' => 'x', 'role' => 'requests', 'created_at' => now()]);
            $mk = function (string $id) {
                $k = WebPush::generateKeys();
                return ['endpoint' => 'https://fcm.googleapis.com/fcm/send/selbsttest-' . $id . '-' . bin2hex(random_bytes(4)), 'p256dh' => $k['public'], 'auth' => random_bytes(16), '_priv' => $k['private']];
            };
            $s1 = $mk('admin');
            $s2 = $mk('requests');
            $s3 = $mk('besucher');
            $s4 = $mk('englisch');
            [$id1] = Push::store($s1, $uid, [], null, 'Mozilla/5.0 (Macintosh) Chrome/141.0');
            [$id2] = Push::store($s2, $ed);
            [$id3] = Push::store($s3, null, ['data:qa_selbsttest']);
            [$id4] = Push::store($s4, null, ['data:qa_selbsttest'], 'zz');
            self::eq('Abos gespeichert', [is_int($id1), is_int($id2), is_int($id3), is_int($id4)], [true, true, true, true]);
            self::eq('Gerät erkannt', $db->fetchValue('SELECT label FROM push_subscriptions WHERE id = ?', [$id1]), 'Chrome · macOS');
            [$same] = Push::store($s1, $uid);
            self::eq('Gleicher Endpunkt = gleiches Abo', $same, $id1);
            [, $err] = Push::store(['auth' => random_bytes(16)] + $s1, null, ['data:qa_selbsttest']);
            self::eq('Fremdes Geheimnis für bekannten Endpunkt abgelehnt', $err, 'conflict');
            self::eq('Besucher-Abo: Geheimnis prüfen', [Push::owns(Push::find($s3['endpoint']), $s3['auth']), Push::owns(Push::find($s3['endpoint']), random_bytes(16))], [true, false]);

            // Personen: Ereignis mit Recht – nur Administration (requests-Rolle hat pages.edit nicht)
            Push::registerEvent('qa_push.selftest', 'Selbsttest', 'pages.edit');
            $n = Push::notifyUsers('pages.edit', ['title' => 'Hallo', 'body' => 'Selbsttest', 'url' => '/admin'], 'qa_push.selftest');
            self::eq('Personen: nur mit Recht', [$n, (int) $db->fetchValue('SELECT COUNT(*) FROM push_queue WHERE subscription_id = ?', [$id2])], [1, 0]);
            Push::saveUserEvents($uid, ['qa_push.selftest' => false]);
            self::eq('Personen: Schalter aus → nichts', Push::notifyUsers([$uid], ['title' => 'x'], 'qa_push.selftest'), 0);
            Push::saveUserEvents($uid, ['qa_push.selftest' => true]);
            self::eq('Personen: Liste von IDs + except', [Push::notifyUsers([$uid, $ed], ['title' => 'x'], 'qa_push.selftest', ['except' => $uid])], [0]);
            self::eq('Personen: unbekanntes Ereignis', Push::notifyUsers([$uid], ['title' => 'x'], 'gibt.es.nicht'), 0);
            $loc = Push::notifyUsers([$uid], fn() => ['title' => __('Speichern')], 'qa_push.selftest');
            self::eq('Personen: Inhalt je Sprache (Callable)', $loc, 1);

            // Thema: Sprache, Grenze je Stunde, alter Schlüssel
            if (Push::visitorsAllowed()) {
                $m = Push::notifyTopic('data:qa_selbsttest', ['title' => 'Neu', 'url' => '/'], 'data.published');
                self::eq('Thema: nur Abos der Standardsprache', $m, 1);
                self::eq('Thema: Sprache „zz“', Push::notifyTopic('data:qa_selbsttest', ['title' => 'New'], 'data.published', ['lang' => 'zz']), 1);
                $db->query('UPDATE push_subscriptions SET key_fp = ? WHERE id = ?', ['alt', $id3]);
                self::eq('Thema: Abo mit altem Schlüssel nicht beliefert', Push::notifyTopic('data:qa_selbsttest', ['title' => 'x'], 'data.published'), 0);
                $db->query('UPDATE push_subscriptions SET key_fp = ? WHERE id = ?', [Keys::fingerprint(), $id3]);
                for ($i = 0; $i < Push::TOPIC_PER_HOUR; $i++) Push::notifyTopic('data:qa_selbsttest', ['title' => 'x'], 'data.published');
                self::eq('Thema: Grenze je Stunde', Push::notifyTopic('data:qa_selbsttest', ['title' => 'x'], 'data.published'), 0);
            } else {
                self::eq('Testumgebung: keine Nachrichten an Besucher', Push::notifyTopic('data:qa_selbsttest', ['title' => 'x'], 'data.published'), 0);
            }

            // Versand mit vorgetäuschtem Push-Dienst: Antwort je Abo; Inhalt muss für den Browser entschlüsselbar sein
            $db->query("UPDATE push_queue SET status = 'done' WHERE status = 'queued'");
            $mid = $db->insert('push_messages', ['kind' => 'test', 'event' => 'test', 'payload_json' => json_encode(Push::payload(['title' => 'Versand', 'url' => '/'])),
                'ttl' => 600, 'urgency' => 'high', 'created_at' => now()]);
            $old = $db->insert('push_messages', ['kind' => 'test', 'event' => 'test', 'payload_json' => '{}', 'ttl' => 60, 'urgency' => 'normal', 'created_at' => date('Y-m-d H:i:s', time() - 3600)]);
            foreach ([$id1, $id2, $id3, $id4] as $sid) $db->insert('push_queue', ['message_id' => $mid, 'subscription_id' => $sid, 'status' => 'queued', 'attempts' => 0, 'next_at' => 0, 'created_at' => time()]);
            $db->insert('push_queue', ['message_id' => $old, 'subscription_id' => $id1, 'status' => 'queued', 'attempts' => 0, 'next_at' => 0, 'created_at' => time()]);
            $seen = [];
            $codes = [$id1 => 201, $id2 => 410, $id3 => 429, $id4 => 400];
            $decrypted = null;
            Push::$transport = function (array $jobs) use (&$seen, &$decrypted, $codes, $vapid, $s1, $db): array {
                $out = [];
                foreach ($jobs as $j) {
                    $sid = (int) $db->fetchValue('SELECT subscription_id FROM push_queue WHERE id = ?', [$j['id']]);
                    $seen[] = $sid;
                    if ($j['endpoint'] === $s1['endpoint']) {
                        // wie der echte Versand: verschlüsseln (Wegwerf-Schlüssel) und mit dem Schlüssel des Browsers wieder öffnen
                        $decrypted = WebPush::decrypt(WebPush::encrypt($j['payload'], $j['p256dh'], $j['auth']), $s1['_priv'], $s1['p256dh'], $s1['auth']);
                        WebPush::vapidHeader($j['endpoint'], 'mailto:test@example.org', $vapid);
                    }
                    $out[$j['id']] = ['code' => $codes[$sid] ?? 500, 'error' => ($codes[$sid] ?? 500) === 201 ? null : 'HTTP ' . ($codes[$sid] ?? 500), 'retry_after' => 120];
                }
                return $out;
            };
            $st = Push::process(50, 10.0);
            self::eq('Versand: Zähler', [$st['sent'] ?? null, $st['gone'] ?? null, $st['retry'] ?? null, $st['failed'] ?? null, $st['expired'] ?? null], [1, 1, 1, 1, 1]);
            self::eq('Versand: Inhalt für den Browser lesbar', json_decode((string) $decrypted, true)['title'] ?? null, 'Versand');
            self::eq('410: Abo gelöscht', $db->fetchValue('SELECT COUNT(*) FROM push_subscriptions WHERE id = ?', [$id2]), 0);
            $q3 = $db->fetch('SELECT status, attempts, next_at FROM push_queue WHERE message_id = ? AND subscription_id = ?', [$mid, $id3]);
            self::eq('429: später erneut (Retry-After)', [$q3['status'], (int) $q3['attempts'], (int) $q3['next_at'] >= time() + 100], ['queued', 1, true]);
            self::eq('400: Fehlversuch gezählt', (int) $db->fetchValue('SELECT failures FROM push_subscriptions WHERE id = ?', [$id4]), 1);
            self::eq('Erfolg: zuletzt gesendet', $db->fetchValue('SELECT last_sent_at FROM push_subscriptions WHERE id = ?', [$id1]) !== null, true);
            self::eq('Nachricht: Zähler', [(int) $db->fetchValue('SELECT sent FROM push_messages WHERE id = ?', [$mid]), (int) $db->fetchValue('SELECT failed FROM push_messages WHERE id = ?', [$mid])], [1, 2]);
            for ($i = 0; $i < Push::MAX_FAILURES; $i++) {
                $db->query('UPDATE push_subscriptions SET failures = ? WHERE id = ?', [Push::MAX_FAILURES - 1, $id4]);
            }
            $db->insert('push_queue', ['message_id' => $mid, 'subscription_id' => $id4, 'status' => 'queued', 'attempts' => 0, 'next_at' => 0, 'created_at' => time()]);
            Push::process(10, 5.0);
            self::eq('Nach 5 Fehlversuchen: Abo gelöscht', $db->fetchValue('SELECT COUNT(*) FROM push_subscriptions WHERE id = ?', [$id4]), 0);

            // Abmelden
            self::eq('Gerät abmelden (ohne Themen) → gelöscht', Push::unlinkUser($uid, $id1), 'deleted');
            [$id5] = Push::store($s3, $uid);
            self::eq('Besucher-Abo mit Konto: abmelden lässt Themen', Push::unlinkUser($uid, $id5), 'unlinked');
            self::eq('Thema abbestellen → Abo weg', Push::removeTopics(Push::find($s3['endpoint']), ['data:qa_selbsttest']), 'deleted');
            self::eq('Status liest Zahlen', is_int(Push::status()['queued']), true);
        } finally {
            Push::unregisterEvent('qa_push.selftest');
            if ($db->pdo->inTransaction()) $db->pdo->rollBack();
        }
    }
}
