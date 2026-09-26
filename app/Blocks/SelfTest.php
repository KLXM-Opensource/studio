<?php
declare(strict_types=1);

namespace Core\Blocks;

use Core\Block;

/**
 * Selbsttest des Block-Baukastens: php bin/console blocks:selftest
 * Prüft Escaping und XSS-Abwehr der Vorlagensprache (Text, Attribute, Links, Rich-Text, Symbole), Übersetzungsfehler,
 * CSS-Begrenzung und die Gleichheit von Interpreter und exportiertem PHP-Renderer.
 */
final class SelfTest
{
    private array $fails = [];
    private int $ok = 0;

    public static function run(): array
    {
        $t = new self();
        $prev = app()->editing;
        app()->editing = false;
        try {
            $t->escaping();
            $t->rejects();
            $t->css();
            $t->export();
        } finally {
            app()->editing = $prev;
        }
        return ['ok' => $t->ok, 'fails' => $t->fails];
    }

    private function assert(bool $cond, string $label): void
    {
        if ($cond) $this->ok++;
        else $this->fails[] = $label;
    }

    private const FIELDS = [
        ['name' => 'title', 'label' => 'Titel', 'type' => 'text'],
        ['name' => 'text', 'label' => 'Text', 'type' => 'richtext'],
        ['name' => 'short', 'label' => 'Kurz', 'type' => 'inline'],
        ['name' => 'link', 'label' => 'Link', 'type' => 'link'],
        ['name' => 'symbol', 'label' => 'Symbol', 'type' => 'icon'],
        ['name' => 'items', 'label' => 'Einträge', 'type' => 'repeater', 'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text'], ['name' => 'lines', 'label' => 'Zeilen', 'type' => 'textarea'],
            ['name' => 'on', 'label' => 'An', 'type' => 'bool'], ['name' => 'url', 'label' => 'Link', 'type' => 'link']]],
    ];

    private const TEMPLATE = <<<'TPL'
