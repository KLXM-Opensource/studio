<?php
/** Kopf und Bereichsnavigation der Erweiterung „kalkulation“. @var string $tab  @var ?string $title */
$items = [
    'list' => ['/admin/kalkulation', __('Kalkulationen')],
    'catalog' => ['/admin/kalkulation/katalog', __('Leistungskatalog')],
    'settings' => ['/admin/kalkulation/einstellungen', __('Einstellungen')],
];
$current = match ($tab) { 'editor' => 'list', 'service' => 'catalog', default => $tab };
$heads = [
    'list' => [__('Kalkulationen'), __('Preise intern kalkulieren und als Angebot ausgeben. Nur für Berechtigte sichtbar – nichts davon erscheint auf der Website.')],
    'catalog' => [__('Leistungskatalog'), __('Leistungen und Pakete als Vorlage für Positionen. Beträge sind hier Vorgaben; in der Kalkulation lässt sich alles anpassen.')],
    'settings' => [__('Einstellungen'), __('Stundensätze, Aufschläge, Umsatzsteuer, Rundung und Standardtexte für Angebote.')],
    'service' => [(string) ($title ?? ''), __('Leistung mit ihren Paketen. Beträge sind Vorgaben für neue Positionen.')],
];
?>
<?php if (isset($heads[$tab])): ?>
<header class="adm-head kx-head">
  <div><p class="adm-eyebrow"><?= e(__('Intern')) ?> · <?= e(__('Kalkulation')) ?></p><h1><?= e($heads[$tab][0]) ?></h1>
    <p class="adm-muted"><?= e($heads[$tab][1]) ?></p></div>
  <?php if ($tab === 'list'): ?>
  <form method="post" action="<?= e(url('/admin/kalkulation/neu')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--primary" type="submit"><?= icon('plus') ?><?= e(__('Neue Kalkulation')) ?></button></form>
  <?php endif; ?>
</header>
<?php endif; ?>
<?php if ($tab === 'editor') return;   // Editor: eigener Kopf mit Rückweg ?>
<nav class="adm-filter kx-tabs" aria-label="<?= e(__('Kalkulation')) ?>">
  <?php foreach ($items as $k => [$href, $label]): ?><a href="<?= e(url($href)) ?>"<?= $k === $current ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
  <a class="kx-tabs__help" href="<?= e(url('/admin/hilfe#kalkulation')) ?>"><?= icon('question') ?><?= e(__('Hilfe')) ?></a>
</nav>
