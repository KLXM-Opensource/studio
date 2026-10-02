<?php
/**
 * Seitenbaum im Finder-Stil (Listenansicht mit Aufklapp-Dreiecken).
 * Ziehen: auf eine Seite = als Unterseite, an den oberen/unteren Rand = davor/dahinter.
 * @var array $tree  @var array $templates  @var array $notFound  @var string $lang
 */
use Core\Lang;
use Core\Pages;
$multi = Lang::multi();

$count = 0;
$canPub = can('pages.publish');
// Status: Online | Offline (war schon online) | Entwurf (nie veröffentlicht). Mit pages.publish ein Knopf zum Umschalten
// (admin.js → Seitenbaum: Offline nehmen mit Rückfrage, Online stellen über Pages::publish samt Platzhalter-Sperre)
$statusCell = function (array $p) use ($canPub): string {
    $st = Pages::state($p);
    $label = ['online' => __('Online'), 'offline' => __('Offline'), 'draft' => __('Entwurf')][$st];
    $cls = 'dt-status dt-status--' . ['online' => 'published', 'offline' => 'offline', 'draft' => 'draft'][$st];
    if (!$canPub || $p['is_home']) {
        return '<span class="' . $cls . '">' . e($label) . '</span>';
    }
    $act = $st === 'online' ? __('Offline nehmen') : ($st === 'offline' ? __('Online stellen') : __('Veröffentlichen'));
    return '<button type="button" class="pt-stbtn ' . $cls . '" data-status-toggle data-state="' . $st . '"'
        . ' aria-label="' . e(__('„{title}“: {status} – {action}', ['title' => $p['title'], 'status' => $label, 'action' => $act])) . '" title="' . e($act) . '">'
        . e($label) . '</button>';
};
$row = function (array $n) use (&$row, &$count, $multi, $statusCell): string {
    $p = $n['page'];
    $count++;
    $url = Pages::url($p);
    $dirty = $p['content_published'] !== null && \Core\Review\Drafts::pageChanged($p);   // schon veröffentlicht, Entwurf mit anderen Blöcken offen
    $kids = $n['children'];
    $id = (int) $p['id'];
    $trans = $multi ? array_keys(Pages::translations($p)) : [];
    $ext = \Core\Extensions::pageList($p);   // Erweiterungen (Extension::pageList): Hinweise + Kontextmenü
    $h = '<li class="pt-node" role="treeitem" id="pt-' . $id . '" data-id="' . $id . '" data-parent="' . (int) ($p['parent_id'] ?? 0) . '"'
        . ' data-url="' . e($url) . '" data-title="' . e($p['title']) . '" data-home="' . (int) $p['is_home'] . '" data-dirty="' . (int) $dirty . '"'
        . ' data-published="' . (int) ($p['content_published'] !== null) . '" data-state="' . Pages::state($p) . '" data-langs="' . e(implode(',', $trans)) . '" aria-level="' . ($n['depth'] + 1) . '"'
        . ($ext['actions'] ? ' data-ext-actions="' . json_attr(array_map(fn($a) => [$a['label'], url($a['href'])], $ext['actions'])) . '"' : '')
        . ($kids ? ' aria-expanded="true"' : '') . ' aria-selected="false">'
        . '<div class="pt-row" draggable="' . ($p['is_home'] ? 'false' : 'true') . '">'
        . '<span class="pt-name" style="--depth:' . $n['depth'] . '">'
        . ($kids ? '<button type="button" class="pt-twisty" tabindex="-1" aria-hidden="true" data-toggle></button>' : '<span class="pt-twisty pt-twisty--none"></span>')
        . '<span class="pt-icon pt-icon--' . ($p['is_home'] ? 'home' : ($kids ? 'folder' : 'page')) . '" aria-hidden="true"></span>'
        . '<a class="pt-title" href="' . e($url) . '?edit=1" tabindex="-1">' . e($p['title']) . '</a>'
        . ($multi ? '<span class="pt-langs">' . implode('', array_map(fn($l) => '<span class="pt-lang' . (in_array($l, $trans, true) ? ' is-on' : '') . '" title="' . e(Lang::all()[$l]) . '">' . e(strtoupper($l)) . '</span>', array_keys(Lang::all()))) . '</span>' : '')
        . '</span>'
        . '<span class="pt-path">' . e($p['is_home'] ? '/' : '/' . $p['path']) . '</span>'
        . '<span class="pt-status">' . $statusCell($p)
        // Veröffentlichte Seite mit offenem Entwurf (Verwaltung → Entwürfe, Core\Review\Drafts)
        . ($dirty ? ' <span class="pt-draft" title="' . e(__('Unveröffentlichte Änderungen – unter „Entwürfe“ vergleichen und veröffentlichen')) . '">' . e(__('Entwurf offen')) . '</span>' : '')
        . implode('', array_map(fn($b) => ' <span class="pt-ext pt-ext--' . e($b['tone']) . '"' . ($b['title'] !== '' ? ' title="' . e($b['title']) . '"' : '') . '>' . e($b['label']) . '</span>', $ext['badges'])) . '</span>'
        . '<span class="pt-menu">' . ($p['is_home'] ? '' : '<label class="pt-switch" title="Im Hauptmenü zeigen"><input type="checkbox" data-menu' . ($p['menu'] ? ' checked' : '') . ' aria-label="„' . e($p['title']) . '“ im Menü zeigen"><span></span></label>') . '</span>'
        . '<span class="pt-date">' . e(date('d.m.Y', strtotime((string) $p['updated_at']))) . '</span>'
        . '<span class="pt-more"><button type="button" class="pt-morebtn" data-more aria-label="Aktionen für „' . e($p['title']) . '“">' . icon('dots-three') . '</button></span>'
        . '</div>';
    if ($kids) {
        $h .= '<ul role="group">' . implode('', array_map($row, $kids)) . '</ul>';
    }
    return $h . '</li>';
};
$html = implode('', array_map($row, $tree));
?>
<header class="adm-head">
  <div><p class="adm-eyebrow">Inhalte</p><div class="adm-titlerow"><h1>Seiten</h1>
    <details class="adm-tip"><summary class="adm-tip__btn" aria-label="<?= e(__('Hilfe: Seiten ordnen und bearbeiten')) ?>" title="<?= e(__('Hilfe')) ?>">?</summary>
      <div class="adm-tip__body">
        <ul>
          <li><?= e(__('Auf eine andere Seite ziehen = Unterseite, zwischen zwei Seiten = Reihenfolge, unter die letzte Zeile = ans Ende.')) ?></li>
          <li><?= e(__('Ohne Ziehen: Seite wählen und Alt + Pfeiltasten (oder „⋯“ → Nach oben/unten, Einrücken, Ausrücken).')) ?></li>
          <li><?= e(__('Doppelklick öffnet den Editor, Rechtsklick weitere Aktionen.')) ?></li>
        </ul>
        <?php if (can('pages.manage') || can('data.schema') || can('system.manage')): ?>
        <h3 class="adm-tip__h"><?= e(__('Sonderseiten & Vorlagen')) ?></h3>
        <ul>
          <?php if (can('pages.manage')): ?><li><?= e(__('„Nicht gefunden (404)“ erscheint, wenn Besucher eine Adresse aufrufen, die es nicht gibt – mit Status 404, nicht in Menü, Sitemap und Suche. Solange sie nicht veröffentlicht ist, zeigt die Website die Standard-Fehlerseite des Kits.')) ?></li><?php endif; ?>
          <?php if (can('system.manage')): ?><li><?= e(__('Seitenvorlagen sind der Ausgangspunkt für neue Seiten der Redaktion – ohne eigene Adresse, nur die Administration kann sie ändern.')) ?></li><?php endif; ?>
          <?php if (can('data.schema')): ?><li><?= e(__('Detailseiten-Vorlagen gelten für alle Einträge einer Datentabelle und werden unter „Daten“ gestaltet.')) ?></li><?php endif; ?>
        </ul>
        <?php endif; ?>
      </div>
    </details></div></div>
  <div class="adm-row">
    <?php if (\Core\AI\Assist::available('text') && can('pages.manage')): ?><a class="adm-btn kia-btn" href="<?= e(url('/admin/ai/seiten')) ?>"><span class="kia-spark" aria-hidden="true"><?= icon('sparkle') ?></span> <?= e(__('Seite generieren')) ?></a><?php endif; ?>
    <a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/pages/new' . ($multi ? '?lang=' . $lang : ''))) ?>">+ Neue Seite</a>
  </div>
