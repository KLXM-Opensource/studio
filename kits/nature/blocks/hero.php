<?php
/**
 * Einstieg (H1): panel (Text + Bildtafel mit Bild oder Landschafts-Illustration und Eckdaten) ·
 * statement (große Aussage, Eckdaten als Liste) · split (Text + Bild) · compact (Seitenkopf).
 * Die Landschaft ohne Bild ist ein Inline-SVG (nature_landscape – keine Datei, keine Anfrage), Farben aus den Tokens,
 * Details je Jahreszeit (Design → Jahreszeit).
 * Dazu drei Varianten mit eigenem Stylesheet (nur dort geladen, theme.php → conditional_css):
 *   season  Jahreszeiten-Bühne: Panorama (Inline-SVG, Jahreszeit fest oder nach Monat) + Hofschild „Jetzt geöffnet“ mit
 *           Öffnungszeiten aus „Website“ (Status im Browser berechnet, site.js – der Seiten-Cache bleibt gültig)  css/b-hero-season.css
 *   dates   Anhänger am Ast: die nächsten Termine einer Datentabelle (Core\Blocks\Hero::dates)             css/b-hero-dates.css
 *   form    Papierkarte mit kompaktem Formular (Hero::form, Kit-Formularstile über 'uses' → data_form)    css/b-hero-form.css
 * @var \Core\Block $b  @var array $d
 */
use Core\Blocks\Hero;

$v = in_array($b->variant(), ['panel', 'statement', 'split', 'compact', 'season', 'dates', 'form'], true) ? $b->variant() : 'panel';
$ratio = (string) ($d['ratio'] ?? '') ?: '4:5';
$points = nature_lines($d['points'] ?? '');
$text = trim((string) $d['text']);
$eager = ['eager' => true];
$eyebrow = trim((string) ($d['eyebrow'] ?? ''));

$title = '<h1 id="' . e($b->titleId()) . '" class="h1 hero__title"' . $b->edit('title') . '>' . nature_title((string) $d['title']) . '</h1>';
$eb = $eyebrow !== '' ? '<p class="eyebrow hero__eyebrow"' . $b->edit('eyebrow') . '>' . nature_title($eyebrow) . '</p>' : '';
$lead = ($text !== '' || is_editing()) ? '<p class="hero__lead"' . $b->edit('text') . '>' . nl2br(nature_title($text), false) . '</p>' : '';

/** Kennwerte: „Bezeichnung: Wert“ → zwei Spalten, sonst eine Zeile; nummeriert */
$readout = function (string $class) use ($points): string {
    if (!$points) return '';
    $h = '<ol class="' . $class . '" role="list">';
    foreach ($points as $i => $p) {
        [$k, $val] = str_contains($p, ':') ? array_map('trim', explode(':', $p, 2)) : [$p, ''];
        $h .= '<li><span class="rd__n" aria-hidden="true">' . nature_num($i + 1) . '</span><span class="rd__k">' . nature_title($k) . '</span>'
            . ($val !== '' ? '<span class="rd__v">' . e($val) . '</span>' : '') . '</li>';
    }
    return $h . '</ol>';
};

if ($v === 'panel'):
    $label = trim((string) ($d['panel_label'] ?? ''));
    $media = !empty($d['image']) ? nature_image((int) $d['image'], '(min-width: 1080px) 560px, 100vw', $ratio, 'tafel__media', $eager) : '';
?>
<div class="wrap hero hero--panel">
  <div class="hero__text">
    <?= $eb ?><?= $title ?><?= $lead ?>
    <?= nature_buttons($b, 'hero__actions') ?>
  </div>
  <div class="tafel panel">
    <div class="tafel__top"><span class="label"<?= $b->edit('panel_label') ?>><?= $label !== '' ? nature_title($label) : e(nature_name(true)) ?></span><span class="tafel__leaf" aria-hidden="true"></span></div>
    <?= $media !== '' ? $media : '<div class="tafel__motif">' . nature_landscape() . '</div>' ?>
    <?= $readout('rd tafel__read') ?>
  </div>
