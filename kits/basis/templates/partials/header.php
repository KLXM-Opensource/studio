<?php
/**
 * Kopfbereich – Variante aus Verwaltung → Design → Navigation (design('nav')):
 *   modern    header-modern.php    schwebende Leiste, optional transparent über dem ersten Abschnitt
 *   classic   header-bar.php       Infoleiste (optional), Logo links, Menü rechts mit Aufklappmenüs
 *   minimal   header-minimal.php   Logo + Menü-Schaltfläche → Vollbild-Menü
 *   extended  header-bar.php       wie klassisch, Unterseiten als Mega-Menü mit Beschreibungen
 * Alle Varianten: Tastatur (Tab, Pfeile, Escape), aria-expanded, ohne JavaScript nutzbar (details/summary).
 * Aktionen (Suche, Handlungsaufruf, Kontakt-Chip, Sprache): header_actions() – Stil und Anordnung aus dem Style-Editor.
 * @var ?array $over  Ergebnis von basis_over_hero() (nur „Modern“)
 */
$variant = (string) design('nav');
$langs = language_links();
$vars = [
    'menu' => \Core\HeaderActions::menu(basis_menu()),   // Stil „Menüpunkt“: Ziel des Handlungsaufrufs nicht doppelt im Menü
    'langs' => $langs,
    'brandHref' => !empty(app()->currentPage['is_home']) ? '#main' : url(\Core\Lang::prefix(\Core\Lang::current()) . '/'),
    'over' => $over ?? '',
    // Kopfbereich-Aktionen (Design → „Kopfbereich: Suche & Aktionen“, Core\HeaderActions): Suche, Handlungsaufruf, Kontakt-Chip …
    // bar = in der Leiste (mobil im Menü), center = Suche mittig, below = Suchleiste unter dem Kopf
    'actions' => header_actions('bar', ['lang' => $variant === 'minimal' ? [] : $langs,
        'lang_class' => design('topbar') && in_array($variant, ['classic', 'extended'], true) ? 'langswitch--mobile' : '']),
    'center' => header_actions('center'),
    'below' => header_actions('below', ['wrap' => 'wrap']),
    // Feld der Website-Suche im Mobil-/Vollbild-Menü
    'searchMenu' => \Core\Search\Search::form('menu'),
];
echo match ($variant) {
    'modern' => app()->theme->partial('header-modern', $vars),
    'minimal' => app()->theme->partial('header-minimal', $vars),
    'extended' => app()->theme->partial('header-bar', $vars + ['mode' => 'mega', 'variant' => 'extended']),
    default => app()->theme->partial('header-bar', $vars + ['mode' => 'dropdown', 'variant' => 'classic']),
};
