<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Api\OpenApi;
use Core\Http\Controllers\McpController;
use Core\Http\Request;
use Core\Http\Response;

/** Handbuch (Redaktion) und technische Dokumentation (Entwicklung, API, MCP). */
final class HelpController extends AdminController
{
    public function manual(Request $r): Response
    {
        $this->auth($r);
        $vars = ['css' => ['css/docs.css'], 'blocks' => app()->theme->blocks(), 'themeDoc' => []];
        // Ein Theme ergänzt das Handbuch über docs/manual.php: Die Datei gibt ein Array zurück (eigene Kapitel,
        // Kern-Kapitel ersetzen oder ergänzen – siehe help/manual.php). Ältere Themes liefern dort eine vollständige
        // Ansicht (kein Array) – die wird wie bisher statt des Kern-Handbuchs gezeigt.
        $file = app()->theme->path . '/docs/manual.php';
        if (is_file($file)) {
            ob_start();
            $def = (static fn(string $__file) => include $__file)($file);
            ob_end_clean();
            if (!is_array($def)) {
                return $this->view('help/theme-manual', $vars + ['themeManual' => $file]);
            }
            $vars['themeDoc'] = $def + ['dir' => dirname($file)];
        }
        return $this->view('help/manual', $vars);
    }

    /** Übersicht aller Symbole nach Themen (Core\Icons) – für die Symbolauswahl und Themes */
    public function icons(Request $r): Response
    {
        $this->auth($r);
        return $this->view('help/icons', ['title' => __('Symbole'), 'catalog' => \Core\Icons::catalog()]);
    }

    /** Lizenzen & Danksagungen: Projektlizenz (LICENSE, COPYRIGHT) und THIRD-PARTY-NOTICES.md (Drittsoftware, Schriften, Daten, Medien) */
    public function licenses(Request $r): Response
    {
        $this->auth($r);
        $read = fn(string $f) => is_file(ROOT . '/' . $f) ? (string) file_get_contents(ROOT . '/' . $f) : '';
        [$html, $toc] = self::noticesHtml($read('THIRD-PARTY-NOTICES.md'));
        return $this->view('help/licenses', ['title' => __('Lizenzen & Danksagungen'), 'css' => ['css/docs.css'],
            'notices' => $html, 'toc' => $toc, 'license' => $read('LICENSE'), 'copyright' => $read('COPYRIGHT')]);
    }

