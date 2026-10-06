<?php
/**
 * Seitenblatt der Menü-Schaltfläche (HTML-popover, außerhalb von .page, damit der Rest der Seite inert werden kann).
 * Ohne JavaScript bedienbar: Escape und Klick daneben schließen, Scrollen gesperrt per :has(); Unterseiten als Akkordeon
 * (<details name> – nur eins offen). site.js ergänzt Fokusfalle, inerten Rest der Seite und den Fokus beim Öffnen.
 */
$menu = galerie_menu();
$langs = language_links();
$cta = galerie_header_cta();
$searchMenu = \Core\Search\Search::form('menu');
$hasSheet = $menu || $langs || $cta || $searchMenu !== '';
?>
<?php if ($hasSheet): ?>
<div class="mnav" id="mnav" popover role="dialog" aria-labelledby="mnav-title" data-mnav>
  <div class="mnav__head">
    <p class="mnav__title" id="mnav-title"><?= e(lt('Menü')) ?></p>
    <button type="button" class="mnav__close" popovertarget="mnav" popovertargetaction="hide" autofocus><?= icon('x') ?><span class="sr-only"><?= e(lt('Menü schließen')) ?></span></button>
  </div>
  <?php if ($searchMenu !== ''): ?><div class="mnav__search"><?= $searchMenu ?></div><?php endif; ?>
  <?php if ($menu): ?><nav class="mnav__nav" aria-label="<?= e(lt('Hauptnavigation')) ?>"><?= galerie_nav_sheet($menu) ?></nav><?php endif; ?>
  <?php if ($cta): ?><a class="btn btn--primary mnav__cta" <?= galerie_link_attrs($cta['link']) ?>><?= e($cta['label']) ?></a><?php endif; ?>
  <?php if (($quick = galerie_quick_contact('quick mnav__quick')) !== ''): ?><div class="mnav__block"><p class="mnav__label"><?= e(lt('Direkter Kontakt')) ?></p><?= $quick ?></div><?php endif; ?>
  <?php if ($langs): ?><div class="mnav__block"><p class="mnav__label" id="mnav-lang"><?= e(lt('Sprache')) ?></p><?= app()->theme->partial('langswitch', ['langs' => $langs, 'class' => 'langswitch--wide', 'full' => true]) ?></div><?php endif; ?>
</div>
<?php endif; ?>