<h2 id="{{ block.title_id }}" title="{{ title }}" class="h {{ title }}">{{ title }}</h2>
<div class="text">{{ text | rich }}</div>
<p>{{ short | inline }} {{ symbol | icon }}</p>
{% if link %}<a class="btn" href="{{ link | link }}">{{ 'Mehr erfahren' | lt }}</a>{% endif %}
<ul>{% for it in items %}<li class="i{% if it.on %} is-on{% endif %}" data-n="{{ loop.index }}"><b>{{ it.name }}</b> <a href="{{ it.url }}">{{ 'Link' | lt }}</a>
{% for l in it.lines | lines %}<span>{{ l | upper }}</span>{% endfor %}</li>{% else %}<li>–</li>{% endfor %}</ul>
TPL;

    private function render(array $data): string
    {
        $tpl = Template::compile(self::TEMPLATE, self::FIELDS);
        return $tpl->run(new Runtime(null, ['key' => 'test']), $data);
    }

    private function escaping(): void
    {
        $evil = '"><img src=x onerror=alert(1)><script>alert(2)</script>';
        $html = $this->render([
            'title' => $evil, 'text' => '<p>ok</p><img src=x onerror=alert(3)><svg onload=alert(4)></svg><a href="javascript:alert(5)">x</a><script>alert(6)</script>',
            'short' => '<b onclick="alert(7)">b</b><iframe src="https://evil.example"></iframe>', 'link' => 'javascript:alert(8)', 'symbol' => '"><script>alert(9)</script>',
            'items' => [['name' => '<script>alert(10)</script>', 'lines' => "<b>a</b>\n\"b", 'on' => true, 'url' => ' JaVaScRiPt:alert(11)'],
                ['name' => 'x', 'lines' => '', 'on' => false, 'url' => '//evil.example/x'], ['name' => 'y', 'lines' => '', 'on' => false, 'url' => 'data:text/html,<script>alert(12)</script>']],
        ]);
        $this->assert(self::inert($html), 'Escaping: keine aktiven Inhalte in der Ausgabe (DOM geprüft)');
        $this->assert(str_contains($html, '&lt;script&gt;alert(10)'), 'Escaping: Text wird escaped');
        $this->assert(str_contains($html, 'title="&quot;&gt;&lt;img'), 'Escaping: Attribut-Ausbruch verhindert');
        $this->assert(str_contains($html, 'class="h img srcx onerroralert1scriptalert2script"'), 'Escaping: class nur sichere Zeichen');
        $this->assert(substr_count($html, 'href="#"') >= 4, 'Links: javascript:, //fremd, data: werden zu #');
        $this->assert(str_contains($html, '<p>ok</p>'), 'Rich-Text: erlaubtes HTML bleibt');
        $ok = $this->render(['title' => 'T', 'text' => '', 'short' => '', 'link' => 'https://example.org/a?b=1&c=2', 'symbol' => 'star', 'items' => []]);
        $this->assert(str_contains($ok, 'href="https://example.org/a?b=1&amp;c=2" target="_blank" rel="noopener"'), 'Links: externe Adresse mit target/rel');
        $this->assert(str_contains($ok, '<li>–</li>'), 'Schleife: {% else %} bei leerer Liste');
        $this->assert(str_contains($ok, '<svg class="ico"'), 'Filter icon: Symbol aus dem Sprite');
    }

    /** Ausgabe als DOM prüfen: keine Skript-/Einbettungs-Elemente, keine Ereignis-Attribute, keine javascript:/data:-Links */
    private static function inert(string $html): bool
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_NONET);
        libxml_clear_errors();
        foreach (['script', 'img', 'svg', 'iframe', 'object', 'embed', 'style', 'link', 'meta', 'base', 'form'] as $tag) {
            foreach ($doc->getElementsByTagName($tag) as $el) {
                // Symbole aus dem Sprite (<svg class="ico"><use href="…#i-name">) sind erlaubt
                if ($tag === 'svg' && $el->getAttribute('class') === 'ico') continue;
                return false;
            }
        }
        foreach ((new \DOMXPath($doc))->query('//@*') as $a) {
            if (str_starts_with(strtolower($a->nodeName), 'on') || $a->nodeName === 'style') return false;
            if (in_array($a->nodeName, ['href', 'src', 'cite'], true) && preg_match('~^\s*(javascript|data|vbscript):~i', $a->nodeValue)) return false;
        }
        return true;
    }

    private function rejects(): void
    {
        $bad = [
            '<{{ title }}>', '<div {{ title }}>x</div>', '<a href="javascript:alert(1)">x</a>', '<a href="x{{ link }}">x</a>', '<div onclick="x">y</div>',
            '<img src="x">', '<div style="color:red">x</div>', '<div>', '</div>', '{% if title %}<div>{% endif %}</div>', '{{{ title }}}',
            '{{ nope }}', '{% for x in items %}{{ x.nope }}{% endfor %}', '<script>alert(1)</script>', '<svg><script>x</script></svg>',
            '<a href="{{ link }}" target="_blank">x</a>', '<p title={{ title }}>x</p>', '<div data-edit="x">y</div>', '{{ title | lt }}', '<!DOCTYPE html>',
            '<h1>x</h1>', '<iframe src="https://evil.example"></iframe>', '<a href="&#106;avascript:alert(1)">x</a>', '<div data-cms-lightbox>x</div>',
            '{{ title | nofilter }}', '{% for a in items %}{% for b in items %}{% for c in items %}{% for d in items %}{% endfor %}{% endfor %}{% endfor %}{% endfor %}',
 '<a href="{% if link %}/a{% endif %}">x</a>', '<object data="x"></object>', '<form action="x"></form>',
            '<div tabindex="5">x</div>', '<math><mi>x</mi></math>', '<base href="https://evil.example/">', '<meta http-equiv="refresh">', '{% include "x" %}',
            '<p><?php echo 1; ?></p>', str_repeat('x', Template::MAX_SIZE + 1),
        ];
        foreach ($bad as $src) {
            try {
                Template::compile($src, self::FIELDS);
                $this->fails[] = 'Nicht abgelehnt: ' . mb_substr($src, 0, 60);
            } catch (TemplateError) {
                $this->ok++;
            }
        }
        try {
            Template::compile("<div>\n<p>\n</div>", self::FIELDS);
            $this->fails[] = 'Unausgeglichene Tags nicht abgelehnt';
        } catch (TemplateError $e) {
            $this->assert($e->templateLine === 3, 'Fehlermeldung mit Zeilennummer');
        }
        // Grenzen zur Laufzeit
        try {
            $big = array_fill(0, 150, ['name' => 'x', 'lines' => str_repeat("a\n", 60), 'on' => false, 'url' => '']);
            $this->render(['title' => '', 'text' => '', 'short' => '', 'link' => '', 'symbol' => '', 'items' => $big]);
            $this->fails[] = 'Schleifen-Grenze greift nicht';
        } catch (TemplateError) {
            $this->ok++;
        }
    }

    private function css(): void
    {
        $bad = ['body{color:red}', 'html .x{a:b}', ':root{--x:1}', '@import url(https://evil.example/x.css);', '.x{background:url(https://evil.example/a.png)}',
            '.x{background:url(data:image/svg+xml,abc)}', '.x{width:expression(alert(1))}', '.x{behavior:url(x.htc)}', '.x{-moz-binding:url(x)}',
            '.x{background:u\72l(https://e.x/a)}', '.x{position:fixed}', ':scope ~ .sec{color:red}', '.x{ .y{a:b} }', '@font-face{font-family:x}',
            'color:red', '.x{background:url(/media/../../etc/passwd)}', '.a :scope{x:y}', '.x{background:url(//evil.example/a.png)}', '</style><script>alert(1)</script>'];
        foreach ($bad as $src) {
            try {
                Css::compile($src, 'test');
                $this->fails[] = 'CSS nicht abgelehnt: ' . $src;
            } catch (TemplateError) {
                $this->ok++;
            }
        }
        $r = Css::compile(".card, h3 > a:hover { color: var(--cb-accent) }\n:scope { padding: 1rem }\n:dark .card{border-color:var(--cb-line)}\n@media (min-width: 40rem) { .card { padding: 2rem } }", 'test');
        foreach (self::selectors($r['css']) as $sel) {
            $this->assert(str_starts_with($sel, '.cblk-test'), 'CSS-Begrenzung: ' . $sel);
        }
    }

    /** Alle Selektoren einer CSS-Ausgabe (ohne @-Zeilen) */
    private static function selectors(string $css): array
    {
        $out = [];
        preg_match_all('~(?:^|[{}])\s*([^{}@]+)\{~', $css, $m);
        foreach ($m[1] as $s) {
            if (preg_match('~^(from|to|\d+%)~', trim($s))) continue;
            foreach (explode(',', $s) as $x) if (trim($x) !== '') $out[] = trim($x);
        }
        return $out;
    }

    private function export(): void
    {
        $def = ['label' => 'Test', 'fields' => self::FIELDS, 'type' => 'test', 'cblk' => ['key' => 'test', 'behaviours' => [], 'width' => 'wrap']];
        $data = ['title' => 'Titel <b>"x"</b>', 'text' => '<p>Hallo <b>Welt</b></p>', 'short' => '<i>kurz</i>', 'link' => '/kontakt', 'symbol' => 'heart',
            'items' => [['name' => 'A', 'lines' => "eins\nzwei", 'on' => true, 'url' => 'https://example.org'], ['name' => 'B', 'lines' => '', 'on' => false, 'url' => '#x']]];
        $b = new Block('t1', 'test', $data, app()->theme->sanitizeTunes([]), $def);
        $tpl = Template::compile(self::TEMPLATE, self::FIELDS);
        foreach ([false, true] as $editing) {
            app()->editing = $editing;
            $rt = Runtime::for($b);
            $a = $rt->open() . $tpl->run($rt, $data) . $rt->close();
            $file = tempnam(sys_get_temp_dir(), 'cblk') . '.php';
            file_put_contents($file, $tpl->toPhp('Test'));
            $php = \Core\Theme::capture($file, ['b' => $b, 'd' => $data]);
            @unlink($file);
            $this->assert($a === $php, 'Kit-Export rendert identisch' . ($editing ? ' (Bearbeiten-Modus)' : ''));
            if ($editing) $this->assert(str_contains($a, 'data-edit="items.1.name"') && str_contains($a, 'data-edit="text" data-edit-mode="rich"'), 'Direktes Bearbeiten: data-edit mit Pfad');
        }
        app()->editing = false;
    }
}
