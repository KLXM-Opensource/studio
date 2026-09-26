<?php
/**
 * Eine Frage mit Antworten, Stimmen und akzeptierter Lösung.
 * @var array $q  @var array $answers  @var array $votes  @var string $draft  @var ?string $error
 */
use Core\Support\Markdown;
use Core\Support\Questions;
use Core\Support\Support;
use Core\Support\Ui;

$helpTab = 'questions';
include __DIR__ . '/_helptabs.php';
$me = Support::me();
$canVote = Support::canRead();
$vote = function (string $target, array $row, bool $on) use ($me, $canVote) {
    $own = $row['author_key'] === $me;
    $action = url('/admin/support/' . ($target === 'q' ? 'fragen' : 'antwort') . '/' . $row['id'] . '/stimme');
    $label = $target === 'q' ? __('Frage ist nützlich (+1)') : __('Antwort ist hilfreich (+1)');
    $out = '<div class="sp-vote">';
    if ($canVote && !$own) {
        $out .= '<form method="post" action="' . e($action) . '" data-support-vote>' . csrf_field()
            . '<button type="submit" class="sp-vote__btn' . ($on ? ' is-on' : '') . '" aria-pressed="' . ($on ? 'true' : 'false') . '" title="' . e($label) . '"><span aria-hidden="true">▲</span><span class="sr-only">' . e($label) . '</span></button></form>';
    }
    $out .= '<span class="sp-vote__n" data-support-score><span class="sr-only">' . e(__('Stimmen:')) . ' </span>' . (int) $row['score'] . '</span></div>';
    return $out;
};
$canAccept = Questions::canAccept($q);
?>
<article class="adm-card sp-question" aria-labelledby="q-h">
  <p class="adm-eyebrow"><a href="<?= e(url('/admin/support/fragen')) ?>">← <?= e(__('Fragen & Antworten')) ?></a></p>
  <h1 id="q-h"><?= e($q['title']) ?></h1>
  <p class="sp-article__meta">
    <?php if ($q['accepted_answer_id']): ?><span class="sp-badge sp-badge--geloest">✓ <?= e(__('Gelöst')) ?></span><?php endif; ?>
    <?php if ($q['visibility'] === 'site'): ?><span class="sp-badge sp-badge--vis"><?= e(Support::visibilityLabel('site')) ?></span><?php endif; ?>
    <?= Ui::tags((string) $q['tags'], '/admin/support/fragen') ?>
    <span class="adm-muted"><?= e(Support::authorLabel((string) $q['author_key'], $q['author_name'], (bool) $q['author_staff'])) ?> · <?= Ui::when($q['created_at']) ?> · <?= e(Ui::n((int) $q['views'], 'views')) ?></span>
  </p>
  <div class="sp-qa">
    <?= $vote('q', $q, in_array((int) $q['id'], $votes['q'], true)) ?>
    <div class="sp-md"><?= Markdown::render($q['body']) ?></div>
  </div>
  <?php if (Questions::canEdit($q)): ?>
  <p class="sp-owner adm-row">
    <a class="adm-link" href="<?= e(url('/admin/support/fragen/' . $q['id'] . '/bearbeiten')) ?>"><?= e(__('Bearbeiten')) ?></a>
    <?php if (Support::isStaff() || (int) $q['answers'] === 0): ?>
    <form method="post" action="<?= e(url('/admin/support/fragen/' . $q['id'] . '/loeschen')) ?>" data-confirm="<?= e(__('Frage mit allen Antworten löschen?')) ?>"><?= csrf_field() ?><button class="adm-link adm-btn--danger-text"><?= e(__('Löschen')) ?></button></form>
    <?php endif; ?>
  </p>
  <?php endif; ?>
</article>

