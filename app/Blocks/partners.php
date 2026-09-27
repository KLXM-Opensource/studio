<?php
/**
 * Partner & Logos (Kern-Block, vom Kit überschreibbar: themes/{name}/blocks/partners.php).
 * Logos in gleich großen Kacheln, flächengleich skaliert (Core\Blocks\PartnerLogos::width → Klasse pl-w-{n}).
 * Details (Kurzinfo, Link) erst nach Klick: js/partners.mjs macht aus den Sprung-Links Schaltflächen
 * (aria-expanded/aria-controls) und zeigt die Angaben unter der Reihe oder im Dialog. Ohne JavaScript und im
 * Bearbeiten-Modus stehen alle Angaben als Liste unter den Logos.
 * Aussehen: resources/css/partners.css (Variablen --partners-*, Zuordnung je Kit über .cms-partners--t-{kit}).
 * @var \Core\Block $b  @var array $d
 */
use Core\Blocks\PartnerLogos as PL;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$editing = is_editing();
$ratio = array_key_exists((string) ($d['ratio'] ?? ''), PL::RATIOS) ? (string) $d['ratio'] : '3:2';
$size = in_array($d['size'] ?? '', ['s', 'm', 'l'], true) ? $d['size'] : 'm';
$cols = in_array((string) ($d['columns'] ?? ''), ['3', '4', '5', '6'], true) ? (string) $d['columns'] : '5';
$tile = in_array($d['tile'] ?? '', ['soft', 'light', 'none'], true) ? $d['tile'] : 'soft';
$invert = in_array($d['invert'] ?? '', ['marked', 'all', 'off'], true) ? $d['invert'] : 'marked';
$mode = in_array($d['details'] ?? '', ['inline', 'dialog', 'off'], true) ? $d['details'] : 'inline';
$sort = (string) ($d['sort'] ?? 'manual');
$items = PL::items($d);
$group = !empty($d['group']) && $sort === 'category';
$title = trim((string) ($d['title'] ?? ''));
$onDark = $b->dark();
$canInvert = $invert !== 'off' && $tile !== 'light';
$uid = $b->domId();
$sizes = ['3' => '(min-width: 1080px) 300px, (min-width: 640px) 30vw, 45vw', '4' => '(min-width: 1080px) 240px, (min-width: 640px) 30vw, 45vw',
    '5' => '(min-width: 1080px) 200px, (min-width: 640px) 30vw, 45vw', '6' => '(min-width: 1080px) 170px, (min-width: 640px) 30vw, 45vw'][$cols];
$hGroup = $title !== '' ? 'h3' : 'h2';
$hName = $group ? ($title !== '' ? 'h4' : 'h3') : ($title !== '' ? 'h3' : 'h2');

$withDetails = fn(array $it): bool => $mode !== 'off' && ($it['info'] !== '' || $it['link'] !== '');
$anyDetails = (bool) array_filter($items, $withDetails);
$js = !$editing && $items && ($anyDetails || $sort === 'random' || $canInvert);

$cls = ['cms-partners', 'cms-partners--t-' . app()->theme->name, 'cms-partners--r-' . str_replace(':', '-', $ratio), 'cms-partners--c' . $cols,
    'cms-partners--tile-' . $tile];
if (!empty($d['gray'])) $cls[] = 'cms-partners--gray';
if ($onDark) $cls[] = 'cms-partners--on-dark';
if ($canInvert) $cls[] = 'cms-partners--invert';
$attrs = $js ? ' data-cms-partners="' . e($mode) . '" data-close="' . e(lt('Schließen')) . '"' . ($sort === 'random' ? ' data-shuffle' : '') . ($canInvert ? ' data-invert' : '') : '';

