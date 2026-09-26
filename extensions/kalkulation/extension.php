<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kalkulation & Angebote. Copyright (C) 2026 KLXM and contributors (see LICENSE)
/**
 * Erweiterung „kalkulation“: interne Preiskalkulation und Angebote – Stundensätze und Aufschläge, Leistungskatalog mit
 * Paketen, Kalkulationen mit Live-Summen (intern: Kosten, Marge, effektiver Stundensatz), Angebotsansicht zum Drucken/PDF, CSV.
 * Ersetzt die Arbeitsmappe „KLXM-Kalkulation-intern.xlsx“. Nur Verwaltung – nichts davon erscheint auf der Website.
 *
 * Aktivieren je Website:  Administration → Funktionen & Erweiterungen (Haupt-Admin) oder per Konfiguration
 *                         'extensions' => ['kalkulation'], 'features' => ['kalkulation' => true] (Funktion ist Standard AUS).
 * Recht:                  kalkulation.manage – Administration hat es immer, andere Rollen nur ausdrücklich.
 * Doku: extensions/kalkulation/README.md, Verwaltung → Hilfe → Handbuch „Kalkulation & Angebote“.
 */
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Klxm\\Kalkulation\\')) {
        $file = __DIR__ . '/src/' . substr($class, strlen('Klxm\\Kalkulation\\')) . '.php';
        if (is_file($file)) require $file;
    }
});

use Klxm\Kalkulation\AdminController;
use Klxm\Kalkulation\Kalkulation;
use Klxm\Kalkulation\Repo;

return [
    'name' => 'kalkulation',
    'label' => 'Kalkulation & Angebote',
    'version' => '1.0.0',
    'requires' => '>=1.0.0',
    'description' => 'Preise intern kalkulieren und Angebote erstellen: Stundensätze, Aufschläge, Leistungskatalog, Kalkulationen mit Marge und effektivem Stundensatz, Angebotsansicht zum Drucken und CSV. Nur in der Verwaltung.',
    'author' => 'KLXM Crossmedia GmbH and contributors',
    'license' => 'MIT',
    'provides' => ['Menüpunkt „Kalkulation“ (Kalkulationen, Leistungskatalog, Einstellungen)', 'Recht „Kalkulationen, Katalog und Einstellungen verwalten“ (nur Administration, sofern nicht vergeben)',
        'Tabellen kalk_* in der Datenbank dieser Website (Beispiel-Katalog ohne Beträge)', 'Keine Website-Adressen, keine API/MCP, kein Suchindex'],
    'docs' => ['Handbuch: Kalkulation & Angebote' => '/admin/hilfe#kalkulation'],
    'usage' => function (): ?string {
        if (!Repo::ready()) return null;
        $n = (int) app()->db->fetchValue('SELECT COUNT(*) FROM kalk_calcs');
        return $n ? __('{n} Kalkulationen', ['n' => $n]) : null;
    },
    'boot' => function (Core\Extension $x): void {
        $x->feature(Kalkulation::FEATURE, 'Kalkulation & Angebote (intern)', [Kalkulation::PERM], false);
        $x->permissions('Kalkulation', [Kalkulation::PERM => 'Kalkulationen, Leistungskatalog und Einstellungen verwalten (Preise, Kosten, Margen)']);
        $x->migration(1, fn(Core\Database $db) => Repo::migrate($db));
        $x->nav('/admin/kalkulation', 'Kalkulation', 'calculator', Kalkulation::PERM);

        // Oberfläche nur auf den eigenen Seiten (Ansichten „calculator/…“ – der Menü-Schlüssel ist zugleich der Symbolname)
        $x->adminAssets(fn(string $view) => str_starts_with($view, 'calculator/') ? ['css/kalkulation.css', 'js/kalkulation.js'] : []);

        // Handbuch-Kapitel nur für Berechtigte (sonst bleibt die Erweiterung in der Hilfe unsichtbar). Das Recht lässt sich
        // erst nach dem Sitzungsstart prüfen – NICHT hier in boot (Auth::user() würde „nicht angemeldet“ zwischenspeichern).
        // Die Prüfung je Verwaltungsaufruf (AdminController::auth → afterAdminResponse) läuft nach der Anmeldung und vor
        // der Ausgabe der Hilfe; sie meldet das Kapitel an und gibt keine Nacharbeit zurück.
        $x->afterAdminResponse(function () use ($x): null {
            if (Kalkulation::allowed() && !isset($x->docChapters['manual']['kalkulation'])) {
                $x->docs('manual', ['kalkulation' => ['title' => __('Kalkulation & Angebote'), 'file' => __DIR__ . '/docs/manual.php', 'after' => 'daten']]);
            }
            return null;
        });

        $x->routes(function (Core\Http\Router $r): void {
            $c = AdminController::class;
            $b = '/admin/kalkulation';
            $r->get($b, [$c, 'index']);
            $r->post($b . '/neu', [$c, 'create']);
            // feste Pfade vor /{id}
            $r->get($b . '/katalog', [$c, 'catalog']);
            $r->post($b . '/katalog/neu', [$c, 'serviceNew']);
            $r->get($b . '/katalog/{sid}', [$c, 'service']);
            $r->post($b . '/katalog/{sid}', [$c, 'serviceSave']);
            $r->post($b . '/katalog/{sid}/loeschen', [$c, 'serviceDelete']);
            $r->post($b . '/katalog/{sid}/verschieben', [$c, 'serviceMove']);
            $r->get($b . '/einstellungen', [$c, 'settings']);
            $r->post($b . '/einstellungen', [$c, 'settingsSave']);
            $r->post($b . '/einstellungen/verschluesselung', [$c, 'encryption']);
            $r->get($b . '/{id}', [$c, 'edit']);
            $r->post($b . '/{id}/speichern', [$c, 'save']);
            $r->post($b . '/{id}/sichern', [$c, 'snapshot']);
            $r->post($b . '/{id}/stand/{vid}', [$c, 'restore']);
            $r->post($b . '/{id}/duplizieren', [$c, 'duplicate']);
            $r->post($b . '/{id}/loeschen', [$c, 'delete']);
            $r->get($b . '/{id}/angebot', [$c, 'offer']);
            $r->get($b . '/{id}/positionen.csv', [$c, 'csv']);
        });
    },
];
