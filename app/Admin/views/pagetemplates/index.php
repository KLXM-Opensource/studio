<?php
/**
 * Werkzeuge → Seitenvorlagen (Core\PageTemplates).
 * @var array $fields  @var array $values  @var array $errors  @var array $templates
 */
use Core\Fields;
?>
<header class="adm-head">
  <div><h1><?= e(__('Seitenvorlagen')) ?></h1>
    <p class="adm-muted"><?= e(__('Vorlagen, aus denen die Redaktion neue Seiten anlegt – z. B. „Leistung“ oder „Stellenanzeige“. Jede Vorlage übernimmt die Blöcke einer Quellseite.')) ?></p></div>
</header>

<div class="adm-grid2 adm-grid2--wide">
  <form class="adm-card" method="post" action="<?= e(url('/admin/seitenvorlagen')) ?>" novalidate>
    <?= csrf_field() ?>
    <div class="adm-fields"><?= Fields::renderForm($fields, $values, $errors, 'f') ?></div>
    <div class="adm-form-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Speichern')) ?></button></div>
  </form>

  <aside class="adm-card" aria-labelledby="tpl-how">
    <h2 id="tpl-how"><?= e(__('So geht’s')) ?></h2>
    <ol>
      <li><?= e(__('Eine Seite mit dem gewünschten Aufbau anlegen – am besten offline unter einer Seite „Vorlagen“.')) ?></li>
      <li><?= e(__('Hier als Vorlage hinzufügen (oder im Seitenbaum „⋯ → Als Vorlage anbieten“), Namen und Beschreibung vergeben.')) ?></li>
      <li><?= e(__('Optional „Vorschlagen unter“ wählen: Neue Seiten in diesem Bereich starten dann mit der Vorlage.')) ?></li>
      <li><?= e(__('Reihenfolge mit ↑/↓ festlegen – so erscheinen die Vorlagen beim Anlegen.')) ?></li>
    </ol>
    <?php if ($templates): ?>
    <h3><?= e(__('Quellseiten')) ?></h3>
    <ul class="adm-list">
      <?php foreach ($templates as $t): ?>
      <li><?= e($t['label']) ?> – <a href="<?= e(\Core\Pages::plainUrl($t['source'])) ?>?edit=1"><?= e(__('Vorlage bearbeiten')) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </aside>
</div>
