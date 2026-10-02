<?php
declare(strict_types=1);

namespace Core;

/**
 * Beschreibungen für „Administration → Funktionen & Erweiterungen“: Gruppe, Kurzbeschreibung, „Was passiert beim Einschalten“
 * (Menü, externe Anfragen, Hintergrund/Cron, Website, gespeicherte Daten), Sicherheitshinweis und Vorsicht-Hinweise.
 * Rechte je Funktion kommen aus Features::catalog(), Abhängigkeiten aus Features::REQUIRES.
 * Funktionen von Erweiterungen ohne Eintrag hier erscheinen in der Gruppe ihrer Erweiterung („Erweiterungen“).
 */
final class FeatureInfo
{
    /** Gruppen in Anzeigereihenfolge: Schlüssel => [Bezeichnung, Symbol] */
    public static function groups(): array
    {
        return [
            'content' => [__('Inhalte'), 'files'],
            'data' => [__('Daten'), 'database'],
            'media' => [__('Medien'), 'images'],
            'ai' => [__('KI'), 'sparkle'],
            'interfaces' => [__('Schnittstellen'), 'plugs-connected'],
            'communication' => [__('Kommunikation'), 'chats'],
            'ops' => [__('Sicherheit & Betrieb'), 'shield-check'],
            'ext' => [__('Erweiterungen'), 'puzzle-piece'],
        ];
    }

    /** Bezeichnungen der Wirkungen in „Was passiert beim Einschalten“ */
    public static function effectLabels(): array
    {
        return ['menu' => __('Menü & Seiten'), 'external' => __('Externe Anfragen'), 'cron' => __('Hintergrund & Cron'),
            'frontend' => __('Auf der Website'), 'data' => __('Gespeicherte Daten')];
    }

    /**
     * Beschreibung einer Funktion: group, desc, effects (menu|external|cron|frontend|data => Text), risk (Sicherheitshinweis:
     * Einschalten verlangt Bestätigung und Passwort), caution (Hinweis beim Abschalten).
     */
    public static function get(string $key): array
    {
        $all = self::all();
        if (isset($all[$key])) return $all[$key] + ['effects' => [], 'risk' => null, 'caution' => null];
        return ['group' => 'ext', 'desc' => '', 'effects' => [], 'risk' => null, 'caution' => null];
    }

    /** Sicherheitsrelevant (Bestätigung mit Risiko-Text und Passwort beim Einschalten)? */
    public static function risky(string $key): bool
    {
        return (string) (self::get($key)['risk'] ?? '') !== '';
    }

