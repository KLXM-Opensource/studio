<?php /** Öffnungszeiten als Beschreibungsliste. @var array $hours (basis_hours()) */ ?>
<dl class="hours">
  <?php foreach ($hours as $h): ?><div class="hours__row"><dt><?= e($h['days']) ?></dt><dd><?= e($h['time']) ?></dd></div><?php endforeach; ?>
</dl>
