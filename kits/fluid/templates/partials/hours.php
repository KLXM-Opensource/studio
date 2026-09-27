<?php /** Öffnungszeiten als Beschreibungsliste. @var list<array{days: string, time: string}> $hours */ ?>
<dl class="hours">
  <?php foreach ($hours as $h): ?><div class="hours__row"><dt><?= e($h['days']) ?></dt><dd><?= e($h['time']) ?></dd></div><?php endforeach; ?>
</dl>
