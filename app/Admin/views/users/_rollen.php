<?php
/** Benutzer & Rollen › Rollen: eine Karte je Rolle mit ihren Rechten (eingebunden von users.php, Variablen von dort) */
?>
<section class="us-roles" id="rollen">
  <div class="set-group__head">
    <p class="set-page__lead"><?= e(__('Was eine Person darf, bestimmt ihre Rolle. Eigene Rollen stellen Sie aus einzelnen Rechten zusammen.')) ?></p>
    <a class="adm-btn adm-btn--small" href="<?= e(url('/admin/roles/new')) ?>">+ <?= e(__('Neue Rolle')) ?></a>
  </div>
  <div class="us-rolegrid">
    <?php foreach ($roles as $k => $ro): $all = in_array('*', $ro['permissions'], true); $editable = $k !== 'admin' && $k !== 'network'; ?>
    <article class="us-role-card">
      <header><strong><?= e($ro['name']) ?></strong><span class="adm-badge adm-badge--muted"><?= e(__('{n} Benutzer', ['n' => (int) ($count[$k] ?? 0)])) ?></span></header>
      <?php if ($ro['description']): ?><p class="us-role-card__desc"><?= e($ro['description']) ?></p><?php endif; ?>
      <ul class="us-perms">
        <?php if ($all): ?><li class="is-all"><?= e(__('Alle Rechte')) ?></li>
        <?php else: foreach ($ro['permissions'] as $p): if (!isset($labels[$p])) continue; ?><li><?= e($labels[$p]) ?></li><?php endforeach; if (!$ro['permissions']): ?><li class="is-none"><?= e(__('Keine Rechte')) ?></li><?php endif; endif; ?>
      </ul>
      <?php if (is_array($ro['tables'])): ?><p class="us-tables"><?= e(__('Datentabellen:')) ?> <?= e(implode(', ', $ro['tables']) ?: __('keine')) ?></p><?php endif; ?>
      <?php if ($k === 'network'): ?><p class="us-tables"><?= e(__('Zentral verwaltet: Konten der Netzwerk-Website, Anmeldung mit Zwei-Faktor-Anmeldung. Hier nicht vergebbar.')) ?></p><?php endif; ?>
      <?php if ($editable): ?><footer class="us-role-card__foot"><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/roles/' . $k)) ?>"><?= e(__('Rechte bearbeiten')) ?></a>
        <?php if (!$ro['builtin'] && empty($count[$k])): ?><form method="post" action="<?= e(url('/admin/roles/' . $k . '/delete')) ?>" data-confirm="<?= e(__('Rolle „{name}“ löschen?', ['name' => $ro['name']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--danger-text"><?= e(__('Löschen')) ?></button></form><?php endif; ?></footer><?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
</section>