/** Logo in der Kachel: flächengleich skaliert – ohne Datei der Name als Schriftzug */
$mark = function (array $it) use ($ratio, $size, $sizes, $invert): string {
    $m = $it['media'];
    if (!$m) return '<span class="cms-partners__word">' . e($it['name']) . '</span>';
    $mono = $invert === 'all' || ($invert === 'marked' && $it['mono']);
    $w = PL::width((int) $m['width'], (int) $m['height'], $ratio, $size, (int) $it['adjust']);
    return '<span class="cms-partners__mark pl-w-' . $w . ($mono ? ' is-mono' : '') . '">'
        . \Core\Media::pictureOf($m, $sizes, ['alt' => $it['name'], 'fit' => false]) . '</span>';
};
$pid = fn(array $it): string => $uid . '-p-' . preg_replace('~[^a-z0-9]~i', '', $it['key']);
?>
<div class="<?= e(trim($wrap . ' cms-partners-wrap')) ?>">
  <?= \Core\MediaBlocks::head($b) ?>
  <?php if ($items): ?>
  <div class="<?= e(implode(' ', $cls)) ?>"<?= $attrs ?>>
    <?php foreach (($group ? PL::groups($items) : ['' => $items]) as $cat => $list): ?>
    <?php if ($group && $cat !== ''): ?><<?= $hGroup ?> class="cms-partners__cat"><?= e((string) $cat) ?></<?= $hGroup ?>><?php endif; ?>
    <ul class="cms-partners__grid" role="list"<?= $title !== '' && !$group ? ' aria-labelledby="' . e($b->titleId()) . '"' : ' aria-label="' . e($group && $cat !== '' ? (string) $cat : lt('Partner')) . '"' ?>>
      <?php foreach ($list as $it):
          $inner = $mark($it);
          $link = $it['link'] !== '' ? link_href($it['link']) : '';
      ?>
      <li class="cms-partners__item">
        <?php if (!$editing && $withDetails($it)): ?>
        <a class="cms-partners__cell" href="#<?= e($pid($it)) ?>" data-partner="<?= e($pid($it)) ?>"><?= $inner ?></a>
        <?php elseif (!$editing && $mode === 'off' && $link !== '' && $link !== '#'): ?>
        <a class="cms-partners__cell" href="<?= e($link) ?>"<?= ext_attrs($link) ?>><?= $inner ?><?= is_external($link) ? '<span class="cms-partners__sr"> ' . e(lt('(öffnet in neuem Tab)')) . '</span>' : '' ?></a>
        <?php else: ?>
        <div class="cms-partners__cell"><?= $inner ?></div>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endforeach; ?>
    <?php if ($anyDetails || ($editing && $mode !== 'off')): ?>
    <div class="cms-partners__details" data-partners-store>
      <?php foreach ($items as $it): if (!$withDetails($it) && !($editing && $it['path'])) continue;
          $link = $it['link'] !== '' ? link_href($it['link']) : '';
          $p = $it['path'];
      ?>
      <div class="cms-partners__detail" id="<?= e($pid($it)) ?>">
        <?php if ($it['media']): ?><span class="cms-partners__dlogo<?= $invert === 'all' || ($invert === 'marked' && $it['mono']) ? ' is-mono' : '' ?>"><?= \Core\Media::pictureOf($it['media'], '120px', ['alt' => '', 'fit' => false]) ?></span><?php endif; ?>
        <div class="cms-partners__dbody">
          <<?= $hName ?> class="cms-partners__name" id="<?= e($pid($it)) ?>-name"<?= $p ? $b->edit("$p.name") : '' ?>><?= e($it['name']) ?></<?= $hName ?>>
          <?php if ($it['category'] !== '' && !$group): ?><p class="cms-partners__tag"<?= $p ? $b->edit("$p.category") : '' ?>><?= e($it['category']) ?></p><?php endif; ?>
          <?php if ($it['info'] !== '' || ($editing && $p)): ?><div class="cms-partners__info"<?= $p ? $b->edit("$p.info", 'inline') : '' ?>><?= $it['info'] ?></div><?php endif; ?>
          <?php if ($editing && $p): ?>
          <p class="cms-partners__link"><span<?= $b->edit("$p.link_label") ?>><?= e(PL::linkLabel($it)) ?></span></p>
          <?php elseif ($link !== '' && $link !== '#'): ?>
          <p class="cms-partners__link"><a href="<?= e($link) ?>"<?= ext_attrs($link) ?>><span><?= e(PL::linkLabel($it)) ?></span><?php if (is_external($link)): ?><svg class="cms-partners__ext" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><path d="M6 3h7v7M13 3 4 12" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span class="cms-partners__sr"> <?= e(lt('(öffnet in neuem Tab)')) ?></span><?php endif; ?></a></p>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($js && !defined('CMS_PARTNERS_JS')): define('CMS_PARTNERS_JS', true); ?>
  <script type="module" src="<?= e(asset('js/partners.mjs')) ?>"></script>
  <?php endif; ?>
  <?php elseif ($editing): ?>
  <p class="cms-empty-hint"><?= e(($d['source'] ?? 'manual') === 'table'
      ? (!empty($d['table']) ? __('Keine veröffentlichten Einträge mit diesen Einstellungen.') : __('Bitte in der Seitenleiste eine Tabelle wählen.'))
      : __('Noch keine Logos – in der Seitenleiste hinzufügen.')) ?></p>
  <?php endif; ?>
</div>
