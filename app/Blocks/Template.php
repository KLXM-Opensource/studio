<?php
declare(strict_types=1);

namespace Core\Blocks;

/**
 * Sichere Vorlagensprache für eigene Blöcke (Block-Designer) – nie PHP aus der Verwaltung.
 *
 *   {{ feld }}                      Ausgabe, immer escaped ({{{ … }}} gibt es nicht)
 *   {{ feld | filter('arg') }}      Filter (Core\Blocks\Runtime::FILTERS): rich, inline, image('sizes', '4:3'), icon, link, date('long'), default('…') …
 *   {% if a and not b %}…{% elseif x == 'y' %}…{% else %}…{% endif %}
 *   {% for item in items %}…{{ loop.index }}…{% else %}(leer){% endfor %}
 *   {# Kommentar #}
 *
 * Die Vorlage wird beim Übersetzen vollständig geprüft: HTML-Tags und -Attribute gegen eine Whitelist, ausgeglichene
 * Tags (auch je Zweig von if/for), Platzhalter nur in Text oder in Attributwerten mit Anführungszeichen (kontextgerechtes
 * Escaping: href/cite nur über sichere Links, class/id nur sichere Zeichen), keine Ereignis-/style-Attribute, keine
 * Skripte, kein SVG, kein <img> (Bilder nur über den Filter image). Grenzen: Größe, Verschachtelung, Schleifen.
 * Fehler: TemplateError mit Zeilennummer. Ausführung: Interpreter (run) – oder als PHP-Code für den Theme-Export (toPhp),
 * beide über Core\Blocks\Runtime und damit mit identischer Ausgabe.
 */
final class Template
{
    public const MAX_SIZE = 30000;
    public const MAX_DEPTH = 8;
    public const MAX_LOOP_DEPTH = 3;
    public const MAX_NODES = 3000;

    /** Erlaubte Elemente (void: ohne Schluss-Tag) */
    public const TAGS = ['div', 'span', 'p', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'a', 'strong', 'b', 'em', 'i', 'u', 's',
        'small', 'br', 'hr', 'wbr', 'figure', 'figcaption', 'blockquote', 'cite', 'q', 'details', 'summary', 'article', 'aside', 'header', 'footer',
        'section', 'time', 'mark', 'abbr', 'sub', 'sup', 'del', 'ins', 'code', 'pre', 'kbd', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'caption', 'colgroup', 'col', 'address', 'bdi'];
    public const VOID = ['br', 'hr', 'wbr', 'col'];
    public const GLOBAL_ATTRS = ['class', 'id', 'title', 'lang', 'dir', 'hidden', 'role', 'tabindex', 'translate'];
    public const TAG_ATTRS = [
        'a' => ['href', 'hreflang'], 'blockquote' => ['cite'], 'q' => ['cite'], 'del' => ['cite', 'datetime'], 'ins' => ['cite', 'datetime'],
        'time' => ['datetime'], 'ol' => ['start', 'reversed', 'type'], 'li' => ['value'], 'details' => ['open', 'name'],
        'td' => ['colspan', 'rowspan', 'headers'], 'th' => ['colspan', 'rowspan', 'headers', 'scope', 'abbr'], 'col' => ['span'], 'colgroup' => ['span'],
    ];
    public const URL_ATTRS = ['href', 'cite'];
    public const BOOL_ATTRS = ['hidden', 'open', 'reversed'];
    /** Reservierte data-Attribute (Editor, Kern-Skripte) */
    public const RESERVED_DATA = ['data-cms', 'data-edit', 'data-bound', 'data-central', 'data-media', 'data-ratio', 'data-srcset', 'data-caption',
        'data-w', 'data-h', 'data-entry', 'data-st', 'data-drawer', 'data-kia'];
    /** Elemente, in denen „rich“-Text direkt bearbeitbar ist */
    private const RICH_CONTAINERS = ['div', 'section', 'article', 'aside', 'header', 'footer', 'blockquote', 'figcaption', 'dd', 'td', 'details'];
    private const BLOCK_VARS = ['id', 'dom_id', 'title_id', 'dark', 'editing', 'anchor', 'background', 'type'];
    private const LOOP_VARS = ['index', 'index0', 'first', 'last', 'length', 'revindex'];

    /** @var list<array> */
    private array $ast = [];
    /** Hinweise (keine Fehler), z. B. fehlende Überschriften-ID */
    public array $warnings = [];
    /** Verwendete Felder (Wurzelnamen) */
    public array $used = [];
    private array $fields = [];
    private array $behaviours = [];
    private int $nodes = 0;
    private int $outId = 0;
    private array $attrsSeen = [];

    /**
     * @param array $fields     Feld-Schema des Blocks (Core\Fields), für Namensprüfung und direktes Bearbeiten
     * @param array $behaviours erlaubte Verhalten (lightbox → data-cms-lightbox)
     */
    public static function compile(string $src, array $fields = [], array $behaviours = []): self
    {
        $t = new self();
        foreach ($fields as $f) {
            if (($f['type'] ?? '') !== 'heading' && isset($f['name'])) $t->fields[(string) $f['name']] = $f;
        }
        $t->behaviours = $behaviours;
        $src = str_replace(["\r\n", "\r"], "\n", $src);
        if (strlen($src) > self::MAX_SIZE) {
            throw new TemplateError(__('Die Vorlage ist zu groß (höchstens {n} Zeichen).', ['n' => self::MAX_SIZE]));
        }
        if (!mb_check_encoding($src, 'UTF-8')) throw new TemplateError(__('Die Vorlage ist kein gültiges UTF-8.'));
        $tokens = $t->lex($src);
        $i = 0;
        [$ast] = $t->parseBody($tokens, $i, [], 0, []);
        $end = $t->scan($ast, self::initState());
        if ($end['m'] !== 'data') throw new TemplateError(__('Die Vorlage endet mitten in einem HTML-Tag.'), substr_count($src, "\n") + 1);
        if ($end['stack']) {
            $open = end($end['stack']);
            throw new TemplateError(__('<{tag}> wird nicht geschlossen.', ['tag' => $open[0]]), $open[1]);
        }
        $t->ast = $t->decorate($ast);
        $t->lint($src);
        return $t;
    }

    // ================================================================== Lexer

