<?php
/**
 * Werk (Detailseite der Tabelle „Werke“): Abbildung im Originalformat (vergrößerbar, weitere Ansichten – Lightbox des Kerns),
 * vollständige Werkangaben (Titel als H1), Verfügbarkeit, Preis, Anfrage, Beschreibung; darunter weitere Werke derselben Person.
 * Switcher: Bild ≥ 24 rem (wächst stärker), Angaben ≥ 18 rem – sonst untereinander. Angaben bleiben beim Scrollen stehen (sticky).
 * Strukturierte Daten: VisualArtwork (+ Offer bei sichtbarem Preis).
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;
use Core\Media;

$t = galerie_table('works');
$w = galerie_entry_from('works');
if (!$w && $t && is_editing()) $w = Entries::query($t, ['limit' => 1])[0] ?? null;
if (!$t || !$w) {
    if (is_editing()) echo '<div class="wrap"><p class="empty-hint">Vorschau erscheint, sobald es einen Eintrag in „Werke“ gibt. Dieser Block gehört auf die Detailseite der Tabelle.</p></div>';
    return;
}
$artistId = (int) ($w['kuenstler'] ?? 0);
$artist = $artistId ? (galerie_by_ids('artists', [$artistId])[$artistId] ?? null) : null;
$at = galerie_table('artists');
$artistHref = $artist && $at ? Entries::href($at, $artist) : null;
// Abbildung + weitere Ansichten: Bilder in die Lightbox, Videos (MP4, YouTube/Vimeo) als Player darunter
$images = $videos = [];
foreach (galerie_entry_media('works', $w) as $mid) {
    $mm = Media::find($mid);
    if (galerie_is_video($mm)) $videos[] = $mid; elseif ($mm && str_starts_with((string) $mm['mime'], 'image/')) $images[] = $mid;
}
$videoUrl = trim((string) ($w['video_url'] ?? ''));
$desc = trim((string) ($w['beschreibung'] ?? ''));
$enq = ($w['verfuegbarkeit'] ?? '') !== 'verkauft' ? galerie_enquiry_href($w) : null;
$back = trim((string) ($d['back_label'] ?? ''));
$more = !empty($d['show_more']) && $artistId ? galerie_works(['artist' => $artistId, 'exclude' => (int) $w['id'], 'limit' => max(1, (int) ($d['more_limit'] ?? 4))]) : [];
$price = galerie_price($w);

// Strukturierte Daten (schema.org VisualArtwork)
$node = ['@type' => 'VisualArtwork', 'name' => Entries::title($t, $w)];
if ($artist) $node['creator'] = ['@type' => 'Person', 'name' => (string) ($artist['name'] ?? '')] + ($artistHref ? ['url' => site_url() . $artistHref] : []);
if (trim((string) ($w['jahr'] ?? '')) !== '') $node['dateCreated'] = trim((string) $w['jahr']);
if (trim((string) ($w['technik'] ?? '')) !== '') $node['artMedium'] = trim((string) $w['technik']);
if (trim((string) ($w['auflage'] ?? '')) !== '') $node['artEdition'] = trim((string) $w['auflage']);
if ($images && ($m = Media::find($images[0]))) $node['image'] = site_url() . Media::url($m);
if (($u = Entries::absUrl($t, $w)) !== null) $node['url'] = $u;
if ($price !== '' && ($w['preis_anzeige'] ?? '') === 'zeigen' && (string) design('price_mode') === 'per_work' && is_numeric($w['preis'] ?? null)) {
    $node['offers'] = ['@type' => 'Offer', 'price' => (string) (float) $w['preis'], 'priceCurrency' => 'EUR',
        'availability' => ($w['verfuegbarkeit'] ?? '') === 'reserviert' ? 'https://schema.org/LimitedAvailability' : 'https://schema.org/InStock'];
}
\Core\StructuredData::add($node);
?>
<article class="wd">
  <div class="wrap">
    <?php if ($back !== '' && trim((string) ($d['back_link'] ?? '')) !== ''): ?><p class="xd__back"><a href="<?= e(galerie_link((string) $d['back_link'])) ?>"><?= icon('arrow-left') ?><span<?= $b->edit('back_label') ?>><?= e($back) ?></span></a></p><?php endif; ?>
    <div class="wd__grid">
      <div class="wd__media">
        <?php if ($images): ?>
          <?php if (is_editing()): ?><?= galerie_art($images[0], '(min-width: 1280px) 820px, 100vw', ['eager' => true]) ?>
          <?php else: ?>
          <ul class="wd__views" role="list" data-cms-lightbox>
            <?php foreach ($images as $n => $mid): $m = Media::find($mid); if (!$m) continue; ?>
            <li class="wd__view<?= $n === 0 ? ' wd__view--main' : '' ?>">
              <a class="wd__zoom"<?= \Core\MediaBlocks::lightboxLink($m, galerie_work_label($w)) ?>>
                <?= $n === 0 ? galerie_art($mid, '(min-width: 1280px) 820px, 100vw', ['eager' => true]) : galerie_art($mid, '(min-width: 1080px) 200px, 30vw') ?>
                <span class="wd__zoomhint"><?= icon('plus') ?><span><?= e($n === 0 ? lt('Vergrößern') : lt('Ansicht {n}', ['n' => $n + 1])) ?></span></span>
              </a>
            </li>
            <?php endforeach; ?>
          </ul>
          <?= \Core\MediaBlocks::lightbox() . \Core\MediaBlocks::script() ?>
          <?php endif; ?>
        <?php else: ?><?= galerie_art(null, '100vw') ?><?php endif; ?>
        <?php if ($videos || $videoUrl !== ''): ?><div class="wd__videos"><?= galerie_media_gallery($videos, $videoUrl, galerie_work_label($w)) ?></div><?php endif; ?>
        <?= galerie_media_manager('works', $w, lt('Werkabbildung: {werk}', ['werk' => galerie_work_label($w)])) ?>
      </div>
      <div class="wd__info">
        <?= galerie_caption($w, ['tag' => 'div', 'full' => true, 'htag' => 'h1', 'class' => 'wd__cap']) ?>
        <?php if ($enq): ?><p class="wd__enq"><a class="btn btn--primary" href="<?= e($enq) ?>"><?= e(galerie_enquiry_label()) ?></a></p><?php endif; ?>
        <?php if ($desc !== ''): ?><div class="prose wd__desc"<?= entry_edit_attr($t, $w, 'beschreibung', 'rich') ?>><?= rich($desc) ?></div><?php endif; ?>
        <?php if ($artistHref): ?><p class="wd__artist"><a class="more" href="<?= e($artistHref) ?>"><?= e(lt('Mehr über {name}', ['name' => (string) ($artist['name'] ?? '')])) ?><?= icon('arrow-right', ['class' => 'more__ico']) ?></a></p><?php endif; ?>
      </div>
    </div>
  </div>
  <?php if ($more): ?>
  <section class="wrap wd__more" aria-labelledby="<?= e($b->domId()) ?>-more">
    <h2 class="xd__h" id="<?= e($b->domId()) ?>-more"><?= e(lt('Weitere Werke von {name}', ['name' => (string) ($artist['name'] ?? '')])) ?></h2>
    <ul class="aws aws--grid min-s" role="list">
      <?php foreach ($more as $mw): ?><?= galerie_work_card($mw, ['artist' => false, 'enquiry' => false, 'sizes' => '(min-width: 1080px) 300px, 50vw']) ?><?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</article>
