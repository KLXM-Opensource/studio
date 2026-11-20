<?php
/**
 * Text + Bild im Kit „NativeCSS“ (.ncss-split: nebeneinander, stapelt sich per Container Query – Bild links bzw. rechts allein
 * über die Reihenfolge im Markup). auto wechselt die Seite in einer Folge von „Text + Bild“-Blöcken wie bei fluid.
 *   morph  „Morphing beim Scrollen“ (nur dieses Kit, theme.php → block_extend): .ncss-morph von NativeCSS – das Bild startet
 *          groß und rückt beim Scrollen zur Seite (nur CSS, Scroll-Animationen); bei „Bewegung reduzieren“ statisch
 *          nebeneinander. Beim Bearbeiten wie „Bild rechts“ (die hohe Scroll-Strecke stört dort nur).
 * „split“ (randlos 50/50) und „overlap“ (Karte über großem Bild) kommen aus dem Kit fluid.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'auto';
if ($v === 'morph' && !is_editing()) {
    $mpic = img(!empty($d['image']) ? (int) $d['image'] : null, '(min-width: 1080px) 1200px, 100vw', ['ratio' => (string) ($d['ratio'] ?? '') ?: '4:3']);
    if ($mpic !== '') {
        native_mark('@morph', 'media_text:@morph');
        echo '<div class="wrap nv-morph"><div class="ncss-morph ncss-morph--card"><div class="ncss-morph-stage">'
            . '<div class="ncss-morph-media">' . $mpic . '</div>'
            . '<div class="ncss-morph-content">' . fluid_head($b, 'nv-mt__head')
            . (trim(strip_tags((string) $d['text'])) !== '' ? '<div class="prose">' . rich((string) $d['text']) . '</div>' : '')
            . fluid_checks(fluid_lines($d['list'] ?? '')) . fluid_buttons($b) . '</div>'
            . '</div></div></div>';
        return;
    }
}
if ($v === 'morph') $v = 'right';
if (!in_array($v, ['auto', 'right', 'left'], true)) {
    require native_parent_view('media_text');
    return;
}
native_mark('@nv-media_text');
$ratio = (string) ($d['ratio'] ?? '') ?: '4:3';
$side = $v;
if ($v === 'auto') {
    $n = 0;
    for ($p = $b->prev; $p && $p->type === 'media_text' && ($p->data['variant'] ?? 'auto') === $v; $p = $p->prev) $n++;
    $side = $n % 2 ? 'left' : 'right';
}
$body = fluid_head($b, 'nv-mt__head')
    . ((trim(strip_tags((string) $d['text'])) !== '' || is_editing()) ? '<div class="prose"' . $b->edit('text', 'rich') . '>' . rich((string) $d['text']) . '</div>' : '')
    . fluid_checks(fluid_lines($d['list'] ?? ''))
    . fluid_buttons($b);
$pic = fluid_image(!empty($d['image']) ? (int) $d['image'] : null, '(min-width: 1080px) 600px, 100vw', $ratio);
$media = $pic !== '' ? '<div class="ncss-split-media">' . $pic . '</div>' : '';
$split = in_array($d['split'] ?? 'even', ['text', 'media', 'mediaxl'], true) ? ' nv-mt--s-' . $d['split'] : '';
?>
<div class="wrap">
  <div class="ncss-split-container">
    <div class="ncss-split nv-mt nv-mt--<?= e($side) ?><?= e($split) ?>">
      <?= $side === 'left' ? $media : '' ?>
      <div class="ncss-split-content"><?= $body ?></div>
      <?= $side === 'right' ? $media : '' ?>
    </div>
  </div>
</div>
