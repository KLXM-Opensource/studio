<?php
/**
 * Verwaltung → KLXM Check: Zustand, Grenzen, anonyme Zähler, Schalter für die eigenständige Seite.
 * @var array $health  @var array $limits  @var int $ttl  @var bool $smtp  @var bool $on  @var bool $page  @var bool $pageSetting
 * @var ?bool $pageLocked  @var string $path  @var array $stats  @var array $days  @var bool $resolver  @var bool $curl  @var bool $intl
 */
$sections = ['spf' => 'SPF', 'dmarc' => 'DMARC', 'dkim' => 'DKIM', 'mail' => __('Mailserver'), 'hosting' => __('Hosting & DNS'), 'web' => __('Website'),
    'tls' => 'SSL/TLS', 'spf-analyse' => __('SPF-Analyse'), 'dmarc-analyse' => __('DMARC-Analyse')];
$state = fn(?bool $ok) => $ok === null ? __('Hinweis') : ($ok ? __('in Ordnung') : __('Problem'));
?>
<header class="adm-head"><div>
  <p class="adm-eyebrow"><?= e(__('Erweiterung')) ?></p>
  <h1><?= e(__('KLXM Check')) ?></h1>
  <p class="adm-muted"><?= e(__('Domain-, Mail- und TLS-Prüfung für Besucher: SPF, DMARC, DKIM, Mailserver, Hosting, Website-Sicherheit und SSL/TLS, dazu SPF- und DMARC-Generator.')) ?>
    <a href="<?= e(url('/admin/hilfe#klxm-check')) ?>"><?= e(__('Handbuch')) ?></a> · <a href="<?= e(url('/admin/hilfe/technik#klxm-check')) ?>"><?= e(__('Technik')) ?></a></p>
</div></header>

<?php if (!$on): ?>
<p class="adm-card" role="status"><?= e(__('Die Funktion „KLXM Check“ ist auf dieser Website abgeschaltet (Administration → Funktionen & Erweiterungen) – Block und Schnittstelle antworten nicht.')) ?></p>
<?php endif; ?>

<section class="adm-card" aria-labelledby="kc-a1">
  <h2 id="kc-a1"><?= e(__('Auf der Website einsetzen')) ?></h2>
  <ol>
    <li><?= e(__('Seite bearbeiten → Block hinzufügen → Gruppe „Werkzeuge“ → „KLXM Check“. Der Block funktioniert in jedem Kit; Überschrift, Start-Reiter und angebotene Werkzeuge sind einstellbar.')) ?></li>
    <li><?= e(__('Alternativ ohne Block: die eigenständige Seite unten einschalten.')) ?></li>
    <li><?= e(__('Links wie beim alten Werkzeug funktionieren: ?domain=beispiel.de startet die Analyse, ?tab=ssl-checker|spf-generator|dmarc-generator öffnet den Reiter.')) ?></li>
  </ol>
</section>

<form class="adm-card" method="post" action="<?= e(url('/admin/klxm-check')) ?>" aria-labelledby="kc-a2">
  <?= csrf_field() ?>
  <h2 id="kc-a2"><?= e(__('Eigenständige Seite')) ?></h2>
  <p class="adm-muted"><?= e(__('Zeigt das Werkzeug unter {path} im Layout des Kits – ohne dass eine Seite angelegt werden muss. Eine Seite des CMS mit derselben Adresse hat immer Vorrang.', ['path' => $path])) ?></p>
  <div class="f"><label><input type="checkbox" name="page" value="1"<?= $page ? ' checked' : '' ?><?= $pageLocked !== null ? ' disabled' : '' ?>> <?= e(__('Seite {path} anzeigen', ['path' => $path])) ?></label></div>
  <?php if ($pageLocked !== null): ?>
  <p class="adm-muted"><?= e(__('Per Konfiguration festgelegt (\'klxm_check\' => [\'page\' => …]) – hier nicht änderbar.')) ?></p>
  <?php else: ?>
  <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Speichern')) ?></button>
  <?php endif; ?>
  <?php if ($page): ?><p><a href="<?= e(url($path)) ?>" target="_blank" rel="noopener"><?= e(__('Seite öffnen')) ?></a></p><?php endif; ?>
