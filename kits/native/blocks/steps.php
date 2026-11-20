<?php
/**
 * Ablauf / Zeitleiste im Kit „NativeCSS“: „Zeitleiste“ mit .ncss-timeline von NativeCSS (Datum, Markierung als Nummer oder
 * Symbol des Schritts, ab ~40 rem Containerbreite abwechselnd links/rechts). „Nummerierte Schritte“ und „Prozess mit Pfeilen“
 * kommen aus dem Kit fluid. Optionen des Blocks: „Kreise“ → gefüllt/zart/Rand bzw. „Kleiner Punkt“ ohne Nummer; „Farben“ →
 * Akzent, abwechselnd oder neutral; „Animation“ → Einträge einblenden (wie die Website), „Linie wächst“ → .ncss-reveal (Wischen
 * von oben) an der Zeitleiste, „Keine“ ohne. Bewegung reduzieren: keine Animation.
 * @var \Core\Block $b  @var array $d
 */
if ($b->variant() !== 'timeline') {
    require native_parent_view('steps');
    return;
}
native_mark('steps:@timeline');
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['title'] ?? '')) !== ''));
$tag = fluid_htag($d);
$pick = fn(string $k, array $ok, string $def) => in_array($d[$k] ?? '', $ok, true) ? (string) $d[$k] : $def;
$marker = $pick('marker', ['filled', 'soft', 'ring', 'dot'], 'soft');
$colors = $pick('colors', ['alternate', 'accent', 'neutral'], 'accent');
$motion = $pick('motion', ['sequence', 'draw', 'none'], 'auto');
$draw = $motion === 'draw' && native_motion();
if ($draw) native_mark('@reveal');
?>
<div class="wrap nv-timeline">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <ol class="ncss-timeline ncss-timeline--alternate nv-tl--m-<?= e($marker) ?> nv-tl--c-<?= e($colors) ?><?= $draw ? ' ncss-reveal ncss-reveal--wipe-down' : '' ?>" role="list"<?= $draw ? ' data-ncss-reveal' : '' ?>>
    <?php foreach ($items as $i => $it): ?>
    <li class="ncss-timeline-item"<?= in_array($motion, ['auto', 'sequence'], true) ? ' data-reveal' : '' ?>>
      <span class="ncss-timeline-marker" aria-hidden="true"><?= $marker === 'dot' ? '' : (!empty($it['icon']) ? icon((string) $it['icon']) : '<span class="nv-tl__num">' . ($i + 1) . '</span>') ?></span>
      <div class="ncss-timeline-content">
        <?php if (trim((string) ($it['meta'] ?? '')) !== ''): ?><p class="ncss-timeline-date"<?= $b->edit("items.$i.meta") ?>><?= e($it['meta']) ?></p><?php endif; ?>
        <<?= $tag ?> class="ncss-timeline-title"><span class="sr-only"><?= e(lt('Schritt {nr}:', ['nr' => $i + 1])) ?> </span><span<?= $b->edit("items.$i.title") ?>><?= fluid_title((string) $it['title']) ?></span></<?= $tag ?>>
        <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p<?= $b->edit("items.$i.text") ?>><?= nl2br(fluid_title((string) $it['text']), false) ?></p><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Schritte – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
