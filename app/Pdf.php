<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * PDF aus HTML – für Kern und Erweiterungen (Report, AVV, Rechnungen …), mit austauschbarer Engine wie Core\Charts.
 *
 *   $bytes = Pdf::render($html, ['paper' => 'A4', 'font' => 'lato', 'title' => 'Report']);
 *   return Pdf::response($bytes, 'Report-2026-10.pdf');            // Download (inline: true = im Browser öffnen)
 *
 * Standard-Engine: dompdf (Dompdf\Dompdf, LGPL-2.1 – nicht Teil des MIT-Kerns). Bereitgestellt vom Paket klxm/studio-pdf
 * (Erweiterung „pdf“, installiert = verfügbar, ohne Einschalten) oder von Composer des Projekts bzw. einer anderen Erweiterung. Weitere Engines: Pdf::registerEngine('name', fn(string $html, array $opt): string),
 * Wahl per Konfiguration 'pdf' => ['engine' => 'name']. Ohne Engine: Pdf::available() === false (Aufrufer zeigen dann z. B. „Drucken“).
 *
 * Sicherheit (dompdf): keine Dateien aus dem Netz (isRemoteEnabled aus), kein PHP im HTML, Zugriff nur auf den eigenen Ordner
 * (chroot) – Bilder als data:-URL einbetten. Schriften: DejaVu Sans (in dompdf) oder eine freie Schrift von Fontsource (OFL),
 * einmal je Installation als TTF unter storage/pdf-fonts/ geladen (Pdf::font('lato')), danach ohne Netz.
 */
final class Pdf
{
    /** @var array<string, callable> */
    private static array $engines = [];
    private const FACES = [['400', 'normal'], ['700', 'normal'], ['400', 'italic'], ['700', 'italic']];

    public static function registerEngine(string $name, callable $fn): void
    {
        if (preg_match('~^[a-z][a-z0-9_-]{0,30}$~', $name)) self::$engines[$name] = $fn;
    }

    public static function engine(): ?string
    {
        $want = (string) (app()->config->get('pdf.engine', '') ?: '');
        if ($want !== '' && isset(self::$engines[$want])) return $want;
        // Manifeste installierter Erweiterungen lesen: Pakete wie klxm/studio-pdf laden dabei ihre Bibliothek
        if (!class_exists(\Dompdf\Dompdf::class)) Extensions::available();
        if (class_exists(\Dompdf\Dompdf::class)) return 'dompdf';
        return array_key_first(self::$engines);
    }

    public static function available(): bool
    {
        return self::engine() !== null;
    }

    /**
     * HTML → PDF-Bytes. $opt: paper (A4), orientation (portrait|landscape), font (Fontsource-Kennung, z. B. 'lato'; leer = DejaVu Sans),
     * title, dir (Ordner für Hilfsdateien/chroot, Standard storage/pdf), footer (Text unten links), pages (true = „Seite x von y“ unten rechts).
     */
    public static function render(string $html, array $opt = []): string
    {
        $engine = self::engine() ?? throw new \RuntimeException(__('Keine PDF-Engine installiert – Paket klxm/studio-pdf (Erweiterung „pdf“) installieren.'));
        if ($engine !== 'dompdf') return (string) (self::$engines[$engine])($html, $opt);
        $dir = (string) ($opt['dir'] ?? self::dir());
        $o = new \Dompdf\Options();
        $o->set('isRemoteEnabled', false);
        $o->set('isPhpEnabled', false);
        $o->set('isHtml5ParserEnabled', true);
        $o->set('defaultFont', 'DejaVu Sans');
        $o->set('tempDir', sys_get_temp_dir());
        $o->set('fontCache', $dir);
        $o->setChroot([$dir, self::fontDir()]);
        $pdf = new \Dompdf\Dompdf($o);
        $fontCss = !empty($opt['font']) ? self::fontCss((string) $opt['font']) : '';
        if ($fontCss !== '' && str_contains($html, '</head>')) $html = str_replace('</head>', '<style>' . $fontCss . '</style></head>', $html);
        elseif ($fontCss !== '') $html = '<style>' . $fontCss . '</style>' . $html;
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper((string) ($opt['paper'] ?? 'A4'), (string) ($opt['orientation'] ?? 'portrait'));
        if (!empty($opt['title'])) $pdf->addInfo('Title', (string) $opt['title']);
        $pdf->addInfo('Creator', 'KLXM Studio');
        $pdf->render();
        if (!empty($opt['pages']) || !empty($opt['footer'])) {
            $canvas = $pdf->getCanvas();
            $fm = $pdf->getFontMetrics();
            $font = ($fontCss !== '' ? $fm->getFont('PdfFont') : null) ?: $fm->getFont('DejaVu Sans');
            [$w, $h] = [$canvas->get_width(), $canvas->get_height()];
            if (!empty($opt['pages'])) $canvas->page_text($w - 110, $h - 28, __('Seite {n} von {total}', ['n' => '{PAGE_NUM}', 'total' => '{PAGE_COUNT}']), $font, 7.5, [0.4, 0.4, 0.4]);
            if (!empty($opt['footer'])) $canvas->page_text(42, $h - 28, (string) $opt['footer'], $font, 7.5, [0.4, 0.4, 0.4]);
        }
        return (string) $pdf->output();
    }

