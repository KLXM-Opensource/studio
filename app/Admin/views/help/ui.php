<?php
/**
 * Musterseite der Verwaltungsbausteine (Core\AdminUi) – selbst damit gebaut. Jeder Teil zeigt den Baustein live und den Code dazu.
 * Ausführlich: Entwicklerhandbuch → Oberfläche (#oberflaeche). @var string $part aufbau|zeilen|formular|navigation
 */
use Core\AdminUi;

$helpTab = 'technical';
include ROOT . '/app/Admin/views/support/_helptabs.php';
$code = fn(string $s): string => '<pre class="ui-code"><code>' . e(trim($s)) . '</code></pre>';
$href = fn(string $t): string => url('/admin/hilfe/ui' . ($t === 'aufbau' ? '' : '?teil=' . $t));

echo AdminUi::head(__('Oberfläche'), __('Entwicklerhandbuch'), __('Alle Bausteine der Verwaltung live – Vorlage für eigene Seiten und Erweiterungen.'),
    '<a class="adm-btn adm-btn--ghost" href="' . e(url('/admin/hilfe/technik#oberflaeche')) . '">' . icon('book-open') . ' ' . e(__('Kapitel im Entwicklerhandbuch')) . '</a>');

echo AdminUi::open([
    'aufbau' => ['href' => $href('aufbau'), 'label' => 'Seitenaufbau', 'icon' => 'layout'],
    'zeilen' => ['href' => $href('zeilen'), 'label' => 'Gruppen & Zeilen', 'icon' => 'list-bullets'],
    'formular' => ['href' => $href('formular'), 'label' => 'Formulare', 'icon' => 'pencil-simple', 'badge' => 2],
    'diagramme' => ['href' => $href('diagramme'), 'label' => 'Diagramme', 'icon' => 'chart-bar'],
    '-',
    'navigation' => ['href' => $href('navigation'), 'label' => 'Hauptnavigation', 'icon' => 'sidebar-simple', 'warn' => 'Beispiel für einen Hinweis'],
], $part, 'Bausteine');
?>
<?php if ($part === 'aufbau'): ?>
<?= AdminUi::group('So ist jede Seite aufgebaut', [
    AdminUi::row('Kopf', '<code>AdminUi::head()</code>', 'Dachzeile (Bereich), Titel, eine Zeile Beschreibung, Aktionen rechts – mehr nicht.'),
    AdminUi::row('Bereichsliste links', '<code>open()</code> / <code>tabs()</code>', 'Wie die macOS-Systemeinstellungen: Symbol + Name, aktiver Bereich markiert, Trennlinien zwischen Themen. Unter 1000 px wird sie automatisch zum Auswahlfeld.'),
    AdminUi::row('Inhalt rechts', '<code>group()</code> / <code>row()</code>', 'Gruppen mit Überschrift, darin Zeilen: Beschriftung und Erklärung links, Bedienung rechts.'),
    AdminUi::row('Speichern', '<code>savebar()</code>', 'Feste Leiste unten, nur auf Seiten mit Formular.'),
], 'Diese Seite ist selbst mit AdminUi gebaut – der Quelltext liegt in app/Admin/views/help/ui.php.') ?>

<?= AdminUi::group('Zwei Arten von Bereichslisten', [
    AdminUi::row('open() / close()', '', 'Eine Adresse je Bereich (wie hier: ?teil=…). Für Bereiche mit eigenen Daten, Listen oder Formularen – z. B. Netzwerk, Mitglieder, AVV, Cookie-Einwilligung.', ['stack' => true]),
    AdminUi::row('tabs()', '', 'Alle Bereiche auf einer Seite, Wechsel ohne Neuladen, Adresse #bereich. Für Einstellungen, die zusammen gespeichert werden – z. B. Grundeinstellungen, Konto, Style-Editor. Bereiche mit Fehlern öffnen beim Laden.', ['stack' => true]),
]) ?>

<h3 class="ui-h3">Code</h3>
<?= $code(<<<'PHP'
use Core\AdminUi;

echo AdminUi::head(__('Mitglieder'), __('Erweiterung'), __('Einladen, Gruppen und geschützte Seiten'),
    '<a class="adm-btn adm-btn--ghost" href="…">' . icon('arrow-square-out') . ' ' . e(__('Anmeldeseite')) . '</a>');

echo AdminUi::open([
    'members'  => ['href' => url('/admin/mitglieder'), 'label' => __('Mitglieder'), 'icon' => 'users', 'badge' => 3],
    'groups'   => ['href' => url('/admin/mitglieder/gruppen'), 'label' => __('Gruppen'), 'icon' => 'users-three'],
    '-',                                                         // Trennlinie
    'settings' => ['href' => url('/admin/mitglieder/einstellungen'), 'label' => __('Einstellungen'), 'icon' => 'gear-six',
                   'warn' => __('Absender fehlt')],              // Punkt + Hinweis für Screenreader
], $current, __('Mitglieder'));
// … Inhalt des Bereichs …
echo AdminUi::close();
PHP) ?>