</header>

<?php
// Reiter: Seitenbaum für alle mit Seitenrechten; „Sonderseiten & Vorlagen“ nur mit passendem Recht (404: pages.manage,
// Detailseiten-Vorlagen: data.schema, Seitenvorlagen: system.manage)
$canSpecial404 = can('pages.manage');
$canSpecial = $canSpecial404 || ($templates && can('data.schema')) || !empty($pageTemplates) || can('system.manage');
?>
<div class="pt-tabs" data-tabs>
<?php if ($canSpecial): ?>
<div class="adm-tabs pt-tabbar" role="tablist" aria-label="<?= e(__('Seiten')) ?>">
  <button type="button" role="tab" id="tab-baum" aria-controls="panel-baum" data-tab="baum" aria-selected="true"><?= e(__('Seitenbaum')) ?></button>
  <button type="button" role="tab" id="tab-sonderseiten" aria-controls="panel-sonderseiten" data-tab="sonderseiten" aria-selected="false" tabindex="-1"><?= e(__('Sonderseiten & Vorlagen')) ?></button>
</div>
<?php endif; ?>
<div id="panel-baum"<?= $canSpecial ? ' role="tabpanel" aria-labelledby="tab-baum"' : '' ?>>
<div class="pt" data-pagetree data-base="<?= e(url('/admin/pages')) ?>" data-can-publish="<?= $canPub ? '1' : '0' ?>" data-can-templates="<?= can('system.manage') ? '1' : '0' ?>" data-languages="<?= e(json_encode($multi ? Lang::all() : [], JSON_UNESCAPED_UNICODE)) ?>" data-lang="<?= e($lang) ?>">
  <div class="dt-bar pt-bar">
    <?php if ($multi): ?><nav class="fx-seg dt-seg" aria-label="Sprache"><?php foreach (Lang::all() as $code => $label): ?><a href="<?= e(url('/admin/pages?lang=' . $code)) ?>"<?= $code === $lang ? ' aria-current="true"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav><?php endif; ?>
    <label class="fx-search dt-search"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M7 2a5 5 0 1 0 3 9l3.3 3.3 1-1L11 10A5 5 0 0 0 7 2zm0 1.5a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7z"/></svg><input type="search" placeholder="Seiten filtern" aria-label="Seiten filtern" data-filter></label>
    <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-expand-all>Alle aufklappen</button>
    <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-collapse-all>Alle zuklappen</button>
  </div>
  <div class="pt-head" aria-hidden="true"><span>Name</span><span>Adresse</span><span>Status</span><span>Menü</span><span>Geändert</span><span></span></div>
  <ul class="pt-tree" role="tree" aria-label="Seitenbaum" tabindex="0"><?= $html ?></ul>
  <footer class="dt-foot"><span><?= $count ?> Seiten</span><span data-pt-msg aria-live="polite"></span></footer>
