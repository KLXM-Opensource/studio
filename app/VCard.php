<?php
declare(strict_types=1);

namespace Core;

use Core\Api\CmsService;
use Core\Data\Entries;
use Core\Data\ICal;
use Core\Data\Tables;

/**
 * Visitenkarten (vCard 3.0, RFC 2426) zum Speichern im Adressbuch – iOS, Android, Outlook, Thunderbird.
 *
 *   GET /vcard.vcf                       Organisation der Website (Name, Adresse, Telefon, E-Mail, Website, Logo, Standort,
 *                                        Öffnungszeiten als Notiz) – Helfer vcard_url()
 *   GET /vcard/{tabelle}/{slug}.vcf      Person aus einer Datentabelle mit Schema-Typ „Person“ (Team, Ansprechpartner) –
 *                                        Helfer vcard_entry_url($table, $entry)
 *
 * Quelle sind die zentralen Angaben, die auch Kontakt-Blöcke, Fußbereich und strukturierte Daten nutzen: project.public_info
 * des Kits (CmsService::publicInfo – Adresse, Telefon, E-Mail, Öffnungszeiten), ergänzt um die Organisation aus dem
 * JSON-LD des Kits (StructuredData::organization – Adresse mit Land, Logo, Standort). Keine eigene Ablage.
 * Ohne Telefon, E-Mail und Adresse gibt es keine Karte (404, vcard_url() = null).
 *
 * Format: Texte nach RFC 2426 maskiert (\\ \; \, \n), Zeilen nach 75 Oktetts gefaltet (UTF-8-sicher), Zeilenende CRLF.
 * Bilder nur als Rastergrafik (PNG/JPEG, WebP/GIF werden umgewandelt), auf höchstens 256 px verkleinert und nur bis 48 KB
 * eingebettet – SVG nie.
 */
final class VCard
{
    public const ORG_PATH = '/vcard.vcf';
    public const PERSON_PREFIX = '/vcard';
    /** Größte eingebettete Bilddatei (Bytes, vor base64) */
    public const IMAGE_MAX_BYTES = 49152;
    /** Längste Bildseite beim Verkleinern */
    public const IMAGE_MAX_SIDE = 256;

    private static ?array $orgCache = null;
    private static bool $orgLoaded = false;

    // ------------------------------------------------------------------ Organisation

    /**
     * Angaben der Organisation für die Karte oder null, wenn weder Telefon, E-Mail noch Adresse eingetragen sind.
     * Schlüssel: name, street, zip, city, country, phone, email, url, geo [lat, lng], photo (Media-ID), note.
     */
    public static function orgData(): ?array
    {
        if (self::$orgLoaded) return self::$orgCache;
        self::$orgLoaded = true;
        $info = CmsService::publicInfo();
        $ld = StructuredData::organization() ?? [];
        $pa = is_array($ld['address'] ?? null) ? $ld['address'] : [];
        $ia = is_array($info['address'] ?? null) ? $info['address'] : [];
        if (array_is_list($ia)) $ia = ['street' => $ia[0] ?? null, 'city' => implode(', ', array_slice($ia, 1))];   // Kits mit Adresszeilen
        $pick = fn(mixed ...$v) => self::firstFilled(...$v);
        $d = [
            'name' => self::orgName($ld),
            'street' => $pick($pa['streetAddress'] ?? null, $ia['street'] ?? null),
            'zip' => $pick($pa['postalCode'] ?? null, $ia['zip'] ?? null),
            'city' => $pick($pa['addressLocality'] ?? null, $ia['city'] ?? null),
            'country' => $pick($pa['addressCountry'] ?? null, $ia['country'] ?? null),
            'phone' => $pick($info['phone'] ?? null, $ld['telephone'] ?? null),
            'email' => $pick($info['email'] ?? null, $ld['email'] ?? null),
        ];
        if ($d['phone'] === null && $d['email'] === null && $d['street'] === null && $d['city'] === null) {
            return self::$orgCache = null;
        }
        $d['url'] = main_url() . url('/');
        if ($p = Maps::siteLocation()) {
            $d['geo'] = $p;
        } elseif (isset($ld['geo']['latitude'], $ld['geo']['longitude'])) {
            $d['geo'] = [(float) $ld['geo']['latitude'], (float) $ld['geo']['longitude']];
        }
        $d['photo'] = (int) setting('logo') ?: null;   // wie das Kern-Fragment „brand“
        $d['note'] = self::hoursNote();
        return self::$orgCache = $d;
    }

