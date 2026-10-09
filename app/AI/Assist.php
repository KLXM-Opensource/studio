<?php
declare(strict_types=1);

namespace Core\AI;

use Core\Lang;
use Core\Pages;
use Core\Sanitizer;
use Core\Search\Documents;
use Core\Search\Text;

/**
 * KI-Assistent für die Redaktion (Texte, Übersetzungen, SEO, Alt-Texte, Support, Tabellen-Felder).
 *
 * Alles hier liefert VORSCHLÄGE – gespeichert wird erst, wenn ein Mensch sie prüft und übernimmt (Oberfläche:
 * resources/js/_ai.js, Endpunkte: Core\Http\Controllers\Admin\AiController). Anweisungen: Core\AI\Prompts.
 * Voraussetzungen je Aufruf: Recht „ai.use“, Ai::enabled(cap) und das Tageslimit der Website (sys.ai_daily_cap).
 * Protokolliert wird nur über Ai::track() (Zähler, keine Inhalte).
 */
final class Assist
{
    /** Tageslimit, falls in Grundeinstellungen → KI nichts eingetragen ist (Aufrufe Text + Bild je Website) */
    public const DEFAULT_DAILY_CAP = 200;

    // ================================================================== Marke, Verlauf (nur Metadaten)

    /** Name des KI-Bereichs in der Verwaltung (config 'ai_brand', für Agenturen anpassbar) */
    public static function brand(): string
    {
        return trim((string) app()->config->get('ai_brand', '')) ?: 'KLXM AI';
    }

    /** KLXM-Logo (übernimmt die Textfarbe) für den Kopf des KI-Bereichs */
    public static function logo(string $class = 'kia-logo'): string
    {
        return '<svg class="' . e($class) . '" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path fill="currentColor" fill-rule="evenodd" d="M10.002,18.997C5.031,18.998 1.001,14.971 1,10.002C0.999,5.033 5.028,1.003 9.998,1.003C14.969,1.001 18.999,5.029 19,9.998C19.001,14.967 14.972,18.996 10.002,18.997ZM10.005,7.175L7.183,4.353L4.361,7.175L7.183,9.997L4.361,12.819L7.183,15.641L10.005,12.819L12.827,15.641L15.649,12.819L12.827,9.997L15.649,7.175L12.827,4.353L10.005,7.175Z"/></svg>';
    }

    private static function eventsDb(): \Core\Database
    {
        static $db = [];
        $k = site()->key;
        if (isset($db[$k])) return $db[$k];
        $dir = site()->storage('ai');
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $d = new \Core\Database(['driver' => 'sqlite', 'path' => $dir . '/events.sqlite']);
        $d->pdo->exec('CREATE TABLE IF NOT EXISTS events (id INTEGER PRIMARY KEY AUTOINCREMENT, ts TEXT NOT NULL, user_id INT NOT NULL, kind TEXT NOT NULL,
            action TEXT NOT NULL DEFAULT \'\', target TEXT NOT NULL DEFAULT \'\', status TEXT NOT NULL, model TEXT NOT NULL DEFAULT \'\', ms INT NOT NULL DEFAULT 0)');
        $d->pdo->exec('CREATE INDEX IF NOT EXISTS events_user ON events (user_id, id)');
        return $db[$k] = $d;
    }

