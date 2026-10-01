<?php
/**
 * Entwürfe (Core\Review\Drafts): offene Seiten- und Eintrags-Entwürfe der Website mit Herkunft, Alter, Notiz/Zuständigkeit,
 * Unterschieden, Veröffentlichen und Verwerfen (einzeln und gesammelt – Rückfrage über data-confirm, admin.js).
 * Ohne JavaScript funktionieren Auswahl, Aktionen und Notizen ebenso (Notiz-Felder hängen per form="…" an eigenen Formularen).
 * @var string $f  @var array $items  @var array $counts  @var array $users  @var array $discards  @var int $submitted
 */
use Core\Http\Controllers\Admin\DraftController;
use Core\Lang;
use Core\Review\Drafts;

$tabs = ['all' => __('Alle'), 'mine' => __('Meine'), 'review' => __('Zur Prüfung'), 'stale' => __('Vergessen?'), 'auto' => __('Content-Sync & KI')];
$back = '/admin/entwuerfe' . ($f !== 'all' ? '?filter=' . $f : '');
$multi = Lang::multi();
$ts = fn($t): int => (int) strtotime((string) $t);
// Relative Zeitangabe („vor 3 Tagen“) mit genauem Datum als <time> + title
$ago = function ($t) use ($ts): string {
    if (!$t) return '–';
    $d = time() - $ts($t);
    $txt = match (true) {
        $d < 90 => __('gerade eben'),
        $d < 3600 => __('vor {n} Min.', ['n' => (int) round($d / 60)]),
        $d < 86400 => __('vor {n} Std.', ['n' => (int) round($d / 3600)]),
        $d < 2 * 86400 => __('gestern'),
        $d < 45 * 86400 => __('vor {n} Tagen', ['n' => (int) floor($d / 86400)]),
        default => date('d.m.Y', $ts($t)),
    };
    return '<time datetime="' . e(date('c', $ts($t))) . '" title="' . e(date('d.m.Y H:i', $ts($t))) . '">' . e($txt) . '</time>';
};
$typeLabel = fn(array $i) => $i['type'] === 'page' ? __('Seite') : (string) ($i['table']['singular'] ?: $i['table']['name']);
$showUrl = fn(array $i) => $i['type'] === 'page' ? '/admin/entwuerfe/seite/' . $i['id'] : '/admin/entwuerfe/eintrag/' . $i['table']['handle'] . '/' . $i['id'];
$anyPublish = (bool) array_filter($items, fn($i) => $i['can_publish']);
$anyDiscard = (bool) array_filter($items, fn($i) => $i['can_discard']);
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Inhalte')) ?></p><h1><?= e(__('Entwürfe')) ?></h1>
    <p class="adm-muted"><?= e(__('Alles, was noch nicht (oder nicht in dieser Fassung) online ist: neue Seiten, veröffentlichte Seiten mit unveröffentlichten Änderungen und Einträge im Entwurf. Prüfen, veröffentlichen oder verwerfen – vergessene Zwischenstände fallen nach {n} Tagen ohne Änderung auf.', ['n' => Drafts::STALE_DAYS])) ?></p></div>
</header>

<?php if ($submitted): ?>
<p class="adm-card dr-hint"><?= icon('clipboard-text') ?> <span><?= e($submitted === 1 ? __('Außerdem wartet 1 eingereichte Änderung (API, MCP oder KI) auf Freigabe.') : __('Außerdem warten {n} eingereichte Änderungen (API, MCP oder KI) auf Freigabe.', ['n' => $submitted])) ?>
  <a href="<?= e(url('/admin/ai/eingereicht')) ?>"><?= e(__('Zu „Eingereicht“ →')) ?></a></span></p>
<?php endif; ?>

<nav class="adm-filter rv-tabs dr-tabs" aria-label="<?= e(__('Entwürfe filtern')) ?>">
  <?php foreach ($tabs as $k => $l): ?>
  <a href="<?= e(url('/admin/entwuerfe' . ($k !== 'all' ? '?filter=' . $k : ''))) ?>"<?= $f === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?> <small class="rv-tabs__n"><?= (int) ($counts[$k] ?? 0) ?></small></a>
  <?php endforeach; ?>
</nav>

<?php if (!$items): ?>
<div class="adm-card rv-empty">
  <p><b><?= e($f === 'all' ? __('Keine offenen Entwürfe.') : __('Keine Entwürfe in dieser Ansicht.')) ?></b></p>
  <p class="adm-muted"><?= e($f === 'all' ? __('Alle Seiten und Einträge sind so online, wie sie zuletzt bearbeitet wurden.') : __('Unter „Alle“ stehen sämtliche offenen Entwürfe.')) ?></p>
