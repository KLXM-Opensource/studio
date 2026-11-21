<?php
/**
 * Grundeinstellungen → E-Mail-Versand → Übersicht (MailAccountsController::overview, Core\Mail\Overview): „Welche Adresse gilt
 * wofür“ – je Mailart wirksamer Absender (mit Herkunft und Geltungsbereich), Empfänger, Antwortadresse, Impressum, Zustellung,
 * Testnachricht an die eigene Adresse. Nur lesend; geändert wird über die Links. Stile: resources/css/mailoverview.css.
 * @var list<array> $rows  @var array $me  @var ?array $network  @var bool $netView  @var bool $canNetwork
 */
use Core\Mail\Accounts;

$staging = environment() !== 'production';
$who = fn(array $a) => $a['from'] !== '' ? trim(($a['from_name'] !== '' ? $a['from_name'] . ' ' : '') . '<' . $a['from'] . '>') : __('kein Absender');
$lvl = fn(string $l) => match ($l) { 'ok' => 'ok', 'error' => 'warn', default => 'muted' };
$multi = \Core\Sites::multi();
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/system')) ?>"><?= e(__('Grundeinstellungen')) ?></a> › <a href="<?= e(url('/admin/system#mail')) ?>"><?= e(__('E-Mail-Versand')) ?></a></p>
    <h1><?= e(__('E-Mail: welche Adresse gilt wofür')) ?></h1>
    <p class="adm-muted"><?= e(__('Für jede Art von E-Mail dieser Website: der Absender, der beim Versand wirklich verwendet wird, die Empfänger, die Antwortadresse und das Impressum. Nur zur Ansicht – geändert wird über „Ändern“.')) ?></p></div>
  <div class="adm-head__actions"><a class="adm-btn adm-btn--ghost adm-btn--small" href="<?= e(url('/admin/system/mail')) ?>"><?= icon('envelope-simple') ?> <?= e(__('E-Mail-Konten')) ?></a></div>
</header>

