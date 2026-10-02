<?php
/**
 * Live-Text / Ticker (Kern-Block, vom Kit überschreibbar: kits/{name}/blocks/live_text.php).
 * Quelle „hier schreiben“: Meldungen im Block (Kanal page:{id}, aktualisiert beim Veröffentlichen; zuletzt angefügt = neueste)
 * oder „Datentabelle“: veröffentlichte Einträge, neueste zuerst (Kanal data:{tabelle}, bei geteilten Tabellen zusätzlich
 * g:shared:{schlüssel}). Darstellung „Ticker“ oder „Nur die aktuelle Meldung“.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;
use Core\Data\Tables;
use Core\Live;
use Core\MediaBlocks;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$source = (string) ($d['source'] ?? (!empty($d['table']) ? 'table' : 'manual'));   // ältere Blöcke ohne „Quelle“: Tabelle
$mode = ($d['mode'] ?? 'ticker') === 'latest' ? 'latest' : 'ticker';
$limit = $mode === 'latest' ? 1 : max(0, (int) ($d['limit'] ?? 20));
// Einheitliche Zeilen: id (für „neu“), title, html (Text), when, url, after (Stift), title_attr/text_attr (Bearbeiten im Text)
$rows = [];
$channels = [];
$t = null;
if ($source === 'table') {
    $t = !empty($d['table']) ? Tables::findContent((string) $d['table']) : null;
    if ($t) {
        $o = ['status' => 'published', 'sort' => 'published_at', 'dir' => 'desc'];
        if ($limit) $o['limit'] = $limit;
        $tf = (string) ($d['text_field'] ?? '');
        $textField = str_starts_with($tf, $t['handle'] . '.') ? substr($tf, strlen($t['handle']) + 1) : (string) ($t['settings']['description_field'] ?? '');
        if ($textField !== '' && !Tables::field($t, $textField)) $textField = '';
        foreach (Entries::query($t, $o) as $e) {
            $rows[] = [
                'id' => (int) $e['id'] . '-' . substr(md5((string) ($e['updated_at'] ?? '')), 0, 6),
                'title' => Entries::title($t, $e), 'html' => $textField !== '' ? Entries::html($t, $e, $textField) : '',
                'when' => (string) ($e['published_at'] ?: $e['created_at']),
                'url' => !empty($d['link_detail']) ? Entries::href($t, $e) : null,
                'after' => \Core\Data\EntryEdit::button($t, $e), 'title_attr' => '', 'text_attr' => '',
            ];
        }
        $channels[] = 'data:' . $t['handle'];
        if (Tables::isShared($t)) $channels[] = 'g:shared:' . $t['shared']['key'];
    }
} else {
    foreach ((array) ($d['items'] ?? []) as $i => $it) {
        if (!is_array($it) || (trim((string) ($it['title'] ?? '')) === '' && trim(strip_tags((string) ($it['text'] ?? ''))) === '')) continue;
        $rows[] = [
            'id' => 'm' . substr(md5(($it['time'] ?? '') . '|' . ($it['title'] ?? '') . '|' . ($it['text'] ?? '')), 0, 10),
            'title' => trim((string) ($it['title'] ?? '')), 'html' => inline((string) ($it['text'] ?? '')),
            'when' => trim((string) ($it['time'] ?? '')), 'url' => null, 'after' => '',
            'title_attr' => $b->edit("items.$i.title"), 'text_attr' => $b->edit("items.$i.text", 'inline'),
        ];
    }
    $rows = array_reverse($rows);   // zuletzt angefügt = neueste oben
    if ($limit) $rows = array_slice($rows, 0, $limit);
    $page = app()->currentPage;
    if (!empty($page['id'])) $channels[] = 'page:' . (int) $page['id'];
}
?>
<div class="<?= e($wrap) ?> cms-livetext cms-livetext--<?= e($mode) ?>"<?= Live::attrs($channels, Live::blockSrc($b)) ?>>
  <?= MediaBlocks::head($b) ?>
  <?= $channels ? Live::controls() : '' ?>
  <ol class="cms-livetext__list" role="list" data-live-list>
    <?php foreach ($rows as $r): $w = $r['when']; $ts = $w !== '' ? strtotime($w) : false; ?>
    <li class="cms-livetext__item" data-live-id="<?= e($r['id']) ?>">
      <?php if (!empty($d['show_time']) && $ts): ?><time class="cms-livetext__time" datetime="<?= e(date('c', $ts)) ?>"><?= e(date('Y-m-d', $ts) === date('Y-m-d') ? fmt()->time($w) : fmt()->datetime($w)) ?></time><?php endif; ?>
      <div class="cms-livetext__body">
        <?php if ($r['title'] !== ''): ?><p class="cms-livetext__title"><?php if ($r['url']): ?><a href="<?= e($r['url']) ?>"><?= emphasis($r['title']) ?></a><?php else: ?><span<?= $r['title_attr'] ?>><?= emphasis($r['title']) ?></span><?php endif; ?><?= $r['after'] ?></p><?php endif; ?>
        <?php if ($r['html'] !== ''): ?><div class="cms-livetext__text"<?= $r['text_attr'] ?>><?= $r['html'] ?></div><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
    <?php if (!$rows): ?><li class="cms-livetext__empty"><?= e(is_editing() && $source === 'table' && !$t ? 'Bitte in der Seitenleiste eine Tabelle wählen.' : (is_editing() && $source !== 'table' ? 'Noch keine Meldungen – in der Seitenleiste unter „Meldungen“ anfügen.' : (string) ($d['empty_text'] ?? ''))) ?></li><?php endif; ?>
  </ol>
  <?= $channels ? Live::assets() : '' ?>
</div>