</div>
<?php else: ?>
<form method="post" action="<?= e(url('/admin/entwuerfe/veroeffentlichen')) ?>" class="rv-listform" data-rv-bulk>
  <?= csrf_field() ?>
  <input type="hidden" name="back" value="<?= e($back) ?>">
  <?php if ($anyPublish || $anyDiscard): ?>
  <div class="rv-bulk" role="group" aria-label="<?= e(__('Sammelaktion')) ?>">
    <label class="rv-check"><input type="checkbox" data-rv-all> <span><?= e(__('Alle auswählen')) ?></span></label>
    <span class="adm-muted" data-rv-count aria-live="polite"></span>
    <span class="dr-bulk__btns">
      <?php if ($anyPublish): ?><button class="adm-btn adm-btn--small adm-btn--primary" type="submit" formaction="<?= e(url('/admin/entwuerfe/veroeffentlichen')) ?>" data-rv-need
        data-confirm="<?= e(__('Ausgewählte Entwürfe veröffentlichen? Sie sind danach sofort auf der Website sichtbar.')) ?>"><?= e(__('Ausgewählte veröffentlichen')) ?></button><?php endif; ?>
      <?php if ($anyDiscard): ?><button class="adm-btn adm-btn--small adm-btn--danger-text" type="submit" formaction="<?= e(url('/admin/entwuerfe/verwerfen')) ?>" data-rv-need
        data-confirm="<?= e(__('Ausgewählte Entwürfe verwerfen? Seiten gehen zurück auf die veröffentlichte Fassung (der Entwurf bleibt als Version gesichert), Einträge werden gelöscht und unter „Zuletzt verworfen“ gesichert.')) ?>"><?= e(__('Ausgewählte verwerfen')) ?></button><?php endif; ?>
    </span>
  </div>
  <?php endif; ?>
  <div class="adm-card adm-card--flush rv-tablewrap">
  <table class="adm-table rv-table dr-table">
    <caption class="sr-only"><?= e(__('Entwürfe') . ' · ' . $tabs[$f]) ?></caption>
    <thead><tr>
      <th scope="col" class="rv-table__sel"><span class="sr-only"><?= e(__('Auswahl')) ?></span></th>
      <th scope="col"><?= e(__('Entwurf')) ?></th><th scope="col"><?= e(__('Status')) ?></th><th scope="col"><?= e(__('Zuletzt geändert')) ?></th>
      <th scope="col"><?= e(__('Notiz & Zuständigkeit')) ?></th><th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th>
    </tr></thead>
    <tbody>
    <?php foreach ($items as $n => $i): $aid = DraftController::anchor($i['key']); $sel = $i['can_publish'] || $i['can_discard']; ?>
      <tr class="rv-row dr-row<?= $i['stale'] ? ' is-stale' : '' ?>" id="<?= e($aid) ?>">
        <td class="rv-table__sel"><?php if ($sel): ?><input type="checkbox" name="items[]" value="<?= e($i['key']) ?>" id="dr-sel-<?= $n ?>" aria-labelledby="dr-t-<?= $n ?>" data-rv-item<?= ($i['state'] ?? '') === 'offline' ? ' data-rv-noall' : '' ?>><?php endif; ?></td>
        <td class="dr-what">
          <span class="rv-row__type"><?= e($typeLabel($i)) ?></span><?php if ($multi): ?> <span class="dr-lang" title="<?= e(Lang::all()[$i['lang']] ?? $i['lang']) ?>"><?= e(strtoupper($i['lang'])) ?></span><?php endif; ?>
          <a class="rv-row__title" id="dr-t-<?= $n ?>" href="<?= e(url($showUrl($i))) ?>"><?= e($i['title'] !== '' ? $i['title'] : '–') ?></a>
          <?php if ($i['origin_label'] !== ''): ?><span class="rv-row__sum"><span class="rv-ch rv-ch--<?= e($i['origin_key']) ?>"><?= e($i['origin_label']) ?></span></span><?php endif; ?>
        </td>
        <td class="dr-state">
          <span class="dr-st dr-st--<?= e($i['state']) ?>"><?= e(Drafts::stateLabel($i['state'])) ?></span>
          <?php if ($i['stale']): ?><span class="dr-st dr-st--stale" title="<?= e(__('Seit über {n} Tagen nicht geändert', ['n' => Drafts::STALE_DAYS])) ?>"><?= e(__('vergessen?')) ?></span><?php endif; ?>
        </td>
        <td class="rv-when"><?= $ago($i['updated_at']) ?><?php if ($i['by'] !== ''): ?><span class="rv-src__client"><?= e(__('von {name}', ['name' => $i['by']])) ?></span><?php endif; ?></td>
        <td class="dr-note">
          <?php if ($i['note'] !== '' || $i['assignee'] !== ''): ?>
          <p class="dr-note__text"><?php if ($i['assignee'] !== ''): ?><span class="dr-who"><?= icon('user') ?> <?= e($i['assignee']) ?></span><?php endif; ?><?= $i['note'] !== '' ? ' ' . e($i['note']) : '' ?></p>
          <?php endif; ?>
          <details class="dr-note__edit">
            <summary><?= e($i['note'] !== '' || $i['assignee'] !== '' ? __('Notiz ändern') : __('Notiz hinzufügen')) ?><span class="sr-only"> – <?= e($i['title']) ?></span></summary>
            <div class="dr-note__form">
              <div class="f"><label for="dr-note-<?= $n ?>"><?= e(__('Notiz')) ?></label>
                <textarea id="dr-note-<?= $n ?>" name="note" form="dr-nf-<?= $n ?>" rows="2" maxlength="500" placeholder="<?= e(__('z. B. wartet auf Freigabe durch …')) ?>"><?= e($i['note']) ?></textarea></div>
              <div class="f"><label for="dr-as-<?= $n ?>"><?= e(__('Zuständig')) ?></label>
                <select id="dr-as-<?= $n ?>" name="assignee" form="dr-nf-<?= $n ?>"><option value=""><?= e(__('– niemand –')) ?></option>
                  <?php foreach ($users as $uid => $uname): ?><option value="<?= (int) $uid ?>"<?= $i['assignee_id'] === $uid ? ' selected' : '' ?>><?= e($uname) ?></option><?php endforeach; ?></select></div>
              <button class="adm-btn adm-btn--small" type="submit" form="dr-nf-<?= $n ?>"><?= e(__('Notiz speichern')) ?></button>
            </div>
          </details>
        </td>
        <td class="dr-actions">
          <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url($i['edit'])) ?>"><?= e($i['type'] === 'page' ? __('Im Editor öffnen') : __('Bearbeiten')) ?><span class="sr-only"> – <?= e($i['title']) ?></span></a>
          <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url($showUrl($i))) ?>"><?= e(__('Unterschiede')) ?><span class="sr-only"> – <?= e($i['title']) ?></span></a>
          <?php if ($i['can_publish']): ?><button class="adm-btn adm-btn--small" type="submit" formaction="<?= e(url('/admin/entwuerfe/veroeffentlichen')) ?>" name="one" value="<?= e($i['key']) ?>"
            data-confirm="<?= e(__('„{title}“ veröffentlichen?', ['title' => $i['title']])) ?>" data-confirm-ok="<?= e(__('Veröffentlichen')) ?>"><?= e(__('Veröffentlichen')) ?><span class="sr-only"> – <?= e($i['title']) ?></span></button><?php endif; ?>
          <?php if ($i['can_discard']): ?><button class="adm-btn adm-btn--small adm-btn--danger-text" type="submit" formaction="<?= e(url('/admin/entwuerfe/verwerfen')) ?>" name="one" value="<?= e($i['key']) ?>"
            data-confirm="<?= e($i['type'] === 'page' ? __('Entwurf von „{title}“ verwerfen? Die Seite geht zurück auf die veröffentlichte Fassung; der Entwurf bleibt unter „Versionen“ gesichert.', ['title' => $i['title']]) : __('„{title}“ verwerfen? Der Eintrag wird gelöscht und unter „Zuletzt verworfen“ gesichert.', ['title' => $i['title']])) ?>"
            data-confirm-ok="<?= e(__('Verwerfen')) ?>"><?= e(__('Verwerfen')) ?><span class="sr-only"> – <?= e($i['title']) ?></span></button>
          <?php elseif ($i['type'] === 'page' && $i['state'] === 'new'): ?><span class="dr-actions__note"><?= e(__('Neue Seite – Löschen unter „Seiten“')) ?></span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</form>
