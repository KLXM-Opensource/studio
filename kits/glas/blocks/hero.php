<?php
/**
 * Einstieg (H1):
 *   aurora     Glaspaneel mit Text und Buttons über einem sanft bewegten Farbfeld (Aurora); daneben schwebende Glasplättchen
 *              mit Kennwerten, ein Bild im Glasrahmen oder – ohne beides – ein gläsernes Prisma (reine Gestaltung)
 *   split      Text + Bild im Glasrahmen, Pluspunkte als Liste
 *   statement  große Aussage direkt auf dem Farbfeld, Kennwerte als Glas-Chips, optional ein breites Bild
 *   compact    Seitenkopf für Unterseiten (mit kleiner Glasperle als Gestaltung)
 *   command    Aurora mit gläserner Befehlssuche: großes Suchfeld (Vorschläge beim Tippen) + Vorschläge als Chips
 *   video      stummes Loop-Video vollflächig, Text auf einem Glaspaneel, Abdunkelung, Pause-Schaltfläche
 *   stack      Text + 2–3 überlappende Glaskarten (Angebote), die sich bei Zeiger/Fokus auffächern
 * Die drei neuen Varianten laden ihr Stylesheet nur bei Bedarf (theme.php → conditional_css: h-command, h-video, h-stack).
 * Die Aurora sind vier Flecken aus radialen Verläufen (keine Datei, kein Filter) – bewegt nur mit „Sanfte Bewegung“, ohne
 * „Bewegung reduzieren“ und nur, solange der Einstieg sichtbar ist (site.js). Alle Texte stehen auf Glas oder auf dem
 * abgeschwächten Farbfeld – beides kontrastgeprüft (tools/contrast.php).
 * @var \Core\Block $b  @var array $d
 */
use Core\Blocks\Hero;

$v = in_array($b->variant(), ['aurora', 'split', 'statement', 'compact', 'command', 'video', 'stack'], true) ? $b->variant() : 'aurora';
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$points = glas_lines($d['points'] ?? '');
$text = trim((string) $d['text']);
$eager = ['eager' => true];
$eyebrow = trim((string) ($d['eyebrow'] ?? ''));

$title = '<h1 id="' . e($b->titleId()) . '" class="h1 hero__title"' . $b->edit('title') . '>' . glas_title((string) $d['title']) . '</h1>';
$eb = $eyebrow !== '' ? '<p class="eyebrow hero__eyebrow"' . $b->edit('eyebrow') . '>' . glas_title($eyebrow) . '</p>' : '';
$lead = ($text !== '' || is_editing()) ? '<p class="hero__lead"' . $b->edit('text') . '>' . nl2br(glas_title($text), false) . '</p>' : '';
$aurora = '<div class="aurora" aria-hidden="true" data-aurora><span class="aurora__b aurora__b--1"></span><span class="aurora__b aurora__b--2"></span>'
    . '<span class="aurora__b aurora__b--3"></span><span class="aurora__ribbon"></span></div>';

/** Kennwerte: „Bezeichnung: Wert“ → Plättchen/Chip mit großer Zahl, sonst eine Zeile */
$tiles = function (string $class) use ($points): string {
    if (!$points) return '';
    $h = '<ul class="' . $class . '" role="list">';
    foreach ($points as $i => $p) {
        [$k, $val] = str_contains($p, ':') ? array_map('trim', explode(':', $p, 2)) : [$p, ''];
        $h .= '<li class="tile glass">' . ($val !== '' ? '<span class="tile__v">' . e($val) . '</span>' : '') . '<span class="tile__k">' . glas_title($k) . '</span></li>';
    }
    return $h . '</ul>';
};

/** Gläsernes Prisma ohne Bild und ohne Kennwerte: drei überlappende Glasscheiben mit Lichtkante (reine Gestaltung) */
$prism = '<div class="prism" aria-hidden="true"><span class="prism__pane prism__pane--1 glass"></span><span class="prism__pane prism__pane--2 glass"></span><span class="prism__pane prism__pane--3 glass"></span><span class="prism__orb"></span></div>';

