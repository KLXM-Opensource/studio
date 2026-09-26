<?php
declare(strict_types=1);

namespace Core\Blocks;

/**
 * CSS eigener Blöcke: prüfen, auf .cblk-{schlüssel} begrenzen, verkleinern.
 *
 *  - Jeder Selektor wird zu „.cblk-x SELEKTOR“; „:scope“ meint den Block selbst, „:dark“ den Block auf dunklem Hintergrund
 *    (.cblk-x.is-dark). html, body, :root und Nachbar-Selektoren nach :scope (+, ~) sind ausgeschlossen – kein Durchsickern.
 *  - Erlaubt: @media, @supports, @container, @keyframes (Namen werden zu cblk-x-name). Verboten: @import, @font-face, @layer,
 *    @page, @property, @namespace …, url() außer Dateien der eigenen Mediathek (/media/…, /sites/…/media/…, /pools/…),
 *    expression(), behavior, -moz-binding, javascript:, Backslash-Escapes außerhalb von Zeichenketten, position: fixed.
 *  - Vorangestellt: Design-Brücke --cb-* (Akzent, Fläche, Linie, Radius …) aus den Design-Variablen des Themes
 *    (--cb-theme-* zum Überschreiben durch ein Theme, sonst --b-…, --f-…, --e-… bzw. praxis --c-…).
 * Ergebnis: statische Datei im Medienordner (Core\Blocks\Custom), nur auf Seiten mit dem Block geladen.
 */
final class Css
{
    public const MAX_SIZE = 24000;
    public const MAX_OUT = 16384;
    public const BEHAVIOURS = ['accordion', 'reel', 'lightbox'];

    private string $s = '';
    private int $i = 0;
    private int $n = 0;
    private int $line = 1;
    private string $root = '';
    private string $key = '';
    private array $kf = [];
    public array $warnings = [];

    /** @return array{css: string, warnings: list<string>} */
    public static function compile(string $css, string $key, array $behaviours = []): array
    {
        $c = new self();
        $c->key = $key;
        $c->root = '.' . Runtime::cls($key);
        $css = str_replace(["\r\n", "\r"], "\n", $css);
        if (strlen($css) > self::MAX_SIZE) throw new TemplateError(__('Das CSS ist zu groß (höchstens {n} Zeichen).', ['n' => self::MAX_SIZE]));
        if (!mb_check_encoding($css, 'UTF-8')) throw new TemplateError(__('Das CSS ist kein gültiges UTF-8.'));
        // Kommentare entfernen (Zeilen bleiben erhalten)
        $css = (string) preg_replace_callback('~/\*.*?\*/~s', fn($m) => str_repeat("\n", substr_count($m[0], "\n")), $css);
        if (str_contains($css, '/*')) throw new TemplateError(__('CSS-Kommentar ohne „*/“.'), substr_count(strstr($css, '/*', true), "\n") + 1);
        if (preg_match_all('~@(?:-webkit-)?keyframes\s+([A-Za-z_][\w-]*)~i', $css, $m)) {
            foreach ($m[1] as $name) $c->kf[$name] = Runtime::cls($key) . '-' . $name;
        }
        $c->s = $css;
        $c->n = strlen($css);
        $body = $c->rules(0);
        $out = self::tokens($c->root) . self::behaviourCss($c->root, $behaviours) . $body;
        if (strlen($out) > self::MAX_OUT) {
            throw new TemplateError(__('Das fertige CSS ist zu groß ({kb} KB, höchstens {max} KB) – bitte kürzen (Ladebudget der Website).', ['kb' => round(strlen($out) / 1024, 1), 'max' => self::MAX_OUT / 1024]));
        }
        return ['css' => $out, 'warnings' => $c->warnings];
    }

