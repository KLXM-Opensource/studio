<?php
/**
 * Block „Externer Inhalt (mit Einwilligung)“ (Erweiterung consent_kit): iframe eines Anbieters als 2-Klick-Platzhalter.
 * Geladen wird erst nach Einwilligung in den Dienst oder nach „Inhalt einmal laden“; der Host der Adresse muss beim Dienst
 * unter „Domains eingebetteter Inhalte“ stehen (die CSP erlaubt genau diese iframe-Hosts).
 * @var \Core\Block $b  @var array $d
 */
use MyCms\Consent\Compiler;
use MyCms\Consent\Consent;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$svc = (string) ($d['service'] ?? '');
$url = trim((string) ($d['url'] ?? ''));
$label = trim((string) ($d['label'] ?? '')) ?: trim((string) ($d['title'] ?? ''));
$c = Consent::enabled() ? (Compiler::build()['services'][$svc] ?? null) : null;
$ok = $c && $url !== '' && Consent::urlFits($url, $c['embed']);
?>
<div class="<?= e($wrap) ?>">
  <?php if (trim((string) ($d['title'] ?? '')) !== '' || is_editing()): ?><h2<?= $b->edit('title') ?>><?= e((string) ($d['title'] ?? '')) ?></h2><?php endif; ?>
  <figure class="consent-embed">
    <?php if ($ok): ?>
    <?= Consent::embed($svc, '<iframe src="' . e($url) . '" title="' . e($label) . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="fullscreen; clipboard-write; encrypted-media; picture-in-picture" width="100%" height="100%"></iframe>',
        ['title' => $label, 'ratio' => (string) ($d['ratio'] ?? '16/9'), 'link' => $url]) ?>
    <?php elseif (is_editing()): ?>
    <p><?= e($c ? 'Die Adresse passt nicht zu den „Domains eingebetteter Inhalte“ des Dienstes (oder beginnt nicht mit https://).' : 'Dienst wählen (Verwaltung → Cookie-Einwilligung) und die Embed-Adresse eintragen.') ?></p>
    <?php elseif ($url !== '' && ($safe = \Core\Sanitizer::safeHref($url))): ?>
    <p><a href="<?= e($safe) ?>" target="_blank" rel="noopener"><?= e($label ?: $url) ?><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a></p>
    <?php endif; ?>
    <?php if (trim((string) ($d['caption'] ?? '')) !== '' || is_editing()): ?><figcaption<?= $b->edit('caption') ?>><?= e((string) ($d['caption'] ?? '')) ?></figcaption><?php endif; ?>
  </figure>
</div>