</form>

<section class="adm-card" aria-labelledby="kc-a3">
  <h2 id="kc-a3"><?= e(__('Zustand')) ?></h2>
  <table class="adm-table">
    <thead><tr><th scope="col"><?= e(__('Prüfung')) ?></th><th scope="col"><?= e(__('Ergebnis')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($health as $label => $ok): ?>
      <tr><td><?= e($label) ?></td><td><?= e($state($ok)) ?></td></tr>
      <?php endforeach; ?>
      <tr><td><?= e(__('Eigener DNS-Client mit Zeitlimit (Resolver aus /etc/resolv.conf)')) ?></td><td><?= e($resolver ? __('aktiv') : __('nicht verfügbar – Rückfall auf dns_get_record (ohne Zeitlimit)')) ?></td></tr>
      <tr><td><?= e(__('Internationale Domains (intl)')) ?></td><td><?= e($intl ? __('intl vorhanden') : __('eingebauter Punycode-Umwandler')) ?></td></tr>
    </tbody>
  </table>
  <p class="adm-muted"><?= e(__('Manche Hoster sperren ausgehende Verbindungen auf Port 25 – dann meldet die Mailserver-Prüfung das als Hinweis. Das Ergebnis der Zustandsprüfung wird 10 Minuten zwischengespeichert.')) ?></p>
</section>

<section class="adm-card" aria-labelledby="kc-a4">
  <h2 id="kc-a4"><?= e(__('Schutz und Grenzen')) ?></h2>
  <dl class="adm-dl">
    <dt><?= e(__('Je Besucher')) ?></dt><dd><?= e(__('{m} Anfragen pro Minute, {d} pro Tag (eine Domain-Analyse = 6 Anfragen)', ['m' => $limits['minute'], 'd' => $limits['day']])) ?></dd>
    <dt><?= e(__('Je Website')) ?></dt><dd><?= e(__('{n} Anfragen pro Tag', ['n' => $limits['site_day']])) ?></dd>
    <dt><?= e(__('Zwischenspeicher je Domain')) ?></dt><dd><?= e($ttl ? __('{n} Sekunden', ['n' => $ttl]) : __('aus')) ?></dd>
    <dt><?= e(__('STARTTLS auf Port 25')) ?></dt><dd><?= e($smtp ? __('wird versucht') : __('abgeschaltet')) ?></dd>
    <dt><?= e(__('Ziele')) ?></dt><dd><?= e(__('nur öffentliche IP-Adressen; Ports 80, 443, 25, 465, 587, 993, 995; Zeitlimit 5 s Verbindung, 10 s gesamt')) ?></dd>
  </dl>
  <p class="adm-muted"><?= e(__('Ändern per Konfiguration: \'klxm_check\' => [\'limits\' => [\'minute\' => 40, \'day\' => 400, \'site_day\' => 5000], \'cache_ttl\' => 300, \'smtp\' => true].')) ?></p>
</section>

<section class="adm-card" aria-labelledby="kc-a5">
  <h2 id="kc-a5"><?= e(__('Nutzung (30 Tage, anonym)')) ?></h2>
  <?php if (!$stats): ?>
  <p class="adm-muted"><?= e(__('Noch keine Prüfungen.')) ?></p>
  <?php else: ?>
  <table class="adm-table">
    <thead><tr><th scope="col"><?= e(__('Prüfung')) ?></th><th scope="col"><?= e(__('Anzahl')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($stats as $k => $n): ?><tr><td><?= e($sections[$k] ?? $k) ?></td><td><?= (int) $n ?></td></tr><?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <p class="adm-muted"><?= e(__('Gezählt wird nur die Art der Prüfung je Tag – keine Domains, keine IP-Adressen.')) ?></p>
</section>
