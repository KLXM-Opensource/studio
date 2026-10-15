<?php /** Netzwerk → Neue Website (Teil von network/index.php, Variablen von dort) */ ?>
  <section class="adm-card" id="neu" aria-labelledby="net-new-h">
    <h2 id="net-new-h" class="adm-sr"><?= e(__('Neue Website')) ?></h2>
    <?php if ($newSite): ?>
    <div class="adm-inline-box net-created">
      <strong><?= e(__('Nächste Schritte für „{site}“', ['site' => $newSite['key']])) ?></strong>
      <ol>
        <li><?= e(__('Domain im Hosting auf denselben Ordner zeigen lassen (Dokumentstamm = httpdocs/public).')) ?></li>
        <li><?= e(__('Erster Aufruf legt Datenbank und Medienordner an. Sind Kit und Startinhalte noch offen, fragt der Willkommen-Bildschirm nach dem ersten Anmelden – sonst werden die Startinhalte sofort eingespielt.')) ?></li>
        <li><?= e(__('Oder erstes lokales Konto über /admin/setup mit dem Setup-Token:')) ?> <code><?= e($newSite['token']) ?></code></li>
      </ol>
      <p class="adm-muted"><?= e(__('Konfiguration:')) ?> <code><?= e($newSite['file']) ?></code></p>
    </div>
    <?php endif; ?>
    <p class="adm-muted"><?= e(__('Legt eine neue Website in dieser Installation an – mit eigener Datenbank, eigenen Schlüsseln und eigenem Medienordner.')) ?></p>
    <form method="post" action="<?= e(url('/admin/network/sites')) ?>" class="net-newsite" novalidate>
      <?= csrf_field() ?>
      <div class="f"><label for="ns-key"><?= e(__('Kurzname')) ?> <span class="req">*</span></label><input id="ns-key" name="key" required pattern="[a-z][a-z0-9\-]{1,31}" maxlength="32" placeholder="kunde" autocomplete="off"></div>
      <div class="f"><label for="ns-hosts"><?= e(__('Domains (mit Komma getrennt)')) ?> <span class="req">*</span></label><input id="ns-hosts" name="hosts" required placeholder="www.kunde.de, kunde.de" autocomplete="off"></div>
      <div class="f"><label for="ns-theme"><?= e(__('Kit')) ?></label><select id="ns-theme" name="theme"><option value=""><?= e(__('Beim ersten Anmelden wählen')) ?></option><?php $kitInfo = \Core\Onboarding::kits($themes); foreach (\Core\Onboarding::CATEGORIES as $cat): $grp = array_filter($kitInfo, fn($i) => $i['category'] === $cat); if (!$grp) continue; uasort($grp, fn($a, $b) => $b['recommended'] <=> $a['recommended']); ?><optgroup label="<?= e(\Core\Onboarding::categoryLabel($cat)) ?>"><?php foreach ($grp as $tk => $ti): ?><option value="<?= e($tk) ?>"><?= e($ti['label'] . ($ti['recommended'] ? ' – ' . __('empfohlen') : '')) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select></div>
      <div class="f"><label for="ns-content"><?= e(__('Startinhalte')) ?></label><select id="ns-content" name="content"><option value="ask"><?= e(__('Beim ersten Anmelden entscheiden')) ?></option><option value="full"><?= e(__('Startinhalte des Kits einspielen')) ?></option><option value="empty"><?= e(__('Ohne Startinhalte (leere Startseite, Rechtstexte als Vorlage)')) ?></option></select></div>
      <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Website anlegen')) ?></button>
    </form>
    <details class="net-clihint"><summary class="adm-link"><?= e(__('Auf der Kommandozeile')) ?></summary>
      <pre class="net-cli"><code>php bin/console site:create &lt;key&gt; &lt;domain[,domain2]&gt; [kit] [--content=ask|full|empty]</code></pre></details>
  </section>
