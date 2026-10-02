<?php
/**
 * Handbuch für die Redaktion. Die Kern-Kapitel liegen in help/manual/{schlüssel}.php und werden in der Reihenfolge
 * von $core ausgegeben (Nummern und Inhaltsverzeichnis entstehen automatisch).
 *
 * Ein Theme ergänzt das Handbuch mit kits/{name}/docs/manual.php – die Datei gibt ein Array zurück:
 *   'hero'     => ['eyebrow' => …, 'title' => … (HTML), 'lead' => …]          Kopf des Handbuchs
 *   'chapters' => ['schlüssel' => [                                           Kapitel des Themes
 *                     'title' => …, 'file' => __DIR__ . '/manual/x.php',
 *                     'id'    => 'anker',                                    optional (Standard: Schlüssel)
 *                     'mode'  => 'replace' | 'before' | 'after',             bei Kern-Kapiteln: ersetzen (Standard)
 *                                                                            oder vor/nach dem Kern-Inhalt ergänzen
 *                     'after' => 'kern-schlüssel',                           neue Kapitel: Position (Standard: vor „faq“)
 *                  ] | false]                                                false = Kern-Kapitel entfernen
 *   'blocks'   => ['typ' => 'Beschreibung']                                   Texte für „Alle Blöcke“ (sonst 'help' des Blocks)
 *   'vars'     => [...]                                                      Werte für die Kapitel ($vars)
 *   'figures'  => false                                                      Bildschirmfotos des Kerns ausblenden
 * Kapitel-Dateien sehen $settingsTitle, $img, $blocks, $blockInfo, $vars, $anchor('schlüssel') (Anker eines Kapitels)
 * und $has('schlüssel'). Ältere Themes, deren docs/manual.php eine vollständige Ansicht ist, zeigt help/theme-manual.php.
 * Kapitel „projekt“ (Hinweise zu diesem Projekt, Core\Guide) erscheint nur, wenn Kit oder Website Hinweise mitbringen.
 * @var array $blocks  @var array $themeDoc
 */
