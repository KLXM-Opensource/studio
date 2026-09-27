<?php
/**
 * Editor-Mount für Inline-Editing (Core-Editor, Theme-unabhängig).
 * @var array $page  @var array $blocks  @var array $previews
 */
$config = app()->theme->editorConfig();
$config['page'] = ['id' => (int) $page['id'], 'title' => $page['title']];
$config['endpoints'] = [
    'save' => url('/admin/api/pages/' . $page['id'] . '/save'),
    'discard' => url('/admin/api/pages/' . $page['id'] . '/discard'),
    'preview' => url('/admin/api/preview'),
    'form' => url('/admin/api/block-form'),
    'links' => url('/admin/api/links'),
    'media' => url('/admin/api/media'),
    'upload' => url('/admin/media/upload'),
    'settings' => url('/admin/settings'),
];
$config['csrf'] = \Core\Csrf::token();
$config['settingsTitle'] = app()->theme->settingsTitle();
// Detailseiten-Vorlage: Vorschau mit dem aufgerufenen Eintrag
$config['entry'] = app()->entry ? ['table' => app()->entry['table']['handle'], 'id' => (int) app()->entry['entry']['id']] : null;
?>
<div id="cms-editor" class="cms-editor"></div>
<script type="application/json" id="cms-editor-config"><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script type="application/json" id="cms-editor-data"><?= json_encode(['blocks' => $blocks, 'previews' => $previews], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('vendor/editorjs/editorjs.umd.js')) ?>" defer></script>
<script src="<?= e(asset('vendor/editorjs/drag-drop.js')) ?>" defer></script>
<script src="<?= e(asset('js/admin.js')) ?>" defer></script>
<script src="<?= e(asset('js/editor.js')) ?>" defer></script>
<aside class="cms-drawer" id="cms-drawer" aria-labelledby="cms-drawer-title" hidden>
  <div class="cms-drawer__head">
    <h2 id="cms-drawer-title"><?= e(__('Block')) ?></h2>
    <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-drawer-close><?= e(__('Schließen')) ?></button>
  </div>
  <p class="cms-drawer__central" data-drawer-central hidden></p>
  <form class="cms-drawer__form adm-fields" data-drawer-form novalidate></form>
  <details class="cms-drawer__section" open>
    <summary><?= e(__('Abschnitt & Navigation')) ?></summary>
    <form data-drawer-tunes novalidate></form>
  </details>
</aside>
<input type="hidden" id="adm-csrf" value="<?= e(\Core\Csrf::token()) ?>">
<datalist id="cms-links" data-endpoint="<?= e(url('/admin/api/links')) ?>"></datalist>
