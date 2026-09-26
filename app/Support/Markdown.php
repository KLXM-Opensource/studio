<?php
declare(strict_types=1);

namespace Core\Support;

use Core\Sanitizer;

/**
 * Markdown-lite für Meldungen, Fragen, Antworten und Wissensartikel.
 * Unterstützt: Absätze, Zeilenumbrüche, Listen (- * 1.), ### Zwischenüberschriften, ```Code-Blöcke```, `Code`,
 * **fett**, *kursiv*, [Text](https://…) und nackte https-Adressen. Alles andere wird als Text ausgegeben
 * (HTML ist nie erlaubt). Links laufen durch Sanitizer::safeHref.
 */
final class Markdown
{
    public static function render(?string $src): string
    {
        $src = str_replace(["\r\n", "\r"], "\n", (string) $src);
        $lines = explode("\n", $src);
        $out = '';
        $para = [];
        $list = null;      // 'ul' | 'ol'
        $items = [];
        $code = null;      // gesammelte Code-Zeilen

        $flushPara = function () use (&$para, &$out) {
            if ($para) $out .= '<p>' . implode('<br>', array_map([self::class, 'inline'], $para)) . '</p>';
            $para = [];
        };
        $flushList = function () use (&$list, &$items, &$out) {
            if ($list) $out .= "<$list>" . implode('', array_map(fn($i) => '<li>' . self::inline($i) . '</li>', $items)) . "</$list>";
            $list = null;
            $items = [];
        };

        foreach ($lines as $line) {
            if ($code !== null) {
                if (preg_match('~^\s*```~', $line)) {
                    $out .= '<pre class="sp-code"><code>' . e(implode("\n", $code)) . '</code></pre>';
                    $code = null;
                } else {
                    $code[] = $line;
                }
                continue;
            }
            if (preg_match('~^\s*```~', $line)) {
                $flushPara(); $flushList();
                $code = [];
                continue;
            }
            if (trim($line) === '') {
                $flushPara(); $flushList();
                continue;
            }
            if (preg_match('~^\s{0,3}(#{1,4})\s+(.+)$~', $line, $m)) {
                $flushPara(); $flushList();
                $tag = strlen($m[1]) <= 3 ? 'h3' : 'h4';
                $out .= "<$tag>" . self::inline(trim($m[2], " #")) . "</$tag>";
                continue;
            }
            if (preg_match('~^\s*[-*•]\s+(.*)$~u', $line, $m)) {
                $flushPara();
                if ($list !== 'ul') { $flushList(); $list = 'ul'; }
                $items[] = $m[1];
                continue;
            }
            if (preg_match('~^\s*\d{1,3}[.)]\s+(.*)$~', $line, $m)) {
                $flushPara();
                if ($list !== 'ol') { $flushList(); $list = 'ol'; }
                $items[] = $m[1];
                continue;
            }
            // Fortsetzungszeile eines Listenpunkts (eingerückt)
            if ($list && preg_match('~^\s{2,}\S~', $line)) {
                $items[count($items) - 1] .= ' ' . trim($line);
                continue;
            }
            $flushList();
            $para[] = $line;
        }
        if ($code !== null) $out .= '<pre class="sp-code"><code>' . e(implode("\n", $code)) . '</code></pre>';
        $flushPara(); $flushList();
        return $out;
    }

    /** Inline-Formatierung auf bereits maskiertem Text */
    public static function inline(string $text): string
    {
        $keep = [];
        $hold = function (string $html) use (&$keep): string {
            $keep[] = $html;
            return "\x01" . (count($keep) - 1) . "\x01";
        };
        // `Code` zuerst (darin keine weitere Formatierung)
        $text = preg_replace_callback('~`([^`\n]+)`~', fn($m) => $hold('<code>' . e($m[1]) . '</code>'), $text);
        // [Text](Adresse)
        $text = preg_replace_callback('~\[([^\]\n]{1,200})\]\(([^)\s]{1,500})\)~', function ($m) use ($hold) {
            $href = Sanitizer::safeHref($m[2]);
            return $href === null ? $m[0] : $hold('<a href="' . e($href) . '"' . (str_starts_with($href, 'https://') ? ' rel="noopener noreferrer" target="_blank"' : '') . '>' . e($m[1]) . '</a>');
        }, $text);
        // nackte https-Adressen
        $text = preg_replace_callback('~\bhttps?://[^\s<>"\x01]+[^\s<>"\x01.,;:!?)]~i', function ($m) use ($hold) {
            $href = Sanitizer::safeHref($m[0]);
            return $href === null ? $m[0] : $hold('<a href="' . e($href) . '" rel="noopener noreferrer" target="_blank">' . e($m[0]) . '</a>');
        }, $text);
        $html = e($text);
        $html = preg_replace('~\*\*(?=\S)(.+?)(?<=\S)\*\*~u', '<b>$1</b>', $html);
        $html = preg_replace('~(?<![\w*])\*(?=\S)([^*\n]+?)(?<=\S)\*(?![\w*])~u', '<i>$1</i>', $html);
        return preg_replace_callback("~\x01(\d+)\x01~", fn($m) => $keep[(int) $m[1]] ?? '', $html);
    }

    /** Klartext (für Auszüge, Suche, E-Mails) */
    public static function plain(?string $src, int $max = 0): string
    {
        $t = (string) $src;
        $t = preg_replace('~```.*?```~s', ' ', $t);
        $t = preg_replace('~\[([^\]]+)\]\([^)]+\)~', '$1', $t);
        $t = preg_replace('~[`*#>]+~', '', $t);
        $t = trim(preg_replace('~\s+~u', ' ', $t));
        if ($max > 0 && mb_strlen($t) > $max) $t = rtrim(mb_substr($t, 0, $max - 1)) . '…';
        return $t;
    }
}
