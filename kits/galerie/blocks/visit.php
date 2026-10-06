<?php
/**
 * Besuch: „Heute geöffnet / geschlossen“ (je Tag berechnet, inkl. Ausnahmen), abweichende Öffnungszeiten der nächsten Wochen,
 * Orte (Hauptort aus Stammdaten + weitere Orte aus Website → Galerie) mit Adresse, Zeiten und Kartenlink, Eintritt, Termine.
 * columns: Orte im auto-fit-Raster · split: Überschrift links, Angaben rechts (Sidebar-Muster). Alles zentral gepflegt.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'columns';
$venues = galerie_venues();
if (empty($d['show_venues'])) $venues = array_slice($venues, 0, 1);
$today = !empty($d['show_today']) ? galerie_today_status() : null;
$exceptions = !empty($d['show_exceptions']) ? galerie_exceptions(60) : [];
$admission = trim((string) setting('admission'));
$appointment = trim((string) setting('appointment_note'));
$note = trim((string) ($d['note'] ?? ''));
$c = $b->central();
$lv = trim((string) ($d['title'] ?? '')) !== '' ? 'h3' : 'h2';
?>
<div class="wrap vis vis--<?= e($v) ?>">
  <div class="vis__head">
    <?= galerie_head($b) ?>
    <?php if ($today): ?><p class="vis__today vis__today--<?= e($today[0]) ?>"<?= $c ?>><span class="vis__led" aria-hidden="true"></span><?= e($today[1]) ?></p><?php endif; ?>
    <?php if ($admission !== '' || $appointment !== ''): ?>
    <ul class="vis__facts" role="list"<?= $c ?>>
      <?php if ($admission !== ''): ?><li><?= e($admission) ?></li><?php endif; ?>
      <?php if ($appointment !== ''): ?><li><?= e($appointment) ?></li><?php endif; ?>
    </ul>
    <?php endif; ?>
    <?= galerie_buttons($b, 'vis__actions') ?>
  </div>
  <div class="vis__body">
    <?php if ($exceptions): ?>
    <div class="vis__exc" role="note"<?= $c ?>>
      <<?= $lv ?> class="vis__h"><?= e(lt('Abweichende Öffnungszeiten')) ?></<?= $lv ?>>
      <ul class="vis__exclist" role="list">
        <?php foreach ($exceptions as $x): ?>
        <li<?= $x['today'] ? ' class="is-today"' : '' ?>><span class="vis__excdate"><?= e($x['dates']) ?></span>
          <span class="vis__exctext"><?= e(implode(' · ', array_filter([$x['text'], $x['closed'] ? lt('geschlossen') : ($x['time'] !== '' ? $x['time'] : lt('geöffnet'))]))) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
    <?php if ($venues): ?>
    <div class="vis__venues"<?= $c ?>>
      <?php foreach ($venues as $vn): ?>
      <section class="vis__venue">
        <<?= $lv ?> class="vis__h"><?= e($vn['name']) ?></<?= $lv ?>>
        <?php if ($vn['lines']): ?><address class="vis__addr"><?= implode('<br>', array_map('e', $vn['lines'])) ?></address><?php endif; ?>
        <?php if ($vn['hours']): ?><?= app()->theme->partial('hours', ['hours' => $vn['hours']]) ?><?php endif; ?>
        <?php if ($vn['hours_text']): ?><ul class="vis__times" role="list"><?php foreach ($vn['hours_text'] as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <?php if ($vn['note'] !== ''): ?><p class="vis__note"><?= e($vn['note']) ?></p><?php endif; ?>
        <?php if (!empty($d['show_map']) && $vn['map'] !== ''): ?><p class="vis__map"><a class="more" href="<?= e($vn['map']) ?>" rel="noopener" target="_blank"><?= e(lt('Auf der Karte')) ?><?= icon('arrow-up-right', ['class' => 'more__ico']) ?><span class="sr-only"> – <?= e($vn['name']) ?> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a></p><?php endif; ?>
      </section>
      <?php endforeach; ?>
    </div>
    <?php elseif (is_editing()): ?><p class="empty-hint">Adresse und Öffnungszeiten unter Website → Stammdaten eintragen; weitere Orte unter Website → Galerie.</p><?php endif; ?>
    <?php if ($note !== '' || is_editing()): ?><p class="vis__extra"<?= $b->edit('note') ?>><?= e($note) ?></p><?php endif; ?>
  </div>
</div>
