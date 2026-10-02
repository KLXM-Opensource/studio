<?php
/**
 * Redaktions-Werkzeugleiste (Core\Toolbar) – landet im Shadow DOM des Hosts .cms-bar-host (Core\Theme::toolbarHost).
 * Alles nach <!--cms-bar-end--> (Hinweiszeile, Konfiguration) bleibt im Dokument.
 * @var array $bar
 */
use Core\Data\EntryEdit;

$b = $bar;
$kind = $b['kind'];
$mode = $b['mode'];
$edit = $mode !== 'view';
$T = $b['config']['texts'];
$chip = $b['status'] !== '' ? $T['chip'][$b['status']] : null;
$page = $b['page'];
// Umschalter „Ansehen | Bearbeiten“ vorhanden → der Primär-Knopf „Bearbeiten“ wäre doppelt: ab 1024 px ausblenden
// (darunter ist der Umschalter im Menü „⋯“, dort bleibt der Knopf die Hauptaktion; editor.shadow.css .cms-bar__grp--seg)
$segEdit = in_array('edit', array_column($b['modes'] ?: [], 0), true) ? ' cms-bar__grp--seg' : '';

// Menüeintrag: <a> mit href oder <button>; $extra = bereits maskierte Attribute
$item = function (string $label, string $ico, ?string $href = null, string $extra = '', string $class = '') {
    $attrs = ' role="menuitem" class="cms-menu__item' . ($class !== '' ? ' ' . $class : '') . '" tabindex="-1"' . $extra;
    $inner = '<span class="cms-menu__ico" aria-hidden="true">' . icon($ico) . '</span><span class="cms-menu__label">' . $label . '</span>';
    return $href !== null ? '<a href="' . e($href) . '"' . $attrs . '>' . $inner . '</a>' : '<button type="button"' . $attrs . '>' . $inner . '</button>';
};
$sep = '<div class="cms-menu__sep" role="separator"></div>';
// Werkzeuge (Core\FrontendTools): auf Detailseiten wechselt „Ansehen ↔ Bearbeiten“ ohne Neuladen – Werkzeuge nur eines Modus
// bekommen data-bar-when (_bar.js setMode), Werkzeuge für beide Modi bleiben immer sichtbar
$toolWhen = function (array $tl) use ($kind, $edit): string {
    if ($kind !== 'entry' || ($tl['when'] ?? 'edit') === 'both') return '';
    $w = $tl['when'] === 'view' ? 'view' : 'edit';
    return ' data-bar-when="' . $w . '"' . (($w === 'edit') === $edit ? '' : ' hidden');
};
?>
<div class="cms-bar cms-bar--<?= e($kind) ?><?= $edit ? ' is-edit' : '' ?>" role="region" aria-label="<?= e(__('Redaktion')) ?>" data-bar="<?= json_attr($b['config']) ?>" data-mode="<?= e($mode) ?>" data-search-endpoint="<?= e(url('/admin/api/search')) ?>">
  <div class="cms-bar__ctx">
    <a class="cms-bar__brand" href="<?= e(url('/admin')) ?>" aria-label="<?= e(CMS_NAME) ?> – <?= e(__('Verwaltung')) ?>" title="<?= e(CMS_NAME) ?> – <?= e(__('Verwaltung')) ?>"><?= cms_logo() ?></a>
    <span class="cms-bar__obj" aria-hidden="true"><?= icon($kind === 'page' ? 'file-text' : ($kind === 'template' ? 'squares-four' : ($b['table']['icon'] ?: 'table'))) ?></span>
    <span class="cms-bar__titles">
      <span class="cms-bar__kind"><?php if ($kind === 'page'): ?><?= e(\Core\NotFound::isPage($page) ? __('Nicht gefunden (404) – für alle unbekannten Adressen') : __('Seite')) ?><?php elseif ($kind === 'template'): ?><?= e(__('Gilt für alle Einträge')) ?><?php else: ?><?= e($b['table']['name']) ?><?php if ($b['foreign']): ?> · <?= e(__('von {site}', ['site' => $b['site']])) ?><?php endif; ?><?php endif; ?></span>
      <?php $title = $kind === 'page' ? (string) $page['title'] : ($kind === 'template' ? __('Vorlage: {table}', ['table' => $b['table']['name']]) : $b['entryTitle']); ?>
      <span class="cms-bar__title" title="<?= e($title) ?>"><?= e($title) ?></span>
    </span>
    <?php if ($chip): ?>
    <span class="cms-chipwrap">
      <button type="button" class="cms-chip" data-bar-chip data-state="<?= e($b['status']) ?>" aria-expanded="false" aria-controls="cms-chip-pop" aria-label="<?= e(__('Status: {status}', ['status' => $chip[0]])) ?>">
        <span class="cms-chip__dot" aria-hidden="true"></span><span class="cms-chip__t"><?= e($chip[0]) ?></span>
      </button>
      <span class="cms-pop" id="cms-chip-pop" hidden><strong data-bar-chip-title><?= e($chip[0]) ?></strong> <span data-bar-chip-text><?= e($chip[1]) ?></span>
        <?php if ($b['toggle']): // Online/Offline umschalten (_bar.js initToggle); sichtbar je nach Zustand ?>
        <span class="cms-pop__err" data-bar-toggle-err role="alert" hidden></span>
        <span class="cms-pop__hint" data-bar-pending hidden><?= e($T['pendingHint']) ?></span>
        <span class="cms-pop__act">
          <button type="button" class="cms-btn cms-btn--small" data-bar-offline hidden><?= icon('eye-slash') ?><span><?= e($T['goOffline']) ?></span></button>
          <button type="button" class="cms-btn cms-btn--small cms-btn--primary" data-bar-online hidden><?= icon('globe') ?><span><?= e($T['goOnline']) ?></span></button>
        </span>
        <?php endif; ?>
      </span>
    </span>
    <?php endif; ?>
    <?php if ($kind === 'template' && count($b['others']) > 1): $cur = array_values(array_filter($b['others'], fn($o) => $o['current']))[0] ?? $b['others'][0]; ?>
    <span class="cms-menuwrap cms-bar__pick">
      <button type="button" class="cms-pick" aria-label="<?= e(__('Vorschau mit') . ': ' . $cur['title']) ?>" aria-haspopup="menu" aria-expanded="false" aria-controls="cms-pick-menu" title="<?= e(__('Vorlage mit anderem Eintrag ansehen')) ?>">
        <?= icon('eye') ?><span class="cms-pick__l"><?= e(__('Vorschau mit')) ?></span> <span class="cms-pick__t"><?= e(mb_strimwidth($cur['title'], 0, 40, '…')) ?></span> <?= icon('caret-down') ?>
      </button>
      <span class="cms-menu cms-menu--scroll" id="cms-pick-menu" role="menu" aria-label="<?= e(__('Vorlage mit anderem Eintrag ansehen')) ?>" hidden>
        <?php foreach ($b['others'] as $o): ?><a role="menuitemradio" class="cms-menu__item" tabindex="-1" href="<?= e($o['url']) ?>" aria-checked="<?= $o['current'] ? 'true' : 'false' ?>"><span class="cms-menu__ico" aria-hidden="true"><?= $o['current'] ? icon('check') : '' ?></span><span class="cms-menu__label"><?= e(mb_strimwidth($o['title'], 0, 56, '…')) ?></span></a><?php endforeach; ?>
      </span>
    </span>
    <?php endif; ?>
  </div>

  <?php if ($b['modes']): ?>
  <div class="cms-seg" role="group" aria-label="<?= e(__('Modus')) ?>">
    <?php foreach ($b['modes'] as [$key, $label, $ico, $href, $on]): ?>
      <?php if ($href !== null): ?><a class="cms-seg__i" href="<?= e($href) ?>" title="<?= e($label) ?>" data-bar-mode="<?= e($key) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= icon($ico) ?><span><?= e($label) ?></span></a>
      <?php else: ?><button type="button" class="cms-seg__i" title="<?= e($label) ?>" data-bar-mode="<?= e($key) ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= icon($ico) ?><span><?= e($label) ?></span></button><?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="cms-bar__act">
    <span class="cms-sr" role="status" aria-live="polite" <?= $kind === 'entry' ? 'data-entry-status' : 'data-editor-status' ?>></span>

    <?php /* ---------- Ansehen: EINE Hauptaktion ---------- */ ?>
    <?php if ($kind === 'entry'): ?>
    <span class="cms-bar__grp<?= $b['entryEditable'] ? $segEdit : '' ?>" data-bar-group="view"<?= $edit ? ' hidden' : '' ?>>
      <?php if ($b['entryEditable']): ?>
        <button type="button" class="cms-btn cms-btn--primary" data-bar-mode="edit"><?= icon('pencil-simple') ?><span><?= e(__('Bearbeiten')) ?></span></button>
      <?php elseif ($b['origin'] && $b['origin']['sso']): ?>
        <form method="post" action="<?= e($b['origin']['url']) ?>" class="cms-bar__form"><?= csrf_field() ?><input type="hidden" name="site" value="<?= e($b['origin']['site']) ?>"><input type="hidden" name="path" value="<?= e($b['origin']['path']) ?>">
          <button class="cms-btn cms-btn--primary"><?= icon('arrow-square-out') ?><span><?= e(__('Auf Ursprungs-Website bearbeiten')) ?></span></button></form>
      <?php elseif ($b['origin']): ?>
        <a class="cms-btn cms-btn--primary" href="<?= e($b['origin']['url']) ?>" rel="noopener"><?= icon('arrow-square-out') ?><span><?= e(__('Auf Ursprungs-Website bearbeiten')) ?></span></a>
      <?php endif; ?>
    </span>
    <?php elseif (!$edit && $b['hasPage'] && $b['canEditPages']): ?>
    <span class="cms-bar__grp<?= $segEdit ?>" data-bar-group="view">
      <a class="cms-btn cms-btn--primary" href="<?= e($b['pageEditUrl']) ?>" data-bar-mode="edit"><?= icon('pencil-simple') ?><span><?= e(__('Bearbeiten')) ?></span></a>
    </span>
    <?php endif; ?>

    <?php /* ---------- Bearbeiten: Abbrechen · Speichern · Veröffentlichen (auf Telefonen als Leiste unten) ---------- */ ?>
    <?php if ($kind === 'entry' && $b['entryEditable']): ?>
    <span class="cms-bar__grp cms-bar__edit" data-bar-group="edit"<?= $edit ? '' : ' hidden' ?>>
      <button type="button" class="cms-btn cms-btn--ghost" data-bar-cancel aria-keyshortcuts="Escape"><?= e(__('Abbrechen')) ?></button>
      <?php if ($b['entryPub']): /* Freigabe-Ablauf: Entwurf = Speichern + Veröffentlichen; veröffentlicht = Speichern (sofort online), „Als Entwurf“ im Menü „⋯“.
        Beide Varianten stehen im Markup – _bar.js (Status-Chip: Online/Offline) schaltet ohne Neuladen um (data-entry-when) */ ?>
        <button type="button" class="cms-btn<?= $b['entryDraft'] ? '' : ' cms-btn--primary' ?>" data-entry-save data-bar-save aria-disabled="true" aria-keyshortcuts="Meta+S Control+S" data-title-online="<?= e(__('Änderungen sind nach dem Speichern sofort sichtbar.')) ?>"<?= $b['entryDraft'] ? '' : ' title="' . e(__('Änderungen sind nach dem Speichern sofort sichtbar.')) . '"' ?>><?= e(__('Speichern')) ?></button>
        <button type="button" class="cms-btn cms-btn--primary" data-entry-publish data-entry-when="draft"<?= $b['entryDraft'] ? '' : ' hidden' ?>><?= e(__('Veröffentlichen')) ?></button>
      <?php else: /* ohne Freigabe-Ablauf bzw. ohne Recht zum Veröffentlichen */ ?>
        <button type="button" class="cms-btn cms-btn--primary" data-entry-save data-bar-save aria-disabled="true" aria-keyshortcuts="Meta+S Control+S"><?= e(__('Speichern')) ?></button>
      <?php endif; ?>
    </span>
    <?php elseif ($edit && $b['hasPage']): ?>
    <span class="cms-bar__grp cms-bar__edit" data-bar-group="edit">
      <button type="button" class="cms-btn cms-btn--ghost" data-bar-cancel aria-keyshortcuts="Escape"><?= e(__('Abbrechen')) ?></button>
      <?php if ($b['canPublish']): ?>
        <button type="button" class="cms-btn" data-editor-save data-bar-save aria-disabled="true" aria-keyshortcuts="Meta+S Control+S"><?= e(__('Speichern')) ?></button>
        <?php if ($b['canDiscard']): ?>
        <span class="cms-split cms-menuwrap">
          <button type="button" class="cms-btn cms-btn--primary" data-editor-publish><?= e(__('Veröffentlichen')) ?></button><button type="button" class="cms-btn cms-btn--primary cms-split__caret" aria-haspopup="menu" aria-expanded="false" aria-controls="cms-pub-menu" aria-label="<?= e(__('Weitere Optionen zum Veröffentlichen')) ?>"><?= icon('caret-down') ?></button>
          <span class="cms-menu cms-menu--end" id="cms-pub-menu" role="menu" aria-label="<?= e(__('Weitere Optionen zum Veröffentlichen')) ?>" hidden>
            <?= $item(e(__('Entwurf verwerfen …')), 'arrow-counter-clockwise', null, ' data-editor-discard title="' . e(__('Alle Änderungen seit der letzten Veröffentlichung verwerfen')) . '"') ?>
          </span>
        </span>
        <?php else: ?>
        <button type="button" class="cms-btn cms-btn--primary" data-editor-publish><?= e(__('Veröffentlichen')) ?></button>
        <?php endif; ?>
      <?php else: ?>
        <button type="button" class="cms-btn cms-btn--primary" data-editor-save data-bar-save aria-disabled="true" aria-keyshortcuts="Meta+S Control+S"><?= e(__('Speichern')) ?></button>
      <?php endif; ?>
    </span>
    <?php endif; ?>

    <?php // Werkzeuge (Core\FrontendTools, resources/js/_tools.js): Knöpfe „main“ – Eintrag: je nach Modus des Werkzeugs ($toolWhen)
    foreach ($b['tools'] as $tl): if ($tl['placement'] !== 'main') continue; $sc = $tl['shortcut']; ?>
    <button type="button" class="cms-ibtn cms-bar__tool" data-cms-tool="<?= e($tl['id']) ?>" aria-label="<?= e($tl['label']) ?>" title="<?= e($tl['label'] . ($sc ? ' (' . $sc['label'] . ')' : '')) ?>" aria-haspopup="dialog" aria-expanded="false"<?= $sc ? ' aria-keyshortcuts="' . e($sc['keys']) . '"' : '' ?><?= $toolWhen($tl) ?>><?= icon($tl['icon']) ?></button>
    <?php endforeach; ?>
    <button type="button" class="cms-ibtn cms-bar__search" data-spotlight aria-label="<?= e(__('Suchen')) ?>" title="<?= e(__('Suchen (⌘K / Strg+K)')) ?>" aria-keyshortcuts="Meta+K Control+K"><?= icon('magnifying-glass') ?></button>

    <?php /* ---------- Alles Weitere: EIN Menü ---------- */ ?>
    <span class="cms-menuwrap">
      <button type="button" class="cms-ibtn" data-bar-more aria-haspopup="menu" aria-expanded="false" aria-controls="cms-more-menu" aria-label="<?= e(__('Weitere Aktionen')) ?>" title="<?= e(__('Weitere Aktionen')) ?>"><?= icon('dots-three') ?></button>
      <span class="cms-menu cms-menu--end" id="cms-more-menu" role="menu" aria-label="<?= e(__('Weitere Aktionen')) ?>" hidden>
        <?php if ($b['modes']): ?>
        <span class="cms-menu__grp cms-menu--narrow" role="group" aria-label="<?= e(__('Modus')) ?>">
          <span class="cms-menu__head" aria-hidden="true"><?= e(__('Modus')) ?></span>
          <?php foreach ($b['modes'] as [$key, $label, $ico, $href, $on]): ?>
            <?php if ($href !== null): ?><a role="menuitemradio" class="cms-menu__item" tabindex="-1" href="<?= e($href) ?>" data-bar-mode="<?= e($key) ?>" aria-checked="<?= $on ? 'true' : 'false' ?>"><span class="cms-menu__ico" aria-hidden="true"><?= icon($ico) ?></span><span class="cms-menu__label"><?= e($label) ?></span></a>
            <?php else: ?><button type="button" role="menuitemradio" class="cms-menu__item" tabindex="-1" data-bar-mode="<?= e($key) ?>" aria-checked="<?= $on ? 'true' : 'false' ?>"><span class="cms-menu__ico" aria-hidden="true"><?= icon($ico) ?></span><span class="cms-menu__label"><?= e($label) ?></span></button><?php endif; ?>
          <?php endforeach; ?>
        </span>
        <span class="cms-menu__sep cms-menu--narrow" role="separator"></span>
        <?php endif; ?>

        <?php if ($edit && $b['hasPage']): ?>
          <?= $item(e(__('Vorschau')) . '<small>' . e(__('ohne Bearbeitungsleisten – speichert vorher')) . '</small>', 'eye', $b['viewUrl'], ' data-editor-preview') ?>
          <button type="button" role="menuitemcheckbox" class="cms-menu__item" tabindex="-1" data-editor-compact aria-checked="false"><span class="cms-menu__ico" aria-hidden="true"><?= icon('list') ?></span><span class="cms-menu__label"><?= e(__('Kompakt')) ?><small><?= e(__('Blöcke einklappen – zum Umsortieren')) ?></small></span></button>
          <?= $item(e(__('Markdown importieren …')) . '<small>' . e(__('Text oder .md-Datei als Textblöcke einfügen')) . '</small>', 'file-text', null, ' data-editor-md aria-haspopup="dialog"') ?>
        <?php endif; ?>
        <?php if ($kind === 'entry' && $b['entryPub']): ?>
          <?= $item(e(__('Als Entwurf speichern (offline nehmen)')), 'eye-slash', null, ' data-entry-draft data-entry-when="published" data-bar-when="' . ($b['entryDraft'] ? 'never' : 'edit') . '"' . ($edit && !$b['entryDraft'] ? '' : ' hidden')) ?>
        <?php endif; ?>
        <?php if ($kind !== 'page' && $b['panel'] && !$b['live']): ?>
          <?= $item(e(__('Alle Felder bearbeiten')) . '<small>' . e(__('Seitenleiste mit allen Feldern des Eintrags')) . '</small>', 'sidebar-simple', null, ' data-entry-edit="' . e($b['panel']) . '" aria-haspopup="dialog"') ?>
        <?php endif; ?>
        <?php if ($kind === 'page' && !$edit && $b['canDiscard']): ?>
          <?= $b['live'] ? $item(e(__('Arbeitsstand ansehen')), 'pencil-simple', $b['viewUrl']) : $item(e(__('Live-Fassung ansehen')) . '<small>' . e(__('so sehen Besucher die Seite gerade')) . '</small>', 'globe', $b['viewUrl'] . '?live=1') ?>
        <?php elseif ($kind === 'entry'): ?>
          <?= $b['live'] ? $item(e(__('Zurück zur Bearbeitung')), 'pencil-simple', $b['viewUrl']) : $item(e(__('Live-Ansicht')) . '<small>' . e(__('so sehen Besucher die Seite gerade')) . '</small>', 'globe', $b['viewUrl'] . '?live=1') ?>
        <?php endif; ?>
        <?php if ($kind === 'page' && !$edit && !$b['live'] && $b['dirtyDraft'] && $b['canDiscard'] && $b['canPublish']): ?>
          <form method="post" action="<?= e(url('/admin/pages/' . $page['id'] . '/discard')) ?>" class="cms-bar__form" data-confirm="<?= e(__('Alle unveröffentlichten Änderungen verwerfen? Der Entwurf wird als Version gesichert.')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="page">
            <button type="submit" role="menuitem" class="cms-menu__item" tabindex="-1"><span class="cms-menu__ico" aria-hidden="true"><?= icon('arrow-counter-clockwise') ?></span><span class="cms-menu__label"><?= e(__('Entwurf verwerfen …')) ?><small><?= e(__('zurück zur veröffentlichten Fassung')) ?></small></span></button></form>
        <?php endif; ?>
        <?= $sep ?>
        <?php if ($kind !== 'page' && $b['canTable'] && !$b['foreign']): ?><?= $item(e(__('In der Verwaltung öffnen')), 'arrow-square-out', $b['adminUrl']) ?><?php endif; ?>
        <?php foreach ($b['tools'] as $tl): // Werkzeuge (Core\FrontendTools): „more“ immer hier, „main“ nur auf Telefonen (Knopf ist dort ausgeblendet)
          $sc = $tl['shortcut']; $when = $toolWhen($tl); ?>
          <button type="button" role="menuitem" class="cms-menu__item<?= $tl['placement'] === 'main' ? ' cms-menu--phone' : '' ?>" tabindex="-1" data-cms-tool="<?= e($tl['id']) ?>" aria-haspopup="dialog"<?= $sc ? ' aria-keyshortcuts="' . e($sc['keys']) . '"' : '' ?><?= $when ?>><span class="cms-menu__ico" aria-hidden="true"><?= icon($tl['icon']) ?></span><span class="cms-menu__label"><?= e($tl['label']) ?><?php if ($tl['hint'] !== ''): ?><small><?= e($tl['hint']) ?></small><?php endif; ?></span><?php if ($sc): ?><kbd><?= e($sc['label']) ?></kbd><?php endif; ?></button>
        <?php endforeach; ?>
        <?php foreach ($b['ext']['items'] ?? [] as $xi): // Erweiterungen (Extension::toolbar) ?>
          <?= $item(e($xi['label']) . ($xi['hint'] !== '' ? '<small>' . e($xi['hint']) . '</small>' : ''), $xi['icon'], $xi['href'] !== null ? url($xi['href']) : null,
              implode('', array_map(fn($k, $v) => ' data-' . e($k) . '="' . e($v) . '"', array_keys($xi['data']), $xi['data']))) ?>
        <?php endforeach; ?>
        <?php if ($b['hasPage'] && $b['canManage']): ?><?= $item(e($kind === 'page' ? __('Seiteneinstellungen') : __('Einstellungen der Vorlagen-Seite')), 'gear-six', url('/admin/pages/' . $page['id'])) ?><?php endif; ?>
        <?php if ($b['canSettings']): ?><?= $item(e($b['settingsTitle']), 'sliders-horizontal', url('/admin/settings')) ?><?php endif; ?>
        <?= $sep ?>
        <button type="button" role="menuitem" class="cms-menu__item cms-menu--phone" tabindex="-1" data-spotlight><span class="cms-menu__ico" aria-hidden="true"><?= icon('magnifying-glass') ?></span><span class="cms-menu__label"><?= e(__('Suchen')) ?></span><kbd data-kbd>⌘K</kbd></button>
        <?php if ($b['ai']): ?><?= $item(e($b['aiBrand']), 'sparkle', $b['ai']['area']) ?><?php endif; ?>
        <?= $item(e(__('Hilfe')), 'question', $b['help'], ' target="_blank" rel="noopener"') ?>
      </span>
    </span>
  </div>