    /** Design-Brücke: neutrale Variablen aus den Design-Werten des Themes (Style-Editor wirkt weiter) */
    public static function tokens(string $root): string
    {
        // Reihenfolge: --cb-theme-* (Theme kann sie setzen), dann die Design-Variablen der mitgelieferten Themes, dann neutrale Werte
        return $root . '{--cb-accent:var(--cb-theme-accent,var(--b-a,var(--f-a,var(--e-a,var(--c-bordeaux,#0F766E)))));'
            . '--cb-on-accent:var(--cb-theme-on-accent,var(--b-a-on,var(--f-a-on,var(--e-a-on,#fff))));'
            . '--cb-surface:var(--cb-theme-surface,var(--b-card,var(--f-card,var(--e-card,transparent))));--cb-ink:currentColor;'
            . '--cb-line:var(--cb-theme-line,var(--b-line,var(--f-line,var(--e-line,var(--c-line,rgba(128,128,128,.35))))));'
            . '--cb-muted:var(--cb-theme-muted,var(--b-muted,var(--f-muted,var(--e-muted,var(--c-text-2,currentColor)))));'
            . '--cb-radius:var(--cb-theme-radius,var(--b-radius,var(--f-radius,var(--e-radius,var(--r-card,12px)))));--cb-gap:clamp(12px,2vw,24px)}'
            . $root . '.is-dark{--cb-surface:rgba(255,255,255,.07);--cb-line:rgba(255,255,255,.28);--cb-accent:var(--cb-theme-accent-dark,#F4F1EC);--cb-on-accent:#15171A;--cb-muted:currentColor}';
    }

    /** CSS der Verhalten (nur CSS – eigenes JavaScript gibt es in eigenen Blöcken nicht) */
    public static function behaviourCss(string $root, array $behaviours): string
    {
        $css = '';
        if (in_array('accordion', $behaviours, true)) {
            $css .= $root . ' details>summary{cursor:pointer}' . $root . ' summary:focus-visible{outline:2px solid currentColor;outline-offset:2px}';
        }
        if (in_array('reel', $behaviours, true)) {
            $css .= $root . ' .cb-reel{display:grid;grid-auto-flow:column;grid-auto-columns:minmax(min(80%,20rem),1fr);gap:var(--cb-gap);'
                . 'overflow-x:auto;overscroll-behavior-x:contain;scroll-snap-type:x mandatory;padding:0 0 .75rem;margin:0;list-style:none}'
                . $root . ' .cb-reel>*{scroll-snap-align:start}' . $root . ' .cb-reel:focus-visible{outline:2px solid currentColor;outline-offset:4px}'
                . '@media (prefers-reduced-motion:no-preference){' . $root . ' .cb-reel{scroll-behavior:smooth}}';
        }
        if (in_array('lightbox', $behaviours, true)) {
            $css .= $root . ' .cblk-zoom{display:block;cursor:zoom-in}' . $root . ' .cblk-zoom:focus-visible{outline:2px solid currentColor;outline-offset:3px}';
        }
        return $css;
    }

    private function err(string $msg, ?int $line = null): TemplateError
    {
        return new TemplateError($msg, $line ?? $this->line);
    }

    private function ws(): void
    {
        while ($this->i < $this->n && ctype_space($this->s[$this->i])) {
            if ($this->s[$this->i] === "\n") $this->line++;
            $this->i++;
        }
    }

    /** Liest bis zu einem der Endzeichen (Zeichenketten und Klammern werden übersprungen); Endzeichen bleibt stehen */
    private function readUntil(array $stops): array
    {
        $start = $this->i;
        $depth = 0;
        while ($this->i < $this->n) {
            $c = $this->s[$this->i];
            if ($c === '"' || $c === "'") {
                $this->skipString($c);
                continue;
            }
            if ($c === '(') $depth++;
            elseif ($c === ')') $depth = max(0, $depth - 1);
            elseif ($depth === 0 && in_array($c, $stops, true)) {
                return [substr($this->s, $start, $this->i - $start), $c];
            }
            if ($c === "\n") $this->line++;
            $this->i++;
        }
        return [substr($this->s, $start), ''];
    }

    private function skipString(string $q): void
    {
        $this->i++;
        while ($this->i < $this->n && $this->s[$this->i] !== $q) {
            if ($this->s[$this->i] === "\n") throw $this->err(__('Zeichenkette ohne schließendes Anführungszeichen.'));
            if ($this->s[$this->i] === '\\') $this->i++;
            $this->i++;
        }
        if ($this->i >= $this->n) throw $this->err(__('Zeichenkette ohne schließendes Anführungszeichen.'));
        $this->i++;
    }