if ($v === 'aurora'):
    $media = !empty($d['image']) ? glas_image((int) $d['image'], '(min-width: 1080px) 520px, 100vw', $ratio, 'gframe hero__media', $eager) : '';
?>
<?= $aurora ?>
<div class="wrap hero hero--aurora">
  <div class="hero__glass glass glass--strong">
    <?= $eb ?><?= $title ?><?= $lead ?>
    <?= glas_buttons($b, 'hero__actions') ?>
  </div>
  <div class="hero__side">
    <?= $media !== '' ? $media : ($points ? $tiles('tiles hero__tiles') : $prism) ?>
    <?= $media !== '' ? $tiles('tiles tiles--row hero__tiles') : '' ?>
  </div>
</div>
<?php elseif ($v === 'statement'): ?>
<div class="wrap hero hero--statement">
  <?= $eb ?>
  <?= $title ?>
  <div class="hero__foot">
    <div class="hero__col"><?= $lead ?><?= glas_buttons($b, 'hero__actions') ?></div>
    <?= $tiles('tiles tiles--chips hero__chips') ?>
  </div>
  <?= !empty($d['image']) || is_editing() ? glas_image(!empty($d['image']) ? (int) $d['image'] : null, '(min-width: 1280px) 1280px, 100vw', '21:9', 'gframe hero__wide', $eager) : '' ?>
</div>
<?php elseif ($v === 'split'):
    $media = glas_image(!empty($d['image']) ? (int) $d['image'] : null, '(min-width: 1080px) 600px, 100vw', $ratio, 'gframe hero__media', $eager);
?>
<div class="wrap hero hero--split<?= $media !== '' ? ' hero--media' : '' ?>">
  <div class="hero__text">
    <?= $eb ?><?= $title ?><?= $lead ?>
    <?= glas_buttons($b, 'hero__actions') ?>
    <?= glas_checks($points, 'hero__points') ?>
  </div>
  <?= $media ?>
</div>
<?php elseif ($v === 'command'):
    // Befehlssuche: Glasfenster mit großem Suchfeld (Core\Search, Vorschläge beim Tippen) und Vorschlägen als Chips
    $form = Hero::search($d);
    $chips = Hero::chips($d);
    $pause = '<label class="gpause"><input class="gpause__in" type="checkbox" role="switch"><span class="gpause__knob" aria-hidden="true"></span>'
        . '<span class="gpause__txt">' . e(lt('Hintergrundbewegung anhalten')) . '</span></label>';
?>
<?= $aurora ?>
<div class="wrap hero hero--command">
  <div class="hero__text gcmd__head">
    <?= $eb ?><?= $title ?><?= $lead ?>
  </div>
  <div class="gcmd glass glass--strong">
    <?= $form !== '' ? $form : (is_editing() ? '<p class="gcmd__empty">' . e(__('Die Website-Suche ist ausgeschaltet (Grundeinstellungen → Funktionen) – es erscheinen nur die Links.')) . '</p>' : '') ?>
    <?php if ($chips): ?>
    <nav class="gcmd__sug" aria-label="<?= e(lt('Häufig gesucht')) ?>">
      <p class="gcmd__label" aria-hidden="true"><?= e(lt('Häufig gesucht')) ?></p>
      <ul class="gcmd__chips" role="list"><?php foreach ($chips as $c): $isQuery = str_contains($c['href'], '?q='); ?>
        <li><a class="gcmd__chip" href="<?= e($c['href']) ?>"><span class="gcmd__ico"><?= icon($isQuery ? 'magnifying-glass' : 'arrow-up-right') ?></span><span><?= e($c['label']) ?></span></a></li><?php endforeach; ?>
      </ul>
    </nav>
    <?php endif; ?>
    <?php if ($form !== ''): ?><p class="gcmd__keys" aria-hidden="true"><kbd>↑</kbd><kbd>↓</kbd> <?= e(lt('Vorschläge')) ?> <kbd>↵</kbd> <?= e(lt('öffnen')) ?> <kbd>Esc</kbd> <?= e(lt('schließen')) ?></p><?php endif; ?>
  </div>
  <?= glas_buttons($b, 'hero__actions gcmd__actions') ?>
  <?= $pause ?>
