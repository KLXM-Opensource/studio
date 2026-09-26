<?php
/**
 * Geteilte Tabelle: Eintrag einer anderen Website – nur lesen, Auswahl dieser Website setzen.
 * @var array $t  @var array $e  @var string $src
 */
use Core\Data\Entries;
use Core\Data\Shared;
use Core\Data\Tables;

$base = '/admin/data/' . $t['handle'];
$key = $t['shared']['key'];
$isOwner = Shared::isOwner($t);
$info = Shared::siteInfo((string) $e['origin_site'], $key);
$origin = Shared::originUrl($t, $e);
$local = Entries::url($t, $e);
$pick = $e['_pick'];
$shown = (bool) Entries::query($t, ['status' => 'published', 'lang' => 'all', 'source' => 'site', 'ids' => [$e['id']]]);
$stateLabel = ['visible' => __('übernommen'), 'featured' => __('hervorgehoben'), 'hidden' => __('ausgeblendet'), 'rejected' => __('abgelehnt')];
$back = $base . '/' . $e['id'];
?>
<header class="adm-head dt-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url($base . '/shared') . '?src=' . $src) ?>"><span aria-hidden="true"><?= icon($t['icon']) ?></span> <?= e($t['name']) ?> · <?= e($src === 'owner' ? term('shared_from_owner') : term('shared_from_members')) ?></a></p>
    <h1><?= e(Entries::title($t, $e)) ?></h1></div>
  <div class="adm-row">
    <?php if ($origin): ?><a class="adm-btn adm-btn--ghost" href="<?= e($origin) ?>" target="_blank" rel="noopener"><?= e(__('Auf Ursprungs-Website öffnen')) ?> ↗</a><?php endif; ?>
    <?php if ($local && $shown): ?><a class="adm-btn adm-btn--ghost" href="<?= e($local) ?>" target="_blank" rel="noopener"><?= e(__('Ansehen')) ?> ↗</a><?php endif; ?>
  </div>
</header>

<div class="dt-entry">
  <section class="adm-card dt-entry__main sh-readonly">
    <p class="adm-flash adm-flash--info sh-note"><?= e(__('Dieser Eintrag stammt von „{site}“ und ist hier nur lesbar. Ändern kann ihn nur diese Website.', ['site' => $info['name']])) ?></p>
    <dl class="sh-fields">
      <?php foreach ($t['fields'] as $f): $h = Entries::html($t, $e, $f['name'], ['link' => false, 'plain' => true]); if ($h === '') continue; ?>
      <dt><?= e($f['label']) ?></dt><dd><?= $h ?></dd>
      <?php endforeach; ?>
    </dl>
  </section>
  <aside class="dt-entry__side">
    <section class="adm-card">
      <h2 class="sh-h"><?= e(__('Auf dieser Website')) ?></h2>
      <p><span class="dt-status dt-status--<?= $shown ? 'published' : 'draft' ?>"><?= e($shown ? __('sichtbar') : __('nicht sichtbar')) ?></span>
        <?php if ($pick): ?> <small class="sh-pick sh-pick--<?= e($pick) ?>"><?= e($stateLabel[$pick] ?? $pick) ?></small><?php endif; ?></p>
      <?php if (can('data.publish', $t['handle'])): ?>
      <form method="post" action="<?= e(url($base . '/pick')) ?>" class="sh-pickform">
        <?= csrf_field() ?><input type="hidden" name="ids[]" value="<?= (int) $e['id'] ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
        <?php if ($isOwner && !in_array($pick, ['visible', 'featured'], true)): ?><button class="adm-btn adm-btn--primary adm-btn--block" name="state" value="visible"><?= e(__('Übernehmen')) ?></button><?php endif; ?>
        <?php if ($pick !== 'featured'): ?><button class="adm-btn adm-btn--block" name="state" value="featured"><?= e(__('Hervorheben')) ?></button><?php endif; ?>
        <?php if ($isOwner && $pick === null && !empty($e['suggest'])): ?><button class="adm-btn adm-btn--block adm-btn--ghost adm-btn--danger-text" name="state" value="rejected"><?= e(__('Vorschlag ablehnen')) ?></button><?php endif; ?>
        <?php if ($pick !== 'hidden'): ?><button class="adm-btn adm-btn--block adm-btn--ghost" name="state" value="hidden"><?= e(__('Ausblenden')) ?></button><?php endif; ?>
        <?php if ($pick !== null): ?><button class="adm-btn adm-btn--block adm-btn--ghost" name="state" value="reset"><?= e(__('Zurücksetzen')) ?></button><?php endif; ?>
      </form>
      <?php endif; ?>
      <dl class="md-info">
        <dt><?= e(__('Website')) ?></dt><dd><?= $info['url'] !== '' ? '<a href="' . e($info['url']) . '" target="_blank" rel="noopener">' . e($info['name']) . '</a>' : e($info['name']) ?></dd>
        <?php if (!empty($e['suggest'])): ?><dt><?= e(__('Vorgeschlagen')) ?></dt><dd><?= e(__('ja')) ?></dd><?php endif; ?>
        <dt><?= e(__('Geändert')) ?></dt><dd><?= e(date('d.m.Y H:i', strtotime((string) $e['updated_at']))) ?></dd>
        <dt>ID</dt><dd><?= (int) $e['id'] ?></dd>
      </dl>
    </section>
  </aside>
</div>
