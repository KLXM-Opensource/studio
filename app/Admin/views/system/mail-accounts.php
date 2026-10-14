<?php
/**
 * Grundeinstellungen → E-Mail-Konten (MailAccountsController, Core\Mail\Accounts): Konten, Verwendung je Zweck, Netzwerk-Konto.
 * @var array $accounts  @var array $extra  @var array $routes  @var array $purposes  @var ?array $network  @var bool $canNetwork
 * @var array $fields  @var array $netFields  @var array $errors  @var ?array $draft  @var string $draftId  @var array $me
 */
use Core\Fields;
use Core\Mail\Accounts;

$base = '/admin/system/mail';
$state = fn(array $a) => Accounts::ready($a) ? ['ok', __('bereit')] : ['warn', __('nicht eingerichtet')];
$noPort = fn(array $a) => ['port' => !empty($a['port']) ? $a['port'] : ''] + $a;   // leerer Port statt „0“ (dann üblicher Port)
$values = fn(string $id, array $cur) => $noPort($draftId === $id && $draft !== null ? $draft : $cur);
$testForm = function (string $id) use ($base, $me): string {
    return '<form method="post" action="' . e(url($base . '/test/' . $id)) . '" class="mk-test">' . csrf_field()
        . '<label class="adm-sr" for="mk-to-' . e($id) . '">' . e(__('Empfänger der Testnachricht')) . '</label>'
        . '<input type="email" id="mk-to-' . e($id) . '" name="to" placeholder="' . e((string) $me['email']) . '">'
        . '<button class="adm-btn adm-btn--small">' . e(__('Testnachricht')) . '</button></form>';
};
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/system#mail')) ?>"><?= e(__('Grundeinstellungen')) ?></a></p><h1><?= e(__('E-Mail-Konten')) ?></h1>
    <p class="adm-muted"><?= e(__('Über welches Konto die Website was versendet – z. B. Konto-Mails über den eigenen Server, Newsletter über einen Dienst wie Mailjet. Ohne weitere Konten gilt überall „Standard“.')) ?></p></div>
</header>

<div class="set-page mk-page">

<section class="set-group" id="konten" aria-labelledby="mk-h">
  <h2 id="mk-h" class="set-group__title"><?= e(__('Konten')) ?></h2>
  <ul class="mk-list" role="list">
    <?php foreach ($accounts as $id => $a): [$cls, $st] = $state($a); ?>
    <li class="mk-item" id="konto-<?= e($id) ?>">
      <div class="mk-item__head">
        <strong><?= e($a['label']) ?></strong>
        <span class="adm-badge adm-badge--<?= $cls ?>"><?= e($st) ?></span>
        <?php if ($id === 'network'): ?><span class="adm-badge"><?= e(__('für alle Websites')) ?></span><?php endif; ?>
        <span class="adm-muted mk-item__desc"><?= e(Accounts::describe($a)) ?></span>
      </div>
      <div class="mk-item__act">
        <?php if ($id === 'standard'): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/system#mail')) ?>"><?= e(__('Bearbeiten')) ?></a><?php endif; ?>
        <?= $testForm($id) ?>
      </div>
      <?php if (isset($extra[$id])): ?>
      <details class="mk-edit"<?= $draftId === $id ? ' open' : '' ?>>
        <summary><?= e(__('Bearbeiten')) ?></summary>
        <form method="post" action="<?= e(url($base . '/konto')) ?>" class="adm-fields" novalidate>
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($id) ?>">
          <?= Fields::renderForm($fields, $values($id, $a), $draftId === $id ? $errors : [], 'f') ?>
          <div class="adm-form-actions"><button class="adm-btn adm-btn--primary"><?= e(__('Speichern')) ?></button></div>
        </form>
        <form method="post" action="<?= e(url($base . '/konto/' . $id . '/loeschen')) ?>" data-confirm="<?= e(__('Konto „{name}“ entfernen? Zwecke, die es nutzen, versenden danach über „Standard“.', ['name' => $a['label']])) ?>">
          <?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Konto entfernen')) ?></button>
        </form>
      </details>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <details class="mk-edit mk-new"<?= $draftId === 'neu' ? ' open' : '' ?>>
    <summary class="adm-btn"><?= icon('plus') ?> <?= e(__('Konto hinzufügen')) ?></summary>
    <form method="post" action="<?= e(url($base . '/konto')) ?>" class="adm-fields" novalidate>
      <?= csrf_field() ?><input type="hidden" name="id" value="">
      <?= Fields::renderForm($fields, $draftId === 'neu' && $draft !== null ? $draft : ['transport' => 'smtp', 'verify_peer' => true, 'encryption' => 'tls'], $draftId === 'neu' ? $errors : [], 'f') ?>
      <div class="adm-form-actions"><button class="adm-btn adm-btn--primary"><?= e(__('Konto anlegen')) ?></button></div>
    </form>
  </details>
</section>

<section class="set-group" id="verwendung" aria-labelledby="mk-use-h">
  <h2 id="mk-use-h" class="set-group__title"><?= e(__('Verwendung')) ?></h2>
  <p class="adm-muted"><?= e(__('Welches Konto versendet was. Hat das gewählte Konto keinen gültigen Absender, versendet das Netzwerk-Konto (falls eingerichtet).')) ?></p>
  <form method="post" action="<?= e(url($base . '/verwendung')) ?>">
    <?= csrf_field() ?>
    <table class="adm-table mk-routes">
      <thead><tr><th><?= e(__('Zweck')) ?></th><th><?= e(__('Konto')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($purposes as $pk => $p): $sel = $routes[$pk] ?? 'standard'; ?>
        <tr>
          <td><label for="mk-r-<?= e(str_replace([':', '.'], '-', $pk)) ?>"><strong><?= e($p['label']) ?></strong></label><br><small class="adm-muted"><?= e($p['help']) ?><?= $p['owner'] !== 'core' ? ' · ' . e(__('Erweiterung {name}', ['name' => $p['owner']])) : '' ?></small></td>
          <td><select id="mk-r-<?= e(str_replace([':', '.'], '-', $pk)) ?>" name="route[<?= e($pk) ?>]">
            <?php foreach ($accounts as $id => $a): ?><option value="<?= e($id) ?>"<?= $sel === $id ? ' selected' : '' ?>><?= e($a['label']) ?></option><?php endforeach; ?>
          </select></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="adm-muted mk-note"><?= e(__('Mails zu Netzwerk-Konten (Einladungen, Passwort) gehen über das Netzwerk-Konto – solange keins eingerichtet ist, über „Standard“ dieser Website (Hinweis in der Prüfung).')) ?></p>
    <div class="adm-form-actions"><button class="adm-btn adm-btn--primary"><?= e(__('Verwendung speichern')) ?></button></div>
  </form>
</section>

<?php if ($canNetwork || $network): ?>
<p class="adm-muted mk-note"><?= e(__('Das E-Mail-Konto des Netzwerks gilt für die ganze Installation und wird im Netzwerk-Dashboard verwaltet.')) ?>
  <?php if ($canNetwork): ?><a href="<?= e(url('/admin/network#email')) ?>"><?= e(__('Zum Netzwerk-Dashboard')) ?></a><?php endif; ?></p>
<?php endif; ?>

</div>
