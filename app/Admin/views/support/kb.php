<?php
/**
 * Wissensdatenbank: Suche, Tags, Beliebt, Neu.
 * @var array $f  @var array $list  @var bool $browse  @var array $popular  @var array $questions  @var array $tags
 */
use Core\Support\Markdown;
use Core\Support\Support;
use Core\Support\Ui;

$qs = fn(array $p) => url('/admin/support/wissen') . '?' . http_build_query(array_filter($p + ['q' => $f['q'], 'tag' => $f['tag'], 'sort' => $f['sort']], fn($v) => $v !== null && $v !== ''));
$helpTab = 'kb';
include __DIR__ . '/_helptabs.php';
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Handbuch & Hilfe')) ?></p><h1><?= e(__('Wissensdatenbank')) ?></h1>
    <p class="adm-muted"><?= e(__('Anleitungen und gesammelte Lösungen aus dem Support – für alle Websites dieser Installation.')) ?></p></div>
  <?php if (Support::isStaff()): ?><a class="adm-btn" href="<?= e(url('/admin/support/wissen/neu')) ?>">+ <?= e(__('Neuer Wissensartikel')) ?></a><?php endif; ?>
</header>

<form class="sp-search" method="get" action="<?= e(url('/admin/support/wissen')) ?>" role="search">
  <label class="sr-only" for="kb-q"><?= e(__('Wissensdatenbank durchsuchen')) ?></label>
  <input id="kb-q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(__('Wonach suchen Sie? z. B. „Alt-Text“, „veröffentlichen“, „PDF“')) ?>">
  <?php if ($f['tag'] !== ''): ?><input type="hidden" name="tag" value="<?= e($f['tag']) ?>"><?php endif; ?>
  <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Suchen')) ?></button>
</form>

<nav class="adm-filter sp-filter" aria-label="<?= e(__('Sortierung')) ?>">
  <?php foreach (['' => __('Neu'), 'beliebt' => __('Beliebt'), 'titel' => __('A–Z')] as $k => $l): ?>
  <a href="<?= e($qs(['sort' => $k, 'seite' => null])) ?>"<?= $f['sort'] === $k && $f['q'] === '' ? ' aria-current="true"' : '' ?>><?= e($l) ?></a>
  <?php endforeach; ?>
  <?php if ($f['tag'] !== ''): ?><span class="sp-activetag"><?= e(__('Tag')) ?>: <b>#<?= e($f['tag']) ?></b> <a href="<?= e($qs(['tag' => null])) ?>" aria-label="<?= e(__('Filter #{tag} entfernen', ['tag' => $f['tag']])) ?>">✕</a></span><?php endif; ?>
</nav>

