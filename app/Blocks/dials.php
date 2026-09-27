<?php
/**
 * Kennzahlen mit Skala (Kern-Block, vom Theme überschreibbar: kits/{name}/blocks/dials.php).
 * Je Kennzahl ein Rundinstrument über 270° als Inline-SVG – alle Maße als SVG-Attribute (CSP: keine Inline-Styles).
 * Felder wie „essenz“ (stats/dials): value, label, text, level – dazu unit, number, max.
 * Füllstand: level (0–100), sonst Zahl ÷ Höchstwert, sonst Prozentwert (Wert oder Einheit „%“), sonst neutrale Skala.
 * Barrierefreiheit: Grafik und sichtbare Texte aria-hidden, ein Satz für Screenreader („92 % – Weiterempfehlung, Umfrage“).
 * Aussehen: resources/css/dials.css (Variablen --dial-*), Hochzählen: resources/js/dials.mjs (nur mit „animate“).
 * @var \Core\Block $b  @var array $d
 */
// '_bare' (Core\Blocks\Hero::dials): eingebettet in einen anderen Block – ohne Container-Klasse und Abschnittskopf
$bare = !empty($d['_bare']);
$wrap = $bare ? '' : (app()->theme->def['container_class'] ?? 'wrap');
$size = in_array($d['size'] ?? '', ['s', 'm', 'l'], true) ? $d['size'] : 'm';
$style = in_array($d['style'] ?? '', ['arc', 'ticks', 'minimal'], true) ? $d['style'] : 'arc';
$animate = !empty($d['animate']) && !is_editing();
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => is_array($i) && (trim((string) ($i['value'] ?? '')) !== '' || is_editing())));
$title = trim((string) ($d['title'] ?? ''));
$de = str_starts_with(\Core\Lang::current(), 'de');

