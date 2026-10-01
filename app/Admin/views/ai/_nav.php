<?php
/**
 * Bereichsnavigation „KLXM AI“ (Marke: config 'ai_brand'): Übersicht, Texte, Übersetzen, SEO, Alt-Texte, Verlauf, Einstellungen.
 * Das Layout zeigt sie links anstelle der Hauptnavigation (resources/js/_drill.js).
 * @var string $cur
 */
use Core\AI\Assist;

$ac = fn(bool $on) => $on ? ' aria-current="page"' : '';
$items = array_values(array_filter([
    ['/admin/ai', 'sparkle', __('Übersicht'), 'index', true],
    // Assistent-Chat (Core\AI\Assistant): Fragen zur Bedienung, Aktionen mit Bestätigung, gespeicherte Unterhaltungen
    ['/admin/ai/assistent', 'chat-teardrop-dots', __('Assistent'), 'assistant', \Core\Features::on('chat.assistant', false)],
    ['/admin/ai/texte', 'pencil-simple', __('Texte'), 'write', Assist::available('text')],
    ['/admin/ai/uebersetzen', 'translate', __('Übersetzen'), 'translate', Assist::available('text') && \Core\Lang::multi()],
    ['/admin/ai/seo', 'magnifying-glass', __('SEO'), 'seo', can('pages.manage')],
    ['/admin/ai/alt-texte', 'image', __('Alt-Texte'), 'alt', can('media.upload')],
    ['/admin/ai/untertitel', 'video-camera', __('Untertitel'), 'captions', can('media.upload') && \Core\MediaTracks::enabled()],
    ['/admin/ai/seiten', 'file-text', __('Seiten-Generator'), 'pages', Assist::available('text') && can('pages.manage')],
    ['/admin/ai/tabellen', 'table', __('Tabellen-Generator'), 'tables', Assist::available('text') && can('data.schema') && \Core\Features::on('data', false)],
    // Prüf-Ebene „Eingereicht“: Änderungen über API, MCP und KI (Core\Review\Queue, Recht review.manage)
    ...(class_exists('Core\\Review\\Queue') ? [['/admin/ai/eingereicht', 'clipboard-text', __('Eingereicht'), 'review', \Core\Review\Queue::canReview()]] : []),
    ['/admin/ai/verlauf', 'arrow-counter-clockwise', __('Verlauf'), 'history', true],
    ['/admin/ai/einstellungen', 'gear-six', __('Einstellungen'), 'settings', true],
], fn($i) => $i[4]));
?>
<nav class="dt-nav kia-nav" data-drill-panel aria-label="<?= e(Assist::brand()) ?>">
  <ul class="dt-nav__list">
    <?php foreach ($items as [$href, $ico, $label, $key]): ?>
    <li><a class="dt-nav__item" href="<?= e(url($href)) ?>"<?= $ac($cur === $key) ?>><span class="dt-nav__ico" aria-hidden="true"><?= icon($ico) ?></span><span class="dt-nav__name"><?= e($label) ?></span><?php if ($key === 'review' && ($rn = \Core\Review\Queue::pendingCount())): ?> <span class="adm-count"><?= $rn ?><span class="sr-only"> <?= e(__('offen')) ?></span></span><?php endif; ?></a></li>
    <?php endforeach; ?>
  </ul>
</nav>
