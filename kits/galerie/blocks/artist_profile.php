<?php
/**
 * Künstler (Detailseite der Tabelle „Künstler“): Name (H1), Geburtsjahr/Wohnort, Kurzbiografie, Biografie, Website,
 * Anfrage – split: Porträt und Text (Switcher, Porträt ≥ 16 rem, Text ≥ 24 rem) · name: großer Name in Container-Einheiten.
 * Strukturierte Daten: Person (Core\StructuredData::add). Darunter „Werke“ und „Ausstellungen“ – automatisch gefiltert.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;
use Core\Media;

$v = $b->variant() ?: 'split';
$t = galerie_table('artists');
$a = galerie_entry_from('artists');
if (!$a && $t && is_editing()) $a = Entries::query($t, ['limit' => 1])[0] ?? null;
if (!$t || !$a) {
    if (is_editing()) echo '<div class="wrap"><p class="empty-hint">Vorschau erscheint, sobald es einen Eintrag in „Künstler“ gibt. Dieser Block gehört auf die Detailseite der Tabelle.</p></div>';
    return;
}
$ea = fn(string $f, ?string $mode = null) => entry_edit_attr($t, $a, $f, $mode);
$name = Entries::title($t, $a);
$meta = array_filter([trim((string) ($a['geboren'] ?? '')), trim((string) ($a['lebt'] ?? ''))]);
$short = trim((string) ($a['kurzbio'] ?? ''));
$bio = trim((string) ($a['bio'] ?? ''));
$web = trim((string) ($a['website'] ?? ''));
$portrait = !empty($d['show_portrait']) && !empty($a['portraet']) ? (int) $a['portraet'] : null;
$enq = !empty($d['show_enquiry']) ? galerie_enquiry_href() : null;
$back = trim((string) ($d['back_label'] ?? ''));
// Weitere Bilder und Videos (Atelier, Interviews, Videoarbeiten) – ohne Porträt
$media = galerie_entry_media('artists', $a, false);
$videoUrl = trim((string) ($a['video_url'] ?? ''));

$node = ['@type' => 'Person', 'name' => $name];
if ($short !== '') $node['description'] = $short;
if (($u = Entries::absUrl($t, $a)) !== null) $node['url'] = $u;
if ($portrait && ($m = Media::find($portrait))) $node['image'] = site_url() . Media::url($m);
if ($web !== '' && is_external($web)) $node['sameAs'] = [$web];
$node['jobTitle'] = lt('Künstlerin / Künstler');
\Core\StructuredData::add($node);
?>
<article class="ap ap--<?= e($v) ?>">
  <div class="wrap">
    <?php if ($back !== '' && trim((string) ($d['back_link'] ?? '')) !== ''): ?><p class="xd__back"><a href="<?= e(galerie_link((string) $d['back_link'])) ?>"><?= icon('arrow-left') ?><span<?= $b->edit('back_label') ?>><?= e($back) ?></span></a></p><?php endif; ?>
    <div class="ap__grid<?= $portrait ? ' ap__grid--img' : '' ?>">
      <?php if ($portrait && $v === 'split'): ?><?= galerie_image($portrait, '(min-width: 1080px) 480px, 100vw', '4:5', 'ap__img', ['eager' => true]) ?><?php endif; ?>
      <div class="ap__text">
        <h1 class="ap__name"><span<?= $ea('name') ?>><?= e($name) ?></span></h1>
        <?php if ($meta): ?><p class="ap__meta"><?= implode('<span class="xd__sep" aria-hidden="true"> · </span>', array_map('e', $meta)) ?></p><?php endif; ?>
        <?php if ($short !== ''): ?><p class="ap__lead"<?= $ea('kurzbio') ?>><?= nl2br(e($short), false) ?></p><?php endif; ?>
        <?php if ($portrait && $v === 'name'): ?><?= galerie_image($portrait, '(min-width: 1080px) 420px, 100vw', '4:5', 'ap__img ap__img--inline') ?><?php endif; ?>
        <?php if ($bio !== ''): ?><div class="prose ap__bio"<?= $ea('bio', 'rich') ?>><?= rich($bio) ?></div><?php endif; ?>
        <?php if ($web !== '' || $enq): ?>
        <div class="btn-row ap__actions">
          <?php if ($enq): ?><a class="btn btn--primary" href="<?= e($enq) ?>"><span<?= $b->edit('enquiry_label') ?>><?= e(trim((string) ($d['enquiry_label'] ?? '')) ?: lt('Verfügbare Werke anfragen')) ?></span></a><?php endif; ?>
          <?php if ($web !== '' && is_external($web)): ?><a class="btn btn--secondary" href="<?= e($web) ?>" rel="noopener" target="_blank"><?= icon('globe') ?><span><?= e(lt('Website')) ?></span><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?= galerie_media_manager('artists', $a, lt('Porträt: {name}', ['name' => $name])) ?>
    <?php if ($media || $videoUrl !== ''): ?>
    <section class="ap__media" aria-labelledby="<?= e($b->domId()) ?>-media">
      <h2 class="xd__h" id="<?= e($b->domId()) ?>-media"><?= e(lt('Bilder und Videos')) ?></h2>
      <?= galerie_media_gallery($media, $videoUrl, $name) ?>
    </section>
    <?php endif; ?>
  </div>
</article>