<?= AdminUi::group('Regeln', [
    AdminUi::row('Keine Reiterleiste oben', '', 'Bereiche immer als Liste links. Reiter oben nur noch für Ansichten derselben Liste (z. B. Liste/Kalender).'),
    AdminUi::row('Eine Hauptaktion', '', 'Neu anlegen, Öffnen o. ä. rechts im Kopf; alles Weitere in der Zeile oder unter ⋯.'),
    AdminUi::row('Symbole aus dem Satz', '', 'Namen aus Handbuch → Symbole (Core\\Icons), keine Emojis.'),
    AdminUi::row('Texte', '', 'Über __(), Sie-Form, Beschreibung höchstens eine Zeile. Neue Texte in lang/en.php.'),
    AdminUi::row('Ältere Kerne', '', 'Erweiterungen prüfen class_exists(\\Core\\AdminUi::class) und behalten sonst ihre bisherige Navigation.'),
]) ?>

<?php elseif ($part === 'zeilen'): ?>
<?= AdminUi::group('Zeilen mit Bedienung rechts', [
    AdminUi::row('Benachrichtigungen', '<label class="ui-demo-toggle"><input type="checkbox" checked> an</label>', 'Schalter, Auswahl oder ein kurzer Wert stehen rechts.'),
    AdminUi::row('Letzte Sicherung', '<span class="adm-muted">heute, 03:12</span>', 'Nur lesen: Wert rechts, grau.'),
    AdminUi::row('Verbindung', '<button type="button" class="adm-btn adm-btn--ghost adm-btn--sm">Testen</button>', 'Knopf für eine Aktion in genau dieser Zeile.'),
], 'Hinweis unter der Gruppe: gilt für alle Zeilen darüber.') ?>

<?= AdminUi::group('Zeilen als Link', [
    AdminUi::link('Absender & Postfach', url('/admin/hilfe/ui?teil=zeilen'), 'Öffnet eine Unterseite', 'info@example.org'),
    AdminUi::link('Vorlagen', url('/admin/hilfe/ui?teil=zeilen'), '', '3'),
]) ?>

<?= AdminUi::group('Inhalt unter der Beschriftung', [
    AdminUi::row('Einbettung', '<code>&lt;script src="…/widget.js"&gt;&lt;/script&gt;</code>', 'stack: für längere Texte, Listen, Tabellen.', ['stack' => true]),
]) ?>

<h3 class="ui-h3">Code</h3>
<?= $code(<<<'PHP'
echo AdminUi::group(__('Verbindung'), [
    AdminUi::row(__('Server'), e($host), __('Wird beim Speichern geprüft')),
    AdminUi::row(__('Verbindung'), '<button class="adm-btn adm-btn--ghost adm-btn--sm" …>' . e(__('Testen')) . '</button>'),
    AdminUi::link(__('Absender & Postfach'), url('/admin/…/absender'), __('Öffnet eine Unterseite'), $from),
    AdminUi::row(__('Einbettung'), '<code>…</code>', '', ['stack' => true]),
], __('Hinweis unter der Gruppe'));
PHP) ?>

<?php elseif ($part === 'formular'): ?>
<?php
$fields = [
    ['type' => 'heading', 'label' => 'Absender', 'help' => 'Aus Fields::renderGroups: dieselben Gruppen und Zeilen wie oben, Feldnamen wie bei Fields::renderForm.'],
    ['name' => 'from_name', 'type' => 'text', 'label' => 'Name', 'help' => 'Erscheint im Postfach der Empfänger'],
    ['name' => 'from_mail', 'type' => 'email', 'label' => 'E-Mail-Adresse'],
    ['type' => 'heading', 'label' => 'Versand'],
    ['name' => 'active', 'type' => 'bool', 'label' => 'Versand eingeschaltet', 'help' => 'Standard aus'],
    ['name' => 'mode', 'type' => 'select', 'label' => 'Häufigkeit', 'options' => ['now' => 'sofort', 'daily' => 'täglich', 'weekly' => 'wöchentlich']],
    ['type' => 'heading', 'label' => 'Für Fortgeschrittene', 'collapse' => true],
    ['name' => 'note', 'type' => 'textarea', 'label' => 'Interne Notiz'],
];
?>
<form method="get" action="<?= e(url('/admin/hilfe/ui')) ?>" class="adm-panel--groups" novalidate>
  <input type="hidden" name="teil" value="formular">
  <?= \Core\Fields::renderGroups($fields, ['from_name' => 'Musterverein', 'mode' => 'daily']) ?>
  <?= AdminUi::savebar('Beispiel – speichert nichts.') ?>
