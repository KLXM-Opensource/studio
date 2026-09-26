<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Design;
use Core\Features;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Pages;

/**
 * Verwaltung → Design (Style-Editor): Farben, Formen, Schriften des aktiven Themes (theme.php → 'design').
 * Werte gelten je Website und Theme; gespeichert über Design::save() (mit Verlauf).
 */
final class DesignController extends AdminController
{
    private function guard(Request $r): array
    {
        if (!Features::on('design')) throw new HttpException(404);
        return $this->auth($r, 'design.edit');
    }

    public function edit(Request $r): Response
    {
        $this->guard($r);
        $theme = app()->theme;
        $vars = ['themeLabel' => $theme->label(), 'themeName' => $theme->name, 'enabled' => Design::enabled()];
        if ($vars['enabled']) {
            $values = Design::values();
            $fontCss = [];
            foreach ((array) (Design::def()['fonts'] ?? []) as $f) {
                if (!empty($f['css'])) $fontCss[$f['css']] = $theme->asset((string) $f['css']);
            }
            // Installierte Schriften (Core\Fonts) für die Schriftproben
            foreach (Design::fonts() as $f) if (!empty($f['href'])) $fontCss[$f['href']] = (string) $f['href'];
            $vars += [
                'schema' => Design::schema(),
                'values' => $values,
                'defaults' => Design::defaults(),
                'checks' => Design::checks($values),
                'history' => array_map(fn($h) => ['values' => Design::normalize((array) ($h['values'] ?? []))] + $h, Design::history()),
                'pages' => self::previewPages(),
                'fontCss' => array_values($fontCss),
            ];
        }
        return $this->view('design', $vars);
    }

    public function save(Request $r): Response
    {
        $this->guard($r);
        if (!Design::enabled()) return $this->back('/admin/design');
        Design::save((array) ($r->post['v'] ?? []), (string) ($r->post['_note'] ?? ''));
        $this->changed();
        $tab = preg_replace('~[^\w\-]~', '', (string) ($r->post['_tab'] ?? ''));
        return $this->back('/admin/design' . ($tab !== '' ? '#' . $tab : ''), 'success', __('Design gespeichert. Die Website verwendet die neuen Werte ab dem nächsten Aufruf.'));
    }

    /** Live-Vorschau: Seite mit den ungespeicherten Werten (nur im Speicher); _dark = dunkle Werte erzwingen */
    public function preview(Request $r): Response
    {
        $this->guard($r);
        $values = Design::normalize((array) ($r->post['v'] ?? []));
        Design::override($values);
        $id = (string) ($r->post['_page'] ?? '');
        $page = ctype_digit($id) ? Pages::find((int) $id) : Pages::home();
        if (!$page) {
            return Response::json(['ok' => false, 'error' => __('Keine Startseite gefunden.')], 404);
        }
        $html = (new \Core\Http\Controllers\SiteController())->previewHtml($page);
        if (!empty($r->post['_dark'])) $html = self::forceDark($html, $values);
        return Response::json(['ok' => true, 'html' => $html, 'checks' => Design::checks($values)]);
    }

    /** Import prüfen: JSON (Export oder reine Werte) → normalisierte Werte + Hinweise */
    public function import(Request $r): Response
    {
        $this->guard($r);
        $in = json_decode((string) ($r->post['json'] ?? ''), true);
        if (!is_array($in)) {
            return Response::json(['ok' => false, 'error' => __('Kein gültiges JSON.')], 422);
        }
        $theme = isset($in['values']) && is_string($in['theme'] ?? null) ? $in['theme'] : null;
        $raw = isset($in['values']) && is_array($in['values']) ? $in['values'] : $in;
        $known = array_keys(Design::defaults());
        $ignored = array_values(array_diff(array_keys($raw), $known));
        if (!array_intersect(array_keys($raw), $known)) {
            return Response::json(['ok' => false, 'error' => __('Die Daten enthalten keine bekannten Design-Werte dieses Kits.')], 422);
        }
        return Response::json(['ok' => true, 'values' => Design::normalize($raw), 'ignored' => $ignored,
            'other_theme' => $theme !== null && $theme !== app()->theme->name ? $theme : null]);
    }

    /** Seiten für die Vorschau-Auswahl; Musterseite des Themes (design.sample = Pfad) zuerst */
    private static function previewPages(): array
    {
        $out = [];
        $sample = (string) (Design::def()['sample'] ?? '');
        foreach (Pages::flat() as $p) {
            if (($p['type'] ?? 'page') !== 'page') continue;
            $isSample = $sample !== '' ? in_array($sample, [(string) ($p['path'] ?? ''), (string) ($p['slug'] ?? '')], true)
                : in_array((string) ($p['slug'] ?? ''), ['muster', 'musterseite', 'styleguide'], true);
            $row = ['id' => (int) $p['id'], 'title' => (string) $p['title'], 'depth' => (int) ($p['depth'] ?? 0), 'home' => !empty($p['is_home']), 'sample' => $isSample];
            if ($isSample) array_unshift($out, $row); else $out[] = $row;
            if (count($out) >= 60) break;
        }
        return $out;
    }

    /** Vorschau „Dunkel“: dunkle Werte ohne Media-Query erzwingen, optional Theme-Schalter (dark.force) am <html> */
    public static function forceDark(string $html, array $values): string
    {
        $vars = [];
        foreach (Design::tokens() as $n => $t) {
            if (!empty($t['var']) && isset($values[$n . '@dark'])) $vars[] = $t['var'] . ':' . $values[$n . '@dark'];
        }
        $d = (array) (Design::def()['dark'] ?? []);
        $sel = 'html:root' . (!empty($d['scope']) ? ',' . $d['scope'] : '');
        $style = '<style data-design-dark>html:root{color-scheme:dark}' . ($vars ? $sel . '{' . implode(';', $vars) . '}' : '') . '</style>';
        $html = preg_replace('~</head>~i', $style . '</head>', $html, 1) ?? $html;
        $force = trim((string) ($d['force'] ?? ''));
        if ($force !== '') {
            $html = preg_replace_callback('~<html\b([^>]*)>~i', function ($m) use ($force) {
                $attrs = $m[1];
                if (str_contains($force, '=')) {
                    [$k, $v] = array_map(fn($x) => trim($x, " \"'"), explode('=', $force, 2));
                    $attrs = preg_replace('~\s' . preg_quote($k, '~') . '="[^"]*"~', '', $attrs) . ' ' . e($k) . '="' . e($v) . '"';
                } elseif (preg_match('~\sclass="([^"]*)"~', $attrs)) {
                    $attrs = preg_replace('~\sclass="([^"]*)"~', ' class="$1 ' . e($force) . '"', $attrs, 1);
                } else {
                    $attrs .= ' class="' . e($force) . '"';
                }
                return '<html' . $attrs . '>';
            }, $html, 1) ?? $html;
        }
        return $html;
    }
}