<section class="sp-answers" aria-labelledby="ans-h">
  <h2 class="sp-h2" id="ans-h"><?= e(Ui::n(count($answers), 'answers')) ?></h2>
  <?php foreach ($answers as $a): $accepted = (int) $q['accepted_answer_id'] === (int) $a['id']; ?>
  <article class="adm-card sp-answer<?= $accepted ? ' is-accepted' : '' ?><?= (int) $a['author_staff'] ? ' is-staff' : '' ?>" id="a<?= (int) $a['id'] ?>" aria-label="<?= e(__('Antwort von {name}', ['name' => Support::authorLabel((string) $a['author_key'], $a['author_name'], (bool) $a['author_staff'])])) ?>">
    <div class="sp-qa">
      <?= $vote('a', $a, in_array((int) $a['id'], $votes['a'], true)) ?>
      <div>
        <?php if ($accepted): ?><p class="sp-accepted">✓ <?= e(__('Als Lösung markiert')) ?></p><?php endif; ?>
        <div class="sp-md"><?= Markdown::render($a['body']) ?></div>
        <p class="sp-answer__meta adm-muted"><?= e(Support::authorLabel((string) $a['author_key'], $a['author_name'], (bool) $a['author_staff'])) ?> · <?= Ui::when($a['created_at']) ?><?= $a['updated_at'] !== $a['created_at'] ? ' · ' . e(__('bearbeitet')) : '' ?></p>
        <div class="sp-owner adm-row">
          <?php if ($canAccept): ?>
          <form method="post" action="<?= e(url('/admin/support/antwort/' . $a['id'] . '/akzeptieren')) ?>"><?= csrf_field() ?>
            <button class="adm-btn adm-btn--small<?= $accepted ? ' adm-btn--ghost' : '' ?>"><?= e($accepted ? __('Markierung entfernen') : __('Als Lösung markieren')) ?></button></form>
          <?php endif; ?>
          <?php if (Questions::canEdit($a)): ?>
          <details class="sp-edit"><summary class="adm-link"><?= e(__('Bearbeiten')) ?></summary>
            <form method="post" action="<?= e(url('/admin/support/antwort/' . $a['id'])) ?>" class="sp-form"><?= csrf_field() ?>
              <label class="sr-only" for="ae-<?= (int) $a['id'] ?>"><?= e(__('Antwort bearbeiten')) ?></label>
              <textarea id="ae-<?= (int) $a['id'] ?>" name="body" rows="6"><?= e($a['body']) ?></textarea>
              <p class="adm-row"><button class="adm-btn adm-btn--small adm-btn--primary"><?= e(__('Speichern')) ?></button></p>
            </form>
          </details>
          <form method="post" action="<?= e(url('/admin/support/antwort/' . $a['id'] . '/loeschen')) ?>" data-confirm="<?= e(__('Antwort löschen?')) ?>"><?= csrf_field() ?><button class="adm-link adm-btn--danger-text"><?= e(__('Löschen')) ?></button></form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </article>
  <?php endforeach; ?>
  <?php if (!$answers): ?><p class="adm-muted"><?= e(__('Noch keine Antwort. Wissen Sie weiter? Dann helfen Sie gern!')) ?></p><?php endif; ?>
</section>

<?php if (Support::canAnswer()): ?>
<form class="adm-card sp-form" method="post" action="<?= e(url('/admin/support/fragen/' . $q['id'])) ?>" id="antworten" data-support-form>
  <?= csrf_field() ?>
  <h2 class="sp-h3"><?= e(__('Ihre Antwort')) ?></h2>
  <div class="f<?= $error ? ' f--error' : '' ?>">
    <label for="ans-body"><?= e(__('Antwort')) ?></label>
    <textarea id="ans-body" name="body" rows="7"<?= $error ? ' aria-invalid="true" aria-describedby="ans-e ans-h"' : ' aria-describedby="ans-h"' ?>><?= e($draft) ?></textarea>
    <?php if ($error): ?><p class="f-error" id="ans-e"><?= e($error) ?></p><?php endif; ?>
    <?= Ui::mdHint('ans-h') ?>
  </div>
  <p class="adm-muted sp-small"><?= e($q['visibility'] === 'all' ? __('Ihre Antwort sehen alle Redaktionen dieser Installation. Redaktionen anderer Websites sehen Ihren Namen nicht.') : __('Diese Frage ist nur für Ihre Website und das Support-Team sichtbar.')) ?></p>
  <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Antwort veröffentlichen')) ?></button>
</form>
<?php endif; ?>
