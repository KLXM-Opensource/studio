<?php
/**
 * Artikel (Detailseiten-Vorlage): setzt den aufgerufenen Eintrag als Artikel.
 * Dachzeile (Auswahlfeld), Schlagzeile, Vorspann (Beschreibungsfeld), Autorzeile (Feld „autor“, auch Verknüpfung zu Personen),
 * Datum, Lesezeit, Herkunft (geteilte Tabellen), Aufmacherbild mit Nachweis, Text mit Inhaltsverzeichnis, Teilen-Links ohne Tracker,
 * Lesefortschritt (CSS scroll-driven animation, ohne JavaScript). Kalender-Tabellen: Termin-Kasten mit nächstem Termin, Ort und iCal.
 * Angemeldet (außerhalb des Vorlagen-Editors): Titel, Vorspann und Text direkt im Text bearbeitbar (entry_edit_attr).
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Calendar;
use Core\Data\Entries;
use Core\Data\Shared;
use Core\Data\Tables;

$ctx = app()->entry;
$t = Tables::findContent((string) ($d['table'] ?? '')) ?? ($ctx['table'] ?? null);
if (!$t) {
    if (is_editing()) echo '<div class="wrap"><p class="empty-hint">Bitte in der Seitenleiste die Tabelle wählen.</p></div>';
    return;
}
$e = $ctx && $ctx['table']['handle'] === $t['handle'] ? $ctx['entry'] : (is_editing() ? (Entries::query($t, ['status' => 'all', 'limit' => 1])[0] ?? null) : null);
if (!$e) {
    if (is_editing()) echo '<div class="wrap"><p class="empty-hint">Noch keine Einträge in „' . e($t['name']) . '“ – die Vorschau erscheint, sobald es einen gibt.</p></div>';
    return;
}
$opt = fn(string $k) => str_starts_with((string) ($d[$k] ?? ''), $t['handle'] . '.') ? substr((string) $d[$k], strlen($t['handle']) + 1) : '';
$first = function (array $types, string $pattern = '') use ($t): string {
    foreach ($t['fields'] as $f) {
        if (in_array($f['type'], $types, true) && ($pattern === '' || preg_match($pattern, $f['name'] . ' ' . $f['label']))) return $f['name'];
    }
    return '';
};
$kf = $opt('kicker_field') ?: $first(['select', 'multiselect']);
$df = $opt('dek_field') ?: (string) ($t['settings']['description_field'] ?? '');
$bf = $opt('body_field') ?: $first(['richtext']);
$af = $opt('author_field') ?: $first(['relation', 'text'], '~autor|author|verfasser|von~i');
$if = Tables::imageField($t);
$cf = $first(['text'], '~bildunterschrift|bildtext|caption~i');

$title = Entries::title($t, $e);
$kicker = $kf !== '' ? Entries::html($t, $e, $kf) : '';
$dek = $df !== '' && $df !== $bf ? Entries::html($t, $e, $df) : '';
$body = $bf !== '' ? Entries::html($t, $e, $bf) : '';
$toc = [];
if (!empty($d['show_toc']) && !is_editing() && !app()->entryEdit) [$body, $toc] = editorial_toc($body, 'a' . (int) $e['id']);

// Autor: Verknüpfung (Personen-Tabelle, verlinkt) oder Text
$author = '';
if ($af !== '' && !empty($e[$af])) {
    $f = Tables::field($t, $af);
    if (($f['type'] ?? '') === 'relation' && ($target = Tables::findContent((string) ($f['target'] ?? ''))) && ($p = Entries::find($target, (int) (is_array($e[$af]) ? ($e[$af][0] ?? 0) : $e[$af])))) {
        $url = Entries::href($target, $p);
        $author = $url ? '<a href="' . e($url) . '" rel="author">' . e(Entries::title($target, $p)) . '</a>' : e(Entries::title($target, $p));
    } else {
        $author = Entries::html($t, $e, $af);
    }
}
$pub = !empty($e['published_at']) ? strtotime((string) $e['published_at']) : null;
$upd = !empty($e['updated_at']) ? strtotime((string) $e['updated_at']) : null;
$calendar = Calendar::enabled($t);
$origin = Shared::isForeign($t, $e) ? Entries::html($t, $e, '_origin') : '';

$image = $if !== '' ? ((int) ($e[$if] ?? 0) ?: null) : null;
$width = in_array($d['image_width'] ?? '', ['wide', 'bleed', 'text', 'none'], true) ? $d['image_width'] : 'wide';
$ratio = (string) ($d['image_ratio'] ?? '3:2') ?: '3:2';
$media = $width !== 'none' && $image ? editorial_image($image, $width === 'bleed' ? '100vw' : ($width === 'text' ? '(min-width: 860px) 760px, 100vw' : '(min-width: 1360px) 1280px, 100vw'), $ratio, '', ['eager' => true]) : '';
$caption = $cf !== '' ? trim(Entries::text($t, $e, $cf)) : '';

// Termin-Kasten: nächster Termin (Wiederholungen), Ort, iCal
$facts = [];
if ($calendar && !empty($d['show_facts'])) {
    $c = Calendar::config($t);
    $next = Calendar::occurrences($t, new DateTimeImmutable('today', Calendar::tz()), new DateTimeImmutable('+1 year', Calendar::tz()),
        ['ids' => [(int) $e['id']], 'limit' => 1, 'status' => $e['status'] === 'published' ? 'published' : 'all'])[0] ?? null;
    $span = $next ?? ((($s = Calendar::span($t, $e)) ? $s + ['recurring' => false] : null));
    if ($span) $facts[] = [lt('Termin'), '<time datetime="' . e($span['all_day'] ? $span['start']->format('Y-m-d') : $span['start']->format('Y-m-d\TH:iP')) . '">' . e(Calendar::when($span, 'long')) . '</time>'];
    $rec = Calendar::recurrence($t, $e);
    if ($rec['rrule'] !== '') $facts[] = [lt('Wiederholung'), e(Calendar::describe($rec))];
    if ($c['location'] !== '' && ($loc = Entries::html($t, $e, $c['location'], ['plain' => true])) !== '') $facts[] = [lt('Ort'), $loc];
    if ($c['feed'] && !empty($e['slug'])) $facts[] = ['', '<a class="more" href="' . e((string) Entries::bindValue($t, $e, '_ics', 'link')) . '" type="text/calendar" download>' . e(lt('In den Kalender übernehmen (.ics)')) . '</a>'];
}
$share = !empty($d['show_share']) ? editorial_share((string) (Entries::absUrl($t, $e) ?? site_url() . (app()->request?->path ?? '/')), $title) : '';
$tocNav = editorial_toc_nav($toc, 'toc-' . (int) $e['id']);
$side = $facts || $tocNav !== '' || $share !== '';
$minutes = $body !== '' && !$calendar ? editorial_reading_time($body) : 0;
?>
<article class="art<?= $side ? ' art--side' : '' ?>" aria-labelledby="art-title">
  <?php if (!empty($d['show_progress']) && !$calendar): ?><div class="readbar" aria-hidden="true"></div><?php endif; ?>
  <div class="wrap art__headwrap">
    <?php if (!empty($d['back_label']) && !empty($d['back_link'])): ?><p class="art__back"><a href="<?= e(link_href((string) $d['back_link'])) ?>"><span aria-hidden="true">←</span> <?= e($d['back_label']) ?></a></p><?php endif; ?>
    <header class="art__head">
      <?php if ($kicker !== ''): ?><p class="kicker"><?= $kicker ?></p><?php endif; ?>
      <h1 id="art-title" class="art__title display"><?php $ea = entry_edit_attr($t, $e, '_title'); ?><?= $ea !== '' ? '<span' . $ea . '>' . e($title) . '</span>' : e($title) ?></h1>
      <?php if ($dek !== ''): ?><p class="art__dek"<?= entry_edit_attr($t, $e, $df) ?>><?= strip_tags($dek, '<br><a><b><strong><i><em>') ?></p><?php endif; ?>
      <p class="byline art__by">
        <?php if ($author !== ''): ?><span class="art__author"><?= e(lt('Von')) ?> <?= $author ?></span><?php endif; ?>
        <?php if ($pub && !$calendar): ?><span><time datetime="<?= e(date('c', $pub)) ?>"><?= e(date_local($pub, 'long')) ?></time><?php if ($upd && $upd - $pub > 86400): ?> · <?= e(lt('aktualisiert {date}', ['date' => date_local($upd, 'short')])) ?><?php endif; ?></span><?php endif; ?>
        <?php if ($minutes): ?><span><?= e(lt('{n} Min. Lesezeit', ['n' => $minutes])) ?></span><?php endif; ?>
        <?php if ($origin !== ''): ?><span class="origin"><?= e(lt('von')) ?> <?= $origin ?></span><?php endif; ?>
      </p>
    </header>
  </div>
  <?php if ($media !== ''): ?>
  <figure class="fig fig--<?= e($width) ?> art__fig<?= $width === 'bleed' ? '' : ' wrap' ?>"><?= $media ?><?= editorial_caption($caption, editorial_credit($image)) ?></figure>
  <?php endif; ?>
  <div class="wrap art__grid">
    <?php if ($side): ?>
    <aside class="art__side" aria-label="<?= e(lt('Zum Artikel')) ?>">
      <?php if ($facts): ?>
      <dl class="facts"><?php foreach ($facts as [$k, $v]): ?><div class="facts__row"><?php if ($k !== ''): ?><dt><?= e($k) ?></dt><?php endif; ?><dd><?= $v ?></dd></div><?php endforeach; ?></dl>
      <?php endif; ?>
      <?= $tocNav ?>
      <?= $share ?>
    </aside>
    <?php endif; ?>
    <?php if (trim(strip_tags($body)) !== '' || is_editing()): ?>
    <div class="art__body prose prose--dropcap"<?= entry_edit_attr($t, $e, $bf) ?>><?= $body ?></div>
    <?php endif; ?>
  </div>
</article>
