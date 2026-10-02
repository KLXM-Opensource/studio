<?php
/**
 * Einrichtung › Website › Seitenvorlagen (Core\PageTemplates) – nur Administration.
 * @var array $fields  @var array $values  @var array $errors  @var array $templates
 */
use Core\Fields;
use Core\PageTemplates;
?>
<header class="adm-head">
  <div><h1><?= e(__('Seitenvorlagen')) ?></h1>
    <p class="adm-muted"><?= e(__('Vorlagen, aus denen die Redaktion neue Seiten anlegt – z. B. „Leistung“ oder „Stellenanzeige“. Vorlagen sind keine Seiten der Website: keine Adresse, nicht im Seitenbaum, Menü oder in der Suche; bearbeiten kann sie nur die Administration.')) ?></p></div>
</header>

<div class="adm-grid2 adm-grid2--wide">
  <div>
    <form class="adm-card" method="post" action="<?= e(url('/admin/seitenvorlagen/neu')) ?>">
      <?= csrf_field() ?>
      <h2><?= e(__('Neue Vorlage')) ?></h2>
      <div class="adm-fields">
        <div class="f f--half"><label for="tpl-label"><?= e(__('Name')) ?></label><input id="tpl-label" name="label" maxlength="60" required placeholder="<?= e(__('z. B. Leistung, Stellenanzeige, Veranstaltung')) ?>"></div>
        <div class="f f--half"><label for="tpl-from"><?= e(__('Ausgangspunkt')) ?></label>
          <select id="tpl-from" name="from"><option value=""><?= e(__('Leer beginnen')) ?></option>
            <?php foreach (\Core\Pages::flat() as $fp): ?><option value="<?= (int) $fp['id'] ?>"><?= e(__('Kopie von:')) ?> <?= str_repeat('  ', (int) $fp['depth']) . e($fp['title']) ?></option><?php endforeach; ?>
          </select><p class="f-help"><?= e(__('Eine gelungene Seite als Ausgangspunkt kopieren – die Seite selbst bleibt unverändert.')) ?></p></div>
      </div>
      <div class="adm-form-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Anlegen und gestalten')) ?></button></div>
    </form>

    <?php if ($values['templates']): ?>
    <form class="adm-card" method="post" action="<?= e(url('/admin/seitenvorlagen')) ?>" novalidate>
      <?= csrf_field() ?>
      <div class="adm-fields"><?= Fields::renderForm($fields, $values, $errors, 'f') ?></div>
      <div class="adm-form-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Speichern')) ?></button></div>
    </form>
    <?php endif; ?>
  </div>

  <aside class="adm-card" aria-labelledby="tpl-list">
    <h2 id="tpl-list"><?= e(__('Blöcke bearbeiten')) ?></h2>
    <?php if ($templates): ?>
    <ul class="adm-list">
      <?php foreach ($templates as $t): ?>
      <li><span><?= icon($t['icon'] ?: 'stamp') ?> <?= e($t['label']) ?></span>
        <a class="adm-btn adm-btn--small" href="<?= e(PageTemplates::editUrl($t['page'])) ?>"><?= e(__('Blöcke bearbeiten')) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="adm-muted"><?= e(__('Noch keine Vorlagen. Legen Sie links die erste an – oder im Seitenbaum „⋯ → Als Vorlage speichern“.')) ?></p>
    <?php endif; ?>
    <h3><?= e(__('So geht’s')) ?></h3>
    <ol>
      <li><?= e(__('Vorlage anlegen – leer oder als Kopie einer Seite – und die Blöcke gestalten. Platzhalter wie „[bitte ergänzen: Preis]“ verhindern, dass eine Seite unfertig online geht.')) ?></li>
      <li><?= e(__('Name, Symbol und Beschreibung vergeben; optional „Vorschlagen unter“ wählen.')) ?></li>
      <li><?= e(__('Reihenfolge mit ↑/↓ festlegen – so erscheinen die Vorlagen beim Anlegen einer Seite.')) ?></li>
    </ol>
  </aside>
</div>
