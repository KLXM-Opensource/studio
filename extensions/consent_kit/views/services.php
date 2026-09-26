<?php
/**
 * Dienste: Status, Tabelle mit Gruppen als Zwischenzeilen, Schalter, Domain-Matrix (ab zwei Domains), Reihenfolge.
 * @var array $services  @var array $domains  @var array $status
 */
use MyCms\Consent\Compiler;
use MyCms\Consent\Repository;

$multi = count($domains) > 1;
$byGroup = [];
foreach ($services as $s) $byGroup[$s['grp']][] = $s;
?>
<section class="adm-card ck-status" aria-label="<?= e(__('Status')) ?>">
  <ul>
    <?php foreach ($status as [$type, $msg]): ?><li class="ck-status__<?= e($type) ?>"><span class="ck-dot" aria-hidden="true"></span><?= e($msg) ?></li><?php endforeach; ?>
  </ul>
  <p><a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/consent/templates')) ?>"><?= e(__('Dienst hinzufügen')) ?></a>
    <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/consent/service/new')) ?>"><?= e(__('Eigener Dienst ohne Vorlage')) ?></a></p>
</section>

<?php if (!$services): ?>
<section class="adm-card"><p class="adm-muted"><?= e(__('Noch keine Dienste. Solange kein einwilligungspflichtiger Dienst aktiv ist, erhalten Besucher weder Hinweis noch Skript – wie ohne diese Erweiterung.')) ?></p></section>
<?php else: ?>
<form method="post" action="<?= e(url('/admin/consent/services/domains')) ?>" id="ck-matrix"><?= csrf_field() ?></form>
<section class="adm-card adm-card--flush">
  <table class="adm-table ck-services">
    <caption class="sr-only"><?= e(__('Dienste nach Gruppen')) ?></caption>
    <thead><tr>
      <th scope="col"><?= e(__('Status')) ?></th><th scope="col"><?= e(__('Dienst')) ?></th>
      <?php if ($multi): foreach ($domains as $label): ?><th scope="col" class="ck-dom"><?= e($label) ?></th><?php endforeach; endif; ?>
      <th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th>
    </tr></thead>
    <?php foreach (Repository::GROUPS as $gk => [$gname]): if (empty($byGroup[$gk])) continue; ?>
    <tbody>
      <tr class="ck-grouprow"><th colspan="<?= 3 + ($multi ? count($domains) : 0) ?>" scope="colgroup"><?= e(__($gname)) ?></th></tr>
      <?php foreach ($byGroup[$gk] as $i => $s): $complete = Compiler::isComplete($s); ?>
      <tr id="s<?= (int) $s['id'] ?>">
        <td>
          <form method="post" action="<?= e(url('/admin/consent/services/toggle')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <button class="ck-toggle" role="switch" aria-checked="<?= $s['active'] ? 'true' : 'false' ?>" aria-label="<?= e(__('{name} aktiv', ['name' => $s['name']])) ?>"><span class="ck-toggle__track" aria-hidden="true"></span><span class="ck-toggle__text"><?= e($s['active'] ? __('an') : __('aus')) ?></span></button>
          </form>
        </td>
        <td>
          <a href="<?= e(url('/admin/consent/service/' . $s['id'])) ?>"><strong><?= e($s['name']) ?></strong></a> <code class="adm-muted"><?= e($s['skey']) ?></code>
          <div class="ck-badges">
            <?php if (!$complete): ?><span class="adm-badge adm-badge--adm-warn"><?= e(__('Unvollständig')) ?></span><?php endif; ?>
            <?php if ($s['variants']): ?><span class="adm-badge adm-badge--muted"><?= e(__('{n} Varianten', ['n' => count($s['variants'])])) ?></span><?php endif; ?>
            <?php if ($s['gcm']): ?><span class="adm-badge adm-badge--muted" title="<?= e(implode(', ', $s['gcm'])) ?>">Consent Mode</span><?php endif; ?>
            <?php if ($s['events']): ?><span class="adm-badge adm-badge--muted"><?= e(__('{n} Ereignisse', ['n' => count($s['events'])])) ?></span><?php endif; ?>
            <?php if ($s['embed_hosts']): ?><span class="adm-badge adm-badge--muted"><?= e(__('Einbettung')) ?></span><?php endif; ?>
            <?php if (str_contains(mb_strtolower($s['name']), 'beispiel')): ?><span class="adm-badge adm-badge--draft"><?= e(__('Beispiel')) ?></span><?php endif; ?>
          </div>
        </td>
        <?php if ($multi): foreach ($domains as $did => $label): $on = !$s['hosts'] || in_array($did, $s['hosts'], true); ?>
        <td class="ck-dom"><input type="checkbox" form="ck-matrix" name="d[<?= (int) $s['id'] ?>][]" value="<?= e($did) ?>"<?= $on ? ' checked' : '' ?> aria-label="<?= e(__('{name} auf {domain}', ['name' => $s['name'], 'domain' => $label])) ?>" data-ck-autosubmit></td>
        <?php endforeach; endif; ?>
        <td class="adm-actions">
          <form method="post" action="<?= e(url('/admin/consent/services/move')) ?>" class="ck-move"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <button class="icon-btn" name="dir" value="up" aria-label="<?= e(__('{name} nach oben', ['name' => $s['name']])) ?>"<?= $i === 0 ? ' disabled' : '' ?>>↑</button>
            <button class="icon-btn" name="dir" value="down" aria-label="<?= e(__('{name} nach unten', ['name' => $s['name']])) ?>"<?= $i === count($byGroup[$gk]) - 1 ? ' disabled' : '' ?>>↓</button>
          </form>
          <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/consent/service/' . $s['id'])) ?>"><?= e(__('Bearbeiten')) ?></a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <?php endforeach; ?>
  </table>
  <?php if ($multi): ?><p class="ck-matrix-save"><button class="adm-btn adm-btn--small" form="ck-matrix"><?= e(__('Domains speichern')) ?></button></p><?php endif; ?>
</section>
<?php endif; ?>
