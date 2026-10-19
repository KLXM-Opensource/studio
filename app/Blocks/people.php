<?php
/**
 * Personen (Kern-Block, vom Kit überschreibbar: kits/{name}/blocks/people.php). Ausgewählte Einträge einer zentral gepflegten
 * Personen-Tabelle (Feld „Person“ = Verweis entry:{tabelle}:{id} aus dem Struktur-Browser). Name, Foto, E-Mail, Telefon und
 * Funktion kommen aus dem Eintrag; Funktion, Text und Foto lassen sich je Stelle überschreiben. Visitenkarte (vCard mit Foto)
 * über vcard_entry_url(), wenn die Tabelle den Schema-Typ „Person“ hat. Aussehen: resources/css/people.css (--kit-*).
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;
use Core\Data\Tables;
use Core\StructuredData;

$wrap = app()->theme->def['container_class'] ?? 'wrap';
$t = Tables::findContent((string) ($d['table'] ?? ''));
$layout = in_array($d['layout'] ?? '', ['cards', 'list', 'feature'], true) ? $d['layout'] : 'cards';
$cols = in_array((string) ($d['columns'] ?? ''), ['2', '3', '4'], true) ? (string) $d['columns'] : '3';
$people = [];
if ($t) {
    $img = Tables::imageField($t);
    foreach ((array) ($d['items'] ?? []) as $i => $it) {
        if (!preg_match('~^entry:([a-z][a-z0-9_]*):(\d+)$~', (string) ($it['person'] ?? ''), $m) || $m[1] !== $t['handle']) continue;
        $e = Entries::find($t, (int) $m[2]);
        if (!$e || (($e['status'] ?? 'published') !== 'published' && !is_editing())) continue;
        $role = trim((string) ($it['role'] ?? '')) ?: (string) (StructuredData::field($t, $e, ['text'], ['funktion', 'position', 'rolle', 'job', 'beruf', 'title']) ?? '');
        $people[] = ['i' => $i, 'name' => Entries::title($t, $e), 'role' => $role, 'note' => trim((string) ($it['note'] ?? '')),
            'image' => (int) ($it['image'] ?? 0) ?: ($img !== '' ? (int) ($e[$img] ?? 0) : 0),
            'email' => (string) (StructuredData::field($t, $e, ['email']) ?? ''), 'tel' => (string) (StructuredData::field($t, $e, ['tel']) ?? ''),
            'href' => !empty($d['link_detail']) ? Entries::href($t, $e) : null, 'vcard' => !empty($d['vcard']) ? vcard_entry_url($t, $e) : null];
    }
}
$initials = function (string $name): string {
    $w = array_values(array_filter(preg_split('~\s+~u', preg_replace('~^(Dr\.|Prof\.)\s*~u', '', $name))));
    return mb_strtoupper(mb_substr($w[0] ?? '', 0, 1) . (count($w) > 1 ? mb_substr($w[count($w) - 1], 0, 1) : ''));
};
$sizes = $layout === 'feature' ? '(min-width: 1080px) 420px, 80vw' : ($layout === 'list' ? '120px' : '(min-width: 1080px) 360px, (min-width: 640px) 45vw, 90vw');
$ratio = $layout === 'list' ? '1:1' : '3:4';
?>
<div class="<?= e($wrap) ?> cms-people cms-people--<?= e($layout) ?> cms-people--c<?= e($cols) ?>">
  <?php if (!empty($d['eyebrow']) || !empty($d['title']) || !empty($d['intro'])): ?>
  <header class="cms-people__head">
    <?php if (!empty($d['eyebrow'])): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= emphasis((string) $d['eyebrow']) ?></p><?php endif; ?>
    <?php if (!empty($d['title'])): ?><h2 class="h2" id="<?= e($b->titleId()) ?>"<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></h2><?php endif; ?>
    <?php if (!empty($d['intro'])): ?><p class="cms-people__intro"<?= $b->edit('intro') ?>><?= emphasis((string) $d['intro']) ?></p><?php endif; ?>
  </header>
  <?php endif; ?>
  <?php if ($people): ?>
  <ul class="cms-people__list" role="list">
    <?php foreach ($people as $p): $tag = !empty($d['title']) ? 'h3' : 'h2'; ?>
    <li class="cms-person">
      <div class="cms-person__img<?= $p['image'] ? '' : ' cms-person__img--none' ?>">
        <?php if ($p['image'] && ($pic = img($p['image'], $sizes, ['ratio' => $ratio, 'alt' => $p['name']]))): ?><?= $pic ?>
        <?php else: ?><span class="cms-person__initials" aria-hidden="true"><?= e($initials($p['name'])) ?></span><?php endif; ?>
      </div>
      <div class="cms-person__body">
        <<?= $tag ?> class="cms-person__name"><?= $p['href'] ? '<a href="' . e($p['href']) . '">' . e($p['name']) . '</a>' : e($p['name']) ?></<?= $tag ?>>
        <?php if ($p['role'] !== ''): ?><p class="cms-person__role"<?= $b->edit('items.' . $p['i'] . '.role') ?>><?= e($p['role']) ?></p><?php endif; ?>
        <?php if ($p['note'] !== ''): ?><p class="cms-person__note"<?= $b->edit('items.' . $p['i'] . '.note') ?>><?= e($p['note']) ?></p><?php endif; ?>
        <?php if ((!empty($d['contact']) && ($p['email'] !== '' || $p['tel'] !== '')) || $p['vcard']): ?>
        <ul class="cms-person__actions" role="list">
          <?php if (!empty($d['contact']) && $p['email'] !== ''): ?><li><a href="mailto:<?= e($p['email']) ?>"><?= icon('envelope-simple') ?><span><?= e($p['email']) ?></span></a></li><?php endif; ?>
          <?php if (!empty($d['contact']) && $p['tel'] !== '' && ($th = tel_href($p['tel']))): ?><li><a href="<?= e($th) ?>"><?= icon('phone') ?><span><?= e($p['tel']) ?></span></a></li><?php endif; ?>
          <?php if ($p['vcard']): ?><li><a href="<?= e($p['vcard']) ?>" download><?= icon('address-book') ?><span><?= e(lt('Kontakt speichern')) ?></span></a></li><?php endif; ?>
        </ul>
        <?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= e(__('Noch keine Personen – Tabelle wählen und Personen hinzufügen.')) ?></p><?php endif; ?>
</div>
