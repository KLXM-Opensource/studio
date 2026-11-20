<?php
/**
 * Kopfleiste „NativeCSS“ (.ncss-topbar): Marke, Hauptmenü (.ncss-nav) und Werkzeuge in EINER Zeile. Passt das Menü nicht
 * hinein, öffnet die Menü-Schaltfläche (NativeCSS js/nav.js misst – kein fester Breakpoint, ohne Skript 64rem)
 * dasselbe Markup als Off-Canvas-<dialog> (command="show-modal" – ohne Skript in aktuellen Browsern, js/native.js ergänzt den
 * Ersatz, „Klick daneben schließt“ und das Schließen nach einem Link); Untermenüs als <details> mit „Übersicht“ als erstem
 * Eintrag (Muster von NativeCSS). Escape schließt den Dialog, der Fokus kehrt zur Schaltfläche zurück (Browser).
 * Werkzeuge: Kopfbereich-Aktionen (Suche, Handlungsaufruf …, Core\HeaderActions), Sprachen (ab drei als Aufklappmenü – Kern),
 * Hell/Dunkel-Schalter (Design), Darstellungs-Knopf für Besucher (Design, Standard aus).
 * @var bool $prefs
 */
$menu = \Core\HeaderActions::menu(fluid_menu());   // Stil „Menüpunkt“: Ziel des Handlungsaufrufs nicht doppelt im Menü
$langs = language_links();
$actions = header_actions('bar', ['compact' => true]);
$prefs = !empty($prefs);
$brandHref = !empty(app()->currentPage['is_home']) ? '#main' : url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$cur = static fn(array $m): string => fluid_is_current($m) ? ' aria-current="page"' : '';
// Menüpunkt: Link oder Untermenü (details) mit „Übersicht: …“ als erstem Eintrag; dritte Ebene eingerückt im Untermenü
$sub = static function (array $items, int $depth) use (&$sub, $cur): string {
    $h = '';
    foreach ($items as $c) {
        $h .= '<li' . ($depth > 2 ? ' class="nv-sub--deep"' : '') . '><a href="' . e($c['href']) . '"' . $cur($c) . '>' . e($c['label']) . '</a></li>';
        if ($c['children']) $h .= $sub($c['children'], $depth + 1);
    }
    return $h;
};
$item = static function (array $m) use ($cur, $sub): string {
    if (!$m['children']) {
        return '<li class="ncss-nav-item"><a href="' . e($m['href']) . '"' . $cur($m) . ($m['active'] ? ' data-active' : '') . '>' . e($m['label']) . '</a></li>';
    }
    return '<li class="ncss-nav-item"><details class="ncss-nav-dropdown"' . ($m['active'] ? ' data-active' : '') . '><summary>' . e($m['label']) . '</summary>'
        . '<ul class="ncss-nav-submenu"><li><a href="' . e($m['href']) . '"' . $cur($m) . '>' . e(lt('Übersicht: {name}', ['name' => $m['label']])) . '</a></li>'
        . $sub($m['children'], 2) . '</ul></details></li>';
};
?>
<header class="ncss-topbar nv-topbar" data-header>
  <div class="ncss-topbar-inner">
    <?= app()->theme->partial('brand', ['href' => $brandHref, 'class' => 'ncss-topbar-brand nv-brand']) ?>
    <?= header_actions('center') ?>
    <?php if ($menu): ?>
    <nav class="ncss-nav nv-nav" aria-label="<?= e(lt('Hauptnavigation')) ?>">
      <button type="button" class="ncss-nav-toggle nv-nav__toggle" command="show-modal" commandfor="nv-menu" aria-haspopup="dialog"><?= icon('list') ?><span class="sr-only"><?= e(lt('Menü')) ?></span></button>
      <dialog id="nv-menu" class="ncss-nav-panel ncss-offcanvas ncss-offcanvas--start" aria-label="<?= e(lt('Menü')) ?>">
        <button type="button" class="ncss-dialog-close nv-nav__close" command="close" commandfor="nv-menu"><?= icon('x') ?><span class="sr-only"><?= e(lt('Menü schließen')) ?></span></button>
        <ul class="ncss-nav-list" role="list"><?= implode('', array_map($item, $menu)) ?></ul>
        <?php if ($langs): ?><?= header_actions_lang($langs, 'nv-nav__langs') ?><?php endif; ?>
        <?php if ($cta = fluid_header_cta()): // Handlungsaufruf: in der Leiste nur breit, im Menü wie bei fluid (mnav__cta) ?><a class="btn btn--primary nv-nav__cta" <?= fluid_link_attrs($cta['link']) ?>><?= e($cta['label']) ?></a><?php endif; ?>
        <?php if (($quick = fluid_quick_contact('nv-nav__quick')) !== ''): ?><?= $quick ?><?php endif; ?>
        <?= $prefs ? native_prefs_element('nv-nav__prefs') : '' ?>
      </dialog>
    </nav>
    <?php endif; ?>
    <div class="ncss-topbar-actions nv-tools">
      <?= $actions ?>
      <?php if ($langs): ?><?= header_actions_lang($langs, 'nv-langs') ?><?php endif; ?>
      <?= $prefs ? native_prefs_element($menu ? 'nv-prefs' : 'nv-prefs nv-prefs--always') : '' ?>
      <?php if (fluid_scheme_toggle()): ?><button type="button" class="scheme-btn" data-scheme-toggle aria-pressed="false" data-label-dark="<?= e(lt('Dunkle Darstellung')) ?>" data-label-light="<?= e(lt('Helle Darstellung')) ?>" aria-label="<?= e(lt('Dunkle Darstellung')) ?>"><?= icon('moon', ['class' => 'scheme-btn__moon']) ?><?= icon('sun', ['class' => 'scheme-btn__sun']) ?></button><?php endif; ?>
    </div>
  </div>
  <?= header_actions('below', ['wrap' => 'hdr__below']) ?>
</header>
