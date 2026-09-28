<?php
/**
 * Grundeinstellungen → Schriften (Core\Fonts, FontsController). Vorschau: resources/js/_fonts.js lädt die Schriften als
 * FontFace von der eigenen Domain (Vorschau-Route bzw. public/assets/fonts/installed) – keine Anfragen an Google/Fontsource im Browser.
 * @var string $q  @var string $cat  @var string $text  @var array $results  @var bool $catalogOk  @var int $catalogSize
 * @var ?array $detail  @var array $installed
 */
use Core\Fonts;
use Core\Media;

$sample = $text !== '' ? $text : __('Franz jagt im komplett verwahrlosten Taxi quer durch Bayern');
$catLabel = fn(string $c) => isset(Fonts::CATEGORIES[$c]) ? __(Fonts::CATEGORIES[$c]) : $c;
$qs = fn(array $p) => url('/admin/system/fonts') . '?' . http_build_query(array_filter($p + ['q' => $q, 'cat' => $cat, 'text' => $text], fn($v) => $v !== '' && $v !== null));
$preview = fn(string $id) => url('/admin/system/fonts/preview/' . $id);
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/system')) ?>"><?= e(__('Grundeinstellungen')) ?></a></p><h1><?= e(__('Schriften')) ?></h1>
    <p class="adm-muted"><?= e(__('Schriften aus dem Google-Fonts-Katalog einmalig auf den Server laden und von der eigenen Domain ausliefern – ohne Verbindung der Besucher zu Google (DSGVO). Installierte Schriften stehen allen Websites dieser Installation im Style-Editor (Design) zur Verfügung.')) ?></p></div>
</header>

<div class="fo-text f">
  <label for="fo-text"><?= e(__('Vorschautext')) ?></label>
  <input id="fo-text" type="text" value="<?= e($sample) ?>" maxlength="120" data-font-text form="fo-search" name="text">
</div>

