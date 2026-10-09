<?php
/**
 * Aufmacher / Seitenkopf (H1). Varianten:
 *   split     Aufmacher: Text und Bild im Seitenraster (klassisch: Text links; asymmetrisch: großes Bild, Text versetzt; zentriert: untereinander)
 *   centered  Plakat: Schlagzeile groß zentriert, Bild 21:9 darunter
 *   cover     Titelbild: Bild bildschirmfüllend, Text darauf (dunkle Verlaufsfläche)
 *   compact   Ressortkopf: große Rubrik-Überschrift, Vorspann, Unterseiten als Reiter
 * Weitere Varianten (Stylesheet css/hero-x.css, nur dort geladen):
 *   issue     Titelgeschichte: Ausgabe-Zeile, Bild im Hochformat, Schlagzeile, „Außerdem in dieser Ausgabe“ (2–3 Anrisse)
 *   agenda    Aktuell: Schlagzeile + die nächsten Termine bzw. neuesten Meldungen einer Tabelle in Zeitungsspalten (Core\Blocks\Hero::dates)
 *   voice     Stimme: Schlagzeile + großes Zitat mit Initialen statt Porträt, optional Bewertung
 * @var \Core\Block $b  @var array $d
 */
use Core\Blocks\Hero;

$v = in_array($b->variant(), ['split', 'centered', 'cover', 'compact', 'issue', 'agenda', 'voice'], true) ? $b->variant() : 'split';
$eyebrow = trim((string) ($d['eyebrow'] ?? ''));
$byline = trim((string) ($d['byline'] ?? ''));
$text = trim((string) ($d['text'] ?? ''));
$image = (int) ($d['image'] ?? 0) ?: null;
$eager = $b->prev === null;
$caption = trim((string) ($d['caption'] ?? ''));
$credit = editorial_credit($image);

$kicker = $eyebrow !== '' || is_editing() ? '<p class="kicker"' . $b->edit('eyebrow') . '>' . emphasis($eyebrow) . '</p>' : '';
$h1 = '<h1 id="' . e($b->titleId()) . '" class="display"' . $b->edit('title') . '>' . emphasis((string) $d['title']) . '</h1>';
$dek = $text !== '' || is_editing() ? '<p class="aufm__dek"' . $b->edit('text') . '>' . nl2br(emphasis($text), false) . '</p>' : '';
$by = $byline !== '' ? '<p class="byline"' . $b->edit('byline') . '>' . emphasis($byline) . '</p>' : '';
$btns = editorial_buttons($b);

if ($v === 'compact'):
    $tabs = !empty($d['subnav']) ? editorial_subpages(app()->currentPage) : [];
?>
<div class="wrap rhead">
  <?= $kicker ?>
  <h1 id="<?= e($b->titleId()) ?>" class="rhead__title"<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></h1>
  <?php if ($dek !== '' || $btns !== ''): ?><div class="rhead__foot"><?= $dek ?><?= $btns ?></div><?php endif; ?>
  <?php if (count($tabs) > 1): ?>
  <nav class="rhead__tabs" aria-label="<?= e(lt('Unterseiten')) ?>"><?= editorial_tab_links($tabs, 'tabs__list') ?></nav>
  <?php endif; ?>
</div>
<?php return; endif;

if ($v === 'issue'):
    $issue = trim((string) ($d['issue'] ?? ''));
    $moreTitle = trim((string) ($d['issue_more_title'] ?? '')) ?: lt('Außerdem in dieser Ausgabe');
    $more = array_values(array_filter((array) ($d['issue_more'] ?? []), fn($i) => is_array($i) && trim((string) ($i['title'] ?? '')) !== ''));
    $media = editorial_image($image, '(min-width: 1360px) 620px, (min-width: 960px) 46vw, 100vw', '4:5', 'issue__media', ['eager' => $eager]);
    $moreId = $b->domId() . '-more';
