<footer class="site-footer">
  <div class="site-footer__inner">
    <div class="site-footer__brand">
      <span class="wordmark wordmark--footer">
        <span class="wordmark__1"><?= e(setting('wortmarke_1')) ?><span class="dot">.</span></span>
        <span class="wordmark__2"><?= e(setting('wortmarke_2')) ?></span>
      </span>
      <span><?= e(praxis_address_line() ?: lt('[Straße und Hausnummer]') . ' · ' . lt('[PLZ {city}]', ['city' => 'Moers'])) ?></span>
    </div>
    <nav aria-label="<?= e(lt('Rechtliches')) ?>">
      <ul>
        <?php foreach (array_merge(praxis_legal_links(), footer_links()) as $l): /* footer_links(): z. B. „Cookie-Einstellungen“ (consent_kit) */ ?>
        <li><a href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>
</footer>
