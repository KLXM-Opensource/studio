<?php
/**
 * Einstieg (H1): panel (Text + Geräte-Paneel mit Bild oder Punktraster/Regler und nummerierten Kennwerten) ·
 * statement (große Aussage, Kennwerte als Index) · split (Text + Bild) · compact (Seitenkopf).
 * Das Motiv ohne Bild ist ein kleines Inline-SVG (keine Datei, keine Anfrage) – Farben aus den Tokens (CSS-Klassen).
 * Dazu drei „Geräte“-Varianten (Stylesheet css/hero-x.css, nur dort geladen – theme.php → conditional_css):
 *   console  Bedienfeld: Anzeige (Segment-Optik) + Kanäle „Bezeichnung: Wert“ mit Pegel (Prozent/Bruch) oder Drehregler
 *   dials    Kennzahlen: große Aussage + 3–4 Rundinstrumente im Paneel (Core\Blocks\Hero::dials, Kern-Stylesheet über 'uses')
 *   monitor  Video im Geräterahmen: stummes Loop-Video mit Abdunkelung, Pause als Geräte-Taste (Core\Blocks\Hero::video)
 * Anzeigen, LEDs, Regler und Raster sind reine Dekoration (aria-hidden) – die Inhalte stehen als Text/Liste im Markup.
 * @var \Core\Block $b  @var array $d
 */
use Core\Blocks\Hero;

$v = in_array($b->variant(), ['panel', 'statement', 'split', 'compact', 'console', 'dials', 'monitor'], true) ? $b->variant() : 'panel';
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$points = essenz_lines($d['points'] ?? '');
$text = trim((string) $d['text']);
$eager = ['eager' => true];
$eyebrow = trim((string) ($d['eyebrow'] ?? ''));

$title = '<h1 id="' . e($b->titleId()) . '" class="h1 hero__title"' . $b->edit('title') . '>' . essenz_title((string) $d['title']) . '</h1>';
$eb = $eyebrow !== '' ? '<p class="eyebrow hero__eyebrow"' . $b->edit('eyebrow') . '>' . essenz_title($eyebrow) . '</p>' : '';
$lead = ($text !== '' || is_editing()) ? '<p class="hero__lead"' . $b->edit('text') . '>' . nl2br(essenz_title($text), false) . '</p>' : '';

/** Kennwerte: „Bezeichnung: Wert“ → zwei Spalten, sonst eine Zeile; nummeriert */
$readout = function (string $class) use ($points): string {
    if (!$points) return '';
    $h = '<ol class="' . $class . '" role="list">';
    foreach ($points as $i => $p) {
        [$k, $val] = str_contains($p, ':') ? array_map('trim', explode(':', $p, 2)) : [$p, ''];
        $h .= '<li><span class="rd__n" aria-hidden="true">' . essenz_num($i + 1) . '</span><span class="rd__k">' . essenz_title($k) . '</span>'
            . ($val !== '' ? '<span class="rd__v">' . e($val) . '</span>' : '') . '</li>';
    }
    return $h . '</ol>';
};

/** Motiv ohne Bild: Lautsprecher-Punktraster, Drehregler mit Skala, Schieberegler */
$motif = function (): string {
    $dots = '';
    for ($y = 0; $y < 9; $y++) for ($x = 0; $x < 11; $x++) $dots .= '<circle cx="' . (28 + $x * 17) . '" cy="' . (34 + $y * 17) . '" r="3.2"/>';
    return '<svg class="motif" viewBox="0 0 400 300" aria-hidden="true" focusable="false">'
        . '<g class="m-dots">' . $dots . '</g>'
        . '<circle class="m-scale" cx="316" cy="104" r="62" pathLength="100"/>'
        . '<circle class="m-arc" cx="316" cy="104" r="62" pathLength="100"/>'
        . '<circle class="m-knob" cx="316" cy="104" r="44"/>'
        . '<line class="m-ind" x1="316" y1="104" x2="342" y2="82"/>'
        . '<rect class="m-track" x="248" y="224" width="136" height="6" rx="3"/>'
        . '<rect class="m-fill" x="248" y="224" width="84" height="6" rx="3"/>'
        . '<circle class="m-thumb" cx="332" cy="227" r="11"/>'
        . '<rect class="m-track" x="28" y="236" width="178" height="1"/>'
        . '<circle class="m-led" cx="36" cy="270" r="5"/><rect class="m-tick" x="52" y="267" width="40" height="6" rx="3"/>'
        . '</svg>';
};

