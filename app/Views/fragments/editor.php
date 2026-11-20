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
    'picker' => url('/admin/api/block-picker'),
];
// Blockauswahl: persönliche Ansicht, Favoriten, zuletzt verwendet (Core\Blocks\Picker, UiPrefs)
$config['picker'] = \Core\Blocks\Picker::prefs(app()->auth->user());
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
<?php if ($content): /* Modus „Bearbeiten“ einer Detailseite: Seite wie für Besucher (Felder im Text bearbeitbar), Block-Editor an der Stelle der Inhaltsblöcke (editor.js) */ ?><div class="eb-page"><?= $content['page'] ?></div>
<div class="eb-slotwrap" data-cms-content-editor><p class="eb-edit-note" role="note"><span class="eb-edit-note__b"><?= icon('article') ?> <?= e(__('{field} – Blöcke dieses Eintrags', ['field' => $content['label']])) ?></span></p><?php endif; ?>
<div id="cms-editor" class="cms-editor"></div>
<?php if ($content): ?></div><?php endif; ?>
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
