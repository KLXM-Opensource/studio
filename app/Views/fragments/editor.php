<?php
/**
 * Kern-Fragment „editor“ (nur Kern – Kits können es nicht ersetzen, Core\Fragments::CORE_ONLY):
 * Editor-Mount für Inline-Editing (Editor.js, Seitenleiste „Block“, Konfiguration). Aufruf im Layout des Kits:
 * <?= $theme->partial('editor', $editor) ?> – Aussehen: resources/css/editor.css (Kern).
 * @var array $page  @var array $blocks  @var array $previews  @var ?array $content (Modus „Inhalt“: Inhaltsblöcke eines Eintrags)
 */
$content ??= null;
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
    'formfields' => url('/admin/api/formfields/'),
];
// „Felder bearbeiten“ bei Formular-Blöcken: nur mit Recht data.schema, geteilte Tabellen nur auf der Eigentümer-Website
$config['formFields'] = can('data.schema')
    ? array_values(array_map(fn($t) => $t['handle'], array_filter(\Core\Data\Tables::all(), [\Core\Data\Shared::class, 'canSchema'])))
    : [];
$config['csrf'] = \Core\Csrf::token();
$config['settingsTitle'] = app()->theme->settingsTitle();
// Detailseiten-Vorlage: Vorschau mit dem aufgerufenen Eintrag
$config['entry'] = app()->entry ? ['table' => app()->entry['table']['handle'], 'id' => (int) app()->entry['entry']['id']] : null;
// Modus „Inhalt“: Speichern ins Feld des Eintrags, nur die erlaubten Blöcke einfügbar, kein Entwurf/Verwerfen
if ($content) {
    $config['content'] = ['table' => $content['table'], 'id' => $content['id'], 'field' => $content['field'], 'label' => $content['label']];
    $config['endpoints']['save'] = url('/admin/api/entries/' . $content['table'] . '/' . $content['id'] . '/blocks/' . $content['field']);
    unset($config['endpoints']['discard']);
    foreach ($config['blocks'] as $t => &$bd) $bd['insertable'] = !empty($bd['insertable']) && in_array($t, $content['allowed'], true);
    unset($bd);
}
?>
<?php if ($content): ?><div class="eb-static" inert><?= $content['before'] ?></div>
<p class="eb-edit-note" role="note"><?= icon('article') ?> <?= e(__('Inhaltsblöcke von „{entry}“ – „{field}“. Vorlage darüber und darunter: nur Vorschau.', ['entry' => \Core\Data\Entries::title(app()->entry['table'], app()->entry['entry']), 'field' => $content['label']])) ?></p><?php endif; ?>
<div id="cms-editor" class="cms-editor"></div>
<?php if ($content): ?><div class="eb-static" inert><?= $content['after'] ?></div><?php endif; ?>
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
  <div class="cms-drawer__vis" data-drawer-vis></div>
  <p class="cms-drawer__central" data-drawer-central hidden></p>
  <form class="cms-drawer__form adm-fields" data-drawer-form novalidate></form>
  <details class="cms-drawer__section" open>
    <summary><?= e(__('Abschnitt & Navigation')) ?></summary>
    <form data-drawer-tunes novalidate></form>
  </details>
</aside>
<input type="hidden" id="adm-csrf" value="<?= e(\Core\Csrf::token()) ?>">
<datalist id="cms-links" data-endpoint="<?= e(url('/admin/api/links')) ?>"></datalist>