</form>

<h3 class="ui-h3">Code</h3>
<?= $code(<<<'PHP'
// Felder wie überall (Fields), 'heading' beginnt eine Gruppe ('collapse' => true: aufklappbar)
<form method="post" action="<?= e(url('/admin/…')) ?>" class="adm-panel--groups" novalidate>
  <?= csrf_field() ?>
  <?= \Core\Fields::renderGroups($fields, $values, $errors) ?>
  <?= AdminUi::savebar() ?>
</form>

// Einstellungen in mehreren Bereichen, ein Formular, Wechsel ohne Neuladen (#bereich):
echo AdminUi::tabs([
    'allgemein' => ['label' => __('Allgemein'), 'icon' => 'gear-six', 'html' => Fields::renderGroups($f1, $values, $errors),
                    'error' => (bool) array_intersect_key($errors, array_flip(array_column($f1, 'name')))],
    'versand'   => ['label' => __('Versand'), 'icon' => 'paper-plane-tilt', 'html' => Fields::renderGroups($f2, $values, $errors)],
], ['action' => url('/admin/…'), 'savebar' => true], __('Einstellungen'));
PHP) ?>
<?= AdminUi::group('Live-Beispiele für tabs()', [
    AdminUi::link('Konto', url('/admin/account'), 'Mehrere Formulare, je Bereich eines'),
    AdminUi::link('Style-Editor', url('/admin/design'), 'Ein Formular mit Vorschau'),
]) ?>

