<?php
declare(strict_types=1);

namespace Core;

/**
 * Hinweisbalken der Website (theme.php 'project' => ['notice' => ['text' => …, 'active' => …]]): Zeitraum und Darstellung.
 *
 * Der Core ergänzt im Einstellungsformular unter dem Hinweistext diese Felder: notice_from / notice_until (Zeitraum, leer = sofort
 * bzw. unbegrenzt) und notice_style ('' = wie im Design, 'left', 'center', 'bubble' / 'bubble-center' / 'bubble-middle' = schwebende Bubble unten links, unten mittig bzw. in der Bildschirmmitte, schließbar),
 * notice_color (Signalfarben statt Kit-Farben) und notice_fx (pulse, wiggle, glow – kurz, still bei „Bewegung reduzieren“).
 * Kits rendern den Balken weiter selbst, öffnen ihn aber mit notice_open('topnote') und rufen notice_late('topnote') vor </body>:
 *
 *   <?php if (notice_on()): ?><?= notice_open('topnote') ?><div class="wrap">…</div></div><?php endif; ?>
 *   …
 *   <?= notice_late('topnote') ?>
 *
 * Seiten-Cache: Der Zeitraum steht zusätzlich als data-notice-from/-until am Element; notice.js blendet ein bzw. aus
 * (CSP ohne Inline-Skripte → eigene Dateien, nur geladen, wenn Zeitraum, Ausrichtung oder Bubble gesetzt sind).
 */
final class Notice
{
    public const STYLES = ['', 'left', 'center', 'bubble', 'bubble-center', 'bubble-middle'];

    private static bool $assets = false;

    public static function has(): bool
    {
        return (bool) project('notice.text');
    }

    /** Zusatzfelder fürs Einstellungsformular (hinter dem Textfeld des Kits) */
    public static function fields(): array
    {
        return [
            ['name' => 'notice_from', 'label' => __('Anzeigen ab'), 'type' => 'datetime', 'translate' => false, 'width' => 'half',
                'help' => __('Leer = sofort. Der Hinweis erscheint automatisch zu diesem Zeitpunkt.')],
            ['name' => 'notice_until', 'label' => __('Anzeigen bis'), 'type' => 'datetime', 'translate' => false, 'width' => 'half',
                'help' => __('Leer = bis Sie ihn ausschalten. Danach verschwindet er von selbst.')],
            ['name' => 'notice_style', 'label' => __('Darstellung'), 'type' => 'select', 'translate' => false, 'default' => '',
                'options' => ['' => __('Balken oben (wie im Design)'), 'left' => __('Balken oben, linksbündig'), 'center' => __('Balken oben, zentriert'),
                    'bubble' => __('Schwebende Bubble (unten links, schließbar)'), 'bubble-center' => __('Schwebende Bubble (unten mittig, schließbar)'),
                    'bubble-middle' => __('Schwebende Bubble (Bildschirmmitte, schließbar)')]],
            ['name' => 'notice_color', 'label' => __('Farbe'), 'type' => 'select', 'translate' => false, 'default' => '', 'width' => 'half',
                'options' => ['' => __('Wie im Design'), 'yellow' => __('Signalgelb'), 'red' => __('Rot'), 'green' => __('Grün'), 'blue' => __('Blau'),
                    'dark' => __('Schwarz'), 'light' => __('Weiß')]],
            ['name' => 'notice_fx', 'label' => __('Hervorheben'), 'type' => 'select', 'translate' => false, 'default' => '', 'width' => 'half',
                'options' => ['' => __('Ruhig'), 'pulse' => __('Pulsieren'), 'wiggle' => __('Kurz wackeln'), 'glow' => __('Leuchten')],
                'help' => __('Die Bewegung läuft nur ein paar Sekunden und entfällt, wenn Besucher „Bewegung reduzieren“ eingestellt haben.')],
        ];
    }

    /** Fügt fields() hinter dem Hinweistext des Kits ein (Theme::settingsGroups) */
    public static function extendGroups(array $groups): array
    {
        $key = (string) project('notice.text', '');
        if ($key === '') return $groups;
        foreach ($groups as $gi => $g) {
            foreach ($g['fields'] ?? [] as $fi => $f) {
                if (($f['name'] ?? '') !== $key) continue;
                $have = array_column($g['fields'], 'name');
                $add = array_values(array_filter(self::fields(), fn($x) => !in_array($x['name'], $have, true)));
                array_splice($groups[$gi]['fields'], $fi + 1, 0, $add);
                return $groups;
            }
        }
        return $groups;
    }

    private static function ts(string $key): ?int
    {
        $v = trim((string) setting($key, ''));
        if ($v === '') return null;
        $t = strtotime(str_replace('T', ' ', $v));
        return $t === false ? null : $t;
    }

