<?php
/**
 * Bereichsnavigation „Daten“: Tabellen (mit Anzahl Einträge), unter der aktuellen Tabelle ihre Ansichten und Einstellungen.
 * Das Layout zeigt sie links anstelle der Hauptnavigation (breite Bildschirme), sonst oben in der Seite (resources/js/_drill.js).
 * @var string $cur  index|list|calendar|entry|new-entry|schema|new|place  @var ?array $active  aktuelle Tabelle
 */
use Core\Data\Entries;
use Core\Data\Tables;

$schema = can('data.schema');
$tables = array_filter(Tables::content(), fn($t) => $schema || can('data.edit', $t['handle']));
$ac = fn(bool $on, string $v = 'page') => $on ? ' aria-current="' . $v . '"' : '';
?>
<nav class="dt-nav" data-drill-panel aria-label="<?= e(__('Daten')) ?>">
  <ul class="dt-nav__list">
    <li><a class="dt-nav__item" href="<?= e(url('/admin/data')) ?>"<?= $ac($cur === 'index') ?>><span class="dt-nav__ico" aria-hidden="true"><?= icon('squares-four') ?></span><span class="dt-nav__name"><?= e(__('Übersicht')) ?></span></a></li>
    <?php foreach ($tables as $t):
      $base = '/admin/data/' . $t['handle'];
      $edit = can('data.edit', $t['handle']);
      $on = $active && $active['handle'] === $t['handle'];
      $subs = [];
      $shared = \Core\Data\Tables::isShared($t);
      if ($on) {
          if ($edit) $subs[] = [url($base) . '?view=list', $shared ? __('Eigene') : __('Liste'), $cur === 'list'];
          if ($edit && \Core\Data\Calendar::enabled($t)) $subs[] = [url($base) . '?view=calendar', __('Kalender'), $cur === 'calendar'];
          if ($shared && $edit) {
              // Geteilte Tabelle: fremde Einträge, Vorschläge (nur Eigentümer), Anzeige auf dieser Website
              $isOwner = \Core\Data\Shared::isOwner($t);
              if (!$isOwner) $subs[] = [url($base . '/shared') . '?src=owner', term('shared_from_owner'), $cur === 'src-owner'];
              if ($isOwner || $t['shared']['members_see_members']) $subs[] = [url($base . '/shared') . '?src=members', term('shared_from_members'), $cur === 'src-members'];
              if ($isOwner) {
                  $pending = Entries::count($t, ['status' => 'published', 'lang' => 'all', 'source' => 'members', 'suggested' => 'pending']);
                  $subs[] = [url($base . '/shared') . '?src=suggestions', __('Vorschläge') . ($pending ? ' (' . $pending . ')' : ''), $cur === 'src-suggestions'];
              }
              $subs[] = [url($base . '/display'), __('Anzeige auf dieser Website'), $cur === 'display'];
          }
          if ($schema && \Core\Data\Shared::canSchema($t)) $subs[] = [url($base . '/schema'), __('Felder & Einstellungen'), $cur === 'schema'];
          // Einsetzen: Block auf einer Seite, Seite anlegen, Verwendungen (Core\Data\Placement) – nicht für interne Listen
          if ($schema && \Core\Data\Placement::blockType($t) !== null) $subs[] = [url($base . '/einsetzen'), __('Einsetzen'), $cur === 'place'];
          // Einstellungsseiten von Funktionen/Erweiterungen zu dieser Tabelle (Core\AdminPages, 'table' => …), z. B. Glossar
          foreach (\Core\AdminPages::forTable($t['handle']) as $tp) $subs[] = [url($tp['href']), $tp['label'], $cur === 'page:' . $tp['id']];
      }
      $tpl = $on && $schema && $t['settings']['route'] !== '';
      // Nur ein Unterpunkt (z. B. „Liste“): kein eigenes Untermenü, die Tabelle selbst ist die Seite
      $single = count($subs) < 2 && !$tpl;
    ?>
    <li<?= $on ? ' class="is-open"' : '' ?>>
      <a class="dt-nav__item<?= $on ? ' is-active' : '' ?>" href="<?= e(url($edit ? $base : $base . '/schema')) ?>"<?= $ac($on, $single && in_array($cur, ['list', 'calendar', 'schema'], true) ? 'page' : 'true') ?>>
        <span class="dt-nav__ico" aria-hidden="true"><?= icon($t['icon']) ?></span><span class="dt-nav__name"><?= e($t['name']) ?><?php if ($shared): ?> <span class="dt-nav__shared" title="<?= e(__('Geteilt mit anderen Websites dieser Installation')) ?>"><?= e(__('geteilt')) ?></span><?php endif; ?></span>
        <small class="dt-nav__count"><?php $n = Entries::count($t, ['status' => 'all', 'source' => 'own']); ?><span aria-hidden="true"><?= $n ?></span><span class="sr-only"><?= e($n === 1 ? __('1 Eintrag') : __('{n} Einträge', ['n' => $n])) ?></span></small>
      </a>
      <?php if (!$single): ?>
      <ul class="dt-nav__sub" aria-label="<?= e($t['name']) ?>">
        <?php foreach ($subs as [$href, $label, $is]): ?>
        <li><a href="<?= e($href) ?>"<?= $ac($is) ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
        <?php if ($tpl): ?>
        <li><form method="post" action="<?= e(url($base . '/template')) ?>"><?= csrf_field() ?><button type="submit"><?= e(__('Detailseite gestalten')) ?></button></form></li>
        <?php endif; ?>
      </ul>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if (\Core\Sources\Sources::navVisible()): /* Externe Quellen (Core\Sources): Feeds, APIs, OpenImmo → Tabellen */ ?>
  <ul class="dt-nav__list dt-nav__list--extra">
    <li><a class="dt-nav__item" href="<?= e(url('/admin/quellen')) ?>"<?= $ac($cur === 'sources') ?>><span class="dt-nav__ico" aria-hidden="true"><?= icon('plugs-connected') ?></span><span class="dt-nav__name"><?= e(__('Externe Quellen')) ?></span></a></li>
  </ul>
  <?php endif; ?>
  <?php if ($schema): ?>
  <a class="dt-nav__new" href="<?= e(url('/admin/data/new')) ?>"<?= $ac($cur === 'new') ?>><?= icon('plus') ?> <?= e(__('Neue Tabelle')) ?></a>
  <?php endif; ?>
</nav>
