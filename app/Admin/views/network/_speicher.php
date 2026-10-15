<?php
/**
 * Netzwerk → Speicher: alle Websites nach Größe mit Balken, je Website aufklappbar aufgeschlüsselt (Core\StorageUsage),
 * dazu der Speicher der Installation (geteilte Pools, Protokolle, Sitzungen …). Stündlich neu gezählt, „Neu zählen“ sofort.
 * @var array $stats  @var array $usage  @var array $install
 */
use Core\StorageUsage;

$defs = StorageUsage::parts();
uasort($usage, fn($a, $b) => $b['total'] <=> $a['total']);
$sum = array_sum(array_column($usage, 'total')) + (int) ($install['total'] ?? 0);
$max = max([1, ...array_values(array_map(fn($u) => (int) $u['total'], $usage))]);
$name = fn(string $k) => (string) ($stats[$k]['label'] ?? $k);
?>
<?= \Core\AdminUi::group(__('Insgesamt'), [
    \Core\AdminUi::row(__('Belegt'), '<strong>' . e(fmt()->bytes($sum)) . '</strong>', __('{n} Websites und die gemeinsamen Daten der Installation – ohne Programmdateien.', ['n' => count($usage)])),
    \Core\AdminUi::row(__('Zählung'), '<a class="adm-btn adm-btn--small" href="' . e(url('/admin/network/speicher?neu=1')) . '">' . icon('arrows-clockwise') . ' ' . e(__('Neu zählen')) . '</a>', __('Wird stündlich neu gezählt.')),
]) ?>

<section class="set-group">
  <h3 class="set-group__title"><?= e(__('Websites')) ?></h3>
  <div class="set-list su-sites">
    <?php foreach ($usage as $k => $u): ?>
    <details class="su-site" id="su-<?= e((string) $k) ?>">
      <summary class="set-row">
        <span class="set-row__main"><span class="set-row__label"><?= e($name((string) $k)) ?></span>
          <span class="su-mini" aria-hidden="true"><?php foreach ($u['parts'] as $pk => $p): $w = $p['bytes'] / $max * 100; if ($w < 0.3) continue; ?><span class="su-seg su-t<?= (int) $defs[$pk][2] ?>" style="width:<?= number_format($w, 2, '.', '') ?>%"></span><?php endforeach; ?></span></span>
        <span class="set-row__ctl"><strong><?= e(fmt()->bytes((int) $u['total'])) ?></strong></span>
      </summary>
      <div class="su-site__body"><?= \Core\Theme::capture(ROOT . '/app/Admin/views/storage/_usage.php', ['u' => $u]) ?></div>
    </details>
    <?php endforeach; ?>
  </div>
  <p class="set-group__note"><?= e(__('Balken im Verhältnis zur größten Website. Geteilte Medien-Pools zählen zur Installation, nicht zu den Websites.')) ?></p>
</section>

<?php if (!empty($install['rows'])): ?>
<?= \Core\AdminUi::group(__('Installation (gemeinsam)'), array_map(fn($r) => \Core\AdminUi::row($r['label'], e(fmt()->bytes((int) $r['bytes']))), $install['rows']),
    __('Gehört keiner einzelnen Website: geteilte Medien, Protokolle, Sitzungen, Chat und Support, hochgeladene Kits, Sicherungen der Installation.')) ?>
<?php endif; ?>
