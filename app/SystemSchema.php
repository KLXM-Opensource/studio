<?php
declare(strict_types=1);

namespace Core;

/** Schema der Grundeinstellungen (Admin → System). Theme-unabhängig. */
final class SystemSchema
{
    /** Gruppen gefiltert nach dem Funktionsumfang der Website (Features) */
    public static function groups(): array
    {
        $out = [];
        foreach (self::allGroups() as $g) {
            if (($g['id'] === 'app' && !Features::on('pwa')) || ($g['id'] === 'sprachen' && !Features::on('languages'))) continue;
            $g['fields'] = array_values(array_filter($g['fields'], fn($f) => !(
                (($f['name'] ?? '') === 'sys.theme' && !Features::on('theme_switch'))
                || (str_starts_with((string) ($f['name'] ?? ''), 'sys.map_') && !Features::on('maps')))));
            $out[] = $g;
        }
        return $out;
    }

    private static function allGroups(): array
    {
        $app = app()->theme->def['app']['defaults'] ?? [];
        $info = AppIcons::appInfo();
        return [
            ['id' => 'website', 'label' => 'Allgemein', 'fields' => [
                ['name' => 'sys.theme', 'label' => 'Aktives Kit', 'type' => 'select', 'required' => true,
                    'options' => site()->allowedThemes(), 'default' => site()->defaultTheme(),
                    'help' => 'Kits liegen unter /kits/{name} (Templates, Blöcke, Fragmente) und /public/assets/kits/{name} (Assets); ältere Kits unter /themes/{name} werden weiter erkannt.'],
                ['name' => 'sys.site_url', 'label' => 'Kanonische Adresse (Domain)', 'type' => 'url',
                    'placeholder' => 'https://www.ihre-domain.de',
                    'help' => 'Für Canonical-Links, Sitemap und E-Mail-Links (auch aus Cron/Kommandozeile). Nur nötig, wenn die Website unter mehreren Domains erreichbar ist; leer = Domain aus der Konfiguration bzw. die aufgerufene.'],
                ['name' => 'sys.admin_locale', 'label' => 'Sprache der Verwaltung (Standard)', 'type' => 'select', 'required' => true, 'default' => 'de',
                    'options' => I18n::available(), 'help' => 'Jede Person kann unter „Konto“ eine eigene Sprache wählen.'],
                // Persönliche Akzentfarbe der Verwaltung (Core\Accent): welche Vorlagen die Personen unter „Konto“ wählen dürfen
                ['name' => 'sys.admin_accents', 'label' => 'Akzentfarben der Verwaltung (Auswahl im Konto)', 'type' => 'multiselect',
                    'options' => array_diff_key(Accent::labels(), [Accent::DEFAULT => true]),
                    'help' => 'Nichts angehakt = alle Vorlagen erlaubt. „KLXM Navy“ (Standard) ist immer dabei. Alle Vorlagen sind für Hell und Dunkel auf Kontrast (WCAG AA) geprüft.'],
                ['name' => 'sys.admin_accent_lock', 'label' => 'Verwaltung immer im Standard „KLXM Navy“ (persönliche Akzentfarben aus)', 'type' => 'bool', 'default' => false],
                // Symbolauswahl (Core\Icons): welche Themenbereiche die Redaktion sieht – „Allgemein“ und „Bedienung“ immer
                ['name' => 'sys.icon_topics', 'label' => 'Symbolbereiche in der Symbolauswahl', 'type' => 'multiselect',
                    'options' => Icons::topicOptions(),
                    'help' => 'Nichts angehakt = alle Bereiche. „Allgemein“ und „Bedienung“ sind immer dabei; über die Suche lassen sich weitere Bereiche einblenden. Auf der Website lädt ohnehin nur ein kleines Sprite mit den tatsächlich verwendeten Symbolen.'],
                ['name' => 'sys.phone_country', 'label' => 'Landesvorwahl', 'type' => 'text', 'width' => 'half', 'default' => '+49', 'max' => 5,
                    'help' => 'Für Anruf-Links und die internationale Schreibweise von Telefonnummern auf Seiten in weiteren Sprachen (z. B. „0211 …“ → „+49 211 …“).'],
                ['name' => 'sys.maintenance', 'label' => 'Wartungsmodus – Website nur für eingeloggte Nutzer', 'type' => 'bool', 'default' => false],
                ['name' => 'sys.maintenance_text', 'label' => 'Text im Wartungsmodus', 'type' => 'textarea', 'rows' => 2,
                    'default' => 'Unsere Website wird gerade überarbeitet. Telefonisch sind wir wie gewohnt für Sie da.'],
            ]],
            // Indexierung & Crawler (Core\Indexing): robots.txt, KI-Crawler, llms.txt
            ['id' => 'index', 'label' => 'Indexierung & Crawler', 'fields' => [
                ...array_map(fn($w) => ['type' => 'heading', 'label' => '⚠ ' . $w], Indexing::warnings()),
                ['name' => 'sys.noindex', 'label' => 'Suchmaschinen aussperren (Testumgebung)', 'type' => 'bool', 'default' => false,
                    'help' => 'Sperrt die ganze Website (robots.txt „Disallow: /“, noindex). Auf Staging und lokal immer an. Einzelne Seiten: Seiteneinstellungen bzw. im Seitenbaum „⋯ → Nicht indexieren“; ganze Datentabellen: in deren Einstellungen.'],
                ...Indexing::fields(),
                ['type' => 'heading', 'label' => 'Ergebnis prüfen', 'help' => 'Aktuelle Fassung: ' . absolute_url('/robots.txt') . ' · ' . absolute_url('/sitemap.xml') . (Indexing::llmsEnabled() ? ' · ' . absolute_url('/llms.txt') : '')],
            ]],
            ['id' => 'app', 'label' => 'App-Icon & PWA', 'fields' => [
                ['type' => 'heading', 'label' => 'Favicon & App-Icon',
                    'help' => 'Daraus entstehen automatisch alle Größen: Browser-Tab (favicon.ico, 16–48 px), iPhone/iPad (180 px), Android/App (192 und 512 px, auch „maskable“). Die Vorschau zeigt das Ergebnis sofort.'],
                ['name' => 'sys.icon_mode', 'label' => 'Icon aus …', 'type' => 'select', 'required' => true, 'default' => 'letters',
                    'options' => ['letters' => 'Buchstaben in der Hausschrift', 'image' => 'Bild oder Logo aus der Mediathek']],
                ['name' => 'sys.icon_text', 'label' => 'Buchstabe(n)', 'type' => 'text', 'width' => 'half', 'max' => 2, 'default' => $app['icon_text'] ?? '',
                    'help' => '1–2 Zeichen, z. B. der Anfangsbuchstabe.'],
                ['name' => 'sys.icon_shape', 'label' => 'Form im Browser-Tab', 'type' => 'select', 'width' => 'half', 'required' => true, 'default' => 'rounded',
                    'options' => ['rounded' => 'Abgerundetes Quadrat', 'circle' => 'Kreis', 'square' => 'Quadrat']],
                ['name' => 'sys.icon_bg', 'label' => 'Hintergrund', 'type' => 'color', 'transparent' => true, 'width' => 'half', 'default' => $app['icon_bg'] ?? '#16201E',
                    'help' => '„Transparent“ gilt für Browser-Tab und Android; iPhone-Icon und „maskable“ brauchen eine Fläche und werden weiß hinterlegt.'],
                ['name' => 'sys.icon_fg', 'label' => 'Schriftfarbe', 'type' => 'color', 'width' => 'half', 'default' => $app['icon_fg'] ?? '#FFFFFF'],
                ['name' => 'sys.icon_dot', 'label' => 'Akzentpunkt wie in der Wortmarke', 'type' => 'bool', 'default' => $app['icon_dot_enabled'] ?? true],
                ['name' => 'sys.icon_dot_color', 'label' => 'Farbe des Punkts', 'type' => 'color', 'width' => 'half', 'default' => $app['icon_dot'] ?? '#F6C9A8'],
                ['name' => 'sys.icon_image', 'label' => 'Bild oder Logo', 'type' => 'media',
                    'help' => 'Quadratisch, mindestens 512 × 512 px. Genutzt wird der 1:1-Zuschnitt bzw. der Fokuspunkt; der Rand füllt sich mit der Hintergrundfarbe.'
                        . (\Core\Svg::canRasterize() ? ' SVG-Logos werden ganz eingepasst.' : ' SVG-Logos gehen hier nicht (dem Server fehlt Imagick mit SVG) – bitte ein PNG wählen.')],
                ['type' => 'heading', 'label' => 'Installierbare Web-App (PWA)',
                    'help' => 'Besucher können die Website auf dem Handy „Zum Home-Bildschirm“ hinzufügen – mit eigenem Icon, Namen und Kurzbefehlen (z. B. Kontakt). Für normale Besucher im Browser wird nichts gespeichert.'],
                ['name' => 'sys.pwa', 'label' => 'Als App installierbar machen (Web-App-Manifest)', 'type' => 'bool', 'default' => true],
                ['name' => 'sys.pwa_name', 'label' => 'Name der App', 'type' => 'text', 'width' => 'half', 'placeholder' => $info['name']],
                ['name' => 'sys.pwa_short_name', 'label' => 'Kurzname unter dem Icon', 'type' => 'text', 'width' => 'half', 'max' => 12, 'placeholder' => $info['short_name'],
                    'help' => 'Max. 12 Zeichen, sonst wird er abgeschnitten.'],
                ['name' => 'sys.pwa_theme', 'label' => 'Farbe der Statusleiste', 'type' => 'color', 'width' => 'half', 'default' => $app['icon_bg'] ?? '#16201E'],
                ['name' => 'sys.pwa_bg', 'label' => 'Hintergrund beim Start', 'type' => 'color', 'width' => 'half', 'default' => '#FFFFFF'],
                ['name' => 'sys.pwa_display', 'label' => 'Darstellung', 'type' => 'select', 'required' => true, 'default' => 'standalone',
                    'options' => ['standalone' => 'Wie eine App (ohne Adressleiste)', 'minimal-ui' => 'Mit schmaler Browserleiste', 'browser' => 'Normaler Browser']],
                ['name' => 'sys.pwa_offline', 'label' => 'Offline-Modus in der installierten App', 'type' => 'bool', 'default' => true,
                    'help' => 'Nur wer die App installiert, bekommt einen Service Worker: Zuletzt besuchte Seiten und die Offline-Seite des Kits bleiben ohne Netz abrufbar. Formulare, Verwaltung und API werden nie zwischengespeichert.'],
            ]],
            ['id' => 'proxy', 'label' => 'Karten & externe Quellen', 'fields' => [
                ['type' => 'heading', 'label' => 'Karten (MapLibre + OpenFreeMap)',
                    'help' => 'Karten laden Stil, Kacheln und Schriften über den eigenen Server (/proxy/…). Besucher haben keinen Kontakt zu Dritten – daher ist keine Einwilligung nötig. Den Standort der Website pflegen Sie in den zentralen Angaben, weitere Orte direkt im Block „Karte“.'],
                ['name' => 'sys.map_style', 'label' => 'Kartenstil (Standard)', 'type' => 'select', 'required' => true, 'default' => 'liberty', 'width' => 'half',
                    'options' => Maps::STYLES],
                ['name' => 'sys.map_style_dark', 'label' => 'Kartenstil im Dunkelmodus', 'type' => 'select', 'required' => true, 'default' => 'dark', 'width' => 'half',
                    'options' => Maps::STYLES, 'help' => 'Nur bei Kits mit dunklem Farbschema, wenn Besucher es im Gerät eingestellt haben.'],
                ['name' => 'sys.map_click', 'label' => 'Karte erst nach Klick laden (Zwei-Klick)', 'type' => 'select', 'default' => '', 'width' => 'half',
                    'options' => ['' => 'Vorgabe des Kits', '1' => 'Ja – Hinweis mit Link zur Datenschutzerklärung, Laden nach Klick', '0' => 'Nein – laden, sobald die Karte in Sichtweite kommt'],
                    'help' => 'Auch ohne Drittanbieter-Kontakt kann eine Praxis oder Behörde verlangen, dass Karten erst auf Wunsch laden. Spart außerdem Daten (MapLibre ≈ 800 KB).'],
                ['name' => 'sys.map_radius', 'label' => 'Kartengebiet je Ort (km)', 'type' => 'number', 'default' => 25, 'width' => 'half',
                    'help' => 'Detailkacheln liefert der Proxy nur in diesem Umkreis um Orte, die auf der Website als Karte vorkommen – so kann niemand den Server als kostenlosen Kachelserver missbrauchen.'],
                ['type' => 'heading', 'label' => 'Zwischenspeicher'],
                ['name' => 'sys.proxy_cache_mb', 'label' => 'Maximale Größe (MB)', 'type' => 'number', 'default' => 500, 'width' => 'half',
                    'help' => 'Externe Inhalte werden unter /storage/cache/proxy gespeichert (außerhalb des öffentlichen Ordners). Wird die Größe überschritten, fallen die ältesten Dateien weg.'],
            ]],
            ['id' => 'sprachen', 'label' => 'Sprachen', 'fields' => [
                ['type' => 'heading', 'label' => 'Sprachen der Website',
                    'help' => 'Die erste aktive Sprache ist die Standardsprache (Adressen ohne Präfix). Jede weitere Sprache erhält ein Präfix, z. B. /en/… – Seiten und Einträge werden je Sprache angelegt und miteinander verknüpft.'],
                ['name' => 'sys.languages', 'label' => 'Sprachen', 'type' => 'repeater', 'item_label' => 'Sprache', 'title_field' => 'label',
                    'default' => [['code' => 'de', 'label' => 'Deutsch', 'active' => true]],
                    'fields' => [
                        ['name' => 'code', 'label' => 'Kürzel (ISO, z. B. de, en, fr)', 'type' => 'text', 'required' => true, 'width' => 'half', 'max' => 5],
                        ['name' => 'label', 'label' => 'Bezeichnung', 'type' => 'text', 'required' => true, 'width' => 'half', 'placeholder' => 'z. B. English'],
                        ['name' => 'active', 'label' => 'Aktiv (auf der Website sichtbar)', 'type' => 'bool', 'default' => true],
                    ]],
            ]],
            ['id' => 'mail', 'label' => 'E-Mail-Versand', 'fields' => [
                ['type' => 'heading', 'label' => 'Versand über Symfony Mailer',
                    'help' => 'Für Plesk empfohlen: SMTP mit dem Postfach der Domain (Port 587, STARTTLS). E-Mails enthalten NIE Formularinhalte – nur den Hinweis „Neue Anfrage liegt vor“.'],
                ['name' => 'sys.mail_transport', 'label' => 'Versandart', 'type' => 'select', 'required' => true, 'default' => 'smtp',
                    'options' => ['smtp' => 'SMTP (empfohlen)', 'sendmail' => 'Sendmail des Servers', 'native' => 'PHP mail() / php.ini', 'null' => 'Deaktiviert (nur protokollieren)']],
                ['name' => 'sys.mail_host', 'label' => 'SMTP-Server', 'type' => 'text', 'width' => 'half', 'placeholder' => 'z. B. localhost oder mail.ihre-domain.de'],
                ['name' => 'sys.mail_port', 'label' => 'Port', 'type' => 'number', 'width' => 'half', 'default' => 587],
                ['name' => 'sys.mail_user', 'label' => 'Benutzername', 'type' => 'text', 'width' => 'half'],
                ['name' => 'sys.mail_pass', 'label' => 'Passwort', 'type' => 'secret', 'width' => 'half'],
                ['name' => 'sys.mail_encryption', 'label' => 'Verschlüsselung', 'type' => 'select', 'default' => 'tls', 'required' => true,
                    'options' => ['tls' => 'STARTTLS (Port 587)', 'ssl' => 'SSL/TLS (Port 465)', 'none' => 'Keine (nur localhost)']],
                ['name' => 'sys.mail_verify_peer', 'label' => 'TLS-Zertifikat prüfen', 'type' => 'bool', 'default' => true,
                    'help' => 'Nur deaktivieren, wenn der Server „localhost“ mit abweichendem Zertifikat nutzt.'],
                ['name' => 'sys.mail_from', 'label' => 'Absender-Adresse', 'type' => 'email', 'width' => 'half', 'placeholder' => 'website@ihre-domain.de'],
                ['name' => 'sys.mail_from_name', 'label' => 'Absender-Name', 'type' => 'text', 'width' => 'half', 'default' => 'Website'],
                ['name' => 'sys.mail_to', 'label' => 'Empfänger für Benachrichtigungen', 'type' => 'text',
                    'help' => 'Mehrere Adressen mit Komma trennen.', 'placeholder' => 'info@ihre-domain.de'],
            ]],
            ['id' => 'spam', 'label' => 'Spamschutz', 'fields' => [
                ['type' => 'heading', 'label' => 'Mehrstufiger Schutz ohne externe Dienste',
                    'help' => 'Kein Captcha, keine Cookies, keine Daten an Dritte. Die Stufen ergänzen sich; empfohlen: alle aktiv.'],
                ['name' => 'sys.spam_honeypot', 'label' => 'Honeypot-Feld (unsichtbares Fangfeld)', 'type' => 'bool', 'default' => true],
                ['name' => 'sys.spam_min_seconds', 'label' => 'Mindest-Ausfüllzeit (Sekunden)', 'type' => 'number', 'width' => 'half', 'default' => 4,
                    'help' => 'Schneller abgeschickte Formulare werden verworfen (Bots).'],
                ['name' => 'sys.spam_max_hours', 'label' => 'Formular gültig für (Stunden)', 'type' => 'number', 'width' => 'half', 'default' => 3],
                ['name' => 'sys.spam_pow', 'label' => 'Proof-of-Work (Browser löst unsichtbar eine Rechenaufgabe)', 'type' => 'bool', 'default' => true,
                    'help' => 'Barrierefreie Alternative zu Captchas: kostet echte Besucher ~1 Sekunde Rechenzeit, macht Massen-Spam teuer.'],
                ['name' => 'sys.spam_pow_difficulty', 'label' => 'Schwierigkeit', 'type' => 'select', 'default' => '16', 'required' => true,
                    'options' => ['14' => 'Niedrig', '16' => 'Mittel (empfohlen)', '18' => 'Hoch', '20' => 'Sehr hoch']],
                ['name' => 'sys.spam_rate_ip', 'label' => 'Max. Anfragen je Absender und Stunde', 'type' => 'number', 'width' => 'half', 'default' => 5],
                ['name' => 'sys.spam_rate_global', 'label' => 'Max. Anfragen insgesamt je Stunde', 'type' => 'number', 'width' => 'half', 'default' => 60],
                ['name' => 'sys.spam_block_links', 'label' => 'Anfragen mit Links (http, www) ablehnen', 'type' => 'bool', 'default' => true],
                ['name' => 'sys.spam_blocklist', 'label' => 'Gesperrte Begriffe', 'type' => 'textarea', 'rows' => 3,
                    'help' => 'Ein Begriff pro Zeile. Groß-/Kleinschreibung egal.', 'default' => "viagra\ncasino\ncrypto\nbitcoin\nseo service"],
                ['type' => 'heading', 'label' => 'Anfragen (Eingangs-Tabellen)',
                    'help' => 'Jedes Entschlüsseln, jede Statusänderung und jedes Löschen wird protokolliert (ohne Inhalte). Aufbewahrung erledigter Anfragen: je Eingang unter Daten → Felder & Einstellungen.'],
                ['name' => 'sys.inbox_log_months', 'label' => 'Protokoll aufbewahren (Monate)', 'type' => 'number', 'width' => 'half', 'default' => 12],
            ]],
            // Website-Suche und KI (Core\Search\AdminSettings – je nach Funktionsumfang „search“ bzw. „ai“)
            ...Search\AdminSettings::groups(),
        ];
    }

    public static function fields(): array
    {
        return array_merge(...array_map(fn($g) => $g['fields'], self::groups()));
    }

    public static function defaults(): array
    {
        return Fields::defaults(self::fields());
    }
}
