<?php
/**
 * „Einsetzen“: So kommt eine Tabelle auf die Website (Core\Data\Placement) – Schritt 4 des Assistenten (?neu=1) und eigene Seite
 * unter Daten → Tabelle → Einsetzen. Neue Seite mit dem passenden Block anlegen, Block in eine bestehende Seite einfügen (Ziel über
 * die Linkauswahl), Anleitung für später und die Seiten, die die Tabelle schon verwenden.
 * @var array $t  @var bool $new  @var array $usages  @var array $errors  @var array $old
 */
use Core\Data\Placement;
use Core\Data\Purpose;
use Core\Data\Tables;

$type = Placement::blockType($t);
$inbox = Tables::isInbox($t);
$purpose = Purpose::of($t);
$pinfo = Purpose::all()[$purpose];
$route = (string) ($t['settings']['route'] ?? '');
$hasTpl = !empty($t['settings']['detail_page_id']) && \Core\Pages::find((int) $t['settings']['detail_page_id']);
$placed = (array) app()->session->get('dt_placed', []);
$placedPage = ($placed['handle'] ?? '') === $t['handle'] && !empty($placed['page']) ? \Core\Pages::find((int) $placed['page']) : null;
if ($placedPage) app()->session->forget('dt_placed');
$entriesUrl = $inbox ? url('/admin/requests?table=' . $t['handle']) : url('/admin/data/' . $t['handle']);
$canCreate = can('pages.manage');
$canAppend = can('pages.edit') || can('pages.manage');
$canPublish = can('pages.publish');
$act = (string) ($old['action'] ?? '');
$blockLabel = Placement::blockLabel($type);
$sw = fn(string $name, bool $on, string $label, string $help = '') => '<div class="f f--bool"><input type="hidden" name="' . e($name) . '" value="0">'
    . '<label class="f-check"><input type="checkbox" role="switch" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . '> <span>' . e($label) . '</span></label>'
    . ($help !== '' ? '<p class="f-help">' . e($help) . '</p>' : '') . '</div>';
$fe = fn(string $k) => isset($errors[$k]) ? '<p class="f-error">' . e($errors[$k]) . '</p>' : '';
?>
<?php if ($new): ?>
<header class="adm-head dt-head wz-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/data')) ?>"><?= e(__('Daten')) ?></a> › <?= e(__('Neu')) ?></p>
    <h1><?= e(__('Neue Tabelle oder neues Formular')) ?></h1></div>
</header>
<?= Core\Theme::capture(__DIR__ . '/_wizard_steps.php', ['step' => 'einsetzen', 'links' => false]) ?>
<?php elseif ($inbox): ?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/data')) ?>"><?= e(__('Daten')) ?></a> · <a href="<?= e($entriesUrl) ?>"><?= e($t['name']) ?></a></p>
    <h1><?= e(__('Einsetzen')) ?></h1></div>
  <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/data/' . $t['handle'] . '/schema')) ?>"><?= e(__('Felder & Einstellungen')) ?></a>
</header>
<?php else: ?>
<header class="adm-head dt-head">
  <h1><span aria-hidden="true" class="dt-h1icon"><?= icon($t['icon']) ?></span> <?= e($t['name']) ?> <span class="dt-h1sub">· <?= e(__('Einsetzen')) ?></span></h1>
</header>
<?php endif; ?>

