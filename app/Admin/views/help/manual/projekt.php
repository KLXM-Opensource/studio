<?php
/**
 * Handbuch · Kapitel „Hinweise zu diesem Projekt“ (Core\Guide) – eingebunden von help/manual.php nur, wenn Hinweise da sind.
 * Quellen: themes/{kit}/guide/*.md (Kit) und {storage}/guide/*.md (Website, Handbuch → Projekt-Hinweise).
 */
$__notes = \Core\Guide::notes();
$__author = \Core\Guide::author();
$__lead = (string) (\Core\Guide::config()['lead'] ?? '');
?>
  <div class="doc-note doc-note--info doc-guide__intro">
    <strong>Nur für dieses Projekt: „<?= e(site_name()) ?>“ · Kit „<?= e(app()->theme->label()) ?>“</strong>
    <p><?= $__lead !== '' ? e($__lead) : 'Diese Hinweise kommen von ' . ($__author !== '' ? e($__author) : 'Ihrer Agentur') . ' bzw. aus dem Kit und ergänzen das allgemeine Handbuch: Sie beschreiben, was auf dieser Website anders oder besonders ist – eigene Blöcke, Bildformate, Abläufe und Regeln. Alles andere steht in den folgenden Kapiteln.' ?></p>
  </div>
<?php if (count($__notes) > 2): ?>
  <div class="doc-cards doc-guide__cards">
    <?php foreach ($__notes as $__g): ?>
    <a class="doc-card" href="#<?= e($__g['anchor']) ?>"><span class="doc-card__icon"><?= icon('lightbulb') ?></span><span class="doc-card__title"><?= e($__g['title']) ?></span><span class="doc-card__sub"><?= e(\Core\Guide::plain($__g, 90)) ?></span></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php foreach ($__notes as $__g): ?>
  <h3 id="<?= e($__g['anchor']) ?>"><?= e($__g['title']) ?></h3>
  <div class="doc-guide__body"><?= \Core\Guide::html($__g) ?></div>
<?php endforeach; ?>
<?php if (\Core\Guide::canEdit()): ?>
  <p class="doc-guide__edit"><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/hilfe/projekt')) ?>">Hinweise für diese Website bearbeiten</a></p>
<?php endif; ?>