<section class="adm-card" id="installiert" aria-labelledby="fo-inst-h">
  <h2 id="fo-inst-h"><?= e(__('Installierte Schriften')) ?> <span class="adm-badge adm-badge--muted"><?= count($installed) ?></span></h2>
  <?php if (!$installed): ?>
  <p class="adm-muted"><?= e(__('Noch keine Schriften installiert. Suchen Sie unten eine Schrift aus und wählen Sie Schnitte und Zeichensätze.')) ?></p>
  <?php else: ?>
  <div class="fo-table-wrap"><table class="adm-table fo-table">
    <thead><tr><th scope="col"><?= e(__('Schrift')) ?></th><th scope="col"><?= e(__('Schnitte')) ?></th><th scope="col"><?= e(__('Größe')) ?></th><th scope="col"><?= e(__('Lizenz')) ?></th><th scope="col"><?= e(__('Verwendet in')) ?></th><th scope="col"><span class="adm-sr"><?= e(__('Aktionen')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach ($installed as $id => $f): $used = Fonts::usage($id); $prim = Fonts::primaryFile($f); ?>
      <tr>
        <td><strong><?= e($f['family']) ?></strong> <code><?= e($id) ?></code>
          <p class="fo-sample" data-font-sample data-font-src="<?= e($prim ? Fonts::url($id . '/' . $prim) : '') ?>" data-font-name="fi-<?= e($id) ?>" data-font-fallback="<?= e(Fonts::stack($f)) ?>"><?= e($sample) ?></p></td>
        <td><?= e($f['variable'] ? __('variabel {min}–{max}', ['min' => (int) $f['axes']['wght']['min'], 'max' => (int) $f['axes']['wght']['max']]) : implode(', ', $f['weights'])) ?><?= in_array('italic', (array) $f['styles'], true) ? ' · ' . e(__('kursiv')) : '' ?>
          <br><small class="adm-muted"><?= e(implode(', ', (array) $f['subsets'])) ?></small></td>
        <td><?= e(Media::humanSize((int) $f['bytes'])) ?><br><small class="adm-muted"><?= e(__('je Seite bis {kb} KB', ['kb' => (int) ceil(Fonts::pageBytes($f) / 1024)])) ?></small></td>
        <td><a href="<?= e(Fonts::url($id . '/LICENSE.txt')) ?>"><?= e($f['license']) ?></a><?php if (($f['copyright'] ?? '') !== ''): ?><br><small class="adm-muted fo-copy"><?= e($f['copyright']) ?></small><?php endif; ?></td>
        <td><?= $used ? e(implode(', ', $used)) : '<span class="adm-muted">' . e(__('nicht verwendet')) . '</span>' ?></td>
        <td class="fo-actions">
          <form method="post" action="<?= e(url('/admin/system/fonts/' . $id . '/preload')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="preload" value="<?= empty($f['preload']) ? '1' : '' ?>">
            <button class="adm-btn adm-btn--small adm-btn--ghost" type="submit" aria-pressed="<?= empty($f['preload']) ? 'false' : 'true' ?>" title="<?= e(__('Lädt den lateinischen Normalschnitt früher (<link rel=preload>) – nur für die Hauptschrift sinnvoll.')) ?>"><?= e(__('Vorladen')) ?></button></form>
          <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($qs(['font' => $id]) . '#installieren') ?>"><?= e(__('Ändern')) ?></a>
          <form method="post" action="<?= e(url('/admin/system/fonts/' . $id . '/remove')) ?>"><?= csrf_field() ?>
            <?php if ($used): ?><input type="hidden" name="force" value="1"><?php endif; ?>
            <button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" type="submit" data-confirm="<?= e($used ? __('„{name}“ wird verwendet ({where}). Trotzdem entfernen? Dort gilt dann wieder die Standardschrift des Kits.', ['name' => $f['family'], 'where' => implode(', ', $used)]) : __('„{name}“ entfernen?', ['name' => $f['family']])) ?>"><?= e(__('Entfernen')) ?></button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="f-help"><?= e(__('Auswahl der Schrift: Design → Schriften (Style-Editor). Dateien: public/assets/fonts/installed/{id}/ mit font.css und LICENSE.txt.')) ?></p>
  <?php endif; ?>
</section>

<?php if ($detail): $isInst = isset($installed[$detail['id']]); $cur = $installed[$detail['id']] ?? null;
  $selW = $cur ? array_map('intval', (array) $cur['weights']) : (array_values(array_intersect([400, 700], $detail['weights'])) ?: [$detail['weights'][0] ?? 400]);
  $selS = $cur ? (array) $cur['subsets'] : array_values(array_intersect(Fonts::DEFAULT_SUBSETS, $detail['subsets']));
  $hasVar = $detail['variable'] && isset($detail['axes']['wght']); ?>
<form class="adm-card fo-install" id="installieren" method="post" action="<?= e(url('/admin/system/fonts/install')) ?>" aria-labelledby="fo-det-h">
  <?= csrf_field() ?><input type="hidden" name="font" value="<?= e($detail['id']) ?>">
  <h2 id="fo-det-h"><?= e($detail['family']) ?> <?php if ($isInst): ?><span class="adm-badge"><?= e(__('installiert')) ?></span><?php endif; ?></h2>
  <p class="fo-sample fo-sample--big" data-font-sample data-font-src="<?= e($preview($detail['id'])) ?>" data-font-name="fp-<?= e($detail['id']) ?>"><?= e($sample) ?></p>
  <p class="adm-muted"><?= e($catLabel($detail['category'])) ?> · <?= e(Fonts::LICENSES[$detail['license']] ?? $detail['license']) ?><?= $detail['version'] !== '' ? ' · ' . e($detail['version']) : '' ?></p>
  <?php if ($hasVar): ?>
  <fieldset class="fo-set"><legend><?= e(__('Fassung')) ?></legend>
    <label class="f-check"><input type="radio" name="mode" value="static"<?= !($cur['variable'] ?? false) ? ' checked' : '' ?>><span><?= e(__('Einzelne Schnitte')) ?> <small class="adm-muted"><?= e(__('eine Datei je Gewicht – klein, wenn nur 2–3 Schnitte gebraucht werden')) ?></small></span></label>
    <label class="f-check"><input type="radio" name="mode" value="variable"<?= ($cur['variable'] ?? false) ? ' checked' : '' ?>><span><?= e(__('Variable Schrift')) ?> <small class="adm-muted"><?= e(__('alle Gewichte {min}–{max} in einer Datei', ['min' => (int) $detail['axes']['wght']['min'], 'max' => (int) $detail['axes']['wght']['max']])) ?></small></span></label>
  </fieldset>
  <?php endif; ?>
  <fieldset class="fo-set"><legend><?= e(__('Gewichte')) ?><?= $hasVar ? ' <small class="adm-muted">' . e(__('(nur für einzelne Schnitte)')) . '</small>' : '' ?></legend>
    <div class="fo-checks"><?php foreach ($detail['weights'] as $w): ?><label class="f-check"><input type="checkbox" name="weights[]" value="<?= (int) $w ?>"<?= in_array((int) $w, $selW, true) ? ' checked' : '' ?>><span><?= (int) $w ?></span></label><?php endforeach; ?></div>
  </fieldset>
  <fieldset class="fo-set"><legend><?= e(__('Stile')) ?></legend>
    <div class="fo-checks"><?php foreach ($detail['styles'] as $st): ?><label class="f-check"><input type="checkbox" name="styles[]" value="<?= e($st) ?>"<?= in_array($st, (array) ($cur['styles'] ?? ['normal']), true) ? ' checked' : '' ?>><span><?= e($st === 'italic' ? __('kursiv') : __('normal')) ?></span></label><?php endforeach; ?></div>
  </fieldset>
  <fieldset class="fo-set"><legend><?= e(__('Zeichensätze')) ?> <small class="adm-muted"><?= e(__('„latin“ und „latin-ext“ decken Deutsch und die meisten europäischen Sprachen ab')) ?></small></legend>
    <div class="fo-checks"><?php foreach ($detail['subsets'] as $sub): ?><label class="f-check"><input type="checkbox" name="subsets[]" value="<?= e($sub) ?>"<?= in_array($sub, $selS, true) ? ' checked' : '' ?>><span><?= e($sub) ?></span></label><?php endforeach; ?></div>
  </fieldset>
  <label class="f-check"><input type="checkbox" name="preload" value="1"<?= !empty($cur['preload']) ? ' checked' : '' ?>><span><?= e(__('Hauptschnitt vorladen')) ?> <small class="adm-muted"><?= e(__('nur für die Hauptschrift der Website sinnvoll')) ?></small></span></label>
  <p class="f-help"><?= e(__('Die Dateien werden vom Server geladen (Fontsource/jsDelivr, sonst Google Fonts), geprüft und unter public/assets/fonts/installed/{id}/ gespeichert – zusammen mit dem Lizenztext.', ['id' => $detail['id']])) ?></p>
  <div class="adm-row">
    <button class="adm-btn adm-btn--primary" type="submit"><?= e($isInst ? __('Neu installieren (ersetzt)') : __('Installieren')) ?></button>
    <a class="adm-btn adm-btn--ghost" href="<?= e($qs([])) ?>"><?= e(__('Abbrechen')) ?></a>
  </div>
</form>
<?php endif; ?>

<section class="adm-card" id="suchen" aria-labelledby="fo-search-h">
  <h2 id="fo-search-h"><?= e(__('Google-Fonts-Katalog')) ?></h2>
  <form class="fo-search" id="fo-search" method="get" action="<?= e(url('/admin/system/fonts')) ?>" role="search">
    <div class="f"><label for="fo-q"><?= e(__('Schrift suchen')) ?></label><input id="fo-q" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('z. B. Inter, Lora, Space Grotesk')) ?>"></div>
    <div class="f"><label for="fo-cat"><?= e(__('Kategorie')) ?></label><select id="fo-cat" name="cat">
      <option value=""><?= e(__('Alle')) ?></option>
      <?php foreach (Fonts::CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= $cat === $k ? ' selected' : '' ?>><?= e(__($l)) ?></option><?php endforeach; ?>
    </select></div>
    <button class="adm-btn" type="submit"><?= e(__('Suchen')) ?></button>
  </form>
  <?php if (!$catalogOk): ?>
  <p class="adm-flash adm-flash--error"><?= e(__('Der Schriften-Katalog ist gerade nicht erreichbar (api.fontsource.org). Bitte später erneut versuchen.')) ?></p>
  <?php else: ?>
  <p class="adm-muted" aria-live="polite"><?= e($q === '' ? __('Beliebte Schriften – {n} Familien im Katalog (nur freie Lizenzen: OFL, Apache, UFL).', ['n' => $catalogSize]) : (count($results) === 1 ? __('1 Treffer') : __('{n} Treffer', ['n' => count($results)]))) ?></p>
  <ul class="fo-grid">
    <?php foreach ($results as $f): $inst = isset($installed[$f['id']]); ?>
    <li class="fo-card">
      <h3><?= e($f['family']) ?><?php if ($inst): ?> <span class="adm-badge"><?= e(__('installiert')) ?></span><?php endif; ?></h3>
      <p class="fo-sample" data-font-sample data-font-src="<?= e($preview($f['id'])) ?>" data-font-name="fp-<?= e($f['id']) ?>"><?= e($sample) ?></p>
      <p class="fo-meta"><?= e($catLabel($f['category'])) ?> · <?= e(count($f['weights']) === 1 ? __('1 Gewicht') : __('{n} Gewichte', ['n' => count($f['weights'])])) ?><?= in_array('italic', $f['styles'], true) ? ' · ' . e(__('kursiv')) : '' ?><?= $f['variable'] ? ' · ' . e(__('variabel')) : '' ?> · <?= e($f['license']) ?></p>
      <a class="adm-btn adm-btn--small" href="<?= e($qs(['font' => $f['id']]) . '#installieren') ?>" aria-label="<?= e(__('„{name}“ auswählen', ['name' => $f['family']])) ?>"><?= e($inst ? __('Ändern') : __('Auswählen')) ?></a>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$results): ?><p><?= e(__('Keine Schrift gefunden.')) ?></p><?php endif; ?>
  <p class="f-help"><?= e(__('Katalog: Fontsource (Spiegel von Google Fonts), einmal täglich vom Server abgefragt. Vorschauen werden über diese Website geladen.')) ?></p>
  <?php endif; ?>
</section>
