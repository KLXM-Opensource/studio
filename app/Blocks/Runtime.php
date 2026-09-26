<?php
declare(strict_types=1);

namespace Core\Blocks;

use Core\Block;
use Core\Icons;
use Core\Lang;
use Core\Media;
use Core\MediaBlocks;

/**
 * Laufzeit der Vorlagensprache eigener Blöcke (Block-Baukasten): Filter, kontextgerechtes Escaping, Schleifen-Grenzen,
 * Hülle des Blocks. Wird vom Interpreter (Core\Blocks\Template::run) UND von als Theme-Block exportierten PHP-Renderern
 * benutzt – beide Wege liefern dadurch dieselbe Ausgabe.
 *
 * Werte sind Text (wird escaped) oder SafeHtml (nur aus Kern-Filtern). Attribute: class/id nur [A-Za-z0-9_-],
 * href/cite nur über link_href() bzw. {theme}_link() (https:, mailto:, tel:, #anker, /pfad, page:ID).
 */
final class Runtime
{
    /** Filter => [min. Argumente, max. Argumente] */
    public const FILTERS = [
        'rich' => [0, 0], 'inline' => [0, 0], 'plain' => [0, 0], 'upper' => [0, 0], 'lower' => [0, 0], 'trim' => [0, 0],
        'date' => [0, 1], 'default' => [1, 1], 'truncate' => [1, 1], 'number' => [0, 1], 'length' => [0, 0],
        'image' => [0, 2], 'zoom' => [0, 2], 'alt' => [0, 0], 'icon' => [0, 0],
        'link' => [0, 0], 'tel' => [0, 0], 'file' => [0, 0], 'filename' => [0, 0], 'filesize' => [0, 0],
        'lt' => [0, 0], 'lines' => [0, 0], 'nl2br' => [0, 0], 'paragraphs' => [0, 0],
    ];
    public const DATE_STYLES = ['short', 'long', 'weekday', 'day_month'];
    /** Grenzen je Ausgabe eines Blocks */
    public const MAX_PER_LOOP = 200;
    public const MAX_ITERATIONS = 2000;

    private int $iterations = 0;
    private ?string $lastHref = null;

    /**
     * @param ?Block $b    Block (null nur in Tests)
     * @param array  $cfg  ['key' => …, 'behaviours' => [...], 'width' => 'wrap'|'full']
     */
    public function __construct(public readonly ?Block $b, public readonly array $cfg = []) {}

    public static function for(Block $b): self
    {
        return new self($b, (array) ($b->def['cblk'] ?? []));
    }

    /** CSS-Klasse eines eigenen Blocks: cblk-{schlüssel} (Unterstriche → Bindestriche) */
    public static function cls(string $key): string
    {
        return 'cblk-' . str_replace('_', '-', $key);
    }

    // ------------------------------------------------------------------ Hülle

    public function open(): string
    {
        $key = (string) ($this->cfg['key'] ?? 'block');
        $cls = 'cblk ' . self::cls($key) . ($this->b?->dark() ? ' is-dark' : '');
        $h = '<div class="' . e($cls) . '">';
        if (($this->cfg['width'] ?? 'wrap') !== 'full') {
            $h .= '<div class="' . e((string) (app()->theme->def['container_class'] ?? 'wrap')) . '">';
        }
        return $h;
    }

    public function close(): string
    {
        $h = ($this->cfg['width'] ?? 'wrap') !== 'full' ? '</div></div>' : '</div>';
        if (in_array('lightbox', (array) ($this->cfg['behaviours'] ?? []), true)) {
            $h .= MediaBlocks::lightbox() . MediaBlocks::script();   // im Bearbeiten-Modus leer
        }
        return $h;
    }

    // ------------------------------------------------------------------ Werte

    /** Angaben zum Block: block.id, block.dom_id, block.title_id, block.dark, block.editing, block.anchor, block.background */
    public function block(string $k): mixed
    {
        $b = $this->b;
        return match ($k) {
            'id' => $b?->id ?? 'vorschau',
            'dom_id' => $b ? $b->domId() : 'b-vorschau',
            'title_id' => $b ? $b->titleId() : 'b-vorschau-title',
            'dark' => $b ? $b->dark() : false,
            'editing' => (bool) app()->editing,
            'anchor' => (string) ($b?->tunes['anchor'] ?? ''),
            'background' => $b ? $b->bg() : 'white',
            'type' => $b?->type ?? '',
            default => null,
        };
    }

