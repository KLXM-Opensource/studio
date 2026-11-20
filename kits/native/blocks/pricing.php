<?php
/**
 * Pakete / Vergleich im Kit „NativeCSS“:
 *   cards  Preistabelle von NativeCSS (.ncss-pricing): gleich hohe Karten, Zeilen per Subgrid bündig, „Hervorheben“ mit Etikett
 *          als echtem Text; Leistungen mit Haken – eine Zeile mit „–“ am Anfang gilt als nicht enthalten (Kreuz + Text für
 *          Screenreader). Kein Monatlich/Jährlich-Umschalter: der Block kennt einen Preis je Paket.
 *   list   Preisliste mit Führungspunkten (.ncss-leaders) – Einträge im Feld entries (name, price, text), sobald der Block die
 *          Variante hat
 *   table  Vergleichstabelle von fluid
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() ?: 'cards';
if (!in_array($v, ['cards', 'list'], true)) {
    require native_parent_view('pricing');
    return;
}
$tag = fluid_htag($d);
if ($v === 'list'):
    native_mark('pricing:@leaders');
    $entries = array_values(array_filter((array) ($d['entries'] ?? []), fn($i) => trim((string) ($i['name'] ?? '')) !== ''));
?>
<div class="wrap nv-pricelist">
  <?= fluid_head($b, 'sec-head--center') ?>
  <?php if ($entries): ?>
  <dl class="ncss-leaders">
    <?php foreach ($entries as $i => $it): ?>
    <div class="ncss-leaders-item">
      <dt<?= $b->edit("entries.$i.name") ?>><?= fluid_title((string) $it['name']) ?></dt>
      <dd<?= $b->edit("entries.$i.price") ?>><?= e((string) ($it['price'] ?? '')) ?></dd>
      <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><dd class="ncss-leaders-desc"<?= $b->edit("entries.$i.text") ?>><?= e((string) $it['text']) ?></dd><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </dl>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Einträge – in der Seitenleiste unter „Preisliste“ hinzufügen.</p><?php endif; ?>
  <?php if (trim((string) ($d['note'] ?? '')) !== ''): ?><p class="plans__note"<?= $b->edit('note') ?>><?= fluid_title((string) $d['note']) ?></p><?php endif; ?>
</div>
<?php return; endif;

native_mark('pricing:@plans');
$items = array_values(array_filter((array) ($d['items'] ?? []), fn($i) => trim((string) ($i['name'] ?? '')) !== ''));
?>
<div class="wrap nv-plans">
  <?= fluid_head($b, 'sec-head--center') ?>
  <?php if ($items): ?>
  <div class="ncss-pricing">
    <ul class="ncss-pricing-plans" role="list">
      <?php foreach ($items as $i => $it):
          $hl = !empty($it['highlight']);
          $badge = trim((string) ($it['badge'] ?? ''));
          $label = trim((string) ($it['button_label'] ?? ''));
          $link = trim((string) ($it['button_link'] ?? ''));
      ?>
      <li class="ncss-pricing-plan<?= $hl ? ' ncss-pricing-plan--featured' : '' ?>">
        <div class="ncss-pricing-header">
          <?php if ($badge !== ''): ?><p class="ncss-pricing-badge"<?= $b->edit("items.$i.badge") ?>><?= fluid_title($badge) ?></p><?php endif; ?>
          <<?= $tag ?> class="ncss-pricing-name"<?= $b->edit("items.$i.name") ?>><?= fluid_title((string) $it['name']) ?></<?= $tag ?>>
          <?php if (trim((string) ($it['text'] ?? '')) !== ''): ?><p class="ncss-pricing-desc"<?= $b->edit("items.$i.text") ?>><?= nl2br(fluid_title((string) $it['text']), false) ?></p><?php endif; ?>
        </div>
        <p class="ncss-pricing-price"><?php if (trim((string) ($it['price'] ?? '')) !== ''): ?><span class="ncss-pricing-value"<?= $b->edit("items.$i.price") ?>><?= e((string) $it['price']) ?></span>
          <?php if (trim((string) ($it['period'] ?? '')) !== ''): ?><span class="ncss-pricing-period"<?= $b->edit("items.$i.period") ?>><?= e((string) $it['period']) ?></span><?php endif; endif; ?></p>
        <ul class="ncss-pricing-features" role="list">
          <?php foreach (fluid_lines($it['features'] ?? '') as $line): $out = (bool) preg_match('~^[–—-]\s*~u', $line); $line = (string) preg_replace('~^[–—-]\s*~u', '', $line); ?>
          <li<?= $out ? ' class="ncss-pricing-excluded"' : '' ?>><span class="sr-only"><?= e($out ? lt('Nicht enthalten:') : lt('Enthalten:')) ?></span> <?= fluid_title($line) ?></li>
          <?php endforeach; ?>
        </ul>
        <div class="ncss-pricing-action"><?php if ($label !== '' && $link !== ''): ?><a class="btn <?= $hl ? 'btn--primary' : 'btn--secondary' ?>" <?= fluid_link_attrs($link) ?>><span<?= $b->edit("items.$i.button_label") ?>><?= e($label) ?></span><span class="sr-only">: <?= e(strip_emphasis((string) $it['name'])) ?></span></a><?php endif; ?></div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Pakete – in der Seitenleiste hinzufügen.</p><?php endif; ?>
  <?php if (trim((string) ($d['note'] ?? '')) !== ''): ?><p class="plans__note"<?= $b->edit('note') ?>><?= fluid_title((string) $d['note']) ?></p><?php endif; ?>
</div>
