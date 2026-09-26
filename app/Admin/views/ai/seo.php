<?php
/**
 * SEO-Übersicht (Core\AI\SeoCheck): Seiten und Einträge mit fehlenden oder unpassenden Angaben für Suchmaschinen.
 * Mit KI: „Vorschläge erzeugen“ für die ausgewählten Zeilen, prüfen/bearbeiten, dann „Ausgewählte übernehmen“ (resources/js/_ai.js).
 * @var array $data  @var bool $ai
 */
use Core\Data\Entries;
use Core\Lang;
use Core\Pages;

$pages = $data['pages'];
$entries = $data['entries'];
$problems = array_filter($pages, fn($r) => $r['issues']);
$badge = fn(array $c) => '<li class="kia-chk kia-chk--' . e($c['level']) . '">' . e($c['text']) . '</li>';
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/ai')) ?>"><?= e(\Core\AI\Assist::brand()) ?></a></p>
    <h1><?= e(__('SEO-Übersicht')) ?></h1>
    <p class="adm-muted"><?= e(__('Seiten und Einträge, bei denen Angaben für Suchmaschinen fehlen oder unpassend sind. Überschriften und Bilder prüft der SEO-Check in den Seiteneinstellungen.')) ?></p></div>
</header>

<?php if ($ai): ?>
<p class="adm-flash adm-flash--info kia-note"><?= e(__('KI-Vorschläge sind Entwürfe: Bitte jeden Vorschlag lesen und bei Bedarf ändern. Übernommen werden nur angehakte Zeilen – Seiteninhalte bleiben unverändert.')) ?></p>
<?php endif; ?>

<section class="adm-card kia-seo" data-kia-seo-overview aria-labelledby="kia-sp-h">
  <div class="kia-seo__head">
    <h2 id="kia-sp-h"><?= e(__('Seiten')) ?> <span class="adm-badge adm-badge--muted"><?= count($problems) ?> / <?= count($pages) ?></span></h2>
    <?php if ($ai): ?><div class="kia-actions">
      <button type="button" class="adm-btn adm-btn--small kia-btn" data-kia-seo-generate><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('Vorschläge für Auswahl erzeugen')) ?></button>
      <button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-kia-seo-apply disabled><?= e(__('Ausgewählte übernehmen')) ?></button>
    </div><?php endif; ?>
  </div>
  <?php if (!$pages): ?><p class="adm-muted"><?= e(__('Keine Seiten.')) ?></p><?php else: ?>
  <div class="kia-tablewrap">
  <table class="adm-table kia-seo__table">
    <thead><tr>
      <?php if ($ai): ?><th scope="col" class="kia-seo__sel"><input type="checkbox" data-kia-all aria-label="<?= e(__('Alle mit Problemen auswählen')) ?>"></th><?php endif; ?>
      <th scope="col"><?= e(__('Seite')) ?></th><th scope="col"><?= e(__('Beschreibung für Suchmaschinen')) ?></th><th scope="col"><?= e(__('Hinweise')) ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($pages as $row): $p = $row['page']; ?>
      <tr data-kia-row data-kind="page" data-id="<?= (int) $p['id'] ?>" data-has-issues="<?= $row['issues'] ? '1' : '0' ?>">
        <?php if ($ai): ?><td class="kia-seo__sel"><input type="checkbox" data-kia-sel aria-label="<?= e(__('„{title}“ auswählen', ['title' => $p['title']])) ?>"<?= $row['missing'] ? ' checked' : '' ?>></td><?php endif; ?>
        <td><a href="<?= e(url('/admin/pages/' . $p['id'])) ?>"><strong><?= e($p['title']) ?></strong></a>
          <br><small class="adm-muted"><?= e(Pages::url($p)) ?><?= Lang::multi() ? ' · ' . e(strtoupper(Lang::norm($p['lang']))) : '' ?><?= $p['status'] !== 'published' ? ' · ' . e(__('Entwurf')) : '' ?></small></td>
        <td class="kia-seo__desc"><span data-kia-current><?= $p['meta_description'] !== '' && $p['meta_description'] !== null ? e($p['meta_description']) : '<span class="adm-muted">' . e(__('– fehlt –')) . '</span>' ?></span>
          <div data-kia-suggest hidden></div></td>
        <td><?php if ($row['issues']): ?><ul class="kia-checks"><?= implode('', array_map($badge, $row['issues'])) ?></ul><?php else: ?><span class="kia-chk kia-chk--ok"><?= e(__('in Ordnung')) ?></span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>

