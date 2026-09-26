<?php
/**
 * Verwaltung → Design (Style-Editor).
 * @var bool $enabled  @var string $themeLabel  @var string $themeName
 * @var array $schema  @var array $values  @var array $defaults  @var array $checks  @var array $history  @var array $pages  @var array $fontCss
 */
// Bezeichnungen aus theme.php sind deutsch → in der Admin-Sprache anzeigen (Übersetzungen in lang/{lang}.php des Cores oder Themes)
$tr = fn($s) => is_string($s) && $s !== '' ? __($s) : $s;
foreach ((array) ($schema['groups'] ?? []) as $gi => $g) {
    $schema['groups'][$gi]['label'] = $tr($g['label'] ?? '');
    foreach ((array) ($g['tokens'] ?? []) as $ti => $t) {
        foreach (['label', 'help'] as $k) if (isset($t[$k])) $schema['groups'][$gi]['tokens'][$ti][$k] = $tr($t[$k]);
        if (isset($t['options'])) $schema['groups'][$gi]['tokens'][$ti]['options'] = array_map($tr, (array) $t['options']);
    }
}
foreach (['presets', 'fonts'] as $k) foreach ((array) ($schema[$k] ?? []) as $key => $p) $schema[$k][$key]['label'] = $tr($p['label'] ?? '');
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Administration')) ?></p><h1><?= e(__('Design')) ?></h1>
    <p class="adm-muted"><?= e(__('Farben, Formen und Schrift des Kits „{theme}“ für diese Website anpassen. Die Vorschau zeigt Änderungen sofort, online gehen sie erst mit „Speichern“.', ['theme' => $themeLabel])) ?></p></div>
  <?php if ($enabled): ?>
  <div class="adm-row">
    <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ds-open="history"><?= e(__('Verlauf')) ?><?= $history ? ' <span class="adm-count">' . count($history) . '</span>' : '' ?></button>
    <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ds-open="export"><?= e(__('Export')) ?></button>
    <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ds-open="import"><?= e(__('Import')) ?></button>
  </div>
  <?php endif; ?>
</header>

<?php if (!$enabled): ?>
<section class="adm-card ds-empty">
  <h2><?= e(__('Dieses Kit bietet keine Design-Einstellungen an')) ?></h2>
  <p><?= e(__('Das aktive Kit „{theme}“ beschreibt keine einstellbaren Farben, Formen oder Schriften. Wenden Sie sich an die Agentur bzw. die Kit-Entwicklung, wenn Sie das Aussehen anpassen möchten.', ['theme' => $themeLabel])) ?></p>
  <details class="ds-empty__dev">
    <summary><?= e(__('Hinweis für die Kit-Entwicklung')) ?></summary>
    <p><?= e(__('Ergänzen Sie in themes/{name}/theme.php den Schlüssel „design“ mit Gruppen und Tokens, die auf die CSS-Variablen des Kits zeigen, und rufen Sie im Layout design_head() nach dem Kit-CSS sowie design_classes() am <html>-Element auf.', ['name' => $themeName])) ?></p>
<pre><code>'design' => [
  'groups' => [
    ['id' => 'farben', 'label' => 'Farben', 'tokens' => [
      ['name' => 'accent', 'label' => 'Akzent', 'type' => 'color', 'var' => '--accent',
       'default' => '#0F766E', 'contrast' => ['with' => '#FFFFFF', 'min' => 4.5]],
    ]],
  ],
  'presets' => ['petrol' => ['label' => 'Petrol', 'values' => ['accent' => '#0F766E']]],
],</code></pre>
    <p><a href="<?= e(url('/admin/hilfe/technik#design')) ?>"><?= e(__('Technische Dokumentation: Design (Style-Editor)')) ?></a></p>
  </details>
</section>
<?php return; endif; ?>