    /**
     * Verlauf des KI-Assistenten protokollieren – nur Metadaten (Zeit, Person, Bereich, Ziel, Status, Modell, Dauer), nie Inhalte.
     * $status: suggested | applied | failed. Einträge älter als 90 Tage werden gelegentlich gelöscht.
     */
    public static function log(string $kind, string $action, string $target, string $status = 'suggested', string $model = '', int $ms = 0): void
    {
        try {
            $u = app()->auth->user();
            if (!$u) return;
            $db = self::eventsDb();
            $db->query('INSERT INTO events (ts, user_id, kind, action, target, status, model, ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [date('Y-m-d H:i:s'), (int) $u['id'], mb_substr($kind, 0, 30), mb_substr($action, 0, 30), mb_substr($target, 0, 120), $status, mb_substr($model, 0, 80), $ms]);
            if (random_int(1, 100) === 1) $db->query('DELETE FROM events WHERE ts < ?', [date('Y-m-d H:i:s', strtotime('-90 days'))]);
        } catch (\Throwable $e) {
            error_log('[ai] events: ' . $e->getMessage());
        }
    }

    /** Verlauf einer Person (neueste zuerst) */
    public static function history(int $userId, int $limit = 200): array
    {
        try {
            return self::eventsDb()->fetchAll('SELECT * FROM events WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit)), [$userId]);
        } catch (\Throwable) {
            return [];
        }
    }

    // ================================================================== Verfügbarkeit, Limit, Oberfläche

    /** Darf die angemeldete Person den Assistenten für diese Fähigkeit nutzen? */
    public static function available(string $cap = 'text'): bool
    {
        try {
            return app()->auth->check() && can('ai.use') && Ai::enabled($cap);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Menüpunkt „KLXM AI“: Funktion „ai“, Recht „ai.use“ und KI eingeschaltet – die Administration sieht ihn auch ohne Einrichtung (Hinweis) */
    public static function navVisible(): bool
    {
        try {
            if (!\Core\Features::on('ai', false) || !can('ai.use')) return false;
            foreach (['text', 'vision'] as $c) if (Ai::enabled($c)) return true;
            return can('system.manage');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Tageslimit: cap (0 = unbegrenzt), used (heute), left (null = unbegrenzt) */
    public static function quota(): array
    {
        $cap = max(0, (int) app()->settings->get('sys.ai_daily_cap', self::DEFAULT_DAILY_CAP));
        $used = Ai::callsToday();
        return ['cap' => $cap, 'used' => $used, 'left' => $cap > 0 ? max(0, $cap - $used) : null];
    }

    /** Hinweise der Website und Glossar für alle Anweisungen */
    public static function ctx(): array
    {
        $notes = trim(mb_substr((string) app()->settings->get('sys.ai_notes', ''), 0, 1500));
        $gl = [];
        foreach (self::glossary() as $term => $to) $gl[] = $to === '' ? '- ' . $term . ' (nicht übersetzen)' : '- ' . $term . ' → ' . $to;
        return ['notes' => $notes, 'glossary' => implode("\n", $gl)];
    }

    /** Glossar aus Grundeinstellungen → KI: je Zeile „Begriff“ (nie übersetzen) oder „Begriff = feste Übersetzung“ */
    public static function glossary(): array
    {
        $out = [];
        foreach (preg_split('~\R~u', (string) app()->settings->get('sys.ai_glossary', '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            [$a, $b] = array_pad(array_map('trim', explode('=', $line, 2)), 2, '');
            if ($a !== '') $out[mb_substr($a, 0, 80)] = mb_substr($b, 0, 80);
            if (count($out) >= 100) break;
        }
        return $out;
    }

    /** Konfiguration für resources/js/_ai.js (null = kein Assistent für diese Person/Website) */
    public static function client(): ?array
    {
        $text = self::available('text');
        $vision = self::available('vision');
        if (!$text && !$vision) return null;
        $q = self::quota();
        return [
            'text' => $text, 'vision' => $vision,
            'base' => url('/admin/api/ai'),
            'lang' => Lang::default(), 'langs' => Lang::all(),
            'quota' => ['cap' => $q['cap'], 'left' => $q['left']],
            'external' => Ai::capability('text')['external'] || Ai::capability('vision')['external'],
            'brand' => self::brand(), 'area' => url('/admin/ai'),
        ];
    }

    /** <script type="application/json" id="cms-ai"> für Verwaltung und Werkzeugleiste der Website (leer ohne Assistent) */
    public static function clientScript(): string
    {
        $c = self::client();
        return $c ? '<script type="application/json" id="cms-ai">' . json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' : '';
    }

    /** Aufruf mit Prüfung von Recht, Schalter und Tageslimit. $pack aus Prompts::…; $fake: Antwort des Test-Anbieters */
    public static function call(string $cap, array $pack, string $fake = '', int|string|null $image = null): array
    {
        if (!self::available($cap)) {
            throw new AiException(__('Der KI-Assistent ist für Sie auf dieser Website nicht verfügbar.'));
        }
        $q = self::quota();
        if ($q['left'] === 0) {
            throw new AiException(__('Das Tageslimit für KI-Aufrufe dieser Website ist erreicht ({n}). Morgen geht es weiter – oder die Administration erhöht das Limit unter Grundeinstellungen → KI.', ['n' => $q['cap']]));
        }
        $o = ['system' => $pack['system'], 'max_tokens' => $pack['max_tokens'] ?? 800, 'temperature' => $pack['temperature'] ?? 0.3,
            'json' => !empty($pack['json']), 'fake' => $fake];
        try {
            $r = $cap === 'vision' ? Ai::vision($image ?? throw new AiException(__('Bild nicht gefunden.')), $pack['user'], $o) : Ai::chat($pack['user'], $o);
        } catch (AiException $e) {
            throw new AiException(self::friendly($e->getMessage()), 0, $e);
        }
        if (trim($r['text']) === '') throw new AiException(__('Die KI hat keine Antwort geliefert. Bitte erneut versuchen.'));
        return $r;
    }

    /** Technische Fehlermeldungen des Anbieters in verständliche Hinweise übersetzen (Details stehen im Fehlerprotokoll) */
    public static function friendly(string $m): string
    {
        if (preg_match('~timed? ?out|Zeitüberschreitung|max_duration|Idle timeout~i', $m)) return __('Die KI hat zu lange gebraucht. Bitte mit einem kürzeren Text oder später erneut versuchen.');
        if (preg_match('~connect|resolve host|refused|Could not|unreachable|nicht erreichbar~i', $m)) return __('Der KI-Anbieter ist gerade nicht erreichbar. Bitte in einer Minute erneut versuchen.');
        if (preg_match('~\b(401|403)\b|unauthori[sz]ed|api key~i', $m)) return __('Der KI-Anbieter lehnt die Anfrage ab (Zugangsdaten). Bitte die Administration informieren.');
        if (preg_match('~\b429\b|rate limit|quota~i', $m)) return __('Der KI-Anbieter ist gerade ausgelastet. Bitte etwas später erneut versuchen.');
        if (preg_match('~model.*(not found|nicht gefunden)|not found.*model~i', $m)) return __('Das eingestellte KI-Modell ist nicht verfügbar. Bitte die Administration informieren.');
        return str_contains($m, 'KI') ? $m : __('Die KI-Anfrage ist fehlgeschlagen. Bitte später erneut versuchen.');
    }

    // ================================================================== Text-Assistent

    /**
     * $action: improve|shorten|expand|simple|fix|tone|free, $input: HTML (rich/inline) bzw. Text (plain)
     * $o: tone, instruction, context (Zusammenhang), language
     * @return array{text: string, warnings: list<string>, model: string, ms: int}
     */
    public static function text(string $action, string $input, string $format, array $o = []): array
    {
        $action = isset(Prompts::TEXT_ACTIONS[$action]) ? $action : 'improve';
        $format = in_array($format, ['rich', 'inline', 'plain'], true) ? $format : 'plain';
        if ($action === 'free' && trim((string) ($o['instruction'] ?? '')) === '') throw new AiException(__('Bitte beschreiben Sie, was die KI schreiben soll.'));
        if ($action !== 'free' && trim(strip_tags($input)) === '') throw new AiException(__('Das Feld ist leer – für einen neuen Text „Freier Auftrag“ wählen.'));
        $input = mb_substr($input, 0, 12000);
        $pack = Prompts::text($action, $format === 'plain' ? $input : self::compactHtml($input), self::ctx() + [
            'language' => (string) ($o['language'] ?? Lang::default()), 'format' => $format,
            'tone' => (string) ($o['tone'] ?? ''), 'instruction' => mb_substr((string) ($o['instruction'] ?? ''), 0, 1000),
            'context' => mb_substr((string) ($o['context'] ?? ''), 0, 2000),
        ]);
        $fake = $action === 'free' ? self::fakeWrap('[Test-KI] ' . __('Entwurf zu: {x}', ['x' => mb_substr((string) $o['instruction'], 0, 80)]) . ' [bitte ergänzen: Details]', $format)
            : ($format === 'plain' ? '[Test-KI] ' . $input : self::fakeWrap('[Test-KI] ', $format) . $input);
        $r = self::call('text', $pack, $fake);
        $out = self::clean($r['text'], $format);
        if (trim(strip_tags($out)) === '') throw new AiException(__('Die KI hat keinen verwertbaren Text geliefert. Bitte erneut versuchen.'));
        return ['text' => $out, 'warnings' => self::warnings($input, $out, $action), 'model' => $r['model'], 'ms' => $r['ms']];
    }

    private static function fakeWrap(string $t, string $format): string
    {
        return $format === 'rich' ? '<p>' . e($t) . '</p>' : e($t);
    }

    /** HTML für den Prompt verkleinern: nur die erlaubten Attribute (Sanitizer), rel ergänzt der Sanitizer wieder */
    private static function compactHtml(string $html): string
    {
        return str_replace(' rel="noopener"', '', Sanitizer::block($html));
    }

    /** Antwort des Modells säubern und auf die erlaubten Tags des Feldes bringen */
    public static function clean(string $out, string $format): string
    {
        $out = trim($out);
        $out = (string) preg_replace('~^```[a-z]*\s*|\s*```$~i', '', $out);
        // „Hier ist der überarbeitete Text:“ u. Ä. am Anfang entfernen
        $out = (string) preg_replace('~^(hier ist|hier sind|here is|here are|gerne|klar)[^\n]{0,80}:\s*\n~iu', '', $out);
        $out = trim($out);
        if (preg_match('~^([„"»«])(.*)([“"«»])$~us', $out, $m) && !str_contains($m[2], $m[1])) $out = trim($m[2]);
        if ($format === 'plain') {
            $out = html_entity_decode(strip_tags((string) preg_replace('~<br\s*/?>|</p>\s*<p>~i', "\n", $out)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $out = (string) preg_replace('~\*\*(.+?)\*\*|__(.+?)__~u', '$1$2', $out);
            return trim((string) preg_replace("~\n{3,}~", "\n\n", $out));
        }
        if (!preg_match('~<(p|ul|ol|li|h[1-6]|blockquote|b|strong|i|em|a|br|mark|span|sup|sub)\b~i', $out)) $out = self::markdownToHtml($out, $format);
        $out = (string) preg_replace(['~<h1\b[^>]*>(.*?)</h1>~is', '~<h[56]\b[^>]*>(.*?)</h[56]>~is'], ['<h2>$1</h2>', '<h4>$1</h4>'], $out);   // H1 = Seitentitel
        if ($format === 'inline') {
            $out = (string) preg_replace('~</(p|li|h[1-6])>\s*<(p|li|h[1-6])[^>]*>~i', '<br>', $out);
            return trim(Sanitizer::inline($out));
        }
        $out = trim((string) preg_replace('~<p>\s*(<br\s*/?>)?\s*</p>~i', '', Sanitizer::block($out)));
        if ($out !== '' && !preg_match('~^<(p|ul|ol|h2|h3|h4|blockquote)\b~i', $out)) $out = '<p>' . $out . '</p>';
        return $out;
    }

    /** Einfaches Markdown (Absätze, Listen, fett/kursiv, ###) → HTML */
    private static function markdownToHtml(string $s, string $format): string
    {
        $esc = fn(string $x) => (string) preg_replace(['~\*\*(.+?)\*\*~u', '~(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])~u'], ['<b>$1</b>', '<i>$1</i>'], e($x));
        if ($format === 'inline') return implode('<br>', array_map($esc, preg_split('~\R~u', trim($s)) ?: []));
        $html = '';
        foreach (preg_split('~\R{2,}~u', trim($s)) ?: [] as $para) {
            $lines = preg_split('~\R~u', trim($para)) ?: [];
            if ($lines && count(array_filter($lines, fn($l) => preg_match('~^\s*([-*•]|\d+[.)])\s+~u', $l))) === count($lines)) {
                $ol = (bool) preg_match('~^\s*\d~', $lines[0]);
                $html .= ($ol ? '<ol>' : '<ul>') . implode('', array_map(fn($l) => '<li>' . $esc((string) preg_replace('~^\s*([-*•]|\d+[.)])\s+~u', '', $l)) . '</li>', $lines)) . ($ol ? '</ol>' : '</ul>');
            } elseif (preg_match('~^(#{1,6})\s+(.+)$~u', $lines[0] ?? '', $m) && count($lines) === 1) {
                $h = min(4, max(2, strlen($m[1])));   // # / ## → h2, ### → h3, #### … → h4
                $html .= '<h' . $h . '>' . $esc($m[2]) . '</h' . $h . '>';
            } else {
                $html .= '<p>' . implode('<br>', array_map($esc, $lines)) . '</p>';
            }
        }
        return $html;
    }

    /**
     * Hinweise zum Vorschlag: Platzhalter, fehlende oder neue Zahlen/E-Mail-Adressen (Namen/Zahlen sollen unverändert bleiben).
     * @return list<string>
     */
    public static function warnings(string $source, string $result, string $action = 'improve'): array
    {
        $w = [];
        $n = self::placeholders($result);
        if ($n) $w[] = $n === 1 ? __('Der Vorschlag enthält einen Platzhalter [bitte ergänzen: …] – bitte ausfüllen oder entfernen.')
            : __('Der Vorschlag enthält {n} Platzhalter [bitte ergänzen: …] – bitte ausfüllen oder entfernen.', ['n' => $n]);
        $a = self::facts(Text::plain($source));
        $b = self::facts(Text::plain($result));
        if ($action !== 'free' && ($missing = array_diff($a, $b)) && $action !== 'shorten') {
            $w[] = __('Diese Angaben aus dem Original fehlen im Vorschlag: {list}', ['list' => implode(', ', array_slice($missing, 0, 8))]);
        }
        if ($new = array_diff($b, $a)) {
            $w[] = __('Neue Zahlen oder Kontaktangaben im Vorschlag – bitte prüfen, ob sie stimmen: {list}', ['list' => implode(', ', array_slice($new, 0, 8))]);
        }
        return $w;
    }

    public static function placeholders(string $s): int
    {
        return (int) preg_match_all('~\[\s*(bitte ergänzen|please add|please complete|bitte ergaenzen)[^\]]*\]~iu', $s);
    }

    /** Zahlen (mit Einheiten-Trennern), E-Mail-Adressen und Web-Adressen eines Textes */
    private static function facts(string $s): array
    {
        $s = (string) preg_replace('~(^|[\n:.!?]\s*)\d{1,2}[.)]\s+(?=\p{Lu})~mu', '$1', $s);   // Nummern von Listen sind keine Angaben
        preg_match_all('~[\w.+-]+@[\w-]+(?:\.[\w-]+)+|https?://\S+|\d+(?:[.,:/]\d+)*~u', $s, $m);
        return array_values(array_unique(array_map(fn($x) => rtrim($x, '.,;:)'), $m[0])));
    }

    // ================================================================== Übersetzen

    /**
     * $items: key => ['text' => string, 'html' => bool]; Ergebnis: key => ['text' => …, 'warnings' => […]]
     * Stapel als JSON; fehlt ein Eintrag in der Antwort, wird er einzeln übersetzt.
     */
    public static function translate(array $items, string $from, string $to): array
    {
        if ($from === $to) throw new AiException(__('Quell- und Zielsprache sind gleich.'));
        $ctx = self::ctx();
        $out = [];
        $batch = [];
        $size = 0;
        $flush = function () use (&$batch, &$size, &$out, $from, $to, $ctx) {
            if (!$batch) return;
            $keys = array_keys($batch);
            $in = [];
            foreach ($keys as $i => $k) $in[(string) ($i + 1)] = $batch[$k]['text'];
            $fake = json_encode(array_map(fn($t) => '[' . strtoupper($to) . '] ' . $t, $in), JSON_UNESCAPED_UNICODE);
            $got = [];
            try {
                $got = self::json(self::call('text', Prompts::translateBatch($in, $from, $to, $ctx), (string) $fake)['text']);
            } catch (AiException $e) {
                if (count($batch) === 1) throw $e;
            }
            foreach ($keys as $i => $k) {
                $t = $got[(string) ($i + 1)] ?? null;
                if (!is_string($t) || trim($t) === '') {
                    $one = Prompts::translateOne($batch[$k]['text'], $from, $to, $batch[$k]['html'], $ctx);
                    $t = self::call('text', $one, '[' . strtoupper($to) . '] ' . $batch[$k]['text'])['text'];
                }
                $out[$k] = self::finishTranslation($batch[$k]['text'], (string) $t, $batch[$k]['html']);
            }
            $batch = [];
            $size = 0;
        };
        foreach ($items as $k => $it) {
            $text = (string) ($it['text'] ?? '');
            if (trim(strip_tags($text)) === '') { $out[$k] = ['text' => $text, 'warnings' => []]; continue; }
            $len = mb_strlen($text);
            if ($batch && ($size + $len > 2500 || count($batch) >= 12)) $flush();
            $batch[$k] = ['text' => mb_substr($text, 0, 12000), 'html' => !empty($it['html'])];
            $size += $len;
        }
        $flush();
        return $out;
    }

    private static function finishTranslation(string $src, string $t, bool $html): array
    {
        $t = trim((string) preg_replace('~^```[a-z]*\s*|\s*```$~i', '', trim($t)));
        if ($html) {
            $rich = (bool) preg_match('~<(p|ul|ol|li|h[2-4]|blockquote)\b~i', $src);
            $t = $rich ? self::clean($t, 'rich') : self::clean($t, 'inline');
        } else {
            $t = trim(html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $w = [];
        $miss = array_diff(self::facts(Text::plain($src)), self::facts(Text::plain($t)));
        if ($miss) $w[] = __('Bitte prüfen – im Original, aber nicht in der Übersetzung: {list}', ['list' => implode(', ', array_slice($miss, 0, 6))]);
        foreach (self::glossary() as $term => $fixed) {
            if (mb_stripos($src, $term) !== false && mb_stripos($t, $fixed !== '' ? $fixed : $term) === false) {
                $w[] = __('Glossar: „{term}“ sollte als „{to}“ erscheinen.', ['term' => $term, 'to' => $fixed !== '' ? $fixed : $term]);
            }
        }
        return ['text' => $t, 'warnings' => $w];
    }

    /** JSON-Objekt aus einer Modell-Antwort lesen (auch mit Codeblock oder Text drumherum) */
    public static function json(string $s): array
    {
        $s = trim((string) preg_replace('~^```[a-z]*\s*|\s*```$~i', '', trim($s)));
        $d = json_decode($s, true);
        if (!is_array($d) && ($a = strpos($s, '{')) !== false && ($b = strrpos($s, '}')) !== false && $b > $a) {
            $d = json_decode(substr($s, $a, $b - $a + 1), true);
        }
        if (!is_array($d)) throw new AiException(__('Die Antwort der KI war unvollständig. Bitte erneut versuchen.'));
        return $d;
    }

    // ================================================================== SEO

    /** Länge des Titel-Zusatzes („ | Website“), den Seo::forPage anhängt */
    public static function titleSuffix(): string
    {
        $theme = app()->theme->def;
        $suffix = (string) setting($theme['seo']['title_suffix'] ?? 'site_title_suffix', '');
        if ($suffix === '' && !empty($theme['seo']['title_suffix_fallback'])) $suffix = (string) setting($theme['seo']['title_suffix_fallback'], '');
        return $suffix !== '' ? ' | ' . $suffix : '';
    }

    /**
     * Zeichen für den eigenen Titelteil (Suchmaschinen zeigen etwa SeoCheck::TITLE_MAX Zeichen inklusive Titel-Zusatz).
     * Mindestens TITLE_MIN_OWN: ist der Zusatz selbst zu lang, kürzt Google ihn ohnehin – der Zähler bleibt dann brauchbar.
     */
    public static function titleBudget(): int
    {
        return max(SeoCheck::TITLE_MIN_OWN, SeoCheck::TITLE_MAX - mb_strlen(self::titleSuffix()));
    }

    /** Titel-Zusatz lässt weniger als TITLE_MIN_OWN Zeichen für den Seitentitel übrig */
    public static function titleSuffixTooLong(): bool
    {
        return SeoCheck::TITLE_MAX - mb_strlen(self::titleSuffix()) < SeoCheck::TITLE_MIN_OWN;
    }

    /**
     * SEO-Vorschläge für einen Text. @return array{title: string, description: string, slug: string, focus: string, keywords: list<string>, warnings: list<string>}
     */
    public static function seo(string $title, string $text, string $lang, int $titleMax = 60, int $descMax = 155): array
    {
        $titleMax = max(20, $titleMax);
        $words = count(preg_split('~\s+~u', trim(Text::plain($text))) ?: []);
        $thin = $words < 40;
        $pack = Prompts::seo($title, mb_substr($text, 0, 6000), self::ctx() + ['language' => $lang, 'title_max' => $titleMax, 'desc_max' => $descMax,
            'site' => (string) site_name(), 'thin' => $thin]);
        $fake = json_encode(['title' => mb_substr('Test: ' . $title, 0, $titleMax), 'description' => mb_substr('Testbeschreibung für „' . $title . '“. ' . mb_substr(Text::plain($text), 0, 200), 0, $descMax),
            'slug' => Pages::slugify($title), 'focus' => $title, 'keywords' => ['test', 'beispiel']], JSON_UNESCAPED_UNICODE);
        $d = self::json(self::call('text', $pack, (string) $fake)['text']);
        $w = [];
        $t = trim(strip_tags((string) ($d['title'] ?? '')));
        if (mb_strlen($t) > $titleMax) { $t = self::cut($t, $titleMax); $w[] = __('Titel wurde auf {n} Zeichen gekürzt.', ['n' => $titleMax]); }
        $desc = trim(preg_replace('~\s+~u', ' ', strip_tags((string) ($d['description'] ?? ''))));
        if (mb_strlen($desc) > $descMax) { $desc = self::cut($desc, $descMax); $w[] = __('Beschreibung wurde auf {n} Zeichen gekürzt.', ['n' => $descMax]); }
        $kw = array_values(array_filter(array_map(fn($k) => trim(strip_tags((string) $k)), is_array($d['keywords'] ?? null) ? $d['keywords'] : explode(',', (string) ($d['keywords'] ?? ''))), fn($k) => $k !== ''));
        $out = ['title' => $t, 'description' => $desc, 'slug' => Pages::slugify((string) ($d['slug'] ?? $t)), 'focus' => trim(strip_tags((string) ($d['focus'] ?? ''))),
            'keywords' => array_slice($kw, 0, 8)];
        if ($thin) array_unshift($w, __('Die Seite hat kaum eigenen Text – der Vorschlag stützt sich fast nur auf den Titel. Bitte besonders sorgfältig prüfen.'));
        $w = array_merge($w, self::warnings($title . ' ' . $text, $t . ' ' . $desc, 'shorten'));
        $out['warnings'] = array_values(array_unique($w));
        return $out;
    }

    /** An Wortgrenze kürzen (ohne „…“ – Suchmaschinen kürzen selbst) */
    public static function cut(string $s, int $max): string
    {
        if (mb_strlen($s) <= $max) return $s;
        $s = mb_substr($s, 0, $max + 1);
        $p = mb_strrpos($s, ' ');
        return rtrim($p !== false && $p > 0 ? mb_substr($s, 0, $p) : mb_substr($s, 0, $max), " ,;:-–|");
    }

    // ================================================================== Seiten & Einträge: Zusammenhang

    /** Titel + sichtbarer Text einer Seite (Entwurf) */
    public static function pageText(array $page, int $max = 6000): string
    {
        $prev = app()->lang;
        app()->lang = ($page['lang'] ?? null) ?: null;
        try {
            [$head, $text] = Documents::blocksText(Pages::blocks($page, true), $page);
        } catch (\Throwable) {
            [$head, $text] = [[], []];
        } finally {
            app()->lang = $prev;
        }
        return mb_substr(trim(implode("\n", array_unique($head)) . "\n" . implode("\n", $text)), 0, $max);
    }

    /** Kurzer Zusammenhang für „Freier Auftrag“ (Seite oder Eintrag) */
    public static function context(?int $pageId, ?array $entry = null): string
    {
        if ($entry && ($t = \Core\Data\Tables::find((string) ($entry['table'] ?? ''))) && can('data.edit', $t['handle'])
            && ($e = \Core\Data\Entries::find($t, (int) ($entry['id'] ?? 0)))) {
            return mb_substr($t['singular'] . ': ' . \Core\Data\Entries::title($t, $e) . "\n" . self::entryText($t, $e, 1200), 0, 1500);
        }
        if ($pageId && can('pages.edit') && ($p = Pages::find($pageId))) {
            return mb_substr(__('Seite') . ': ' . $p['title'] . "\n" . self::pageText($p, 1200), 0, 1500);
        }
        return '';
    }

    /** Textfelder eines Eintrags als Klartext */
    public static function entryText(array $t, array $e, int $max = 6000): string
    {
        $parts = [];
        foreach ($t['fields'] as $f) {
            if (!in_array($f['type'], ['text', 'textarea', 'richtext', 'inline'], true)) continue;
            $v = $e[$f['name']] ?? '';
            if (is_string($v) && trim($v) !== '') $parts[] = $f['label'] . ': ' . Text::plain($v);
        }
        return mb_substr(implode("\n", $parts), 0, $max);
    }

    /** Feld für Teaser/Zusammenfassung einer Tabelle (Beschreibungsfeld, Zusammenfassung der Suche oder bekannter Name) */
    public static function teaserField(array $t): ?array
    {
        $cands = array_filter([(string) ($t['settings']['search']['summary'] ?? ''), (string) ($t['settings']['description_field'] ?? '')]);
        foreach ($t['fields'] as $f) {
            if (preg_match('~^(teaser|kurztext|zusammenfassung|summary|intro|anriss|kurzbeschreibung|excerpt)$~i', $f['name'])) $cands[] = $f['name'];
        }
        foreach ($cands as $name) {
            foreach ($t['fields'] as $f) {
                if ($f['name'] === $name && in_array($f['type'], ['textarea', 'text'], true) && $name !== ($t['settings']['title_field'] ?? '')) return $f;
            }
        }
        return null;
    }

    /** Teaser/Zusammenfassung (reiner Text, höchstens $max Zeichen) */
    public static function summary(string $title, string $text, int $max = 200, ?string $lang = null): array
    {
        if (trim($text) === '') throw new AiException(__('Es gibt noch keinen Text, aus dem eine Zusammenfassung entstehen kann.'));
        $pack = Prompts::summary($title, mb_substr($text, 0, 8000), self::ctx() + ['language' => $lang ?? Lang::default(), 'max' => $max]);
        $r = self::call('text', $pack, '[Test-KI] ' . mb_substr(Text::plain($text), 0, $max - 12));
        $s = self::clean($r['text'], 'plain');
        $w = [];
        if (mb_strlen($s) > $max) { $s = self::cut($s, $max); $w[] = __('Auf {n} Zeichen gekürzt.', ['n' => $max]); }
        return ['text' => $s, 'warnings' => array_merge($w, self::warnings($text, $s, 'shorten'))];
    }

    // ================================================================== Medien

    /**
     * Alt-Text-Vorschlag für ein Bild (Medien-ID oder Dateipfad) in den gewünschten Sprachen.
     * Die erste Sprache beschreibt das Bildmodell, weitere Sprachen übersetzt das Textmodell (falls eingeschaltet).
     * @return array{alt: array<string,string>, title: string, tags: list<string>, decorative: bool, warnings: list<string>}
     */
    public static function alt(int|string $image, array $langs, string $context = ''): array
    {
        $langs = array_values(array_filter($langs, [Lang::class, 'valid'])) ?: [Lang::default()];
        $first = $langs[0];
        $pack = Prompts::alt(['language' => $first, 'context' => mb_substr($context, 0, 200), 'notes' => self::ctx()['notes']]);
        $fake = json_encode(['alt' => 'Testbild: Beschreibung des Bildinhalts', 'title' => 'Testbild', 'tags' => ['test'], 'decorative' => false], JSON_UNESCAPED_UNICODE);
        $r = self::call('vision', $pack, (string) $fake, $image);
        try {
            $d = self::json($r['text']);
        } catch (AiException) {
            $d = ['alt' => self::clean($r['text'], 'plain')];   // Modell hat reinen Text geliefert
        }
        $alt = trim((string) preg_replace('~^(bild|foto|abbildung|image|photo|picture)\s+(von|of|:)\s*~iu', '', trim(strip_tags((string) ($d['alt'] ?? '')))));
        $alt = mb_substr(self::cut($alt, 250), 0, 250);
        $warn = [];
        if ($alt === '') $warn[] = __('Die KI konnte das Bild nicht beschreiben.');
        $out = ['alt' => [$first => $alt], 'title' => mb_substr(trim(strip_tags((string) ($d['title'] ?? ''))), 0, 120),
            'tags' => array_slice(array_values(array_filter(array_map(fn($t) => mb_strtolower(trim(strip_tags((string) $t))), (array) ($d['tags'] ?? [])))), 0, 6),
            'decorative' => filter_var($d['decorative'] ?? false, FILTER_VALIDATE_BOOLEAN), 'warnings' => $warn];
        $more = array_slice($langs, 1);
        if ($alt !== '' && $more && self::available('text')) {
            foreach ($more as $l) {
                try {
                    $out['alt'][$l] = mb_substr(self::translate(['alt' => ['text' => $alt, 'html' => false]], $first, $l)['alt']['text'], 0, 250);
                } catch (AiException $e) {
                    $out['warnings'][] = $e->getMessage();
                }
            }
        }
        return $out;
    }

    // ================================================================== Support & Tabellen

    /** Antwortvorschlag für eine Support-Meldung (nur Support-Team) */
    public static function supportReply(array $issue): array
    {
        $posts = \Core\Support\Tickets::posts($issue);
        $conv = __('Meldung') . ': ' . $issue['title'] . "\n" . \Core\Support\Knowledge::anonymise((string) $issue['body'], $issue);
        foreach ($posts as $p) {
            if (($p['kind'] ?? 'reply') !== 'reply' || trim((string) $p['body']) === '') continue;
            $who = (int) $p['author_staff'] ? __('Support') : __('Kunde');
            $conv .= "\n\n" . $who . ((int) $p['internal'] ? ' (' . __('interne Notiz') . ')' : '') . ":\n" . \Core\Support\Knowledge::anonymise((string) $p['body'], $issue);
        }
        $kb = [];
        foreach (\Core\Support\Knowledge::search((string) $issue['title'], 3, true) as $a) {
            $kb[] = ['title' => (string) $a['title'], 'body' => mb_substr((string) $a['body'], 0, 1200)];
        }
        $pack = Prompts::supportReply(mb_substr($conv, 0, 8000), $kb, self::ctx());
        $r = self::call('text', $pack, "Guten Tag,\n\n[Test-KI] vielen Dank für Ihre Meldung. [bitte ergänzen: Lösung]\n\nViele Grüße");
        $text = self::clean($r['text'], 'plain');
        return ['text' => $text, 'warnings' => self::warnings($conv, $text, 'free'), 'sources' => array_column($kb, 'title')];
    }

    /** Wissensartikel-Entwurf überarbeiten (Titel + Markdown) */
    public static function kbPolish(string $title, string $body): array
    {
        if (trim($body) === '') throw new AiException(__('Der Artikel ist noch leer.'));
        $fake = json_encode(['title' => '[Test-KI] ' . $title, 'body' => $body], JSON_UNESCAPED_UNICODE);
        $d = self::json(self::call('text', Prompts::kbPolish($title, mb_substr($body, 0, 10000), self::ctx()), (string) $fake)['text']);
        $t = mb_substr(trim(strip_tags((string) ($d['title'] ?? $title))), 0, 160);
        $b = trim(strip_tags((string) ($d['body'] ?? '')));
        if ($b === '') throw new AiException(__('Die Antwort der KI war unvollständig. Bitte erneut versuchen.'));
        return ['title' => $t, 'body' => $b, 'warnings' => self::warnings($title . "\n" . $body, $t . "\n" . $b, 'improve')];
    }

    /** Feldvorschlag für eine Datentabelle. $types: erlaubte Typen (Schlüssel aus Tables::TYPES) */
    public static function schema(string $description, array $types): array
    {
        if (mb_strlen(trim($description)) < 5) throw new AiException(__('Bitte beschreiben Sie kurz, was die Tabelle enthalten soll.'));
        $all = \Core\Data\Tables::TYPES + ['textarea' => ['Text (mehrzeilig)'], 'richtext' => ['Formatierter Text'], 'number' => ['Zahl']];
        $allowed = [];
        foreach ($types as $k) if (isset($all[$k])) $allowed[$k] = $all[$k][0];
        unset($allowed['relation'], $allowed['relations'], $allowed['group'], $allowed['section'], $allowed['content']);   // brauchen Zieltabellen/Unterfelder bzw. sind Gestaltung – von Hand
        if (!$allowed) throw new AiException(__('Keine Feldtypen verfügbar.'));
        $fake = json_encode(['fields' => [['label' => 'Titel', 'type' => 'text', 'required' => true, 'help' => ''], ['label' => 'Beschreibung', 'type' => isset($allowed['textarea']) ? 'textarea' : 'text', 'required' => false, 'help' => 'Test-KI']]], JSON_UNESCAPED_UNICODE);
        $d = self::json(self::call('text', Prompts::schema(mb_substr($description, 0, 2000), $allowed, self::ctx()), (string) $fake)['text']);
        $out = [];
        foreach ((array) ($d['fields'] ?? []) as $f) {
            if (!is_array($f)) continue;
            $type = (string) ($f['type'] ?? 'text');
            if (!isset($allowed[$type])) $type = isset($allowed['text']) ? 'text' : (string) array_key_first($allowed);
            $label = mb_substr(trim(strip_tags((string) ($f['label'] ?? ''))), 0, 60);
            if ($label === '') continue;
            $opts = array_values(array_filter(array_map(fn($o) => mb_substr(trim(strip_tags((string) $o)), 0, 60), (array) ($f['options'] ?? []))));
            $out[] = ['label' => $label, 'type' => $type, 'type_label' => $allowed[$type], 'required' => !empty($f['required']),
                'help' => mb_substr(trim(strip_tags((string) ($f['help'] ?? ''))), 0, 200), 'options' => in_array($type, ['select', 'multiselect'], true) ? array_slice($opts, 0, 20) : []];
            if (count($out) >= 20) break;
        }
        if (!$out) throw new AiException(__('Die KI hat keine Felder vorgeschlagen. Bitte die Beschreibung genauer fassen.'));
        return ['fields' => $out];
    }
}