<section class="adm-card kia-seo" data-kia-seo-overview aria-labelledby="kia-se-h">
  <div class="kia-seo__head">
    <h2 id="kia-se-h"><?= e(__('Einträge mit Detailseite')) ?> <span class="adm-badge adm-badge--muted"><?= count($entries) ?></span></h2>
    <?php if ($ai && $entries): ?><div class="kia-actions">
      <button type="button" class="adm-btn adm-btn--small kia-btn" data-kia-seo-generate><span class="kia-spark" aria-hidden="true">✦</span> <?= e(__('Vorschläge für Auswahl erzeugen')) ?></button>
      <button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-kia-seo-apply disabled><?= e(__('Ausgewählte übernehmen')) ?></button>
    </div><?php endif; ?>
  </div>
  <p class="adm-muted"><?= e(__('Die Beschreibung für Suchmaschinen kommt aus dem Beschreibungsfeld der Tabelle. Aufgeführt sind Einträge, bei denen es leer oder länger als 160 Zeichen ist.')) ?></p>
  <?php if ($entries): ?>
  <div class="kia-tablewrap">
  <table class="adm-table kia-seo__table">
    <thead><tr>
      <?php if ($ai): ?><th scope="col" class="kia-seo__sel"><input type="checkbox" data-kia-all aria-label="<?= e(__('Alle auswählen')) ?>"></th><?php endif; ?>
      <th scope="col"><?= e(__('Eintrag')) ?></th><th scope="col"><?= e(__('Beschreibung')) ?></th><th scope="col"><?= e(__('Hinweise')) ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($entries as $row): $t = $row['table']; $en = $row['entry']; $v = (string) ($en[$row['field']['name']] ?? ''); ?>
      <tr data-kia-row data-kind="entry" data-table="<?= e($t['handle']) ?>" data-id="<?= (int) $en['id'] ?>" data-has-issues="1">
        <?php if ($ai): ?><td class="kia-seo__sel"><input type="checkbox" data-kia-sel aria-label="<?= e(__('„{title}“ auswählen', ['title' => Entries::title($t, $en)])) ?>"<?= $v === '' ? ' checked' : '' ?>></td><?php endif; ?>
        <td><a href="<?= e(url('/admin/data/' . $t['handle'] . '/' . $en['id'])) ?>"><strong><?= e(Entries::title($t, $en)) ?></strong></a>
          <br><small class="adm-muted"><?= e($t['name']) ?> · <?= e($row['field']['label']) ?><?= Lang::multi() ? ' · ' . e(strtoupper(Lang::norm($en['lang'] ?? null))) : '' ?></small></td>
        <td class="kia-seo__desc"><span data-kia-current><?= $v !== '' ? e(mb_strimwidth(strip_tags($v), 0, 220, '…')) : '<span class="adm-muted">' . e(__('– fehlt –')) . '</span>' ?></span>
          <div data-kia-suggest hidden></div></td>
        <td><ul class="kia-checks"><li class="kia-chk kia-chk--<?= $v === '' ? 'error' : 'warn' ?>"><?= e($v === '' ? __('Keine Beschreibung.') : __('Zu lang ({n} Zeichen).', ['n' => $row['length']])) ?></li></ul></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?><p class="kia-chk kia-chk--ok"><?= e(__('Alle Einträge haben eine passende Beschreibung.')) ?></p><?php endif; ?>
</section>
