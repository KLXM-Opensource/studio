<?php
// SPDX-License-Identifier: MIT
/*
 * Startinhalte – spielt der Core beim ersten Aufruf einer neuen Website ein (Core\Seeder), bzw. neu mit
 *   php bin/console reseed --force --site=<website>      (löscht ALLE Inhalte der Website!)
 *
 *   settings   Werte für das Formular „Website“ (theme.php § 7)
 *   pages      Seiten mit Blöcken: slug, title, is_home, menu, meta_description, blocks[type, data, tunes]
 *              (tunes = Abschnitts-Optionen: background, anchor, showInNav, navLabel, spaceTop, …)
 *   page_refs  Einstellung → slug einer Seite, z. B. 'imprint_page' => 'impressum' (wird zur Seiten-ID)
 *   after      optional: function (\Core\App $app) { … } für weitere Inhalte (Datentabellen, Medien, Seitenbäume)
 */
return [
    'settings' => [
        'org_name' => 'Beispiel GmbH',
        'short_name' => 'Beispiel',
        'tagline' => 'Ein neutrales Start-Kit für KLXM Studio.',
        'email' => 'hallo@example.com',
        'phone' => '',
        'street' => '',
        'zip' => '',
        'city' => '',
        'footer_text' => 'Demo-Inhalte des Start-Kits – Namen und Texte sind Beispiele.',
        'site_title' => 'Beispiel – Start-Kit für KLXM Studio',
        'default_meta_description' => 'Demo-Website des Start-Kits: sechs Beispielblöcke, Design-Tokens, hell und dunkel.',
        'schema_type' => 'Organization',
    ],
    'pages' => require __DIR__ . '/tools/demo-content.php',
    'page_refs' => [],
    // 'after' => function (\Core\App $app) { require_once __DIR__ . '/tools/demo-extra.php'; starter_demo_extra(); },
];
