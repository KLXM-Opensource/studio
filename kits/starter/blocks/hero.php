<?php
/**
 * Einstieg (Hero) – die H1 der Seite. Varianten (theme.php § 8): center | split (Text + Bild) | search (Such-Einstieg).
 * $b = Core\Block (Abschnitts-Optionen, Variante, Bearbeiten), $d = Daten mit Standardwerten.
 * $b->edit('title') → im Bearbeiten-Modus direkt im Frontend editierbar; für Besucher ein leerer String.
 * Bild: eager (fetchpriority="high"), weil es meist das größte Element im sichtbaren Bereich ist (LCP).
 * @var \Core\Block $b  @var array $d
 */
use Core\Blocks\Hero;

// ---------------------------------------------------------------- Beispiel für eine eigene Variante: „search“
// Suchfeld des Kerns (Vorschläge beim Tippen lädt js/site.js beim ersten Fokus) + Vorschläge als Links darunter.
// Hero::search() liefert '' wenn die Website-Suche ausgeschaltet ist; Hero::chips() dann nur die direkten Links („Text | /link“).
// Aussehen: assets/css/hero-search.css – lädt nur auf Seiten mit dieser Variante (theme.php → conditional_css 'hero:search').
if ($b->variant() === 'search'):
    $form = Hero::search($d);
    $chips = Hero::chips($d);
?>
<div class="wrap hero hero--search">
  <?php if (filled($d['eyebrow'])): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= emphasis((string) $d['eyebrow']) ?></p><?php endif; ?>
  <h1 id="<?= e($b->titleId()) ?>"<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></h1>
  <?php if (filled($d['text']) || is_editing()): ?><p class="lead"<?= $b->edit('text') ?>><?= nl2br(emphasis((string) $d['text']), false) ?></p><?php endif; ?>
  <?= $form ?>
  <?php if ($chips): /* eigene Navigation mit Namen – Screenreader finden sie über die Landmarke */ ?>
  <nav class="hero__chips" aria-label="<?= e(lt('Häufig gesucht')) ?>">
    <ul role="list"><?php foreach ($chips as $c): ?><li><a href="<?= e($c['href']) ?>"><?= e($c['label']) ?></a></li><?php endforeach; ?></ul>
  </nav>
  <?php endif; ?>
  <?= starter_buttons($b) ?>
</div>
<?php return; endif;

// ---------------------------------------------------------------- center | split
$split = $b->variant() === 'split';
$image = $split ? img((int) $d['image'] ?: null, '(min-width: 1100px) 560px, 100vw', ['ratio' => '4:3', 'eager' => true]) : '';
?>
<div class="wrap hero<?= $image !== '' ? ' hero--split' : '' ?>">
  <div class="hero__text">
    <?php if (filled($d['eyebrow'])): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= emphasis((string) $d['eyebrow']) ?></p><?php endif; ?>
    <h1 id="<?= e($b->titleId()) ?>"<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></h1>
    <?php if (filled($d['text']) || is_editing()): ?><p class="lead"<?= $b->edit('text') ?>><?= nl2br(emphasis((string) $d['text']), false) ?></p><?php endif; ?>
    <?= starter_buttons($b) ?>
  </div>
  <?php if ($image !== ''): ?><div class="media r-4-3"><?= $image ?></div><?php endif; ?>
</div>