    private function lex(string $src): array
    {
        $out = [];
        $pos = 0;
        $line = 1;
        $len = strlen($src);
        while ($pos < $len) {
            if (!preg_match('~\{\{|\{%|\{#~', $src, $m, PREG_OFFSET_CAPTURE, $pos)) {
                $out[] = ['text', substr($src, $pos), $line];
                break;
            }
            $at = (int) $m[0][1];
            if ($at > $pos) {
                $txt = substr($src, $pos, $at - $pos);
                $out[] = ['text', $txt, $line];
                $line += substr_count($txt, "\n");
            }
            $open = $m[0][0];
            if ($open === '{{' && substr($src, $at, 3) === '{{{') {
                throw new TemplateError(__('Dreifache Klammern {{{ … }}} sind nicht erlaubt – für formatierten Text den Filter „rich“ bzw. „inline“ verwenden.'), $line);
            }
            $close = ['{{' => '}}', '{%' => '%}', '{#' => '#}'][$open];
            $end = strpos($src, $close, $at + 2);
            if ($end === false) {
                throw new TemplateError(__('„{open}“ wird nicht mit „{close}“ geschlossen.', ['open' => $open, 'close' => $close]), $line);
            }
            $inner = substr($src, $at + 2, $end - $at - 2);
            if ($open !== '{#' && (str_contains($inner, '{{') || str_contains($inner, '{%'))) {
                throw new TemplateError(__('Platzhalter und Anweisungen lassen sich nicht verschachteln.'), $line);
            }
            if ($open === '{{') $out[] = ['out', trim($inner), $line];
            elseif ($open === '{%') $out[] = ['tag', trim($inner), $line];
            $line += substr_count($inner, "\n");
            $pos = $end + 2;
        }
        return $out;
    }

    // ================================================================== Parser (Anweisungen)

    /**
     * @param list<string> $stop  erwartete Abschluss-Anweisungen
     * @param array $scope        Schleifenvariablen: name => ['fields' => ?array, 'dp' => bool]
     * @return array{0: list<array>, 1: ?array} [Knoten, gefundene Abschluss-Anweisung]
     */
    private function parseBody(array $tokens, int &$i, array $stop, int $depth, array $scope): array
    {
        $nodes = [];
        while ($i < count($tokens)) {
            [$kind, $v, $line] = $tokens[$i];
            if (++$this->nodes > self::MAX_NODES) throw new TemplateError(__('Die Vorlage ist zu umfangreich.'), $line);
            if ($kind === 'text') {
                $nodes[] = ['t' => 'text', 'v' => $v, 'line' => $line];
                $i++;
                continue;
            }
            if ($kind === 'out') {
                if ($v === '') throw new TemplateError(__('Leerer Platzhalter {{ }}.'), $line);
                $nodes[] = ['t' => 'out', 'e' => $this->expr($v, $line, $scope), 'line' => $line, 'id' => ++$this->outId];
                $i++;
                continue;
            }
            // Anweisung
            preg_match('~^([a-z]+)\b\s*(.*)$~s', $v, $m);
            $word = $m[1] ?? '';
            $rest = trim($m[2] ?? '');
            if (in_array($word, $stop, true)) {
                return [$nodes, ['word' => $word, 'rest' => $rest, 'line' => $line]];
            }
            $i++;
            if ($word === 'if') {
                if ($depth + 1 > self::MAX_DEPTH) throw new TemplateError(__('Zu tief verschachtelt (höchstens {n} Ebenen).', ['n' => self::MAX_DEPTH]), $line);
                if ($rest === '') throw new TemplateError(__('{% if %} braucht eine Bedingung.'), $line);
                $node = ['t' => 'if', 'branches' => [], 'else' => null, 'line' => $line];
                $cond = $this->expr($rest, $line, $scope);
                while (true) {
                    [$body, $end] = $this->parseBody($tokens, $i, ['elseif', 'else', 'endif'], $depth + 1, $scope);
                    if ($end === null) throw new TemplateError(__('{% if %} wird nicht mit {% endif %} geschlossen.'), $line);
                    $i++;
                    if ($cond !== null) $node['branches'][] = [$cond, $body];
                    else $node['else'] = $body;
                    if ($end['word'] === 'endif') break;
                    if ($cond === null) throw new TemplateError(__('Nach {% else %} ist nur {% endif %} möglich.'), $end['line']);
                    if ($end['word'] === 'else') {
                        if ($end['rest'] !== '') throw new TemplateError(__('{% else %} hat keine Bedingung – {% elseif … %} verwenden.'), $end['line']);
                        $cond = null;
                    } else {
                        if ($end['rest'] === '') throw new TemplateError(__('{% elseif %} braucht eine Bedingung.'), $end['line']);
                        $cond = $this->expr($end['rest'], $end['line'], $scope);
                    }
                }
                $nodes[] = $node;
                continue;
            }
            if ($word === 'for') {
                if ($depth + 1 > self::MAX_DEPTH) throw new TemplateError(__('Zu tief verschachtelt (höchstens {n} Ebenen).', ['n' => self::MAX_DEPTH]), $line);
                $loops = count($scope);
                if ($loops + 1 > self::MAX_LOOP_DEPTH) throw new TemplateError(__('Höchstens {n} Schleifen ineinander.', ['n' => self::MAX_LOOP_DEPTH]), $line);
                if (!preg_match('~^([a-z_][a-z0-9_]*)\s+in\s+(.+)$~si', $rest, $fm)) {
                    throw new TemplateError(__('Schreibweise: {% for eintrag in liste %}'), $line);
                }
                $var = $fm[1];
                if (isset($scope[$var]) || isset($this->fields[$var]) || in_array($var, ['loop', 'block', 'true', 'false', 'not', 'and', 'or', 'in'], true)) {
                    throw new TemplateError(__('Der Name „{name}“ ist schon vergeben – bitte einen anderen Namen für die Schleifenvariable wählen.', ['name' => $var]), $line);
                }
                $src = $this->expr($fm[2], $line, $scope);
                // Unterfelder bekannt, wenn über ein Wiederholfeld (repeater) geschleift wird
                $sub = null;
                $dp = false;
                if ($src['k'] === 'path') {
                    $f = $this->pathField($src['p'], $scope);
                    if ($f && ($f['type'] ?? '') === 'repeater') {
                        $sub = [];
                        foreach ((array) ($f['fields'] ?? []) as $sf) if (isset($sf['name'])) $sub[(string) $sf['name']] = $sf;
                    }
                    $dp = $this->pathDp($src['p'], $scope);
                }
                $inner = $scope + [$var => ['fields' => $sub, 'dp' => $dp]];
                [$body, $end] = $this->parseBody($tokens, $i, ['else', 'endfor'], $depth + 1, $inner);
                if ($end === null) throw new TemplateError(__('{% for %} wird nicht mit {% endfor %} geschlossen.'), $line);
                $i++;
                $else = null;
                if ($end['word'] === 'else') {
                    [$else, $end2] = $this->parseBody($tokens, $i, ['endfor'], $depth + 1, $scope);
                    if ($end2 === null) throw new TemplateError(__('{% for %} wird nicht mit {% endfor %} geschlossen.'), $line);
                    $i++;
                }
                $nodes[] = ['t' => 'for', 'var' => $var, 'e' => $src, 'body' => $body, 'else' => $else, 'line' => $line, 'dp' => $dp, 'outer' => array_key_last($scope)];
                continue;
            }
            if (in_array($word, ['elseif', 'elif', 'else', 'endif', 'endfor'], true)) {
                throw new TemplateError(__('{% {word} %} ohne passendes {% if %} bzw. {% for %}.', ['word' => $word]), $line);
            }
            throw new TemplateError(__('Unbekannte Anweisung „{word}“ – möglich sind if, elseif, else, endif, for, endfor.', ['word' => $word ?: $v]), $line);
        }
        return [$nodes, null];
    }

