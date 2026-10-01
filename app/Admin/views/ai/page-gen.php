<?php
/**
 * Bereich „KLXM AI“ → Seiten-Generator: Thema/Ziel → Seiten-Entwurf aus vorhandenen Blöcken → bearbeitbare Vorschau →
 * „Als Entwurf anlegen“ (unveröffentlicht, öffnet im Editor). Fakten nur aus dem Auftrag und den Angaben der Website;
 * fehlende Angaben werden „[bitte ergänzen: …]“ – solche Seiten lassen sich nicht veröffentlichen.
 */
use Core\AI\Assist;
use Core\AI\Generator;
use Core\Lang;

$brand = Assist::brand();
$catalog = Generator::blockCatalog();
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/ai')) ?>"><?= e($brand) ?></a></p><h1><?= e(__('Seiten-Generator')) ?></h1>
    <p class="adm-muted"><?= e(__('Die KI entwirft eine neue Seite aus den Blöcken Ihres Designs. Fakten stammen nur aus Ihrem Auftrag und den Angaben der Website – was fehlt, wird als [bitte ergänzen: …] markiert. Die Seite entsteht als Entwurf.')) ?></p></div>
</header>
<?php if (!Assist::available('text')): ?><p class="adm-flash adm-flash--info"><?= e(__('Die Text-KI ist für diese Website nicht eingeschaltet.')) ?></p><?php return; endif; ?>
<div class="kia-gen" data-kia-pagegen="<?= e(json_encode(['catalog' => $catalog], JSON_UNESCAPED_UNICODE)) ?>">
  <section class="adm-card" aria-labelledby="kia-pg-h">
    <h2 id="kia-pg-h"><?= e(__('Auftrag')) ?></h2>
    <div class="adm-fields">
      <div class="f"><label for="kia-pg-topic"><?= e(__('Thema und Ziel der Seite')) ?> <span class="req" aria-hidden="true">*</span></label>
        <textarea id="kia-pg-topic" rows="3" maxlength="1500" required data-kia-off placeholder="<?= e(__('z. B. Seite zur Reisemedizin: Beratung vor Fernreisen, welche Impfungen es gibt, wie man einen Termin bekommt')) ?>"></textarea>
        <p class="f-help"><?= e(__('Worum geht es, was sollen Besucher danach wissen oder tun?')) ?></p></div>
      <div class="f"><label for="kia-pg-facts"><?= e(__('Fakten & Stichpunkte (optional)')) ?></label>
        <textarea id="kia-pg-facts" rows="4" maxlength="4000" data-kia-off placeholder="<?= e(__('z. B. Gelbfieber-Impfung vor Ort, Beratung dienstags 14–16 Uhr, Kosten übernimmt oft die Kasse')) ?>"></textarea>
        <p class="f-help"><?= e(__('Nur was hier, im Thema oder in den einbezogenen Website-Angaben steht, darf als Fakt auf die Seite. Alles andere markiert die KI mit [bitte ergänzen: …].')) ?></p></div>
      <div class="f f--half"><label for="kia-pg-ctx"><?= e(__('Website-Angaben einbeziehen')) ?></label><select id="kia-pg-ctx">
        <option value="basic"><?= e(__('Nur Name & Kontakt')) ?></option><option value="full"><?= e(__('Alles Passende (zentrale Angaben, ähnliche Seiten)')) ?></option><option value="none"><?= e(__('Keine')) ?></option></select></div>
      <div class="f f--half"><label for="kia-pg-aud"><?= e(__('Zielgruppe (optional)')) ?></label><input id="kia-pg-aud" maxlength="200" placeholder="<?= e(__('z. B. Familien, ältere Menschen')) ?>"></div>
      <div class="f f--half"><label for="kia-pg-tone"><?= e(__('Ton')) ?></label><select id="kia-pg-tone"><option value="sachlich"><?= e(__('sachlich')) ?></option><option value="freundlich"><?= e(__('freundlich')) ?></option><option value="foermlich"><?= e(__('förmlich')) ?></option></select></div>
      <?php if (Lang::multi()): ?><div class="f f--half"><label for="kia-pg-lang"><?= e(__('Sprache')) ?></label><select id="kia-pg-lang"><?php foreach (Lang::all() as $c => $l): ?><option value="<?= e($c) ?>"><?= e($l) ?></option><?php endforeach; ?></select></div><?php endif; ?>
      <div class="f f--half"><label for="kia-pg-parent"><?= e(__('Übergeordnete Seite')) ?></label><select id="kia-pg-parent"><option value=""><?= e(__('– oberste Ebene –')) ?></option>
        <?php foreach (\Core\Pages::flat() as $p): if ($p['type'] !== 'page' || $p['is_home']) continue; ?><option value="<?= (int) $p['id'] ?>"><?= e(str_repeat('  ', (int) $p['depth']) . $p['title']) ?></option><?php endforeach; ?></select></div>
      <fieldset class="f kia-pg-blocks"><legend><?= e(__('Erlaubte Blöcke')) ?></legend>
        <div class="f-multi"><?php foreach ($catalog as $type => $b): ?><label class="f-check"><input type="checkbox" value="<?= e($type) ?>" checked> <span><?= e($b['label']) ?></span></label><?php endforeach; ?></div></fieldset>
    </div>
    <button type="button" class="adm-btn adm-btn--primary kia-btn" data-kia-pg-go><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('Seite entwerfen')) ?></button>
    <div data-kia-pg-status aria-live="polite"></div>
  </section>
  <section class="adm-card" data-kia-pg-preview hidden aria-labelledby="kia-pgp-h"></section>
</div>