    /** vCard der Organisation oder null (keine Angaben) */
    public static function org(): ?string
    {
        $d = self::orgData();
        if ($d === null) return null;
        return self::build([
            'kind' => 'org',
            'fn' => $d['name'],
            'org' => $d['name'],
            'adr' => [$d['street'], $d['zip'], $d['city'], $d['country']],
            'tel' => $d['phone'],
            'email' => $d['email'],
            'url' => $d['url'],
            'geo' => $d['geo'] ?? null,
            'photo' => $d['photo'] ? self::imageOf((int) $d['photo']) : null,
            'note' => $d['note'],
        ]);
    }

    /** Dateiname der Organisations-Karte, z. B. „praxis-dr-muster.vcf“ */
    public static function orgFile(): string
    {
        return self::fileName((string) (self::orgData()['name'] ?? org_name())) . '.vcf';
    }

    /** Öffnungszeiten als Notiz: „Öffnungszeiten:\nMo–Fr: 8:00–12:00\nSa: nach Vereinbarung“ (Wochentage zusammengefasst) */
    public static function hoursNote(): ?string
    {
        if (!CmsService::hasHours()) return null;
        try {
            $rows = (new CmsService(['name' => 'public', 'scope' => 'read', 'user_id' => null]))->hoursGet();
        } catch (\Throwable) {
            return null;
        }
        $byDay = [];
        foreach ($rows as $r) {
            $dow = (int) ($r['tag'] ?? -1);
            if ($dow < 0 || $dow > 6) continue;
            $t = trim(implode(' ', array_filter([trim((string) ($r['text'] ?? '')), trim((string) ($r['notiz'] ?? ''))], 'filled')));
            if ($t !== '') $byDay[$dow][] = $t;
        }
        if (!$byDay) return null;
        $groups = [];
        foreach ([1, 2, 3, 4, 5, 6, 0] as $dow) {
            $time = isset($byDay[$dow]) ? implode(', ', $byDay[$dow]) : null;
            $last = $groups ? array_key_last($groups) : null;
            if ($time !== null && $last !== null && $groups[$last]['time'] === $time && $groups[$last]['next'] === $dow) {
                $groups[$last]['to'] = $dow;
                $groups[$last]['next'] = ($dow + 1) % 7;
            } elseif ($time !== null) {
                $groups[] = ['from' => $dow, 'to' => $dow, 'next' => ($dow + 1) % 7, 'time' => $time];
            }
        }
        $lines = [lt((string) project('hours.label', 'Öffnungszeiten')) . ':'];
        foreach ($groups as $g) {
            $lines[] = self::dayShort($g['from']) . ($g['to'] !== $g['from'] ? '–' . self::dayShort($g['to']) : '') . ': ' . $g['time'];
        }
        return implode("\n", $lines);
    }

    // ------------------------------------------------------------------ Personen (Datentabellen mit Schema-Typ „Person“)

    /** Tabelle für Personen-Karten: Inhaltstabelle mit Schema-Typ „Person“ (Einstellung der Tabelle), sonst null */
    public static function personTable(string $handle): ?array
    {
        $t = Tables::findContent($handle);
        return $t && StructuredData::typeOf($t) === 'Person' ? $t : null;
    }