    private function rules(int $depth, bool $keyframes = false): string
    {
        $out = '';
        while (true) {
            $this->ws();
            if ($this->i >= $this->n) {
                if ($depth > 0) throw $this->err(__('Es fehlt eine schließende „}“.'));
                return $out;
            }
            if ($this->s[$this->i] === '}') {
                if ($depth === 0) throw $this->err(__('„}“ ohne passende „{“.'));
                $this->i++;
                return $out;
            }
            $line = $this->line;
            [$prelude, $term] = $this->readUntil(['{', ';', '}']);
            $p = trim((string) preg_replace('~\s+~', ' ', $prelude));
            if ($p !== '' && $p[0] === '@') {
                $out .= $this->atRule($p, $term, $depth, $line);
                continue;
            }
            if ($term !== '{') {
                throw $this->err($term === '' ? __('Unvollständige Regel „{rule}“.', ['rule' => mb_substr($p, 0, 40)])
                    : __('Deklaration außerhalb einer Regel: „{rule}“ – bitte in einen Selektor { … } setzen.', ['rule' => mb_substr($p, 0, 40)]), $line);
            }
            if ($p === '') throw $this->err(__('Regel ohne Selektor.'), $line);
            $this->i++;
            $body = $this->block();
            $decls = $this->decls($body, $line);
            if ($keyframes) {
                if (!preg_match('~^(from|to|\d{1,3}(\.\d+)?%)(\s*,\s*(from|to|\d{1,3}(\.\d+)?%))*$~i', $p)) {
                    throw $this->err(__('In @keyframes sind nur from, to und Prozentangaben möglich.'), $line);
                }
                $out .= str_replace(' ', '', $p) . '{' . $decls . '}';
                continue;
            }
            $sels = array_map(fn($x) => $this->scopeSel($x, $line), self::splitTop($p, ','));
            if ($decls !== '') $out .= implode(',', $sels) . '{' . $decls . '}';
        }
    }

    /** Inhalt einer Regel bis zur passenden „}“ (verschachtelte Regeln sind nicht erlaubt) */
    private function block(): string
    {
        $start = $this->i;
        $startLine = $this->line;
        [$body, $term] = $this->readUntil(['}', '{']);
        if ($term === '{') throw $this->err(__('Verschachtelte Regeln (CSS-Nesting) werden nicht unterstützt – bitte ausschreiben.'));
        if ($term === '') throw $this->err(__('Es fehlt eine schließende „}“.'), $startLine);
        $this->i++;
        return $body;
    }

    private function atRule(string $p, string $term, int $depth, int $line): string
    {
        preg_match('~^@([a-zA-Z-]+)\s*(.*)$~s', $p, $m);
        $name = strtolower($m[1] ?? '');
        $arg = trim($m[2] ?? '');
        if ($name === 'charset') {
            if ($term === ';') $this->i++;
            return '';
        }
        if (in_array($name, ['media', 'supports', 'container'], true)) {
            if ($term !== '{') throw $this->err(__('@{name} braucht einen Block { … }.', ['name' => $name]), $line);
            if ($depth >= 2) throw $this->err(__('@-Regeln höchstens zweifach verschachteln.'), $line);
            if ($arg === '' || !preg_match('~^[a-zA-Z0-9\s(),:.\-<>=/%\'"]+$~', $arg) || stripos($arg, 'url(') !== false) {
                throw $this->err(__('Ungültige Bedingung für @{name}.', ['name' => $name]), $line);
            }
            $this->i++;
            return '@' . $name . ' ' . self::squash($arg) . '{' . $this->rules($depth + 1) . '}';
        }
        if ($name === 'keyframes' || $name === '-webkit-keyframes') {
            if ($term !== '{' || !preg_match('~^[A-Za-z_][\w-]*$~', $arg)) throw $this->err(__('@keyframes braucht einen Namen und einen Block.'), $line);
            if ($depth > 0) throw $this->err(__('@keyframes bitte auf oberster Ebene.'), $line);
            $this->i++;
            return '@keyframes ' . $this->kf[$arg] . '{' . $this->rules(1, true) . '}';
        }
        throw $this->err(match ($name) {
            'import' => __('@import ist nicht erlaubt (keine fremden Stylesheets).'),
            'font-face' => __('@font-face ist nicht erlaubt – bitte die Schriften des Kits nutzen (z. B. font-family: inherit).'),
            default => __('@{name} ist in eigenen Blöcken nicht erlaubt (möglich: @media, @supports, @container, @keyframes).', ['name' => $name]),
        }, $line);
    }