</div>

</div>
<?php if ($canSpecial): ?>
<div role="tabpanel" id="panel-sonderseiten" aria-labelledby="tab-sonderseiten" class="pt-special" hidden>
<?php if (can('system.manage') && empty($pageTemplates)): ?>
<section class="adm-card pt-templates">
  <h2><?= e(__('Seitenvorlagen')) ?></h2>
  <p><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/seitenvorlagen')) ?>"><?= e(__('Erste Vorlage anlegen')) ?></a></p>
</section>
<?php endif; ?>
<?php // Sonderseiten: „Nicht gefunden (404)“ je Sprache (Core\NotFound) – nie unter eigener Adresse öffentlich, nicht im Seitenbaum
$nfLangs = $multi ? Lang::all() : [Lang::default() => ''];
$nfDefault = $notFound[Lang::default()] ?? null; ?>
<?php if ($canSpecial404): ?>
<section class="adm-card pt-templates" aria-labelledby="pt-special-h">
  <h2 id="pt-special-h"><?= e(__('Sonderseiten')) ?></h2>
  <ul class="adm-list">
    <?php foreach ($nfLangs as $code => $label): $nf = $notFound[$code] ?? null; ?>
    <li><span><span class="pt-icon pt-icon--404" aria-hidden="true"></span> <?= e(__('Nicht gefunden (404)')) ?><?php if ($multi): ?> <span class="adm-badge"><?= e(strtoupper($code)) ?></span><?php endif; ?>
      <?php if ($nf): $nfDirty = $nf['content_published'] !== null && \Core\Review\Drafts::pageChanged($nf); ?>
        <small>/<?= e(ltrim(Lang::prefix($code) . '/' . $nf['path'], '/')) ?></small>
        <span class="dt-status dt-status--<?= $nf['status'] === 'published' ? 'published' : 'draft' ?>"><?= e($nf['status'] === 'published' ? __('Online') : __('Entwurf')) ?></span><?= $nfDirty ? ' <span class="pt-draft">' . e(__('Entwurf offen')) . '</span>' : '' ?>
      <?php elseif ($code !== Lang::default() && $nfDefault): ?>
        <small><?= e(__('zeigt die Seite der Standardsprache')) ?></small>
      <?php else: ?>
        <small><?= e(__('nicht angelegt – Standard-Fehlerseite des Kits')) ?></small>
      <?php endif; ?></span>
      <span class="adm-row">
      <?php if ($nf): ?>
        <a class="adm-btn adm-btn--small" href="<?= e(Pages::url($nf)) ?>?edit=1"><?= e(__('404-Seite bearbeiten')) ?></a>
        <?php if (can('pages.manage')): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/pages/' . (int) $nf['id'])) ?>"><?= e(__('Einstellungen')) ?></a><?php endif; ?>
      <?php elseif (can('pages.manage')): ?>
        <form method="post" action="<?= e(url('/admin/pages/nicht-gefunden')) ?>"><?= csrf_field() ?><input type="hidden" name="lang" value="<?= e($code) ?>">
          <button class="adm-btn adm-btn--small adm-btn--primary"><?= e($code !== Lang::default() && $nfDefault ? __('Übersetzung anlegen') : __('404-Seite anlegen')) ?></button></form>
      <?php endif; ?>
      </span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if (!empty($pageTemplates)): ?>
