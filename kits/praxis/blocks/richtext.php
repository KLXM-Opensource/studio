<?php /** Fließtext (Stellenanzeigen, Erläuterungen, Rechtstexte). @var \Core\Block $b  @var array $d */
$html = rich($d['text']);
// Leerer Block (kein Text, keine Überschrift): für Besucher nichts ausgeben – kein leerer Abschnitt mit Abstand
if (!is_editing() && trim(strip_tags($html)) === '' && trim((string) $d['title_strong']) === '') return;
// Leerzeilen direkt vor Zwischenüberschriften (<p><br></p>): die Überschrift bringt ihren Abstand selbst mit
$html = (string) preg_replace('~(?:<p>(?:\s|<br\s*/?>)*</p>\s*)+(?=<h[2-4][\s>])~i', '', $html);
$wide = ($d['width'] ?? 'text') === 'wide';
?>
<div class="wrap<?= $wide ? ' richtext--wide' : ' wrap--text' ?>">
  <?php if ($d['title_strong']): ?><?= praxis_heading($b, 'h2', 'h2 h2--m richtext__title') ?><?php endif; ?>
  <div class="prose prose--long"<?= $b->edit('text', 'rich') ?>><?= $html ?></div>
</div>