<?php
$fonts = $schema['fonts'];
$tokensByName = [];
foreach ($schema['groups'] as $g) foreach ($g['tokens'] as $t) $tokensByName[$t['name']] = $t;
$fails = count(array_filter($checks, fn($c) => !$c['ok']));
$checkFor = fn(string $n, string $mode) => current(array_filter($checks, fn($c) => $c['token'] === $n && $c['mode'] === $mode)) ?: null;
$badge = function (?array $c): string {
    if (!$c) return '';
    $cls = $c['ok'] ? ($c['level'] === 'AAA' ? 'is-aaa' : 'is-aa') : 'is-fail';
    $lbl = $c['ok'] ? $c['level'] : __('zu schwach');
    return '<span class="ds-cr__b ' . $cls . '">' . e(number_format($c['ratio'], 1, ',', '')) . ':1 · ' . e($lbl) . '</span>';
};
$hasDark = !empty($schema['dark']);
// Schema-Vorschau für Navigations-Varianten (reines CSS). Theme: 'preview' => 'nav', optional 'thumbs' => [option => Schema]
$navAlias = ['classic' => 'left', 'standard' => 'left', 'default' => 'left', 'klassisch' => 'left', 'centered' => 'center', 'zentriert' => 'center', 'mitte' => 'center',
    'minimal' => 'burger', 'compact' => 'burger', 'kompakt' => 'burger', 'hamburger' => 'burger', 'side' => 'sidebar', 'vertical' => 'sidebar', 'seitlich' => 'sidebar',
    'pill' => 'floating', 'boxed' => 'floating', 'schwebend' => 'floating', 'overlay' => 'transparent', 'transparent' => 'transparent', 'tabbar' => 'bottom', 'unten' => 'bottom', 'geteilt' => 'split'];
