<?php
/**
 * Kern-Fragment „legal“ (überschreibbar: kits/{kit}/fragments/legal.php): Rechtliches-Zeile im Fußbereich –
 * Impressum, Datenschutz, Barrierefreiheit (legal_links(), Core\Legal) und Links der Erweiterungen (footer_links(),
 * z. B. „Datenschutz-Einstellungen“ von consent_kit). Klassen: .legal (+ $class) · aktuelle Seite aria-current="page".
 * @var ?string $class  @var ?string $label  Beschriftung der Navigation (Standard „Rechtliches“)
 */
$links = array_merge(legal_links(), footer_links());
if (!$links) return;
$here = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
?>
<nav class="legal<?= !empty($class) ? ' ' . e($class) : '' ?>" aria-label="<?= e(($label ?? '') !== '' ? $label : lt('Rechtliches')) ?>">
  <ul role="list">
    <?php foreach ($links as $l): ?><li><a href="<?= e($l['href']) ?>"<?= ($l['href'] ?? '') === $here ? ' aria-current="page"' : '' ?><?= str_starts_with((string) $l['href'], '#cookie') ? ' data-consent-open' : '' ?>><?= e($l['label']) ?></a></li><?php endforeach; ?>
  </ul>
</nav>