</div>
<?php elseif ($v === 'statement'): ?>
<div class="wrap hero hero--statement">
  <div class="hero__rule"><?= $eb ?><span class="hero__scale" aria-hidden="true"></span></div>
  <?= $title ?>
  <div class="hero__foot">
    <div class="hero__col"><?= $lead ?><?= nature_buttons($b, 'hero__actions') ?></div>
    <?= $readout('rd hero__index') ?>
  </div>
  <?= !empty($d['image']) || is_editing() ? nature_image(!empty($d['image']) ? (int) $d['image'] : null, '(min-width: 1280px) 1280px, 100vw', '21:9', 'hero__wide', $eager) : '' ?>
</div>
<?php elseif ($v === 'season'):
    // Jahreszeit: fest gewählt oder „automatisch“ nach Monat (serverseitig – mit Seiten-Cache: Stand der letzten Leerung)
    $season = (string) ($d['season_scene'] ?? 'auto');
    if (!in_array($season, ['fruehling', 'sommer', 'herbst', 'winter'], true)) $season = nature_season_now();
    $hours = ($d['sign_hours'] ?? true) ? nature_hours() : [];
    $sign = trim((string) ($d['sign_label'] ?? ''));
    $seasonName = ['fruehling' => lt('Frühling'), 'sommer' => lt('Sommer'), 'herbst' => lt('Herbst'), 'winter' => lt('Winter')][$season];

    /**
     * Panorama 1200 × 440 als Inline-SVG: drei Hügelketten (Sinuswellen, weich verbunden), Hof mit Scheune und Silo, Bäume,
     * Zaun, Ackerfurchen – und je Jahreszeit eigene Details. Nur Klassen (Farben aus den Tokens, css/b-hero-season.css),
     * kein style-Attribut (CSP). Deterministisch (fester Zufallsstart), rein dekorativ (aria-hidden).
     */
    $scene = function (string $s, string $uid): string {
        $f = fn(float $v) => rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');
        $wave = fn(float $base, array $w) => function (float $x) use ($base, $w): float {
            $y = $base;
            foreach ($w as [$a, $k, $ph]) $y += sin($x * $k + $ph) * $a;
            return $y;
        };
        $hill = function (callable $y) use ($f): string {   // Catmull-Rom → kubische Bézier, unten geschlossen
            $p = [];
            for ($x = -100; $x <= 1300; $x += 50) $p[] = [$x, $y($x)];
            $n = count($p);
            $d = 'M-100 440V' . $f($p[0][1]);
            for ($i = 0; $i < $n - 1; $i++) {
                [$a, $b, $c, $e] = [$p[max(0, $i - 1)], $p[$i], $p[$i + 1], $p[min($n - 1, $i + 2)]];
                $d .= 'C' . $f($b[0] + ($c[0] - $a[0]) / 6) . ' ' . $f($b[1] + ($c[1] - $a[1]) / 6) . ' ' . $f($c[0] - ($e[0] - $b[0]) / 6) . ' ' . $f($c[1] - ($e[1] - $b[1]) / 6) . ' ' . $f($c[0]) . ' ' . $f($c[1]);
            }
            return $d . 'V440Z';
        };
        $h3 = $wave(250, [[24, .0062, 1.2], [11, .017, .4]]);
        $h2 = $wave(306, [[20, .0052, 3.1], [9, .013, 2.0]]);
        $h1 = $wave(372, [[16, .0041, .3], [7, .011, 1.7]]);
        mt_srand(1911 + crc32($s) % 97);
        $winter = $s === 'winter';

        // Sonne (Stand je Jahreszeit), Strahlen im Sommer, Wolken, Vögel
        [$sx, $sy, $sr] = ['fruehling' => [792, 112, 36], 'sommer' => [800, 98, 42], 'herbst' => [786, 142, 36], 'winter' => [780, 158, 30]][$s];
        $sky = '<circle class="hs__sun" cx="' . $sx . '" cy="' . $sy . '" r="' . $sr . '"/>';
        if ($s === 'sommer') {
            $r = '';
            for ($k = 0; $k < 16; $k++) {
                $a = deg2rad($k * 22.5 + 6);
                $r .= 'M' . $f($sx + cos($a) * ($sr + 12)) . ' ' . $f($sy + sin($a) * ($sr + 12)) . 'L' . $f($sx + cos($a) * ($sr + 24)) . ' ' . $f($sy + sin($a) * ($sr + 24));
            }
            $sky = '<path class="hs__rays" d="' . $r . '"/>' . $sky;
        }
        $cloud = fn(float $x, float $y, float $w) => '<path d="M' . $f($x) . ' ' . $f($y) . 'h' . $f($w) . 'a' . $f($w * .16) . ' ' . $f($w * .16) . ' 0 0 0-' . $f($w * .2) . '-' . $f($w * .2)
            . 'a' . $f($w * .24) . ' ' . $f($w * .24) . ' 0 0 0-' . $f($w * .42) . '-' . $f($w * .06) . 'a' . $f($w * .17) . ' ' . $f($w * .17) . ' 0 0 0-' . $f($w * .38) . ' ' . $f($w * .26) . 'Z"/>';
        $sky .= '<g class="hs__clouds">' . $cloud(700, 186, 90) . $cloud(1150, 130, 100) . '</g>';
        if (in_array($s, ['fruehling', 'sommer'], true)) $sky .= '<path class="hs__birds" d="M912 150q7-7 14 0q7-7 14 0M954 172q5-5 10 0q5-5 10 0M884 178q4-4 8 0q4-4 8 0"/>';

        // Bäume: Laubbäume (Krone oder im Winter kahle Äste), Nadelbäume
        $trunks = $crowns = $bare = $firs = $bloom = '';
        $leafy = function (float $x, float $y, float $r) use (&$trunks, &$crowns, &$bare, &$bloom, $f, $s): void {
            $trunks .= '<rect x="' . $f($x - $r * .09) . '" y="' . $f($y - $r * 1.5) . '" width="' . $f($r * .18) . '" height="' . $f($r * 1.55) . '" rx="' . $f($r * .06) . '"/>';
            if ($s === 'winter') {
                $t = $y - $r * 1.35;
                $bare .= 'M' . $f($x) . ' ' . $f($t) . 'l' . $f(-$r * .55) . ' ' . $f(-$r * .75) . 'M' . $f($x) . ' ' . $f($t + $r * .2) . 'l' . $f($r * .6) . ' ' . $f(-$r * .7)
                    . 'M' . $f($x) . ' ' . $f($t) . 'v' . $f(-$r * .95) . 'M' . $f($x - $r * .3) . ' ' . $f($t - $r * .4) . 'l' . $f(-$r * .3) . ' ' . $f(-$r * .1)
                    . 'M' . $f($x + $r * .32) . ' ' . $f($t - $r * .35) . 'l' . $f($r * .25) . ' ' . $f(-$r * .2);
                return;
            }
            $cy = $y - $r * 1.55;
            $crowns .= '<circle cx="' . $f($x) . '" cy="' . $f($cy) . '" r="' . $f($r) . '"/><circle cx="' . $f($x - $r * .55) . '" cy="' . $f($cy + $r * .35) . '" r="' . $f($r * .62) . '"/><circle cx="' . $f($x + $r * .6) . '" cy="' . $f($cy + $r * .3) . '" r="' . $f($r * .66) . '"/>';
            if ($s === 'fruehling' || $s === 'herbst') {
                for ($i = 0; $i < 7; $i++) {
                    $a = mt_rand(0, 628) / 100; $dist = mt_rand(20, 85) / 100 * $r;
                    $bloom .= '<circle cx="' . $f($x + cos($a) * $dist) . '" cy="' . $f($cy + sin($a) * $dist * .8) . '" r="' . $f(max(2.2, $r * .085)) . '"/>';
                }
            }
        };
        $fir = function (float $x, float $y, float $h) use (&$firs, &$trunks, $f): void {
            $w = $h * .42;
            $firs .= 'M' . $f($x) . ' ' . $f($y - $h) . 'L' . $f($x + $w * .7) . ' ' . $f($y - $h * .45) . 'H' . $f($x + $w * .35) . 'L' . $f($x + $w) . ' ' . $f($y - $h * .08)
                . 'H' . $f($x - $w) . 'L' . $f($x - $w * .35) . ' ' . $f($y - $h * .45) . 'H' . $f($x - $w * .7) . 'Z';
            $trunks .= '<rect x="' . $f($x - 2) . '" y="' . $f($y - $h * .1) . '" width="4" height="' . $f($h * .14) . '"/>';
        };
        // Bäume einer Hügelkette als Gruppen ausgeben (dann zurücksetzen – die nächste Kette liegt davor)
        $trees = function () use (&$trunks, &$crowns, &$bare, &$firs, &$bloom): string {
            $h = ($firs !== '' ? '<path class="hs__fir" d="' . $firs . '"/>' : '') . '<g class="hs__trunk">' . $trunks . '</g>'
                . ($crowns !== '' ? '<g class="hs__crown">' . $crowns . '</g>' : '') . ($bare !== '' ? '<path class="hs__bare" d="' . $bare . '"/>' : '')
                . ($bloom !== '' ? '<g class="hs__bloom">' . $bloom . '</g>' : '');
            $trunks = $crowns = $bare = $firs = $bloom = '';
            return $h;
        };
        foreach ([40, 78, 118, 150, 196] as $i => $x) $fir($x, $h3($x) + 4, 34 + ($i % 3) * 8);
        foreach ([1060, 1100, 1150] as $i => $x) $leafy($x, $h3($x) + 3, 16 + $i * 3);
        $far = $trees();
        $leafy(400, $h2(400) + 5, 36);
        $leafy(458, $h2(458) + 4, 24);
        foreach ([1004, 1034] as $i => $x) $fir($x, $h2($x) + 5, 58 - $i * 12);
        $mid = $trees();

        $leafy(1130, $h1(1130) + 10, 44);
        $near = $trees();

        // Hof: Scheune mit Mansarddach, Tor, Silo; Rauch im Herbst und Winter
        $bx = 850; $by = $h2($bx) + 6;
        $farm = '<path class="hs__barn" d="M' . ($bx - 48) . ' ' . $f($by) . 'V' . $f($by - 46) . 'H' . ($bx + 48) . 'V' . $f($by) . 'Z"/>'
            . '<path class="hs__roof" d="M' . ($bx - 56) . ' ' . $f($by - 44) . 'L' . ($bx - 40) . ' ' . $f($by - 72) . 'L' . $bx . ' ' . $f($by - 90) . 'L' . ($bx + 40) . ' ' . $f($by - 72) . 'L' . ($bx + 56) . ' ' . $f($by - 44) . 'Z"/>'
            . '<path class="hs__door" d="M' . ($bx - 15) . ' ' . $f($by) . 'V' . $f($by - 30) . 'H' . ($bx + 15) . 'V' . $f($by) . 'ZM' . ($bx - 15) . ' ' . $f($by - 30) . 'L' . ($bx + 15) . ' ' . $f($by) . 'M' . ($bx + 15) . ' ' . $f($by - 30) . 'L' . ($bx - 15) . ' ' . $f($by) . '"/>'
            . '<circle class="hs__door" cx="' . $bx . '" cy="' . $f($by - 64) . '" r="7"/>'
            . '<path class="hs__silo" d="M' . ($bx + 62) . ' ' . $f($by) . 'V' . $f($by - 70) . 'a16 16 0 0 1 32 0V' . $f($by) . 'Z"/>'
            . '<path class="hs__chimney" d="M' . ($bx - 30) . ' ' . $f($by - 76) . 'v-18h9v12Z"/>';
        if ($winter) {
            $farm .= '<path class="hs__snowcap" d="M' . ($bx - 58) . ' ' . $f($by - 43) . 'L' . ($bx - 41) . ' ' . $f($by - 74) . 'L' . $bx . ' ' . $f($by - 93) . 'L' . ($bx + 41) . ' ' . $f($by - 74) . 'L' . ($bx + 58) . ' ' . $f($by - 43)
                . 'l-6 3L' . ($bx + 36) . ' ' . $f($by - 68) . 'L' . $bx . ' ' . $f($by - 84) . 'L' . ($bx - 36) . ' ' . $f($by - 68) . 'L' . ($bx - 52) . ' ' . $f($by - 40) . 'Z'
                . 'M' . ($bx + 62) . ' ' . $f($by - 70) . 'a16 16 0 0 1 32 0q-16-7-32 0Z"/>';
        }
        if ($s === 'herbst' || $winter) {
            $farm .= '<path class="hs__smoke" d="M' . ($bx - 25) . ' ' . $f($by - 98) . 'c-10-12 10-18 0-30s8-20 2-32"/>';
        }

        // Zaun am Feldrand, Furchen im Acker (auf die vordere Hügelkette zugeschnitten)
        $posts = $rail1 = $rail2 = '';
        for ($x = 24; $x <= 384; $x += 36) {
            $y = $h1($x);
            $posts .= '<rect x="' . ($x - 2.5) . '" y="' . $f($y - 26) . '" width="5" height="30" rx="1.5"/>';
            $rail1 .= ($rail1 === '' ? 'M' : 'L') . $x . ' ' . $f($y - 20);
            $rail2 .= ($rail2 === '' ? 'M' : 'L') . $x . ' ' . $f($y - 9);
        }
        $vx = 700; $vy = $h1($vx) - 60;
        $furrows = '';
        for ($i = -8; $i <= 22; $i++) $furrows .= 'M' . ($i * 90 - 300) . ' 470L' . $f($vx + ($i - 7) * 7) . ' ' . $f($vy);

        // Jahreszeiten-Details im Vordergrund
        $fore = '';
        if ($s === 'fruehling') {
            $a = $b2 = '';
            for ($i = 0; $i < 70; $i++) {
                $x = mt_rand(0, 1200); $y = $h1($x) + mt_rand(10, 66); $r = mt_rand(20, 38) / 10;
                $c = '<circle cx="' . $x . '" cy="' . $y . '" r="' . $f($r) . '"/>';
                if ($i % 2) $a .= $c; else $b2 .= $c;
            }
            for ($i = 0; $i < 26; $i++) { $x = mt_rand(480, 1200); $b2 .= '<circle cx="' . $x . '" cy="' . $f($h2($x) + mt_rand(8, 40)) . '" r="2"/>'; }
            $fore = '<g class="hs__flowers">' . $a . '</g><g class="hs__flowers hs__flowers--b">' . $b2 . '</g>';
        } elseif ($s === 'sommer') {
            $t = '';
            for ($i = 0; $i < 170; $i++) { $x = mt_rand(0, 1200); $y = $h1($x) + mt_rand(6, 70); $t .= 'M' . $x . ' ' . $y . 'l' . mt_rand(-2, 2) . '-' . mt_rand(7, 12); }
            $fore = '<path class="hs__wheat" d="' . $t . '"/>';
            foreach ([560, 612, 668, 1180] as $x) {
                $y = $h2($x) - 10;
                $fore .= '<circle class="hs__bale" cx="' . $x . '" cy="' . $f($y) . '" r="13"/><path class="hs__bale-line" d="M' . $x . ' ' . $f($y) . 'm-4 0a4 4 0 1 1 4 4a8 8 0 1 1 8-8"/>';
            }
        } elseif ($s === 'herbst') {
            foreach ([[520, 18], [566, 14], [604, 20], [650, 13], [930, 16], [972, 12], [1010, 17]] as [$x, $w]) {
                $y = $h1($x) + 22 + ($x % 3) * 6;
                $fore .= '<ellipse class="hs__pumpkin" cx="' . $x . '" cy="' . $f($y) . '" rx="' . $w . '" ry="' . $f($w * .72) . '"/>'
                    . '<path class="hs__pumpkin-rib" d="M' . $x . ' ' . $f($y - $w * .7) . 'v' . $f($w * 1.4) . 'M' . $f($x - $w * .45) . ' ' . $f($y - $w * .62) . 'q-5 ' . $f($w * .62) . ' 0 ' . $f($w * 1.24)
                    . 'M' . $f($x + $w * .45) . ' ' . $f($y - $w * .62) . 'q5 ' . $f($w * .62) . ' 0 ' . $f($w * 1.24) . '"/>'
                    . '<path class="hs__stem" d="M' . $x . ' ' . $f($y - $w * .7) . 'q2-7 7-8"/>';
            }
        }
        $drift = '';
        if ($s === 'herbst') {
            foreach ([[330, 262, 20], [520, 238, -30], [760, 214, 50], [640, 250, 10], [1120, 150, -60], [1180, 210, 30], [236, 280, 70], [980, 196, -20]] as $i => [$x, $y, $r]) {
                $drift .= '<path class="hs__fall hs__fall--' . ($i % 3) . '" d="M0 0C4-6 12-6 16 0C12 6 4 6 0 0Z" transform="translate(' . $x . ' ' . $y . ') rotate(' . $r . ')"/>';
            }
        } elseif ($winter) {
            for ($i = 0; $i < 64; $i++) {
                // im Himmel nur rechts vom Text (Kontrast der Überschrift), über den Hügeln überall
                $y = mt_rand(10, 420); $x = $y < 210 ? mt_rand(740, 1200) : mt_rand(0, 1200);
                $drift .= '<circle class="hs__fall hs__fall--' . ($i % 3) . '" cx="' . $x . '" cy="' . $y . '" r="' . $f(1.4 + ($i % 4) * .7) . '"/>';
            }
        }

        return '<svg class="hs__scene" data-overflow-ok viewBox="0 0 1200 440" preserveAspectRatio="xMidYMax slice" aria-hidden="true" focusable="false">'
            . '<defs><clipPath id="' . e($uid) . '-field"><path d="' . $hill($h1) . '"/></clipPath></defs>'
            . $sky
            . '<path class="hs__h3" d="' . $hill($h3) . '"/>'
            . $far
            . '<path class="hs__h2" d="' . $hill($h2) . '"/>'
            . $farm . $mid
            . '<path class="hs__h1" d="' . $hill($h1) . '"/>'
            . '<path class="hs__furrow" clip-path="url(#' . e($uid) . '-field)" d="' . $furrows . '"/>'
            . '<g class="hs__fence"><path class="hs__rail" d="' . $rail1 . $rail2 . '"/>' . $posts . '</g>'
            . $fore . $near
            . ($drift !== '' ? '<g class="hs__drift">' . $drift . '</g>' : '')
            . '</svg>';
    };
