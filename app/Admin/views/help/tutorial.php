<?php
/**
 * Ein Tutorial: Ziel, Voraussetzungen, Link zum Video auf der Produkt-Website, Schritte (= Transkript), Tipps, Handbuch-Links, „Weiter zu …“.
 * HelpController::tutorial – Inhalte aus app/Admin/tutorials.php (Englisch: tutorials.en.php), Core\Tutorials. Keine Inline-Skripte/-Stile (CSP).
 * Kern-Videos liegen auf der Produkt-Website (config 'docs_url') – nur ein Link (neuer Tab), keine Einbettung, keine Anfrage nach außen.
 * Kits können eigene Videos lokal mitbringen ('video' => '/themes/{name}/tutorials/datei') – die werden hier eingebettet.
 * @var string $slug  @var array $tut  @var array $track  @var array $tutorials  @var array $tracks  @var ?string $prev  @var ?string $next  @var string $recommended  @var string $docsHost
 */
$helpTab = 'tutorials';
include ROOT . '/app/Admin/views/support/_helptabs.php';
$fmt = fn(int $s) => $s ? sprintf('%d:%02d', intdiv($s, 60), $s % 60) : '';
$en = \Core\Tutorials::en();
/** Lokales Kit-Video: <video> (MP4/WebM) mit Vorschaubild und Untertiteln; Verwaltung auf Englisch = englische Untertitel voreingestellt. */
$video = function (array $m, string $label, string $class = '') use ($en): string {
    $h = '<video class="tut-video ' . e($class) . '" controls preload="none" playsinline width="' . (int) $m['width'] . '" height="' . (int) $m['height'] . '"'
        . ($m['poster'] ? ' poster="' . e($m['poster']) . '"' : '') . ' aria-label="' . e($label) . '">';
    if ($m['webm']) $h .= '<source src="' . e($m['webm']) . '" type="video/webm">';
    if ($m['mp4']) $h .= '<source src="' . e($m['mp4']) . '" type="video/mp4">';
    if ($m['de']) $h .= '<track kind="subtitles" srclang="de" label="Deutsch" src="' . e($m['de']) . '"' . ($en && $m['en'] ? '' : ' default') . '>';
    if ($m['en']) $h .= '<track kind="subtitles" srclang="en" label="English" src="' . e($m['en']) . '"' . ($en ? ' default' : '') . '>';
    return $h . '<a href="' . e($m['mp4'] ?: $m['webm']) . '">' . e(__('Video herunterladen')) . '</a></video>';
};
$link = fn(?string $s) => $s ? '<a href="' . e(url('/admin/hilfe/tutorials/' . $s)) . '">' . e($tutorials[$s]['title']) . '</a>' : '';
?>
<div class="doc tut">
  <p class="tut-crumb"><a href="<?= e(url('/admin/hilfe/tutorials')) ?>">← <?= e(__('Tutorials')) ?></a> · <a href="<?= e(url('/admin/hilfe/tutorials#' . $tut['track'])) ?>"><?= e($track['title'] ?? '') ?></a></p>

  <header class="tut-head">
    <span class="tut-card__icon tut-head__icon"><?= icon($tut['icon'] ?? 'play-circle') ?></span>
    <div>
      <h1><?= e($tut['title']) ?></h1>
      <p class="tut-head__meta">
        <span><?= icon($track['icon'] ?? 'users') ?> <?= e($track['for'] ?? '') ?></span>
        <span><?= icon('signpost') ?> <?= e($tut['levelLabel']) ?></span>
        <?php if ($tut['media'] && $tut['media']['duration']): ?><span><?= icon('clock') ?> <?= e(__('Video {d} Min.', ['d' => $fmt($tut['media']['duration'])])) ?></span><?php endif; ?>
        <?php if ($tut['track'] === $recommended): ?><span class="tut-badge tut-badge--rec"><?= e(__('Für Sie empfohlen')) ?></span><?php endif; ?>
      </p>
    </div>
  </header>

  <?php if ($tut['off']): ?>
  <div class="doc-note doc-note--warn"><strong><?= e(__('Auf dieser Website ausgeschaltet')) ?></strong><p><?= e(__('Diese Funktion gehört nicht zum Funktionsumfang dieser Website. Das Tutorial zeigt sie trotzdem – fragen Sie bei Bedarf Ihre Agentur.')) ?></p></div>
  <?php endif; ?>

  <div class="tut-layout">
    <div class="tut-main">
      <?php if ($tut['media']): ?>
      <figure class="tut-fig">
        <?= $video($tut['media'], __('Video: {title}', ['title' => $tut['title']])) ?>
        <figcaption><?= icon('info') ?> <?= e(__('Video ohne Ton, mit Untertiteln (Deutsch, Englisch). Untertitel im Player über „CC“ ein- und ausschalten. Die Schritte unten sind der vollständige Text zum Video.')) ?></figcaption>
      </figure>
      <?php elseif ($tut['web']): ?>
      <aside class="tut-web" aria-labelledby="tut-web-h">
        <span class="tut-web__icon" aria-hidden="true"><?= icon('play-circle') ?></span>
        <div>
          <h2 id="tut-web-h" class="tut-web__h"><?= e(__('Das Video zu diesem Tutorial')) ?></h2>
          <p><?= e(__('Ohne Ton, mit Untertiteln (Deutsch, Englisch) – auf {host}. Die Schritte unten sind der vollständige Text zum Video.', ['host' => $docsHost])) ?></p>
        </div>
        <a class="adm-btn adm-btn--small adm-btn--primary tut-web__btn" href="<?= e($tut['web']) ?>" target="_blank" rel="noopener"><?= e(__('Video ansehen')) ?> <span aria-hidden="true">↗</span><span class="sr-only"> (<?= e(__('öffnet {host} in einem neuen Tab', ['host' => $docsHost])) ?>)</span></a>
      </aside>
      <?php endif; ?>

      <section class="doc-ch" aria-labelledby="tut-goal">
        <h2 id="tut-goal"><?= e(__('Ziel dieses Tutorials')) ?></h2>
        <p class="lead"><?= e($tut['goal'] ?? '') ?></p>
        <?php if (!empty($tut['prerequisites'])): ?>
        <h3><?= e(__('Voraussetzungen')) ?></h3>
        <ul><?php foreach ($tut['prerequisites'] as $p): ?><li><?= $p /* vertrauenswürdiger Inhalt aus tutorials.php */ ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </section>

      <section class="doc-ch" aria-labelledby="tut-steps">
        <h2 id="tut-steps"><?= e(__('Schritt für Schritt')) ?></h2>
        <?php if ($tut['media'] || $tut['web']): ?><p class="tut-muted"><?= e(__('Text zum Video – jeder Schritt entspricht einem Untertitel.')) ?></p><?php endif; ?>
        <ol class="doc-steps"><?php foreach ($tut['steps'] as $s): ?><li><?= $s ?></li><?php endforeach; ?></ol>
        <?php if (!empty($tut['commands'])): ?>
        <h3><?= e(__('Befehle & Konfiguration')) ?></h3>
        <pre class="tut-code"><code><?= e($tut['commands']) ?></code></pre>
        <?php endif; ?>
      </section>

      <?php if ($tut['mobileMedia']): ?>
      <section class="doc-ch tut-mobile" aria-labelledby="tut-mobile">
        <h2 id="tut-mobile"><?= e(__('Auf dem Smartphone')) ?></h2>
        <div class="tut-mobile__grid">
          <?= $video($tut['mobileMedia'], __('Video: {title} auf dem Smartphone', ['title' => $tut['title']]), 'tut-video--phone') ?>
          <p><?= !empty($tut['mobileText']) ? $tut['mobileText'] : e(__('Die Verwaltung passt sich an kleine Bildschirme an: Das Menü öffnet sich über ☰ oben links, die Suche über die Lupe.')) ?></p>
        </div>
      </section>
      <?php endif; ?>

      <?php if (!empty($tut['tips']) || !empty($tut['pitfalls'])): ?>
      <section class="doc-ch" aria-labelledby="tut-tips">
        <h2 id="tut-tips"><?= e(__('Tipps & Stolperfallen')) ?></h2>
        <?php foreach ((array) ($tut['tips'] ?? []) as $x): ?><div class="doc-note doc-note--tip"><strong><?= e(__('Tipp')) ?></strong><p><?= $x ?></p></div><?php endforeach; ?>
        <?php foreach ((array) ($tut['pitfalls'] ?? []) as $x): ?><div class="doc-note doc-note--warn"><strong><?= e(__('Achtung')) ?></strong><p><?= $x ?></p></div><?php endforeach; ?>
      </section>
      <?php endif; ?>
    </div>

    <aside class="tut-side" aria-label="<?= e(__('Mehr zum Thema')) ?>">
      <?php if (!empty($tut['manual'])): ?>
      <div class="doc-toc">
        <p><?= e(__('Im Handbuch')) ?></p>
        <ul class="tut-links"><?php foreach ($tut['manual'] as [$label, $href]): ?><li><a href="<?= e(url($href)) ?>"><?= e($label) ?></a></li><?php endforeach; ?></ul>
      </div>
      <?php endif; ?>
      <div class="doc-toc">
        <p><?= e($track['title'] ?? __('Tutorials')) ?></p>
        <ol class="tut-links tut-links--track">
          <?php foreach ($tutorials as $s => $t): if ($t['track'] !== $tut['track']) continue; ?>
          <li><a href="<?= e(url('/admin/hilfe/tutorials/' . $s)) ?>"<?= $s === $slug ? ' aria-current="page"' : '' ?>><?= e($t['title']) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </div>
    </aside>
  </div>

  <nav class="tut-next" aria-label="<?= e(__('Weitere Tutorials')) ?>">
    <?php if ($prev): ?><p class="tut-next__prev"><span><?= e(__('Zurück zu')) ?></span> <?= $link($prev) ?></p><?php endif; ?>
    <?php if ($next): ?><p class="tut-next__next"><span><?= e(__('Weiter zu')) ?></span> <?= $link($next) ?></p><?php else: ?><p class="tut-next__next"><span><?= e(__('Geschafft')) ?></span> <a href="<?= e(url('/admin/hilfe/tutorials')) ?>"><?= e(__('Alle Tutorials')) ?></a></p><?php endif; ?>
  </nav>
</div>