    // ================================================================== Ausdrücke

    private function expr(string $src, int $line, array $scope): array
    {
        $toks = $this->exprTokens($src, $line);
        $p = 0;
        $e = $this->pOr($toks, $p, $line, $scope);
        if ($p < count($toks)) throw new TemplateError(__('Unerwartetes „{tok}“ im Ausdruck „{expr}“.', ['tok' => $toks[$p][1], 'expr' => $src]), $line);
        return $e;
    }

    private function exprTokens(string $s, int $line): array
    {
        $out = [];
        $i = 0;
        $n = strlen($s);
        while ($i < $n) {
            $c = $s[$i];
            if (ctype_space($c)) { $i++; continue; }
            if ($c === "'" || $c === '"') {
                $j = $i + 1;
                $buf = '';
                while ($j < $n && $s[$j] !== $c) {
                    if ($s[$j] === '\\' && $j + 1 < $n && ($s[$j + 1] === $c || $s[$j + 1] === '\\')) { $buf .= $s[$j + 1]; $j += 2; continue; }
                    $buf .= $s[$j++];
                }
                if ($j >= $n) throw new TemplateError(__('Zeichenkette ohne schließendes Anführungszeichen.'), $line);
                if (mb_strlen($buf) > 500) throw new TemplateError(__('Zeichenkette zu lang (höchstens 500 Zeichen).'), $line);
                $out[] = ['str', $buf];
                $i = $j + 1;
                continue;
            }
            if (preg_match('~\G\d+(\.\d+)?~', $s, $m, 0, $i)) { $out[] = ['num', $m[0]]; $i += strlen($m[0]); continue; }
            if (preg_match('~\G[A-Za-z_][A-Za-z0-9_]*~', $s, $m, 0, $i)) { $out[] = ['id', $m[0]]; $i += strlen($m[0]); continue; }
            if (preg_match('~\G(==|!=|<=|>=|<|>|\||\(|\)|,|\.)~', $s, $m, 0, $i)) { $out[] = ['op', $m[0]]; $i += strlen($m[0]); continue; }
            throw new TemplateError(__('Unerlaubtes Zeichen „{c}“ im Ausdruck.', ['c' => $c]), $line);
        }
        return $out;
    }

    private function pOr(array $t, int &$p, int $line, array $scope): array
    {
        $a = $this->pAnd($t, $p, $line, $scope);
        while (($t[$p] ?? null) === ['id', 'or']) { $p++; $a = ['k' => 'or', 'a' => $a, 'b' => $this->pAnd($t, $p, $line, $scope)]; }
        return $a;
    }

    private function pAnd(array $t, int &$p, int $line, array $scope): array
    {
        $a = $this->pNot($t, $p, $line, $scope);
        while (($t[$p] ?? null) === ['id', 'and']) { $p++; $a = ['k' => 'and', 'a' => $a, 'b' => $this->pNot($t, $p, $line, $scope)]; }
        return $a;
    }

    private function pNot(array $t, int &$p, int $line, array $scope): array
    {
        if (($t[$p] ?? null) === ['id', 'not']) { $p++; return ['k' => 'not', 'e' => $this->pNot($t, $p, $line, $scope)]; }
        $a = $this->pFilter($t, $p, $line, $scope);
        if (($t[$p][0] ?? '') === 'op' && in_array($t[$p][1], ['==', '!=', '<', '>', '<=', '>='], true)) {
            $op = $t[$p++][1];
            return ['k' => 'cmp', 'op' => $op, 'a' => $a, 'b' => $this->pFilter($t, $p, $line, $scope)];
        }
        return $a;
    }

    private function pFilter(array $t, int &$p, int $line, array $scope): array
    {
        $e = $this->pPrimary($t, $p, $line, $scope);
        while (($t[$p] ?? null) === ['op', '|']) {
            $p++;
            if (($t[$p][0] ?? '') !== 'id') throw new TemplateError(__('Nach „|“ fehlt der Name eines Filters.'), $line);
            $name = $t[$p++][1];
            if (!isset(Runtime::FILTERS[$name])) {
                throw new TemplateError(__('Unbekannter Filter „{name}“. Verfügbar: {list}.', ['name' => $name, 'list' => implode(', ', array_keys(Runtime::FILTERS))]), $line);
            }
            $args = [];
            if (($t[$p] ?? null) === ['op', '(']) {
                $p++;
                while (($t[$p] ?? null) !== ['op', ')']) {
                    $tok = $t[$p] ?? null;
                    if ($tok === null) throw new TemplateError(__('Filter „{name}“: schließende Klammer fehlt.', ['name' => $name]), $line);
                    if ($tok[0] === 'str') $args[] = $tok[1];
                    elseif ($tok[0] === 'num') $args[] = str_contains($tok[1], '.') ? (float) $tok[1] : (int) $tok[1];
                    else throw new TemplateError(__('Filter „{name}“: Argumente nur als Text in Anführungszeichen oder Zahl.', ['name' => $name]), $line);
                    $p++;
                    if (($t[$p] ?? null) === ['op', ',']) $p++;
                    elseif (($t[$p] ?? null) !== ['op', ')']) throw new TemplateError(__('Filter „{name}“: Argumente mit Komma trennen.', ['name' => $name]), $line);
                }
                $p++;
            }
            [$min, $max] = Runtime::FILTERS[$name];
            if (count($args) < $min || count($args) > $max) {
                throw new TemplateError(__('Filter „{name}“ erwartet {min}–{max} Argumente.', ['name' => $name, 'min' => $min, 'max' => $max]), $line);
            }
            if ($name === 'lt' && $e['k'] !== 'lit') {
                throw new TemplateError(__('„lt“ übersetzt nur feste Texte: {{ \'Mehr erfahren\' | lt }}.'), $line);
            }
            if ($name === 'date' && isset($args[0]) && !in_array($args[0], Runtime::DATE_STYLES, true)) {
                throw new TemplateError(__('date(…): möglich sind {list}.', ['list' => implode(', ', Runtime::DATE_STYLES)]), $line);
            }
            $e = ['k' => 'f', 'n' => $name, 'e' => $e, 'a' => $args];
        }
        return $e;
    }