?>
<div class="wrap hero hs hs--<?= e($season) ?>">
  <div class="hs__stage">
    <?= $scene($season, $b->domId()) ?>
    <div class="hs__body<?= $hours || is_editing() ? ' hs__body--sign' : '' ?>">
      <div class="hero__text hs__text">
        <?= $eb ?><?= $title ?><?= $lead ?>
        <?= nature_buttons($b, 'hero__actions') ?>
      </div>
      <?php if ($hours): ?>
      <div class="hs__sign"<?= $b->central() ?>>
        <p class="hs__sign-top"><span class="hs__sign-name"<?= $b->edit('sign_label') ?>><?= $sign !== '' ? nature_title($sign) : e(nature_name(true)) ?></span><span class="hs__season"><?= e($seasonName) ?></span></p>
        <?= app()->theme->partial('hours', ['hours' => $hours, 'state' => true]) ?>
      </div>
      <?php elseif (is_editing()): ?>
      <p class="hs__sign hs__sign--empty"><?= e(__('Öffnungszeiten unter „Website“ eintragen – dann erscheinen sie hier mit „Jetzt geöffnet“.')) ?></p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php elseif ($v === 'dates'):
    $list = Hero::dates($d);
    $items = $list['items'];
    $n = count($items);
    $listTitle = trim((string) ($d['dates_title'] ?? ''));
    $moreLabel = trim((string) ($d['dates_more_label'] ?? ''));
    $moreLink = trim((string) ($d['dates_more_link'] ?? ''));
    // Ast mit Knoten über jeder Spalte (Anhänger hängen an Schnüren darunter) – dekorativ
    $knots = '';
    for ($i = 0; $i < $n; $i++) $knots .= '<circle cx="' . round((2 * $i + 1) / (2 * $n) * 600, 1) . '" cy="' . (22 - ($i % 2) * 3) . '" r="4.5"/>';
    $branch = '<svg class="hd__branch" viewBox="0 0 600 48" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
        . '<path class="hd__wood" d="M6 28C110 14 220 30 318 20S520 8 594 22"/>'
        . '<path class="hd__twig" d="M96 22c10-8 22-12 34-12M430 14c8-8 20-10 30-9M250 24c6 7 16 10 26 10"/>'
        . '<g class="hd__leaves"><path d="M130 10c6-8 18-8 22 0c-6 7-16 7-22 0Z"/><path d="M460 5c6-8 18-8 22 0c-6 7-16 7-22 0Z"/><path d="M276 34c6-8 18-8 22 0c-6 7-16 7-22 0Z"/></g>'
        . '<g class="hd__knots">' . $knots . '</g></svg>';
