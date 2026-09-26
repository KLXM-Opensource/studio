<?php
/** Bereich „KLXM Ai“ → Verlauf: eigene KI-Vorschläge der letzten 90 Tage – nur Metadaten, keine Inhalte. @var array $rows */
use Core\AI\Assist;

$kinds = ['text' => __('Text-Assistent'), 'translate' => __('Übersetzen'), 'seo' => __('SEO'), 'alt' => __('Alt-Text'), 'summary' => __('Teaser'),
    'support' => __('Support-Antwort'), 'kb' => __('Wissensartikel'), 'schema' => __('Tabellen-Felder'), 'write' => __('Texte'), 'assistant' => __('Assistent')];
$status = ['suggested' => [__('Vorschlag'), 'info'], 'applied' => [__('Übernommen'), 'ok'], 'failed' => [__('Fehler'), 'error']];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/ai')) ?>"><?= e(Assist::brand()) ?></a></p><h1><?= e(__('Verlauf')) ?></h1>
    <p class="adm-muted"><?= e(__('Ihre KI-Vorschläge der letzten 90 Tage und ob sie übernommen wurden. Gespeichert werden nur Zeit, Bereich, Ziel, Modell und Dauer – keine Texte oder Bilder.')) ?></p></div>
</header>
<section class="adm-card">
  <?php if (!$rows): ?><p class="adm-muted"><?= e(__('Noch keine Einträge.')) ?></p>
  <?php else: ?>
  <div class="kia-tablewrap"><table class="adm-table">
    <thead><tr><th scope="col"><?= e(__('Zeit')) ?></th><th scope="col"><?= e(__('Bereich')) ?></th><th scope="col"><?= e(__('Ziel')) ?></th><th scope="col"><?= e(__('Status')) ?></th><th scope="col"><?= e(__('Modell')) ?></th><th scope="col" class="num"><?= e(__('Dauer')) ?></th></tr></thead>
    <tbody><?php foreach ($rows as $r): [$sl, $sc] = $status[$r['status']] ?? [$r['status'], 'info']; ?>
      <tr><td><?= e(date('d.m.Y H:i', strtotime($r['ts']))) ?></td><td><?= e($kinds[$r['kind']] ?? $r['kind']) ?><?= $r['action'] !== '' ? ' <small class="adm-muted">· ' . e($r['action']) . '</small>' : '' ?></td>
        <td><?= e($r['target'] ?: '–') ?></td><td><span class="kia-chk kia-chk--<?= e($sc) ?>"><?= e($sl) ?></span></td><td><?= e($r['model'] ?: '–') ?></td>
        <td class="num"><?= $r['ms'] ? e(number_format($r['ms'] / 1000, 1, ',', '.') . ' s') : '–' ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