?>
<div class="wrap issue<?= $media === '' ? ' issue--noimg' : '' ?>">
  <?php if ($issue !== '' || $eyebrow !== '' || is_editing()): ?>
  <p class="issue__line"><span class="issue__no"<?= $b->edit('issue') ?>><?= emphasis($issue) ?></span><?php if ($eyebrow !== '' || is_editing()): ?><span class="issue__rubric"<?= $b->edit('eyebrow') ?>><?= emphasis($eyebrow) ?></span><?php endif; ?></p>
  <?php endif; ?>
  <?php if ($media !== ''): ?>
  <figure class="issue__fig"><?= $media ?><?= editorial_caption($caption, $credit, is_editing() ? $b->edit('caption') : '') ?></figure>
  <?php endif; ?>
  <div class="issue__text">
    <div class="issue__head"><?= $h1 ?><?= $dek ?><?= $by ?><?= $btns ?></div>
    <?php if ($more): ?>
    <section class="issue__more" aria-labelledby="<?= e($moreId) ?>">
      <h2 id="<?= e($moreId) ?>" class="issue__more-title"<?= $b->edit('issue_more_title') ?>><?= emphasis($moreTitle) ?></h2>
      <ol class="issue__list" role="list">
        <?php foreach ($more as $m): $href = editorial_link((string) ($m['link'] ?? '')); $k = trim((string) ($m['kicker'] ?? '')); $meta = trim((string) ($m['meta'] ?? '')); ?>
        <li class="issue__item">
          <?php if ($k !== ''): ?><span class="issue__kick"><?= emphasis($k) ?></span><?php endif; ?>
          <?php if ($href !== ''): ?><a class="issue__link" <?= editorial_link_attrs((string) $m['link']) ?>><?= emphasis((string) $m['title']) ?><?= editorial_ext_note($href) ?></a><?php else: ?><span class="issue__link"><?= emphasis((string) $m['title']) ?></span><?php endif; ?>
          <?php if ($meta !== ''): ?><span class="issue__meta"><?= e($meta) ?></span><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ol>
    </section>
    <?php endif; ?>
  </div>
</div>
<?php return; endif;

if ($v === 'agenda'):
    $list = Hero::dates($d);
    $items = $list['items'];
    $listTitle = trim((string) ($d['dates_title'] ?? '')) ?: ($list['calendar'] ? lt('Als Nächstes') : lt('Neueste Meldungen'));
    $moreLabel = trim((string) ($d['dates_more_label'] ?? ''));
    $moreLink = trim((string) ($d['dates_more_link'] ?? ''));
    $listId = $b->domId() . '-dates';
?>
<div class="wrap agd">
  <div class="agd__head">
    <div class="agd__lead"><?= $kicker ?><?= $h1 ?></div>
    <?php if ($dek !== '' || $by !== '' || $btns !== ''): ?><div class="agd__side"><?= $dek ?><?= $by ?><?= $btns ?></div><?php endif; ?>
  </div>
  <?php if ($items || is_editing()): ?>
  <section class="agd__board" aria-labelledby="<?= e($listId) ?>">
    <div class="agd__bar">
      <h2 id="<?= e($listId) ?>" class="agd__title"<?= $b->edit('dates_title') ?>><?= emphasis($listTitle) ?></h2>
      <?php if ($moreLabel !== '' && $moreLink !== ''): ?><a class="agd__all" <?= editorial_link_attrs($moreLink) ?>><?= e($moreLabel) ?> <span aria-hidden="true">→</span></a><?php endif; ?>
    </div>
    <?php if (!$items): ?>
    <p class="empty-hint"><?= e(__('Bitte in der Seitenleiste eine Tabelle wählen – Kalender-Tabelle für Termine, andere Tabelle für die neuesten Einträge.')) ?></p>
    <?php else: ?>
    <ol class="agd__cols agd__cols--<?= count($items) ?>" role="list">
      <?php foreach ($items as $it): ?>
      <li class="agd__item">
        <?php if ($it['day'] !== ''): ?>
        <p class="agd__date"><time datetime="<?= e($it['datetime']) ?>"><span class="agd__day"><?= e($it['day']) ?></span> <span class="agd__mon"><?= e($it['month']) ?></span></time></p>
        <?php endif; ?>
        <h3 class="agd__name"><?php if ($it['href']): ?><a class="agd__link" href="<?= e($it['href']) ?>"><?= e($it['title']) ?></a><?php else: ?><?= e($it['title']) ?><?php endif; ?></h3>
        <?php if ($list['calendar'] && $it['when'] !== ''): ?><p class="agd__when"><?= e($it['when']) ?></p><?php endif; ?>
        <?php if ($it['place'] !== ''): ?><p class="agd__place"><?= e($it['place']) ?></p><?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>
<?php return; endif;

