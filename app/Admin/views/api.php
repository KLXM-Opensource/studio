<?php
use Core\Api\Tokens;
$mcpUrl = absolute_url('/mcp');
$apiUrl = absolute_url('/api/v1');
$tok = $newToken ?: 'cms_IHR_TOKEN';
$err = fn($k) => isset($errors[$k]) ? '<p class="f-error">' . e($errors[$k]) . '</p>' : '';
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url(\Core\AdminPages::HUB)) ?>"><?= e(__('Einstellungen der Funktionen')) ?></a></p><h1>API &amp; MCP</h1>
    <p class="adm-muted">Schnittstellen für Automatisierungen und KI-Assistenten. REST-API unter <code><?= e($apiUrl) ?></code>, MCP-Server unter <code><?= e($mcpUrl) ?></code>.
      <a href="<?= e(url('/admin/hilfe/technik#api')) ?>">Technische Dokumentation →</a></p></div>
</header>

<?php if ($newToken): ?>
<section class="adm-card adm-card--secret" role="alert">
  <h2>Neuer Token – nur jetzt sichtbar</h2>
  <p>Kopieren Sie den Token jetzt und bewahren Sie ihn sicher auf (Passwortmanager). Gespeichert wird nur ein Hash.</p>
  <div class="adm-secret"><code id="new-token"><?= e($newToken) ?></code><button type="button" class="adm-btn adm-btn--small" data-copy="#new-token">Kopieren</button></div>
</section>
<?php endif; ?>

