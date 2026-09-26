<?php
/**
 * Kopfbereich – Variante aus Verwaltung → Design → „Navigation“ (design('nav')): modern | classic | minimal | extended.
 *   modern     schwebende, abgerundete Leiste mit feiner Kante (beim Scrollen milchglasartig)
 *   classic    volle Breite, Logo links, Menü rechts, Linie unten
 *   minimal    nur Marke, Button und Menü-Schaltfläche (Seitenblatt auf allen Geräten)
 *   extended   zusätzliche Kontaktzeile oben (Telefon, E-Mail, „Jetzt geöffnet“, Sprache)
 *
 * Unter 64 em öffnet die Menü-Schaltfläche das Seitenblatt (#mnav, HTML-popover – funktioniert ohne JavaScript).
 * site.js prüft zusätzlich, ob das Menü wirklich in die Leiste passt (sonst .is-overflow → Menü-Schaltfläche).
 */
$variant = in_array(design('nav'), ['modern', 'classic', 'minimal', 'extended'], true) ? (string) design('nav') : 'modern';
$menu = \Core\HeaderActions::menu(modern_menu());   // Stil „Menüpunkt“: Ziel des Handlungsaufrufs nicht doppelt im Menü
$langs = language_links();
$cta = modern_header_cta();
// Kopfbereich-Aktionen (Design → „Kopfbereich: Suche & Aktionen“, Core\HeaderActions): Suche, Handlungsaufruf, Kontakt-Chip …
$actions = header_actions('bar', ['compact' => true]);
$searchMenu = \Core\Search\Search::form('menu');
$brandHref = !empty(app()->currentPage['is_home']) ? '#main' : url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$hasSheet = $menu || $langs || $cta || $searchMenu !== '';
$hours = $variant === 'extended' ? modern_hours() : [];
?>
<header class="hdr hdr--<?= e($variant) ?>" data-header>
  <?php if ($variant === 'extended' && (($quick = modern_quick_contact('hdr__quick')) !== '' || $hours || $langs)): ?>
  <div class="hdr__top">
    <div class="hdr__topin">
      <?= $quick ?>
      <?php if ($hours): ?><p class="openstate" data-openstate data-open="<?= e(lt('Jetzt geöffnet')) ?>" data-closed="<?= e(lt('Zurzeit geschlossen')) ?>" hidden><span class="openstate__led" aria-hidden="true"></span><span data-openstate-text></span></p>
      <span hidden data-hours><?php foreach ($hours as $h): ?><span data-dows="<?= e($h['dows']) ?>" data-slots="<?= e($h['slots']) ?>"></span><?php endforeach; ?></span><?php endif; ?>
      <?php if ($langs): ?><?= header_actions_lang($langs, 'hdr__toplang') ?><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="hdr__bar">
    <?= app()->theme->partial('brand', ['href' => $brandHref, 'class' => 'hdr__brand']) ?>
    <?= header_actions('center') ?>
    <?php if (($menu || ($langs && $variant !== 'extended')) && $variant !== 'minimal'): ?>
    <div class="hdr__nav">
      <?php if ($menu): ?><nav class="hnav" aria-label="<?= e(lt('Hauptnavigation')) ?>"><?= modern_nav_inline($menu) ?></nav><?php endif; ?>
      <?php if ($langs && $variant !== 'extended'): ?><?= header_actions_lang($langs, 'hdr__lang') ?><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="hdr__tools">
      <?= $actions ?>
      <?php if ($hasSheet): ?>
      <button type="button" class="menu-btn" popovertarget="mnav" aria-controls="mnav" aria-haspopup="dialog" aria-expanded="false">
        <span class="menu-btn__bars" aria-hidden="true"></span><span class="menu-btn__label"><?= e(lt('Menü')) ?></span>
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?= header_actions('below', ['wrap' => 'hdr__below']) ?>
</header>
