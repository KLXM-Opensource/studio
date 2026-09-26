<?php
/**
 * Suchformular der Website-Suche (Themes können es mit templates/partials/search-form.php ersetzen).
 * Aufruf: \Core\Search\Search::form('header' | 'menu' | 'page', $q)
 *   header  Lupe im Kopfbereich öffnet das Feld als Popover (HTML popover: ohne JavaScript, Escape/Klick daneben schließt,
 *           Fokus springt per autofocus ins Feld und beim Schließen zurück auf die Lupe)
 *   menu    Feld im mobilen Menü
 *   page    großes Feld auf der Ergebnisseite
 * Vorschläge beim Tippen: search.js wird erst geladen, wenn das Feld den Fokus bekommt (data-suggest-js, Theme-site.js).
 * @var string $action  @var string $q  @var string $variant  @var string $suggest  @var string $js  @var string $id
 * @var ?string $pid  id des Popovers  @var ?bool $panelOnly  nur das Popover (eigener Auslöser, Core\HeaderActions „Befehlsfeld“)
 * Variante „hero“: großes Feld im Einstieg (Core\Blocks\Hero::search) – Beschriftung sichtbar, sobald $label gesetzt ist.
 * @var ?string $label  @var ?string $placeholder  eigene Texte (leer = Standard)
 */
$label ??= '';
$placeholder ??= '';
$visible = $variant === 'page' || ($variant === 'hero' && $label !== '');
$icon = '<svg aria-hidden="true" focusable="false" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>';
$form = '<form class="sf sf--' . e($variant) . '" role="search" action="' . e($action) . '" method="get">'
    . '<label class="sf__label' . ($visible ? '' : ' sf-sr') . '" for="' . e($id) . '">' . e($label !== '' ? $label : lt('Website durchsuchen')) . '</label>'
    . '<span class="sf__row"><input class="sf__input" id="' . e($id) . '" type="search" name="q" value="' . e($q) . '" maxlength="200" autocomplete="off" spellcheck="false" enterkeyhint="search"'
    . ($variant === 'header' ? ' autofocus' : '')
    . ' placeholder="' . e($placeholder !== '' ? $placeholder : lt('Suchbegriff eingeben')) . '" data-suggest="' . e($suggest) . '" data-suggest-js="' . e($js) . '"'
    . ' data-l10n="' . json_attr(['all' => lt('Alle Ergebnisse für'), 'count' => lt('{n} Vorschläge')]) . '">'
    . '<button class="sf__btn" type="submit">' . $icon . '<span' . (in_array($variant, ['page', 'hero'], true) ? '' : ' class="sf-sr"') . '>' . e(lt('Suchen')) . '</span></button></span>'
    . '</form>';
if ($variant === 'header'): $pid ??= 'hsearch-' . substr(md5($id), 0, 6); ?>
<?php if (empty($panelOnly)): ?><button type="button" class="hsearch__btn" popovertarget="<?= e($pid) ?>" title="<?= e(lt('Suche')) ?>"><?= $icon ?><span class="sf-sr"><?= e(lt('Suche öffnen')) ?></span></button>
<?php endif; ?>
<div class="hsearch__panel" id="<?= e($pid) ?>" popover>
  <?= $form ?>
  <button type="button" class="hsearch__close" popovertarget="<?= e($pid) ?>" popovertargetaction="hide"><span aria-hidden="true">×</span><span class="sf-sr"><?= e(lt('Suche schließen')) ?></span></button>
</div>
<?php else: ?>
<?= $form ?>
<?php endif;