<div class="sp-kb<?= $browse ? ' sp-kb--browse' : '' ?>">
  <section aria-labelledby="kb-list-h">
    <h2 class="sp-h2" id="kb-list-h"><?= e($f['q'] !== '' ? __('{n} Treffer für „{q}“', ['n' => $list['total'], 'q' => $f['q']]) : ($f['sort'] === 'beliebt' ? __('Beliebt') : ($browse ? __('Neu') : __('Artikel')))) ?></h2>
    <?php if (!$list['rows']): ?>
    <div class="adm-card sp-empty">
      <p><b><?= e(__('Nichts gefunden.')) ?></b></p>
      <p class="adm-muted"><?= e(__('Andere Wörter probieren – oder die Frage der Gemeinschaft stellen bzw. dem Support melden.')) ?></p>
      <p class="adm-row">
        <?php if (Support::canAnswer()): ?><a class="adm-btn" href="<?= e(url('/admin/support/fragen/neu') . '?titel=' . rawurlencode($f['q'])) ?>"><?= e(__('Frage stellen')) ?></a><?php endif; ?>
        <?php if (Support::canReport()): ?><a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/support/neu') . '?titel=' . rawurlencode($f['q'])) ?>"><?= e(__('Problem melden')) ?></a><?php endif; ?>
      </p>
    </div>
    <?php else: ?>
    <ul class="sp-cards">
      <?php foreach ($list['rows'] as $a): ?>
      <li class="sp-card">
        <h3><a href="<?= e(url('/admin/support/wissen/' . $a['id'])) ?>"><?= e($a['title']) ?></a></h3>
        <p class="sp-card__text"><?= !empty($a['snippet']) ? Ui::snippet($a['snippet']) : e(Markdown::plain($a['body'], 180)) ?></p>
        <p class="sp-card__meta">
          <?php if ($a['status'] !== 'published'): ?><span class="sp-badge sp-badge--draft"><?= e(__('Entwurf')) ?></span><?php endif; ?>
          <?php if ($a['visibility'] !== 'all'): ?><span class="sp-badge sp-badge--vis"><?= e(Support::visibilityLabel($a['visibility'])) ?></span><?php endif; ?>
          <?= Ui::tags((string) $a['tags']) ?>
          <span class="adm-muted"><?= e(Ui::n((int) $a['views'], 'views')) ?><?php if ((int) $a['helpful_yes']): ?> · <?= e(__('{n}× hilfreich', ['n' => (int) $a['helpful_yes']])) ?><?php endif; ?></span>
        </p>
      </li>
      <?php endforeach; ?>
    </ul>
    <?= Ui::pager(max(1, (int) $f['page']), $list['pages'], $qs) ?>
    <?php endif; ?>
  </section>

  <?php if ($browse): ?>
  <aside class="sp-kb__aside">
    <?php if ($popular): ?>
    <section class="adm-card" aria-labelledby="kb-pop-h">
      <h2 class="sp-h3" id="kb-pop-h"><?= e(__('Beliebt')) ?></h2>
      <ol class="sp-mini"><?php foreach ($popular as $a): ?><li><a href="<?= e(url('/admin/support/wissen/' . $a['id'])) ?>"><?= e($a['title']) ?></a></li><?php endforeach; ?></ol>
    </section>
    <?php endif; ?>
    <?php if ($questions): ?>
    <section class="adm-card" aria-labelledby="kb-q-h">
      <h2 class="sp-h3" id="kb-q-h"><?= e(__('Gelöste Fragen')) ?></h2>
      <ul class="sp-mini"><?php foreach ($questions as $q): ?><li><a href="<?= e(url('/admin/support/fragen/' . $q['id'])) ?>"><?= e($q['title']) ?></a> <span aria-label="<?= e(__('gelöst')) ?>">✓</span></li><?php endforeach; ?></ul>
      <p><a href="<?= e(url('/admin/support/fragen')) ?>"><?= e(__('Alle Fragen & Antworten')) ?> →</a></p>
    </section>
    <?php endif; ?>
    <?php if ($tags): ?>
    <section class="adm-card" aria-labelledby="kb-tags-h">
      <h2 class="sp-h3" id="kb-tags-h"><?= e(__('Tags')) ?></h2>
      <p class="sp-tags"><?php foreach ($tags as $t => $n): ?><a class="sp-tag" href="<?= e($qs(['tag' => $t, 'q' => null, 'sort' => null])) ?>">#<?= e($t) ?> <small><?= (int) $n ?></small></a><?php endforeach; ?></p>
    </section>
    <?php endif; ?>
    <section class="adm-card sp-cta" aria-labelledby="kb-cta-h">
      <h2 class="sp-h3" id="kb-cta-h"><?= e(__('Nichts Passendes dabei?')) ?></h2>
      <p class="adm-muted"><?= e(__('Fragen Sie die anderen Redaktionen oder wenden Sie sich direkt an den Support.')) ?></p>
      <p class="adm-row"><?php if (Support::canAnswer()): ?><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/support/fragen/neu')) ?>"><?= e(__('Frage stellen')) ?></a><?php endif; ?>
        <?php if (Support::canReport()): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/support/neu')) ?>" data-support-report><?= e(__('Problem melden')) ?></a><?php endif; ?></p>
    </section>
  </aside>
  <?php endif; ?>
</div>