    private function pPrimary(array $t, int &$p, int $line, array $scope): array
    {
        $tok = $t[$p] ?? null;
        if ($tok === null) throw new TemplateError(__('Unvollständiger Ausdruck.'), $line);
        if ($tok === ['op', '(']) {
            $p++;
            $e = $this->pOr($t, $p, $line, $scope);
            if (($t[$p] ?? null) !== ['op', ')']) throw new TemplateError(__('Schließende Klammer fehlt.'), $line);
            $p++;
            return $e;
        }
        if ($tok[0] === 'str') { $p++; return ['k' => 'lit', 'v' => $tok[1]]; }
        if ($tok[0] === 'num') { $p++; return ['k' => 'lit', 'v' => str_contains($tok[1], '.') ? (float) $tok[1] : (int) $tok[1]]; }
        if ($tok[0] === 'id') {
            if (in_array($tok[1], ['true', 'false'], true)) { $p++; return ['k' => 'lit', 'v' => $tok[1] === 'true']; }
            if (in_array($tok[1], ['and', 'or', 'not'], true)) throw new TemplateError(__('Unerwartetes „{tok}“.', ['tok' => $tok[1]]), $line);
            $path = [$tok[1]];
            $p++;
            while (($t[$p] ?? null) === ['op', '.']) {
                $p++;
                $seg = $t[$p] ?? null;
                if ($seg === null || !in_array($seg[0], ['id', 'num'], true) || str_contains($seg[1], '.')) {
                    throw new TemplateError(__('Nach „.“ fehlt ein Feldname.'), $line);
                }
                $path[] = $seg[0] === 'num' ? (int) $seg[1] : $seg[1];
                $p++;
            }
            if (count($path) > 6) throw new TemplateError(__('Pfad zu lang.'), $line);
            $this->checkPath($path, $line, $scope);
            return ['k' => 'path', 'p' => $path, 'ft' => ($this->pathField($path, $scope)['type'] ?? null)];
        }
        throw new TemplateError(__('Unerwartetes „{tok}“.', ['tok' => $tok[1]]), $line);
    }

    /** Namen gegen Felder, Schleifenvariablen, loop und block prüfen */
    private function checkPath(array $path, int $line, array $scope): void
    {
        $root = $path[0];
        if ($root === 'block') {
            if (count($path) !== 2 || !in_array($path[1], self::BLOCK_VARS, true)) {
                throw new TemplateError(__('block.… kennt: {list}.', ['list' => implode(', ', self::BLOCK_VARS)]), $line);
            }
            return;
        }
        if ($root === 'loop') {
            if (!$scope) throw new TemplateError(__('„loop“ gibt es nur innerhalb von {% for %}.'), $line);
            if (count($path) !== 2 || !in_array($path[1], self::LOOP_VARS, true)) {
                throw new TemplateError(__('loop.… kennt: {list}.', ['list' => implode(', ', self::LOOP_VARS)]), $line);
            }
            return;
        }
        if (isset($scope[$root])) {
            $sub = $scope[$root]['fields'];
            if ($sub !== null && isset($path[1]) && is_string($path[1]) && !isset($sub[$path[1]])) {
                throw new TemplateError(__('„{name}“ hat kein Unterfeld „{sub}“. Vorhanden: {list}.', ['name' => $root, 'sub' => $path[1], 'list' => implode(', ', array_keys($sub)) ?: '–']), $line);
            }
            return;
        }
        if (!isset($this->fields[$root])) {
            $names = array_keys($this->fields);
            throw new TemplateError(__('Unbekanntes Feld „{name}“. Vorhanden: {list}.', ['name' => $root, 'list' => implode(', ', $names) ?: '–']), $line);
        }
        $this->used[$root] = true;
    }

    /** Felddefinition eines Pfads (feld, feld.unterfeld über Schleifenvariable) oder null */
    private function pathField(array $path, array $scope): ?array
    {
        $root = $path[0];
        if (isset($scope[$root])) {
            $sub = $scope[$root]['fields'];
            return count($path) === 2 && is_string($path[1]) && $sub !== null ? ($sub[$path[1]] ?? null) : null;
        }
        if (isset($this->fields[$root]) && count($path) === 1) return $this->fields[$root];
        return null;
    }

    /** Hat der Pfad einen Datenpfad (für direktes Bearbeiten)? */
    private function pathDp(array $path, array $scope): bool
    {
        $root = $path[0];
        if (isset($scope[$root])) return $scope[$root]['dp'];
        return isset($this->fields[$root]);
    }

    // ================================================================== HTML-Prüfung

    private static function initState(): array
    {
        return ['m' => 'data', 'stack' => [], 'tag' => '', 'attr' => '', 'q' => '', 'val' => '', 'dyn' => false, 'urlOut' => false];
    }

    /** Zustände, die an Anweisungsgrenzen übereinstimmen müssen */
    private static function sig(array $s): string
    {
        return $s['m'] . '|' . implode(',', array_column($s['stack'], 0)) . '|' . $s['tag'] . '|' . $s['attr'] . '|' . $s['q'];
    }

    /** Knotenliste im Dokument-Zusammenhang prüfen; Kontexte der Ausgaben eintragen */
    private function scan(array &$nodes, array $s): array
    {
        foreach ($nodes as &$n) {
            switch ($n['t']) {
                case 'text':
                    $s = $this->scanText($n, $s);
                    break;
                case 'out':
                    $this->scanOut($n, $s);
                    break;
                case 'if':
                case 'for':
                    if (in_array($s['m'], ['val_dq', 'val_sq'], true) && in_array($s['attr'], self::URL_ATTRS, true)) {
                        throw new TemplateError(__('In href/cite sind keine Anweisungen möglich – nur ein einzelner Platzhalter, z. B. href="{{ link | link }}".'), $n['line']);
                    }
                    if (!in_array($s['m'], ['data', 'val_dq', 'val_sq', 'before_attr'], true)) {
                        throw new TemplateError(__('Anweisungen sind hier nicht möglich (mitten in einem Tag-Namen oder Attribut).'), $n['line']);
                    }
                    if (in_array($s['m'], ['val_dq', 'val_sq'], true)) $s['dyn'] = true;
                    $sig = self::sig($s);
                    $seen = $this->attrsSeen;
                    $allSeen = $seen;
                    $parts = $n['t'] === 'if' ? array_merge(array_column($n['branches'], 1), [$n['else'] ?? []]) : [$n['body'], $n['else'] ?? []];
                    $keys = $n['t'] === 'if' ? array_keys($n['branches']) : [];
                    foreach ($parts as $k => $body) {
                        $this->attrsSeen = $seen;
                        $end = $this->scan($body, $s);
                        $allSeen += $this->attrsSeen;
                        if (self::sig($end) !== $sig) {
                            throw new TemplateError($n['t'] === 'if'
                                ? __('Jeder Zweig von {% if %} muss die HTML-Tags schließen, die er öffnet (Anweisung in Zeile {n}).', ['n' => $n['line']])
                                : __('Der Inhalt von {% for %} muss die HTML-Tags schließen, die er öffnet (Anweisung in Zeile {n}).', ['n' => $n['line']]), $n['line']);
                        }
                        // zurückschreiben (Kontexte der Ausgaben)
                        if ($n['t'] === 'if') {
                            if (isset($keys[$k])) $n['branches'][$keys[$k]][1] = $body; else $n['else'] = $n['else'] === null ? null : $body;
                        } else {
                            if ($k === 0) $n['body'] = $body; elseif ($n['else'] !== null) $n['else'] = $body;
                        }
                    }
                    $this->attrsSeen = $allSeen;
                    break;
            }
        }
        unset($n);
        return $s;
    }

