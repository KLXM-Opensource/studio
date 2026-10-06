<?php
/**
 * Ausstellung (Detailseite der Tabelle „Ausstellungen“): Kopf mit Status, Titel (H1), Künstlern, Zeitraum, Ort, Eröffnung;
 * Text mit Angaben-Spalte (Sidebar-Muster), Pressetext, Ausstellungsansichten (Lightbox des Kerns), gezeigte Werke.
 * Varianten: bleed (Bild über die ganze Breite, Titel darunter) · split (Switcher Bild/Kopf) · text (typografisch).
 * Strukturierte Daten: ExhibitionEvent (Core\StructuredData::add).
 * Im Seiten-Editor der Vorlage: erster Eintrag als Vorschau.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;
use Core\Media;

$v = $b->variant() ?: 'split';
$t = galerie_table('exhibitions');
$x = galerie_entry_from('exhibitions');
if (!$x && $t && is_editing()) $x = Entries::query($t, ['limit' => 1])[0] ?? null;
if (!$t || !$x) {
    if (is_editing()) echo '<div class="wrap"><p class="empty-hint">Vorschau erscheint, sobald es einen Eintrag in „Ausstellungen“ gibt. Dieser Block gehört auf die Detailseite der Tabelle.</p></div>';
    return;
}
$x['_status'] = galerie_status($x);
$ea = fn(string $f, ?string $mode = null) => entry_edit_attr($t, $x, $f, $mode);
$title = Entries::title($t, $x);
$sub = trim((string) ($x['untertitel'] ?? ''));
$artistIds = (array) ($x['kuenstler'] ?? []);
$artists = galerie_artist_names($artistIds);
$dates = galerie_dates($x, (string) design('date_style') === 'relative' ? 'long' : null);
$opening = galerie_opening($x, true);
$place = galerie_venue($x);
$imgId = !empty($x['bild']) ? (int) $x['bild'] : null;
$text = trim((string) ($x['text'] ?? ''));
$lead = trim((string) ($x['kurztext'] ?? ''));
$press = !empty($x['pressetext']) ? Media::find((int) $x['pressetext']) : null;
$pressLabel = trim((string) ($d['press_label'] ?? '')) ?: lt('Pressetext');
// Ausstellungsansichten: Bilder und Videos (Hauptbild nur bei „Typografisch“ – sonst steht es schon oben), dazu ein YouTube-/Vimeo-Link
$views = !empty($d['show_views']) ? galerie_entry_media('exhibitions', $x, $v === 'text') : [];
$videoUrl = !empty($d['show_views']) ? trim((string) ($x['video_url'] ?? '')) : '';
$works = !empty($d['show_works']) && !empty($x['werke']) ? galerie_works(['ids' => (array) $x['werke']]) : [];
$back = trim((string) ($d['back_label'] ?? ''));
$enq = galerie_enquiry_href();

// Strukturierte Daten (schema.org ExhibitionEvent) – nur befüllte Angaben
$node = ['@type' => 'ExhibitionEvent', 'name' => $title, 'startDate' => substr((string) $x['beginn'], 0, 10)];
if (!empty($x['ende'])) $node['endDate'] = substr((string) $x['ende'], 0, 10);
if ($lead !== '') $node['description'] = $lead;
if (($u = Entries::absUrl($t, $x)) !== null) $node['url'] = $u;
if ($imgId && ($m = Media::find($imgId))) $node['image'] = site_url() . Media::url($m);
$node['location'] = array_filter(['@type' => 'Place', 'name' => $place !== '' ? $place : galerie_name(), 'address' => implode(', ', galerie_address_lines()) ?: null]);
$node['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
$performers = [];
foreach (galerie_by_ids('artists', $artistIds) as $a) $performers[] = ['@type' => 'Person', 'name' => (string) ($a['name'] ?? '')];
if ($performers) $node['performer'] = $performers;
$node['organizer'] = ['@type' => 'Organization', 'name' => galerie_name(), 'url' => absolute_url('/')];
\Core\StructuredData::add($node);

$head = '<header class="xd__head">'
    . ($back !== '' && trim((string) ($d['back_link'] ?? '')) !== '' ? '<p class="xd__back"><a href="' . e(galerie_link((string) $d['back_link'])) . '">' . icon('arrow-left') . '<span' . $b->edit('back_label') . '>' . e($back) . '</span></a></p>' : '')
    . '<p class="xd__status">' . galerie_status_badge($x) . '</p>'
    . ($artists !== '' ? '<p class="xd__artists">' . $artists . '</p>' : '')
    . '<h1 class="xd__title"><span' . $ea('titel') . '>' . e($title) . '</span></h1>'
    . ($sub !== '' ? '<p class="xd__sub"' . $ea('untertitel') . '>' . e($sub) . '</p>' : '')
    . '<p class="xd__meta"><span class="xd__dates">' . e($dates) . '</span>' . ($place !== '' ? '<span class="xd__sep" aria-hidden="true"> · </span><span>' . e($place) . '</span>' : '') . '</p>'
    . ($opening !== '' && $x['_status'] !== 'past' ? '<p class="xd__opening">' . e($opening) . '</p>' : '')
    . '</header>';
$heroImg = $imgId ? img($imgId, $v === 'bleed' ? '100vw' : '(min-width: 1080px) 760px, 100vw', ['eager' => true]) : '';
?>
<article class="xd xd--<?= e($v) ?>">
  <?php if ($v === 'bleed'): ?>
    <?php if ($heroImg !== ''): ?><div class="xd__hero"><?= $heroImg ?></div><?php endif; ?>
    <div class="wrap"><?= $head ?></div>
  <?php elseif ($v === 'split'): ?>
    <div class="wrap xd__split">
      <?php if ($heroImg !== ''): ?><div class="xd__img"><?= $heroImg ?></div><?php endif; ?>
      <?= $head ?>
    </div>
  <?php else: ?>
    <div class="wrap"><?= $head ?></div>
  <?php endif; ?>

  <div class="wrap xd__body">
    <aside class="xd__aside" aria-label="<?= e(lt('Angaben zur Ausstellung')) ?>">
      <dl class="xd__dl">
        <div><dt><?= e(lt('Zeitraum')) ?></dt><dd><?= e(galerie_dates($x, 'long')) ?></dd></div>
        <?php if ($opening !== ''): ?><div><dt><?= e(lt('Eröffnung')) ?></dt><dd><?= e(preg_replace('~^[^:]+:\s*~u', '', $opening)) ?></dd></div><?php endif; ?>
        <?php if ($place !== ''): ?><div><dt><?= e(lt('Ort')) ?></dt><dd><?= e($place) ?></dd></div><?php endif; ?>
        <?php if ($artists !== ''): ?><div><dt><?= e(count($artistIds) > 1 ? lt('Künstlerinnen und Künstler') : lt('Künstlerin / Künstler')) ?></dt><dd><?= $artists ?></dd></div><?php endif; ?>
      </dl>
      <?php if ($press): ?>
      <p class="xd__press"><a class="btn btn--secondary btn--small" href="<?= e(Media::url($press)) ?>" download><?= icon('file-pdf') ?><span><?= e($pressLabel) ?></span><span class="xd__size">(<?= e(Media::typeLabel((string) $press['mime'])) ?>, <?= e(Media::humanSize((int) $press['size'])) ?>)</span></a></p>
      <?php endif; ?>
      <?php if ($enq && $x['_status'] !== 'past'): ?><p class="xd__enq"><a class="more" href="<?= e($enq) ?>"><?= e(lt('Anfrage an die Galerie')) ?><?= icon('arrow-right', ['class' => 'more__ico']) ?></a></p><?php endif; ?>
    </aside>
    <div class="xd__text">
      <?php if ($lead !== ''): ?><p class="xd__lead"<?= $ea('kurztext') ?>><?= nl2br(e($lead), false) ?></p><?php endif; ?>
      <?php if ($text !== ''): ?><div class="prose"<?= $ea('text', 'rich') ?>><?= rich($text) ?></div><?php endif; ?>
    </div>
  </div>

  <?php if ($manager = galerie_media_manager('exhibitions', $x, lt('Ausstellungsansicht: {titel}', ['titel' => $title]))): ?><div class="wrap"><?= $manager ?></div><?php endif; ?>

  <?php if ($views || $videoUrl !== ''): ?>
  <section class="wrap xd__views" aria-labelledby="<?= e($b->domId()) ?>-views">
    <h2 class="xd__h" id="<?= e($b->domId()) ?>-views"><?= e(lt('Ausstellungsansichten')) ?></h2>
    <?= galerie_media_gallery($views, $videoUrl, $title) ?>
  </section>
  <?php endif; ?>

  <?php if ($works): ?>
  <section class="wrap xd__works" aria-labelledby="<?= e($b->domId()) ?>-works">
    <h2 class="xd__h" id="<?= e($b->domId()) ?>-works"<?= $b->edit('works_title') ?>><?= e(trim((string) ($d['works_title'] ?? '')) ?: lt('Werke in der Ausstellung')) ?></h2>
    <ul class="aws aws--grid min-m" role="list">
      <?php foreach ($works as $w): ?><?= galerie_work_card($w, ['enquiry' => true, 'artist' => count($artistIds) !== 1]) ?><?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</article>
