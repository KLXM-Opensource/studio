<?php
/*
 * Helfer des Kits „NativeCSS“. Alles Übrige (fluid_head, fluid_image, fluid_buttons …) kommt aus kits/fluid/functions.php.
 * Präfix native_ – Funktionen nach Namenskonvention (native_html_class …) gelten für Kits, die von diesem erben (Theme::kitFunction).
 */

use Core\Kit;

/**
 * Block-Vorlage des Eltern-Kits (fluid) für Varianten, die dieses Kit nicht selbst gestaltet – zum Einbinden in der View:
 *   require native_parent_view('hero'); return;
 * Meldet '@fluid-{typ}' (Theme::markUsed), damit das CSS von fluid für diese Ausgabe lädt (theme.php → conditional_css).
 */
function native_parent_view(string $type): string
{
    app()->theme->markUsed('@fluid-' . $type);
    $chain = Kit::chain(app()->theme->name);
    $after = false;
    foreach ($chain as $name => $dir) {
        if ($after && is_file($dir . '/blocks/' . $type . '.php')) return $dir . '/blocks/' . $type . '.php';
        if ($name === 'native') $after = true;
    }
    throw new \RuntimeException('Block „' . $type . '“ fehlt im Eltern-Kit von „native“.');
}

/** Darstellung dieses Kits für einen Block melden: CSS css/nv-{typ}.css lädt mit (theme.php → conditional_css '@nv-{typ}') */
function native_mark(string ...$keys): void
{
    app()->theme->markUsed(...$keys);
}

/** Bewegung im Design an („Dezente Animationen beim Scrollen“) und nicht beim Bearbeiten – für Hochzählen, Laufband, Zeitleiste */
function native_motion(): bool
{
    return !is_editing() && !in_array(design('motion'), [false, 0, '0'], true);
}

/** Darstellungs-Knopf für Besucher (<ncss-preferences>, Design → „Bewegung & Farbschema“) – Standard aus */
function native_prefs(): bool
{
    return (bool) design('visitor_prefs') || (bool) setting('visitor_prefs', false);
}

/** <ncss-preferences> mit den angebotenen Einstellungen (Farbschema nur mit „Dunkles Farbschema“, Lese-Schrift abschaltbar) */
function native_prefs_element(string $class): string
{
    $settings = array_filter(['theme' => design('dark') ? 'theme' : '', 'contrast', 'motion', 'text',
        'font' => design('visitor_prefs_fonts') !== false ? 'font' : '', 'spacing', 'links', 'focus']);
    // Einleitung in der Anrede der Website (NativeCSS duzt) – übrige Texte wie in NativeCSS (de/en nach <html lang>)
    $intro = lt('Passen Sie die Darstellung an Ihre Bedürfnisse an. Solange Sie nichts ändern, gelten die Einstellungen Ihres Geräts.');
    return '<ncss-preferences class="' . e($class) . '" settings="' . e(implode(' ', $settings)) . '" data-label-intro="' . e($intro) . '"></ncss-preferences>';
}

/**
 * Klasse am äußersten Element eines Bildes aus img() – meist <picture> (AVIF/WebP), sonst <img>. Für Komponenten von NativeCSS,
 * die das Bild als direktes Kind erwarten (z. B. <ncss-compare> > .ncss-compare-before).
 */
function native_outer_class(string $html, string $class): string
{
    return (string) preg_replace_callback('~^(\s*<(?:picture|img)\b)([^>]*)>~', static function (array $m) use ($class): string {
        if (preg_match('~\sclass="([^"]*)"~', $m[2])) return $m[1] . preg_replace('~\sclass="([^"]*)"~', ' class="$1 ' . e($class) . '"', $m[2], 1) . '>';
        return $m[1] . ' class="' . e($class) . '"' . $m[2] . '>';
    }, $html, 1);
}

/** Mindestbreite je Eintrag im Raster (min-s | min-m | min-l, Klassen von fluid) */
function native_min(array $d): string
{
    return fluid_min($d);
}

/**
 * Kennzahl zum Hochzählen (.ncss-count-up von NativeCSS): Es zählt, was nach einer Zahl aussieht („25+“, „98 %“, „12.500“,
 * „€ 40“), nicht Jahreszahlen und Werte wie „24/7“ oder „3,5“. Die echte Zahl bleibt als Text stehen (Screenreader, ohne Skript,
 * „Bewegung reduzieren“). Zielwert, Präfix und Suffix als data-Attribute – js/ncss-count-up.js setzt daraus die CSS-Variablen
 * (CSSOM, keine style-Attribute). Liefert [html, zählt?].
 * @return array{0: string, 1: bool}
 */
function native_count(string $raw): array
{
    $raw = trim($raw);
    if (!native_motion() || !preg_match('~^(\D{0,3}?)(\d{1,3}(?:[.\x{202F}\x{00A0} ]\d{3})+|\d{1,7})(\D{0,6})$~u', $raw, $m)
        || preg_match('~^[,./:]~', $m[3]) || preg_match('~[;"\\\\{}<>]~', $m[1] . $m[3])) return [e($raw), false];
    $n = (int) preg_replace('~\D~', '', $m[2]);
    if (($m[1] === '' && trim($m[3]) === '' && $n >= 1800 && $n <= 2100) || $n < 2) return [e($raw), false];   // Jahreszahl, 0/1
    return ['<span class="ncss-count-up" data-ncss-count-up data-nv-count="' . $n . '"' . ($m[1] !== '' ? ' data-nv-prefix="' . e($m[1]) . '"' : '')
        . ($m[3] !== '' ? ' data-nv-suffix="' . e($m[3]) . '"' : '') . '>' . e($raw) . '</span>', true];
}