$thumbNav = function (string $k) use ($navAlias) {
    $k = strtolower($k);
    $k = $navAlias[$k] ?? (in_array($k, ['left', 'center', 'split', 'burger', 'sidebar', 'floating', 'transparent', 'bottom'], true) ? $k : 'left');
    return '<span class="ds-nav" data-nav="' . e($k) . '" aria-hidden="true"><b></b><i></i><i></i><i></i><u></u></span>';
};
?>
<?php foreach ($fontCss as $href): ?><link rel="stylesheet" href="<?= e($href) ?>">
<?php endforeach; ?>
<div class="st-layout ds" data-st-preview="<?= e(url('/admin/api/design-preview')) ?>" data-ds data-ds-import="<?= e(url('/admin/api/design-import')) ?>">
<form method="post" action="<?= e(url('/admin/design')) ?>" class="adm-tabs-form ds-form" data-tabs data-ds-form novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="_tab" value="">

  <?php if ($schema['presets']): ?>
  <section class="ds-presets" aria-labelledby="ds-presets-h">
    <h2 id="ds-presets-h" class="ds-h"><?= e(__('Vorlagen')) ?> <small class="adm-muted"><?= e(__('Füllt das Formular – gespeichert wird erst mit „Speichern“.')) ?></small></h2>
    <div class="ds-presets__list">
      <?php foreach ($schema['presets'] as $key => $p): $cols = array_values(array_filter($p['values'], fn($v, $k) => ($tokensByName[$k]['type'] ?? '') === 'color' && !str_contains($k, '@'), ARRAY_FILTER_USE_BOTH)); ?>
      <button type="button" class="ds-preset" data-ds-preset="<?= e($key) ?>">
        <span class="ds-preset__sw" aria-hidden="true"><?php foreach (array_slice($cols, 0, 5) as $c): ?><i style="background:<?= e((string) \Core\Design::color($c)) ?>"></i><?php endforeach; ?></span>
        <span class="ds-preset__l"><?= e($p['label']) ?></span>
      </button>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <div class="st-bar">
    <div class="adm-tabs" role="tablist" aria-label="<?= e(__('Bereiche')) ?>">
      <?php foreach ($schema['groups'] as $i => $g): ?>
      <button type="button" role="tab" id="tab-<?= e($g['id']) ?>" aria-controls="panel-<?= e($g['id']) ?>" data-tab="<?= e($g['id']) ?>"
        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"<?= $i === 0 ? '' : ' tabindex="-1"' ?>><?= e($g['label']) ?><span class="ds-tabdot" data-ds-tabdot="<?= e($g['id']) ?>" hidden aria-label="<?= e(__('Kontrastproblem')) ?>"> ●</span></button>
      <?php endforeach; ?>
    </div>
    <div class="st-tools">
      <button type="button" class="ds-sum<?= $fails ? ' is-fail' : ' is-ok' ?>" data-ds-summary<?= $checks ? '' : ' hidden' ?>><?= e($fails ? ($fails === 1 ? __('1 Kontrastproblem') : __('{n} Kontrastprobleme', ['n' => $fails])) : __('Kontrast in Ordnung')) ?></button>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-st-pvtoggle aria-pressed="false"><?= e(__('Vorschau')) ?></button>
    </div>
  </div>

  <?php foreach ($schema['groups'] as $i => $g): ?>
  <section class="adm-card adm-panel ds-panel" role="tabpanel" id="panel-<?= e($g['id']) ?>" aria-labelledby="tab-<?= e($g['id']) ?>"<?= $i === 0 ? '' : ' hidden' ?>>
    <h2><?= e($g['label']) ?></h2>
    <div class="ds-toks">
    <?php foreach ($g['tokens'] as $t):
        $n = (string) $t['name']; $type = (string) ($t['type'] ?? 'color'); $v = $values[$n] ?? null; $id = 'ds-' . preg_replace('~[^\w-]~', '', $n);
        $help = (string) ($t['help'] ?? ''); ?>
      <div class="ds-tok ds-tok--<?= e($type) ?>" data-tok="<?= e($n) ?>" data-type="<?= e($type) ?>" data-group="<?= e($g['id']) ?>">
      <?php if ($type === 'color'): $dk = array_key_exists($n . '@dark', $values); ?>
        <div class="ds-tok__head"><label for="<?= e($id) ?>"><?= e($t['label'] ?? $n) ?></label><?= $help !== '' ? '<small class="adm-muted">' . e($help) . '</small>' : '' ?></div>
        <div class="ds-color">
          <span class="ds-color__pair">
            <?php if ($dk): ?><span class="ds-color__mode"><?= e(__('Hell')) ?></span><?php endif; ?>
            <input type="color" value="<?= e(strtolower((string) $v)) ?>" data-ds-picker="<?= e($n) ?>" aria-label="<?= e(__('Farbe wählen: {name}', ['name' => $t['label'] ?? $n])) ?>">
            <input type="text" id="<?= e($id) ?>" name="v[<?= e($n) ?>]" value="<?= e((string) $v) ?>" class="ds-hex" maxlength="7" spellcheck="false" autocomplete="off" pattern="#?[0-9A-Fa-f]{6}|#?[0-9A-Fa-f]{3}">
            <span class="ds-cr" data-cr="<?= e($n) ?>" data-mode="light"><?= $badge($checkFor($n, 'light')) ?></span>
          </span>
          <?php if ($dk): ?>
          <span class="ds-color__pair ds-color__pair--dark">
            <span class="ds-color__mode"><?= e(__('Dunkel')) ?></span>
            <input type="color" value="<?= e(strtolower((string) $values[$n . '@dark'])) ?>" data-ds-picker="<?= e($n) ?>@dark" aria-label="<?= e(__('Dunkle Farbe wählen: {name}', ['name' => $t['label'] ?? $n])) ?>">
            <input type="text" name="v[<?= e($n) ?>@dark]" value="<?= e((string) $values[$n . '@dark']) ?>" class="ds-hex" maxlength="7" spellcheck="false" autocomplete="off" aria-label="<?= e(__('Dunkler Wert: {name}', ['name' => $t['label'] ?? $n])) ?>">
            <span class="ds-cr" data-cr="<?= e($n) ?>" data-mode="dark"><?= $badge($checkFor($n, 'dark')) ?></span>
          </span>
          <?php endif; ?>
        </div>
      <?php elseif ($type === 'range'): $unit = (string) ($t['unit'] ?? ''); ?>
        <div class="ds-tok__head"><label for="<?= e($id) ?>"><?= e($t['label'] ?? $n) ?></label><?= $help !== '' ? '<small class="adm-muted">' . e($help) . '</small>' : '' ?></div>
        <div class="ds-range">
          <input type="range" id="<?= e($id) ?>" name="v[<?= e($n) ?>]" value="<?= e((string) $v) ?>" min="<?= e((string) ($t['min'] ?? 0)) ?>" max="<?= e((string) ($t['max'] ?? 100)) ?>" step="<?= e((string) ($t['step'] ?? 1)) ?>">
          <span class="ds-range__num"><input type="number" value="<?= e((string) $v) ?>" min="<?= e((string) ($t['min'] ?? 0)) ?>" max="<?= e((string) ($t['max'] ?? 100)) ?>" step="<?= e((string) ($t['step'] ?? 1)) ?>" data-ds-num="<?= e($n) ?>" aria-label="<?= e(($t['label'] ?? $n) . ($unit !== '' ? " ($unit)" : '')) ?>"><?= $unit !== '' ? '<span>' . e($unit) . '</span>' : '' ?></span>
        </div>
      <?php elseif ($type === 'choice'):
        $opts = (array) ($t['options'] ?? []); $pv = (string) ($t['preview'] ?? ''); $cards = $pv !== '' || isset($t['thumbs']); ?>
        <fieldset class="ds-choice<?= $cards ? ' ds-choice--cards' : '' ?>">
          <legend><?= e($t['label'] ?? $n) ?><?= $help !== '' ? ' <small class="adm-muted">' . e($help) . '</small>' : '' ?></legend>
          <div class="<?= $cards ? 'ds-cards' : 'ds-seg' ?>">
          <?php foreach ($opts as $ok => $ol): $ok = (string) $ok; ?>
            <label class="<?= $cards ? 'ds-card' : 'ds-seg__o' ?>">
              <input type="radio" name="v[<?= e($n) ?>]" value="<?= e($ok) ?>"<?= (string) $v === $ok ? ' checked' : '' ?>>
              <?php if ($cards): ?>
                <?php if ($pv === 'radius'): ?><span class="ds-thumb ds-thumb--radius" aria-hidden="true"><i style="border-radius:<?= e((string) ($t['values'][$ok] ?? '0')) ?>"></i></span>
                <?php elseif ($pv === 'color' && isset($t['values'][$ok])): ?><span class="ds-thumb ds-thumb--color" aria-hidden="true"><i style="background:<?= e((string) $t['values'][$ok]) ?>"></i></span>
                <?php else: ?><span class="ds-thumb" aria-hidden="true"><?= $thumbNav((string) ($t['thumbs'][$ok] ?? $ok)) ?></span><?php endif; ?>
              <?php endif; ?>
              <span class="ds-opt__l"><?= e((string) $ol) ?></span>
            </label>
          <?php endforeach; ?>
          </div>
        </fieldset>
      <?php elseif ($type === 'bool'): ?>
        <div class="ds-bool">
          <label class="ds-switch"><input type="hidden" name="v[<?= e($n) ?>]" value="0"><input type="checkbox" id="<?= e($id) ?>" name="v[<?= e($n) ?>]" value="1"<?= $v ? ' checked' : '' ?>><span aria-hidden="true"></span></label>
          <label for="<?= e($id) ?>" class="ds-bool__l"><?= e($t['label'] ?? $n) ?><?= $help !== '' ? '<small class="adm-muted">' . e($help) . '</small>' : '' ?></label>
        </div>
      <?php elseif ($type === 'font'): ?>
        <div class="ds-tok__head"><label for="<?= e($id) ?>"><?= e($t['label'] ?? $n) ?></label><?= $help !== '' ? '<small class="adm-muted">' . e($help) . '</small>' : '' ?></div>
        <div class="ds-font">
          <select id="<?= e($id) ?>" name="v[<?= e($n) ?>]" data-ds-font>
            <?php foreach ($fonts as $fk => $f): ?><option value="<?= e((string) $fk) ?>" data-stack="<?= e($f['stack']) ?>"<?= !empty($f['installed']) ? ' data-kb="' . (int) $f['kb'] . '"' : '' ?><?= (string) $v === (string) $fk ? ' selected' : '' ?>><?= e($f['label']) ?></option><?php endforeach; ?>
          </select>
          <?php // Installierte Schrift (Core\Fonts): Hinweis, wenn sie viel lädt – resources/js/_design.js ?>
          <p class="ds-fontwarn" data-ds-fontwarn data-budget="<?= \Core\Fonts::BUDGET_KB ?>" data-text="<?= e(__('Diese Schrift lädt bis zu {kb} KB (lateinische Zeichen, alle installierten Schnitte). Für schnelle Seiten weniger Schnitte installieren oder die variable Fassung nutzen.')) ?>" role="status"<?= !empty($fonts[$v]['installed']) && (int) $fonts[$v]['kb'] > \Core\Fonts::BUDGET_KB ? '' : ' hidden' ?>><?= !empty($fonts[$v]['installed']) ? e(__('Diese Schrift lädt bis zu {kb} KB (lateinische Zeichen, alle installierten Schnitte). Für schnelle Seiten weniger Schnitte installieren oder die variable Fassung nutzen.', ['kb' => (int) $fonts[$v]['kb']])) : '' ?></p>
          <p class="ds-sample" data-ds-sample style="font-family:<?= e($fonts[$v]['stack'] ?? 'inherit') ?>"><strong><?= e(__('Überschrift in dieser Schrift')) ?></strong><span><?= e(__('Franz jagt im komplett verwahrlosten Taxi quer durch Bayern. 0123456789')) ?></span></p>
        </div>
      <?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <div class="adm-savebar ds-savebar">
    <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Speichern')) ?></button>
    <button class="adm-btn adm-btn--ghost" type="button" data-ds-discard disabled><?= e(__('Verwerfen')) ?></button>
    <button class="adm-btn adm-btn--ghost" type="button" data-ds-reset><?= e(__('Auf Kit-Standard zurücksetzen')) ?></button>
    <span class="adm-muted ds-state" data-ds-state aria-live="polite"></span>
  </div>