</div>
<!--cms-bar-end-->
<?php if ($b['tools']): // Werkzeuge beim Bearbeiten: Konfiguration für resources/js/_tools.js (Module erst beim Öffnen) ?>
<script type="application/json" id="cms-tools"><?= json_encode(\Core\FrontendTools::config($b, $b['tools']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
<?php foreach ($b['ext']['scripts'] ?? [] as $xs): // Skripte der Erweiterungen (Extension::toolbar) – nur 'self' ?><script src="<?= e($xs['src']) ?>"<?= $xs['module'] ? ' type="module"' : ' defer' ?>></script>
<?php endforeach; ?>
<?php if ($kind === 'entry'): $reason = $b['canTable'] && !$b['foreign'] ? EntryEdit::reason($b['table'], $b['entry']) : null; ?>
<?php $other = ($b['foreign'] ? ' ' . __('Dieser Eintrag stammt von „{site}“ und ist hier nur lesbar. Ändern kann ihn nur diese Website.', ['site' => $b['site']]) : '') . ($reason && !$b['live'] ? ' ' . $reason : '');
// Mit Online/Offline-Umschalter (Status-Chip) steht der Hinweis immer im Markup – _bar.js blendet den Entwurfs-Teil ohne Neuladen ein/aus
if ($b['entryDraft'] || $other !== '' || $b['toggle']): ?>
<div class="cms-entry-note<?= $b['entryDraft'] ? ' cms-entry-note--draft' : '' ?>" role="note" data-entry-note<?= $b['entryDraft'] || $other !== '' ? '' : ' hidden' ?>>
  <span data-entry-note-draft<?= $b['entryDraft'] ? '' : ' hidden' ?>><strong><?= e(__('Entwurf')) ?></strong> – <?= e(__('nur für die angemeldete Redaktion sichtbar. Besucher sehen diesen Eintrag erst nach dem Veröffentlichen.')) ?></span><?php if ($other !== ''): ?><span data-entry-note-other><?= e($other) ?></span><?php endif; ?>
</div>
<?php endif; ?>
<?php if ($b['entryEditable']): ?>
<script type="application/json" id="cms-entry-config"><?= json_encode(EntryEdit::config($b['table'], $b['entry']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
<?php endif; ?>
