<?php
/**
 * Daten → Felder & Einstellungen → „Benachrichtigungen“ → „Push“ (Core\Push\Topics): Besucher können neue Einträge abonnieren,
 * Titel-Vorlage, Kurztext, Filter. Gespeichert unter settings.push (nur gesetzte Werte). Nur mit Funktion „push“, nicht für Eingänge.
 * @var ?array $table  @var array $def
 */
use Core\Push\Topics;

if (($def['settings']['kind'] ?? 'content') === 'inbox') return;
if (!\Core\Push\Push::on()): // Funktion aus: Hinweis statt nichts – wer Funktionen schalten darf, bekommt den Weg dorthin ?>
<section class="set-group dt-push" id="table-push" aria-labelledby="t-push-h">
  <h3 class="set-group__title" id="t-push-h"><?= e(__('Push-Benachrichtigungen für Besucher')) ?></h3>
  <div class="set-list"><div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Auf dieser Website aus')) ?></span>
    <span class="set-row__sub"><?= e(__('Push-Benachrichtigungen sind auf dieser Website aus. Eingeschaltet können Besucher neue Einträge dieser Tabelle abonnieren.')) ?></span></div>
    <div class="set-row__ctl"><?php if (\Core\Features::canManage()): ?><a href="<?= e(url('/admin/funktionen#f-push')) ?>"><?= e(__('Unter Funktionen & Erweiterungen einschalten')) ?></a>
    <?php elseif (\Core\Features::canView()): ?><span><?= e(__('Einschalten kann die Netzwerk-Administration bzw. Agentur (Funktionen & Erweiterungen).')) ?></span><?php endif; ?></div></div></div>
</section>
<?php return; endif;
$p = (array) ($def['settings']['push'] ?? []);
$pf = \Core\Data\Tables::dataFields((array) ($def['fields'] ?? []));
$pOpts = fn(array $types) => array_column(array_filter($pf, fn($f) => in_array($f['type'] ?? '', $types, true)), 'label', 'name');
$pRoute = ($def['settings']['route'] ?? '') !== '' && !empty($def['settings']['detail_page_id']);
$pCount = $table && !empty($table['id']) ? Topics::subscribers($table) : 0;
?>
<section class="set-group dt-push" id="table-push" aria-labelledby="t-push-h">
  <h3 class="set-group__title" id="t-push-h"><?= e(__('Push-Benachrichtigungen für Besucher')) ?></h3>
  <div class="set-list">
    <div class="f f--bool"><input type="hidden" name="settings[push][enabled]" value="0">
      <label class="f-check"><input type="checkbox" name="settings[push][enabled]" value="1"<?= !empty($p['enabled']) ? ' checked' : '' ?> data-push-toggle> <span><?= e(__('Besucher können neue Einträge abonnieren (Push)')) ?></span></label>
      <p class="f-help"><?= e(__('Wird ein Eintrag zum ersten Mal veröffentlicht, erhalten die Abos eine Mitteilung mit Titel, Kurztext und Link zur Detailseite – nicht bei späteren Änderungen. Den Knopf zeigt der Block „Benachrichtigungen abonnieren“ (z. B. auf der Liste oder der Detailseite).')) ?></p>
      <?php if (!$pRoute): ?><p class="dt-note"><?= e(__('Diese Tabelle hat noch keine Detailseiten (Adresse unter „Website“) – die Mitteilung führt dann zur Startseite.')) ?></p><?php endif; ?></div>
    <div class="f f--inline"><label for="t-push-title"><?= e(__('Titel der Mitteilung')) ?></label>
      <p class="f-help"><?= e(__('Platzhalter: {title} (Titel des Eintrags), {table}, {site} und jedes Feld als {feldname}. Leer = Titel des Eintrags.')) ?></p>
      <input id="t-push-title" name="settings[push][title]" value="<?= e((string) ($p['title'] ?? '')) ?>" maxlength="120" placeholder="{title}"></div>
    <div class="f f--inline"><label for="t-push-desc"><?= e(__('Beschreibung für Besucher (optional)')) ?></label>
      <p class="f-help"><?= e(__('Erscheint, wenn Besucher zwischen mehreren Kanälen wählen (Block, Banner, Glocke).')) ?></p>
      <input id="t-push-desc" name="settings[push][description]" value="<?= e((string) ($p['description'] ?? '')) ?>" maxlength="200" placeholder="<?= e(__('z. B. Neue Angebote, etwa zweimal im Monat')) ?>"></div>
    <div class="f f--inline"><label for="t-push-body"><?= e(__('Kurztext aus')) ?></label>
      <select id="t-push-body" name="settings[push][body]">
        <option value=""><?= e(__('– Beschreibungsfeld bzw. erstes Textfeld –')) ?></option>
        <option value="-"<?= ($p['body'] ?? '') === '-' ? ' selected' : '' ?>><?= e(__('kein Kurztext')) ?></option>
        <?php foreach ($pOpts(['text', 'textarea', 'richtext']) as $k => $l): ?><option value="<?= e($k) ?>"<?= ($p['body'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select></div>
    <div class="f f--inline"><label for="t-push-ff"><?= e(__('Nur Einträge, bei denen Feld …')) ?></label>
      <select id="t-push-ff" name="settings[push][filter][field]"><option value=""><?= e(__('– alle Einträge –')) ?></option>
      <?php foreach ($pOpts(['text', 'select', 'multiselect', 'bool', 'relation']) as $k => $l): ?><option value="<?= e($k) ?>"<?= ($p['filter']['field'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    <div class="f f--inline"><label for="t-push-fv"><?= e(__('… diesen Wert hat')) ?></label>
      <p class="f-help"><?= e(__('Auswahl: Kurzname der Option; Ja/Nein: „ja“ oder „nein“. Höchstens {n} Mitteilungen je Stunde und Tabelle; in der Testumgebung keine an Besucher.', ['n' => (int) app()->config->get('push_topic_per_hour', \Core\Push\Push::TOPIC_PER_HOUR)])) ?></p>
      <input id="t-push-fv" name="settings[push][filter][value]" value="<?= e((string) ($p['filter']['value'] ?? '')) ?>" maxlength="120" placeholder="<?= e(__('z. B. presse')) ?>"></div>
    <?php if ($table && !empty($table['id'])): ?>
    <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Abos: {n}', ['n' => fmt()->number($pCount)])) ?></span></div>
      <div class="set-row__ctl"><a href="<?= e(url('/admin/system#push')) ?>"><?= e(__('Status und Datenschutz-Hinweis')) ?></a></div></div>
    <?php endif; ?>
  </div>
</section>