<div class="adm-grid2 adm-grid2--wide">
  <section class="adm-card adm-card--flush">
    <table class="adm-table">
      <thead><tr><th scope="col">Name</th><th scope="col">Berechtigung</th><th scope="col">Zuletzt genutzt</th><th scope="col"><span class="sr-only">Aktionen</span></th></tr></thead>
      <tbody>
      <?php foreach ($tokens as $t): $expired = $t['expires_at'] && $t['expires_at'] < date('Y-m-d'); ?>
        <tr>
          <td><strong><?= e($t['name']) ?></strong><br><span class="adm-muted"><code><?= e($t['prefix']) ?>…</code> · angelegt <?= e(date('d.m.Y', strtotime($t['created_at']))) ?><?= $t['email'] ? ' von ' . e($t['email']) : '' ?></span>
            <?php if (!empty($t['mcp_client'])): ?><br><span class="adm-muted"><?= e(__('Client: {name}', ['name' => $t['mcp_client']])) ?></span><?php endif; ?></td>
          <td><span class="adm-badge<?= $t['scope'] === 'write' ? ' adm-badge--warn' : '' ?>"><?= e(Tokens::SCOPES[$t['scope']] ?? $t['scope']) ?></span>
            <?php if ($t['expires_at']): ?><br><span class="adm-muted"><?= $expired ? 'abgelaufen' : 'bis ' . e(date('d.m.Y', strtotime($t['expires_at']))) ?></span><?php endif; ?>
            <?php if ($t['scope'] === 'write' && \Core\Review\Queue::enabled()): $rm = ($t['review_mode'] ?? 'direct') === 'review' ? 'review' : 'direct'; // Prüf-Ebene (Core\Review) ?>
            <form class="rv-tokmode" method="post" action="<?= e(url('/admin/api-tokens/' . $t['id'] . '/mode')) ?>"><?= csrf_field() ?>
              <label class="rv-tokmode__l" for="rm-<?= (int) $t['id'] ?>"><?= e(__('Änderungen')) ?><span class="sr-only"> <?= e(__('von „{name}“', ['name' => $t['name']])) ?></span></label>
              <select id="rm-<?= (int) $t['id'] ?>" name="review_mode"><?php foreach (Tokens::MODES as $k => $l): ?><option value="<?= $k ?>"<?= $rm === $k ? ' selected' : '' ?>><?= e(__($l)) ?></option><?php endforeach; ?></select>
              <button class="adm-btn adm-btn--small adm-btn--ghost" type="submit"><?= e(__('Ändern')) ?></button></form>
            <?php endif; ?></td>
          <td class="adm-muted"><?= $t['last_used_at'] ? e(date('d.m.Y H:i', strtotime($t['last_used_at']))) : 'nie' ?></td>
          <td class="adm-actions"><form method="post" action="<?= e(url('/admin/api-tokens/' . $t['id'] . '/delete')) ?>" data-confirm="Token „<?= e($t['name']) ?>“ widerrufen?"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text">Widerrufen</button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$tokens): ?><tr><td colspan="4" class="adm-muted">Noch keine Tokens.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?php if (\Core\Review\Queue::enabled()): ?>
    <p class="adm-muted rv-tokhint"><?= e(__('„Zur Freigabe“: Änderungen werden nicht ausgeführt, sondern unter {brand} → Eingereicht zur Prüfung vorgelegt (API: HTTP 202, MCP: pending_review). Bestehende Tokens bleiben auf „Direkt übernehmen“, damit laufende Anbindungen nicht brechen – jeder Weg wird protokolliert.', ['brand' => \Core\AI\Assist::brand()])) ?>
      <?php if (\Core\Review\Queue::canReview()): ?><a href="<?= e(url('/admin/ai/eingereicht')) ?>"><?= e(__('Zu „Eingereicht“ →')) ?></a><?php endif; ?></p>
    <?php endif; ?>
  </section>

  <form class="adm-card" method="post" action="<?= e(url('/admin/api-tokens')) ?>" novalidate>
    <?= csrf_field() ?>
    <h2>Neuen Token erzeugen</h2>
    <div class="f<?= isset($errors['name']) ? ' f--error' : '' ?>"><label for="t-name">Name <span class="req">*</span></label><input id="t-name" name="name" placeholder="z. B. Claude – Redaktion" maxlength="120"><?= $err('name') ?></div>
    <div class="f"><label for="t-scope">Berechtigung</label>
      <select id="t-scope" name="scope"><?php foreach (Tokens::SCOPES as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select>
      <p class="f-help">„Nur lesen“ genügt für Auswertungen und Prüfungen. „Lesen und ändern“ erlaubt Inhalte, <?= e(app()->theme->settingsTitle()) ?> und Veröffentlichen.</p></div>
    <?php if (\Core\Review\Queue::enabled()): ?>
    <div class="f"><label for="t-mode"><?= e(__('Änderungen')) ?></label>
      <select id="t-mode" name="review_mode" aria-describedby="t-mode-help"><?php foreach (array_reverse(Tokens::MODES, true) as $k => $l): ?><option value="<?= $k ?>"><?= e(__($l)) ?></option><?php endforeach; ?></select>
      <p class="f-help" id="t-mode-help"><?= e(__('Empfohlen für KI-Assistenten (MCP): „Zur Freigabe“ – ein Mensch prüft jede Änderung vor dem Übernehmen. Gilt nur für Tokens mit „Lesen und ändern“.')) ?></p></div>
    <?php endif; ?>
    <div class="f<?= isset($errors['expires']) ? ' f--error' : '' ?>"><label for="t-exp">Gültig bis (optional)</label><input id="t-exp" name="expires" type="date"><?= $err('expires') ?></div>
    <button class="adm-btn adm-btn--primary" type="submit">Token erzeugen</button>
  </form>
</div>

<section class="adm-card">
  <h2>MCP einrichten – KI-Assistenten verbinden</h2>
  <p class="adm-muted">Der MCP-Server erlaubt Assistenten wie Claude, die Website in natürlicher Sprache zu pflegen („Trag bitte Urlaub vom 21. bis 25.10. ein“). Änderungen an Seiten landen als Entwurf.</p>
  <div class="adm-snippets">
    <div>
      <h3>Claude Code (Terminal)</h3>
      <pre id="snip-cc"><code>claude mcp add --transport http klxm-studio <?= e($mcpUrl) ?> \
  --header "Authorization: Bearer <?= e($tok) ?>"</code></pre>
      <button type="button" class="adm-btn adm-btn--small" data-copy="#snip-cc">Kopieren</button>
    </div>
    <div>
      <h3>Claude Desktop · <code>claude_desktop_config.json</code></h3>
      <pre id="snip-cd"><code>{
  "mcpServers": {
    "klxm-studio": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "<?= e($mcpUrl) ?>",
               "--header", "Authorization:${CMS_TOKEN}"],
      "env": { "CMS_TOKEN": "Bearer <?= e($tok) ?>" }
    }
  }
}</code></pre>
      <button type="button" class="adm-btn adm-btn--small" data-copy="#snip-cd">Kopieren</button>
    </div>
    <div>
      <h3>REST-API testen</h3>
      <pre id="snip-curl"><code>curl -H "Authorization: Bearer <?= e($tok) ?>" \
  <?= e($apiUrl) ?>/hours</code></pre>
      <button type="button" class="adm-btn adm-btn--small" data-copy="#snip-curl">Kopieren</button>
      <p class="f-help">Maschinenlesbare Beschreibung: <a href="<?= e($apiUrl) ?>/openapi.json" target="_blank" rel="noopener">openapi.json</a></p>
    </div>
  </div>
</section>
