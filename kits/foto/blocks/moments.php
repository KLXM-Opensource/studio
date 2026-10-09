<?php
/**
 * Bildstrom – Bilder und Videos als Bento (Kacheln verschiedener Größe im 12er-Raster, dicht gepackt) oder als Strom
 * (große Momente in ihrem eigenen Format, frei gesetzt, mit Textkacheln daneben). Wenig Text: Titel und eine Zeile erscheinen
 * beim Zeigen bzw. auf Touch-Geräten leise auf dem Bild.
 *
 * Hinter jeder Kachel kann etwas stecken: das Bild groß (Lightbox), eine ganze Galerie (Sammlung → Lightbox-Folge, die
 * weiteren Bilder als versteckte Links derselben Gruppe) oder eine Seite (Struktur-Browser). Quelle „Unterseiten“: jede
 * Unterseite wird zur Kachel (Titelbild und Titel aus „Serie (Kopf)“).
 *
 * js/moments.js: Einblenden beim Scrollen (versetzt, je Block wählbar), stumme Video-Schleifen nur im Bild, Nachladen beim
 * Scrollen bzw. per Schaltfläche (weitere Kacheln stehen schon im HTML – ohne JavaScript ist alles sichtbar), Zähler
 * „07 / 24“ (Scrollspy). Bewegung nur mit Design → „Dezente Animationen“ und nie bei „Bewegung reduzieren“.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() === 'stream' ? 'stream' : 'bento';
$tiles = foto_moments($d);
$editing = is_editing();
$fx = in_array($d['motion'] ?? '', ['none', 'fade', 'rise', 'zoom', 'reveal', 'drift'], true) ? (string) $d['motion'] : 'rise';
$rows = in_array($d['row_height'] ?? '', ['s', 'm', 'l'], true) ? (string) $d['row_height'] : 'm';
$more = in_array($d['more'] ?? '', ['all', 'scroll', 'button'], true) ? (string) $d['more'] : 'all';
$batch = max(4, min(60, (int) ($d['batch'] ?? 12) ?: 12));
$labels = in_array($d['labels'] ?? '', ['hover', 'always', 'none'], true) ? (string) $d['labels'] : 'hover';
$spy = !empty($d['spy']) && count($tiles) > 2 && !$editing;
$manual = ($d['source'] ?? 'manual') === 'manual';
$head = foto_head($b);
$label = trim(strip_emphasis((string) ($d['title'] ?? ''))) ?: lt('Bildstrom');
// Spalten je Größe: Bento [Spalten, Zeilen], Strom [Spalten]
$span = ['s' => 4, 'm' => 6, 'wide' => 8, 'tall' => 4, 'l' => 10, 'full' => 12];
$sizes = ['s' => '(min-width: 1100px) 25vw, 50vw', 'm' => '(min-width: 1100px) 33vw, 50vw', 'wide' => '(min-width: 1100px) 50vw, 100vw',
    'tall' => '(min-width: 1100px) 33vw, 50vw', 'l' => '(min-width: 1100px) 66vw, 100vw', 'full' => '100vw'];
$zone = $manual ? foto_drop_zone('tiles', 'list', ['accept' => 'visual']) : '';
$icons = ['gallery' => 'images', 'page' => 'arrow-right'];
?>
<div class="<?= e(foto_gw($d)) ?>">
<?= $head ?>
<?php if ($tiles): ?>
<div class="mo mo--<?= e($v) ?> mo-rh-<?= e($rows) ?> mo-fx-<?= e($fx) ?> mo-lbl-<?= e($labels) ?>" data-mo data-mo-more="<?= e($editing ? 'all' : $more) ?>" data-mo-batch="<?= (int) $batch ?>">
  <ul class="mo__grid" role="list" aria-label="<?= e($label) ?>">
  <?php foreach ($tiles as $n => $t):
      $m = $t['m'];
      $size = $t['size'];
      // Strom: Lage im Raster (Mitte / rechts) – „Automatisch“ fließt
      $col = '';
      if ($v === 'stream' && $t['place'] !== 'auto' && $span[$size] < 12) {
          $col = ' mo-c' . ($t['place'] === 'end' ? 13 - $span[$size] : ($t['place'] === 'center' ? intdiv(12 - $span[$size], 2) + 1 : 1));
      }
      $cls = 'mo__t mo-' . $size . ' mo-tone-' . $t['tone'] . $col . (!$m ? ' mo__t--text' : '') . ($t['video'] ? ' mo__t--video' : '')
          . (!$editing && $more !== 'all' && $n >= $batch ? ' mo__later' : '');
      $ratio = '';
      if ($v === 'stream' && $m) {
          $mw = (int) ($m['width'] ?? 0); $mh = (int) ($m['height'] ?? 0);
          $ratio = foto_nearest_ratio($mw, $mh, '3:2');
      }
      $txt = $t['title'] !== '' || $t['text'] !== '' || $editing
          ? '<span class="mo__lbl">'
            . ($t['title'] !== '' || ($editing && $t['path']) ? '<span class="mo__ti"' . ($editing && $t['path'] ? $b->edit($t['path'] . '.title') : '') . '>' . foto_title($t['title']) . '</span>' : '')
            . ($t['text'] !== '' || ($editing && $t['path']) ? '<span class="mo__tx"' . ($editing && $t['path'] ? $b->edit($t['path'] . '.text') : '') . '>' . foto_title($t['text']) . '</span>' : '')
            . '</span>'
          : '';
      $badge = $t['open'] === 'gallery'
          ? '<span class="mo__badge" aria-hidden="true">' . icon($icons['gallery']) . '<span>' . (count($t['gallery']) + 1) . '</span></span>'
          : (in_array($t['open'], ['page'], true) ? '<span class="mo__badge mo__badge--go" aria-hidden="true">' . icon($icons['page']) . '</span>' : '');
      $media = $m ? '<span class="mo__m' . ($ratio !== '' ? ' ' . e(foto_ratio_class($ratio)) : '') . '">' . foto_mo_media($m, $sizes[$size], '', $b->prev === null && $n < 3) . '</span>' : '';
      $inner = $media . $txt . $badge;
      $cap = trim($t['title'] . ($t['title'] !== '' && $t['text'] !== '' ? ' – ' : '') . $t['text']);
      $sr = match ($t['open']) {
          'gallery' => '<span class="sr-only"> – ' . e(lt('Galerie mit {n} Bildern öffnen', ['n' => count($t['gallery']) + 1])) . '</span>',
          'zoom' => $m && !$t['video'] && \Core\Media::alt($m) === '' && $cap === '' ? '<span class="sr-only">' . e(lt('Bild {n} vergrößern', ['n' => $n + 1])) . '</span>' : '',
          default => '',
      };
      if ($editing || $t['open'] === 'none') {
          $body = '<div class="mo__a">' . $inner . ($editing && $manual ? foto_item_tools('tiles', (int) $t['i'], $n, count($tiles)) : '') . '</div>';
      } elseif ($t['open'] === 'page') {
          $body = '<a class="mo__a" href="' . e($t['href']) . '"' . ext_attrs($t['href']) . '>' . $inner . foto_ext_note($t['href']) . '</a>';
      } else {
          $body = foto_lb_link($m, $cap, 'mo__a', $inner . $sr);
          foreach ($t['gallery'] as $g) $body .= foto_lb_link($g, trim(\Core\Media::title($g)), 'mo__more', '', true);
      }
      $pause = $t['video'] && !$editing ? '<button type="button" class="mo__pause" data-mo-pause hidden aria-pressed="false"><span class="sr-only">' . e(lt('Video anhalten')) . '</span></button>' : '';
  ?>
    <li class="<?= e($cls) ?>" data-mo-tile<?= $t['open'] === 'gallery' || $t['open'] === 'zoom' ? ' data-lb-group' : '' ?><?= $t['title'] !== '' ? ' data-mo-title="' . e(strip_emphasis($t['title'])) . '"' : '' ?>><?= $body ?><?= $pause ?></li>
  <?php endforeach; ?>
  </ul>
  <?php if (!$editing && $more !== 'all' && count($tiles) > $batch): ?>
  <p class="mo__foot"><button type="button" class="btn btn--secondary mo__btn" data-mo-load hidden><?= e(lt('Mehr zeigen')) ?></button><span class="sr-only" role="status" aria-live="polite" data-mo-status data-msg="<?= e(lt('{n} weitere Bilder geladen')) ?>"></span></p>
  <?php endif; ?>
  <?php if ($spy): ?>
  <p class="mo__spy" aria-hidden="true" hidden><span class="mo__spy-n" data-mo-n>01</span><span class="mo__spy-of"> / <?= str_pad((string) count($tiles), 2, '0', STR_PAD_LEFT) ?></span><span class="mo__spy-t" data-mo-t></span></p>
  <?php endif; ?>
</div>
<?= !$editing ? foto_lightbox() : '' ?>
<?php endif; ?>
<?= $zone ?>
</div>
