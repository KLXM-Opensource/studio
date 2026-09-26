<?php
/**
 * Fragen & Antworten: Liste mit Stimmen, Antworten, Aufrufen, Lösung.
 * @var array $f  @var array $list
 */
use Core\Support\Markdown;
use Core\Support\Support;
use Core\Support\Ui;

$qs = fn(array $p) => url('/admin/support/fragen') . '?' . http_build_query(array_filter($p + ['q' => $f['q'], 'tag' => $f['tag'], 'sort' => $f['sort'], 'filter' => $f['filter']], fn($v) => $v !== null && $v !== ''));
$helpTab = 'questions';
include __DIR__ . '/_helptabs.php';
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Handbuch & Hilfe')) ?></p><h1><?= e(__('Fragen & Antworten')) ?></h1>
    <p class="adm-muted"><?= e(__('Redaktionen helfen sich gegenseitig – gute Antworten bekommen Stimmen, die beste wird als Lösung markiert.')) ?></p></div>
  <?php if (Support::canAnswer()): ?><a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/support/fragen/neu')) ?>">+ <?= e(__('Frage stellen')) ?></a><?php endif; ?>
</header>

<form class="sp-search" method="get" action="<?= e(url('/admin/support/fragen')) ?>" role="search">
  <label class="sr-only" for="qa-q"><?= e(__('Fragen durchsuchen')) ?></label>
  <input id="qa-q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(__('Fragen und Antworten durchsuchen')) ?>">
  <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Suchen')) ?></button>
</form>

<nav class="adm-filter sp-filter" aria-label="<?= e(__('Ansicht')) ?>">
  <?php foreach (['' => __('Aktiv'), 'neu' => __('Neu'), 'beliebt' => __('Beliebt')] as $k => $l): ?>
  <a href="<?= e($qs(['sort' => $k, 'filter' => null, 'seite' => null])) ?>"<?= $f['sort'] === $k && $f['filter'] === '' && $f['q'] === '' ? ' aria-current="true"' : '' ?>><?= e($l) ?></a>
  <?php endforeach; ?>
  <?php foreach (['offen' => __('Unbeantwortet'), 'geloest' => __('Gelöst'), 'meine' => __('Meine Fragen')] as $k => $l): ?>
  <a href="<?= e($qs(['filter' => $k, 'sort' => null, 'seite' => null])) ?>"<?= $f['filter'] === $k ? ' aria-current="true"' : '' ?>><?= e($l) ?></a>
  <?php endforeach; ?>
  <?php if ($f['tag'] !== ''): ?><span class="sp-activetag"><?= e(__('Tag')) ?>: <b>#<?= e($f['tag']) ?></b> <a href="<?= e($qs(['tag' => null])) ?>" aria-label="<?= e(__('Filter #{tag} entfernen', ['tag' => $f['tag']])) ?>">✕</a></span><?php endif; ?>
</nav>

<?php if (!$list['rows']): ?>
<div class="adm-card sp-empty">
  <p><b><?= e($f['q'] !== '' ? __('Keine Frage gefunden.') : __('Hier gibt es noch keine Fragen.')) ?></b></p>
  <?php if (Support::canAnswer()): ?><p><a class="adm-btn" href="<?= e(url('/admin/support/fragen/neu') . ($f['q'] !== '' ? '?titel=' . rawurlencode($f['q']) : '')) ?>"><?= e(__('Die erste Frage stellen')) ?></a></p><?php endif; ?>
</div>
<?php else: ?>
<ul class="sp-qlist">
  <?php foreach ($list['rows'] as $q): $solved = (bool) $q['accepted_answer_id']; ?>
  <li class="sp-q">
    <div class="sp-q__stats" aria-hidden="true">
      <span><b><?= (int) $q['score'] ?></b> <?= e(__('Stimmen')) ?></span>
      <span class="<?= $solved ? 'is-solved' : ((int) $q['answers'] ? 'is-answered' : '') ?>"><b><?= $solved ? '✓ ' : '' ?><?= (int) $q['answers'] ?></b> <?= e(__('Antworten')) ?></span>
      <span><?= e(Ui::n((int) $q['views'], 'views')) ?></span>
    </div>
    <div class="sp-q__main">
      <h2><a href="<?= e(url('/admin/support/fragen/' . $q['id'])) ?>"><?= e($q['title']) ?></a></h2>
      <p class="sr-only"><?= e(__('{score} Stimmen, {n} Antworten, {v} Aufrufe', ['score' => (int) $q['score'], 'n' => (int) $q['answers'], 'v' => (int) $q['views']])) ?><?= $solved ? ', ' . e(__('gelöst')) : '' ?></p>
      <p class="sp-card__text"><?= !empty($q['snippet']) ? Ui::snippet($q['snippet']) : e(Markdown::plain($q['body'], 160)) ?></p>
      <p class="sp-card__meta"><?= Ui::tags((string) $q['tags'], '/admin/support/fragen') ?>
        <?php if ($q['visibility'] === 'site'): ?><span class="sp-badge sp-badge--vis"><?= e(Support::visibilityLabel('site')) ?></span><?php endif; ?>
        <span class="adm-muted"><?= e(Support::authorLabel((string) $q['author_key'], $q['author_name'], (bool) $q['author_staff'])) ?> · <?= Ui::when($q['created_at']) ?></span></p>
    </div>
  </li>
  <?php endforeach; ?>
</ul>
<?= Ui::pager(max(1, (int) $f['page']), $list['pages'], $qs) ?>
<?php endif; ?>
