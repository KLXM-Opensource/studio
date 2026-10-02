<?php
/**
 * Live-Text / Ticker (Kern-Block, vom Kit überschreibbar: kits/{name}/blocks/live_text.php).
 * Veröffentlichte Einträge einer Datentabelle, neueste zuerst; Aktualisierung per Core\Live (Kanal data:{tabelle},
 * bei geteilten Tabellen zusätzlich g:shared:{schlüssel}). Darstellung „Ticker“ oder „Nur die aktuelle Meldung“.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;
use Core\Data\Tables;
use Core\Live;
use Core\MediaBlocks;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$t = !empty($d['table']) ? Tables::findContent((string) $d['table']) : null;
$mode = ($d['mode'] ?? 'ticker') === 'latest' ? 'latest' : 'ticker';
$limit = $mode === 'latest' ? 1 : max(0, (int) ($d['limit'] ?? 20));
$rows = [];
$channels = [];
if ($t) {
    $o = ['status' => 'published', 'sort' => 'published_at', 'dir' => 'desc'];
    if ($limit) $o['limit'] = $limit;
    $rows = Entries::query($t, $o);
    $channels[] = 'data:' . $t['handle'];
    if (Tables::isShared($t)) $channels[] = 'g:shared:' . $t['shared']['key'];
}
$textField = '';
if ($t) {
    $tf = (string) ($d['text_field'] ?? '');
    $textField = str_starts_with($tf, $t['handle'] . '.') ? substr($tf, strlen($t['handle']) + 1) : (string) ($t['settings']['description_field'] ?? '');
    if ($textField !== '' && !Tables::field($t, $textField)) $textField = '';
}
$when = fn(array $e) => (string) ($e['published_at'] ?: $e['created_at']);
?>
<div class="<?= e($wrap) ?> cms-livetext cms-livetext--<?= e($mode) ?>"<?= Live::attrs($channels, Live::blockSrc($b)) ?>>
  <?= MediaBlocks::head($b) ?>
  <?= $channels ? Live::controls() : '' ?>
  <ol class="cms-livetext__list" role="list" data-live-list>
    <?php foreach ($rows as $e):
        $url = !empty($d['link_detail']) ? Entries::href($t, $e) : null;
        $title = Entries::title($t, $e);
        $w = $when($e);
    ?>
    <li class="cms-livetext__item" data-live-id="<?= (int) $e['id'] ?>-<?= e(substr(md5((string) ($e['updated_at'] ?? '')), 0, 6)) ?>">
      <?php if (!empty($d['show_time']) && $w !== ''): ?><time class="cms-livetext__time" datetime="<?= e(date('c', strtotime($w) ?: time())) ?>"><?= e(date('Y-m-d', strtotime($w) ?: time()) === date('Y-m-d') ? fmt()->time($w) : fmt()->datetime($w)) ?></time><?php endif; ?>
      <div class="cms-livetext__body">
        <?php if ($title !== ''): ?><p class="cms-livetext__title"><?= $url ? '<a href="' . e($url) . '">' . emphasis($title) . '</a>' : emphasis($title) ?><?= \Core\Data\EntryEdit::button($t, $e) ?></p><?php endif; ?>
        <?php if ($textField !== '' && ($txt = Entries::html($t, $e, $textField)) !== ''): ?><div class="cms-livetext__text"><?= $txt ?></div><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
    <?php if (!$rows): ?><li class="cms-livetext__empty"><?= e($t ? (string) ($d['empty_text'] ?? '') : (is_editing() ? 'Bitte in der Seitenleiste eine Tabelle wählen.' : '')) ?></li><?php endif; ?>
  </ol>
  <?= $channels ? Live::assets() : '' ?>
</div>
