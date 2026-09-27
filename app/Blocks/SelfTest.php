<?php
declare(strict_types=1);

namespace Core\Blocks;

use Core\Block;

/**
 * Selbsttest des Block-Baukastens: php bin/console blocks:selftest
 * Prüft Escaping und XSS-Abwehr der Vorlagensprache (Text, Attribute, Links, Rich-Text, Symbole), Übersetzungsfehler,
 * CSS-Begrenzung und die Gleichheit von Interpreter und exportiertem PHP-Renderer. Dazu die Logo-Normalisierung des Blocks „Partner & Logos“.
 * Außerdem: Bild anpassen je Einbindung (Core\ImageFx: Format, Klassen, Feldpfade in data._fx) und Bild im Rahmen
 * (Core\ImageFit: Format, Vorrang Einbindung → Mediathek → automatisch, Klassen, Regeln, transparenter Rand).
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
            $t->partnerLogos();
            $t->imageFx();
            $t->imageFit();
        } finally {
            app()->editing = $prev;
        }
        return ['ok' => $t->ok, 'fails' => $t->fails];
    }

    /** Block „Partner & Logos“ (Core\Blocks\PartnerLogos): flächengleiche Logo-Breite, Grenzen, Sortierung */
    private function partnerLogos(): void
    {
        $pl = PartnerLogos::class;   // gleicher Namensraum
        $sq = $pl::width(400, 400);
        $wide = $pl::width(640, 120);
        $tall = $pl::width(120, 260);
        $this->assert($sq > $tall && $wide > $sq && $wide <= 100, "PartnerLogos: Breite folgt dem Format (quadratisch $sq, breit $wide, hoch $tall)");
        // gleiche Fläche: Breite × Höhe (in Anteilen der Innenfläche) für quadratisch und 3:1 nahezu gleich
        $a = fn(int $w, int $h) => ($x = $pl::width($w, $h) / 100) * $x * $h / $w;
        $this->assert(abs($a(400, 400) - $a(600, 200)) < .02, 'PartnerLogos: gleiche Fläche statt gleicher Höhe');
        $this->assert($pl::width(4000, 100) === 100 && $pl::width(1, 1, '3:2', 'l') <= 100, 'PartnerLogos: höchstens volle Breite');
        $this->assert($pl::width(100, 1000) >= 6 && $pl::width(100, 1000) % 2 === 0 && $pl::width(0, 0) > 0, 'PartnerLogos: Mindestbreite, gerade Stufen, ohne Maße');
        $this->assert($pl::width(400, 400, '3:2', 'm', 1) > $sq && $pl::width(400, 400, '3:2', 'm', -1) < $sq && $pl::width(400, 400, '3:2', 's') < $sq, 'PartnerLogos: Feinjustierung und Logogröße');
        $it = fn(string $n, string $c = '') => ['name' => $n, 'category' => $c];
        $sorted = array_column($pl::sort([$it('Zebra', 'B'), $it('Äpfel'), $it('alpha', 'B'), $it('Mitte', 'A')], 'category'), 'name');
        $this->assert($sorted === ['Mitte', 'alpha', 'Zebra', 'Äpfel'], 'PartnerLogos: Kategorie, dann Name (ohne Kategorie am Ende): ' . implode(', ', $sorted));
    }

    /** Bild anpassen (Core\ImageFx): Format, Klassen, erlaubte Feldpfade, Bereinigung von data._fx */
    private function imageFx(): void
    {
        $fx = \Core\ImageFx::class;
        $this->assert($fx::normalize('c110 SEPIA s120') === 'sepia s120 c110', 'ImageFx: kanonische Schreibweise');
        $this->assert($fx::normalize('s100 b100') === '', 'ImageFx: Standardwerte entfallen');
        $this->assert($fx::normalize('s125') === null && $fx::normalize('b40') === null && $fx::normalize('gray sepia') === null, 'ImageFx: ungültige Werte abgelehnt');
        $this->assert($fx::normalize('none') === 'none' && $fx::normalize('none s120') === null, 'ImageFx: „none“ nur allein');
        $this->assert($fx::normalize(['preset' => 'gray', 'contrast' => 130]) === 'gray c130', 'ImageFx: Angabe als Objekt');
        $this->assert($fx::classes('warm s80 b120') === 'ifx ifx-warm ifx-s8 ifx-b12' && $fx::classes('none') === '', 'ImageFx: Klassen');
        $fields = [['name' => 'image', 'type' => 'media'], ['name' => 'file', 'type' => 'file'], ['name' => 'title', 'type' => 'text'],
            ['name' => 'items', 'type' => 'repeater', 'fields' => [['name' => 'image', 'type' => 'media']]]];
        $this->assert($fx::isMediaPath($fields, 'image') && $fx::isMediaPath($fields, 'items.2.image'), 'ImageFx: Bild-Feldpfade erkannt');
        $this->assert(!$fx::isMediaPath($fields, 'file') && !$fx::isMediaPath($fields, 'title') && !$fx::isMediaPath($fields, 'items.image')
            && !$fx::isMediaPath($fields, 'items.2') && !$fx::isMediaPath($fields, 'nope'), 'ImageFx: andere Pfade abgelehnt');
        $data = ['image' => 7, 'title' => 'x', 'items' => [['image' => 8], ['image' => null]]];
        $clean = $fx::sanitize(['image' => 'gray', 'items.0.image' => 's120', 'items.1.image' => 'sepia', 'title' => 'gray', 'items.0.image.x' => 'gray', 'image2' => 'bogus'], $fields, $data);
        $this->assert($clean === ['image' => 'gray', 'items.0.image' => 's120'], 'ImageFx: data._fx bereinigt (nur Bild-Felder mit Bild)');
        $fx::enter(['_fx' => ['items.0.image' => 'sepia', 'image' => 'gray'], 'image' => 7, 'items' => [['image' => 7]]]);
        $ok = $fx::classFor(['id' => 7, 'adjust' => 'vivid']) === 'ifx ifx-gray' && $fx::classFor(['id' => 9, 'adjust' => 'vivid']) === 'ifx ifx-vivid';
        $fx::leave();
        $this->assert($ok && $fx::classFor(['id' => 7, 'adjust' => '']) === '', 'ImageFx: Einbindung vor global, erster Pfad gewinnt');
        $html = $fx::inject('<html><head><title>x</title></head><body><img class="a ifx ifx-gray"></body></html>');
        $this->assert(substr_count($html, 'data-ifx-css') === 1 && $fx::inject('<head></head><img class="fx50">') === '<head></head><img class="fx50">', 'ImageFx: Stylesheet nur bei Bedarf');
        // Schärfe/Unschärfe: Format, Klassen, SVG-Filter nur bei Bedarf
        $this->assert($fx::normalize('sharp+40 sepia') === 'sepia sharp40' && $fx::normalize('sharp0') === '' && $fx::normalize('gray sharp-60 s120') === 'gray s120 sharp-60', 'ImageFx: Schärfe kanonisch');
        $this->assert($fx::normalize('sharp45') === null && $fx::normalize('sharp110') === null && $fx::normalize('sharp-110') === null && $fx::normalize('sharp20 sharp30') === null, 'ImageFx: ungültige Schärfe abgelehnt');
        $this->assert($fx::normalize(['sharpness' => -20]) === 'sharp-20' && $fx::normalize(['preset' => 'warm', 'k' => 80]) === 'warm sharp80', 'ImageFx: Schärfe als Objekt');
        $this->assert($fx::classes('sharp40') === 'ifx ifx-sharp-p4' && $fx::classes('c120 sharp-60') === 'ifx ifx-c12 ifx-sharp-m6'
            && $fx::classes('sharp-100') === 'ifx ifx-sharp-m10' && $fx::classes('sharp10') === 'ifx ifx-sharp-p1', 'ImageFx: Schärfe-Klassen');
        $defs = $fx::defs();
        $this->assert(substr_count($defs, '<filter id="ifx-sharp-') === 20 && str_contains($defs, 'id="ifx-sharp-m10"') && str_contains($defs, 'id="ifx-sharp-p10"')
            && !str_contains($defs, 'style='), 'ImageFx: SVG-Filter m1–m10/p1–p10 ohne style-Attribut');
        $page = '<html><head></head><body><img class="ifx ifx-sharp-p4"></body></html>';
        $out = $fx::inject($fx::inject($page));
        $this->assert(substr_count($out, 'id="ifx-defs"') === 1 && strpos($out, 'id="ifx-defs"') < strpos($out, '</body>')
            && !str_contains($fx::inject('<html><head></head><body><img class="ifx ifx-gray"></body></html>'), 'ifx-defs')
            && str_contains($fx::inject('<html><head></head><body></body></html>', true), 'id="ifx-defs"'), 'ImageFx: SVG-Filter nur bei Bedarf (und im Bearbeiten-Modus)');
    }

    /** Bild im Rahmen (Core\ImageFit): Format, Vorrang, Klassen am <picture>, erzeugte Regeln, Bereinigung, Transparenz */
    private function imageFit(): void
    {
        $f = \Core\ImageFit::class;
        $f::reset();
        $this->assert($f::normalize('CONTAIN  #1E2638') === 'contain #1e2638' && $f::normalize('contain transparent') === 'contain'
            && $f::normalize('contain #abc') === 'contain #aabbcc' && $f::normalize('') === '', 'ImageFit: kanonische Schreibweise');
        $this->assert($f::normalize('original blur') === null && $f::normalize('cover #fff') === null && $f::normalize('stretch') === null
            && $f::normalize('contain red') === null && $f::normalize('contain url(x)') === null && $f::normalize('contain kit:Sur-face') === null, 'ImageFit: ungültige Werte abgelehnt');
        $this->assert($f::normalize(['mode' => 'contain', 'bg' => 'blur']) === 'contain blur' && $f::normalize(['mode' => 'original']) === 'original', 'ImageFit: Angabe als Objekt');
        $fields = [['name' => 'image', 'type' => 'media'], ['name' => 'items', 'type' => 'repeater', 'fields' => [['name' => 'image', 'type' => 'media']]]];
        $clean = $f::sanitize(['image' => 'contain blur', 'items.0.image' => 'original', 'items.1.image' => 'contain', 'image2' => 'contain', 'items.0.image.x' => 'cover'],
            $fields, ['image' => 7, 'items' => [['image' => 8], ['image' => null]]]);
        $this->assert($clean === ['image' => 'contain blur', 'items.0.image' => 'original'], 'ImageFit: data._fit bereinigt (nur Bild-Felder mit Bild)');
        $jpg = ['id' => 7, 'mime' => 'image/jpeg', 'variants_json' => '{}', 'fit' => ''];
        $svg = ['id' => 9, 'mime' => \Core\Svg::MIME, 'variants_json' => '{}', 'fit' => ''];
        $png = ['id' => 11, 'mime' => 'image/png', 'variants_json' => '{"alpha":true}', 'fit' => '', 'crops' => ''];
        $this->assert($f::resolve($jpg) === null && $f::resolve($svg)['mode'] === 'contain' && $f::resolve($svg)['auto'] === true
            && $f::resolve($png)['mode'] === 'contain', 'ImageFit: automatisch – Foto füllt, SVG und transparenter Rand passen ein');
        $f::enter(['_fit' => ['image' => 'original', 'items.0.image' => 'contain'], 'image' => 7, 'items' => [['image' => 9]]]);
        $r1 = $f::resolve(['fit' => 'contain blur'] + $jpg);
        $r2 = $f::resolve($svg);
        $f::leave();
        $f::enter(['_fit' => ['image' => 'cover'], 'image' => 9]);
        $r3 = $f::resolve($svg);
        $f::leave();
        $this->assert($r1['mode'] === 'original' && $r2['mode'] === 'contain' && $r2['auto'] === false && $r3 === null
            && $f::resolve(['fit' => 'contain blur'] + $jpg)['bg'] === 'blur', 'ImageFit: Einbindung vor Mediathek vor automatisch, „Füllen“ hebt auf');
        $cls = $f::pictureClass(['mode' => 'contain', 'bg' => '#1e2638', 'auto' => false], $jpg);
        $this->assert($cls === 'img-fit img-fit--contain img-fit-c-1e2638' && str_contains($f::css(), '.img-fit-c-1e2638{--img-fit-bg:#1e2638}'), 'ImageFit: Klassen und Regel für Farbe');
        $this->assert($f::pictureClass(['mode' => 'original', 'bg' => '', 'auto' => true], $jpg) === 'img-fit img-fit--original img-fit--auto', 'ImageFit: Originalformat');
        // Einpassen in einer Stelle mit Bildformat: <img> im Format des Rahmens (Kits ohne feste Bildhöhe, z. B. Porträt im Zoom-Link)
        $this->assert($f::pictureClass(['mode' => 'contain', 'bg' => '', 'auto' => false], $jpg, '3:4') === 'img-fit img-fit--contain img-fit--framed img-fit-r-3x4'
            && str_contains($f::css(), '.img-fit-r-3x4{--img-fit-ratio:3/4}')
            && $f::pictureClass(['mode' => 'contain', 'bg' => '', 'auto' => true], $jpg, '3:4') === 'img-fit img-fit--contain img-fit--auto'
            && $f::pictureClass(['mode' => 'original', 'bg' => '', 'auto' => false], $jpg, '3:4') === 'img-fit img-fit--original'
            && str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/resources/css/image-fit.css'), '.img-fit--framed>img{aspect-ratio:var(--img-fit-ratio)'), 'ImageFit: Format der Stelle beim Einpassen');
        // „Unscharf“: vorab weichgezeichnete Kopie als Hintergrund des <img> (kein ::before am <picture> – Kits mit
        // picture{display:contents} legten die Ebene sonst neben, über oder hinter den Rahmen)
        $f::reset();
        $dir = site()->mediaDir('fit');
        @mkdir($dir, 0775, true);
        $srcRel = 'fit/_selftest-' . bin2hex(random_bytes(4)) . '.jpg';
        $pic = imagecreatetruecolor(90, 160);
        imagefilledrectangle($pic, 0, 0, 44, 159, imagecolorallocate($pic, 200, 40, 40));
        imagefilledrectangle($pic, 45, 0, 89, 159, imagecolorallocate($pic, 30, 90, 200));
        imagejpeg($pic, site()->mediaDir() . '/' . $srcRel, 90);
        imagedestroy($pic);
        $port = ['id' => 0, 'mime' => 'image/jpeg', 'file' => $srcRel, 'width' => 90, 'height' => 160, 'variants_json' => '{}', 'fit' => 'contain blur', 'alt' => 'x', 'decorative' => 0];
        $blurCls = $f::pictureClass(['mode' => 'contain', 'bg' => 'blur', 'auto' => false], $port);
        preg_match('~url\("([^"]+/fit/(blur-[0-9a-f]{12}\.(?:webp|jpg)))"\)~', $f::css(), $bm);
        $blurFile = $bm ? $dir . '/' . $bm[2] : '';
        $bi = $blurFile && is_file($blurFile) ? @getimagesize($blurFile) : false;
        $markup = \Core\Media::pictureOf($port, '50vw');
        $this->assert((bool) preg_match('~^img-fit img-fit--contain img-fit--blur img-fit-src-[0-9a-f]{10}$~', $blurCls) && $bi && $bi[1] > $bi[0]
            && filesize($blurFile) < 20000 && str_contains($f::css(), '.' . substr($blurCls, strrpos($blurCls, ' ') + 1) . '{--img-fit-src:url("'),
            'ImageFit: unscharf – Klassen, Regel und erzeugte weichgezeichnete Kopie (Hochformat, klein)');
        $this->assert((bool) preg_match('~^<picture class="img-fit img-fit--contain img-fit--blur img-fit-src-[0-9a-f]{10}">.*<img [^>]*></picture>$~s', $markup),
            'ImageFit: unscharf – Markup von Media::pictureOf()');
        $this->assert($f::pictureClass(['mode' => 'contain', 'bg' => 'blur', 'auto' => false], $svg) === 'img-fit img-fit--contain img-fit--blur',
            'ImageFit: unscharf bei SVG ohne erzeugte Kopie (wie transparent)');
        $fitCss = (string) @file_get_contents(ROOT . '/resources/css/image-fit.css');
        $this->assert(str_contains($fitCss, '.img-fit--blur>img{background:var(--img-fit-src') && !str_contains($fitCss, '.img-fit--blur::before'),
            'ImageFit: unscharf liegt am <img>, nicht als ::before am <picture>');
        @unlink(site()->mediaDir() . '/' . $srcRel);
        if ($blurFile) @unlink($blurFile);
        $f::reset();
        $f::pictureClass(['mode' => 'contain', 'bg' => '#1e2638', 'auto' => false], $jpg);
        $file = site()->mediaDir('fit') . '/fit-' . substr(sha1($f::css()), 0, 12) . '.css';
        $had = is_file($file);
        $html = $f::inject('<html><head></head><body><picture class="img-fit img-fit--contain img-fit-c-1e2638"><img></picture></body></html>');
        if (!$had) @unlink($file);   // Testdatei nicht liegen lassen
        $this->assert(substr_count($html, 'data-img-fit-css') === 1 && str_contains($html, '/fit/fit-') && $f::css() === ''
            && $f::inject('<head></head><picture><img></picture>') === '<head></head><picture><img></picture>', 'ImageFit: Stylesheet und Regeln nur bei Bedarf');
        // Transparenter Rand: Logo auf durchsichtigem Grund ja, deckendes Bild nein
        $logo = imagecreatetruecolor(60, 40);
        imagealphablending($logo, false);
        imagesavealpha($logo, true);
        imagefill($logo, 0, 0, imagecolorallocatealpha($logo, 0, 0, 0, 127));
        imagefilledrectangle($logo, 15, 10, 45, 30, imagecolorallocatealpha($logo, 200, 30, 30, 0));
        $photo = imagecreatetruecolor(60, 40);
        imagefill($photo, 0, 0, imagecolorallocate($photo, 90, 120, 150));
        $this->assert($f::edgeAlpha($logo) && !$f::edgeAlpha($photo), 'ImageFit: transparenter Rand erkannt');
        $f::reset();
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
