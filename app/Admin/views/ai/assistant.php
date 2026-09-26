<?php
/**
 * KI-Bereich → Assistent (Core\AI\Assistant): Chat im Hauptbereich (resources/js/assistant.mjs hängt sich an
 * [data-assistant-page]), gespeicherte Unterhaltungen und der Verlauf ohne Inhalte.
 * @var bool $available  @var string $q  @var array $list
 */
use Core\AI\Assist;

$fmt = fn(string $ts) => date('d.m.Y H:i', strtotime($ts));
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/ai')) ?>"><?= e(Assist::brand()) ?></a></p><h1><?= e(__('Assistent')) ?></h1>
    <p class="adm-muted"><?= e(__('Fragen Sie, wie etwas geht – die Antworten stammen aus Handbuch, Tutorials und Wissensdatenbank, mit Links zu den Stellen. Der Assistent kann auch Entwürfe vorschlagen; ausgeführt wird erst nach Ihrem Klick auf „Ausführen“.')) ?></p></div>
</header>
<?php if (!$available): ?>
<div class="adm-flash adm-flash--info" role="status"><?= e(__('Der Assistent ist auf dieser Website nicht eingeschaltet. Die Administration schaltet KI (Texte) und den Assistenten unter Grundeinstellungen → KI ein.')) ?></div>
<?php else: ?>
<section class="adm-card kas-host" aria-label="<?= e(__('Assistent')) ?>">
  <div data-assistant-page data-q="<?= e($q) ?>"><p class="adm-muted"><?= e(__('Der Assistent braucht JavaScript.')) ?></p></div>
</section>

<section class="adm-card" id="kas-saved" aria-labelledby="kas-saved-h">
  <h2 id="kas-saved-h"><?= e(__('Gespeicherte Unterhaltungen')) ?></h2>
  <p class="adm-muted"><?= e(__('Nur Unterhaltungen, die Sie selbst speichern, werden mit Inhalt aufbewahrt – bis Sie sie löschen.')) ?></p>
  <?php if (!$list['saved']): ?><p class="adm-muted" data-kas-empty><?= e(__('Noch keine gespeicherten Unterhaltungen.')) ?></p><?php endif; ?>
  <ul class="kas-list" data-kas-saved>
    <?php foreach ($list['saved'] as $c): ?>
    <li data-id="<?= e($c['id']) ?>"><button type="button" class="adm-link kas-list__open" data-kas-open="<?= e($c['id']) ?>"><?= $c['pinned'] ? icon('push-pin', ['label' => __('Angeheftet')]) . ' ' : '' ?><?= e($c['title'] ?: __('Unterhaltung')) ?></button>
      <small class="adm-muted"><?= e($fmt($c['updated'])) ?> · <?= e(__('{n} Fragen', ['n' => (int) $c['turns']])) ?></small>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-kas-delete="<?= e($c['id']) ?>"><?= e(__('Löschen')) ?></button></li>
    <?php endforeach; ?>
  </ul>
</section>

<?php if ($list['recent']): ?>
<section class="adm-card" aria-labelledby="kas-recent-h">
  <h2 id="kas-recent-h"><?= e(__('Verlauf (ohne Inhalte)')) ?></h2>
  <p class="adm-muted"><?= e(__('Ungespeicherte Unterhaltungen der letzten 90 Tage: nur Zeit, Anzahl der Fragen und Aktionen und der Bildschirm, von dem aus gefragt wurde.')) ?></p>
  <div class="kia-tablewrap"><table class="adm-table">
    <thead><tr><th scope="col"><?= e(__('Zeit')) ?></th><th scope="col" class="num"><?= e(__('Fragen')) ?></th><th scope="col" class="num"><?= e(__('Aktionen')) ?></th><th scope="col"><?= e(__('Bildschirm')) ?></th><th scope="col"><span class="sr-only"><?= e(__('Löschen')) ?></span></th></tr></thead>
    <tbody><?php foreach ($list['recent'] as $c): ?>
      <tr data-id="<?= e($c['id']) ?>"><td><?= e($fmt($c['updated'])) ?></td><td class="num"><?= (int) $c['turns'] ?></td><td class="num"><?= (int) $c['actions'] ?></td><td><code><?= e($c['route'] ?: '/admin') ?></code></td>
        <td><button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-kas-delete="<?= e($c['id']) ?>"><?= e(__('Löschen')) ?></button></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>
<?php endif; ?>