<div class="pl">
  <section class="pl-how" aria-labelledby="pl-how-t">
    <div class="pl-how__text">
      <h2 id="pl-how-t" class="wz-q"><?= e($type ? __('So kommt „{name}“ auf die Website', ['name' => $t['name']]) : __('„{name}“ ist eine interne Liste', ['name' => $t['name']])) ?></h2>
      <?php if ($type === 'data_form'): ?>
      <p class="wz-lead"><?= e(__('Das Formular erscheint auf einer Seite über den Block „{block}“. Darin wählen Sie die Tabelle „{name}“ – den Rest (Felder, Zustellung, Bestätigung) holt sich der Block aus den Einstellungen der Tabelle.', ['block' => $blockLabel, 'name' => $t['name']])) ?></p>
      <?php elseif ($type === 'data_list'): ?>
      <p class="wz-lead"><?= e(__('Die Einträge erscheinen auf einer Seite über den Block „{block}“ (Karten, Liste, Tabelle oder Verzeichnis).', ['block' => $blockLabel])) ?>
        <?= $route !== '' ? e(__('Jeder veröffentlichte Eintrag hat zusätzlich eine eigene Detailseite unter /{route}/… – gestaltet über die Detailseiten-Vorlage.', ['route' => $route])) : e(__('Diese Tabelle hat keine Detailseiten – alles steht in der Liste.')) ?></p>
      <?php else: ?>
      <p class="wz-lead"><?= e(__('Interne Listen erscheinen nicht auf der Website. Soll sie doch öffentlich werden, stellen Sie unter „Felder & Einstellungen → Allgemein“ den Zweck „Inhalte auf der Website“ ein.')) ?></p>
      <?php endif; ?>
    </div>
    <?php if ($type): /* Kleine Abbildung: Seite mit dem markierten Block (nur Schmuck – der Text sagt dasselbe) */ ?>
    <figure class="pl-fig" aria-hidden="true">
      <div class="pl-fig__page">
        <div class="pl-fig__bar"><i></i><i></i><i></i></div>
        <div class="pl-fig__line pl-fig__line--h"></div>
        <div class="pl-fig__line"></div>
        <div class="pl-fig__block"><span class="pl-fig__ico"><?= icon($type === 'data_form' ? 'clipboard-text' : 'list-bullets') ?></span>
          <span><b><?= e($blockLabel) ?></b><small><?= e(__('Tabelle: {name}', ['name' => $t['name']])) ?></small></span></div>
        <?php if ($type === 'data_form'): ?><div class="pl-fig__form"><i></i><i></i><i class="is-btn"></i></div>
        <?php else: ?><div class="pl-fig__cards"><i></i><i></i><i></i></div><?php endif; ?>
      </div>
    </figure>
    <?php endif; ?>
  </section>

  <?php if ($placedPage): $pu = \Core\Pages::url($placedPage); ?>
  <section class="pl-done" role="status" aria-labelledby="pl-done-t">
    <span class="pl-done__ico" aria-hidden="true"><?= icon('check-circle') ?></span>
    <div><h2 id="pl-done-t"><?= e(__('Eingesetzt in „{title}“', ['title' => $placedPage['title']])) ?></h2>
      <p><?= e($placedPage['status'] === 'published' && !\Core\Pages::hasUnpublished($placedPage) ? __('Die Seite ist online.') : __('Die Änderung ist noch ein Entwurf – im Editor prüfen und veröffentlichen.')) ?></p>
      <p class="adm-row">
        <a class="adm-btn adm-btn--primary" href="<?= e($pu . '?edit=1') ?>"><?= e(__('Im Editor öffnen')) ?></a>
        <a class="adm-btn" href="<?= e($pu) ?>" target="_blank" rel="noopener"><?= e(__('Ansehen')) ?> ↗</a>
        <a class="adm-btn adm-btn--ghost" href="<?= e($entriesUrl) ?>"><?= e($inbox ? __('Zu den Anfragen') : __('Zu den Einträgen')) ?></a>
      </p></div>
  </section>
  <?php endif; ?>

  <?php if ($type): ?>
  <div class="pl-ways">
    <form method="post" action="<?= e(url('/admin/data/' . $t['handle'] . '/einsetzen')) ?>" class="pl-way" aria-labelledby="pl-a-t" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="page"><?php if ($new): ?><input type="hidden" name="neu" value="1"><?php endif; ?>
      <h3 id="pl-a-t" class="pl-way__title"><span class="pl-way__ico" aria-hidden="true"><?= icon('file-plus') ?></span> <?= e(__('Neue Seite anlegen')) ?></h3>
      <p class="pl-way__lead"><?= e(__('Mit dem Block „{block}“ für „{name}“ – fertig zum Veröffentlichen.', ['block' => $blockLabel, 'name' => $t['name']])) ?></p>
      <?php if ($canCreate): ?>
      <div class="set-list">
        <div class="f"><label for="pl-title"><?= e(__('Titel der Seite')) ?></label>
          <input id="pl-title" name="title" value="<?= e($act === 'page' ? (string) ($old['title'] ?? '') : $t['name']) ?>" maxlength="120" required<?= isset($errors['title']) ? ' aria-invalid="true"' : '' ?>><?= $fe('title') ?></div>
        <div class="f"><label for="pl-parent"><?= e(__('Unterhalb von (optional)')) ?></label>
          <?= \Core\Fields::renderLink('pl-parent', 'parent', $act === 'page' ? (string) ($old['parent'] ?? '') : '') ?>
          <p class="f-help"><?= e(__('Seite im Seitenbaum wählen – leer = oberste Ebene.')) ?></p><?= $fe('parent') ?></div>
        <?= $sw('menu', $act === 'page' ? !empty($old['menu']) : true, __('Im Menü zeigen')) ?>
        <?php if ($type === 'data_list' && $route !== '' && !$hasTpl): ?><?= $sw('template', true, __('Detailseiten-Vorlage gleich mit anlegen')) ?><?php endif; ?>
        <?php if ($canPublish): ?><?= $sw('publish', $act === 'page' && !empty($old['publish']), __('Seite gleich veröffentlichen'), __('Aus = Entwurf: Sie prüfen die Seite im Editor und veröffentlichen dort.')) ?><?php endif; ?>
      </div>
      <div class="pl-way__go"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Seite anlegen')) ?></button></div>
      <?php else: ?><p class="f-help"><?= e(__('Seiten anlegen darf Ihre Rolle nicht – bitten Sie die Administration darum.')) ?></p><?php endif; ?>
    </form>

    <form method="post" action="<?= e(url('/admin/data/' . $t['handle'] . '/einsetzen')) ?>" class="pl-way" aria-labelledby="pl-b-t" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="append"><?php if ($new): ?><input type="hidden" name="neu" value="1"><?php endif; ?>
      <h3 id="pl-b-t" class="pl-way__title"><span class="pl-way__ico" aria-hidden="true"><?= icon('browser') ?></span> <?= e(__('In bestehende Seite einfügen')) ?></h3>
      <p class="pl-way__lead"><?= e(__('Der Block kommt ans Ende der Seite (als Entwurf). Verschieben Sie ihn danach im Editor an die gewünschte Stelle.')) ?></p>
      <?php if ($canAppend): ?>
      <div class="set-list">
        <div class="f"><label for="pl-target"><?= e(__('Seite')) ?></label>
          <?= \Core\Fields::renderLink('pl-target', 'target', $act === 'append' ? (string) ($old['target'] ?? '') : '') ?><?= $fe('target') ?></div>
        <?php if ($canPublish): ?><?= $sw('publish', false, __('Änderung gleich veröffentlichen'), __('Aus = Entwurf: Die Seite zeigt den Block erst nach dem Veröffentlichen im Editor.')) ?><?php endif; ?>
      </div>
      <div class="pl-way__go"><button class="adm-btn" type="submit"><?= e(__('Block einfügen')) ?></button></div>
      <?php else: ?><p class="f-help"><?= e(__('Seiten bearbeiten darf Ihre Rolle nicht – bitten Sie die Administration darum.')) ?></p><?php endif; ?>
    </form>
  </div>

  <details class="pl-later"<?= $new ? '' : ' open' ?>>
    <summary><?= e(__('Später selbst einsetzen – so geht’s')) ?></summary>
    <ol class="pl-steps">
      <li><?= e(__('Die gewünschte Seite öffnen und „Bearbeiten“ wählen (oder unter Seiten eine neue anlegen).')) ?></li>
      <li><?= e(__('Mit „+“ einen Block hinzufügen und „{block}“ wählen (Gruppe „Daten“).', ['block' => $blockLabel])) ?></li>
      <li><?= e(__('In der Seitenleiste des Blocks bei „Tabelle“ „{name}“ auswählen.', ['name' => $t['name']])) ?></li>
      <li><?= e(__('Überschrift und Darstellung nach Wunsch einstellen, dann die Seite veröffentlichen.')) ?></li>
    </ol>
    <?php if ($type === 'data_list' && \Core\Data\Calendar::enabled($t)): ?><p class="f-help"><?= e(__('Für Termine gibt es zusätzlich die Blöcke „Kalender“ (Monatsübersicht) und „Nächste Termine“.')) ?></p><?php endif; ?>
    <?php if ($type === 'data_form'): ?><p class="f-help"><?= e(__('Welche Felder das Formular zeigt, wohin Einsendungen gehen und ob eine Bestätigung verschickt wird, stellen Sie bei der Tabelle ein – nicht im Block.')) ?></p><?php endif; ?>
  </details>
  <?php endif; ?>

  <section class="set-group" aria-labelledby="pl-use-t">
    <h3 class="set-group__title" id="pl-use-t"><?= e(__('Hier wird „{name}“ verwendet', ['name' => $t['name']])) ?></h3>
    <div class="set-list">
      <?php if (!$usages): ?>
      <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Noch auf keiner Seite')) ?></span>
        <span class="set-row__sub"><?= e($type ? __('Besucher sehen „{name}“ erst, wenn ein Block auf einer veröffentlichten Seite steht.', ['name' => $t['name']]) : __('Interne Liste – das ist so gewollt.')) ?></span></div></div>
      <?php else: foreach ($usages as $u): ?>
      <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e($u['title']) ?><?= $u['status'] !== 'published' ? ' <span class="adm-badge adm-badge--muted">' . e(__('Entwurf')) . '</span>' : '' ?></span>
        <span class="set-row__sub"><?= e(implode(' · ', $u['blocks'])) ?></span></div>
        <div class="set-row__ctl"><?php if ($u['edit']): ?><a href="<?= e($u['edit']) ?>"><?= e($u['template'] ? __('Vorlage gestalten') : __('Bearbeiten')) ?></a><?php endif; ?>
          <?php if ($u['url'] && !$u['template']): ?> · <a href="<?= e($u['url']) ?>" target="_blank" rel="noopener"><?= e(__('Ansehen')) ?> ↗</a><?php endif; ?></div></div>
      <?php endforeach; endif; ?>
    </div>
  </section>

  <div class="wz-nav wz-nav--end">
    <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/data/' . $t['handle'] . '/schema')) ?>"><?= e(__('Felder & Einstellungen')) ?></a>
    <a class="adm-btn<?= $new ? ' adm-btn--primary' : '' ?>" href="<?= e($entriesUrl) ?>"><?= e($new ? __('Fertig – zu „{name}“', ['name' => $t['name']]) : ($inbox ? __('Zu den Anfragen') : __('Zu den Einträgen'))) ?></a>
  </div>
</div>
