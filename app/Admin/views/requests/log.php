<?php
/**
 * Protokoll der Anfragen (inbox_log): wer hat wann welche Einträge entschlüsselt, geändert oder gelöscht – ohne Inhalte.
 * @var array $tables  @var array $events  @var array $users  @var string $table  @var int $userId  @var int $months
 */
$names = array_column($tables, 'name', 'handle');
$actions = ['decrypt' => __('Entschlüsselt'), 'status' => __('Status geändert'), 'assign' => __('Zugewiesen'), 'delete' => __('Gelöscht'), 'purge' => __('Automatisch gelöscht')];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/requests')) ?>"><?= e(__('Anfragen')) ?></a></p><h1><?= e(__('Protokoll')) ?></h1>
    <p class="adm-muted"><?= e(__('Die letzten 200 Ereignisse: Entschlüsseln, Status, Zuweisung, Löschen. Es werden nie Inhalte protokolliert, die IP-Adresse nur als Kürzel (Hash). Aufbewahrung: {months} Monate (Grundeinstellungen → Spamschutz).', ['months' => $months])) ?></p></div>
</header>

<form class="adm-card adm-row" method="get" action="<?= e(url('/admin/requests/log')) ?>">
  <div class="f"><label for="lg-t"><?= e(__('Tabelle')) ?></label>
    <select id="lg-t" name="table"><option value=""><?= e(__('Alle')) ?></option>
      <?php foreach ($tables as $x): ?><option value="<?= e($x['handle']) ?>"<?= $x['handle'] === $table ? ' selected' : '' ?>><?= e($x['name']) ?></option><?php endforeach; ?>
    </select></div>
  <div class="f"><label for="lg-u"><?= e(__('Benutzer')) ?></label>
    <select id="lg-u" name="user"><option value=""><?= e(__('Alle')) ?></option>
      <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= (int) $u['id'] === $userId ? ' selected' : '' ?>><?= e($u['name'] ?: $u['email']) ?></option><?php endforeach; ?>
    </select></div>
  <button class="adm-btn" type="submit"><?= e(__('Filtern')) ?></button>
</form>

<div class="adm-card adm-card--flush">
  <table class="adm-table">
    <thead><tr><th><?= e(__('Zeitpunkt')) ?></th><th><?= e(__('Aktion')) ?></th><th><?= e(__('Tabelle')) ?></th><th><?= e(__('Einträge')) ?></th><th><?= e(__('Benutzer')) ?></th><th><?= e(__('Detail')) ?></th><th><?= e(__('IP (Hash)')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($events as $ev): $ids = json_decode((string) $ev['entry_ids'], true) ?: []; ?>
      <tr>
        <td><?= e(date('d.m.Y H:i:s', strtotime((string) $ev['created_at']))) ?></td>
        <td><?= e($actions[$ev['action']] ?? $ev['action']) ?></td>
        <td><?= e($names[$ev['table_handle']] ?? $ev['table_handle']) ?></td>
        <td><?= $ids ? e(count($ids) > 8 ? '#' . implode(', #', array_slice($ids, 0, 8)) . ' … (' . count($ids) . ')' : '#' . implode(', #', $ids)) : '–' ?></td>
        <td><?= e($ev['user_id'] ? ($ev['user_name'] ?: $ev['user_email'] ?: '#' . $ev['user_id']) : __('System / API')) ?></td>
        <td><?php
          // Lesbare Details: Status-Kürzel und zugewiesene Person statt interner Werte
          $det = (string) $ev['detail'];
          $det = match (true) {
              $det === 'neu' => __('Neu'), $det === 'in_bearbeitung' => __('In Bearbeitung'), $det === 'erledigt' => __('Erledigt'),
              $det === 'user:0' || $det === 'user:' => __('niemand'),
              str_starts_with($det, 'user:') => (string) (app()->db->fetchValue('SELECT COALESCE(NULLIF(name, \'\'), email) FROM users WHERE id = ?', [(int) substr($det, 5)]) ?: $det),
              default => $det,
          };
          echo e($det); ?></td>
        <td><code><?= e((string) $ev['ip_hash']) ?></code></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$events): ?><tr><td colspan="7" class="adm-muted"><?= e(__('Noch keine Ereignisse.')) ?></td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
