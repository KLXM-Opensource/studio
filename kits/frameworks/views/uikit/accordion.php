<?php
/**
 * Aufklappliste (UIkit) – uk-accordion (setzt aria-expanded, role, hidden selbst). Im Bearbeiten-Modus ohne das Attribut:
 * alle Einträge offen und bearbeitbar (ein Klick auf den Titel klappte sonst zu). Symbol: icon() des Kerns statt
 * uk-accordion-icon (dessen SVG enthält ein <style> → CSP-Meldung ohne 'unsafe-inline').
 * @var \Core\Block $b  @var array $d
 */
$js = !is_editing();
?>
<div class="uk-container uk-container-small">
  <?php include __DIR__ . '/_head.php'; ?>
  <ul class="uk-accordion uk-accordion-default fw-accordion"<?= $js ? ' uk-accordion="multiple: true"' : '' ?>>
    <?php foreach ($d['items'] as $i => $it): ?>
    <li<?= $js ? '' : ' class="uk-open"' ?>>
      <?php if ($js): ?><a class="uk-accordion-title" href><span><?= e($it['q']) ?></span><?= icon('caret-down', ['class' => 'fw-acc-icon']) ?></a>
      <?php else: ?><h3 class="uk-accordion-title uk-margin-remove"><span<?= $b->edit("items.$i.q") ?>><?= e($it['q']) ?></span></h3><?php endif; ?>
      <div class="uk-accordion-content fw-prose"<?= $b->edit("items.$i.a", 'rich') ?>><?= rich($it['a']) ?></div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if (!$d['items'] && is_editing()): ?><p class="empty-hint"><?= e(lt('Noch keine Einträge – in der Seitenleiste hinzufügen.')) ?></p><?php endif; ?>
</div>