    /** vCard einer Person aus einem veröffentlichten Eintrag */
    public static function person(array $table, array $e): string
    {
        $find = fn(array $types, array $names = []) => StructuredData::field($table, $e, $types, $names);
        $img = Tables::imageField($table);
        $tel = $find(['tel']);
        $org = self::orgData();
        return self::build([
            'kind' => 'person',
            'fn' => Entries::title($table, $e),
            'org' => $org ? $org['name'] : org_name(),
            'title' => $find(['text'], ['position', 'funktion', 'rolle', 'job', 'beruf', 'title']),
            'tel' => $tel !== null ? (substr((string) tel_href((string) $tel), 4) ?: (string) $tel) : null,
            'email' => $find(['email']),
            'url' => Entries::absUrl($table, $e),
            'photo' => $img !== '' && !empty($e[$img]) ? self::imageOf((int) $e[$img]) : null,
        ]);
    }

    /** Adresse der Personen-Karte eines Eintrags oder null (Tabelle ohne Schema-Typ „Person“, Entwurf, ohne Slug) */
    public static function personUrl(array $table, array $e): ?string
    {
        if (StructuredData::typeOf($table) !== 'Person' || Tables::isInbox($table) || empty($e['slug'])) return null;
        if (($e['status'] ?? 'published') !== 'published') return null;
        return url(self::PERSON_PREFIX . '/' . rawurlencode((string) $table['handle']) . '/' . rawurlencode((string) $e['slug']) . '.vcf')
            . (!empty($e['lang']) && $e['lang'] !== Lang::default() ? '?lang=' . rawurlencode((string) $e['lang']) : '');
    }

    // ------------------------------------------------------------------ Format

    /**
     * vCard 3.0 aus Rohangaben (Werte ungefiltert, null/leer = weglassen):
     * kind (org|person), fn, org, title, adr [Straße, PLZ, Ort, Land], tel, email, url, geo [lat, lng],
     * photo ['type' => 'PNG'|'JPEG', 'data' => Binärdaten], note, rev (Zeitstempel, Standard jetzt).
     */
    public static function build(array $d): string
    {
        $fn = trim((string) ($d['fn'] ?? ''));
        $isOrg = ($d['kind'] ?? 'org') === 'org';
        $l = ['BEGIN:VCARD', 'VERSION:3.0', 'PRODID:' . ICal::prodId()];
        if ($isOrg) {
            $l[] = 'N:;;;;';
        } else {
            [$family, $given, $prefix] = self::splitName($fn);
            $l[] = 'N:' . self::text($family) . ';' . self::text($given) . ';;' . self::text($prefix) . ';';
        }
        $l[] = 'FN:' . self::text($fn);
        if (filled($d['org'] ?? null)) $l[] = 'ORG:' . self::text((string) $d['org']);
        if ($isOrg) $l[] = 'X-ABShowAs:COMPANY';           // Apple-Kontakte: als Firma anzeigen
        if (filled($d['title'] ?? null)) $l[] = 'TITLE:' . self::text((string) $d['title']);
        $adr = array_map(fn($v) => filled($v) ? trim((string) $v) : '', array_pad(array_values((array) ($d['adr'] ?? [])), 4, ''));
        if (implode('', $adr) !== '') {
            [$street, $zip, $city, $country] = $adr;
            // Postfach;Zusatz;Straße;Ort;Region;PLZ;Land
            $l[] = 'ADR;TYPE=WORK:;;' . self::text($street) . ';' . self::text($city) . ';;' . self::text($zip) . ';' . self::text($country);
        }
        if (filled($d['tel'] ?? null)) $l[] = 'TEL;TYPE=WORK,VOICE:' . self::text((string) $d['tel']);
        if (filled($d['email'] ?? null)) $l[] = 'EMAIL;TYPE=INTERNET,WORK:' . self::text((string) $d['email']);
        if (filled($d['url'] ?? null)) $l[] = 'URL:' . self::uri((string) $d['url']);
        if (!empty($d['geo']) && count((array) $d['geo']) === 2) {
            [$lat, $lng] = array_values((array) $d['geo']);
            $l[] = 'GEO:' . self::num((float) $lat) . ';' . self::num((float) $lng);
        }
        if (!empty($d['photo']['data']) && in_array($d['photo']['type'] ?? '', ['PNG', 'JPEG'], true)) {
            $l[] = 'PHOTO;ENCODING=b;TYPE=' . $d['photo']['type'] . ':' . base64_encode((string) $d['photo']['data']);
        }
        if (filled($d['note'] ?? null)) $l[] = 'NOTE:' . self::text((string) $d['note']);
        $l[] = 'REV:' . gmdate('Y-m-d\TH:i:s\Z', (int) ($d['rev'] ?? time()));
        $l[] = 'END:VCARD';
        return implode("\r\n", array_map([self::class, 'fold'], $l)) . "\r\n";
    }