/** Beschriftung des Geräts (Feld „Beschriftung des Paneels“, sonst Kurzname) */
$devLabel = fn(): string => '<span class="label"' . $b->edit('panel_label') . '>' . (trim((string) ($d['panel_label'] ?? '')) !== '' ? essenz_title(trim((string) $d['panel_label'])) : e(essenz_name(true))) . '</span>';

if ($v === 'console'):
    // Pegel aus dem Wert: „87 %“ → 87, „4 / 5“ oder „4 von 5“ → 80; sonst null (dann Drehregler als Dekor)
    $num = fn(string $s): float => (float) str_replace(',', '.', $s);
    $level = function (string $val) use ($num): ?int {
        if (preg_match('~(\d+(?:[.,]\d+)?)\s*%~u', $val, $m)) return (int) round(max(0, min(100, $num($m[1]))));
        if (preg_match('~(\d+(?:[.,]\d+)?)\s*(?:/|von|of)\s*(\d+(?:[.,]\d+)?)~u', $val, $m) && $num($m[2]) > 0 && $num($m[1]) <= $num($m[2])) {
            return (int) round($num($m[1]) / $num($m[2]) * 100);
        }
        return null;
    };
    $rows = array_slice(Hero::pairs($d['points'] ?? ''), 0, 6);
    $dispVal = trim((string) ($d['display_value'] ?? ''));
    $dispLabel = trim((string) ($d['display_label'] ?? ''));
?>
<div class="wrap hero hz-cn">
  <div class="hero__text">
    <?= $eb ?><?= $title ?><?= $lead ?>
    <?= essenz_buttons($b, 'hero__actions') ?>
  </div>
  <div class="hz-cn__dev panel">
    <div class="hz-cn__top"><?= $devLabel() ?><span class="hz-cn__leds" aria-hidden="true"><i class="is-on"></i><i></i><i></i></span></div>
    <?php if ($dispVal !== '' || is_editing()): ?>
    <p class="hz-cn__disp">
      <span class="hz-cn__dlabel"<?= $b->edit('display_label') ?>><?= essenz_title($dispLabel) ?></span>
      <span class="hz-cn__dval"><span class="hz-cn__ghost" aria-hidden="true"><?= e(preg_replace('~\d~', '8', $dispVal) ?? '') ?></span><span class="hz-cn__dnum"<?= $b->edit('display_value') ?>><?= e($dispVal) ?></span></span>
    </p>
    <?php endif; ?>
    <?php if ($rows): ?>
    <dl class="hz-cn__ch hz-cn__ch--<?= count($rows) ?>">
      <?php foreach ($rows as $i => $r):
          $k = $r['label'] !== '' ? $r['label'] : $r['value'];
          $val = $r['label'] !== '' ? $r['value'] : '';
          $lv = $val !== '' ? $level($val) : null;
      ?>
      <div class="hz-ch">
        <dt class="hz-ch__k"><span class="hz-ch__n" aria-hidden="true"><?= essenz_num($i + 1) ?></span><?= essenz_title($k) ?></dt>
        <dd class="hz-ch__d">
          <?php if ($lv !== null): $on = (int) round($lv / 10); ?>
          <span class="hz-meter" aria-hidden="true"><?php for ($s = 1; $s <= 10; $s++): ?><i<?= $s <= $on ? ' class="is-on"' : '' ?>></i><?php endfor; ?></span>
          <?php else: ?>
          <span class="hz-knob hz-knob--<?= $i % 6 ?>" aria-hidden="true"><i></i></span>
          <?php endif; ?>
          <?php if ($val !== ''): ?><span class="hz-ch__v"><?= e($val) ?></span><?php endif; ?>
        </dd>
      </div>
      <?php endforeach; ?>
    </dl>
    <?php elseif (is_editing()): ?>
    <p class="hz-cn__empty"><?= e(__('Kanäle: im Feld „Kennwerte“ je Zeile „Bezeichnung: Wert“ eintragen.')) ?></p>
    <?php endif; ?>
    <div class="hz-cn__foot" aria-hidden="true"><span class="hz-cn__grille"></span><span class="hz-cn__fader"><i></i></span></div>
  </div>