    /** Wert eines Pfads: get($item, 'name') */
    public function get(mixed $base, string|int ...$segs): mixed
    {
        foreach ($segs as $s) {
            if (!is_array($base) || !array_key_exists($s, $base)) return null;
            $base = $base[$s];
        }
        return $base;
    }

    public function truthy(mixed $v): bool
    {
        if ($v instanceof SafeHtml) return trim($v->html) !== '';
        if (is_string($v)) return trim($v) !== '';
        if (is_array($v)) return $v !== [];
        return (bool) $v;
    }

    public function cmp(string $op, mixed $a, mixed $b): bool
    {
        $a = self::scalar($a);
        $b = self::scalar($b);
        $num = is_numeric($a) && is_numeric($b);
        return match ($op) {
            '==' => $num ? (float) $a == (float) $b : $a === $b,
            '!=' => $num ? (float) $a != (float) $b : $a !== $b,
            '<' => $num ? (float) $a < (float) $b : strcmp($a, $b) < 0,
            '>' => $num ? (float) $a > (float) $b : strcmp($a, $b) > 0,
            '<=' => $num ? (float) $a <= (float) $b : strcmp($a, $b) <= 0,
            '>=' => $num ? (float) $a >= (float) $b : strcmp($a, $b) >= 0,
            default => false,
        };
    }