</form>
<aside class="st-pv" aria-label="<?= e(__('Vorschau')) ?>" hidden>
  <header class="st-pv__bar">
    <strong><?= e(__('Vorschau')) ?></strong>
    <label class="adm-sr" for="ds-page"><?= e(__('Seite für die Vorschau')) ?></label>
    <select id="ds-page" class="ds-pvpage" data-ds-page>
      <?php foreach ($pages as $p): ?><option value="<?= $p['home'] ? '' : (int) $p['id'] ?>"<?= $p['home'] ? ' selected' : '' ?>><?= $p['sample'] ? e(__('Muster')) . ' – ' : '' ?><?= str_repeat('– ', $p['sample'] ? 0 : $p['depth']) ?><?= e($p['title']) ?><?= $p['home'] ? ' (' . e(__('Startseite')) . ')' : '' ?></option><?php endforeach; ?>
    </select>
    <div class="fx-seg" role="group" aria-label="<?= e(__('Gerät')) ?>">
      <button type="button" data-st-device="desktop" aria-pressed="true"><?= e(__('Desktop')) ?></button>
      <button type="button" data-st-device="mobile" aria-pressed="false"><?= e(__('Mobil')) ?></button>
    </div>
    <?php if ($hasDark): ?>
    <div class="fx-seg" role="group" aria-label="<?= e(__('Farbschema')) ?>">
      <button type="button" data-ds-scheme="light" aria-pressed="true"><?= e(__('Hell')) ?></button>
      <button type="button" data-ds-scheme="dark" aria-pressed="false"><?= e(__('Dunkel')) ?></button>
    </div>
    <?php endif; ?>
    <span class="st-pv__state" data-st-state aria-live="polite"></span>
  </header>
  <div class="st-pv__stage" data-st-stage><iframe title="<?= e(__('Vorschau der Website mit ungespeicherten Änderungen')) ?>" data-st-frame></iframe></div>
  <p class="st-pv__note adm-muted"><?= e(__('Zeigt die Website mit Ihren ungespeicherten Design-Werten. Gespeichert wird erst mit „Speichern“.')) ?></p>