    /** TEXT-Wert maskieren (RFC 2426 §4): \ ; , und Zeilenumbrüche; Redaktionsnotizen [# … #] fallen weg */
    public static function text(string $s): string
    {
        $s = EditorNotes::strip($s);
        $s = str_replace(["\r\n", "\r"], "\n", trim($s));
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $s);
    }

    /** Zeile nach 75 Oktetts falten (Folgezeilen mit Leerzeichen), ohne UTF-8-Zeichen zu teilen */
    public static function fold(string $line): string
    {
        return ICal::fold($line);
    }

    /** Dateiname aus einem Namen: Kleinbuchstaben, Umlaute umschrieben, Bindestriche */
    public static function fileName(string $name): string
    {
        $s = strtr(mb_strtolower($name), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        if (function_exists('iconv')) $s = (string) @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        $s = trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower($s)), '-');
        return $s !== '' ? mb_substr($s, 0, 60) : 'kontakt';
    }

    /**
     * Bild einer Medien-ID für PHOTO: PNG/JPEG unverändert, wenn klein genug, sonst (auch WebP/GIF) mit GD auf
     * IMAGE_MAX_SIDE verkleinert – mit Transparenz als PNG, sonst JPEG. SVG, PDF, Videos und zu große Dateien: null.
     */
    public static function imageOf(int $id): ?array
    {
        $m = Media::find($id);
        if (!$m) return null;
        $path = Media::localFile($m);
        if (!is_file($path)) $path = Media::path($m);
        return is_file($path) ? self::image($path) : null;
    }

    /** Bilddatei → ['type' => 'PNG'|'JPEG', 'data' => …] oder null */
    public static function image(string $path): ?array
    {
        $info = @getimagesize($path);
        if (!$info) return null;                                      // SVG, PDF, kaputt
        [$w, $h, $type] = $info;
        $kind = match ($type) { IMAGETYPE_PNG => 'PNG', IMAGETYPE_JPEG => 'JPEG', default => null };
        $size = (int) @filesize($path);
        if ($kind && $size > 0 && $size <= self::IMAGE_MAX_BYTES && max($w, $h) <= self::IMAGE_MAX_SIDE * 2) {
            return ['type' => $kind, 'data' => (string) file_get_contents($path)];
        }
        if (!function_exists('imagecreatetruecolor')) return null;
        $src = match ($type) {
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => false,
        };
        if (!$src) return null;
        $alpha = $type !== IMAGETYPE_JPEG;
        $scale = min(1, self::IMAGE_MAX_SIDE / max($w, $h, 1));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($alpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        ob_start();
        $alpha ? imagepng($dst, null, 9) : imagejpeg($dst, null, 85);
        $data = (string) ob_get_clean();
        return $data !== '' && strlen($data) <= self::IMAGE_MAX_BYTES ? ['type' => $alpha ? 'PNG' : 'JPEG', 'data' => $data] : null;
    }

    /** HTTP-Antwort für eine Karte (Download, kurz zwischenspeicherbar, nicht indexieren) */
    public static function response(string $body, string $file): Http\Response
    {
        return new Http\Response($body, 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('~[^a-z0-9._-]+~i', '-', $file) . '"',
            'Cache-Control' => 'public, max-age=900',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /** Zwischenspeicher der Anfrage leeren (Selbsttest, geänderte Einstellungen in derselben Anfrage) */
    public static function reset(): void
    {
        self::$orgCache = null;
        self::$orgLoaded = false;
    }

    // ------------------------------------------------------------------ Selbsttest (php bin/console vcard:selftest)

    /** Erzeugen + Zurücklesen: Aufbau, Maskierung, Faltung, CRLF, UTF-8, Bild; dazu die Karten dieser Website */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $check = function (string $label, string $card) use ($eq): array {
            $p = self::parse($card);
            $eq("$label: Zeilenende nur CRLF", $p['bare_lf'], 0);
            $eq("$label: endet mit CRLF", str_ends_with($card, "\r\n"), true);
            $eq("$label: Zeilen ≤ 75 Oktetts", $p['too_long'], 0);
            $eq("$label: gültiges UTF-8 je Zeile", $p['bad_utf8'], 0);
            $names = array_column($p['props'], 0);
            $eq("$label: BEGIN zuerst", $names[0] ?? null, 'BEGIN');
            $eq("$label: VERSION 3.0 an zweiter Stelle", [$names[1] ?? null, $p['props'][1][2] ?? null], ['VERSION', '3.0']);
            $eq("$label: END zuletzt", end($names), 'END');
            $eq("$label: FN vorhanden", in_array('FN', $names, true), true);
            $eq("$label: N vorhanden (Pflicht in 3.0)", in_array('N', $names, true), true);
            return $p;
        };

        // Rohangaben mit allen Sonderzeichen
        $name = "Praxis Dr. Müller, Söhne; Partner \\ Co.";
        $note = "Öffnungszeiten:\nMo–Fr: 8:00–12:00, 14:00–18:00\nSa: nach Vereinbarung; bitte anrufen" . str_repeat(' – Grüße aus Musterstadt', 6);
        $png = null;
        if (function_exists('imagecreatetruecolor')) {
            $im = imagecreatetruecolor(600, 300);
            imagefill($im, 0, 0, imagecolorallocate($im, 15, 110, 104));
            $tmp = tempnam(sys_get_temp_dir(), 'vcf') . '.png';
            imagepng($im, $tmp);
            $png = self::image($tmp);
            $eq('Bild: verkleinert auf 256 px (PNG)', $png ? [$png['type'], getimagesizefromstring($png['data'])[0] ?? 0] : null, ['PNG', 256]);
            @unlink($tmp);
        }
        $svg = tempnam(sys_get_temp_dir(), 'vcf') . '.svg';
        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>');
        $eq('Bild: SVG wird nicht eingebettet', self::image($svg), null);
        @unlink($svg);

        $card = self::build(['kind' => 'org', 'fn' => $name, 'org' => $name, 'adr' => ['Musterstraße 1', '12345', 'Musterstadt', 'Deutschland'],
            'tel' => '+4912345678', 'email' => 'info@example.org', 'url' => 'https://example.org/', 'geo' => [51.451234, 6.6263],
            'photo' => $png, 'note' => $note, 'rev' => 0]);
        $p = $check('Organisation', $card);
        $get = function (string $prop) use ($p): ?array {
            foreach ($p['props'] as $x) if ($x[0] === $prop) return $x;
            return null;
        };
        $eq('FN maskiert (\\, \; \\\\)', $get('FN')[2] ?? null, 'Praxis Dr. Müller\, Söhne\; Partner \\\\ Co.');
        $eq('FN zurückgelesen', self::unescape((string) ($get('FN')[2] ?? '')), $name);
        $eq('ORG zurückgelesen', self::unescape((string) ($get('ORG')[2] ?? '')), $name);
        $eq('N leer bei Organisation', $get('N')[2] ?? null, ';;;;');
        $eq('Apple: als Firma anzeigen', $get('X-ABSHOWAS')[2] ?? null, 'COMPANY');
        $adr = self::components((string) ($get('ADR')[2] ?? ''));
        $eq('ADR: 7 Bestandteile', count($adr), 7);
        $eq('ADR: Straße/Ort/PLZ/Land an richtiger Stelle', [$adr[2] ?? null, $adr[3] ?? null, $adr[5] ?? null, $adr[6] ?? null], ['Musterstraße 1', 'Musterstadt', '12345', 'Deutschland']);
        $eq('TEL mit TYPE=WORK,VOICE', [$get('TEL')[1] ?? null, $get('TEL')[2] ?? null], ['TYPE=WORK,VOICE', '+4912345678']);
        $eq('EMAIL mit TYPE=INTERNET,WORK', [$get('EMAIL')[1] ?? null, $get('EMAIL')[2] ?? null], ['TYPE=INTERNET,WORK', 'info@example.org']);
        $eq('URL unverändert', $get('URL')[2] ?? null, 'https://example.org/');
        $eq('GEO lat;lng', $get('GEO')[2] ?? null, '51.451234;6.6263');
        $eq('NOTE: Zeilenumbrüche als \\n, zurückgelesen', self::unescape((string) ($get('NOTE')[2] ?? '')), $note);
        $eq('NOTE wurde gefaltet', $p['folded'] > 0, true);
        $eq('REV als UTC', $get('REV')[2] ?? null, '1970-01-01T00:00:00Z');
        if ($png) {
            $ph = $get('PHOTO');
            $eq('PHOTO: Parameter', $ph[1] ?? null, 'ENCODING=b;TYPE=PNG');
            $eq('PHOTO: base64 ergibt PNG', substr((string) base64_decode((string) ($ph[2] ?? ''), true), 0, 8), "\x89PNG\r\n\x1a\n");
        }
        $fl = explode("\r\n", self::fold('NOTE:' . str_repeat('ä', 80)));
        $eq('Faltung teilt keine UTF-8-Zeichen', count($fl) > 1 && !array_filter($fl, fn($x) => strlen($x) > 75 || !mb_check_encoding($x, 'UTF-8')), true);
        $eq('Redaktionsnotizen fallen weg', self::text('Mo geschlossen [# intern #]'), 'Mo geschlossen');
        $eq('Leere Angaben fehlen', str_contains(self::build(['kind' => 'org', 'fn' => 'X', 'tel' => '', 'adr' => ['', '', '', '']]), 'ADR'), false);

        // Person
        $pc = self::build(['kind' => 'person', 'fn' => 'Dr. Anna Maria Muster', 'org' => 'Praxis', 'title' => 'Ärztin, Leitung', 'email' => 'a@example.org']);
        $pp = $check('Person', $pc);
        $pn = null;
        $pt = null;
        foreach ($pp['props'] as $x) {
            if ($x[0] === 'N') $pn = $x[2];
            if ($x[0] === 'TITLE') $pt = $x[2];
        }
        $eq('Person: N = Nachname;Vornamen;;Titel;', $pn, 'Muster;Anna Maria;;Dr.;');
        $eq('Person: TITLE maskiert', $pt, 'Ärztin\, Leitung');
        $eq('Person: kein X-ABShowAs', str_contains($pc, 'X-ABShowAs'), false);

        $eq('Dateiname aus Namen', self::fileName('Praxis Dr. Müller & Söhne'), 'praxis-dr-mueller-soehne');
        $eq('Dateiname leer → kontakt', self::fileName('***'), 'kontakt');
        $eq('Adresse /vcard ist gesperrt', PublicPaths::isReserved('vcard'), true);

        // Diese Website
        self::reset();
        $data = self::orgData();
        $eq('vcard_url() passt zu den Angaben', vcard_url() !== null, $data !== null);
        if ($data !== null) {
            $live = (string) self::org();
            $lp = $check('Website', $live);
            $eq('Website: Größe < 100 KB', strlen($live) < 102400, true);
            $fn = null;
            foreach ($lp['props'] as $x) if ($x[0] === 'FN') $fn = self::unescape($x[2]);
            $eq('Website: FN = Name der Organisation', $fn, $data['name']);
        }
        foreach (Tables::content() as $t) {
            if (StructuredData::typeOf($t) !== 'Person') continue;
            $e = Entries::query($t, ['status' => 'published', 'limit' => 1, 'source' => 'own'])[0] ?? null;
            if (!$e) continue;
            $check('Person ' . $t['handle'], self::person($t, $e));
            $eq('Person ' . $t['handle'] . ': Adresse', (bool) self::personUrl($t, $e), !empty($e['slug']));
        }
        return ['ok' => $ok, 'fails' => $fails, 'org' => $data !== null];
    }

    /** Karte zerlegen: [[NAME, Parameter, Wert], …] (entfaltet) + Zähler für Formfehler */
    private static function parse(string $card): array
    {
        $out = ['props' => [], 'bare_lf' => 0, 'too_long' => 0, 'bad_utf8' => 0, 'folded' => 0];
        $out['bare_lf'] = preg_match_all('~(?<!\r)\n~', $card) + preg_match_all('~\r(?!\n)~', $card);
        $physical = explode("\r\n", rtrim($card, "\r\n"));
        $logical = [];
        foreach ($physical as $line) {
            if (strlen($line) > 75) $out['too_long']++;
            if (!mb_check_encoding($line, 'UTF-8')) $out['bad_utf8']++;
            if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t") && $logical) {
                $logical[count($logical) - 1] .= substr($line, 1);
                $out['folded']++;
            } else {
                $logical[] = $line;
            }
        }
        foreach ($logical as $l) {
            $c = strpos($l, ':');
            if ($c === false) continue;
            $head = explode(';', substr($l, 0, $c), 2);
            $out['props'][] = [strtoupper($head[0]), $head[1] ?? '', substr($l, $c + 1)];
        }
        return $out;
    }

    /** Strukturierten Wert an nicht maskierten Semikolons teilen und die Teile zurückwandeln */
    private static function components(string $v): array
    {
        return array_map([self::class, 'unescape'], preg_split('~(?<!\\\\);~', $v) ?: []);
    }

    private static function unescape(string $v): string
    {
        return (string) preg_replace_callback('~\\\\(.)~s', fn($m) => match ($m[1]) { 'n', 'N' => "\n", default => $m[1] }, $v);
    }

    // ------------------------------------------------------------------ intern

    /** org_name() – ohne eingetragenen Namen (Rückfall auf die Domain) der Name aus dem JSON-LD des Kits, falls vorhanden */
    private static function orgName(array $ld): string
    {
        $own = trim((string) setting('org_name')) . trim(strip_tags((string) setting((string) project('name_setting', 'site_name'), '')));
        return filled($own) ? org_name() : (self::firstFilled($ld['name'] ?? null) ?? org_name());
    }

    private static function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $v) {
            if (is_scalar($v) && filled((string) $v)) return trim(strip_tags((string) $v));
        }
        return null;
    }

    /** Name → [Nachname, Vorname(n), Titel]: letzter Teil = Nachname, vorangestellte Titel mit Punkt („Dr.“, „Prof.“) = Präfix */
    private static function splitName(string $name): array
    {
        $parts = preg_split('~\s+~u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $prefix = [];
        while (count($parts) > 1 && str_ends_with($parts[0], '.')) $prefix[] = array_shift($parts);
        $family = (string) (array_pop($parts) ?? '');
        return [$family, implode(' ', $parts), implode(' ', $prefix)];
    }

    private static function uri(string $u): string
    {
        return str_replace(["\r", "\n"], '', trim($u));
    }

    private static function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.');
    }

    private static function dayShort(int $dow): string
    {
        if (class_exists(\IntlDateFormatter::class)) {
            $f = new \IntlDateFormatter(Lang::current(), \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'UTC', null, 'EEE');
            $out = $f->format(strtotime('2024-01-0' . ($dow === 0 ? 7 : $dow) . ' 12:00 UTC'));
            if (is_string($out)) return rtrim($out, '.');
        }
        return ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][$dow];
    }
}