<?php // Notiz-Formulare außerhalb der Tabelle (Formulare dürfen nicht verschachtelt sein); die Felder verweisen mit form="…" hierher ?>
<?php foreach ($items as $n => $i): ?>
<form id="dr-nf-<?= $n ?>" method="post" action="<?= e(url('/admin/entwuerfe/notiz')) ?>" hidden><?= csrf_field() ?><input type="hidden" name="item" value="<?= e($i['key']) ?>"><input type="hidden" name="back" value="<?= e($back) ?>"></form>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($discards): ?>
<section class="adm-card dr-discards" aria-labelledby="dr-disc-h">
  <h2 id="dr-disc-h"><?= e(__('Zuletzt verworfen')) ?></h2>
  <p class="adm-muted"><?= e(__('Verworfene Einträge bleiben 90 Tage gesichert und lassen sich als Entwurf wiederherstellen. Verworfene Seiten-Entwürfe finden Sie in der Seite unter „Versionen“.')) ?></p>
  <ul class="adm-list">
    <?php foreach ($discards as $d): ?>
    <li><span class="dr-discards__what"><span class="rv-row__type"><?= e($d['table']['singular'] ?: $d['table']['name']) ?></span> <b><?= e($d['label'] ?: '#' . (int) $d['entry_id']) ?></b>
      <small class="adm-muted"><?= $ago($d['created_at']) ?><?= $d['by'] !== '' ? ' · ' . e(__('von {name}', ['name' => $d['by']])) : '' ?></small></span>
      <form method="post" action="<?= e(url('/admin/entwuerfe/wiederherstellen/' . (int) $d['id'])) ?>"><?= csrf_field() ?>
        <button class="adm-btn adm-btn--small adm-btn--ghost" type="submit"><?= e(__('Wiederherstellen')) ?><span class="sr-only"> – <?= e($d['label']) ?></span></button></form></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