// Zahl aus einem angezeigten Wert: „0,4“, „92 %“, „1.250“ (Punkt + drei Ziffern = Tausender), „3.5“
$parse = function (string $v) use ($de): ?float {
    if (!preg_match('~-?\d[\d.,]*~', $v, $m)) return null;
    $s = rtrim($m[0], '.,');
    if (str_contains($s, ',') && str_contains($s, '.')) {
        $s = strrpos($s, ',') > strrpos($s, '.') ? str_replace(['.', ','], ['', '.'], $s) : str_replace(',', '', $s);
    } elseif (str_contains($s, ',')) {
        $s = preg_match('~^-?\d{1,3}(,\d{3})+$~', $s) && !$de ? str_replace(',', '', $s) : str_replace(',', '.', $s);
    } elseif (preg_match('~^-?\d{1,3}(\.\d{3})+$~', $s)) {
        $s = str_replace('.', '', $s);
    }
    return is_numeric($s) ? (float) $s : null;
};
$num = fn($x) => is_numeric($x) ? (float) $x : null;
$n2 = fn(float $x): string => ($s = rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.')) === '-0' || $s === '' ? '0' : $s;
$pt = fn(float $deg, float $r): string => $n2(60 + $r * cos(deg2rad($deg))) . ' ' . $n2(60 + $r * sin(deg2rad($deg)));
// Bogen über 270°: Beginn unten links (135°), im Uhrzeigersinn bis unten rechts (45°)
$arc = fn(float $r): string => 'M' . $pt(135, $r) . 'A' . $n2($r) . ' ' . $n2($r) . ' 0 1 1 ' . $pt(45, $r);
// Skalenstriche alle 9° (31 Striche), jeder fünfte länger; getrennt nach „bis zum Wert“ und „danach“
$ticks = function (float $outer, float $minor, float $major, ?float $upto) use ($pt): array {
    $on = $off = '';
    for ($i = 0; $i <= 30; $i++) {
        $deg = 135 + $i * 9;
        $seg = 'M' . $pt($deg, $outer) . 'L' . $pt($deg, $i % 5 === 0 ? $major : $minor);
        if ($upto !== null && $i / 30 <= $upto + 1e-9) $on .= $seg; else $off .= $seg;
    }
    return [$on, $off];
};
// Hochzählen nur bei einer ganzen Zahl ohne Trennzeichen („12“, „92 %“): [vorher, Zahl, nachher] – sonst null
$countable = fn(string $v): ?array => preg_match('~^(\D*?)([1-9]\d{0,5})(\D*)$~u', $v, $m) ? [$m[1], $m[2], $m[3]] : null;
$fmt = fn(float $x): string => $de ? str_replace('.', ',', $n2($x)) : $n2($x);

$hasJs = false;
?>
<div class="<?= e(trim($wrap . ' cms-dials-wrap')) ?>">
  <?= $bare ? '' : \Core\MediaBlocks::head($b) ?>
  <?php if ($items): ?>
  <ul class="cms-dials cms-dials--<?= e($size) ?> cms-dials--<?= e($style) ?> cms-dials--t-<?= e(app()->theme->name) ?>" role="list"<?= $title !== '' && !$bare ? ' aria-labelledby="' . e($b->titleId()) . '"' : ' aria-label="' . e(lt('Kennzahlen')) . '"' ?><?= $animate ? ' data-dials' : '' ?>>
    <?php foreach ($items as $i => $it):
        $value = trim((string) ($it['value'] ?? ''));
        $unit = trim((string) ($it['unit'] ?? ''));
        $caption = trim((string) ($it['label'] ?? ''));
        $text = trim((string) ($it['text'] ?? ''));
        $percent = str_ends_with($value, '%') || $unit === '%';
        $n = $num($it['number'] ?? null) ?? $parse($value);
        $max = $num($it['max'] ?? null);
        $level = $num($it['level'] ?? null);   // wie beim Kit „essenz“: Füllstand 0–100 direkt
        $frac = $level !== null ? $level / 100 : ($n !== null && $max !== null && $max > 0 ? $n / $max : ($n !== null && $percent ? $n / 100 : null));
        $frac = $frac === null ? null : max(0.0, min(1.0, $frac));
        $len = mb_strlen($value) + ($unit !== '' ? mb_strlen($unit) * .5 + .3 : 0);
        $lenClass = $len <= 2 ? 1 : ($len <= 3.5 ? 2 : ($len <= 5 ? 3 : ($len <= 7 ? 4 : 5)));
        $count = $animate ? $countable($value) : null;
        $hasJs = $hasJs || $animate;
        // Satz für Screenreader: „92 % – Weiterempfehlung, Umfrage 2025“ (+ „von 20“, wenn ein Höchstwert gilt)
        $sr = $value . ($unit !== '' ? ($unit === '%' ? "\u{202F}" : "\u{00A0}") . $unit : '');
        if ($max !== null && $max > 0 && !$percent) $sr .= ' ' . lt('von {max}', ['max' => $fmt($max) . ($unit !== '' ? "\u{00A0}" . $unit : '')]);
        if ($caption !== '') $sr .= ' – ' . $caption;
        if ($text !== '') $sr .= ', ' . $text;
    ?>
    <li class="cms-dial<?= $frac === null ? ' is-neutral' : '' ?>">
      <span class="cms-dials__sr"><?= e($sr) ?></span>
      <div class="cms-dial__gauge" aria-hidden="true">
        <svg class="cms-dial__svg" viewBox="0 0 120 120" focusable="false">
          <?php if ($style === 'arc'): [$on, $off] = $ticks(58, 54.5, 52, null); ?>
          <path class="cms-dial__ticks" d="<?= $off ?>"/>
          <path class="cms-dial__track" d="<?= $arc(45) ?>" pathLength="100"/>
          <?php if ($frac !== null && $frac > 0): ?><path class="cms-dial__arc" d="<?= $arc(45) ?>" pathLength="100" stroke-dasharray="<?= $n2($frac * 100) ?> 200"/><?php endif; ?>
          <circle class="cms-dial__hub" cx="60" cy="60" r="37"/>
          <?php elseif ($style === 'ticks'): [$on, $off] = $ticks(58, 49, 45, $frac); ?>
          <?php if ($off !== ''): ?><path class="cms-dial__ticks" d="<?= $off ?>"/><?php endif; ?>
          <?php if ($on !== ''): ?><path class="cms-dial__ticks cms-dial__ticks--on" d="<?= $on ?>"/><?php endif; ?>
          <?php else: ?>
          <path class="cms-dial__track" d="<?= $arc(54) ?>" pathLength="100"/>
          <?php if ($frac !== null && $frac > 0): ?><path class="cms-dial__arc" d="<?= $arc(54) ?>" pathLength="100" stroke-dasharray="<?= $n2($frac * 100) ?> 200"/><?php endif; ?>
          <?php endif; ?>
        </svg>
        <span class="cms-dial__value l<?= $lenClass ?>"><span class="cms-dial__num"<?= $b->edit("items.$i.value") ?>><?= $count ? e($count[0]) . '<span data-n>' . e($count[1]) . '</span>' . e($count[2]) : e($value) ?></span><?php if ($unit !== ''): ?><span class="cms-dial__unit"<?= $b->edit("items.$i.unit") ?>><?= e($unit) ?></span><?php endif; ?></span>
      </div>
      <?php if ($caption !== '' || is_editing()): ?><p class="cms-dial__caption" aria-hidden="true"<?= $b->edit("items.$i.label") ?>><?= e($caption) ?></p><?php endif; ?>
      <?php if ($text !== ''): ?><p class="cms-dial__text" aria-hidden="true"<?= $b->edit("items.$i.text") ?>><?= e($text) ?></p><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($hasJs && !defined('CMS_DIALS_JS')): define('CMS_DIALS_JS', true); ?>
  <script type="module" src="<?= e(asset('js/dials.mjs')) ?>"></script>
  <?php endif; ?>
  <?php elseif (is_editing()): ?>
  <p class="cms-empty-hint"><?= e(__('Noch keine Kennzahlen – in der Seitenleiste hinzufügen.')) ?></p>
  <?php endif; ?>
</div>
