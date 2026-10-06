<?php
/**
 * Pakete / Vergleich: cards (Raster auto-fit, Hervorhebung) · table (Vergleichstabelle – scrollt bei wenig Platz waagerecht,
 * erste Spalte bleibt stehen; Paketnamen als Spaltenköpfe, Werte ja/nein als Symbol mit Text für Screenreader).
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() === 'table' ? 'table' : 'cards';
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['name'] ?? '')) !== ''));
$rows = array_values(array_filter((array) ($d['rows'] ?? []), fn($r) => trim((string) ($r['label'] ?? '')) !== ''));
$tag = foto_htag($d);
$yes = ['ja', 'yes', '✓', '✔', 'x', '+', 'inklusive', 'inkl.'];
$no = ['nein', 'no', '–', '-', '—', '', '0'];
$cell = function (string $val) use ($yes, $no): string {
    $k = mb_strtolower(trim($val));
    if (in_array($k, $yes, true)) return icon('check', ['class' => 'cmp__yes']) . '<span class="sr-only">' . e(lt('enthalten')) . '</span>';
    if (in_array($k, $no, true)) return '<span class="cmp__no" aria-hidden="true">–</span><span class="sr-only">' . e(lt('nicht enthalten')) . '</span>';
    return e(trim($val));
};
$button = function (array $it, int $i, string $class = '') use ($b): string {
    $label = trim((string) ($it['button_label'] ?? ''));
    $link = trim((string) ($it['button_link'] ?? ''));
    if ($label === '' || $link === '') return '';
    return '<a class="btn ' . (!empty($it['highlight']) ? 'btn--primary' : 'btn--secondary') . ($class !== '' ? ' ' . $class : '') . '" ' . foto_link_attrs($link) . '><span' . $b->edit("items.$i.button_label") . '>'
        . e($label) . '</span><span class="sr-only">: ' . e($it['name']) . '</span></a>';
};
?>
<div class="wrap">
  <?= foto_head($b, 'sec-head--center') ?>
  <?php if ($items && $v === 'table'): ?>
  <div class="cmp-wrap" role="region" tabindex="0" aria-labelledby="<?= e($b->titleId()) ?>">
    <table class="cmp">
      <thead>
        <tr><td class="cmp__corner"></td>
          <?php foreach ($items as $i => $it): ?>
          <th scope="col" class="cmp__plan<?= !empty($it['highlight']) ? ' is-hl' : '' ?>">
            <?php if (trim((string) ($it['badge'] ?? '')) !== ''): ?><span class="badge"<?= $b->edit("items.$i.badge") ?>><?= e($it['badge']) ?></span><?php endif; ?>
            <span class="cmp__name"<?= $b->edit("items.$i.name") ?>><?= e($it['name']) ?></span>
            <?php if (trim((string) ($it['price'] ?? '')) !== ''): ?><span class="cmp__price"><span<?= $b->edit("items.$i.price") ?>><?= e($it['price']) ?></span> <small<?= $b->edit("items.$i.period") ?>><?= e((string) ($it['period'] ?? '')) ?></small></span><?php endif; ?>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): $vals = preg_split('~\R~', (string) ($r['values'] ?? '')); ?>
        <tr><th scope="row"><?= e($r['label']) ?></th>
          <?php foreach ($items as $k => $it): ?><td class="<?= !empty($it['highlight']) ? 'is-hl' : '' ?>"><?= $cell((string) ($vals[$k] ?? '')) ?></td><?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td></td><?php foreach ($items as $i => $it): ?><td class="<?= !empty($it['highlight']) ? 'is-hl' : '' ?>"><?= $button($it, $i, 'btn--small') ?></td><?php endforeach; ?></tr></tfoot>
    </table>
  </div>
  <?php elseif ($items): ?>
  <ul class="grid min-m plans" role="list">
    <?php foreach ($items as $i => $it): $hl = !empty($it['highlight']); $list = foto_lines($it['features'] ?? ''); ?>
    <li class="plan card<?= $hl ? ' plan--hl' : '' ?>" data-reveal>
      <div class="plan__top">
        <<?= $tag ?> class="plan__name"<?= $b->edit("items.$i.name") ?>><?= e($it['name']) ?></<?= $tag ?>>
        <?php if (trim((string) ($it['badge'] ?? '')) !== ''): ?><p class="badge"<?= $b->edit("items.$i.badge") ?>><?= e($it['badge']) ?></p><?php endif; ?>
      </div>
      <?php if (trim((string) ($it['price'] ?? '')) !== ''): ?>
      <p class="plan__price"><span class="plan__amount"<?= $b->edit("items.$i.price") ?>><?= e($it['price']) ?></span>
        <?php if (trim((string) ($it['period'] ?? '')) !== ''): ?><span class="plan__period"<?= $b->edit("items.$i.period") ?>><?= e($it['period']) ?></span><?php endif; ?></p>
      <?php endif; ?>
      <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="plan__text"<?= $b->edit("items.$i.text") ?>><?= nl2br(e($it['text']), false) ?></p><?php endif; ?>
      <?= foto_checks($list, 'plan__list') ?>
      <?= $button($it, $i, 'plan__btn') ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Pakete – in der Seitenleiste hinzufügen.</p><?php endif; ?>
  <?php if (trim((string) ($d['note'] ?? '')) !== ''): ?><p class="plans__note"<?= $b->edit('note') ?>><?= e($d['note']) ?></p><?php endif; ?>
</div>
