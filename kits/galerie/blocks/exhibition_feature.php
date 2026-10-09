<?php
/**
 * Aktuelle Ausstellung: die laufende (oder gewählte) Ausstellung groß – Status „Jetzt“/„Demnächst“ aus den Daten berechnet.
 * bleed (Vollbild, Titel unten auf dem Bild – Abschnitt ohne Innenabstand) · split (Switcher: Bild/Text je ≥ 20 rem) ·
 * type (Titel in Container-Einheiten, kleines Bild daneben). Bild = Feld „bild“ der Ausstellung (Ausstellungsansicht).
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;

$v = $b->variant() ?: 'bleed';
$t = galerie_table('exhibitions');
$venue = (string) ($d['venue'] ?? '');
$x = $t ? (trim((string) ($d['exhibition'] ?? '')) !== '' ? galerie_entry_from('exhibitions', (string) $d['exhibition']) : galerie_featured_exhibition($venue !== '' ? [$venue] : [])) : null;
if (!$t || !$x) {
    if (is_editing()) echo '<div class="wrap"><p class="empty-hint">' . e($t ? 'Zurzeit keine laufende oder kommende Ausstellung – oder die gewählte ist nicht veröffentlicht.' : 'Die Tabelle „Ausstellungen“ fehlt (Website → Galerie → Datentabellen).') . '</p></div>';
    return;
}
$href = Entries::href($t, $x);
$tag = !empty($d['h1']) ? 'h1' : 'h2';
$eyebrow = trim((string) ($d['eyebrow'] ?? ''));
$sub = trim((string) ($x['untertitel'] ?? ''));
$artists = galerie_artist_names((array) ($x['kuenstler'] ?? []));
$dates = galerie_dates($x);
$opening = galerie_opening($x);
$place = galerie_venue($x);
$lead = !empty($d['show_text']) ? trim((string) ($x['kurztext'] ?? '')) : '';
$more = trim((string) ($d['button_label'] ?? '')) ?: lt('Zur Ausstellung');
$ea = fn(string $f) => entry_edit_attr($t, $x, $f);

$text = function (string $cls) use ($b, $x, $href, $tag, $eyebrow, $sub, $artists, $dates, $opening, $place, $lead, $more, $ea, $t): string {
    $h = '<div class="xf__text ' . $cls . '">';
    $h .= $eyebrow !== '' ? '<p class="eyebrow xf__eyebrow"' . $b->edit('eyebrow') . '>' . galerie_title($eyebrow) . '</p>' : '<p class="xf__eyebrow">' . galerie_status_badge($x) . '</p>';
    $h .= '<' . $tag . ' id="' . e($b->titleId()) . '" class="xf__title"><span' . $ea('titel') . '>' . e(Entries::title($t, $x)) . '</span></' . $tag . '>';
    if ($sub !== '') $h .= '<p class="xf__sub"' . $ea('untertitel') . '>' . e($sub) . '</p>';
    if ($artists !== '') $h .= '<p class="xf__artists">' . $artists . '</p>';
    $meta = array_filter([$dates !== '' ? '<span class="xf__dates">' . e($dates) . '</span>' : '', $place !== '' ? '<span>' . e($place) . '</span>' : '']);
    if ($meta) $h .= '<p class="xf__meta">' . implode('<span class="xf__sep" aria-hidden="true"> · </span>', $meta) . '</p>';
    if ($opening !== '') $h .= '<p class="xf__opening">' . e($opening) . '</p>';
    if ($lead !== '') $h .= '<p class="xf__lead"' . $ea('kurztext') . '>' . nl2br(e($lead), false) . '</p>';
    if ($href) $h .= '<p class="xf__more"><a class="more" href="' . e($href) . '">' . e($more) . icon('arrow-right', ['class' => 'more__ico']) . '<span class="sr-only">: ' . e(Entries::title($t, $x)) . '</span></a></p>';
    return $h . '</div>';
};
$imgId = !empty($x['bild']) ? (int) $x['bild'] : null;

if ($v === 'bleed'):
    $ov = in_array($d['overlay'] ?? '', ['gradient', 'strong', 'none'], true) ? $d['overlay'] : 'gradient';
    $hgt = ($d['height'] ?? 'screen') === 'large' ? 'large' : 'screen';
    $pic = $imgId ? img($imgId, '100vw', ['eager' => $b->prev === null]) : '';
?>
<div class="xf xf--bleed xf--ov-<?= e($ov) ?> xf--h-<?= e($hgt) ?><?= $pic === '' ? ' xf--noimg' : '' ?>">
  <?php if ($pic !== ''): ?><div class="xf__media"><?= $pic ?></div><?php endif; ?>
  <div class="wrap xf__inner"><?= $text($ov !== 'none' && $pic !== '' ? 'on-media' : '') ?></div>
</div>
<?php elseif ($v === 'type'):
    $ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
?>
<div class="wrap xf xf--type">
  <?= $text('') ?>
  <?= galerie_image($imgId, '(min-width: 1080px) 420px, 60vw', $ratio, 'xf__img', ['eager' => $b->prev === null]) ?>
</div>
<?php else:
    $ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
?>
<div class="wrap xf xf--split">
  <?= galerie_image($imgId, '(min-width: 1080px) 700px, 100vw', $ratio, 'xf__img', ['eager' => $b->prev === null]) ?>
  <?= $text('') ?>
</div>
<?php endif;