    private static function all(): array
    {
        return [
            // ------------------------------------------------------------------ Inhalte
            'pages.structure' => ['group' => 'content', 'desc' => __('Seiten anlegen, verschieben und löschen – ohne diese Funktion nur bestehende Seiten bearbeiten.'),
                'effects' => ['menu' => __('Seitenbaum mit „Neue Seite“, Verschieben und Löschen')]],
            'settings' => ['group' => 'content', 'desc' => __('Zentrale Angaben des Kits (Name, Kontakt, Öffnungszeiten …) an einer Stelle pflegen.'),
                'effects' => ['menu' => __('Menüpunkt mit den zentralen Angaben des Kits')]],
            'languages' => ['group' => 'content', 'desc' => __('Inhalte in mehreren Sprachen pflegen und ausliefern.'),
                'effects' => ['menu' => __('Sprachen in den Grundeinstellungen, Übersetzungen je Seite und Eintrag'), 'frontend' => __('Sprachumschalter und Sprach-Adressen (/en/…), hreflang')]],
            'search' => ['group' => 'content', 'desc' => __('Website-Suche für Besucher, optional semantisch über den KI-Anbieter.'),
                'effects' => ['frontend' => __('Suchseite /suche und Suchfeld im Kopf (je nach Kit)'), 'cron' => __('Suchindex abgleichen: search:index (empfohlen alle 15 min)'),
                    'data' => __('Suchindex unter storage/ (Texte der veröffentlichten Seiten), anonyme Suchbegriffe ohne Treffer'), 'external' => __('Nur mit semantischer Suche: Texte gehen an den KI-Anbieter (Embeddings)')]],
            'glossary' => ['group' => 'content', 'desc' => __('Fachbegriffe erklären: Das erste Vorkommen eines Begriffs im Text bekommt ein kleines Hinweisfenster, dazu eine Übersicht A–Z und Detailseiten.'),
                'effects' => ['menu' => __('Glossar (Begriffe, Prüfungen, Import/Export, Einstellungen) und Tabelle Daten → Glossar'),
                    'frontend' => __('Begriffe gepunktet unterstrichen mit Erklärung zum Aufklappen; Stylesheet und kleines Skript nur auf Seiten mit Begriffen; Block „Glossar“'),
                    'data' => __('Datentabelle „glossar“, Übersichtsseite /glossar und Detailseiten /glossar/…')]],
            'maps' => ['group' => 'content', 'desc' => __('Karten-Block mit Kartenmaterial von OpenFreeMap über den eigenen Proxy.'),
                'effects' => ['external' => __('Server lädt Kartenkacheln von OpenFreeMap (Besucher-IP bleibt verborgen)'), 'frontend' => __('Block „Karte“, Karten-Skript nur auf Seiten mit Karte')]],
            'design' => ['group' => 'content', 'desc' => __('Farben, Schriften und Formen im Style-Editor anpassen.'),
                'effects' => ['menu' => __('Administration → Design'), 'frontend' => __('Geänderte Design-Tokens wirken sofort auf der ganzen Website')]],
            'fonts' => ['group' => 'content', 'desc' => __('Schriften aus dem Google-Fonts-Katalog laden und selbst ausliefern.'),
                'effects' => ['menu' => __('Grundeinstellungen → Schriften'), 'external' => __('Nur beim Installieren: Download vom Fontsource-Katalog; Besucher laden nichts von Google'),
                    'data' => __('Schriftdateien unter public/assets/fonts/installed (für alle Websites der Installation)')]],
            'theme_switch' => ['group' => 'content', 'desc' => __('Kit in den Grundeinstellungen wechseln.'),
                'effects' => ['menu' => __('Auswahl des Kits in den Grundeinstellungen'), 'frontend' => __('Ein Wechsel ändert Aussehen, Blöcke und zentrale Angaben der ganzen Website')],
                'caution' => __('Ein Kit-Wechsel betrifft die ganze Website – nur einschalten, wenn wirklich gewechselt werden soll.')],
            'blocks.custom' => ['group' => 'content', 'desc' => __('Eigene Blöcke aus Feldern, sicherer Vorlage und begrenztem CSS bauen.'),
                'effects' => ['menu' => __('Administration → Blöcke'), 'frontend' => __('Freigegebene eigene Blöcke erscheinen im Editor der Redaktion')]],
            'landings' => ['group' => 'content', 'desc' => __('Weitere Domains zeigen eine Seite bzw. einen Seitenzweig dieser Website.'),
                'effects' => ['menu' => __('Administration → Landingpages'), 'frontend' => __('Zusätzliche Domains mit eigener Marke, Canonical und Sitemap')]],
            'redirects' => ['group' => 'content', 'desc' => __('Alte Adressen weiterleiten (301/302) oder als entfernt melden (410); beim Umbenennen und Verschieben von Seiten automatisch.'),
                'effects' => ['menu' => __('Administration → Weiterleitungen (mit 404-Protokoll, Import und Export)'), 'frontend' => __('Nur Adressen ohne Seite werden weitergeleitet – bestehende Seiten gehen immer vor'),
                    'data' => __('Weiterleitungen mit Trefferzahl; die letzten 200 nicht gefundenen Pfade mit Anzahl (ohne IP-Adressen)')],
                'caution' => __('Ausgeschaltet greifen auch vorhandene Weiterleitungen nicht mehr – alte Adressen enden dann mit 404.')],
            'pwa' => ['group' => 'content', 'desc' => __('App-Icon und installierbare Web-App (Manifest, Service-Worker).'),
                'effects' => ['frontend' => __('Manifest /manifest.webmanifest und Service-Worker /sw.js (speichert Seiten im Browser zwischen)')]],

            // ------------------------------------------------------------------ Daten
            'data' => ['group' => 'data', 'desc' => __('Eigene Datentabellen (Aktuelles, Team, Termine …) mit Listen und Detailseiten.'),
                'effects' => ['menu' => __('Menüpunkt „Daten“'), 'frontend' => __('Blöcke „Datenliste“ und „Datensatz-Felder“, Detailseiten'), 'data' => __('Je Tabelle eine Datenbanktabelle data_{name}')]],
            'data.schema' => ['group' => 'data', 'desc' => __('Tabellen und Felder selbst anlegen und ändern (Tabellen-Designer).'),
                'effects' => ['menu' => __('„Neue Tabelle“ sowie „Felder & Einstellungen“ je Tabelle')],
                'caution' => __('Mit dem Tabellen-Designer lassen sich Felder löschen – nur für Personen, die die Datenstruktur verantworten.')],
            'data.shared' => ['group' => 'data', 'desc' => __('Tabellen mit anderen Websites der Installation teilen.'),
                'effects' => ['menu' => __('Grundeinstellungen → Geteilte Daten'), 'data' => __('Geteilte Datenbank unter storage/shared (für mehrere Websites)')]],
            'calendar' => ['group' => 'data', 'desc' => __('Kalender-Tabellen mit Wiederholungen und iCal-Abonnement.'),
                'effects' => ['frontend' => __('Blöcke „Kalender“ und „Nächste Termine“, öffentliche iCal-Adressen /kalender/….ics')]],
            'forms.data' => ['group' => 'data', 'desc' => __('Öffentliche Formulare legen Einträge in Inhaltstabellen an (z. B. Einreichungen).'),
                'effects' => ['frontend' => __('Formular-Block und /formular/… – Besucher können Einträge anlegen (mit Spam-Schutz)'), 'data' => __('Eingaben der Besucher als Einträge (Entwurf)')]],
            'requests' => ['group' => 'data', 'desc' => __('Online-Anfragen: verschlüsselte Eingangs-Tabellen und ihre Formulare.'),
                'effects' => ['menu' => __('Menüpunkt „Anfragen“'), 'frontend' => __('Anfrage-Formulare (/anfrage/…, /api/form/…)'), 'cron' => __('Aufbewahrungsfrist: inbox:purge (täglich empfohlen)'),
                    'data' => __('Anfragen verschlüsselt (libsodium) – lesbar nur mit dem geheimen Schlüssel')]],
            'requests.mail' => ['group' => 'data', 'desc' => __('Anfragen je Eingang zusätzlich oder ausschließlich per E-Mail zustellen – mit vollem Inhalt, Anhängen und optional S/MIME-Verschlüsselung.'),
                'effects' => ['menu' => __('Abschnitt „Zustellung der Anfragen“ in den Einstellungen eines Eingangs'), 'external' => __('Inhalte der Anfragen gehen per E-Mail an die eingetragenen Empfänger (SMTP mit TLS; mit Zertifikat Ende-zu-Ende per S/MIME)'),
                    'data' => __('Modus „nur per E-Mail“: keine Inhalte in der Datenbank, nur ein Zustellprotokoll (ohne Inhalte)')],
                'caution' => __('Gesundheitsdaten nur mit S/MIME-Zertifikat der Empfänger per E-Mail zustellen.')],

            // ------------------------------------------------------------------ Medien
            'media' => ['group' => 'media', 'desc' => __('Mediathek: Bilder und Dateien hochladen, zuschneiden, beschriften.'),
                'effects' => ['menu' => __('Menüpunkt „Medien“'), 'data' => __('Dateien unter public/media bzw. public/sites/{website}/media')]],
            'media.svg' => ['group' => 'media', 'desc' => __('SVG-Grafiken (Logos, Icons, Illustrationen) hochladen – nur bereinigt und optimiert.'),
                'effects' => ['menu' => __('Mediathek nimmt .svg an'), 'data' => __('Gespeichert wird nur die bereinigte Fassung (ohne Skripte, externe Verweise, eingebettete Bilder)')]],
            'media.captions' => ['group' => 'media', 'desc' => __('Untertitel, Kapitel und Transkripte für Videos und Audio.'),
                'effects' => ['menu' => __('Untertitel & Transkripte in der Mediathek'), 'frontend' => __('Untertitel (WebVTT) im Video-Player'), 'external' => __('Nur mit KI-Transkription: Tonspur geht an den eingestellten Dienst (z. B. whisper.cpp lokal)')]],

            // ------------------------------------------------------------------ KI
            'ai' => ['group' => 'ai', 'desc' => __('KI-Funktionen: Texte, Übersetzung, SEO, Alt-Texte, semantische Suche (Symfony AI).'),
                'effects' => ['menu' => __('KI-Bereich, KI-Knöpfe in Editor und Mediathek, Grundeinstellungen → KI'),
                    'external' => __('Texte und ggf. Bilder gehen an den eingestellten KI-Anbieter (Ollama auf eigenem Server oder externer Dienst)'),
                    'cron' => __('Medien-Aufträge (Transkription): ai:jobs'), 'data' => __('Protokoll der KI-Aktionen und Einreichungen (change_log)')],
                'risk' => __('Inhalte der Website – bei Bildern auch Bilddaten – werden an einen KI-Anbieter übertragen. Bei externen Anbietern brauchen Sie einen Auftragsverarbeitungsvertrag und einen Hinweis in der Datenschutzerklärung. KI-Ergebnisse können falsch sein und müssen geprüft werden.')],
            'chat.assistant' => ['group' => 'ai', 'desc' => __('KI-Assistent der Redaktion: Hilfe zur Bedienung und Aktionen mit Bestätigung.'),
                'effects' => ['menu' => __('Assistent im KI-Bereich und in der Seitenleiste'), 'external' => __('Fragen der Redaktion und Seitenkontext gehen an den KI-Anbieter')]],
            'chat.visitor' => ['group' => 'ai', 'desc' => __('KI-Chat für Besucher, der nur aus den Inhalten der Website antwortet.'),
                'effects' => ['frontend' => __('Chat-Knopf und Skript auf der Website (nach Einschalten unter Grundeinstellungen → KI), kein Cookie'),
                    'external' => __('Fragen der Besucher gehen an den KI-Anbieter'), 'data' => __('Tageszähler; Gesprächsinhalte werden nicht dauerhaft gespeichert')],
                'risk' => __('Texte von Besucherinnen und Besuchern gehen an einen KI-Anbieter – auch Angaben, die sie besser nicht eingeben sollten. Datenschutzerklärung ergänzen, Tageslimit setzen, bei externen Anbietern AV-Vertrag abschließen. Antworten können falsch sein.')],

            // ------------------------------------------------------------------ Schnittstellen
            'api' => ['group' => 'interfaces', 'desc' => __('REST-API (OpenAPI 3.1) für Programme mit Zugangs-Token.'),
                'effects' => ['menu' => __('Administration → API & MCP (Tokens)'), 'external' => __('Programme mit Token lesen und – je nach Token – ändern Inhalte über /api/v1'),
                    'data' => __('Token-Hashes, Zeitpunkt der letzten Nutzung, Protokoll der Änderungen')],
                'risk' => __('Die REST-API öffnet die Website für Programme: Wer ein Token besitzt, kann – je nach Token – Inhalte lesen, ändern und veröffentlichen. Tokens nur sparsam, mit knapper Berechtigung und Ablaufdatum ausstellen; Änderungen auf Wunsch über „Eingereicht“ prüfen.')],
            'mcp' => ['group' => 'interfaces', 'desc' => __('MCP-Server für KI-Assistenten (Claude, ChatGPT & Co.) mit Zugangs-Token.'),
                'effects' => ['menu' => __('Administration → API & MCP (Tokens)'), 'external' => __('KI-Assistenten mit Token arbeiten über /mcp mit den Inhalten'),
                    'data' => __('Token-Hashes, gemeldeter MCP-Client, Protokoll der Änderungen')],
                'risk' => __('Über MCP arbeiten KI-Assistenten direkt mit den Inhalten dieser Website. Was der Assistent sieht, geht an dessen Anbieter. Tokens nur mit knapper Berechtigung ausstellen und Änderungen bevorzugt über „Eingereicht“ freigeben.')],
            'sources' => ['group' => 'interfaces', 'desc' => __('Externe Quellen: RSS/Atom, JSON-APIs, XML und OpenImmo in Datentabellen übernehmen.'),
                'effects' => ['menu' => __('Daten → Externe Quellen'), 'external' => __('Der Server ruft die eingetragenen Adressen regelmäßig ab'),
                    'cron' => __('Abgleich: sources:sync (empfohlen alle 15 min)'), 'data' => __('Übernommene Einträge, Zugangsdaten verschlüsselt, Abruf-Protokoll')],
                'risk' => __('Der Server ruft fremde Adressen ab und übernimmt deren Inhalte in Ihre Website. Nur vertrauenswürdige Quellen eintragen; Zugangsdaten werden verschlüsselt gespeichert, bleiben aber für Administratoren nutzbar.')],
            'review' => ['group' => 'interfaces', 'desc' => __('Eingereicht: Änderungen von API, MCP und KI prüfen und freigeben.'),
                'effects' => ['menu' => __('„Eingereicht“ mit Vergleich und Freigabe'), 'data' => __('Protokoll mit Vorher/Nachher (change_log, Einträge nach 365 Tagen gelöscht)')]],

            // ------------------------------------------------------------------ Kommunikation
            'chat' => ['group' => 'communication', 'desc' => __('Chat zwischen Benutzern der Verwaltung (Direktnachrichten, Kanäle).'),
                'effects' => ['menu' => __('Menüpunkt „Chat“, Chat-Einstellungen'), 'cron' => __('chat:purge (Aufbewahrung) und chat:digest (E-Mail-Hinweise)'),
                    'data' => __('Nachrichten und Bilder bis zur Aufbewahrungsfrist')]],
            'support' => ['group' => 'communication', 'desc' => __('Support & Wissensdatenbank: Probleme melden, Fragen & Antworten.'),
                'effects' => ['menu' => __('„Problem melden“ und „Support“ unter Hilfe & Support'), 'data' => __('Meldungen in der zentralen Support-Datenbank der Installation')]],

            // ------------------------------------------------------------------ Sicherheit & Betrieb
            'users' => ['group' => 'ops', 'desc' => __('Benutzer und Rollen verwalten.'),
                'effects' => ['menu' => __('Administration → Benutzer & Rollen')],
                'caution' => __('Aus = niemand (auch nicht die Administration) kann Benutzer anlegen oder Rollen ändern, bis die Funktion wieder an ist.')],
            'system' => ['group' => 'ops', 'desc' => __('Grundeinstellungen (Adresse, E-Mail, Schlüssel, Sicherheit).'),
                'effects' => ['menu' => __('Administration → Grundeinstellungen')],
                'caution' => __('Aus = die Grundeinstellungen sind für alle gesperrt, bis die Funktion wieder an ist.')],
            // Funktionen der mitgelieferten Erweiterungen (erscheinen nur, wenn die Erweiterung läuft)
            'video.tools' => ['group' => 'media', 'desc' => __('Videos der Mediathek prüfen, fürs Web optimieren, schneiden und mit Poster versehen.'),
                'effects' => ['menu' => __('Administration → Video-Werkzeuge, Werkzeuge in der Mediathek'), 'cron' => __('Aufträge: video:work (jede Minute empfohlen)'),
                    'data' => __('Aufträge, Protokolle und optimierte Versionen in der Mediathek')],
                'risk' => __('Startet ffmpeg/ffprobe als Prozesse auf dem Server (proc_open) und verarbeitet hochgeladene Dateien. Nur einschalten, wenn Videos wirklich hier bearbeitet werden; Speicherplatz und CPU-Last im Blick behalten.')],
            'dav' => ['group' => 'interfaces', 'desc' => __('Termine und Kontakte mit Kalender- und Adressbuch-Apps abgleichen.'),
                'effects' => ['menu' => __('Konto → „Kalender & Kontakte in Apps“ (App-Passwörter); Tabellen unter Administration → Einstellungen'), 'external' => __('Apps greifen mit App-Passwort über /dav auf Tabellen zu')],
                'risk' => __('Öffnet /dav für Kalender- und Adressbuch-Apps. Wer ein App-Passwort hat, kann freigegebene Tabellen lesen und – je nach Passwort – ändern.')],
            'consent' => ['group' => 'ops', 'desc' => __('Cookie-Einwilligung: Dienste, Hinweis, Protokoll.'),
                'effects' => ['menu' => __('Administration → Einstellungen → Cookie-Einwilligung'), 'frontend' => __('Hinweis und Skript auf der Website, sobald ein einwilligungspflichtiger Dienst aktiv ist; Cookie mit der Auswahl'),
                    'data' => __('Einwilligungs-Protokoll ohne IP-Adresse und User-Agent')]],
        ];
    }
}
