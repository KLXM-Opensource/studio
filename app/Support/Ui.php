<?php
declare(strict_types=1);

namespace Core\Support;

/** Kleine Bausteine für die Support-Ansichten (app/Admin/views/support/*) */
final class Ui
{
    public static function status(string $s): string
    {
        return '<span class="sp-badge sp-badge--' . e($s) . '">' . e(Support::statusLabel($s)) . '</span>';
    }

    public static function priority(string $p): string
    {
        return $p === 'dringend' ? '<span class="sp-badge sp-badge--urgent"><span aria-hidden="true">!</span> ' . e(__('Dringend')) . '</span>' : '';
    }

    public static function category(string $c): string
    {
        $ico = ['frage' => '?', 'fehler' => '⚠', 'wunsch' => '✦'][$c] ?? '•';
        return '<span class="sp-cat"><span aria-hidden="true">' . $ico . '</span> ' . e(Support::categoryLabel($c)) . '</span>';
    }

    /** Relatives Datum mit maschinenlesbarem <time> */
    public static function when(?string $at): string
    {
        if (!$at) return '';
        $ts = strtotime($at);
        $d = time() - $ts;
        $txt = match (true) {
            $d < 60 => __('gerade eben'),
            $d < 3600 => __('vor {n} Min.', ['n' => (int) floor($d / 60)]),
            $d < 86400 && date('Y-m-d', $ts) === date('Y-m-d') => __('heute, {time}', ['time' => date('H:i', $ts)]),
            $d < 172800 && date('Y-m-d', $ts) === date('Y-m-d', time() - 86400) => __('gestern, {time}', ['time' => date('H:i', $ts)]),
            default => date('d.m.Y', $ts),
        };
        return '<time datetime="' . e(date('c', $ts)) . '" title="' . e(date('d.m.Y H:i', $ts)) . '">' . e($txt) . '</time>';
    }

    public static function tags(string $stored, string $base = '/admin/support/wissen'): string
    {
        $out = '';
        foreach (Support::tagList($stored) as $t) {
            $out .= '<a class="sp-tag" href="' . e(url($base) . '?tag=' . rawurlencode($t)) . '">#' . e($t) . '</a>';
        }
        return $out ? '<span class="sp-tags">' . $out . '</span>' : '';
    }

    /** Suchauszug mit Treffermarken (\x02 … \x03 aus FTS5 snippet()) */
    public static function snippet(?string $s): string
    {
        if ($s === null || $s === '') return '';
        return str_replace(["\x02", "\x03"], ['<mark>', '</mark>'], e($s));
    }

    /** Anzahl mit Einzahl/Mehrzahl: Ui::n(1, 'Aufruf') → „1 Aufruf“, sonst „{n} Aufrufe“ */
    public static function n(int $n, string $kind): string
    {
        return match ($kind) {
            'views' => $n === 1 ? __('1 Aufruf') : __('{n} Aufrufe', ['n' => $n]),
            'answers' => $n === 1 ? __('1 Antwort') : __('{n} Antworten', ['n' => $n]),
            'messages' => $n === 1 ? __('1 Nachricht') : __('{n} Nachrichten', ['n' => $n]),
            default => (string) $n,
        };
    }

    /** Hinweis zur Formatierung unter Textfeldern */
    public static function mdHint(string $id): string
    {
        return '<p class="f-hint sp-mdhint" id="' . e($id) . '">' . e(__('Formatierung: Leerzeile = neuer Absatz · „- “ = Liste · „1. “ = nummerierte Liste · `Code` · **fett** · [Linktext](https://…) · ``` für Code-Blöcke')) . '</p>';
    }

    public static function pager(int $page, int $pages, callable $qs): string
    {
        if ($pages <= 1) return '';
        $out = '<nav class="dt-pager" aria-label="' . e(__('Seiten')) . '">';
        if ($page > 1) $out .= '<a href="' . e($qs(['seite' => $page - 1])) . '">← ' . e(__('Zurück')) . '</a> ';
        $out .= e(__('Seite {page} / {pages}', ['page' => $page, 'pages' => $pages]));
        if ($page < $pages) $out .= ' <a href="' . e($qs(['seite' => $page + 1])) . '">' . e(__('Weiter')) . ' →</a>';
        return $out . '</nav>';
    }
}