<div class="adm-tabs-form--side us-shell" data-secnav>
<?php $current = 'mail'; include __DIR__ . '/_sidenav.php'; ?>
  <div class="us-main">
  <div class="set-page mo-page">
    <?php // Geltungsbereich: was gilt nur hier, was für alle Websites (Netzwerk) ?>
    <section class="set-group mo-scope" aria-labelledby="mo-scope-h">
      <h2 class="set-group__title" id="mo-scope-h"><?= e(__('Wo wird was eingestellt?')) ?></h2>
      <div class="mo-scope__grid">
        <div class="mo-scope__box">
          <p class="mo-scope__tag mo-tag mo-tag--site"><?= e(__('nur diese Website')) ?> · <?= e(site()->label()) ?></p>
          <p class="f-help"><?= e(__('Konto „Standard“ und weitere Konten, Verwendung je Zweck, Empfänger für Benachrichtigungen, Test-Umleitung, Impressum für E-Mails – unter Grundeinstellungen → E-Mail-Versand. Empfänger einzelner Formulare in der jeweiligen Tabelle.')) ?></p>
        </div>
        <?php if ($multi): ?>
        <div class="mo-scope__box mo-scope__box--net">
          <p class="mo-scope__tag mo-tag mo-tag--net"><?= e(__('alle Websites (Netzwerk)')) ?></p>
          <p class="f-help"><?= e($network ? __('Netzwerk-Konto: {how}', ['how' => Accounts::describe($network)]) . '.' : __('Kein Netzwerk-Konto eingerichtet.')) ?>
            <?= e(__('Es versendet die Mails an Netzwerk-Konten und springt ein, wenn das Konto dieser Website nicht versandbereit ist.')) ?>
            <?php if ($canNetwork): ?><a href="<?= e(url('/admin/network/email')) ?>"><?= e(__('Netzwerk → E-Mail')) ?></a><?php else: ?><?= e(__('Eingerichtet von der Netzwerk-Administration.')) ?><?php endif; ?></p>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($staging): ?><p class="adm-flash adm-flash--info" role="note"><?= e(__('Diese Website ist eine Testumgebung ({env}): Sie verschickt keine E-Mails, sondern protokolliert sie nur. Mit „wirklich senden“ geht nur die Testnachricht an Ihre Adresse.', ['env' => environment()])) ?></p><?php endif; ?>
    </section>

    <section class="set-group" aria-labelledby="mo-h">
      <h2 class="set-group__title" id="mo-h"><?= e(__('Mailarten')) ?></h2>
      <div class="mo-wrap">
      <table class="adm-table mo-table">
        <thead><tr><th scope="col"><?= e(__('Mailart')) ?></th><th scope="col"><?= e(__('Absender')) ?></th><th scope="col"><?= e(__('Empfänger')) ?></th>
          <th scope="col"><?= e(__('Antworten an')) ?></th><th scope="col"><?= e(__('Impressum')) ?></th><th scope="col"><?= e(__('Zustellung')) ?></th><th scope="col"><span class="adm-sr"><?= e(__('Test')) ?></span></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): $a = $row['acc']; $net = ($a['scope'] ?? 'site') === 'network'; $aid = 'art-' . preg_replace('~[^a-z0-9_-]~', '-', $row['key']); ?>
          <tr id="<?= e($aid) ?>">
            <th scope="row" data-label="<?= e(__('Mailart')) ?>"><strong><?= e($row['label']) ?></strong><span class="mo-sub"><?= e($row['help']) ?></span>
              <span class="mo-sub"><?= e(__('Zweck: {name}', ['name' => $row['purpose_label']])) ?></span></th>
            <td data-label="<?= e(__('Absender')) ?>"><span class="mo-addr"><?= e($who($a)) ?></span>
              <span class="mo-sub"><?= e($row['origin_text']) ?></span>
              <span class="mo-tag mo-tag--<?= $net ? 'net' : 'site' ?>"><?= e($row['scope_text']) ?></span>
              <?php if ($row['origin'] === 'fallback' || $row['origin'] === 'network-missing' || $row['origin'] === 'missing'): ?><span class="adm-badge adm-badge--warn"><?= e(__('Rückfall')) ?></span><?php endif; ?>
              <?php if ($row['edit_from']): ?><a class="mo-edit" href="<?= e(url($row['edit_from'])) ?>"><?= e(__('Ändern')) ?></a><?php endif; ?></td>
            <td data-label="<?= e(__('Empfänger')) ?>"><span class="mo-addr"><?= e($row['to']) ?></span><span class="mo-sub"><?= e($row['to_src']) ?></span>
              <?php if ($row['edit_to']): ?><a class="mo-edit" href="<?= e(url($row['edit_to'])) ?>"><?= e(__('Ändern')) ?></a><?php endif; ?></td>
            <td data-label="<?= e(__('Antworten an')) ?>"><?= e($row['reply']) ?></td>
            <td data-label="<?= e(__('Impressum')) ?>"><span class="adm-badge adm-badge--<?= $lvl($row['imprint']['level']) ?>"><?= e($row['imprint']['text']) ?></span></td>
            <td data-label="<?= e(__('Zustellung')) ?>"><span class="adm-badge adm-badge--<?= $lvl($row['state']['level']) ?>"><?= e($row['state']['text']) ?></span></td>
            <td class="mo-test">
              <form method="post" action="<?= e(url('/admin/system/mail/uebersicht/test/' . $row['key'])) ?>"><?= csrf_field() ?>
                <?php if ($staging): ?><label class="mk-test__real"><input type="checkbox" name="real" value="1"> <?= e(__('wirklich senden')) ?></label><?php endif; ?>
                <button class="adm-btn adm-btn--small adm-btn--ghost" title="<?= e(__('Testnachricht dieser Mailart an {to}', ['to' => (string) $me['email']])) ?>"><?= e(__('Testen')) ?></button>
              </form></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <p class="set-group__note"><?= e(__('Die Testnachricht geht immer an Ihre eigene Adresse ({to}) – mit Absender, Antwortadresse und Impressum dieser Mailart.', ['to' => (string) $me['email']])) ?></p>
    </section>

    <section class="set-group" aria-labelledby="mo-rule-h">
      <h2 class="set-group__title" id="mo-rule-h"><?= e(__('So wird der Absender bestimmt')) ?></h2>
      <ol class="mo-rules">
        <?php if ($multi): ?><li><?= e(__('Mails an Netzwerk-Konten gehen über das Netzwerk-Konto (alle Websites). Fehlt es, über das Konto dieser Website.')) ?></li><?php endif; ?>
        <li><?= e(__('Sonst das Konto, das unter E-Mail-Versand → Verwendung für den Zweck gewählt ist – ohne Wahl das Konto „Standard“ dieser Website.')) ?></li>
        <?php if ($multi): ?><li><?= e(__('Ist dieses Konto nicht versandbereit (kein gültiger Absender oder Server, „Deaktiviert“), springt das Netzwerk-Konto ein.')) ?></li><?php endif; ?>
        <li><?= e(__('Antworten gehen an die Adresse, die der Aufruf mitgibt (z. B. das E-Mail-Feld einer Anfrage), sonst an „Antworten an“ des Kontos, sonst an den Absender.')) ?></li>
        <li><?= e(__('Zuletzt: Test-Umleitung dieser Website (nicht für Mails zur Kontosicherheit); in einer Testumgebung wird nur umgeleitet oder protokolliert.')) ?></li>
      </ol>
    </section>
  </div>
  </div>
</div>
