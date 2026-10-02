<?php
// SPDX-License-Identifier: MIT
/*
 * Startinhalte des Kits „frameworks“ – spielt der Core beim ersten Aufruf einer neuen Website ein (Core\Seeder).
 * Fiktive Firma „Beispielwerk“; alle Namen, Projekte und Adressen sind erfunden.
 *   settings  Formular „Website“ (theme.php § 7)
 *   after     Seiten, Bilder, Datentabelle „Projekte“ mit Detailseite, Formular „Anfragen“, Glossar (tools/demo-content.php)
 * Bestehende Website neu befüllen: CMS_SITE=<website> php kits/frameworks/tools/demo.php --force
 */
return [
    'settings' => [
        'org_name' => 'Beispielwerk (fiktiv)',
        'short_name' => 'Beispielwerk',
        'tagline' => 'Demo-Firma für das Kit „frameworks“ – Tailwind CSS oder UIkit.',
        'email' => 'hallo@example.com',
        'footer_text' => 'Demo-Inhalte des Kits „frameworks“: Firma, Projekte und Adressen sind frei erfunden.',
        'site_title' => 'Beispielwerk – ein Kit, zwei Frameworks',
        'default_meta_description' => 'Demo-Website: dieselben Inhalte mit Tailwind CSS oder UIkit – mit Bearbeiten auf der Website, Glossar, Datenlisten und Formularen.',
    ],
    'pages' => [],
    'page_refs' => [],
    'after' => function () {
        require_once __DIR__ . '/tools/demo-content.php';
        frameworks_demo_install();
    },
];
