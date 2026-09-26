<?php
/**
 * Eintrag bearbeiten.
 * @var array $t  @var ?array $e  @var array $values  @var array $errors
 */
use Core\Data\Entries;
use Core\Fields;

$isNew = $e === null;
$base = '/admin/data/' . $t['handle'];
$url = $e ? Entries::url($t, $e) : null;
$status = $values['status'] ?? ($e['status'] ?? 'published');
?>
<header class="adm-head dt-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url($base)) ?>"><span aria-hidden="true"><?= icon($t['icon']) ?></span> <?= e($t['name']) ?></a></p>
    <h1><?= $isNew ? 'Neu: ' . e($t['singular']) : e(Entries::title($t, $e)) ?></h1></div>
  <?php if ($url): ?><a class="adm-btn adm-btn--ghost" href="<?= e($url) ?>" target="_blank" rel="noopener">Ansehen ↗</a><?php endif; ?>
</header>

<form method="post" action="<?= e(url($isNew ? $base . '/new' : $base . '/' . $e['id'])) ?>" class="dt-entry" novalidate<?php if ($cond = \Core\Data\Rules::client($t['fields'])): ?> data-conditions="<?= e(json_encode($cond, JSON_UNESCAPED_UNICODE)) ?>"<?php endif; ?>>
  <?= csrf_field() ?>
  <section class="adm-card dt-entry__main">
    <div class="adm-fields"><?= Fields::renderForm(Entries::schema($t), $values, $errors, 'f') ?></div>
  </section>
  <aside class="dt-entry__side">
    <section class="adm-card">
      <?php if ($t['settings']['workflow'] && !can('data.publish', $t['handle'])): ?>
      <p class="dt-note"><span class="dt-status dt-status--draft">Entwurf</span> <?= e(__('Ihre Rolle speichert Einträge als Entwurf – veröffentlicht wird von der Redaktion.')) ?></p>
      <?php elseif ($t['settings']['workflow']): ?>
      <fieldset class="dt-statuspick"><legend>Status</legend>
        <label><input type="radio" name="status" value="published"<?= $status === 'published' ? ' checked' : '' ?>> <span class="dt-status dt-status--published">Online</span></label>
        <label><input type="radio" name="status" value="draft"<?= $status === 'draft' ? ' checked' : '' ?>> <span class="dt-status dt-status--draft">Entwurf</span></label>
      </fieldset>
      <?php endif; ?>
      <?php if (\Core\Lang::multi()): $elang = $isNew ? (\Core\Lang::valid($_GET['lang'] ?? '') ? $_GET['lang'] : \Core\Lang::default()) : \Core\Lang::norm($e['lang'] ?? null); ?>
      <?php if ($isNew): ?><input type="hidden" name="lang" value="<?= e($elang) ?>"><?php endif; ?>
      <div class="dt-langbox"><strong><?= e(__('Sprache')) ?>: <?= e(\Core\Lang::all()[$elang] ?? $elang) ?></strong>
        <?php if (!$isNew): $tr = Entries::translations($t, $e); foreach (\Core\Lang::all() as $code => $label): if ($code === $elang) continue; ?>
        <?php if (isset($tr[$code])): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url($base . '/' . $tr[$code]['id'])) ?>"><?= e($label) ?> ↗</a>
        <?php else: ?><button class="adm-btn adm-btn--small" form="tr-<?= e($code) ?>">+ <?= e($label) ?></button><?php endif; ?>
        <?php endforeach; endif; ?>
      </div>
      <?php endif; ?>
      <?php if (\Core\Data\Tables::isShared($t)): $shOwner = \Core\Data\Shared::isOwner($t); ?>
      <div class="f sh-suggest">
        <p class="sh-sharednote"><span class="dt-nav__shared"><?= e(__('geteilt')) ?></span> <?= e($shOwner ? __('Erscheint je nach deren Einstellung auch auf den übrigen Websites dieser Tabelle.') : __('Diese Website pflegt ihre eigenen Einträge; „{site}“ legt die Felder fest.', ['site' => \Core\Data\Shared::siteInfo($t['shared']['owner'], $t['shared']['key'])['name']])) ?></p>
        <?php if (!$shOwner):
          $sugg = !empty($values['_suggest'] ?? $values['suggest'] ?? false);
          $sp = $e ? (\Core\Data\Shared::picks($t, [$e['id']], $t['shared']['owner'])[$e['id']] ?? null) : null; ?>
        <input type="hidden" name="suggest" value="0">
        <label class="f-check"><input type="checkbox" name="suggest" value="1"<?= $sugg ? ' checked' : '' ?>> <span><?= e(__('Dem {owner} vorschlagen', ['owner' => term('shared_owner')])) ?></span></label>
        <p class="f-help"><?= e(match ($sp) {
            'visible' => __('Übernommen – erscheint auf der Website von „{site}“.', ['site' => \Core\Data\Shared::siteInfo($t['shared']['owner'], $t['shared']['key'])['name']]),
            'featured' => __('Übernommen und hervorgehoben.'),
            'rejected' => __('Vorschlag wurde abgelehnt.'),
            'hidden' => __('Dort ausgeblendet.'),
            default => $sugg ? __('Vorgeschlagen – noch nicht entschieden.') : __('Veröffentlichte Einträge mit Häkchen erscheinen dort unter „Vorschläge“.'),
        }) ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="f"><label for="slug">Adresse</label>
        <div class="adm-prefix"><span>/<?= e($t['settings']['route'] ?: '…') ?>/</span><input id="slug" name="slug" value="<?= e($values['slug'] ?? '') ?>" placeholder="aus dem Titel"></div>
        <?php if ($t['settings']['route'] === ''): ?><p class="f-help">Diese Tabelle hat keine Detailseiten.</p><?php endif; ?></div>
      <div class="dt-save">
        <button class="adm-btn adm-btn--primary adm-btn--block" type="submit">Speichern</button>
        <div class="adm-row"><button class="adm-btn adm-btn--small adm-btn--ghost" name="then" value="new">Speichern &amp; neu</button><button class="adm-btn adm-btn--small adm-btn--ghost" name="then" value="list">Speichern &amp; zur Liste</button></div>
      </div>
      <?php if (!$isNew): ?>
      <dl class="md-info">
        <dt>Angelegt</dt><dd><?= e(date('d.m.Y H:i', strtotime((string) $e['created_at']))) ?></dd>
        <dt>Geändert</dt><dd><?= e(date('d.m.Y H:i', strtotime((string) $e['updated_at']))) ?></dd>
        <?php if ($e['published_at']): ?><dt>Veröffentlicht</dt><dd><?= e(date('d.m.Y', strtotime((string) $e['published_at']))) ?></dd><?php endif; ?>
        <dt>ID</dt><dd><?= (int) $e['id'] ?></dd>
      </dl>
      <?php endif; ?>
    </section>
    <?php if (!$isNew && ($links = Entries::backlinks($t, (int) $e['id']))): ?>
    <section class="adm-card dt-backlinks">
      <h2>Verknüpft mit</h2>
      <?php foreach ($links as $bl): ?>
      <p class="dt-bl-head"><span aria-hidden="true"><?= icon($bl['table']['icon']) ?></span> <?= e($bl['table']['name']) ?> <small>· <?= e($bl['field']['label']) ?> · <?= count($bl['entries']) ?></small></p>
      <ul><?php foreach ($bl['entries'] as $be): ?><li><a href="<?= e(url('/admin/data/' . $bl['table']['handle'] . '/' . $be['id'])) ?>"><?= e(Entries::title($bl['table'], $be)) ?></a><?= $be['status'] === 'draft' ? ' <small class="adm-muted">(Entwurf)</small>' : '' ?></li><?php endforeach; ?></ul>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>
    <?= /* KI-Assistent: Teaser, SEO, Übersetzen (Core\AI) */ \Core\Theme::capture(ROOT . '/app/Admin/views/ai/_entry.php', ['t' => $t, 'e' => $e]) ?>
    <?php if (!$isNew && can('data.delete', $t['handle'])): ?>
    <button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" form="del" type="submit">Eintrag löschen</button>
    <?php endif; ?>
  </aside>
</form>
<?php if (!$isNew && \Core\Lang::multi()): foreach (\Core\Lang::all() as $code => $label): ?>
<form id="tr-<?= e($code) ?>" method="post" action="<?= e(url($base . '/' . $e['id'] . '/translate')) ?>"><?= csrf_field() ?><input type="hidden" name="lang" value="<?= e($code) ?>"></form>
<?php endforeach; endif; ?>
<?php if (!$isNew): ?>
<form id="del" method="post" action="<?= e(url($base . '/' . $e['id'] . '/delete')) ?>" data-confirm="„<?= e(Entries::title($t, $e)) ?>“ endgültig löschen?"><?= csrf_field() ?></form>
<?php endif; ?>
