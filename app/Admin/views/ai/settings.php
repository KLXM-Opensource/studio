<?php
/** Bereich „KLXM AI“ → Einstellungen: Zusammenfassung; ändern in Grundeinstellungen → KI (Administration). */
use Core\AI\Ai;
use Core\AI\Assist;

$cfg = Ai::config();
$site = Ai::siteSettings();
$q = Assist::quota();
$gl = Assist::glossary();
$notes = trim((string) app()->settings->get('sys.ai_notes', ''));
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/ai')) ?>"><?= e(Assist::brand()) ?></a></p><h1><?= e(__('Einstellungen')) ?></h1>
    <p class="adm-muted"><?= e(__('So ist der KI-Assistent für diese Website eingerichtet.')) ?></p></div>
  <?php if (can('system.manage')): ?><a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/system#ki')) ?>"><?= e(__('Ändern in Grundeinstellungen → KI')) ?></a><?php endif; ?>
</header>
<section class="adm-card">
  <dl class="adm-dl kia-dl">
    <dt><?= e(__('KI auf dieser Website')) ?></dt><dd><?= e($site['enabled'] ? __('eingeschaltet') : __('ausgeschaltet')) ?></dd>
    <dt><?= e(__('Anbieter')) ?></dt><dd><?= e($cfg['configured'] ? __(Ai::PROVIDERS[$cfg['provider']] ?? $cfg['provider']) : __('nicht eingerichtet')) ?></dd>
    <?php foreach (['text' => __('Texte'), 'vision' => __('Bilder')] as $c => $l): $cap = Ai::capability($c); ?>
    <dt><?= e($l) ?></dt><dd><?= e(Ai::enabled($c) ? __('aktiv') . ' · ' . $cap['model'] . ' · ' . ($cap['external'] ? __('externer Anbieter') : __('bleibt auf dem Server')) : __('aus')) ?></dd>
    <?php endforeach; ?>
    <dt><?= e(__('Tageslimit')) ?></dt><dd><?= e($q['cap'] > 0 ? __('{cap} Aufrufe (heute {used} genutzt)', $q) : __('unbegrenzt (heute {used} Aufrufe)', $q)) ?></dd>
    <dt><?= e(__('Hinweise für die KI')) ?></dt><dd><?= $notes !== '' ? e($notes) : '<span class="adm-muted">' . e(__('keine')) . '</span>' ?></dd>
    <dt><?= e(__('Glossar')) ?></dt><dd><?= $gl ? e(implode(', ', array_map(fn($k, $v) => $v !== '' ? "$k = $v" : $k, array_keys($gl), $gl))) : '<span class="adm-muted">' . e(__('keine Einträge')) . '</span>' ?></dd>
    <dt><?= e(__('Name des Bereichs')) ?></dt><dd><?= e(Assist::brand()) ?> <small class="adm-muted">(<?= e(__('Konfiguration „ai_brand“')) ?>)</small></dd>
  </dl>
  <?php if (!can('system.manage')): ?><p class="adm-muted"><?= e(__('Änderungen nimmt die Administration vor.')) ?></p><?php endif; ?>
  <?php if (\Core\Features::integrator()): ?><p class="adm-muted kia-small"><?= e(__('Für Agenturen: Alle Anweisungen an die KI stehen in app/AI/Prompts.php.')) ?></p><?php endif; ?>
</section>