    /** Text (Inline-HTML) oder '' */
    public static function text(): string
    {
        if (!self::has()) return '';
        $t = (string) setting((string) project('notice.text'), '');
        return trim(strip_tags($t)) === '' ? '' : $t;
    }

    public static function style(): string
    {
        $s = (string) setting('notice_style', '');
        return in_array($s, self::STYLES, true) ? $s : '';
    }

    public const COLORS = ['', 'yellow', 'red', 'green', 'blue', 'dark', 'light'];
    public const EFFECTS = ['', 'pulse', 'wiggle', 'glow'];

    private static function pick(string $key, array $allowed): string
    {
        $v = (string) setting($key, '');
        return in_array($v, $allowed, true) ? $v : '';
    }

    /** Farbe und Hervorhebung als data-Attribute (notice.css) */
    private static function lookAttrs(): string
    {
        $c = self::pick('notice_color', self::COLORS);
        $fx = self::pick('notice_fx', self::EFFECTS);
        return ($c !== '' ? ' data-notice-color="' . $c . '"' : '') . ($fx !== '' ? ' data-notice-fx="' . $fx . '"' : '');
    }

    /** Eingeschaltet, Text vorhanden und Zeitraum nicht abgelaufen (Beginn darf in der Zukunft liegen → per JS) */
    public static function pending(): bool
    {
        $activeKey = (string) project('notice.active', '');
        if (self::text() === '' || ($activeKey !== '' && !setting($activeKey))) return false;
        $until = self::ts('notice_until');
        return $until === null || $until > time();
    }

    /** Jetzt sichtbar (für API/MCP/Vorschau) */
    public static function visible(): bool
    {
        $from = self::ts('notice_from');
        return self::pending() && ($from === null || $from <= time());
    }

    /** Balken im Kit-Layout rendern? (Bubble rendert der Core in late()) */
    public static function bar(): bool
    {
        return self::pending() && !self::isBubble();
    }

    private static function isBubble(): bool
    {
        return str_starts_with(self::style(), 'bubble');
    }

    private static function timeAttrs(): string
    {
        $out = '';
        $from = self::ts('notice_from');
        $until = self::ts('notice_until');
        if ($from !== null) $out .= ' data-notice-from="' . ($from * 1000) . '"';
        if ($until !== null) $out .= ' data-notice-until="' . ($until * 1000) . '"';
        if ($from !== null && $from > time()) $out .= ' hidden';
        return $out;
    }

    private static function needsAssets(): bool
    {
        return self::style() !== '' || self::ts('notice_from') !== null || self::ts('notice_until') !== null || self::lookAttrs() !== '';
    }

    private static function assets(): string
    {
        if (self::$assets || !self::needsAssets()) return '';
        self::$assets = true;
        return '<link rel="stylesheet" href="' . e(asset('css/notice.css')) . '">';
    }

    /** Öffnendes Element des Balkens (Klasse des Kits), inkl. Ausrichtung und Zeitraum */
    public static function open(string $class = 'topnote', string $tag = 'div'): string
    {
        $tag = preg_match('~^[a-z]+$~', $tag) ? $tag : 'div';
        $align = in_array(self::style(), ['left', 'center'], true) ? ' data-notice-align="' . self::style() . '"' : '';
        return self::assets() . '<' . $tag . ' class="' . e($class) . '" role="note" data-notice' . $align . self::lookAttrs() . self::timeAttrs() . '>';
    }

    /** Vor </body>: Bubble (Kit-Klasse für Farben) und Skript für Zeitraum/Schließen */
    public static function late(string $class = 'topnote'): string
    {
        if (!self::pending() || !self::needsAssets()) return '';
        $html = self::assets();
        if (self::isBubble()) {
            $id = substr(md5(self::text() . '|' . setting('notice_from', '') . '|' . setting('notice_until', '')), 0, 10);
            $html .= '<aside class="' . e($class) . ' cms-notice-bubble' . (self::style() !== 'bubble' ? ' cms-notice-bubble--' . substr(self::style(), 7) : '') . '" role="note" aria-label="' . e(lt('Hinweis')) . '" data-notice data-notice-id="' . $id . '"' . self::lookAttrs() . self::timeAttrs() . '>'
                . '<div class="cms-notice-bubble__text">' . inline(self::text()) . '</div>'
                . '<button type="button" class="cms-notice-bubble__close" data-notice-close aria-label="' . e(lt('Hinweis schließen')) . '">'
                . '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true"><path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button></aside>';
        }
        return $html . '<script src="' . e(asset('js/notice.js')) . '" defer></script>';
    }
}
