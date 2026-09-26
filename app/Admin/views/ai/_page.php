<?php
/**
 * Seiten-Einstellungen: Karte „SEO & KI-Assistent“ (Core\AI). SEO-Check ohne KI für alle mit „pages.manage“;
 * Vorschläge (Titel, Beschreibung, Adresse) und Übersetzen nur mit KI + Recht „ai.use“. Oberfläche: resources/js/_ai.js
 * @var array $page
 */
use Core\AI\Assist;
use Core\AI\SeoCheck;
use Core\Lang;
use Core\Pages;

if (!can('pages.manage')) return;
$ai = Assist::available('text');
$lang = Lang::norm($page['lang'] ?? null);
$src = null;
if (Lang::multi() && $lang !== Lang::default()) $src = Pages::translations($page)[Lang::default()] ?? null;
$cfg = [
    'id' => (int) $page['id'], 'ai' => $ai, 'lang' => $lang, 'home' => (bool) $page['is_home'],
    'published' => $page['content_published'] !== null,
    'titleMax' => SeoCheck::TITLE_MAX - mb_strlen(Assist::titleSuffix()), 'descMax' => 155,
    'translate' => $src && $ai ? ['from' => Lang::default(), 'fromLabel' => Lang::all()[Lang::default()] ?? Lang::default(), 'toLabel' => Lang::all()[$lang] ?? $lang] : null,
];
?>
<section class="adm-card kia-card" data-kia-page="<?= e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) ?>" aria-labelledby="kia-page-h">
  <h2 id="kia-page-h"><?= e($ai ? __('SEO & KI-Assistent') : __('SEO-Check')) ?></h2>
  <p class="adm-muted"><?= e($ai ? __('Prüft die Seite und schlägt Titel, Beschreibung und Adresse vor. Vorschläge landen erst im Formular, wenn Sie sie übernehmen – gespeichert wird mit „Speichern“.') : __('Prüft Titel, Beschreibung, Überschriften, Alt-Texte und Textmenge dieser Seite.')) ?></p>
  <div class="kia-actions">
    <button type="button" class="adm-btn adm-btn--small" data-kia-check><?= e(__('SEO-Check')) ?></button>
    <?php if ($ai): ?><button type="button" class="adm-btn adm-btn--small kia-btn" data-kia-seo><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('SEO-Vorschläge')) ?></button><?php endif; ?>
    <?php if ($cfg['translate']): ?><button type="button" class="adm-btn adm-btn--small kia-btn" data-kia-translate-page><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('Aus {lang} übersetzen', ['lang' => $cfg['translate']['fromLabel']])) ?></button><?php endif; ?>
  </div>
  <div class="kia-out" data-kia-out aria-live="polite"></div>
</section>
