<?php
/**
 * Mitteilungen → Verlauf: alle Nachrichten (von Hand, automatisch, Tests) mit Status und Zählern; rechts die gewählte Nachricht
 * mit Inhalt, Empfängern, Zustellung, häufigsten Fehlern und Aktionen (Abbrechen, als Entwurf duplizieren).
 * @var string $folder  @var array $rows  @var ?array $sel  @var array $detail  @var bool $canSend
 */
use Core\Push\Compose;

$b = \Core\Http\Controllers\Admin\MessagesController::BASE;
$titles = ['alle' => __('Alle Mitteilungen'), 'manuell' => __('Von Hand'), 'automatisch' => __('Automatisch'), 'geplant' => __('Geplant')];
$stateLabel = ['scheduled' => __('geplant'), 'canceled' => __('abgebrochen'), 'sending' => __('wird gesendet'), 'sent' => __('gesendet')];
$stateTone = ['scheduled' => 'info', 'canceled' => 'muted', 'sending' => 'warn', 'sent' => 'ok'];
$q = fn(array $p) => url($b) . '?' . http_build_query(array_filter(['ordner' => $folder !== 'alle' ? $folder : null] + $p));
$when = fn(array $m) => $m['status'] === 'scheduled' && $m['scheduled_at'] ? fmt()->datetime((int) $m['scheduled_at']) : fmt()->relative((string) $m['created_at']);
?>
<h1 class="adm-sr"><?= e(__('Mitteilungen')) ?></h1>
<div class="pm pm--split<?= $sel ? ' is-reading' : '' ?>">
  <section class="pm-col pm-col--list" aria-labelledby="pm-list-h">
    <div class="pm-bar"><div class="pm-bar__title"><h2 id="pm-list-h"><?= e($titles[$folder]) ?></h2><small><?= e(count($rows) === 1 ? __('1 Mitteilung') : __('{n} Mitteilungen', ['n' => count($rows)])) ?></small></div>
      <?php if ($canSend): ?><a class="adm-btn adm-btn--small adm-btn--primary" href="<?= e(url($b . '/neu')) ?>"><?= icon('pencil-simple') ?> <span><?= e(__('Neu')) ?></span></a><?php endif; ?></div>
    <ul class="pm-list" role="list">
      <?php if (!$rows): ?><li class="pm-empty"><?= e($folder === 'geplant' ? __('Keine geplanten Mitteilungen.') : __('Noch keine Mitteilungen.')) ?></li><?php endif; ?>
      <?php foreach ($rows as $m): $st = Compose::state($m); $p = json_decode((string) $m['payload_json'], true) ?: []; $on = $sel && (int) $sel['id'] === (int) $m['id']; ?>
      <li><a class="pm-row<?= $on ? ' is-active' : '' ?>" href="<?= e($q(['id' => (int) $m['id']])) ?>"<?= $on ? ' aria-current="true"' : '' ?>>
        <span class="pm-row__ico pm-row__ico--<?= e((string) ($m['source'] ?? 'auto')) ?>" aria-hidden="true"><?= icon(($m['source'] ?? '') === 'manual' ? 'paper-plane-tilt' : (($m['source'] ?? '') === 'test' ? 'check' : 'lightning')) ?></span>
        <span class="pm-row__main"><span class="pm-row__title"><?= e((string) ($m['title'] ?: ($p['title'] ?? '–'))) ?></span>
          <span class="pm-row__sub"><?= e(Compose::summary($m)) ?></span></span>
        <span class="pm-row__meta"><span class="pm-row__when"><?= e($when($m)) ?></span>
          <?php if ($st === 'sent'): ?><span class="pm-row__n" title="<?= e(__('zugestellt / Empfänger')) ?>"><?= (int) $m['sent'] ?>/<?= (int) $m['recipients'] ?></span><?php else: ?>
          <span class="adm-badge adm-badge--<?= e($stateTone[$st]) ?>"><?= e($stateLabel[$st]) ?></span><?php endif; ?></span>
      </a></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <section class="pm-col pm-col--pane" aria-labelledby="pm-pane-h">
    <?php if (!$sel): ?>
    <div class="pm-blank"><?= icon('bell-ringing') ?><p id="pm-pane-h"><?= e(__('Wählen Sie links eine Mitteilung, um Inhalt, Empfänger und Zustellung zu sehen.')) ?></p></div>
    <?php else: $st = Compose::state($sel); $p = json_decode((string) $sel['payload_json'], true) ?: []; $lost = (int) $sel['failed'] + (int) $sel['gone'] + (int) $sel['expired']; ?>
    <div class="pm-bar"><a class="pm-back" href="<?= e($q([])) ?>"><span aria-hidden="true">‹</span> <?= e(__('Zurück')) ?></a>
      <div class="pm-bar__title"><h2 id="pm-pane-h"><?= e((string) ($p['title'] ?? $sel['title'])) ?></h2>
        <small><?= e(match ((string) ($sel['source'] ?? 'auto')) { 'manual' => __('Von Hand'), 'test' => __('Testnachricht'), default => __('Automatisch') }) ?> · <?= e($stateLabel[$st]) ?></small></div>
      <?php if ($canSend): ?><a class="adm-btn adm-btn--small" href="<?= e(url($b . '/neu?von=' . (int) $sel['id'])) ?>"><?= icon('copy') ?> <span><?= e(__('Als Entwurf kopieren')) ?></span></a><?php endif; ?></div>
    <div class="pm-scroll pm-detail">
      <div class="pm-note pm-note--desk pm-note--static">
        <img class="pm-note__icon" src="<?= e((string) ($p['icon'] ?? \Core\Push\Push::icon())) ?>" alt="" width="40" height="40">
        <div class="pm-note__text"><p class="pm-note__title"><?= e((string) ($p['title'] ?? '')) ?></p><p class="pm-note__body"><?= e((string) ($p['body'] ?? '')) ?></p></div>
        <?php if (!empty($p['image'])): ?><img class="pm-note__image" src="<?= e((string) $p['image']) ?>" alt=""><?php endif; ?>
      </div>
      <section class="set-group">
        <h3 class="set-group__title"><?= e(__('Zustellung')) ?></h3>
        <div class="pm-stats">
          <div><strong><?= (int) $sel['recipients'] ?></strong><span><?= e(__('Geräte')) ?></span></div>
          <div class="is-ok"><strong><?= (int) $sel['sent'] ?></strong><span><?= e(__('zugestellt')) ?></span></div>
          <div><strong><?= (int) $sel['failed'] ?></strong><span><?= e(__('fehlgeschlagen')) ?></span></div>
          <div><strong><?= (int) $sel['gone'] + (int) $sel['expired'] ?></strong><span><?= e(__('abgelaufen')) ?></span></div>
          <?php if ($detail['queued']): ?><div class="is-warn"><strong><?= (int) $detail['queued'] ?></strong><span><?= e(__('wartend')) ?></span></div><?php endif; ?>
        </div>
        <?php if ((int) $sel['recipients'] > 0 && $st === 'sent'): ?><p class="set-group__note"><?= e(__('Zustellquote {n} %. „Zugestellt“ heißt: vom Push-Dienst angenommen – ob jemand die Mitteilung gesehen hat, erfahren wir nicht (kein Tracking).', ['n' => (int) round((int) $sel['sent'] / max(1, (int) $sel['sent'] + $lost) * 100)])) ?></p><?php endif; ?>
      </section>
      <section class="set-group">
        <h3 class="set-group__title"><?= e(__('Angaben')) ?></h3>
        <div class="set-list">
          <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Empfänger')) ?></span></div><div class="set-row__ctl"><?= e(Compose::summary($sel)) ?></div></div>
          <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e($st === 'scheduled' ? __('Geplant für') : __('Gesendet')) ?></span></div>
            <div class="set-row__ctl"><?= e($st === 'scheduled' ? fmt()->datetime((int) $sel['scheduled_at']) : fmt()->datetime((string) $sel['created_at'])) ?></div></div>
          <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Ziel')) ?></span></div><div class="set-row__ctl"><code><?= e((string) ($p['url'] ?? '')) ?></code></div></div>
          <?php if ($detail['author']): ?><div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Von')) ?></span></div><div class="set-row__ctl"><?= e((string) ($detail['author']['name'] ?: $detail['author']['email'])) ?></div></div><?php endif; ?>
          <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Anlass')) ?></span></div><div class="set-row__ctl"><code><?= e((string) $sel['event']) ?></code></div></div>
        </div>
      </section>
      <?php if ($detail['errors']): ?>
      <section class="set-group">
        <h3 class="set-group__title"><?= e(__('Häufigste Fehler')) ?></h3>
        <div class="set-list">
          <?php foreach ($detail['errors'] as $er): ?><div class="set-row"><div class="set-row__main"><span class="set-row__sub"><?= e((string) $er['error']) ?></span></div><div class="set-row__ctl"><?= (int) $er['n'] ?>×</div></div><?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>
      <?php if ($st === 'scheduled' && $canSend): ?>
      <form class="set-actions" method="post" action="<?= e(url($b . '/' . (int) $sel['id'] . '/abbrechen')) ?>" data-confirm="<?= e(__('Geplante Mitteilung abbrechen? Sie wird dann nicht gesendet.')) ?>"><?= csrf_field() ?>
        <button class="adm-btn adm-btn--danger-text" type="submit"><?= e(__('Planung abbrechen')) ?></button></form>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </section>
</div>
