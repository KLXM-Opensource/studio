<?php
/**
 * Bereich „KLXM AI“ → Texte: freier Schreib-Assistent mit Zusammenhang (Seite/Eintrag). Das Ergebnis lässt sich kopieren
 * oder als ENTWURF in ein Textfeld einer Seite einfügen (resources/js/_ai.js → initWrite).
 */
use Core\AI\Assist;
use Core\Data\Entries;
use Core\Data\Tables;

$brand = Assist::brand();
if (!Assist::available('text')): ?>
<header class="adm-head"><div><p class="adm-eyebrow"><?= e($brand) ?></p><h1><?= e(__('Texte')) ?></h1></div></header>
<p class="adm-flash adm-flash--info"><?= e(__('Die Text-KI ist für diese Website nicht eingeschaltet.')) ?> <a href="<?= e(url('/admin/ai')) ?>"><?= e(__('Zur Übersicht')) ?></a></p>
<?php return; endif;
$pages = can('pages.edit') ? \Core\Pages::flat() : [];
$tables = array_values(array_filter(Tables::content(), fn($t) => can('data.edit', $t['handle'])));
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/ai')) ?>"><?= e($brand) ?></a></p><h1><?= e(__('Texte')) ?></h1>
    <p class="adm-muted"><?= e(__('Beschreiben Sie, was entstehen soll – oder fügen Sie einen Text ein und lassen Sie ihn überarbeiten. Das Ergebnis ist ein Vorschlag: kopieren oder als Entwurf in eine Seite einfügen.')) ?></p></div>
</header>
<div class="adm-grid2 adm-grid2--wide kia-write" data-kia-write>
  <section class="adm-card" aria-labelledby="kia-w-h">
    <h2 id="kia-w-h"><?= e(__('Auftrag')) ?></h2>
    <div class="adm-fields">
      <div class="f"><label for="kia-w-ctx"><?= e(__('Zusammenhang (optional)')) ?></label>
        <select id="kia-w-ctx" data-kia-w-ctx aria-describedby="kia-w-ctx-h">
          <option value=""><?= e(__('– ohne –')) ?></option>
          <?php if ($pages): ?><optgroup label="<?= e(__('Seiten')) ?>"><?php foreach ($pages as $p): if ($p['type'] !== 'page') continue; ?>
            <option value="page:<?= (int) $p['id'] ?>"><?= e(str_repeat('  ', (int) $p['depth']) . $p['title'] . (\Core\Lang::multi() ? ' (' . strtoupper(\Core\Lang::norm($p['lang'] ?? null)) . ')' : '')) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
          <?php foreach ($tables as $t): $rows = Entries::query($t, ['status' => 'all', 'lang' => 'all', 'source' => 'own', 'limit' => 40, 'sort' => 'updated_at', 'dir' => 'desc']); if (!$rows) continue; ?>
          <optgroup label="<?= e($t['name']) ?>"><?php foreach ($rows as $en): ?><option value="entry:<?= e($t['handle']) ?>:<?= (int) $en['id'] ?>"><?= e(Entries::title($t, $en)) ?></option><?php endforeach; ?></optgroup>
          <?php endforeach; ?>
        </select>
        <p class="f-help" id="kia-w-ctx-h"><?= e(__('Titel und Text dieser Seite bzw. dieses Eintrags gibt die KI als Hintergrund mit – so bleibt sie beim Thema.')) ?></p></div>
      <div class="f"><label for="kia-w-src"><?= e(__('Ausgangstext (optional)')) ?></label>
        <textarea id="kia-w-src" rows="6" data-kia-w-src data-kia-off placeholder="<?= e(__('Text einfügen, den die KI überarbeiten soll – leer lassen, um neu zu schreiben.')) ?>"></textarea></div>
      <fieldset class="f kia-w-format"><legend><?= e(__('Ergebnis als')) ?></legend>
        <label class="f-check"><input type="radio" name="kia-w-format" value="rich" checked> <span><?= e(__('Formatierter Text (Absätze, Listen)')) ?></span></label>
        <label class="f-check"><input type="radio" name="kia-w-format" value="plain"> <span><?= e(__('Reiner Text')) ?></span></label></fieldset>
    </div>
    <button type="button" class="adm-btn adm-btn--primary kia-btn" data-kia-w-open><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('KI-Assistent öffnen')) ?></button>
  </section>
  <section class="adm-card" aria-labelledby="kia-wr-h">
    <h2 id="kia-wr-h"><?= e(__('Ergebnis')) ?></h2>
    <p class="adm-muted" data-kia-w-empty><?= e(__('Noch kein Ergebnis. Übernommene Vorschläge erscheinen hier und lassen sich weiter bearbeiten.')) ?></p>
    <div class="kia-w-result" data-kia-w-result hidden>
      <span class="kia-badge"><?= e(__('KI-Vorschlag – bitte prüfen')) ?></span>
      <div class="kia-preview kia-preview--html kia-w-edit" contenteditable="true" role="textbox" aria-multiline="true" aria-label="<?= e(__('Ergebnis bearbeiten')) ?>" data-kia-w-out></div>
      <div class="kia-actions">
        <button type="button" class="adm-btn adm-btn--small" data-kia-w-copy><?= e(__('Kopieren')) ?></button>
        <?php if ($pages): ?><button type="button" class="adm-btn adm-btn--small" data-kia-w-insert><?= e(__('In eine Seite einfügen …')) ?></button><?php endif; ?>
      </div>
      <div class="kia-w-target" data-kia-w-target hidden>
        <div class="f"><label for="kia-w-page"><?= e(__('Seite')) ?></label><select id="kia-w-page" data-kia-w-page><option value=""><?= e(__('– wählen –')) ?></option>
          <?php foreach ($pages as $p): if ($p['type'] !== 'page') continue; ?><option value="<?= (int) $p['id'] ?>"><?= e(str_repeat('  ', (int) $p['depth']) . $p['title'] . (\Core\Lang::multi() ? ' (' . strtoupper(\Core\Lang::norm($p['lang'] ?? null)) . ')' : '')) ?></option><?php endforeach; ?></select></div>
        <div class="f"><label for="kia-w-field"><?= e(__('Textfeld')) ?></label><select id="kia-w-field" data-kia-w-field disabled></select></div>
        <fieldset class="f"><legend><?= e(__('Einfügen')) ?></legend>
          <label class="f-check"><input type="radio" name="kia-w-mode" value="append" checked> <span><?= e(__('ans Ende anhängen')) ?></span></label>
          <label class="f-check"><input type="radio" name="kia-w-mode" value="replace"> <span><?= e(__('Inhalt ersetzen')) ?></span></label></fieldset>
        <button type="button" class="adm-btn adm-btn--primary adm-btn--small" data-kia-w-save disabled><?= e(__('Als Entwurf einfügen')) ?></button>
        <p class="adm-muted kia-small"><?= e(__('Die Seite bleibt, wie sie ist, bis jemand den Entwurf veröffentlicht.')) ?></p>
      </div>
      <div data-kia-w-status aria-live="polite"></div>
    </div>
  </section>
</div>
