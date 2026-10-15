<?php
/** Kopf und Bereichsnavigation der Erweiterung „consent_kit“. @var string $tab */
$items = [
    'services' => ['/admin/consent', __('Dienste')],
    'settings' => ['/admin/consent/settings', __('Einstellungen')],
    'design' => ['/admin/consent/design', __('Design')],
    'log' => ['/admin/consent/log', __('Protokoll')],
    'io' => ['/admin/consent/io', __('Eigene Vorlagen')],
];
$current = in_array($tab, ['service', 'templates'], true) ? 'services' : ($tab === 'revision' ? 'log' : $tab);
?>
<header class="adm-head ck-head">
  <div><p class="adm-eyebrow"><?= e(__('Erweiterung')) ?> · Consent-Kit</p><h1><?= e(__('Cookie-Einwilligung')) ?></h1>
    <p class="adm-muted"><?= e(__('Dienste, die eine Einwilligung brauchen, Banner-Texte und -Design, Nachweise.')) ?></p></div>
  <div class="adm-head__actions"><a class="adm-btn adm-btn--ghost adm-btn--small" href="<?= e(url('/admin/hilfe/technik#consent')) ?>"><?= icon('question') ?> <?= e(__('Technik-Hilfe')) ?></a></div>
</header>
<?php
// Bereichsliste links wie Grundeinstellungen (Core\AdminUi); geschlossen im AdminController::page
$icons = ['services' => 'plugs-connected', 'settings' => 'gear-six', 'design' => 'palette', 'log' => 'clock-counter-clockwise', 'io' => 'stamp'];
$navItems = [];
foreach ($items as $k => [$href, $label]) $navItems[$k] = ['href' => url($href), 'label' => $label, 'icon' => $icons[$k] ?? 'circle'];
echo \Core\AdminUi::open($navItems, $current, __('Cookie-Einwilligung'), false);
?>
