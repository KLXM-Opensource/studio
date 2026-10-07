<?php
/**
 * Mitteilungen → Auf der Website: Banner (oben/unten, schließbar, erscheint verzögert bzw. ab der zweiten Seite) und schwebende
 * Glocke mit Kanalauswahl (Core\Push\Visitor::overlay), dazu der Block „Benachrichtigungen abonnieren“ als Hinweis.
 * @var array $cfg  @var bool $canSend
 */
use Core\Push\Channels;
use Core\Push\Visitor;

$b = \Core\Http\Controllers\Admin\MessagesController::BASE;
$public = Channels::all(true);
$sel = Visitor::siteChannels($cfg);
?>
<h1 class="adm-sr"><?= e(__('Auf der Website')) ?></h1>
<div class="pm pm--page">
  <div class="pm-bar"><div class="pm-bar__title"><h2><?= e(__('Auf der Website')) ?></h2><small><?= e(__('Wie Besucher Mitteilungen abonnieren: Block, Banner und Glocke')) ?></small></div></div>
  <form class="pm-scroll pm-statpage" method="post" action="<?= e(url($b . '/website')) ?>">
    <?= csrf_field() ?>
    <fieldset class="set-group"<?= $canSend ? '' : ' disabled' ?>>
      <legend class="set-group__title"><?= e(__('Anzeige')) ?></legend>
      <div class="set-list">
        <div class="set-row set-row--keep"><div class="set-row__main"><label class="set-row__label" for="pw-banner"><?= e(__('Banner')) ?></label>
          <span class="set-row__sub"><?= e(__('Schmale Leiste mit kurzer Einladung – erscheint erst nach einer Wartezeit, schließbar; der Browser merkt sich „Nein, danke“.')) ?></span></div>
          <div class="set-row__ctl"><input type="hidden" name="banner" value="0"><input type="checkbox" role="switch" id="pw-banner" name="banner" value="1"<?= $cfg['banner'] ? ' checked' : '' ?>></div></div>
        <div class="set-row set-row--keep"><div class="set-row__main"><label class="set-row__label" for="pw-bell"><?= e(__('Schwebende Glocke')) ?></label>
          <span class="set-row__sub"><?= e(__('Kleiner runder Knopf am Rand – öffnet die Auswahl der Kanäle. Abonnierte sehen dort ihr Abo und können es ändern.')) ?></span></div>
          <div class="set-row__ctl"><input type="hidden" name="bell" value="0"><input type="checkbox" role="switch" id="pw-bell" name="bell" value="1"<?= $cfg['bell'] ? ' checked' : '' ?>></div></div>
        <div class="f f--inline"><label for="pw-pos"><?= e(__('Banner-Position')) ?></label><select id="pw-pos" name="position">
          <option value="bottom"<?= $cfg['position'] === 'bottom' ? ' selected' : '' ?>><?= e(__('unten')) ?></option><option value="top"<?= $cfg['position'] === 'top' ? ' selected' : '' ?>><?= e(__('oben')) ?></option></select></div>
        <div class="f f--inline"><label for="pw-bpos"><?= e(__('Glocke')) ?></label><select id="pw-bpos" name="bell_position">
          <option value="right"<?= $cfg['bell_position'] === 'right' ? ' selected' : '' ?>><?= e(__('unten rechts')) ?></option><option value="left"<?= $cfg['bell_position'] === 'left' ? ' selected' : '' ?>><?= e(__('unten links')) ?></option></select></div>
        <div class="f f--inline"><label for="pw-delay"><?= e(__('Banner nach (Sekunden)')) ?></label><input type="number" id="pw-delay" name="delay" min="0" max="120" value="<?= (int) $cfg['delay'] ?>" class="f-in--short"></div>
        <div class="set-row set-row--keep"><div class="set-row__main"><label class="set-row__label" for="pw-second"><?= e(__('Banner erst ab der zweiten Seite'))  ?></label>
          <span class="set-row__sub"><?= e(__('Wer nur kurz vorbeischaut, sieht kein Banner. Erkannt am Aufruf von einer anderen Seite dieser Website – ohne Speichern im Browser.')) ?></span></div>
          <div class="set-row__ctl"><input type="hidden" name="second" value="0"><input type="checkbox" role="switch" id="pw-second" name="second" value="1"<?= $cfg['second'] ? ' checked' : '' ?>></div></div>
      </div>
    </fieldset>
    <fieldset class="set-group"<?= $canSend ? '' : ' disabled' ?>>
      <legend class="set-group__title"><?= e(__('Texte')) ?></legend>
      <div class="set-list">
        <div class="f"><label for="pw-title"><?= e(__('Überschrift')) ?></label><input id="pw-title" name="title" maxlength="80" value="<?= e($cfg['title']) ?>" placeholder="<?= e(lt('Nichts mehr verpassen?')) ?>"></div>
        <div class="f"><label for="pw-text"><?= e(__('Text')) ?></label><textarea id="pw-text" name="text" rows="2" maxlength="240" placeholder="<?= e(lt('Auf Wunsch benachrichtigen wir Sie auf diesem Gerät über Neuigkeiten – ohne Anmeldung, jederzeit abbestellbar.')) ?>"><?= e($cfg['text']) ?></textarea></div>
        <div class="f"><label for="pw-btn"><?= e(__('Knopf im Banner')) ?></label><input id="pw-btn" name="button" maxlength="40" value="<?= e($cfg['button']) ?>" placeholder="<?= e(lt('Auswählen …')) ?>"></div>
      </div>
      <p class="set-group__note"><?= e(__('Leer = Standardtexte (in jeder Sprache der Website übersetzt).')) ?></p>
    </fieldset>
    <fieldset class="set-group"<?= $canSend ? '' : ' disabled' ?>>
      <legend class="set-group__title"><?= e(__('Kanäle zur Auswahl')) ?></legend>
      <div class="set-list">
        <?php if (!$public): ?><div class="set-row"><span class="set-row__sub"><?= e(__('Noch keine öffentlichen Kanäle – Banner und Glocke erscheinen erst, wenn es etwas zu abonnieren gibt.')) ?> <a href="<?= e(url($b . '/kanaele?neu=1')) ?>"><?= e(__('Kanal anlegen')) ?></a></span></div><?php endif; ?>
        <div class="set-row set-row--stack"><div class="set-checks">
        <?php foreach ($public as $t => $c): ?><label class="f-check"><input type="checkbox" name="channels[]" value="<?= e($t) ?>"<?= in_array($t, $cfg['channels'], true) ? ' checked' : '' ?>> <span><?= e($c['name']) ?></span></label><?php endforeach; ?>
        </div></div>
      </div>
      <p class="set-group__note"><?= e(__('Nichts angehakt = alle öffentlichen Kanäle ({list}).', ['list' => implode(', ', array_map(fn($t) => $public[$t]['name'], $sel)) ?: '–'])) ?></p>
    </fieldset>
    <?php if ($canSend): ?><div class="set-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Speichern')) ?></button></div><?php endif; ?>
    <section class="set-group">
      <h3 class="set-group__title"><?= e(__('Block und Kit')) ?></h3>
      <div class="set-list"><div class="set-row set-row--stack"><div class="set-row__body">
        <p><?= e(__('Auf einzelnen Seiten: Block „Benachrichtigungen abonnieren“ (Gruppe Daten) mit einem oder mehreren Kanälen. In Kit-Vorlagen: push_subscribe(…).')) ?></p>
        <p><?= e(__('Die Abfrage des Browsers erscheint immer erst nach einem Klick. Auf iPhone und iPad geht Push nur in der installierten Web-App (Funktion „App-Icon & PWA“).')) ?></p>
      </div></div></div>
    </section>
  </form>
</div>
