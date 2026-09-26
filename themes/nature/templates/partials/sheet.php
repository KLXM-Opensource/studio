<?php
/**
 * Seitenblatt der Menü-Schaltfläche (HTML-popover, außerhalb des Inhalts, damit der Rest der Seite inert werden kann):
 * ein ruhiges Blatt mit den Seiten (Blattmarken). Ohne JavaScript bedienbar: Escape und Klick daneben schließen, Scrollen
 * gesperrt per :has(); Unterseiten als Akkordeon (<details name> – nur eins offen). site.js ergänzt Fokusfalle, inerten
 * Rest der Seite und den Fokus beim Öffnen/Schließen.
 */
$menu = nature_menu();
$langs = language_links();
$cta = nature_header_cta();
$searchMenu = \Core\Search\Search::form('menu');
$hasSheet = $menu || $langs || $cta || $searchMenu !== '';
?>
<?php if ($hasSheet): ?>
<div class="mnav" id="mnav" popover role="dialog" aria-labelledby="mnav-title" data-mnav>
  <div class="mnav__head">
    <p class="mnav__title" id="mnav-title"><span class="mnav__leaf" aria-hidden="true"></span><?= e(lt('Menü')) ?></p>
    <button type="button" class="mnav__close" popovertarget="mnav" popovertargetaction="hide"><?= icon('x') ?><span class="sr-only"><?= e(lt('Menü schließen')) ?></span></button>
  </div>
  <?php if ($searchMenu !== ''): ?><div class="mnav__search"><?= $searchMenu ?></div><?php endif; ?>
  <?php if ($menu): ?><nav class="mnav__nav" aria-label="<?= e(lt('Hauptnavigation')) ?>"><?= nature_nav_sheet($menu) ?></nav><?php endif; ?>
  <?php if ($cta): ?><a class="btn btn--primary mnav__cta" <?= nature_link_attrs($cta['link']) ?>><?= e($cta['label']) ?></a><?php endif; ?>
  <?php if (($quick = nature_quick_contact('quick mnav__quick')) !== ''): ?><div class="mnav__block"><p class="mnav__label"><?= e(lt('Direkter Kontakt')) ?></p><?= $quick ?></div><?php endif; ?>
  <?php if ($langs): ?><div class="mnav__block"><p class="mnav__label"><?= e(lt('Sprache')) ?></p><?= app()->theme->partial('langswitch', ['langs' => $langs, 'class' => 'langswitch--wide', 'full' => true]) ?></div><?php endif; ?>
</div>
<?php endif; ?>
