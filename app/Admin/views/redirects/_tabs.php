<?php
/**
 * Administration → Weiterleitungen: Bereichsliste links (Core\AdminUi) – Liste, 404-Protokoll, Adresse testen, Import & Export,
 * Einstellungen. Der Rahmen wird am Ende der Ansicht mit AdminUi::close() geschlossen.
 * @var string $tab  list|404|test|import|einstellungen  @var int $nRules  @var int $n404
 */
$b = '/admin/weiterleitungen';
echo \Core\AdminUi::open([
    'list' => ['href' => url($b), 'label' => __('Weiterleitungen'), 'icon' => 'signpost', 'badge' => (int) $nRules],
    '404' => ['href' => url($b . '/404'), 'label' => __('Nicht gefunden (404)'), 'icon' => 'warning', 'badge' => (int) $n404],
    '-',
    'test' => ['href' => url($b . '?bereich=test'), 'label' => __('Adresse testen'), 'icon' => 'magnifying-glass'],
    'import' => ['href' => url($b . '?bereich=import'), 'label' => __('Import & Export'), 'icon' => 'download-simple'],
    'einstellungen' => ['href' => url($b . '?bereich=einstellungen'), 'label' => __('Einstellungen'), 'icon' => 'gear-six'],
], $tab, __('Weiterleitungen'), false);
