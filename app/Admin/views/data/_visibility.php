<?php
/**
 * Formularkopf „Auf der Website: Name, Funktion, E-Mail …“ (Verwaltung data/entry.php und Seitenleiste „Eintrag bearbeiten“
 * app/Views/entry-panel.php). Inhalt aus Core\Data\Exposure::webSummary() – dieselben Regeln wie die Merkmale an den Feldern;
 * resources/js/_exposure.js hält die Liste beim Tippen aktuell. Hinweis „nicht auf der Website“, wenn ein Kontakt nicht
 * freigegeben ist („Auf der Website zeigen“ aus) oder der Eintrag ein Entwurf ist.
 * @var array $t  @var array $values  @var string $status
 */
use Core\Data\Exposure;
use Core\Data\Kinds;

if (\Core\Data\Tables::isInbox($t)) return;
$list = Exposure::webSummary($t, $values);
$hidden = !Exposure::visible($t, $values);
$draft = !empty($t['settings']['workflow']) && $status === 'draft';
$off = $hidden || $draft;
?>
<div class="f-pubsum<?= $off ? ' is-off' : '' ?>" data-pubsum data-city="<?= e(__('nur Ort')) ?>"<?= Exposure::perEntry($t) ? ' data-entry-field="f[' . Kinds::PUBLIC_FIELD . ']"' : '' ?> data-draft="<?= $draft ? '1' : '0' ?>">
  <h2 class="adm-sr"><?= e(__('Sichtbarkeit')) ?></h2>
  <div aria-live="polite"><p class="f-pubsum__off" data-pubsum-off<?= $off ? '' : ' hidden' ?>><?= icon('lock') ?> <span><strong><?= e(__('Dieser Eintrag ist nicht auf der Website.')) ?></strong>
    <span data-why="hidden"<?= $hidden ? '' : ' hidden' ?>><?= e(__('„Auf der Website zeigen“ ist aus.')) ?></span>
    <span data-why="draft"<?= $draft ? '' : ' hidden' ?>><?= e(__('Er ist ein Entwurf.')) ?></span></span></p></div>
  <p class="f-pubsum__on"><?= icon('globe') ?> <span><span class="f-pubsum__k" data-k="on"<?= $off ? ' hidden' : '' ?>><?= e(__('Auf der Website:')) ?></span><span class="f-pubsum__k" data-k="off"<?= $off ? '' : ' hidden' ?>><?= e(__('Sobald er erscheint, zeigt die Website:')) ?></span>
    <span data-pubsum-list data-none="<?= e(__('noch keine Angaben')) ?>"><?= e($list ? implode(', ', $list) : __('noch keine Angaben')) ?></span></span></p>
  <p class="f-pubsum__legend"><span class="f-pubsum__chip is-public"><?= icon('globe') ?> <?= e(__('Öffentlich')) ?></span> <?= e(__('erscheint auf der Website')) ?> · <span class="f-pubsum__chip is-internal"><?= icon('lock') ?> <?= e(__('Intern')) ?></span> <?= e(__('nur für die Redaktion')) ?></p>
</div>
