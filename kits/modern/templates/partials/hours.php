<?php
/**
 * Öffnungszeiten als Beschreibungsliste. data-dows/data-slots: site.js hebt den heutigen Tag hervor und schaltet die
 * Anzeige „Jetzt geöffnet / Geschlossen“ (im Browser berechnet – der Seiten-Cache liefert so nie einen alten Zustand).
 * @var list<array{days: string, time: string, dows: string, slots: string}> $hours  @var ?bool $state
 */
?>
<?php if (!empty($state)): ?><p class="openstate" data-openstate data-open="<?= e(lt('Jetzt geöffnet')) ?>" data-closed="<?= e(lt('Zurzeit geschlossen')) ?>" hidden><span class="openstate__led" aria-hidden="true"></span><span data-openstate-text></span></p><?php endif; ?>
<dl class="hours" data-hours>
  <?php foreach ($hours as $h): ?><div class="hours__row" data-dows="<?= e($h['dows'] ?? '') ?>" data-slots="<?= e($h['slots'] ?? '') ?>"><dt><?= e($h['days']) ?></dt><dd><?= e($h['time']) ?></dd></div><?php endforeach; ?>
</dl>
