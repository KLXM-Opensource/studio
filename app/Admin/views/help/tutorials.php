<?php
/**
 * Handbuch & Hilfe › Tutorials: Übersicht der drei Zielgruppen (HelpController::tutorials, Inhalte app/Admin/tutorials.php, Core\Tutorials).
 * Alle Zielgruppen sind sichtbar; die zur Rolle passende ist als „Für Sie empfohlen“ markiert und steht zuerst.
 * Videos und Trailer liegen auf der Produkt-Website (config 'docs_url') – hier nur Links (neuer Tab), keine Einbettung, keine
 * Anfrage nach außen. Jede Karte führt zur Textfassung (Schritte, Tipps – auch offline) und zum Video.
 * @var array $tracks  @var array $tutorials  @var string $recommended  @var string $docsHost  @var ?string $overviewUrl  @var ?string $trailerUrl
 */
$helpTab = 'tutorials';
include ROOT . '/app/Admin/views/support/_helptabs.php';
$fmt = fn(int $s) => $s ? sprintf('%d:%02d', intdiv($s, 60), $s % 60) : '';
$order = [$recommended => $tracks[$recommended]] + $tracks;
$web = count(array_filter($tutorials, fn($t) => $t['web']));
$ext = fn(string $href, string $label, string $class = '') => '<a class="' . e($class) . '" href="' . e($href) . '" target="_blank" rel="noopener">' . e($label)
    . ' <span aria-hidden="true">↗</span><span class="sr-only"> (' . e(__('öffnet {host} in einem neuen Tab', ['host' => $docsHost])) . ')</span></a>';
?>
<div class="doc tut">
  <header class="doc-hero">
    <span class="doc-hero__eyebrow"><?= e(CMS_NAME) ?> · <?= e(__('Tutorials')) ?></span>
    <h1><?= e(__('Schritt für Schritt')) ?><i>.</i><br><?= e($web ? __('Mit kurzen Videos.') : __('Zum Nachlesen.')) ?></h1>
    <p><?= e(__('Die wichtigsten Aufgaben für die Redaktion, die Administration einer Website und für Agenturen. Jedes Tutorial nennt Ziel, Voraussetzungen und alle Schritte mit den genauen Bezeichnungen.')) ?></p>
    <?php if ($web): ?>
    <p class="tut-hero__note"><?= icon('video-camera') ?> <span><?= e(__('Die Videos (ohne Ton, mit Untertiteln Deutsch/Englisch) stehen auf {host} – hier finden Sie dieselben Schritte als Text, auch ohne Internet.', ['host' => $docsHost])) ?></span></p>
    <?php endif; ?>
    <nav class="doc-hero__links" aria-label="<?= e(__('Zielgruppen')) ?>">
      <?php foreach ($order as $key => $tr): ?>
      <a href="#<?= e($key) ?>"<?= $key === $recommended ? '' : ' class="ghost"' ?>><?= e($tr['title']) ?></a>
      <?php endforeach; ?>
      <?php if ($overviewUrl): ?><?= $ext($overviewUrl, __('Alle Videos auf {host}', ['host' => $docsHost]), 'ghost') ?><?php endif; ?>
    </nav>
  </header>

  <?php if ($trailerUrl): ?>
  <section class="tut-trailer" id="tut-trailer" aria-labelledby="tut-trailer-h">
    <span class="tut-track__icon" aria-hidden="true"><?= icon('play-circle') ?></span>
    <div class="tut-trailer__text">
      <h2 id="tut-trailer-h"><?= e(__('KLXM Studio im Überblick')) ?></h2>
      <p><?= e(__('Der Trailer zeigt Kits, Bearbeiten und Verwalten in rund zwei Minuten – ohne Ton, mit Untertiteln (Deutsch, Englisch, Slowenisch).')) ?></p>
    </div>
    <?= $ext($trailerUrl, __('KLXM Studio im Überblick'), 'adm-btn adm-btn--small tut-trailer__link') ?>
  </section>
  <?php endif; ?>

  <?php foreach ($order as $key => $tr): $list = array_filter($tutorials, fn($t) => $t['track'] === $key); if (!$list) continue; ?>
  <section class="doc-ch tut-track" id="<?= e($key) ?>" aria-labelledby="<?= e($key) ?>-h">
    <header class="tut-track__head">
      <span class="tut-track__icon"><?= icon($tr['icon'] ?? 'play-circle') ?></span>
      <div>
        <h2 id="<?= e($key) ?>-h"><?= e($tr['title']) ?><?php if ($key === $recommended): ?> <span class="tut-badge tut-badge--rec"><?= e(__('Für Sie empfohlen')) ?></span><?php endif; ?></h2>
        <p class="lead"><?= e($tr['lead'] ?? '') ?></p>
      </div>
    </header>
    <ul class="tut-grid">
      <?php foreach ($list as $slug => $t): ?>
      <li>
        <article class="tut-card">
          <span class="tut-card__icon"><?= icon($t['icon'] ?? 'play-circle') ?></span>
          <h3 class="tut-card__title"><a class="tut-card__link" href="<?= e(url('/admin/hilfe/tutorials/' . $slug)) ?>"><?= e($t['title']) ?></a></h3>
          <p class="tut-card__sub"><?= e($t['summary'] ?? '') ?></p>
          <p class="tut-card__meta">
            <span><?= icon('signpost') ?> <?= e($t['levelLabel']) ?></span>
            <span><?= icon('list-checks') ?> <?= e(__('{n} Schritte', ['n' => count((array) ($t['steps'] ?? []))])) ?></span>
            <?php if ($t['media'] && $t['media']['duration']): ?><span><?= icon('play-circle') ?> <?= e($fmt($t['media']['duration'])) ?> <?= e(__('Min.')) ?></span><?php endif; ?>
            <?php if ($t['off']): ?><span class="tut-badge tut-badge--off"><?= e(__('auf dieser Website aus')) ?></span><?php endif; ?>
          </p>
          <?php if ($t['web']): ?><p class="tut-card__ext"><?= $ext($t['web'], __('Video ansehen'), 'tut-ext') ?> <span class="tut-card__host">(<?= e($docsHost) ?>)</span></p><?php endif; ?>
        </article>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endforeach; ?>

  <p class="doc-foot"><?php if ($web): ?><?= e(__('Die Videos werden nicht mit dem CMS ausgeliefert – so bleibt die Installation klein. Die Verwaltung lädt nichts von {host}; die Links öffnen einen neuen Tab.', ['host' => $docsHost])) ?><br><?php endif; ?>
    <?= e(__('Eigene Tutorials für ein Kit:')) ?> <code>kits/{name}/docs/tutorials.php</code> · <a href="<?= e(url('/admin/hilfe/technik#tutorials')) ?>"><?= e(__('Technische Dokumentation')) ?></a></p>
</div>
