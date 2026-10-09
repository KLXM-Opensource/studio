<?php
/**
 * Werke (Tabelle „Werke“) mit Werkangaben, Verfügbarkeit und Preis bzw. „Preis auf Anfrage“.
 * grid     auto-fill-Raster; jedes Werk im eigenen Format, eingepasst und unten bündig (wie an einer Wand)
 * masonry  CSS-Spalten (column-width) – Originalformate ohne Lücken
 * salon    dichtes Raster (grid-auto-flow: dense) mit wechselnden Spannweiten ab 40 rem Blockbreite – Salonhängung
 * room     Viewing Room: ein Werk je Bildschirm (scroll-snap, „proximity“), Angaben und Anfrage daneben (Switcher)
 * Quelle „automatisch“: Detailseite Künstler → dessen Werke; Detailseite Ausstellung → gezeigte Werke; Detailseite Werk →
 * weitere Werke derselben Person; sonst alle. Filter (Künstler, Verfügbarkeit): js/gallery.js – ohne JavaScript alle sichtbar.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;

$v = $b->variant() ?: 'grid';
$t = galerie_table('works');
if (!$t) {
    if (is_editing()) echo '<div class="wrap"><p class="empty-hint">Die Tabelle „Werke“ fehlt (Website → Galerie → Datentabellen).</p></div>';
    return;
}
$src = (string) ($d['source'] ?? 'auto');
$ctx = app()->entry;
$role = null;
if ($ctx) foreach (['artists', 'exhibitions', 'works'] as $r) if (($rt = galerie_table($r)) && $rt['handle'] === $ctx['table']['handle']) $role = $r;
$o = ['limit' => max(0, (int) ($d['limit'] ?? 0))];
$byArtist = false;
$none = false;
if ($src === 'artist' || ($src === 'auto' && $role === 'artists')) {
    $a = $src === 'artist' ? galerie_entry_from('artists', (string) ($d['ref'] ?? '')) : $ctx['entry'];
    if ($a) { $o['artist'] = (int) $a['id']; $byArtist = true; } else $none = true;
} elseif ($src === 'exhibition' || ($src === 'auto' && $role === 'exhibitions')) {
    $x = $src === 'exhibition' ? galerie_entry_from('exhibitions', (string) ($d['ref'] ?? '')) : $ctx['entry'];
    $o['ids'] = $x ? (array) ($x['werke'] ?? []) : [];
    if (!$o['ids']) $none = true;
} elseif ($src === 'auto' && $role === 'works') {
    $o['artist'] = (int) ($ctx['entry']['kuenstler'] ?? 0);
    $o['exclude'] = (int) $ctx['entry']['id'];
    $byArtist = $o['artist'] > 0;
    if (!$byArtist) $none = true;
}
if (!empty($d['only_available'])) $o['availability'] = ['verfuegbar'];
$works = $none ? [] : galerie_works($o);
$showArtist = !empty($d['show_artist']) && !$byArtist;
$enquiry = !empty($d['enquiry']);
$link = !empty($d['link_detail']);
$htag = galerie_htag($d);
$filters = !empty($d['filters']) && $v !== 'room' && count($works) > 1;
$id = $b->domId();
?>
<div class="wrap">
  <?= galerie_head($b) ?>
  <?= galerie_new_work_drop() ?>
  <?php if (!$works): ?>
    <?php if (is_editing()): ?><p class="empty-hint">Keine passenden Werke (Quelle: <?= e($src) ?>). Auf Detailseiten erscheinen hier automatisch die passenden Werke.</p><?php endif; ?>
  <?php elseif ($v === 'room'): ?>
  <ol class="vr" role="list" aria-label="<?= e(trim(strip_emphasis((string) ($d['title'] ?? ''))) ?: lt('Viewing Room')) ?>">
    <?php foreach ($works as $i => $w):
        $href = $link ? Entries::href($t, $w) : null;
        $enq = $enquiry && ($w['verfuegbarkeit'] ?? '') !== 'verkauft' ? galerie_enquiry_href($w) : null;
        $desc = trim(strip_tags((string) ($w['beschreibung'] ?? '')));
    ?>
    <li class="vr__item"><?= edit_link('entry:' . $t['handle'] . ':' . $w['id'], Entries::title($t, $w)) ?>
      <div class="vr__art"><?= galerie_art(!empty($w['bild']) ? (int) $w['bild'] : null, '(min-width: 1080px) 62vw, 100vw', ['eager' => $i === 0 && $b->prev === null]) ?></div>
      <div class="vr__info">
        <p class="vr__count"><?= e(lt('{n} von {total}', ['n' => $i + 1, 'total' => count($works)])) ?></p>
        <?= galerie_caption($w, ['tag' => 'div', 'full' => true, 'artist' => $showArtist || $byArtist, 'htag' => $htag]) ?>
        <?php if ($desc !== ''): ?><p class="vr__desc"><?= e(fmt()->excerpt($desc, 320, false)) ?></p><?php endif; ?>
        <div class="vr__actions">
          <?php if ($enq): ?><a class="btn btn--primary" href="<?= e($enq) ?>"><?= e(galerie_enquiry_label()) ?><span class="sr-only">: <?= e(galerie_work_label($w)) ?></span></a><?php endif; ?>
          <?php if ($href): ?><a class="more" href="<?= e($href) ?>"><?= e(lt('Details')) ?><?= icon('arrow-right', ['class' => 'more__ico']) ?><span class="sr-only">: <?= e(galerie_work_label($w)) ?></span></a><?php endif; ?>
        </div>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php else: ?>
    <?php if ($filters):
        $artists = [];
        foreach ($works as $w) if (!empty($w['kuenstler'])) $artists[(int) $w['kuenstler']] = true;
        $artistRows = galerie_by_ids('artists', array_keys($artists));
        $at = galerie_table('artists');
    ?>
    <div class="awf" data-aw-filter="<?= e($id) ?>" hidden>
      <?php if (count($artistRows) > 1 && $at): ?>
      <div class="awf__group" role="group" aria-label="<?= e(lt('Nach Künstler filtern')) ?>">
        <button type="button" class="awf__btn" data-f="artist" data-v="" aria-pressed="true"><?= e(lt('Alle Künstler')) ?></button>
        <?php foreach ($artistRows as $aid => $ar): ?><button type="button" class="awf__btn" data-f="artist" data-v="<?= (int) $aid ?>" aria-pressed="false"><?= e(Entries::title($at, $ar)) ?></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="awf__group" role="group" aria-label="<?= e(lt('Nach Verfügbarkeit filtern')) ?>">
        <button type="button" class="awf__btn" data-f="avail" data-v="" aria-pressed="true"><?= e(lt('Alle Werke')) ?></button>
        <button type="button" class="awf__btn" data-f="avail" data-v="verfuegbar" aria-pressed="false"><?= e(lt('Nur verfügbare')) ?></button>
      </div>
      <p class="awf__count" aria-live="polite" data-aw-count data-one="<?= e(lt('1 Werk')) ?>" data-many="<?= e(lt('{n} Werke')) ?>"></p>
    </div>
    <?php endif; ?>
  <ul class="aws aws--<?= e($v) ?><?= $v !== 'salon' ? ' ' . e(galerie_min($d)) : '' ?>" role="list" id="<?= e($id) ?>-works">
    <?php foreach ($works as $i => $w):
        $sizes = match ($v) { 'salon' => $i % 5 === 0 ? '(min-width: 1080px) 640px, 100vw' : '(min-width: 1080px) 360px, 50vw', default => '(min-width: 1080px) 420px, (min-width: 640px) 50vw, 100vw' };
        $attrs = ' data-artist="' . (int) ($w['kuenstler'] ?? 0) . '" data-avail="' . e((string) ($w['verfuegbarkeit'] ?? '')) . '"';
    ?>
    <?= galerie_work_card($w, ['sizes' => $sizes, 'artist' => $showArtist, 'link' => $link, 'enquiry' => $enquiry, 'attrs' => $attrs, 'htag' => $htag,
        'class' => $v === 'salon' ? 'aw--s' . ($i % 5) : '']) ?>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if (trim((string) ($d['more_label'] ?? '')) !== '' && trim((string) ($d['more_link'] ?? '')) !== ''): ?>
  <p class="more-row"><a class="btn btn--secondary" <?= galerie_link_attrs($d['more_link']) ?>><span<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></span></a></p>
  <?php endif; ?>
</div>