<?php elseif ($part === 'diagramme'): ?>
<?php
$days = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
$demo = [
    ['Linie', ['type' => 'line', 'labels' => $days, 'series' => [['name' => 'Besucher', 'data' => [120, 132, 101, 134, 190, 230, 210]], ['name' => 'Vorwoche', 'data' => [98, 110, 115, 120, 150, 160, 170]]], 'description' => 'Besucher je Tag, diese Woche und Vorwoche']],
    ['Fläche, gestapelt', ['type' => 'area', 'stacked' => true, 'labels' => $days, 'series' => [['name' => 'Mobil', 'data' => [60, 70, 55, 72, 110, 150, 140]], ['name' => 'Desktop', 'data' => [60, 62, 46, 62, 80, 80, 70]]], 'description' => 'Besucher je Gerät']],
    ['Balken', ['type' => 'bar', 'labels' => ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun'], 'series' => [['name' => 'Anfragen', 'data' => [12, 19, 15, 22, 30, 27]]], 'description' => 'Anfragen je Monat']],
    ['Balken waagerecht', ['type' => 'hbar', 'labels' => ['/', '/leistungen', '/kontakt', '/team', '/blog'], 'series' => [['name' => 'Aufrufe', 'data' => [940, 420, 310, 180, 150]]], 'description' => 'Meistbesuchte Seiten']],
    ['Ring', ['type' => 'donut', 'labels' => ['Mobil', 'Desktop', 'Tablet'], 'series' => [['name' => 'Geräte', 'data' => [61, 33, 6]]], 'format' => 'percent', 'description' => 'Anteil der Geräte']],
    ['Torte, Speicher', ['type' => 'pie', 'labels' => ['Bilder', 'Videos', 'Datenbank'], 'series' => [['name' => 'Speicher', 'data' => [10802613, 5242880, 1540096]]], 'format' => 'bytes', 'description' => 'Speicher nach Art']],
];
?>
<div class="ui-charts">
  <?php foreach ($demo as [$name, $spec]): ?>
  <section class="adm-card ui-chart"><h3 class="ui-h3"><?= e($name) ?></h3><?= \Core\Charts::render($spec + ['height' => 240]) ?></section>
  <?php endforeach; ?>
</div>

<h3 class="ui-h3">Code</h3>
<?= $code(<<<'PHP'
// Eigenes, schlichtes Format – kein ECharts-Format. Die Engine (Standard: ECharts) lässt sich tauschen, ohne Aufrufer zu ändern.
echo \Core\Charts::render([
    'type'        => 'line',                    // line | area | bar | hbar | pie | donut
    'labels'      => ['Mo', 'Di', 'Mi'],
    'series'      => [['name' => __('Besucher'), 'data' => [120, 132, 101]]],
    'title'       => __('Besucher'),            // sichtbarer Titel (optional)
    'description' => __('Besucher je Tag'),     // für Screenreader
    'format'      => 'number',                  // number | percent | bytes | duration
    'stacked'     => false, 'height' => 280,
]);

// Andere Engine anmelden (Erweiterung oder Kit) und wählen: 'charts' => ['engine' => 'meine'] bzw. theme.php 'chart_engine'
\Core\Charts::registerEngine('meine', asset('ext/meine/charts-meine.mjs'));
// charts-meine.mjs: export function render(el, spec, theme) { …; return { update(theme) {}, resize() {}, dispose() {} }; }
PHP) ?>
<?= AdminUi::group('Regeln', [
    AdminUi::row('Eine Achse', '', 'Nie zwei Skalen in einem Diagramm – zwei Werte mit verschiedener Größe gehören in zwei Diagramme.'),
    AdminUi::row('Feste Farben', '', 'Reihen bekommen die Farben in fester Reihenfolge (--chart-1 … --chart-8); mehr als acht Reihen zu „Sonstige“ zusammenfassen.'),
    AdminUi::row('Tabelle', '', 'Jedes Diagramm hat „Als Tabelle anzeigen“ – ohne JavaScript bleibt nur die Tabelle.'),
    AdminUi::row('Laden', '', 'Die Engine lädt erst, wenn ein Diagramm sichtbar wird; ohne Diagramm lädt nichts.'),
]) ?>

<?php else: ?>
<?= AdminUi::group('Wo eine Seite in der Seitenleiste erscheint', [
    AdminUi::row('content', 'Hauptmenü', 'Arbeit mit Inhalten: Einträge, Buchungen, Mitglieder, Verträge …'),
    AdminUi::row('tool', 'Administration → Werkzeuge', 'Arbeitsabläufe: prüfen, importieren, Video bearbeiten …'),
    AdminUi::row('settings', 'Sammelseite „Einstellungen der Funktionen“', 'Reine Konfiguration – nie ein eigener Menüpunkt. Mit table zusätzlich an der Datentabelle.'),
    AdminUi::row('stats', 'Sammelseite „Statistiken“', 'Berichte und Auswertungen.'),
], 'Die Art bestimmt den Ort. Adresse und Recht bleiben gleich, egal wo der Punkt steht.') ?>

<h3 class="ui-h3">Anmelden (extension.php → boot)</h3>
<?= $code(<<<'PHP'
'boot' => function (Core\Extension $x) {
    $x->adminPage(['href' => '/admin/mitglieder', 'label' => 'Mitglieder', 'kind' => 'content',
        'icon' => 'users', 'perm' => 'members.manage', 'feature' => 'members']);
    $x->adminPage(['href' => '/admin/mitglieder/einstellungen', 'label' => 'Mitglieder', 'kind' => 'settings',
        'icon' => 'users', 'perm' => 'members.settings', 'description' => 'Anmeldung, E-Mails, geschützte Dateien']);
    $x->adminPage(['href' => '/admin/videos/schnitt', 'label' => 'Videos schneiden', 'kind' => 'tool', 'icon' => 'film-slate',
        'visible' => fn() => \Core\Features::on('video')]);
}
PHP) ?>

<?= AdminUi::group('Checkliste für Erweiterungen', [
    AdminUi::row('1. Anmelden', '', 'Jede Seite mit adminPage() und passender Art – kein eigener Menü-Code, keine Links ins Layout.'),
    AdminUi::row('2. Ein Menüpunkt', '', 'Ein Punkt je Erweiterung im Hauptmenü; Unterseiten erscheinen in der Bereichsliste links (open()), nicht als weitere Menüpunkte.'),
    AdminUi::row('3. Einstellungen', '', 'Als settings anmelden; die Seite selbst ist ein Bereich der eigenen Bereichsliste oder steht allein mit group()/row().'),
    AdminUi::row('4. Recht', '', 'perm an jeder Anmeldung und jeder Route; ohne Recht erscheint der Punkt nicht.'),
    AdminUi::row('5. Kopf', '', 'head() mit Dachzeile „Erweiterung“ oder dem Namen des Bereichs und einer Zeile Beschreibung.'),
    AdminUi::row('6. Mobil', '', 'Nichts extra: Bereichsliste wird zum Auswahlfeld, Zeilen stapeln sich. Mit 390 px Breite prüfen.'),
]) ?>
<?= AdminUi::group('Weiter lesen', [
    AdminUi::link('Verwaltungsseiten: Art und Ort', url('/admin/hilfe/technik#erweiterungen-seiten'), 'Alle Felder von adminPage()'),
    AdminUi::link('Verwaltungsoberfläche', url('/admin/hilfe/technik#verwaltung'), 'Seitenleiste, Drill-down, Mobil, Favoriten'),
    AdminUi::link('Symbole', url('/admin/hilfe/symbole'), 'Namen für icon'),
]) ?>
<?php endif; ?>
<?= AdminUi::close() ?>
