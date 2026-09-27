<?php
/**
 * [Platzhalter] und Redaktionsnotizen [# … #] (Core\Dashboard\Metrics::placeholders), nach Seite bzw. Eintrag gruppiert – Übersicht
 * „Was ist zu tun?“ und Seiteneinstellungen. Je Fundstelle: Art (Platzhalter/Notiz), Blocktyp, Text (lang → aufklappbar),
 * „Im Frontend bearbeiten“ (Editor springt zum Block und öffnet dessen Felder; Einträge: „Eintrag bearbeiten“ in der Verwaltung),
 * „In der Verwaltung“ (Seiteneinstellungen mit dieser Liste oben – einen Block-Editor gibt es nur im Frontend) und – nur bei
 * Platzhaltern – „Ist gewollt“. Notizen sind erledigt, wenn sie im Editor gelöscht werden.
 * @var list<array{id:int,title:string,text:string,block:string,type:string,label:string,url:string,kind:string,entry:string}> $hits
 * @var string $back      Rücksprung nach „Ist gewollt“ (/admin oder /admin/pages/{id})
 * @var bool   $showPage  Seitentitel als Gruppenüberschrift zeigen (Übersicht)
 * @var bool   $backend   Knopf „In der Verwaltung“ zeigen (Recht pages.manage, nicht auf der Seite selbst)
 * @var string $hTag      Überschriften-Ebene der Seitengruppen
 */
use Core\Dashboard\Metrics;

$showPage ??= true;
$backend ??= false;
$hTag = in_array($hTag ?? 'h3', ['h3', 'h4'], true) ? $hTag ?? 'h3' : 'h3';
$groups = [];
foreach ($hits as $h) $groups[(($h['entry'] ?? '') !== '' ? 'e:' . $h['entry'] . ':' : 'p:') . $h['id']][] = $h;
$canOk = can('pages.edit') || can('settings.edit') || can('system.manage');
?>
<div class="ph-list">
  <?php foreach ($groups as $pid => $list): $page = $list[0]; ?>
  <div class="ph-page">
    <?php if ($showPage): ?>
    <<?= $hTag ?> class="ph-page__title"><?= e($page['title']) ?> <span class="ph-page__n"><?= e(count($list) === 1 ? __('1 Fundstelle') : __('{n} Fundstellen', ['n' => count($list)])) ?></span></<?= $hTag ?>>
    <?php endif; ?>
    <ul class="ph-hits" role="list">
      <?php foreach ($list as $h): $note = ($h['kind'] ?? 'placeholder') === 'note'; $entry = ($h['entry'] ?? '') !== '';
          $ctx = $entry ? __('Notiz „{text}“ im Eintrag „{page}“', ['text' => mb_strimwidth($h['text'], 0, 60, '…'), 'page' => $h['title']])
              : __('{text} im Block „{block}“ auf „{page}“', ['text' => $note ? __('Notiz „{text}“', ['text' => mb_strimwidth($h['text'], 0, 60, '…')]) : mb_strimwidth($h['text'], 0, 60, '…]'), 'block' => $h['label'], 'page' => $h['title']]); ?>
      <li class="ph-hit<?= $note ? ' ph-hit--note' : '' ?>">
        <p class="ph-hit__type"><span class="ph-kind ph-kind--<?= $note ? 'note' : 'placeholder' ?>"><?= e($note ? __('Notiz') : __('Platzhalter')) ?></span> <?= icon($entry ? 'table' : 'squares-four') ?> <span><?= e($entry ? __('Feld') : __('Block')) ?>: <?= e($entry ? $h['block'] : ($h['label'] !== '' ? $h['label'] : __('unbekannt'))) ?></span></p>
        <?php if (mb_strlen($h['text']) > 110): ?>
        <details class="ph-hit__text ph-hit__text--long">
          <summary><span class="ph-hit__q"><?= e(mb_strimwidth($h['text'], 0, 110, '…')) ?></span> <span class="ph-hit__more"><?= e(__('ganz anzeigen')) ?></span></summary>
          <p class="ph-hit__q"><?= e($h['text']) ?></p>
        </details>
        <?php else: ?>
        <p class="ph-hit__text"><span class="ph-hit__q"><?= e($h['text']) ?></span></p>
        <?php endif; ?>
        <div class="ph-hit__actions">
          <?php if ($entry && $h['url'] !== ''): ?>
          <a class="adm-btn adm-btn--small adm-btn--primary" href="<?= e(Metrics::placeholderEditUrl($h)) ?>"><?= icon('pencil-simple') ?> <?= e(__('Eintrag bearbeiten')) ?><span class="sr-only">: <?= e($ctx) ?></span></a>
          <?php elseif ($h['url'] !== '' && can('pages.edit')): ?>
          <a class="adm-btn adm-btn--small adm-btn--primary" href="<?= e(Metrics::placeholderEditUrl($h)) ?>"><?= icon('pencil-simple') ?> <?= e(__('Im Frontend bearbeiten')) ?><span class="sr-only">: <?= e($ctx) ?></span></a>
          <?php endif; ?>
          <?php if ($backend && !$entry && can('pages.manage')): ?>
          <a class="adm-btn adm-btn--small" href="<?= e(url('/admin/pages/' . $h['id'] . '#platzhalter')) ?>"><?= e(__('In der Verwaltung')) ?><span class="sr-only">: <?= e($ctx) ?></span></a>
          <?php endif; ?>
          <?php if ($canOk && !$note): ?>
          <form method="post" action="<?= e(url('/admin/api/dashboard/placeholder-ok')) ?>" class="ph-hit__ok"><?= csrf_field() ?>
            <input type="hidden" name="text" value="<?= e($h['text']) ?>"><input type="hidden" name="back" value="<?= e($back) ?>">
            <button type="submit" class="adm-btn adm-btn--small adm-btn--ghost" title="<?= e(__('{text} ist kein Platzhalter, sondern Absicht – auf keiner Seite mehr melden', ['text' => mb_strimwidth($h['text'], 0, 160, '…]')])) ?>"><?= e(__('Ist gewollt')) ?><span class="sr-only">: <?= e($ctx) ?></span></button>
          </form>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endforeach; ?>
</div>
