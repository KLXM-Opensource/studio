<?php
/**
 * Eintrag aus einer externen Quelle (Core\Sources): nur lesen, ein-/ausblenden. Inhalte ändert nur der Abgleich.
 * @var array $t  @var array $e  @var array $origin  (ext_source_items + source_name, source_format)
 */
use Core\Data\Entries;

$base = '/admin/data/' . $t['handle'];
$url = Entries::url($t, $e);
$canSrc = \Core\Sources\Sources::canManage();
$published = $e['status'] === 'published';
?>
<header class="adm-head dt-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url($base)) ?>"><span aria-hidden="true"><?= icon($t['icon']) ?></span> <?= e($t['name']) ?></a></p>
    <h1><?= e(Entries::title($t, $e)) ?></h1></div>
  <?php if ($url && $published): ?><a class="adm-btn adm-btn--ghost" href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e(__('Ansehen')) ?> ↗</a><?php endif; ?>
</header>

<div class="dt-entry">
  <section class="adm-card dt-entry__main sh-readonly">
    <p class="adm-flash adm-flash--info sh-note src-note"><span class="src-badge src-badge--l"><?= icon('plugs-connected') ?> <?= e(__('aus Quelle „{name}“', ['name' => $origin['source_name']])) ?></span>
      <?= e(__('Dieser Eintrag wird bei jedem Abruf aus der Quelle aktualisiert und ist hier nur lesbar. Änderungen bitte in der Quelle vornehmen.')) ?></p>
    <dl class="sh-fields">
      <?php foreach ($t['fields'] as $f): $h = Entries::html($t, $e, $f['name'], ['link' => false, 'plain' => true, 'unmask' => false]); if ($h === '') continue; ?>
      <dt><?= e($f['label']) ?></dt><dd><?= $h ?></dd>
      <?php endforeach; ?>
    </dl>
  </section>
  <aside class="dt-entry__side">
    <section class="adm-card">
      <h2 class="sh-h"><?= e(__('Auf der Website')) ?></h2>
      <p><span class="dt-status dt-status--<?= $published ? 'published' : 'draft' ?>"><?= e($published ? __('Online') : __('Ausgeblendet (Entwurf)')) ?></span></p>
      <?php if ($origin['state'] === 'missing'): ?><p class="f-help"><?= e(__('Beim letzten Abruf fehlte dieser Eintrag in der Quelle.')) ?></p><?php endif; ?>
      <?php if (can('data.publish', $t['handle'])): ?>
      <form method="post" action="<?= e(url('/admin/quellen/eintrag/' . $t['handle'] . '/' . (int) $e['id'])) ?>">
        <?= csrf_field() ?>
        <?php if ($published): ?>
        <button class="adm-btn adm-btn--block" name="state" value="draft"><?= icon('eye-slash') ?> <?= e(__('Ausblenden')) ?></button>
        <?php else: ?>
        <button class="adm-btn adm-btn--primary adm-btn--block" name="state" value="published"><?= icon('eye') ?> <?= e(__('Wieder einblenden')) ?></button>
        <?php endif; ?>
      </form>
      <?php endif; ?>
      <dl class="md-info">
        <dt><?= e(__('Quelle')) ?></dt><dd><?= $canSrc ? '<a href="' . e(url('/admin/quellen/' . (int) $origin['source_id'])) . '">' . e($origin['source_name']) . '</a>' : e($origin['source_name']) ?></dd>
        <dt><?= e(__('Externe ID')) ?></dt><dd><code><?= e(mb_strimwidth((string) $origin['ext_id'], 0, 60, '…')) ?></code></dd>
        <dt><?= e(__('Zuletzt gesehen')) ?></dt><dd><?= e($origin['last_seen'] ? date('d.m.Y H:i', strtotime((string) $origin['last_seen'])) : '–') ?></dd>
        <dt><?= e(__('Geändert')) ?></dt><dd><?= e(date('d.m.Y H:i', strtotime((string) ($e['updated_at'] ?: $e['created_at'])))) ?></dd>
        <dt>ID</dt><dd><?= (int) $e['id'] ?></dd>
      </dl>
    </section>
    <?php if (can('data.delete', $t['handle'])): ?>
    <form method="post" action="<?= e(url($base . '/' . $e['id'] . '/delete')) ?>" data-confirm="<?= e(__('„{title}“ löschen? Beim nächsten Abruf legt die Quelle den Eintrag wieder an – dauerhaft entfernen Sie ihn in der Quelle oder mit „Ausblenden“.', ['title' => Entries::title($t, $e)])) ?>">
      <?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" type="submit"><?= e(__('Eintrag löschen')) ?></button>
    </form>
    <?php endif; ?>
  </aside>
</div>