</div>
<?php elseif ($v === 'video'):
    // Video-Hero: Video (bzw. Standbild) füllt den Abschnitt, Abdunkelung darüber, Text auf einem dichten Glaspaneel
    $vid = Hero::video($d, ['sizes' => '100vw', 'class' => 'gvid']);
    $ov = in_array($d['overlay'] ?? '', ['strong', 'medium', 'gradient'], true) ? $d['overlay'] : 'strong';
    $still = !$vid && !empty($d['image']) ? img((int) $d['image'], '100vw', ['eager' => true, 'alt' => '', 'class' => 'gvid__still']) : '';
?>
<?php if ($vid || $still !== ''): ?>
<div class="gvid gvid--<?= e($ov) ?>" data-hero-video-box>
  <?= $vid ? $vid['media'] : $still ?>
  <span class="gvid__scrim" aria-hidden="true"></span>
  <?= $vid ? $vid['toggle'] : '' ?>
</div>
<?php else: ?>
<div class="gvid gvid--none" aria-hidden="true"><span class="gvid__scrim"></span></div>
<?php endif; ?>
<div class="wrap hero hero--video">
  <div class="hero__glass glass glass--strong gvid__panel">
    <?= $eb ?><?= $title ?><?= $lead ?>
    <?= glas_buttons($b, 'hero__actions') ?>
    <?php if (!$vid && is_editing()): ?><p class="gvid__empty"><?= e(__('Bitte in der Seitenleiste ein Video (MP4) wählen – bis dahin erscheint ein Farbverlauf bzw. das Bild.')) ?></p><?php endif; ?>
  </div>
</div>
<?php elseif ($v === 'stack'):
    // Karten-Stapel: bis zu drei Glaskarten übereinander, fächern bei Zeiger oder Tastaturfokus auf (ohne Bewegung bei „Bewegung reduzieren“)
    $cards = array_slice(array_values(array_filter((array) ($d['cards'] ?? []), fn($c) => is_array($c) && (trim((string) ($c['title'] ?? '')) !== '' || is_editing()))), 0, 3);
?>
<div class="wrap hero hero--stack">
  <div class="hero__text">
    <?= $eb ?><?= $title ?><?= $lead ?>
    <?= glas_buttons($b, 'hero__actions') ?>
    <?= glas_checks($points, 'hero__points') ?>
  </div>
  <?php if ($cards): ?>
  <ul class="gstack gstack--<?= count($cards) ?>" role="list">
    <?php foreach ($cards as $i => $c):
        $link = trim((string) ($c['link'] ?? ''));
        $label = trim((string) ($c['link_label'] ?? ''));
        $ctitle = (string) ($c['title'] ?? '');
    ?>
    <li class="gstack__card glass glass--strong<?= $link !== '' ? ' gstack__card--link' : '' ?>">
      <?php if (!empty($c['icon'])): ?><span class="gstack__ico"><?= icon((string) $c['icon']) ?></span><?php endif; ?>
      <p class="gstack__title"><?php if ($link !== '' && !is_editing()): ?><a class="cover-link" <?= glas_link_attrs($link) ?>><?= glas_title($ctitle) ?><?= glas_ext_note(glas_link($link)) ?></a><?php else: ?><span<?= $b->edit("cards.$i.title") ?>><?= glas_title($ctitle) ?></span><?php endif; ?></p>
      <?php if (trim((string) ($c['text'] ?? '')) !== ''): ?><p class="gstack__text"<?= $b->edit("cards.$i.text") ?>><?= glas_title((string) $c['text']) ?></p><?php endif; ?>
      <?php if ($link !== ''): ?><span class="gstack__go" aria-hidden="true"><?= $label !== '' ? '<span>' . e($label) . '</span>' : '' ?><?= icon('arrow-right') ?></span><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= e(__('Noch keine Karten – in der Seitenleiste unter „Karten“ hinzufügen.')) ?></p>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="wrap hero hero--compact">
  <div class="hero__text">
    <?= $eb ?><?= $title ?><?= $lead ?>
    <?= glas_buttons($b, 'hero__actions') ?>
  </div>
  <span class="hero__orb" aria-hidden="true"></span>
</div>
<?php endif;
