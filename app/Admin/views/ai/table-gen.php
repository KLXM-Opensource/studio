<?php
/**
 * Bereich „KLXM AI“ → Tabellen-Generator: Beschreibung → Tabellen-Entwurf (nur vorhandene Feldtypen) → bearbeitbare Vorschau →
 * „Tabelle anlegen“ über Tables::validate/create. Beispiel-Einträge nur auf Wunsch, als Entwurf und deutlich markiert.
 */
use Core\AI\Assist;
use Core\AI\Generator;

$brand = Assist::brand();
$cfg = ['types' => Generator::tableTypes(), 'tables' => array_column(\Core\Data\Tables::content(), 'name', 'handle')];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/ai')) ?>"><?= e($brand) ?></a></p><h1><?= e(__('Tabellen-Generator')) ?></h1>
    <p class="adm-muted"><?= e(__('Beschreiben Sie, was Sie verwalten möchten – die KI entwirft eine Datentabelle mit passenden Feldern. Sie prüfen und ändern den Entwurf, bevor er angelegt wird.')) ?></p></div>
</header>
<?php if (!Assist::available('text')): ?><p class="adm-flash adm-flash--info"><?= e(__('Die Text-KI ist für diese Website nicht eingeschaltet.')) ?></p><?php return; endif; ?>
<div class="kia-gen" data-kia-tablegen="<?= e(json_encode($cfg, JSON_UNESCAPED_UNICODE)) ?>">
  <section class="adm-card" aria-labelledby="kia-tg-h">
    <h2 id="kia-tg-h"><?= e(__('Was soll die Tabelle enthalten?')) ?></h2>
    <div class="f"><label for="kia-tg-desc" class="adm-sr"><?= e(__('Beschreibung')) ?></label>
      <textarea id="kia-tg-desc" rows="3" maxlength="2000" data-kia-off placeholder="<?= e(__('z. B. Veranstaltungen mit Ort, Datum, Anmeldung und Kategorie')) ?>"></textarea></div>
    <button type="button" class="adm-btn adm-btn--primary kia-btn" data-kia-tg-go><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('Tabelle entwerfen')) ?></button>
    <div data-kia-tg-status aria-live="polite"></div>
  </section>
  <section class="adm-card" data-kia-tg-preview hidden aria-labelledby="kia-tgp-h"></section>
</div>