    private function scanOut(array &$n, array &$s): void
    {
        if ($s['m'] === 'data') {
            $n['ctx'] = 'text';
            return;
        }
        if (in_array($s['m'], ['val_dq', 'val_sq'], true)) {
            $attr = $s['attr'];
            if (in_array($attr, ['tabindex', 'dir', 'translate', 'data-cms-lightbox'], true)) {
                throw new TemplateError(__('Das Attribut „{attr}“ darf keinen Platzhalter enthalten.', ['attr' => $attr]), $n['line']);
            }
            if (in_array($attr, self::URL_ATTRS, true)) {
                if ($s['val'] !== '' || $s['dyn']) {
                    throw new TemplateError(__('In „{attr}“ ist nur ein einzelner Platzhalter erlaubt (ohne weiteren Text), z. B. href="{{ link | link }}".', ['attr' => $attr]), $n['line']);
                }
                $n['ctx'] = 'url';
                $n['ext'] = $s['tag'] === 'a';
                $s['urlOut'] = true;
            } else {
                $n['ctx'] = 'attr';
                $n['attr'] = $attr;
            }
            $s['dyn'] = true;
            return;
        }
        if ($s['m'] === 'before_val') {
            throw new TemplateError(__('Attributwerte mit Platzhaltern bitte in Anführungszeichen setzen: attr="{{ … }}".'), $n['line']);
        }
        throw new TemplateError(__('Platzhalter sind nur im Text oder in Attributwerten erlaubt – nicht als Tag- oder Attributname.'), $n['line']);
    }

    private function scanText(array &$n, array $s): array
    {
        $t = $n['v'];
        $line = $n['line'];
        // HTML-Kommentare entfernen (dürfen keine Platzhalter enthalten)
        if (str_contains($t, '<!--')) {
            if ($s['m'] !== 'data') throw new TemplateError(__('HTML-Kommentare sind nur zwischen Tags möglich.'), $line);
            $t = (string) preg_replace_callback('~<!--.*?-->~s', fn($m) => str_repeat("\n", substr_count($m[0], "\n")), $t);
            if (str_contains($t, '<!--')) throw new TemplateError(__('HTML-Kommentar ohne „-->“ oder mit Platzhalter – für Kommentare {# … #} verwenden.'), $line);
        }
        $len = strlen($t);
        $n['open'] = null;
        $n['close'] = null;
        for ($i = 0; $i < $len; $i++) {
            $c = $t[$i];
            switch ($s['m']) {
                case 'data':
                    if ($c !== '<') break;
                    $nx = $t[$i + 1] ?? '';
                    if ($nx === '/') {
                        if (!preg_match('~\G</([a-zA-Z][a-zA-Z0-9]*)\s*>~', $t, $m, 0, $i)) {
                            throw new TemplateError(__('Unvollständiges schließendes Tag.'), $line);
                        }
                        $tag = strtolower($m[1]);
                        $top = end($s['stack']);
                        if ($top === false) throw new TemplateError(__('</{tag}> schließt kein offenes Tag.', ['tag' => $tag]), $line);
                        if ($top[0] !== $tag) {
                            throw new TemplateError(__('</{tag}> passt nicht – zuerst <{open}> aus Zeile {n} schließen.', ['tag' => $tag, 'open' => $top[0], 'n' => $top[1]]), $line);
                        }
                        array_pop($s['stack']);
                        if ($i === 0) $n['close'] = $tag;
                        $line += substr_count($m[0], "\n");
                        $i += strlen($m[0]) - 1;
                    } elseif (ctype_alpha($nx)) {
                        preg_match('~\G<([a-zA-Z][a-zA-Z0-9]*)~', $t, $m, 0, $i);
                        $tag = strtolower($m[1]);
                        if ($tag === 'h1') throw new TemplateError(__('<h1> ist der Seitenüberschrift vorbehalten – in Blöcken <h2> bis <h6> verwenden.'), $line);
                        if (!in_array($tag, self::TAGS, true)) {
                            throw new TemplateError(in_array($tag, ['img', 'picture', 'svg', 'video', 'iframe'], true)
                                ? __('<{tag}> ist nicht erlaubt – Bilder mit {{ bild | image(\'100vw\', \'4:3\') }}, Symbole mit {{ symbol | icon }} ausgeben.', ['tag' => $tag])
                                : __('Das HTML-Element <{tag}> ist nicht erlaubt.', ['tag' => $tag]), $line);
                        }
                        $s['m'] = 'before_attr';
                        $s['tag'] = $tag;
                        $this->attrsSeen = [];
                        $s['tagLine'] = $line;
                        $i += strlen($m[0]) - 1;
                    } elseif ($nx === '!' || $nx === '?') {
                        throw new TemplateError(__('Deklarationen, CDATA und Verarbeitungsanweisungen (<!…>, <?…>) sind nicht erlaubt.'), $line);
                    } elseif ($nx === '') {
                        // „<{{ x }}“ würde aus dem Wert ein Tag machen
                        throw new TemplateError(__('„<“ direkt vor einem Platzhalter oder einer Anweisung ist nicht erlaubt – als &lt; schreiben.'), $line);
                    }
                    break;
                case 'before_attr':
                    if (ctype_space($c)) break;
                    if ($c === '>') {
                        if (!in_array($s['tag'], self::VOID, true)) {
                            $s['stack'][] = [$s['tag'], $s['tagLine'] ?? $line];
                            if ($i === $len - 1) $n['open'] = $s['tag'];
                        }
                        $s['m'] = 'data';
                        $s['tag'] = '';
                        break;
                    }
                    if ($c === '/' && ($t[$i + 1] ?? '') === '>') {
                        if (!in_array($s['tag'], self::VOID, true)) {
                            throw new TemplateError(__('<{tag} /> ist kein leeres Element – bitte mit </{tag}> schließen.', ['tag' => $s['tag']]), $line);
                        }
                        $i++;
                        $s['m'] = 'data';
                        $s['tag'] = '';
                        break;
                    }
                    if (!preg_match('~\G[a-zA-Z_:][-a-zA-Z0-9_:.]*~', $t, $m, 0, $i)) {
                        throw new TemplateError(__('Ungültiges Zeichen „{c}“ in <{tag}>.', ['c' => $c, 'tag' => $s['tag']]), $line);
                    }
                    $attr = strtolower($m[0]);
                    $this->checkAttr($s['tag'], $attr, $line);
                    $s['attr'] = $attr;
                    $s['m'] = 'after_attr_name';
                    $i += strlen($m[0]) - 1;
                    break;
                case 'after_attr_name':
                    if (ctype_space($c)) break;
                    if ($c === '=') { $s['m'] = 'before_val'; break; }
                    $this->checkValue($s['tag'], $s['attr'], null, false, $line);
                    $s['m'] = 'before_attr';
                    $s['attr'] = '';
                    $i--;
                    break;
                case 'before_val':
                    if (ctype_space($c)) break;
                    if ($c === '"' || $c === "'") {
                        $s['m'] = $c === '"' ? 'val_dq' : 'val_sq';
                        $s['q'] = $c;
                        $s['val'] = '';
                        $s['dyn'] = false;
                        $s['urlOut'] = false;
                        break;
                    }
                    if (!preg_match('~\G[^\s"\'=<>`]+~', $t, $m, 0, $i)) {
                        throw new TemplateError(__('Ungültiger Wert für „{attr}“.', ['attr' => $s['attr']]), $line);
                    }
                    $this->checkValue($s['tag'], $s['attr'], $m[0], false, $line);
                    $s['m'] = 'before_attr';
                    $s['attr'] = '';
                    $i += strlen($m[0]) - 1;
                    break;
                case 'val_dq':
                case 'val_sq':
                    if ($c === $s['q']) {
                        $this->checkValue($s['tag'], $s['attr'], $s['val'], $s['dyn'], $line);
                        $s['m'] = 'before_attr';
                        $s['attr'] = '';
                        $s['q'] = '';
                        $s['val'] = '';
                        $s['dyn'] = false;
                        $s['urlOut'] = false;
                        break;
                    }
                    if ($s['urlOut']) {
                        throw new TemplateError(__('In „{attr}“ ist nur ein einzelner Platzhalter erlaubt (ohne weiteren Text).', ['attr' => $s['attr']]), $line);
                    }
                    if ($c === '<') throw new TemplateError(__('„<“ ist in Attributwerten nicht erlaubt.'), $line);
                    $s['val'] .= $c;
                    break;
            }
            if ($c === "\n") $line++;
        }
        // Attributname am Ende des Textstücks (vor einer Anweisung): als Attribut ohne Wert abschließen
        if ($s['m'] === 'after_attr_name') {
            $this->checkValue($s['tag'], $s['attr'], null, false, $line);
            $s['m'] = 'before_attr';
            $s['attr'] = '';
        }
        $n['v'] = $t;
        return $s;
    }