</aside>
</div>

<dialog class="adm-dialog adm-dialog--small ds-dlg" data-ds-dialog="history" aria-labelledby="ds-hist-h">
  <div class="adm-dialog__head"><h2 id="ds-hist-h"><?= e(__('Verlauf')) ?></h2><button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ds-close><?= e(__('Schließen')) ?></button></div>
  <?php if (!$history): ?>
  <p class="adm-muted"><?= e(__('Noch keine früheren Stände – der Verlauf entsteht mit jedem Speichern (die letzten 10).')) ?></p>
  <?php else: ?>
  <p class="adm-muted"><?= e(__('Frühere Stände vor jedem Speichern. „Wiederherstellen“ lädt den Stand ins Formular – übernommen wird er mit „Speichern“.')) ?></p>
  <ol class="ds-hist">
    <?php foreach ($history as $i => $h): $sw = array_slice(array_values(array_filter($h['values'], fn($v, $k) => ($tokensByName[$k]['type'] ?? '') === 'color', ARRAY_FILTER_USE_BOTH)), 0, 6); ?>
    <li>
      <span class="ds-preset__sw" aria-hidden="true"><?php foreach ($sw as $c): ?><i style="background:<?= e((string) $c) ?>"></i><?php endforeach; ?></span>
      <span class="ds-hist__t"><strong><?= e(__('Stand bis {date}', ['date' => date('d.m.Y H:i', strtotime((string) $h['at']) ?: time())])) ?></strong>
        <small class="adm-muted"><?= e(__('ersetzt von {who}', ['who' => ($h['by'] ?? '') !== '' ? $h['by'] : __('unbekannt')])) ?><?= ($h['note'] ?? '') !== '' ? ' · ' . e($h['note']) : '' ?></small></span>
      <button type="button" class="adm-btn adm-btn--small" data-ds-restore="<?= (int) $i ?>"><?= e(__('Wiederherstellen')) ?></button>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>