$themeDoc ??= [];
$settingsTitle = app()->theme->settingsTitle();
$vars = (array) ($themeDoc['vars'] ?? []);
$figures = ($themeDoc['figures'] ?? true) !== false;
$img = fn(string $file, string $alt, string $caption) => $figures ? '<figure class="doc-fig"><img src="' . e(asset('docs/' . $file)) . '" alt="' . e($alt) . '" loading="lazy" width="1440" height="900"><figcaption>' . $caption . '</figcaption></figure>' : '';
$core = [
    'start' => 'Überblick',
    'uebersicht' => 'Die Übersicht (Startseite der Verwaltung)',
    'anmelden' => 'Anmelden & Konto',
    'bearbeiten' => 'Inhalte bearbeiten',
    'einstellungen' => $settingsTitle,
    'seiten' => 'Seiten verwalten',
    'entwuerfe' => 'Entwürfe prüfen und aufräumen',
    'medien' => 'Bilder & Dateien',
    'daten' => 'Eigene Daten (Aktuelles, Team …)',
    'stellen' => 'Stellenangebote & Google for Jobs',
    'quellen' => 'Externe Quellen: Feeds, APIs, OpenImmo',
    'anfragen' => 'Online-Anfragen',
    'smime' => 'S/MIME einrichten: verschlüsselte Anfragen per E-Mail',
    'bloecke' => 'Alle Blöcke',
    'baukasten' => 'Eigene Blöcke bauen (Administration)',
    'vorlagen' => 'Seitenvorlagen & Blöcke kopieren',
    'live' => 'Live-Galerie & Live-Ticker',
    'funktionen' => 'Funktionen & Erweiterungen (Haupt-Admin)',
    'aufgaben' => 'Häufige Aufgaben',
    'assistent' => \Core\AI\Assist::brand() . ': schreiben, übersetzen, prüfen',
    'ki-chat' => 'KI-Chats: Assistent & Besucher-Chat',
    'ki' => 'KI-Assistenten über MCP & Freigabe',
    'suche' => 'Website-Suche',
    'glossar' => 'Glossar: Fachbegriffe erklären',
    'landingpages' => 'Landingpages mit eigener Domain',
    'weiterleitungen' => 'Weiterleitungen & 404-Protokoll',
    'cookies' => 'Cookie-Einwilligung (falls eingeschaltet)',
    'regeln' => 'Regeln für gute Inhalte',
    'support' => 'Hilfe & Support',
    'chat' => 'Chat im Team',
    'faq' => 'Fragen & Probleme',
];
$chapters = [];
foreach ($core as $__key => $__title) {
    $chapters[$__key] = ['id' => $__key, 'title' => $__title, 'files' => [__DIR__ . '/manual/' . $__key . '.php']];
}
$chapters['faq']['class'] = 'doc-faq';
// Hinweise zu diesem Projekt (Core\Guide: kits/{kit}/guide/*.md, {storage}/guide/*.md) – nur wenn es welche gibt, gleich nach dem Überblick
$guide = \Core\Guide::exists();
if ($guide) {
    $chapters = array_slice($chapters, 0, 1, true) + ['projekt' => ['id' => 'projekt', 'title' => \Core\Guide::title(), 'files' => [__DIR__ . '/manual/projekt.php'], 'class' => 'doc-guide']]
        + array_slice($chapters, 1, null, true);
}
// Kapitel aktiver Erweiterungen (Extension::docs('manual', …), gleiches Format wie 'chapters') – vor denen des Themes
foreach ([...\Core\Extensions::docs('manual'), ...(array) ($themeDoc['chapters'] ?? [])] as $__key => $__spec) {
    if ($__spec === false) { unset($chapters[$__key]); continue; }
    $__file = (string) ($__spec['file'] ?? '');
    if (isset($chapters[$__key])) {
        $__mode = $__spec['mode'] ?? 'replace';
        $__files = $chapters[$__key]['files'];
        $chapters[$__key]['files'] = match ($__mode) { 'before' => [$__file, ...$__files], 'after' => [...$__files, $__file], default => [$__file] };
        $chapters[$__key]['title'] = $__spec['title'] ?? $chapters[$__key]['title'];
        $chapters[$__key]['id'] = $__spec['id'] ?? $chapters[$__key]['id'];
        continue;
    }
    $__new = ['id' => $__spec['id'] ?? $__key, 'title' => (string) ($__spec['title'] ?? $__key), 'files' => [$__file], 'class' => $__spec['class'] ?? null];
    $__pos = array_search($__spec['after'] ?? '', array_keys($chapters), true);
    $__pos = $__pos === false ? (array_search('faq', array_keys($chapters), true) ?: count($chapters)) : $__pos + 1;
    $chapters = array_slice($chapters, 0, $__pos, true) + [$__key => $__new] + array_slice($chapters, $__pos, null, true);
}
$anchor = fn(string $key): string => $chapters[$key]['id'] ?? $key;
$has = fn(string $key): bool => isset($chapters[$key]);
$blockInfo = array_map(fn($b) => (string) ($b['help'] ?? ''), $blocks);
foreach ((array) ($themeDoc['blocks'] ?? []) as $__type => $__text) {
    $blockInfo[$__type] = (string) $__text;
}
$hero = (array) ($themeDoc['hero'] ?? []);
// Tutorials (eigener Reiter unter „Handbuch & Hilfe“; Videos auf der Produkt-Website, config docs_url) – verlinkt, sobald die Route existiert
$__router = new \Core\Http\Router();
(require ROOT . '/app/routes.php')($__router);
$tutorials = $__router->match('GET', '/admin/hilfe/tutorials') === '/admin/hilfe/tutorials' ? url('/admin/hilfe/tutorials') : null;
?>
<?php $helpTab = 'manual'; include ROOT . '/app/Admin/views/support/_helptabs.php'; // Reiter: Handbuch · Wissensdatenbank · Fragen & Antworten · Symbole · Technik ?>
<div class="doc">
  <header class="doc-hero">
    <span class="doc-hero__eyebrow"><?= e(CMS_NAME) ?> · <?= e($hero['eyebrow'] ?? 'Handbuch für die Redaktion') ?></span>
    <h1><?= $hero['title'] ?? 'Die Website pflegen<i>.</i><br>Einfach und sicher.' ?></h1>
    <p><?= e($hero['lead'] ?? 'Alles, was Sie für die tägliche Arbeit brauchen: Texte ändern, Bilder hochladen, Seiten und Einträge pflegen und Online-Anfragen lesen – Schritt für Schritt erklärt.') ?></p>
    <div class="doc-hero__links">
      <?php if ($guide): ?><a href="#projekt"><?= e(\Core\Guide::title()) ?> →</a><?php endif; ?>
      <a href="#<?= e($anchor('aufgaben')) ?>"<?= $guide ? ' class="ghost"' : '' ?>>Häufige Aufgaben<?= $guide ? '' : ' →' ?></a>
      <?php if ($tutorials): ?><a href="<?= e($tutorials) ?>" class="ghost">Tutorials</a><?php endif; ?>
      <a href="<?= e(url('/')) ?>?edit=1" class="ghost">Startseite bearbeiten</a>
      <a href="<?= e(url('/admin/hilfe/technik')) ?>" class="ghost">Technische Dokumentation</a>
    </div>
  </header>

  <div class="doc-layout">
    <div>

<?php $__n = 0; foreach ($chapters as $__key => $__ch): $__n++; ?>
<section class="doc-ch<?= $__ch['class'] ?? '' ? ' ' . e($__ch['class']) : '' ?>" id="<?= e($__ch['id']) ?>">
  <h2><span class="no"><?= sprintf('%02d', $__n) ?></span><?= e($__ch['title']) ?><span class="dot">.</span></h2>
<?php foreach ($__ch['files'] as $__file) { include $__file; } ?>
</section>

<?php endforeach; ?>
      <p class="doc-foot">Handbuch · Stand <?= e(date('d.m.Y')) ?> · <?= e(CMS_NAME) ?> <?= e(CMS_VERSION) ?> · Kit „<?= e(app()->theme->label()) ?>“<?php if (!$guide && \Core\Guide::canEdit()): ?> · <a href="<?= e(url('/admin/hilfe/projekt')) ?>">Projekt-Hinweise anlegen</a><?php endif; ?></p>
    </div>

    <nav class="doc-toc" aria-label="Inhalt" data-doc-toc>
      <p>Inhalt</p>
      <ol>
        <?php foreach ($chapters as $__ch): ?><li><a href="#<?= e($__ch['id']) ?>"><?= e($__ch['title']) ?></a></li><?php endforeach; ?>
      </ol>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost doc-print" data-print>Als PDF drucken</button>
    </nav>
  </div>
</div>
