<?php
/**
 * Wissensartikel: Text, Tags, „Hilfreich?“, verwandte Artikel, Versionen (Team).
 * @var array $a  @var array $related  @var ?bool $mine  @var array $revisions
 */
use Core\Support\Markdown;
use Core\Support\Support;
use Core\Support\Ui;

$helpTab = 'kb';
include __DIR__ . '/_helptabs.php';
$isStaff = Support::isStaff();
?>
<div class="sp-article-wrap">
  <article class="adm-card sp-article" aria-labelledby="art-h">
    <p class="adm-eyebrow"><a href="<?= e(url('/admin/support/wissen')) ?>">← <?= e(__('Wissensdatenbank')) ?></a></p>
    <h1 id="art-h"><?= e($a['title']) ?></h1>
    <p class="sp-article__meta">
      <?php if ($a['status'] !== 'published'): ?><span class="sp-badge sp-badge--draft"><?= e(__('Entwurf')) ?></span><?php endif; ?>
      <?php if ($a['visibility'] !== 'all'): ?><span class="sp-badge sp-badge--vis"><?= e(Support::visibilityLabel($a['visibility'])) ?><?= $a['visibility'] === 'sites' && $isStaff ? ': ' . e(implode(', ', array_map([Support::class, 'siteLabel'], Support::tagList((string) $a['sites'])))) : '' ?></span><?php endif; ?>
      <?= Ui::tags((string) $a['tags']) ?>
      <span class="adm-muted"><?= e(__('Aktualisiert')) ?> <?= Ui::when($a['updated_at']) ?> · <?= e(Ui::n((int) $a['views'], 'views')) ?></span>
    </p>
    <div class="sp-md sp-md--article"><?= Markdown::render($a['body']) ?></div>

    <section class="sp-feedback" id="feedback" aria-labelledby="fb-h">
      <h2 id="fb-h"><?= e(__('War dieser Artikel hilfreich?')) ?></h2>
      <form method="post" action="<?= e(url('/admin/support/wissen/' . $a['id'] . '/hilfreich')) ?>" class="adm-row"><?= csrf_field() ?>
        <button class="adm-btn<?= $mine === true ? ' is-on' : '' ?>" name="helpful" value="1" aria-pressed="<?= $mine === true ? 'true' : 'false' ?>"><?= e(__('Ja')) ?></button>
        <button class="adm-btn<?= $mine === false ? ' is-on' : '' ?>" name="helpful" value="0" aria-pressed="<?= $mine === false ? 'true' : 'false' ?>"><?= e(__('Nein')) ?></button>
        <?php if ($mine !== null): ?><span class="adm-muted"><?= e(__('Danke für Ihre Rückmeldung!')) ?></span><?php endif; ?>
      </form>
      <?php if ($mine === false && Support::canReport()): ?>
      <p class="adm-muted"><?= e(__('Das tut uns leid. Beschreiben Sie Ihr Anliegen gern direkt dem Support:')) ?> <a href="<?= e(url('/admin/support/neu') . '?titel=' . rawurlencode($a['title'])) ?>" data-support-report><?= e(__('Problem melden')) ?></a></p>
      <?php endif; ?>
      <?php if ($isStaff): ?><p class="adm-muted sp-small"><?= e(__('Rückmeldungen: {yes}× ja, {no}× nein', ['yes' => (int) $a['helpful_yes'], 'no' => (int) $a['helpful_no']])) ?></p><?php endif; ?>
    </section>

    <?php if ($isStaff): ?>
    <footer class="sp-article__staff adm-row">
      <a class="adm-btn" href="<?= e(url('/admin/support/wissen/' . $a['id'] . '/bearbeiten')) ?>"><?= e(__('Bearbeiten')) ?></a>
      <?php if ($a['source_issue_id']): ?><a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/support/meldung/' . $a['source_issue_id'])) ?>"><?= e(__('Ursprüngliche Meldung #{id}', ['id' => $a['source_issue_id']])) ?></a><?php endif; ?>
      <form method="post" action="<?= e(url('/admin/support/wissen/' . $a['id'] . '/loeschen')) ?>" data-confirm="<?= e(__('Artikel „{title}“ mit allen Versionen löschen?', ['title' => $a['title']])) ?>"><?= csrf_field() ?>
        <button class="adm-btn adm-btn--ghost adm-btn--danger-text"><?= e(__('Löschen')) ?></button></form>
      <span class="adm-muted sp-small"><?= e(__('Verfasst von {author}, zuletzt bearbeitet von {editor}', ['author' => $a['author_name'] ?: '–', 'editor' => $a['editor_name'] ?: '–'])) ?></span>
    </footer>
    <?php endif; ?>
  </article>

  <aside class="sp-article-aside">
    <?php if ($related): ?>
    <section class="adm-card" aria-labelledby="rel-h">
      <h2 class="sp-h3" id="rel-h"><?= e(__('Verwandte Artikel')) ?></h2>
      <ul class="sp-mini"><?php foreach ($related as $r): ?><li><a href="<?= e(url('/admin/support/wissen/' . $r['id'])) ?>"><?= e($r['title']) ?></a></li><?php endforeach; ?></ul>
    </section>
    <?php endif; ?>
    <?php if ($revisions): ?>
    <section class="adm-card" aria-labelledby="rev-h">
      <h2 class="sp-h3" id="rev-h"><?= e(__('Versionen')) ?></h2>
      <ol class="sp-revs">
        <?php foreach ($revisions as $i => $v): ?>
        <li><span><?= e(date('d.m.Y H:i', strtotime((string) $v['created_at']))) ?> · <?= e($v['editor_name'] ?: '–') ?><?php if ($v['note']): ?><br><small><?= e($v['note']) ?></small><?php endif; ?></span>
          <?php if ($i === 0): ?><small class="adm-muted"><?= e(__('aktuell')) ?></small><?php else: ?>
          <form method="post" action="<?= e(url('/admin/support/wissen/' . $a['id'] . '/version/' . $v['id'])) ?>" data-confirm="<?= e(__('Diese Version wiederherstellen? Der aktuelle Stand bleibt als Version erhalten.')) ?>"><?= csrf_field() ?>
            <button class="adm-link"><?= e(__('Wiederherstellen')) ?></button></form><?php endif; ?></li>
        <?php endforeach; ?>
      </ol>
    </section>
    <?php endif; ?>
    <section class="adm-card sp-cta">
      <h2 class="sp-h3"><?= e(__('Noch Fragen?')) ?></h2>
      <p class="adm-row"><?php if (Support::canAnswer()): ?><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/support/fragen/neu')) ?>"><?= e(__('Frage stellen')) ?></a><?php endif; ?>
        <?php if (Support::canReport()): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/support/neu')) ?>" data-support-report><?= e(__('Problem melden')) ?></a><?php endif; ?></p>
    </section>
  </aside>
</div>
