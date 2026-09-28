<?php
/**
 * Seitenbaum im Finder-Stil (Listenansicht mit Aufklapp-Dreiecken).
 * Ziehen: auf eine Seite = als Unterseite, an den oberen/unteren Rand = davor/dahinter.
 * @var array $tree  @var array $templates  @var string $lang
 */
use Core\Lang;
use Core\Pages;
$multi = Lang::multi();

$count = 0;
$row = function (array $n) use (&$row, &$count, $multi): string {
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
        . ' data-published="' . (int) ($p['content_published'] !== null) . '" data-langs="' . e(implode(',', $trans)) . '" aria-level="' . ($n['depth'] + 1) . '"'
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
        . '<span class="pt-status"><span class="dt-status dt-status--' . ($p['status'] === 'published' ? 'published' : 'draft') . '">' . ($p['status'] === 'published' ? 'Online' : 'Entwurf') . '</span>'
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
  <div><p class="adm-eyebrow">Inhalte</p><h1>Seiten</h1>
    <p class="adm-muted">Seiten per Ziehen ordnen: auf eine andere Seite ziehen = Unterseite, zwischen zwei Seiten = Reihenfolge. Doppelklick öffnet den Editor, Rechtsklick weitere Aktionen.</p></div>
  <div class="adm-row">
    <?php if (\Core\AI\Assist::available('text') && can('pages.manage')): ?><a class="adm-btn kia-btn" href="<?= e(url('/admin/ai/seiten')) ?>"><span class="kia-spark" aria-hidden="true"><?= icon('sparkle') ?></span> <?= e(__('Seite generieren')) ?></a><?php endif; ?>
    <a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/pages/new' . ($multi ? '?lang=' . $lang : ''))) ?>">+ Neue Seite</a>
  </div>
</header>

<div class="pt" data-pagetree data-base="<?= e(url('/admin/pages')) ?>" data-languages="<?= e(json_encode($multi ? Lang::all() : [], JSON_UNESCAPED_UNICODE)) ?>" data-lang="<?= e($lang) ?>">
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

<?php if ($templates): ?>
<section class="adm-card pt-templates">
  <h2>Detailseiten-Vorlagen</h2>
  <p class="adm-muted">Gelten für alle Einträge einer Datentabelle und werden unter <a href="<?= e(url('/admin/data')) ?>">Daten</a> gestaltet.</p>
  <ul class="adm-list">
    <?php foreach ($templates as $tp): $tbl = \Core\Data\Tables::find((string) $tp['template_for']); ?>
    <li><span><span class="pt-icon pt-icon--tpl" aria-hidden="true"></span> <?= e($tp['title']) ?><?php if ($tbl && $tbl['settings']['route']): ?> <small>/<?= e($tbl['settings']['route']) ?>/…</small><?php endif; ?></span>
      <?php if ($tbl): ?><form method="post" action="<?= e(url('/admin/data/' . $tbl['handle'] . '/template')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost">Gestalten</button></form><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