if ($v === 'voice'):
    $quote = trim((string) ($d['quote'] ?? ''));
    $name = trim((string) ($d['quote_name'] ?? ''));
    $role = trim((string) ($d['quote_role'] ?? ''));
    $rating = (string) ($d['rating'] ?? '');
    $ratingNote = trim((string) ($d['rating_note'] ?? ''));
    $words = array_values(array_filter(preg_split('~\s+~u', $name) ?: [], fn($w) => $w !== '' && !preg_match('~^(dr|prof|med|dipl|frau|herr)\.?$~iu', $w)));
    $ini = $words ? mb_strtoupper(mb_substr($words[0], 0, 1) . (count($words) > 1 ? mb_substr(end($words), 0, 1) : '')) : '';
    $stars = in_array($rating, ['5', '4.5', '4'], true) ? $rating : '';
    $starSvg = '<svg viewBox="0 0 120 24" width="120" height="24" focusable="false">' . implode('', array_map(fn($i) => '<path transform="translate(' . ($i * 24) . ' 0)" d="M12 1.8l3.1 6.6 7.2.9-5.3 5 1.4 7.1L12 17.9l-6.4 3.5 1.4-7.1-5.3-5 7.2-.9z"/>', range(0, 4))) . '</svg>';
?>
<div class="wrap voice">
  <div class="voice__text"><?= $kicker ?><h1 id="<?= e($b->titleId()) ?>" class="h1 voice__title"<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></h1><?= $dek ?><?= $btns ?></div>
  <figure class="voice__quote">
    <?php if ($quote !== '' || is_editing()): ?><blockquote class="voice__q"><p<?= $b->edit('quote') ?>><?= nl2br(emphasis($quote), false) ?></p></blockquote><?php endif; ?>
    <?php if ($name !== '' || $role !== '' || $stars !== ''): ?>
    <figcaption class="voice__by">
      <?php if ($ini !== ''): ?><span class="voice__ini" aria-hidden="true"><?= e($ini) ?></span><?php endif; ?>
      <span class="voice__who">
        <?php if ($name !== ''): ?><span class="voice__name"><?= emphasis($name) ?></span><?php endif; ?>
        <?php if ($role !== ''): ?><span class="voice__role"><?= emphasis($role) ?></span><?php endif; ?>
      </span>
      <?php if ($stars !== ''): ?>
      <span class="voice__rating">
        <span class="voice__stars voice__stars--<?= e(str_replace('.', '', $stars)) ?>" aria-hidden="true"><span class="voice__stars-base"><?= $starSvg ?></span><span class="voice__stars-fill"><?= $starSvg ?></span></span>
        <span class="voice__score"><?= e(['5' => lt('5 von 5'), '4.5' => lt('4,5 von 5'), '4' => lt('4 von 5')][$stars]) ?><?php if ($ratingNote !== ''): ?><span class="voice__note"> · <?= e($ratingNote) ?></span><?php endif; ?></span>
      </span>
      <?php endif; ?>
    </figcaption>
    <?php endif; ?>
  </figure>
</div>
<?php return; endif;

if ($v === 'cover'):
    $pic = $image ? img($image, '100vw', ['alt' => '', 'eager' => $eager]) : '';
?>
<div class="aufm aufm--cover<?= $pic === '' ? ' aufm--nopic' : '' ?>">
  <?php if ($pic !== ''): ?><div class="aufm__bg media" aria-hidden="true"><?= $pic ?></div><?php endif; ?>
  <div class="wrap aufm__inner">
    <div class="aufm__text"><?= $kicker ?><?= $h1 ?><?= $dek ?><?= $by ?><?= $btns ?></div>
    <?php if ($caption !== '' || $credit !== ''): ?><p class="aufm__credit"><?= implode(' · ', array_filter([emphasis($caption), e($credit)])) ?></p><?php endif; ?>
  </div>
</div>
<?php return; endif;

$ratio = $v === 'centered' ? '21:9' : (string) ($d['ratio'] ?: '3:2');
$sizes = $v === 'centered' ? '(min-width: 1360px) 1280px, 100vw' : '(min-width: 1360px) 760px, (min-width: 960px) 58vw, 100vw';
$media = editorial_image($image, $sizes, $ratio, 'aufm__media', ['eager' => $eager]);
?>
<div class="wrap aufm aufm--<?= e($v) ?><?= $media === '' ? ' aufm--noimg' : '' ?>">
  <div class="aufm__text"><?= $kicker ?><?= $h1 ?><?= $dek ?><?= $by ?><?= $btns ?></div>
  <?php if ($media !== ''): ?>
  <figure class="aufm__fig"><?= $media ?><?= editorial_caption($caption, $credit, is_editing() ? $b->edit('caption') : '') ?></figure>
  <?php endif; ?>
</div>