?>
<div class="wrap hero hd">
  <div class="hero__text hd__text">
    <?= $eb ?><?= $title ?><?= $lead ?>
    <?= nature_buttons($b, 'hero__actions') ?>
  </div>
  <?php if ($items): ?>
  <section class="hd__board" aria-labelledby="<?= e($b->domId()) ?>-dates">
    <h2 class="hd__head label" id="<?= e($b->domId()) ?>-dates"<?= $b->edit('dates_title') ?>><?= $listTitle !== '' ? nature_title($listTitle) : e(lt('Die nächsten Termine')) ?></h2>
    <div class="hd__line">
      <?= $branch ?>
      <ol class="hd__tags hd__tags--<?= $n ?>" role="list">
        <?php foreach ($items as $it): ?>
        <li class="hd__item">
          <div class="hd__tag">
            <?php if ($it['day'] !== ''): ?><p class="hd__date"><time datetime="<?= e($it['datetime']) ?>"><span class="hd__day"><?= e($it['day']) ?></span> <span class="hd__mon"><?= e($it['month']) ?></span></time></p><?php endif; ?>
            <h3 class="hd__title"><?php if ($it['href']): ?><a class="cover-link" href="<?= e($it['href']) ?>"><?= e($it['title']) ?></a><?php else: ?><?= e($it['title']) ?><?php endif; ?></h3>
            <?php if ($it['when'] !== ''): ?><p class="hd__when"><?= e($it['when']) ?></p><?php endif; ?>
            <?php if ($it['place'] !== ''): ?><p class="hd__place"><?= icon('map-pin', ['class' => 'hd__pin']) ?><span><?= e($it['place']) ?></span></p><?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>
    <?php if ($moreLabel !== '' && $moreLink !== ''): ?>
    <p class="hd__more"><a class="more" <?= nature_link_attrs($moreLink) ?>><span<?= $b->edit('dates_more_label') ?>><?= e($moreLabel) ?></span><?= icon('arrow-right', ['class' => 'more__ico']) ?></a></p>
    <?php endif; ?>
  </section>
  <?php elseif (is_editing()): ?>
  <p class="hd__empty"><?= e(__('Bitte in der Seitenleiste eine Tabelle wählen – es erscheinen die nächsten Termine bzw. die neuesten Einträge.')) ?></p>
  <?php endif; ?>