    /** Selektor auf den Block begrenzen */
    private function scopeSel(string $sel, int $line): string
    {
        $sel = trim((string) preg_replace('~\s+~', ' ', $sel));
        if ($sel === '') throw $this->err(__('Leerer Selektor.'), $line);
        if (preg_match('~[{};@\\\\<]~', $sel)) throw $this->err(__('Ungültiger Selektor „{sel}“.', ['sel' => $sel]), $line);
        if (preg_match('~(^|[\s>+\~,(])(html|body)(?![\w-])~i', $sel) || stripos($sel, ':root') !== false || stripos($sel, ':host') !== false) {
            throw $this->err(__('„{sel}“ würde auf die ganze Seite wirken – Selektoren gelten nur innerhalb des Blocks (:scope = der Block selbst).', ['sel' => $sel]), $line);
        }
        $base = null;
        $rest = '';
        if (preg_match('~^:(scope|dark)(?![\w-])(.*)$~', $sel, $m)) {
            $base = $m[1] === 'dark' ? $this->root . '.is-dark' : $this->root;
            $rest = $m[2];
        } elseif (preg_match('~^' . preg_quote($this->root, '~') . '(?![\w-])(.*)$~', $sel, $m)) {
            $base = $this->root;
            $rest = $m[1];
        }
        if (preg_match('~:(scope|dark)(?![\w-])~', $base === null ? $sel : $rest)) {
            throw $this->err(__(':scope und :dark nur am Anfang eines Selektors.'), $line);
        }
        if ($base !== null) {
            if (preg_match('~^\s*[+\~]~', $rest)) throw $this->err(__('Nachbar-Selektoren (+, ~) nach :scope würden außerhalb des Blocks wirken.'), $line);
            return self::minSel($base . $rest);
        }
        return self::minSel($this->root . ' ' . $sel);
    }

    private static function minSel(string $s): string
    {
        return (string) preg_replace('~\s*([>+\~,])\s*~', '$1', $s);
    }

    private function decls(string $body, int $line): string
    {
        $out = [];
        foreach (self::splitTop($body, ';') as $d) {
            $d = trim($d);
            if ($d === '') continue;
            if (!preg_match('~^(--[A-Za-z0-9_-]+|-?[a-zA-Z][a-zA-Z-]*)\s*:(.*)$~s', $d, $m)) {
                throw $this->err(__('Ungültige Deklaration „{d}“.', ['d' => mb_substr($d, 0, 60)]), $line);
            }
            $prop = str_starts_with($m[1], '--') ? $m[1] : strtolower($m[1]);
            $val = trim($m[2]);
            if ($val === '') throw $this->err(__('„{prop}“ hat keinen Wert.', ['prop' => $prop]), $line);
            $this->checkValue($prop, $val, $line);
            if (in_array($prop, ['animation', 'animation-name'], true) && $this->kf) {
                $val = (string) preg_replace_callback('~(?<![\w-])([A-Za-z_][\w-]*)(?![\w-])~', fn($x) => $this->kf[$x[1]] ?? $x[1], $val);
            }
            $out[] = $prop . ':' . self::squash($val);
        }
        return implode(';', $out);
    }