</div>
<?php elseif ($v === 'dials'): ?>
<div class="wrap hero hz-dl">
  <div class="hero__rule"><?= $eb ?><span class="hero__scale" aria-hidden="true"></span></div>
  <div class="hz-dl__head">
    <?= $title ?>
    <div class="hero__col"><?= $lead ?><?= essenz_buttons($b, 'hero__actions') ?></div>
  </div>
  <?php $dials = Hero::dials($b, $d, 'm'); if ($dials !== ''): ?>
  <div class="hz-dl__panel panel">
    <div class="dev__top"><?= $devLabel() ?><span class="dev__led" aria-hidden="true"></span></div>
    <?= $dials ?>
  </div>
  <?php endif; ?>
</div>
<?php elseif ($v === 'monitor'):
    $video = Hero::video($d, ['sizes' => '(min-width: 1280px) 1200px, 100vw', 'class' => 'hz-mon']);
    $ov = in_array($d['overlay'] ?? '', ['strong', 'medium', 'gradient'], true) ? $d['overlay'] : 'strong';
    $still = $video ? '' : (!empty($d['image']) ? img((int) $d['image'], '(min-width: 1280px) 1200px, 100vw', ['eager' => true, 'class' => 'hz-mon__still']) : '');
?>
<div class="wrap hero hz-mon hz-mon--<?= e($ov) ?>" data-hero-video-box>
  <div class="hz-mon__screen<?= $video || $still !== '' ? '' : ' hz-mon__screen--blank' ?>">
    <?= $video['media'] ?? $still ?>
    <span class="hz-mon__scrim" aria-hidden="true"></span>
    <span class="hz-mon__marks" aria-hidden="true"></span>
    <?php if (!$video && is_editing()): ?><p class="hz-mon__empty"><?= e(__('Bitte ein Video (MP4) wählen – das Bild dient als Standbild.')) ?></p><?php endif; ?>
  </div>
  <div class="hero__text hz-mon__text">
    <?= $eb ?><?= $title ?><?= $lead ?>
    <?= essenz_buttons($b, 'hero__actions') ?>
  </div>
  <div class="hz-mon__chin">
    <?= $devLabel() ?>
    <span class="hz-mon__grille" aria-hidden="true"></span>
    <span class="hz-mon__led" aria-hidden="true"></span>
    <?= $video['toggle'] ?? '' ?>
  </div>
</div>
<?php elseif ($v === 'panel'):
    $label = trim((string) ($d['panel_label'] ?? ''));
    $media = !empty($d['image']) ? essenz_image((int) $d['image'], '(min-width: 1080px) 560px, 100vw', $ratio, 'dev__media', $eager) : '';
?>
<div class="wrap hero hero--panel">
  <div class="hero__text">
    <?= $eb ?><?= $title ?><?= $lead ?>
    <?= essenz_buttons($b, 'hero__actions') ?>
  </div>
  <div class="dev panel">
    <div class="dev__top"><span class="label"<?= $b->edit('panel_label') ?>><?= $label !== '' ? essenz_title($label) : e(essenz_name(true)) ?></span><span class="dev__led" aria-hidden="true"></span></div>
    <?= $media !== '' ? $media : '<div class="dev__motif">' . $motif() . '</div>' ?>
    <?= $readout('rd dev__read') ?>
  </div>
</div>
<?php elseif ($v === 'statement'): ?>
<div class="wrap hero hero--statement">
  <div class="hero__rule"><?= $eb ?><span class="hero__scale" aria-hidden="true"></span></div>
  <?= $title ?>
  <div class="hero__foot">
    <div class="hero__col"><?= $lead ?><?= essenz_buttons($b, 'hero__actions') ?></div>
    <?= $readout('rd hero__index') ?>
  </div>
  <?= !empty($d['image']) || is_editing() ? essenz_image(!empty($d['image']) ? (int) $d['image'] : null, '(min-width: 1280px) 1280px, 100vw', '21:9', 'hero__wide', $eager) : '' ?>
</div>
<?php else:
    $media = $v === 'compact' ? '' : essenz_image(!empty($d['image']) ? (int) $d['image'] : null, '(min-width: 1080px) 600px, 100vw', $ratio, 'hero__media', $eager);
?>
<div class="wrap hero hero--<?= e($v) ?><?= $media !== '' ? ' hero--media' : '' ?>">
  <div class="hero__text">
    <?= $eb ?><?= $title ?><?= $lead ?>
    <?= essenz_buttons($b, 'hero__actions') ?>
    <?= $v === 'split' ? essenz_checks($points, 'hero__points') : '' ?>
  </div>
  <?= $media ?>
</div>
<?php endif;