</div>
<?php elseif ($v === 'form'):
    $formTitle = trim((string) ($d['form_title'] ?? ''));
    $note = trim((string) ($d['form_note'] ?? ''));
?>
<div class="wrap hero hf">
  <div class="hero__text hf__text">
    <?= $eb ?><?= $title ?><?= $lead ?>
    <?= nature_checks($points, 'hero__points hf__points') ?>
    <?= nature_buttons($b, 'hero__actions') ?>
  </div>
  <div class="hf__paper dff-wrap">
    <span class="hf__tape hf__tape--l" aria-hidden="true"></span><span class="hf__tape hf__tape--r" aria-hidden="true"></span>
    <?php if ($formTitle !== ''): ?><h2 class="hf__title"<?= $b->edit('form_title') ?>><?= nature_title($formTitle) ?></h2><?php endif; ?>
    <?= Hero::form($b, $d) ?>
    <?php if ($note !== ''): ?><p class="hf__note"<?= $b->edit('form_note') ?>><?= nature_title($note) ?></p><?php endif; ?>
  </div>
</div>
<?php else:
    $media = $v === 'compact' ? '' : nature_image(!empty($d['image']) ? (int) $d['image'] : null, '(min-width: 1080px) 600px, 100vw', $ratio, 'hero__media', $eager);
?>
<div class="wrap hero hero--<?= e($v) ?><?= $media !== '' ? ' hero--media' : '' ?>">
  <div class="hero__text">
    <?= $eb ?><?= $title ?><?= $lead ?>
    <?= nature_buttons($b, 'hero__actions') ?>
    <?= $v === 'split' ? nature_checks($points, 'hero__points') : '' ?>
  </div>
  <?= $media ?>
</div>
<?php endif;
