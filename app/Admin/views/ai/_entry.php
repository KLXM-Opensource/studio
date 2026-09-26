<?php
/**
 * Eintrag bearbeiten: Karte „KI-Assistent“ (Core\AI) – Teaser, SEO-Vorschläge (Beschreibung, Adresse) und Übersetzen
 * aus der Standardsprache. Alles füllt nur das Formular; gespeichert wird mit „Speichern“. Oberfläche: resources/js/_ai.js
 * @var array $t  @var ?array $e
 */
use Core\AI\Assist;
use Core\Data\Entries;
use Core\Fields;
use Core\Lang;

if (!$e || !Assist::available('text')) return;
$teaser = Assist::teaserField($t);
$df = (string) ($t['settings']['description_field'] ?? '');
$descField = null;
foreach ($t['fields'] as $f) if ($f['name'] === $df && in_array($f['type'], ['text', 'textarea'], true)) $descField = $f;
$lang = Lang::norm($e['lang'] ?? null);
$translate = null;
if (Lang::multi() && $lang !== Lang::default() && ($src = Entries::translations($t, $e)[Lang::default()] ?? null)) {
    $fields = [];
    foreach (Entries::schema($t) as $f) {
        if (!isset($f['name']) || !Fields::translatable($f) || in_array($f['type'], ['repeater', 'group'], true)) continue;
        $v = $src[$f['name']] ?? '';
        if (!is_string($v) || trim(strip_tags($v)) === '') continue;
        $fields[] = ['name' => 'f[' . $f['name'] . ']', 'label' => $f['label'] ?? $f['name'], 'html' => in_array($f['type'], ['richtext', 'inline'], true), 'source' => $v];
    }
    if ($fields) $translate = ['from' => Lang::default(), 'fromLabel' => Lang::all()[Lang::default()] ?? '', 'to' => $lang, 'toLabel' => Lang::all()[$lang] ?? $lang, 'fields' => $fields];
}
$cfg = [
    'table' => $t['handle'], 'id' => (int) $e['id'], 'lang' => $lang, 'route' => ($t['settings']['route'] ?? '') !== '',
    'teaser' => $teaser ? ['name' => 'f[' . $teaser['name'] . ']', 'label' => $teaser['label'], 'max' => (int) ($teaser['max'] ?? 0) ?: 200] : null,
    'desc' => $descField ? ['name' => 'f[' . $descField['name'] . ']', 'label' => $descField['label']] : null,
    'translate' => $translate,
];
?>
<section class="adm-card kia-card" data-kia-entry="<?= e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) ?>" aria-labelledby="kia-entry-h">
  <h2 id="kia-entry-h"><?= e(__('KI-Assistent')) ?></h2>
  <p class="adm-muted"><?= e(__('Vorschläge füllen nur das Formular – bitte prüfen und dann speichern.')) ?></p>
  <div class="kia-actions">
    <?php if ($teaser): ?><button type="button" class="adm-btn adm-btn--small kia-btn" data-kia-teaser><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('„{field}“ vorschlagen', ['field' => $teaser['label']])) ?></button><?php endif; ?>
    <?php if ($cfg['route']): ?><button type="button" class="adm-btn adm-btn--small kia-btn" data-kia-seo><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('SEO-Vorschläge')) ?></button><?php endif; ?>
    <?php if ($translate): ?><button type="button" class="adm-btn adm-btn--small kia-btn" data-kia-translate-form="<?= e(json_encode($translate, JSON_UNESCAPED_UNICODE)) ?>"><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('Aus {lang} übersetzen', ['lang' => $translate['fromLabel']])) ?></button><?php endif; ?>
  </div>
  <div class="kia-out" data-kia-out aria-live="polite"></div>
</section>