    /**
     * THIRD-PARTY-NOTICES.md → HTML (feste Datei aus dem Projekt, kein Nutzerinhalt; trotzdem alles escaped):
     * ## / ### Überschriften (mit Anker), Tabellen, Listen mit eingerückten Folgezeilen, Absätze;
     * inline `Code`, **fett**, [Text](https://…) und nackte https-Adressen. # (Titel) entfällt – die Seite hat eine eigene Überschrift.
     * @return array{0: string, 1: list<array{0: string, 1: string}>}  HTML und Inhaltsverzeichnis [Anker, Titel] der ##-Kapitel
     */
    private static function noticesHtml(string $md): array
    {
        $inline = function (string $s): string {
            $codes = [];
            $s = preg_replace_callback('~`([^`]+)`~', function ($m) use (&$codes) { $codes[] = '<code>' . e($m[1]) . '</code>'; return "\x01" . (count($codes) - 1) . "\x02"; }, $s);
            $s = e($s);
            $s = preg_replace('~\*\*(.+?)\*\*~', '<b>$1</b>', $s);
            $s = preg_replace('~\[([^\]]+)\]\((https?://[^\s)]+)\)~', '<a href="$2" rel="noopener noreferrer" target="_blank">$1</a>', $s);
            $s = preg_replace('~(?<![">])\bhttps?://[^\s<)|,]+[^\s<)|,.:;]~', '<a href="$0" rel="noopener noreferrer" target="_blank">$0</a>', $s);
            return preg_replace_callback("~\x01(\d+)\x02~", fn($m) => $codes[(int) $m[1]], $s);
        };
        $lines = explode("\n", str_replace("\r\n", "\n", $md));
        $out = '<section class="doc-ch" id="l-intro">';
        $toc = [];
        $para = [];
        $list = [];
        $flush = function () use (&$para, &$list, &$out, $inline) {
            if ($para) $out .= '<p>' . $inline(implode(' ', $para)) . '</p>';
            if ($list) $out .= '<ul>' . implode('', array_map(fn($i) => '<li>' . $inline($i) . '</li>', $list)) . '</ul>';
            $para = $list = [];
        };
        $cells = fn(string $row) => array_map('trim', explode('|', trim(trim($row), '|')));
        for ($i = 0, $n = count($lines); $i < $n; $i++) {
            $line = $lines[$i];
            if (trim($line) === '') { $flush(); continue; }
            if (preg_match('~^(#{1,4})\s+(.+)$~', $line, $m)) {
                $flush();
                if (strlen($m[1]) === 1) continue;
                $id = 'l-' . trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower($m[2])), '-');
                $tag = strlen($m[1]) === 2 ? 'h2' : 'h3';
                if ($tag === 'h2') { $out .= '</section><section class="doc-ch" id="' . e($id) . '">'; $toc[] = [$id, $m[2]]; }
                $out .= "<$tag" . ($tag === 'h3' ? ' id="' . e($id) . '"' : '') . '>' . $inline($m[2]) . "</$tag>";
                continue;
            }
            if (str_starts_with(ltrim($line), '|') && isset($lines[$i + 1]) && preg_match('~^\s*\|[\s:|-]+\|\s*$~', $lines[$i + 1])) {
                $flush();
                $out .= '<div class="doc-scroll"><table class="doc-table doc-table--wrap"><thead><tr>' . implode('', array_map(fn($c) => '<th>' . $inline($c) . '</th>', $cells($line))) . '</tr></thead><tbody>';
                for ($i += 2; $i < $n && str_starts_with(ltrim($lines[$i]), '|'); $i++) {
                    $out .= '<tr>' . implode('', array_map(fn($c) => '<td>' . $inline($c) . '</td>', $cells($lines[$i]))) . '</tr>';
                }
                $i--;
                $out .= '</tbody></table></div>';
                continue;
            }
            if (preg_match('~^[-*]\s+(.*)$~', $line, $m)) { if ($para) $flush(); $list[] = $m[1]; continue; }
            if ($list && preg_match('~^\s{2,}\S~', $line)) { $list[count($list) - 1] .= ' ' . trim($line); continue; }
            if ($list) $flush();
            $para[] = trim($line);
        }
        $flush();
        return [$out . '</section>', $toc];
    }

    /**
     * Tutorials für Redaktion, Administration und Agenturen (Inhalte: app/Admin/tutorials.php, Englisch tutorials.en.php).
     * Die Videos liegen auf der Produkt-Website (config 'docs_url', Core\Tutorials) – hier nur Text und Links nach außen.
     */
    public function tutorials(Request $r): Response
    {
        $this->auth($r);
        $data = self::tutorialData();
        return $this->view('help/tutorials', ['title' => __('Tutorials'), 'css' => ['css/docs.css', 'css/tutorials.css']] + $data);
    }

    public function tutorial(Request $r, string $slug): Response
    {
        $this->auth($r);
        $data = self::tutorialData();
        $tut = $data['tutorials'][$slug] ?? throw new \Core\Http\HttpException(404);
        // „Weiter zu …“: vorheriges und nächstes Tutorial derselben Zielgruppe
        $same = array_keys(array_filter($data['tutorials'], fn($t) => $t['track'] === $tut['track']));
        $i = (int) array_search($slug, $same, true);
        return $this->view('help/tutorial', ['title' => $tut['title'] . ' · ' . __('Tutorials'), 'css' => ['css/docs.css', 'css/tutorials.css'],
            'slug' => $slug, 'tut' => $tut, 'track' => $data['tracks'][$tut['track']] ?? [],
            'prev' => $same[$i - 1] ?? null, 'next' => $same[$i + 1] ?? null] + $data);
    }

    /** Katalog (Kern + Kit, Sprache der Verwaltung), empfohlene Zielgruppe nach Rolle, Adresse der Produkt-Website */
    private static function tutorialData(): array
    {
        $c = \Core\Tutorials::catalogue();
        return $c + ['recommended' => \Core\Tutorials::recommended($c['tracks']), 'docsHost' => \Core\Tutorials::docsHost(),
            'overviewUrl' => \Core\Tutorials::overviewUrl(), 'trailerUrl' => \Core\Tutorials::trailerUrl()];
    }

    public function technical(Request $r): Response
    {
        $this->auth($r);
        return $this->view('help/technical', [
            'css' => ['css/docs.css'],
            'openapi' => OpenApi::spec(),
            'mcp' => McpController::catalog(),
            'fieldTypes' => \Core\Fields::TYPES,
            'blocks' => app()->theme->blocks(),
        ]);
    }
}