    /** Antwort mit PDF (Download oder inline), nie zwischengespeichert */
    public static function response(string $bytes, string $filename, bool $inline = false): Http\Response
    {
        $name = preg_replace('~[^A-Za-z0-9._-]+~', '-', $filename) ?: 'dokument.pdf';
        return (new Http\Response($bytes))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"')
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex');
    }

    public static function dir(): string
    {
        $d = site()->storage('pdf');
        if (!is_dir($d)) @mkdir($d, 0770, true);
        return $d;
    }

    // ------------------------------------------------------------------ Schriften (Fontsource, OFL)

    private static function fontDir(): string
    {
        $d = ROOT . '/storage/pdf-fonts';
        if (!is_dir($d)) @mkdir($d, 0770, true);
        return $d;
    }

    private static function fontFile(string $id, string $w, string $st): string
    {
        return self::fontDir() . "/$id-$w-$st.ttf";
    }

    /** Schrift laden (falls nötig): Normal, Fett, Kursiv, Fett kursiv als TTF → Fehlertext oder null */
    public static function font(string $id): ?string
    {
        if (!preg_match('~^[a-z0-9][a-z0-9-]{0,63}$~', $id)) return __('Ungültige Schrift.');
        foreach (self::FACES as [$w, $st]) {
            $f = self::fontFile($id, $w, $st);
            if (is_file($f)) continue;
            $bin = self::download("https://cdn.jsdelivr.net/fontsource/fonts/$id@latest/latin-$w-$st.ttf");
            if ($bin === null) {
                if ($w === '400' && $st === 'normal') return __('Die Schrift „{name}“ konnte nicht geladen werden – das PDF nutzt DejaVu Sans.', ['name' => $id]);
                continue;
            }
            file_put_contents($f, $bin, LOCK_EX);
        }
        return null;
    }

    private static function download(string $url): ?string
    {
        if (!function_exists('curl_init')) return null;
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'KLXM-Studio-PDF']);
        $bin = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (!is_string($bin) || $status !== 200 || strlen($bin) > 2_000_000 || strlen($bin) < 1000) return null;
        return in_array(substr($bin, 0, 4), ["\x00\x01\x00\x00", 'true', 'OTTO'], true) ? $bin : null;
    }

    /** @font-face für dompdf (Familie „PdfFont“) und body-Schrift; leer, wenn die Schrift fehlt und nicht geladen werden kann */
    public static function fontCss(string $id): string
    {
        if (!is_file(self::fontFile($id, '400', 'normal')) && self::font($id) !== null) return '';
        $out = '';
        foreach (self::FACES as [$w, $st]) {
            $f = self::fontFile($id, $w, $st);
            if (is_file($f)) $out .= '@font-face{font-family:"PdfFont";font-weight:' . $w . ';font-style:' . $st . ';src:url("' . $f . '") format("truetype")}';
        }
        return $out . 'body{font-family:"PdfFont","DejaVu Sans",sans-serif}';
    }
}