<section class="adm-card pt-templates" aria-labelledby="pt-pagetpl-h">
  <h2 id="pt-pagetpl-h"><?= e(__('Seitenvorlagen')) ?></h2>
  <p class="pt-cardlink"><a href="<?= e(url('/admin/seitenvorlagen')) ?>"><?= e(__('Anordnen und benennen')) ?></a></p>
  <ul class="adm-list">
    <?php foreach ($pageTemplates as $pt_): ?>
    <li><span><?= $pt_['icon'] !== '' ? '<span class="pt-tplico" aria-hidden="true">' . icon($pt_['icon']) . '</span>' : '<span class="pt-icon pt-icon--pagetpl" aria-hidden="true"></span>' ?> <?= e($pt_['label']) ?></span>
      <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(\Core\PageTemplates::editUrl($pt_['page'])) ?>"><?= e(__('Blöcke bearbeiten')) ?></a></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php if ($templates && can('data.schema')): ?>
<section class="adm-card pt-templates">
  <h2>Detailseiten-Vorlagen</h2>
  <ul class="adm-list">
    <?php foreach ($templates as $tp): $tbl = \Core\Data\Tables::find((string) $tp['template_for']); ?>
    <li><span><span class="pt-icon pt-icon--tpl" aria-hidden="true"></span> <?= e($tp['title']) ?><?php if ($tbl && $tbl['settings']['route']): ?> <small>/<?= e($tbl['settings']['route']) ?>/…</small><?php endif; ?></span>
      <?php if ($tbl): ?><form method="post" action="<?= e(url('/admin/data/' . $tbl['handle'] . '/template')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost">Gestalten</button></form><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
</div>
<?php endif; ?>
</div>