    /** Wert als einfacher Text (SafeHtml → ohne Tags, bool → „1“/„“) */
    public static function scalar(mixed $v): string
    {
        if ($v instanceof SafeHtml) return trim(html_entity_decode(strip_tags($v->html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (is_bool($v)) return $v ? '1' : '';
        if (is_int($v) || is_float($v)) return (string) $v;
        return is_string($v) ? $v : '';
    }

    /** Schleife: Liste der Einträge (höchstens MAX_PER_LOOP, insgesamt MAX_ITERATIONS je Block) */
    public function each(mixed $v): array
    {
        if (!is_array($v)) return [];
        $list = array_slice(array_values($v), 0, self::MAX_PER_LOOP);
        $this->iterations += count($list);
        if ($this->iterations > self::MAX_ITERATIONS) {
            throw new TemplateError(__('Zu viele Schleifendurchläufe (höchstens {n} je Block).', ['n' => self::MAX_ITERATIONS]));
        }
        return $list;
    }

    /** loop.index (ab 1), loop.index0, loop.first, loop.last, loop.length, loop.revindex */
    public function loop(int $i, int $n): array
    {
        return ['index' => $i + 1, 'index0' => $i, 'first' => $i === 0, 'last' => $i === $n - 1, 'length' => $n, 'revindex' => $n - $i];
    }

    // ------------------------------------------------------------------ Ausgabe

    /** Ausgabe im Text: escaped, SafeHtml unverändert */
    public function text(mixed $v): string
    {
        if ($v instanceof SafeHtml) return $v->html;
        if ($v === null || is_bool($v) || is_array($v) || is_object($v)) return '';
        return e((string) $v);
    }

    /** Ausgabe in einem Attributwert (in Anführungszeichen): immer escaped; class/id nur sichere Zeichen */
    public function attr(mixed $v, string $attr): string
    {
        $s = self::scalar($v);
        if ($attr === 'class') {
            $s = trim((string) preg_replace('~[^A-Za-z0-9_\- ]+~', '', $s));
        } elseif (in_array($attr, ['id', 'aria-labelledby', 'aria-describedby', 'aria-controls', 'aria-owns', 'name'], true)) {
            $s = trim((string) preg_replace('~[^A-Za-z0-9_\- ]+~', '', $s));
        }
        return e($s);
    }

    /** href/cite: Link-Feld auflösen (Seiten, Anker, Sonderwerte des Themes); unsichere Adressen werden „#“ */
    public function href(mixed $v): string
    {
        $this->lastHref = self::resolveLink(self::scalar($v));
        return e($this->lastHref);
    }

    /** Nach einem dynamischen href: target/rel für externe Adressen */
    public function ext(): string
    {
        return $this->lastHref !== null ? ext_attrs($this->lastHref) : '';
    }

    /** Link-Wert → sichere Adresse */
    public static function resolveLink(string $link): string
    {
        $link = trim($link);
        if ($link === '') return '#';
        $fn = preg_replace('~[^a-z0-9_]~', '', app()->theme->name) . '_link';
        $h = function_exists($fn) ? (string) $fn($link) : link_href($link);
        // Letzte Prüfung: nur interne Pfade, Anker und erlaubte Schemata
        if (preg_match('~^(#|/(?!/)|https?://|mailto:|tel:)~i', $h) !== 1) return '#';
        return $h;
    }

    /** Im Bearbeiten-Modus: Text direkt auf der Seite bearbeitbar (data-edit), sonst leer */
    public function edit(string $path, string $mode = 'plain'): string
    {
        return $this->b ? $this->b->edit($path, $mode) : '';
    }

    // ------------------------------------------------------------------ Filter

    public function f(string $name, mixed $v, array $args = []): mixed
    {
        switch ($name) {
            case 'rich':
                return new SafeHtml(rich(self::raw($v)));
            case 'inline':
                return new SafeHtml(inline(self::raw($v)));
            case 'plain':
                return self::scalar($v);
            case 'upper':
                return mb_strtoupper(self::scalar($v));
            case 'lower':
                return mb_strtolower(self::scalar($v));
            case 'trim':
                return trim(self::scalar($v));
            case 'date':
                $s = self::scalar($v);
                $style = in_array($args[0] ?? 'short', self::DATE_STYLES, true) ? (string) ($args[0] ?? 'short') : 'short';
                return $s === '' ? '' : date_local($s, $style);
            case 'default':
                if ($this->truthy($v)) return $v;
                return is_string($args[0] ?? null) ? lt($args[0]) : ($args[0] ?? '');
            case 'truncate':
                $s = self::scalar($v);
                $n = max(1, (int) ($args[0] ?? 80));
                return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s;
            case 'number':
                $s = self::scalar($v);
                if (!is_numeric($s)) return $s;
                $dec = max(0, min(4, (int) ($args[0] ?? 0)));
                return Lang::current() === 'de' ? number_format((float) $s, $dec, ',', '.') : number_format((float) $s, $dec, '.', ',');
            case 'length':
                return is_array($v) ? count($v) : mb_strlen(self::scalar($v));
            case 'image':
            case 'zoom':
                $m = is_numeric($v) ? Media::find((int) $v) : null;
                if (!$m || !str_starts_with((string) $m['mime'], 'image/')) return '';
                $sizes = (string) ($args[0] ?? '100vw');
                if (!preg_match('~^[\w\s(),:.%\-]{1,200}$~', $sizes)) $sizes = '100vw';
                $ratio = (string) ($args[1] ?? '');
                $pic = Media::picture((int) $m['id'], $sizes, preg_match('~^\d{1,2}[:\-]\d{1,2}$~', $ratio) ? ['ratio' => str_replace('-', ':', $ratio)] : []);
                if ($name === 'image' || app()->editing) return new SafeHtml($pic);
                $alt = Media::alt($m);
                return new SafeHtml('<a class="cblk-zoom"' . MediaBlocks::lightboxLink($m, Media::title($m)) . '>' . $pic
                    . ($alt === '' ? '<span class="sr-only">' . e(lt('Bild vergrößern')) . '</span>' : '') . '</a>');
            case 'alt':
                $m = is_numeric($v) ? Media::find((int) $v) : null;
                return $m ? Media::alt($m) : '';
            case 'icon':
                $n = Icons::resolve(self::scalar($v));
                return $n !== null ? new SafeHtml(icon($n)) : '';
            case 'link':
                return self::resolveLink(self::scalar($v));
            case 'tel':
                return tel_href(self::scalar($v)) ?? '';
            case 'file':
                $m = is_numeric($v) ? Media::find((int) $v) : null;
                return $m ? Media::url($m) : '';
            case 'filename':
                $m = is_numeric($v) ? Media::find((int) $v) : null;
                return $m ? Media::displayName($m) : '';
            case 'filesize':
                $m = is_numeric($v) ? Media::find((int) $v) : null;
                return $m ? Media::humanSize((int) $m['size']) : '';
            case 'lt':
                return lt(self::scalar($v));
            case 'lines':
                return array_values(array_filter(array_map('trim', preg_split('~\R~', self::scalar($v)) ?: []), fn($l) => $l !== ''));
            case 'nl2br':
                return new SafeHtml(nl2br(e(self::scalar($v)), false));
            case 'paragraphs':
                return new SafeHtml(paragraphs(self::scalar($v)));
        }
        throw new TemplateError(__('Unbekannter Filter „{name}“.', ['name' => $name]));
    }

    /** Rohwert für die Sanitizer-Filter (SafeHtml bleibt HTML) */
    private static function raw(mixed $v): string
    {
        return $v instanceof SafeHtml ? $v->html : self::scalar($v);
    }
}