    private function checkAttr(string $tag, string $attr, int $line): void
    {
        if (str_starts_with($attr, 'on') || $attr === 'style' || $attr === 'srcdoc' || $attr === 'formaction' || str_contains($attr, ':')) {
            throw new TemplateError($attr === 'style'
                ? __('Das Attribut „style“ ist nicht erlaubt (Content-Security-Policy) – Gestaltung bitte im Reiter CSS.')
                : __('Das Attribut „{attr}“ ist nicht erlaubt (Skripte und Ereignisse sind in eigenen Blöcken ausgeschlossen).', ['attr' => $attr]), $line);
        }
        if (isset($this->attrsSeen[$attr])) throw new TemplateError(__('Das Attribut „{attr}“ steht doppelt in <{tag}>.', ['attr' => $attr, 'tag' => $tag]), $line);
        $this->attrsSeen[$attr] = true;
        if (str_starts_with($attr, 'aria-') && preg_match('~^aria-[a-z]+$~', $attr)) return;
        if (str_starts_with($attr, 'data-')) {
            if (!preg_match('~^data-[a-z][a-z0-9\-]*$~', $attr)) throw new TemplateError(__('Ungültiges data-Attribut „{attr}“.', ['attr' => $attr]), $line);
            if ($attr === 'data-cms-lightbox') {
                if (!in_array('lightbox', $this->behaviours, true)) {
                    throw new TemplateError(__('data-cms-lightbox braucht das Verhalten „Lightbox“ (Reiter Einstellungen).'), $line);
                }
                return;
            }
            foreach (self::RESERVED_DATA as $r) {
                if ($attr === $r || str_starts_with($attr, $r . '-')) {
                    throw new TemplateError(__('„{attr}“ ist für den Editor bzw. Kern-Skripte reserviert.', ['attr' => $attr]), $line);
                }
            }
            return;
        }
        if (in_array($attr, self::GLOBAL_ATTRS, true) || in_array($attr, self::TAG_ATTRS[$tag] ?? [], true)) return;
        throw new TemplateError(in_array($attr, ['target', 'rel'], true)
            ? __('„{attr}“ setzt das System selbst (externe Links öffnen in neuem Tab mit rel="noopener").', ['attr' => $attr])
            : __('Das Attribut „{attr}“ ist an <{tag}> nicht erlaubt.', ['attr' => $attr, 'tag' => $tag]), $line);
    }

