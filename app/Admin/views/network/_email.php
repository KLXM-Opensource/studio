<?php /** Netzwerk → E-Mail-Konto des Netzwerks (Teil von network/index.php, Variablen von dort) */ ?>
  <?php $nm = \Core\Mail\Accounts::network(); $nmErr = $mailErrors ?? []; $nmDraft = $mailDraft ?? null; // E-Mail-Konto des Netzwerks (Core\Mail\Accounts) ?>
  <section class="adm-card" id="email" aria-labelledby="net-mail-h">
    <h2 id="net-mail-h" class="adm-sr"><?= e(__('E-Mail-Konto des Netzwerks')) ?></h2>
    <p class="mo-tag mo-tag--net"><?= e(__('gilt für: alle Websites (Netzwerk)')) ?></p>
    <p class="adm-muted"><?= e(__('Für die ganze Installation: Mails an Netzwerk-Konten (Einladungen, Passwort) und die Mails von Websites ohne eigenes Konto. Mit neutralem Absender verrät keine Mail einem Kunden, welche anderen Kunden die Installation nutzen. Websites wählen ihre eigenen Konten in den Grundeinstellungen.')) ?></p>
    <?php if ($nm): ?>
    <p class="net-mail__state"><span class="adm-badge adm-badge--<?= \Core\Mail\Accounts::ready($nm) ? 'ok' : 'warn' ?>"><?= e(\Core\Mail\Accounts::ready($nm) ? __('bereit') : __('nicht eingerichtet')) ?></span> <?= e(\Core\Mail\Accounts::describe($nm)) ?></p>
    <form method="post" action="<?= e(url('/admin/network/mail/test')) ?>" class="mk-test">
      <?= csrf_field() ?><label class="adm-sr" for="net-mail-to"><?= e(__('Empfänger der Testnachricht')) ?></label>
      <input type="email" id="net-mail-to" name="to" placeholder="<?= e((string) (app()->auth->user()['email'] ?? '')) ?>">
      <button class="adm-btn adm-btn--small"><?= e(__('Testnachricht')) ?></button>
      <?php if (environment() !== 'production'): // Testumgebung: Mails gehen sonst nicht hinaus (Mailer) – für den Test bewusst umgehen ?>
      <label class="mk-test__real" title="<?= e(__('Diese Website ist eine Testumgebung ({env}) und verschickt sonst keine E-Mails. Mit dieser Option geht nur die Testnachricht wirklich an die eingegebene Adresse – so lassen sich die Zugangsdaten prüfen.', ['env' => environment()])) ?>"><input type="checkbox" name="real" value="1" checked> <?= e(__('Testumgebung umgehen – wirklich senden')) ?></label>
      <?php endif; ?>
    </form>
    <?php else: ?>
    <p class="adm-flash adm-flash--info" role="note"><?= e(__('Noch kein E-Mail-Konto des Netzwerks: Mails an Netzwerk-Konten gehen bis dahin über das Konto der jeweiligen Website.')) ?></p>
    <?php endif; ?>
    <details class="net-add"<?= $nmErr || !$nm ? ' open' : '' ?>>
      <summary class="adm-btn adm-btn--small"><?= e($nm ? __('Konto bearbeiten') : __('Konto einrichten')) ?></summary>
      <form method="post" action="<?= e(url('/admin/network/mail')) ?>" class="adm-fields" novalidate>
        <?= csrf_field() ?>
        <?php $nv = $nmDraft ?? ($nm ?? ['transport' => 'smtp', 'verify_peer' => true, 'encryption' => 'tls', 'from_name' => 'KLXM Studio']); $nv['port'] = !empty($nv['port']) ? $nv['port'] : ''; ?>
        <?= \Core\Fields::renderForm(\Core\Http\Controllers\Admin\MailAccountsController::fields(true), $nv, $nmErr, 'n') ?>
        <div class="adm-form-actions">
          <button class="adm-btn adm-btn--primary"><?= e(__('Speichern')) ?></button>
          <?php if ($nm): ?><button class="adm-btn adm-btn--ghost adm-btn--danger-text" name="remove" value="1" formnovalidate data-confirm="<?= e(__('Netzwerk-Konto entfernen? Netzwerk-Mails gehen dann über das Konto der jeweiligen Website.')) ?>"><?= e(__('Entfernen')) ?></button><?php endif; ?>
        </div>
      </form>
    </details>
  </section>
  <?php // Welche Adresse gilt wofür – alle Websites (Core\Mail\Overview::sites, dieselbe Rangfolge wie beim Versand) ?>
  <?php $moSites = \Core\Mail\Overview::sites(); $moWho = fn(array $a) => $a['from'] !== '' ? trim(($a['from_name'] !== '' ? $a['from_name'] . ' ' : '') . '<' . $a['from'] . '>') : __('kein Absender');
  $moOrigin = fn(array $p) => match (true) { $p['origin'] === 'fallback' => [__('Rückfall: Netzwerk-Konto'), 'net'], ($p['acc']['scope'] ?? 'site') === 'network' => [__('Netzwerk-Konto'), 'net'],
      $p['origin'] === 'network-missing' => [__('Konto der Website (kein Netzwerk-Konto)'), 'site'], default => [__('Konto „{name}“ der Website', ['name' => $p['acc']['label']]), 'site'] };
  $moLvl = fn(string $l) => match ($l) { 'ok' => 'ok', 'error' => 'warn', default => 'muted' };
  $moPurp = \Core\Mail\Purposes::all(); ?>
  <section class="adm-card" id="email-uebersicht" aria-labelledby="net-mo-h">
    <h2 id="net-mo-h"><?= e(__('Welche Adresse gilt wofür – alle Websites')) ?></h2>
    <p class="adm-muted"><?= e(__('Wirksamer Absender je Website und Zweck, wie er beim Versand bestimmt wird. Konten, Verwendung, Empfänger, Test-Umleitung und Impressum stellt jede Website selbst ein (nur diese Website); das Netzwerk-Konto oben gilt für alle.')) ?></p>
    <div class="mo-wrap">
    <table class="adm-table mo-table mo-net">
      <thead><tr><th scope="col"><?= e(__('Website')) ?></th>
        <?php foreach (\Core\Mail\Overview::NET_PURPOSES as $p): ?><th scope="col"><?= e((string) ($moPurp[$p]['label'] ?? $p)) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php foreach ($moSites as $ms): ?>
        <tr>
          <th scope="row" data-label="<?= e(__('Website')) ?>"><strong><?= e($ms['label']) ?></strong><span class="mo-sub"><code><?= e($ms['key']) ?></code><?= $ms['env'] !== 'production' ? ' · ' . e($ms['env']) : '' ?></span>
            <?php if ($ms['network_site']): ?><span class="mo-tag mo-tag--net"><?= e(__('Netzwerk-Website')) ?></span><?php endif; ?>
            <?php if ($ms['error'] === ''): ?>
            <span class="mo-sub"><?= e(__('Empfänger für Benachrichtigungen')) ?>: <?= e($ms['mail_to'] !== '' ? $ms['mail_to'] : __('– niemand eingetragen')) ?></span>
            <?php if ($ms['netmail']['origin'] !== 'network'): ?><span class="mo-sub"><?= e(__('Mails an Netzwerk-Konten')) ?>: <?= e($moWho($ms['netmail']['acc'])) ?></span><?php endif; ?>
            <span class="adm-badge adm-badge--<?= $ms['imprint'] ? 'ok' : 'warn' ?>"><?= e($ms['imprint'] ? __('Impressum für E-Mails vorhanden') : __('Impressum für E-Mails fehlt')) ?></span>
            <span class="adm-badge adm-badge--<?= $moLvl((string) $ms['state']['level']) ?>"><?= e((string) $ms['state']['text']) ?></span>
            <form method="post" action="<?= e(url('/admin/network/open')) ?>" class="mo-open"><?= csrf_field() ?><input type="hidden" name="site" value="<?= e($ms['key']) ?>"><input type="hidden" name="path" value="/admin/system/mail/uebersicht">
              <button class="adm-btn adm-btn--small adm-btn--ghost"><?= e(__('Übersicht der Website')) ?></button></form>
            <?php endif; ?></th>
          <?php if ($ms['error'] !== ''): ?><td colspan="<?= count(\Core\Mail\Overview::NET_PURPOSES) ?>"><?= e($ms['error']) ?></td>
          <?php else: ?>
          <?php foreach (\Core\Mail\Overview::NET_PURPOSES as $p): $pp = $ms['purposes'][$p]; [$ot, $oc] = $moOrigin($pp); ?>
          <td data-label="<?= e((string) ($moPurp[$p]['label'] ?? $p)) ?>"><span class="mo-addr"><?= e($moWho($pp['acc'])) ?></span><span class="mo-tag mo-tag--<?= e($oc) ?>"><?= e($ot) ?></span><?php if (!$pp['ready']): ?> <span class="adm-badge adm-badge--warn"><?= e(__('nicht eingerichtet')) ?></span><?php endif; ?></td>
          <?php endforeach; ?>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </section>
