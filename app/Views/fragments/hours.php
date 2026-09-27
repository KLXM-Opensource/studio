<?php
/**
 * Kern-Fragment „hours“ (überschreibbar: kits/{kit}/fragments/hours.php): Öffnungszeiten als Beschreibungsliste.
 * data-dows/data-slots: Skripte des Kits können den heutigen Tag hervorheben und „Jetzt geöffnet / Geschlossen“ schalten
 * (im Browser berechnet – der Seiten-Cache liefert so nie einen alten Zustand); $state = Anzeige dafür ausgeben.
 * Klassen: .hours · .hours__row · .openstate (.openstate__led)
 * @var list<array{days: string, time: string, dows?: string, slots?: string}> $hours  @var ?bool $state
 */
?>
<?php if (!empty($state)): ?><p class="openstate" data-openstate data-open="<?= e(lt('Jetzt geöffnet')) ?>" data-closed="<?= e(lt('Zurzeit geschlossen')) ?>" hidden><span class="openstate__led" aria-hidden="true"></span><span data-openstate-text></span></p><?php endif; ?>
<dl class="hours" data-hours>
  <?php foreach ($hours ?? [] as $h): ?><div class="hours__row" data-dows="<?= e($h['dows'] ?? '') ?>" data-slots="<?= e($h['slots'] ?? '') ?>"><dt><?= e($h['days']) ?></dt><dd><?= e($h['time']) ?></dd></div><?php endforeach; ?>
</dl>