</dialog>

<dialog class="adm-dialog adm-dialog--small ds-dlg" data-ds-dialog="export" aria-labelledby="ds-exp-h">
  <div class="adm-dialog__head"><h2 id="ds-exp-h"><?= e(__('Export')) ?></h2><button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ds-close><?= e(__('Schließen')) ?></button></div>
  <p class="adm-muted"><?= e(__('Die aktuellen Werte im Formular als JSON – z. B. für eine andere Website mit demselben Kit.')) ?></p>
  <textarea class="ds-json" rows="12" readonly data-ds-export aria-label="JSON"></textarea>
  <div class="adm-row"><button type="button" class="adm-btn adm-btn--primary adm-btn--small" data-ds-copy><?= e(__('Kopieren')) ?></button><span class="adm-muted" data-ds-copied aria-live="polite"></span></div>
</dialog>

<dialog class="adm-dialog adm-dialog--small ds-dlg" data-ds-dialog="import" aria-labelledby="ds-imp-h">
  <div class="adm-dialog__head"><h2 id="ds-imp-h"><?= e(__('Import')) ?></h2><button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-ds-close><?= e(__('Schließen')) ?></button></div>
  <p class="adm-muted"><?= e(__('JSON aus einem Export einfügen. Die Werte werden geprüft und ins Formular übernommen – gespeichert wird erst mit „Speichern“.')) ?></p>
  <textarea class="ds-json" rows="10" data-ds-import-text aria-label="JSON" placeholder='{"theme": "…", "values": {…}}'></textarea>
  <p class="ds-imp-msg" data-ds-import-msg role="status"></p>
  <div class="adm-row"><button type="button" class="adm-btn adm-btn--primary adm-btn--small" data-ds-import-go><?= e(__('Übernehmen')) ?></button></div>
</dialog>

<script type="application/json" id="ds-data"><?= json_encode([
    'theme' => $themeName, 'schema' => $schema, 'saved' => $values, 'defaults' => $defaults,
    'history' => array_map(fn($h) => $h['values'], $history),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