    private function checkValue(string $prop, string $val, int $line): void
    {
        if (in_array($prop, ['behavior', '-ms-behavior', '-moz-binding'], true)) {
            throw $this->err(__('„{prop}“ ist nicht erlaubt.', ['prop' => $prop]), $line);
        }
        // Zeichenketten prüfen und für die weiteren Prüfungen ausblenden
        $outside = (string) preg_replace_callback('~"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\'\\\\]|\\\\.)*)\'~s', function ($m) use ($line) {
            $inner = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
            if (preg_match('~\\\\(?![0-9a-fA-F]{1,6}\s?)~', $inner)) {
                throw $this->err(__('In Zeichenketten sind nur Unicode-Escapes wie \\201C erlaubt.'), $line);
            }
            return '""';
        }, $val);
        if (str_contains($outside, '\\')) throw $this->err(__('Backslash-Escapes sind außerhalb von Zeichenketten nicht erlaubt.'), $line);
        if (preg_match('~[{}<]~', $outside)) throw $this->err(__('Ungültiges Zeichen im Wert von „{prop}“.', ['prop' => $prop]), $line);
        if (preg_match('~expression\s*\(|javascript\s*:|vbscript\s*:|-moz-binding|\bbehavior\b~i', $val)) {
            throw $this->err(__('Unsicherer Wert in „{prop}“.', ['prop' => $prop]), $line);
        }
        if (preg_match('~(?<![\w-])src\s*\(~i', $outside)) throw $this->err(__('src() ist nicht erlaubt – Bilder der Mediathek mit url(/media/…).'), $line);
        if (preg_match_all('~url\(~i', $val, $all)) {
            preg_match_all('~url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\')]*))\s*\)~i', $val, $urls, PREG_SET_ORDER);
            if (count($urls) !== count($all[0])) throw $this->err(__('Ungültige url(…).'), $line);
            foreach ($urls as $u) {
                $path = ($u[1] ?? '') . ($u[2] ?? '') . ($u[3] ?? '');
                if (!self::allowedUrl($path)) {
                    throw $this->err(__('url({url}) ist nicht erlaubt – nur Dateien der eigenen Mediathek (/media/…).', ['url' => mb_substr($path, 0, 60)]), $line);
                }
            }
        }
        if ($prop === 'position' && preg_match('~\bfixed\b~i', $outside)) {
            throw $this->err(__('position: fixed ist nicht erlaubt – der Block würde die Seite überdecken.'), $line);
        }
        if ($prop === 'z-index' && is_numeric(trim($outside)) && (int) $outside > 10) {
            $this->warnings[] = __('Zeile {n}: z-index über 10 kann Menüs und Dialoge der Website überdecken.', ['n' => $line]);
        }
    }

    /** Nur Dateien dieser Installation: Mediathek, Websites, Pools – ohne „..“ */
    public static function allowedUrl(string $path): bool
    {
        $base = rtrim(base_path(), '/');
        if ($base !== '' && str_starts_with($path, $base . '/')) $path = substr($path, strlen($base));
        return (bool) preg_match('~^/(media|sites/[a-z0-9_-]+/media|pools/[a-z0-9_-]+)/[A-Za-z0-9._/\-]+$~', $path) && !str_contains($path, '..');
    }

    /** Leerraum außerhalb von Zeichenketten zusammenfassen */
    private static function squash(string $v): string
    {
        $parts = preg_split('~("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')~s', $v, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $out = '';
        foreach ($parts as $k => $p) {
            $out .= $k % 2 ? $p : (string) preg_replace(['~\s+~', '~\s*,\s*~'], [' ', ','], $p);
        }
        return trim($out);
    }

    /** Auf oberster Ebene trennen (nicht in Klammern oder Zeichenketten) */
    private static function splitTop(string $s, string $sep): array
    {
        $out = [];
        $buf = '';
        $depth = 0;
        $q = '';
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($q !== '') {
                $buf .= $c;
                if ($c === '\\' && $i + 1 < $len) { $buf .= $s[++$i]; continue; }
                if ($c === $q) $q = '';
                continue;
            }
            if ($c === '"' || $c === "'") { $q = $c; $buf .= $c; continue; }
            if ($c === '(' || $c === '[') $depth++;
            elseif ($c === ')' || $c === ']') $depth = max(0, $depth - 1);
            if ($c === $sep && $depth === 0) { $out[] = $buf; $buf = ''; continue; }
            $buf .= $c;
        }
        $out[] = $buf;
        return $out;
    }
}
