<?php
/**
 * „Jetzt geöffnet“ (Infoleiste, Mobilmenü): site.js berechnet den Status im Browser aus den Öffnungszeiten (Zeitzone der
 * Website) – so bleibt der Seiten-Cache gültig. Ohne JavaScript steht dort die erste Zeile der Öffnungszeiten.
 */
$hours = basis_hours();
if (!$hours) return;
$days = array_map(fn($d) => basis_weekday($d), range(0, 6));
?>
<span class="openstate" data-hours="<?= e(json_encode(basis_hours_ranges())) ?>" data-tz="<?= e(date_default_timezone_get()) ?>" data-days="<?= e(json_encode($days, JSON_UNESCAPED_UNICODE)) ?>"
  data-t-open="<?= e(lt('Jetzt geöffnet')) ?>" data-t-until="<?= e(lt('bis {zeit} Uhr')) ?>" data-t-closed="<?= e(lt('Geschlossen')) ?>"
  data-t-opens="<?= e(lt('öffnet {tag} um {zeit} Uhr')) ?>" data-t-today="<?= e(lt('heute')) ?>" data-t-tomorrow="<?= e(lt('morgen')) ?>">
  <span class="openstate__dot" aria-hidden="true"></span><span class="openstate__text"><?= e($hours[0]['days'] . ': ' . $hours[0]['time']) ?></span></span>