    /** Statischen Teil eines Attributwerts prüfen ($val null = ohne Wert) */
    private function checkValue(string $tag, string $attr, ?string $val, bool $dyn, int $line): void
    {
        if ($val === null) {
            if (!in_array($attr, self::BOOL_ATTRS, true) && !str_starts_with($attr, 'data-') && !in_array($attr, ['translate'], true)) {
                throw new TemplateError(__('Das Attribut „{attr}“ braucht einen Wert.', ['attr' => $attr]), $line);
            }
            return;
        }
        if (in_array($attr, self::URL_ATTRS, true) && !$dyn) {
            $href = trim(html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($href === '' || \Core\Sanitizer::safeHref($href) === null) {
                throw new TemplateError(__('Unsichere oder leere Adresse in „{attr}“ – erlaubt sind https://, mailto:, tel:, #anker und /pfad.', ['attr' => $attr]), $line);
            }
        }
        if ($attr === 'tabindex' && !in_array(trim($val), ['0', '-1'], true)) {
            throw new TemplateError(__('tabindex nur 0 oder -1 (Reihenfolge der Tastaturbedienung nicht verändern).'), $line);
        }
        if (in_array($attr, ['colspan', 'rowspan', 'start', 'value', 'span'], true) && !$dyn && !preg_match('~^-?\d{1,4}$~', trim($val))) {
            throw new TemplateError(__('„{attr}“ erwartet eine Zahl.', ['attr' => $attr]), $line);
        }
    }

    // ================================================================== Nachbearbeitung: direktes Bearbeiten, externe Links

    /**
     * <h3>{{ item.name }}</h3> → im Bearbeiten-Modus data-edit am Element (Einfügen eines „editattr“-Knotens vor „>“).
     * href="{{ x }}" an <a> → target/rel für externe Adressen direkt nach dem Attribut („extattr“).
     */
    private function decorate(array $nodes): array
    {
        $out = [];
        $count = count($nodes);
        for ($k = 0; $k < $count; $k++) {
            $n = $nodes[$k];
            if ($n['t'] === 'if') {
                foreach ($n['branches'] as $bi => [$c, $body]) $n['branches'][$bi][1] = $this->decorate($body);
                if ($n['else'] !== null) $n['else'] = $this->decorate($n['else']);
            } elseif ($n['t'] === 'for') {
                $n['body'] = $this->decorate($n['body']);
                if ($n['else'] !== null) $n['else'] = $this->decorate($n['else']);
            }
            // Editierbar: Text endet mit öffnendem Tag, Ausgabe, Text beginnt mit dem passenden schließenden Tag
            if ($n['t'] === 'text' && !empty($n['open']) && ($nodes[$k + 1]['t'] ?? '') === 'out' && ($nodes[$k + 2]['t'] ?? '') === 'text'
                && ($nodes[$k + 2]['close'] ?? null) === $n['open'] && ($nodes[$k + 1]['ctx'] ?? '') === 'text') {
                $edit = $this->editSpec($nodes[$k + 1]['e'], $n['open']);
                if ($edit !== null) {
                    $out[] = ['t' => 'text', 'v' => substr($n['v'], 0, -1), 'line' => $n['line']];
                    $out[] = ['t' => 'editattr', 'e' => $edit[0], 'mode' => $edit[1]];
                    $out[] = ['t' => 'text', 'v' => '>', 'line' => $n['line']];
                    continue;
                }
            }
            // Nach href-Platzhalter an <a>: target/rel für externe Adressen
            if ($n['t'] === 'text' && $k > 0 && ($nodes[$k - 1]['t'] ?? '') === 'out' && !empty($nodes[$k - 1]['ext'])
                && isset($n['v'][0]) && in_array($n['v'][0], ['"', "'"], true)) {
                $out[] = ['t' => 'text', 'v' => $n['v'][0], 'line' => $n['line']];
                $out[] = ['t' => 'extattr'];
                $rest = substr($n['v'], 1);
                if ($rest !== '') $out[] = ['t' => 'text', 'v' => $rest, 'line' => $n['line'], 'open' => $n['open'] ?? null, 'close' => null];
                continue;
            }
            $out[] = $n;
        }
        return $out;
    }

    /** [Pfad-Ausdruck, Modus] für direktes Bearbeiten oder null */
    private function editSpec(array $e, string $tag): ?array
    {
        $filters = [];
        while ($e['k'] === 'f') { $filters[] = $e['n']; $e = $e['e']; }
        if ($e['k'] !== 'path' || count($filters) > 1 || ($e['ft'] ?? null) === null || $tag === 'a') return null;
        $ft = $e['ft'];
        $f = $filters[0] ?? '';
        if (in_array($ft, ['text', 'textarea'], true) && $f === '') $mode = 'plain';
        elseif ($ft === 'inline' && $f === 'inline') $mode = 'inline';
        elseif ($ft === 'richtext' && $f === 'rich' && in_array($tag, self::RICH_CONTAINERS, true)) $mode = 'rich';
        else return null;
        return [$e, $mode];
    }

    /** Hinweise: Überschrift ohne block.title_id, Lightbox-Bilder ohne Container, ungenutzte Felder */
    private function lint(string $src): void
    {
        if (isset($this->fields['title']) && !str_contains($src, 'block.title_id')) {
            $this->warnings[] = __('Das Feld „title“ setzt aria-labelledby am Abschnitt – bitte der Überschrift id="{{ block.title_id }}" geben.');
        }
        if (preg_match('~\|\s*zoom\b~', $src) && !str_contains($src, 'data-cms-lightbox')) {
            $this->warnings[] = __('Bilder mit „zoom“ öffnen erst in der Lightbox, wenn ein umgebendes Element data-cms-lightbox trägt.');
        }
        if (str_contains($src, 'cb-reel') && !preg_match('~cb-reel[^>]*tabindex="0"|tabindex="0"[^>]*cb-reel~', $src)) {
            $this->warnings[] = __('Scroll-Leisten (cb-reel) brauchen tabindex="0", role="region" und aria-label, damit sie per Tastatur erreichbar sind.');
        }
        $unused = array_diff(array_keys($this->fields), array_keys($this->used));
        if ($unused) $this->warnings[] = __('Nicht verwendete Felder: {list}.', ['list' => implode(', ', $unused)]);
    }

    // ================================================================== Interpreter

    public function run(Runtime $rt, array $data): string
    {
        $out = '';
        $this->exec($this->ast, $rt, $data, [], $out);
        return $out;
    }

    private function exec(array $nodes, Runtime $rt, array $data, array $scopes, string &$out): void
    {
        foreach ($nodes as $n) {
            switch ($n['t']) {
                case 'text':
                    $out .= $n['v'];
                    break;
                case 'out':
                    $v = $this->ev($n['e'], $rt, $data, $scopes);
                    $out .= match ($n['ctx'] ?? 'text') {
                        'url' => $rt->href($v),
                        'attr' => $rt->attr($v, (string) $n['attr']),
                        default => $rt->text($v),
                    };
                    break;
                case 'editattr':
                    $dp = $this->dataPath($n['e']['p'], $scopes);
                    if ($dp !== null) $out .= $rt->edit($dp, $n['mode']);
                    break;
                case 'extattr':
                    $out .= $rt->ext();
                    break;
                case 'if':
                    $done = false;
                    foreach ($n['branches'] as [$c, $body]) {
                        if ($rt->truthy($this->ev($c, $rt, $data, $scopes))) {
                            $this->exec($body, $rt, $data, $scopes, $out);
                            $done = true;
                            break;
                        }
                    }
                    if (!$done && $n['else'] !== null) $this->exec($n['else'], $rt, $data, $scopes, $out);
                    break;
                case 'for':
                    $list = $rt->each($this->ev($n['e'], $rt, $data, $scopes));
                    $base = $n['dp'] ? $this->dataPath($n['e']['p'], $scopes) : null;
                    $cnt = count($list);
                    foreach ($list as $i => $item) {
                        $frame = ['var' => $n['var'], 'val' => $item, 'dp' => $base !== null ? $base . '.' . $i : null, 'loop' => $rt->loop($i, $cnt)];
                        $this->exec($n['body'], $rt, $data, [...$scopes, $frame], $out);
                    }
                    if (!$cnt && $n['else'] !== null) $this->exec($n['else'], $rt, $data, $scopes, $out);
                    break;
            }
        }
    }

    private function ev(array $e, Runtime $rt, array $data, array $scopes): mixed
    {
        switch ($e['k']) {
            case 'lit':
                return $e['v'];
            case 'path':
                $p = $e['p'];
                $root = array_shift($p);
                if ($root === 'block') return $rt->block((string) $p[0]);
                if ($root === 'loop') return $rt->get(end($scopes)['loop'] ?? [], ...$p);
                for ($i = count($scopes) - 1; $i >= 0; $i--) {
                    if ($scopes[$i]['var'] === $root) return $rt->get($scopes[$i]['val'], ...$p);
                }
                return $rt->get($data, $root, ...$p);
            case 'f':
                return $rt->f($e['n'], $this->ev($e['e'], $rt, $data, $scopes), $e['a']);
            case 'not':
                return !$rt->truthy($this->ev($e['e'], $rt, $data, $scopes));
            case 'and':
                return $rt->truthy($this->ev($e['a'], $rt, $data, $scopes)) && $rt->truthy($this->ev($e['b'], $rt, $data, $scopes));
            case 'or':
                return $rt->truthy($this->ev($e['a'], $rt, $data, $scopes)) || $rt->truthy($this->ev($e['b'], $rt, $data, $scopes));
            case 'cmp':
                return $rt->cmp($e['op'], $this->ev($e['a'], $rt, $data, $scopes), $this->ev($e['b'], $rt, $data, $scopes));
        }
        return null;
    }

    /** Datenpfad „items.2.name“ eines Pfad-Ausdrucks (für data-edit) oder null */
    private function dataPath(array $p, array $scopes): ?string
    {
        $root = array_shift($p);
        for ($i = count($scopes) - 1; $i >= 0; $i--) {
            if ($scopes[$i]['var'] === $root) {
                return $scopes[$i]['dp'] === null ? null : implode('.', [$scopes[$i]['dp'], ...$p]);
            }
        }
        return in_array($root, ['block', 'loop'], true) ? null : implode('.', [$root, ...$p]);
    }

    // ================================================================== PHP-Export (Theme-Block)

    /** PHP-Renderer für kits/{name}/blocks/{typ}.php – Ausgabe identisch zum Interpreter (gleiche Runtime) */
    public function toPhp(string $label = ''): string
    {
        $this->phpOut = '';
        $this->phpClosed = false;
        $this->php("<?php\n/**\n * Block „" . str_replace('*/', '* /', $label) . "“ – exportiert aus dem Block-Designer von KLXM Studio.\n"
            . " * Erzeugt aus der sicheren Vorlagensprache: Escaping, Filter und Grenzen über Core\\Blocks\\Runtime.\n"
            . " * @var \\Core\\Block \$b  @var array \$d\n */\n\$rt = \\Core\\Blocks\\Runtime::for(\$b);\n?>\n");
        $this->php('<?= $rt->open() ?>');
        $this->genNodes($this->ast, []);
        $this->php('<?= $rt->close() ?>');
        return $this->phpOut . "\n";
    }

    private string $phpOut = '';
    private bool $phpClosed = false;

    /** PHP-Abschnitt anhängen (endet mit „?>“) */
    private function php(string $code): void
    {
        $this->phpOut .= $code;
        $this->phpClosed = str_ends_with($code, '?>');
    }

    /** Statisches HTML: roh (PHP verschluckt einen Zeilenumbruch direkt nach „?>“ – dann doppelt), mit „<?“ als echo */
    private function raw(string $html): void
    {
        if ($html === '') return;
        if (str_contains($html, '<?')) {
            $this->php('<?= ' . var_export($html, true) . ' ?>');
            return;
        }
        if ($this->phpClosed && str_starts_with($html, "\n")) $html = "\n" . $html;
        $this->phpOut .= $html;
        $this->phpClosed = false;
    }

    private function genNodes(array $nodes, array $loops): void
    {
        foreach ($nodes as $n) {
            switch ($n['t']) {
                case 'text':
                    $this->raw($n['v']);
                    break;
                case 'out':
                    $x = $this->genExpr($n['e'], $loops);
                    $this->php(match ($n['ctx'] ?? 'text') {
                        'url' => '<?= $rt->href(' . $x . ') ?>',
                        'attr' => '<?= $rt->attr(' . $x . ', ' . var_export((string) $n['attr'], true) . ') ?>',
                        default => '<?= $rt->text(' . $x . ') ?>',
                    });
                    break;
                case 'editattr':
                    $dp = $this->genDp($n['e']['p'], $loops);
                    if ($dp !== null) $this->php('<?= $rt->edit(' . $dp . ', ' . var_export($n['mode'], true) . ') ?>');
                    break;
                case 'extattr':
                    $this->php('<?= $rt->ext() ?>');
                    break;
                case 'if':
                    foreach ($n['branches'] as $bi => [$c, $body]) {
                        $this->php('<?php ' . ($bi === 0 ? 'if' : 'elseif') . ' ($rt->truthy(' . $this->genExpr($c, $loops) . ')): ?>');
                        $this->genNodes($body, $loops);
                    }
                    if ($n['else'] !== null) {
                        $this->php('<?php else: ?>');
                        $this->genNodes($n['else'], $loops);
                    }
                    $this->php('<?php endif; ?>');
                    break;
                case 'for':
                    $v = $n['var'];
                    $base = $n['dp'] ? $this->genDp($n['e']['p'], $loops) : null;
                    $this->php('<?php $x_' . $v . ' = $rt->each(' . $this->genExpr($n['e'], $loops) . '); foreach ($x_' . $v . ' as $i_' . $v . ' => $v_' . $v . '):'
                        . ' $l_' . $v . ' = $rt->loop($i_' . $v . ', count($x_' . $v . '));'
                        . ($base !== null ? ' $p_' . $v . ' = ' . $base . " . '.' . \$i_" . $v . ';' : '') . ' ?>');
                    $this->genNodes($n['body'], [...$loops, $v => $base !== null]);
                    $this->php('<?php endforeach; ?>');
                    if ($n['else'] !== null) {
                        $this->php('<?php if (!$x_' . $v . '): ?>');
                        $this->genNodes($n['else'], $loops);
                        $this->php('<?php endif; ?>');
                    }
                    break;
            }
        }
    }

    private function genExpr(array $e, array $loops): string
    {
        switch ($e['k']) {
            case 'lit':
                return var_export($e['v'], true);
            case 'path':
                $p = $e['p'];
                $root = array_shift($p);
                $segs = implode('', array_map(fn($s) => ', ' . var_export($s, true), $p));
                if ($root === 'block') return '$rt->block(' . var_export((string) $p[0], true) . ')';
                if ($root === 'loop') return '$rt->get($l_' . array_key_last($loops) . $segs . ')';
                if (array_key_exists($root, $loops)) return $p ? '$rt->get($v_' . $root . $segs . ')' : '$v_' . $root;
                return '$rt->get($d, ' . var_export($root, true) . $segs . ')';
            case 'f':
                return '$rt->f(' . var_export($e['n'], true) . ', ' . $this->genExpr($e['e'], $loops) . ($e['a'] ? ', [' . implode(', ', array_map(fn($a) => var_export($a, true), $e['a'])) . ']' : '') . ')';
            case 'not':
                return '!$rt->truthy(' . $this->genExpr($e['e'], $loops) . ')';
            case 'and':
            case 'or':
                return '($rt->truthy(' . $this->genExpr($e['a'], $loops) . ') ' . ($e['k'] === 'and' ? '&&' : '||') . ' $rt->truthy(' . $this->genExpr($e['b'], $loops) . '))';
            case 'cmp':
                return '$rt->cmp(' . var_export($e['op'], true) . ', ' . $this->genExpr($e['a'], $loops) . ', ' . $this->genExpr($e['b'], $loops) . ')';
        }
        return 'null';
    }

    /** PHP-Ausdruck für den Datenpfad oder null */
    private function genDp(array $p, array $loops): ?string
    {
        $root = array_shift($p);
        $tail = $p ? " . '." . implode('.', $p) . "'" : '';
        if (array_key_exists($root, $loops)) return $loops[$root] ? '$p_' . $root . $tail : null;
        if (in_array($root, ['block', 'loop'], true)) return null;
        return var_export(implode('.', [$root, ...$p]), true);
    }
}
