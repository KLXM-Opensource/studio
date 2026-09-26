<?php
/*
 * Standard-Konfiguration (versioniert).
 * Projektspezifische Werte und Geheimnisse gehören in config.local.php –
 * diese Datei wird beim ersten Aufruf automatisch erzeugt.
 */
return [
    // Wird in config.local.php gesetzt (HMAC für Tokens, Hashes).
    'app_key'     => '',
    // Einmal-Token für /admin/setup (erstes Admin-Konto anlegen).
    'setup_token' => '',

    'debug'    => false,
    'timezone' => 'Europe/Berlin',
    'locale'   => 'de',

    // Leer = automatisch aus dem Request ermitteln. Für Canonical/Sitemap
    // empfiehlt sich die feste Angabe, z. B. 'https://www.ihre-domain.de'.
    'base_url' => '',

    // true  = saubere URLs (/impressum). Erfordert FallbackResource bzw. try_files
    //         (siehe README → Plesk). Ohne .htaccess!
    // false = /index.php/impressum – funktioniert ohne jede Server-Konfiguration.
    'url_rewrite' => true,

    // Theme-Vorgabe, falls in den Grundeinstellungen keines gewählt ist (leer = Vorgabe der Website bzw. erstes installiertes).
    // Je Website überschreibbar in config/sites/{key}.php
    'theme' => '',

    // Multi-Site: Website für Domains, die in keiner config/sites/*.php stehen.
    // 'default' = Hauptwebsite, null = unbekannte Domains abweisen (empfohlen, sobald mehrere Websites laufen).
    'fallback_site' => 'default',
    // Website, deren Verwaltung die Übersicht aller Websites zeigt (Grundeinstellungen → Websites)
    'network_site' => 'default',

    // Sicherungen (site:backup, pool:backup, shared:backup): nur die neuesten N je Website/Pool/Tabelle behalten (0 = alle); --keep=N
    'backup_keep' => 14,

    'db' => [
        'driver'  => 'sqlite',                        // sqlite | mysql
        'path'    => ROOT . '/storage/database/site.sqlite',
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => '',
        'user'    => '',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name'     => 'cms_sess',
        'lifetime' => 60 * 60 * 8,
    ],

    // Ganzseiten-Cache für Besucher (wird bei jeder Änderung geleert)
    'page_cache' => true,

    'media' => [
        'max_upload_mb' => 50,   // Upload in Stücken – unabhängig von upload_max_filesize
        'sizes'         => [480, 800, 1200, 1600, 2400],
        'quality'       => 80,
    ],

    // Anzahl gespeicherter Revisionen pro Seite
    'revisions' => 20,

    // Produkt-Website mit Tutorial-Videos und Trailer (Handbuch & Hilfe › Tutorials, Übersicht „Hilfe & Einstieg“).
    // Die Verwaltung lädt von dort nichts – sie setzt nur Links (neuer Tab). Agenturen: eigene Adresse (White-Label)
    // oder '' = keine Links nach außen (die Schritte als Text bleiben). Je Website überschreibbar in config/sites/{key}.php
    'docs_url' => 'https://studio.klxm.de',
];
