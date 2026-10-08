<?php
declare(strict_types=1);

namespace Core\AI;

use Core\Features;
use Core\Lang;
use Core\Search\Search;
use Core\Search\Text;

/**
 * KI-Chat für Besucher (Funktion „chat.visitor“, Standard AUS; Einstellungen unter Grundeinstellungen → KI → „Besucher-Chat“).
 *
 * Antwortet NUR aus den Inhalten der eigenen Website: Suchindex (Core\Search – Seiten, FAQ-Blöcke, Einträge mit
 * Detailseite, Transkripte; auf Landing-Domains wie deren Sucheinstellung) → beste Textstellen je Treffer + öffentliche
 * Angaben des Themes (project.public_info: Kontakt, Öffnungszeiten, Hinweis). Die KI belegt Aussagen mit [n]; die
 * Oberfläche verlinkt die Quellen. Findet sich nichts, sagt der Chat das und bietet den Kontakt an – er rät nie.
 *
 * Schutz: Anweisungs-Angriffe („ignoriere …“) werden ohne KI-Aufruf abgelehnt, Zahlen in der Antwort müssen in den
 * Quellen stehen, E-Mail-Adressen und lange Nummern werden vor dem Senden an den Anbieter unkenntlich gemacht.
 * Datenschutz: keine Cookies, keine Speicherung der Fragen/Antworten (nur Zähler in Ai::track, Zählname „chat“),
 * Ratenbegrenzung über einen HMAC der IP. Anweisungen: Core\AI\Prompts::visitorChat(), Zusatz des Themes:
 * theme.php 'ai' => ['chat' => ['system' => '…']]; eigene feste Leisten am unteren Rand: CSS-Variable --cms-chat-lift im Theme.
 * Oberfläche: resources/js/visitor-chat-start.js (Starter ≤ 1 KB) → visitor-chat.mjs + resources/css/visitor-chat.css (Shadow DOM, erst nach Klick geladen).
 */
final class VisitorChat
{
    /** Antworten je Tag und Website, falls nichts eingestellt ist */
    public const DEFAULT_DAILY = 300;
    /** Fragen je IP (HMAC) und Minute bzw. Tag */
    public const PER_MINUTE = 8;
    public const PER_DAY = 80;
    /** Länge einer Frage, Anzahl mitgeschickter früherer Nachrichten, Quellen je Antwort */
    public const MAX_QUESTION = 500;
    public const MAX_HISTORY = 6;
    public const TOP_K = 5;
    /** Zeichen je Quelle im Prompt */
    private const SOURCE_CHARS = 1400;

    // ================================================================== Einstellungen & Verfügbarkeit

    public static function settings(): array
    {
        $s = app()->settings;
        $lines = fn(string $k) => array_values(array_filter(array_map(fn($l) => trim(mb_substr($l, 0, 140)), preg_split('~\R~u', (string) $s->get($k, '')) ?: []), fn($l) => $l !== ''));
        return [
            'enabled' => (bool) $s->get('sys.chat_enabled', false),
            'greeting' => trim((string) $s->get('sys.chat_greeting', '')),
            'suggestions' => array_slice($lines('sys.chat_suggestions'), 0, 4),
            'contact_text' => trim((string) $s->get('sys.chat_contact_text', '')),
            'contact_url' => trim((string) $s->get('sys.chat_contact_url', '')),
            'privacy' => trim((string) $s->get('sys.chat_privacy', '')),
            'privacy_url' => trim((string) $s->get('sys.chat_privacy_url', '')),
            'daily' => max(0, (int) $s->get('sys.chat_daily', self::DEFAULT_DAILY)),
            // Seiten ohne Chat (Feldtyp „pages“): „12“ = diese Seite, „12*“ = mit allen Unterseiten
            'exclude' => array_values(array_map('strval', (array) $s->get('sys.chat_exclude', []))),
            'position' => $s->get('sys.chat_position', 'right') === 'left' ? 'left' : 'right',
        ];
    }

    /** Chat auf dieser Website (bzw. Landing-Domain) aktiv? Funktion + Schalter + KI (Texte) + Suche */
    public static function available(): bool
    {
        try {
            return Features::on('chat.visitor', false) && Features::on('ai', false) && self::settings()['enabled']
                && Ai::enabled('text') && Search::enabled();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * KI-Antwort über den Suchergebnissen (Grundeinstellungen → Suche „sys.search_answer“): off | click (Knopf) |
     * question (automatisch bei Fragen, sonst Knopf) | auto. Standard: off (bewusst einschalten). Braucht KI (Texte) und Suche, nicht den Chat-Knopf.
     */
    public static function searchAnswerMode(): string
    {
        try {
            $m = (string) app()->settings->get('sys.search_answer', 'off');
            if (!in_array($m, ['off', 'click', 'question', 'auto'], true)) $m = 'off';
            return $m !== 'off' && Features::on('ai', false) && Ai::enabled('text') && Search::enabled() ? $m : 'off';
        } catch (\Throwable) {
            return 'off';
        }
    }

    /** Sieht die Suchanfrage wie eine Frage aus? (Fragezeichen, Fragewort am Anfang oder mindestens fünf Wörter) */
    public static function looksLikeQuestion(string $q): bool
    {
        $q = trim(mb_strtolower($q));
        if (str_ends_with($q, '?')) return true;
        $w = '(was|wie|wo|wohin|woher|wann|wer|wen|wem|warum|wieso|weshalb|welche[rsmn]?|kann|darf|muss|gibt|ist|sind|habe|hat|brauche|what|how|where|when|who|why|which|can|do|does|is|are)';
        return (bool) preg_match('~^' . $w . '\b~u', $q) || count(preg_split('~\s+~u', $q) ?: []) >= 5;
    }

    /** Kasten „Antwort“ für die Ergebnisseite (leer, wenn aus); lädt search-answer.js – ohne JavaScript unsichtbar */
    public static function searchAnswerBox(string $q, array $result): string
    {
        $mode = self::searchAnswerMode();
        if ($mode === 'off' || mb_strlen(trim($q)) < 3 || ($result['page'] ?? 1) > 1 || ($result['type'] ?? '') !== '') return '';
        $auto = $mode === 'auto' || ($mode === 'question' && self::looksLikeQuestion($q));
        $l = ['wait' => lt('Antwort wird erstellt …'), 'err' => lt('Das hat leider nicht geklappt. Bitte versuchen Sie es später noch einmal.'),
            'src' => lt('Quellen'), 'go' => lt('Antwort erzeugen')];
        $note = self::external() ? lt('Ihre Frage wird dafür (ohne IP-Adresse) an unseren KI-Dienstleister übermittelt.') : '';
        return '<aside class="srch-ai" data-srch-ai hidden aria-labelledby="srch-ai-h" data-api="' . e(url('/api/chat')) . '" data-q="' . e($q) . '" data-lang="' . e(Lang::current()) . '"'
            . ($auto ? ' data-auto' : '') . ' data-l="' . e(json_encode($l, JSON_UNESCAPED_UNICODE)) . '">'
            . '<p class="srch-ai__h" id="srch-ai-h"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l1.9 5.6L19.5 9.5l-5.6 1.9L12 17l-1.9-5.6L4.5 9.5l5.6-1.9zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z"/></svg>'
            . e(lt('Antwort')) . ' <span class="srch-ai__tag">' . e(lt('KI')) . '</span></p>'
            . '<div class="srch-ai__body" data-body aria-live="polite">' . ($auto ? '' : '<button type="button" class="srch-ai__go" data-go>' . e($l['go']) . '</button>') . '</div>'
            . '<p class="srch-ai__note">' . e(lt('Automatisch aus den Inhalten dieser Website erstellt – bitte in den Quellen prüfen.')) . ($note !== '' ? ' ' . e($note) : '') . '</p>'
            . '</aside>';
    }

    /** Verlassen die Fragen den Server? (externer Text- oder Embedding-Anbieter) */
    public static function external(): bool
    {
        return Ai::capability('chat')['external'] || (Search::semanticActive() && Ai::capability('embed')['external']);
    }

    /**
     * Starter-Knopf für Theme-Layouts (cms_chat_launcher()): leerer Host + Skript ≤ 1 KB. Das Chat-Fenster (JS/CSS)
     * lädt erst beim ersten Klick. $page: aktuelle Seite (Ausnahmen), $editing: Editor offen → nichts.
     */
    public static function launcher(?array $page = null, bool $editing = false): string
    {
        if ($editing || !self::available()) return '';
        $s = self::settings();
        if ($page && \Core\PagePicker::matches($s['exclude'], $page)) return '';
        $attrs = [
            'class' => 'cms-chat', 'data-cms-chat' => '', 'hidden' => '',
            'data-start' => asset('css/visitor-chat-start.css'), 'data-src' => asset('js/visitor-chat.mjs'), 'data-css' => asset('css/visitor-chat.css'),
            'data-api' => url('/api/chat'), 'data-lang' => Lang::current(), 'data-pos' => $s['position'],
            'data-label' => lt('Fragen?'), 'data-title' => lt('Chat öffnen – Fragen zu dieser Website stellen'),
        ];
        $html = '<div';
        foreach ($attrs as $k => $v) $html .= ' ' . $k . ($v === '' ? '' : '="' . e((string) $v) . '"');
        return $html . '></div><script src="' . e(asset('js/visitor-chat-start.js')) . '" defer></script>';
    }

    /** Texte und Angaben für das Chat-Fenster (GET /api/chat/config, Sprache der Seite) */
    public static function publicConfig(): array
    {
        $s = self::settings();
        $info = self::info();
        $ext = self::external();
        // Eigene Texte der Administration gelten für die Standardsprache; weitere Sprachen bekommen die übersetzten Standardtexte
        if (Lang::current() !== Lang::default()) $s = ['greeting' => '', 'suggestions' => [], 'privacy' => '', 'contact_text' => ''] + $s;
        return [
            'title' => lt('Fragen zur Website'),
            'greeting' => $s['greeting'] !== '' ? $s['greeting'] : lt('Hallo! Ich beantworte Fragen zu den Inhalten dieser Website und nenne Ihnen die passenden Seiten.'),
            'suggestions' => $s['suggestions'],
            'privacy' => $s['privacy'] !== '' ? $s['privacy'] : ($ext
                ? lt('Ihre Frage wird an einen KI-Dienst übermittelt, der daraus eine Antwort aus den Inhalten dieser Website erstellt. Wir speichern Fragen und Antworten nicht und setzen keine Cookies. Bitte geben Sie keine persönlichen Daten ein (z. B. Namen, Telefonnummern oder Angaben zur Gesundheit).')
                : lt('Ihre Frage wird auf unserem eigenen Server von einer KI verarbeitet, die nur die Inhalte dieser Website kennt. Wir speichern Fragen und Antworten nicht und setzen keine Cookies. Bitte geben Sie keine persönlichen Daten ein (z. B. Namen, Telefonnummern oder Angaben zur Gesundheit).')),
            'privacy_url' => $s['privacy_url'] !== '' ? link_href($s['privacy_url']) : null,
            'contact' => self::contact($s, $info),
            'external' => $ext,
            'max' => self::MAX_QUESTION,
            'l10n' => [
                'start' => lt('Verstanden – Chat starten'), 'privacy_link' => lt('Datenschutzerklärung'),
                'placeholder' => lt('Ihre Frage …'), 'send' => lt('Senden'), 'close' => lt('Chat schließen'), 'new' => lt('Neuer Chat'),
                'sources' => lt('Quellen'), 'you' => lt('Sie'), 'bot' => lt('Assistent'), 'thinking' => lt('Antwort wird erstellt …'),
                'note' => lt('KI-Antwort aus den Inhalten dieser Website – bitte wichtige Angaben auf der verlinkten Seite prüfen.'),
                'error' => lt('Das hat leider nicht geklappt. Bitte versuchen Sie es später noch einmal.'),
                'stop' => lt('Antwort abbrechen'), 'suggest' => lt('Vorschläge'), 'log' => lt('Chatverlauf'),
                'call' => lt('Anrufen'), 'mail' => lt('E-Mail schreiben'), 'chars' => lt('{n} Zeichen übrig'),
            ],
        ];
    }

    /** Kontakt für „weiß ich nicht“: Text, Link („Anfrage senden“), Telefon, E-Mail aus den öffentlichen Angaben */
    private static function contact(array $s, array $info): array
    {
        $phone = (string) ($info['phone'] ?? '');
        return [
            'text' => $s['contact_text'] !== '' ? $s['contact_text'] : lt('Dazu finde ich auf dieser Website leider keine verlässliche Angabe. Bitte wenden Sie sich direkt an uns – wir helfen gern weiter.'),
            'url' => $s['contact_url'] !== '' ? link_href($s['contact_url']) : null,
            'label' => lt('Anfrage senden'),
            'phone' => $phone !== '' ? ['href' => 'tel:' . preg_replace('~[^\d+]~', '', $phone), 'label' => (string) ($info['phone_display'] ?? $phone)] : null,
            'email' => filter_var((string) ($info['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string) $info['email'] : null,
        ];
    }

    /** Öffentliche Angaben (Core\Api\CmsService::publicInfo – Name, Kontakt, Öffnungszeiten, Hinweis) */
    public static function info(): array
    {
        try {
            return \Core\Api\CmsService::publicInfo();
        } catch (\Throwable) {
            return ['name' => site_name()];
        }
    }

    /** Zusätzliche Regeln des Themes (theme.php 'ai' => ['chat' => ['system' => Text oder Funktionsname]]) */
    public static function themeHint(): string
    {
        $h = app()->theme->def['ai']['chat']['system'] ?? project('ai.chat.system', '');
        if (is_string($h) && $h !== '' && function_exists($h) && !str_contains($h, ' ')) $h = (string) $h();
        return is_string($h) ? mb_substr(trim($h), 0, 2000) : '';
    }

    /** Vorschlag für die Datenschutzerklärung (Grundeinstellungen → KI → Besucher-Chat) */
    public static function privacyPolicyText(): string
    {
        $c = Ai::capability('chat');
        $name = __(Ai::PROVIDERS[$c['provider']] ?? 'KI-Dienstleister');
        return self::external()
            ? __('KI-Chat auf dieser Website: Wenn Sie den Chat öffnen und eine Frage stellen, übermitteln wir den Text Ihrer Frage (ohne IP-Adresse, ohne Cookies) an {name}, um daraus eine Antwort aus den Inhalten dieser Website zu erzeugen (Art. 6 Abs. 1 lit. f DSGVO, berechtigtes Interesse an einer hilfreichen Auskunft). Mit dem Anbieter besteht ein Vertrag zur Auftragsverarbeitung. Fragen und Antworten werden von uns nicht gespeichert; zum Schutz vor Missbrauch zählen wir Anfragen je gekürzter, verschlüsselter IP-Adresse für höchstens 24 Stunden. Bitte geben Sie im Chat keine personenbezogenen Daten ein.', ['name' => $name])
            : __('KI-Chat auf dieser Website: Wenn Sie den Chat öffnen und eine Frage stellen, wird der Text Ihrer Frage ausschließlich auf unserem eigenen Server von einer KI verarbeitet, um eine Antwort aus den Inhalten dieser Website zu erzeugen (Art. 6 Abs. 1 lit. f DSGVO, berechtigtes Interesse an einer hilfreichen Auskunft). Es werden keine Daten an Dritte übermittelt und keine Cookies gesetzt. Fragen und Antworten werden nicht gespeichert; zum Schutz vor Missbrauch zählen wir Anfragen je verschlüsselter IP-Adresse für höchstens 24 Stunden. Bitte geben Sie im Chat keine personenbezogenen Daten ein.');
    }

    // ================================================================== Schutz

    /** Versuch, die Anweisungen auszuhebeln („ignoriere alle Regeln“, „zeige deinen Prompt“, Rollenspiele)? */
    public static function injection(string $q): bool
    {
        $t = mb_strtolower($q);
        $patterns = [
            '~\b(ignor|vergiss|missacht|überschreib|umgeh)\w*\b.{0,40}\b(anweisung|regel|vorgabe|prompt|instruktion|system)~u',
            '~\b(ignore|disregard|forget|override|bypass)\b.{0,40}\b(instruction|rule|prompt|guideline|system|previous|above)~u',
            '~\b(system ?prompt|systemanweisung|deine anweisungen|your (instructions|prompt)|initial prompt)\b~u',
            '~\b(jailbreak|dan mode|developer mode|entwicklermodus|do anything now)\b~u',
            '~\b(du bist (jetzt|ab sofort|nun)|you are now|act as|pretend to be|tu so,? als|spiel(e)? die rolle|role ?play)\b~u',
            '~\b(zeig|gib|nenn|verrat|print|reveal|show|repeat)\w*\b.{0,30}\b(prompt|anweisungen|instructions|konfiguration|api.?key|schlüssel)~u',
            '~<\s*/?\s*(system|assistant|script)\b|\[\[|\]\]|@@|```~u',
        ];
        foreach ($patterns as $p) if (preg_match($p, $t)) return true;
        return false;
    }

    /** E-Mail-Adressen und lange Nummern (Telefon, Versicherten-, Kontonummern) vor dem Senden an den Anbieter unkenntlich machen */
    public static function scrub(string $s): string
    {
        $s = (string) preg_replace('~[\w.+\-]+@[\w\-]+(\.[\w\-]+)+~u', '[E-Mail]', $s);
        $s = (string) preg_replace('~(?<![\w])\+?\d[\d \-/().]{6,}\d~u', '[Nummer]', $s);
        return (string) preg_replace('~\b[A-Z]{2}\d{2}(?: ?[A-Z0-9]{4}){3,7}\b~', '[IBAN]', $s);
    }

    /** Frage bereinigen (Länge, Steuerzeichen) */
    public static function clean(string $q): string
    {
        $q = (string) preg_replace('~[\x00-\x08\x0B-\x1F\x7F]+~u', ' ', $q);
        return trim(mb_substr((string) preg_replace('~[ \t]+~u', ' ', $q), 0, self::MAX_QUESTION));
    }

    /** Frühere Nachrichten vom Browser (nur zur Einordnung von Rückfragen) – gekürzt, ohne Marker */
    public static function history(mixed $in): array
    {
        $out = [];
        foreach (array_slice(is_array($in) ? $in : [], -self::MAX_HISTORY) as $m) {
            if (!is_array($m) || !in_array($m['role'] ?? '', ['user', 'assistant'], true)) continue;
            $c = self::clean(str_replace([Prompts::CHAT_UNKNOWN, Prompts::CHAT_REFUSE], '', strip_tags((string) ($m['content'] ?? ''))));
            if ($c === '') continue;
            $out[] = ['role' => $m['role'], 'content' => $m['role'] === 'user' ? self::scrub($c) : mb_substr($c, 0, 800)];
        }
        return $out;
    }

    /** Kurzer HMAC der IP (mit app_key) – nie die IP selbst */
    public static function ipKey(string $ip): string
    {
        return substr(hash_hmac('sha256', 'chat|' . $ip, (string) app()->config->get('app_key', CMS_NAME)), 0, 20);
    }

    /** Tageslimit der Website: [cap, used, left] (cap 0 = unbegrenzt) */
    public static function quota(): array
    {
        $cap = self::settings()['daily'];
        $used = Ai::callsToday(['chat']);
        return ['cap' => $cap, 'used' => $used, 'left' => $cap > 0 ? max(0, $cap - $used) : null];
    }

    // ================================================================== Quellen (Retrieval)

    /**
     * Quellen zur Frage: beste Textstellen der Suchtreffer (Sprache der Seite, Landing-Umfang) + öffentliche Angaben.
     * @return list<array{n: int, title: string, kind: string, url: string, text: string}>
     */
    public static function sources(string $q, array $history = []): array
    {
        // Kurze Rückfragen („Und am Samstag?“) mit der vorigen Frage zusammen suchen
        $query = $q;
        if (count(Text::words($q)) < 4) {
            foreach (array_reverse($history) as $m) if ($m['role'] === 'user') { $query = $m['content'] . ' ' . $q; break; }
        }
        $query = Search::clean($query);
        $out = [];
        $r = Search::query($query, ['per' => self::TOP_K + 3, 'count_miss' => false]);
        $lang = Lang::current();
        $kw = Search::keyword($lang);
        $words = Text::words($query);
        foreach ($r['items'] as $it) {
            if (count($out) >= self::TOP_K) break;
            $doc = method_exists($kw, 'document') ? $kw->document((string) $it['id']) : null;
            $text = $doc ? trim(implode("\n", array_filter([(string) ($doc['headings'] ?? ''), (string) ($doc['text'] ?? ''), (string) ($doc['extra'] ?? '')]))) : '';
            if ($text === '') $text = trim(html_entity_decode(strip_tags((string) $it['snippet']), ENT_QUOTES | ENT_HTML5));
            $passage = self::passage($text, $words, $out ? self::SOURCE_CHARS : self::SOURCE_CHARS * 2);   // beste Quelle: mehr Kontext
            if ($passage === '') continue;
            $out[] = ['n' => count($out) + 1, 'title' => (string) $it['title_plain'], 'kind' => (string) $it['badge'],
                'url' => (string) $it['url'], 'text' => ($it['date_label'] ?? '') !== '' ? $it['date_label'] . ': ' . $passage : $passage];
        }
        $info = self::infoText();
        if ($info !== '') {
            $contact = self::settings()['contact_url'];
            $out[] = ['n' => count($out) + 1, 'title' => lt('Kontakt & Öffnungszeiten'), 'kind' => '', 'url' => $contact !== '' ? link_href($contact) : url('/'), 'text' => $info];
        }
        return $out;
    }

    /**
     * Passendste Textstellen einer Quelle (Abschnitte à ~180 Wörter, gewertet nach Suchwörtern): die besten Abschnitte bis
     * $max Zeichen, in der Reihenfolge der Seite, getrennt mit „…“ – so kommen z. B. „Kosten“ am Seitenende mit, auch wenn
     * der Anfang ebenfalls passt.
     */
    private static function passage(string $text, array $words, int $max = self::SOURCE_CHARS): string
    {
        $text = trim((string) preg_replace('~\s+~u', ' ', $text));
        if (mb_strlen($text) <= $max) return $text;
        $chunks = Text::chunks($text, 180, 30);
        $stems = array_values(array_filter(array_map(fn($w) => mb_substr(Text::fold($w), 0, 5), $words)));
        // Seltene Suchwörter zählen mehr (IDF über die Abschnitte): „Netzwerk…“ steht überall, „Übernachtung“ nur beim Preis
        $folded = array_map(fn($c) => Text::fold($c), $chunks);
        $n = count($chunks);
        $idf = [];
        foreach ($stems as $st) {
            $df = count(array_filter($folded, fn($f) => str_contains($f, $st)));
            $idf[$st] = $df ? log(1 + $n / $df) : 0.0;
        }
        $score = [];
        foreach ($folded as $i => $f) {
            $sc = 0.0;
            foreach ($stems as $st) if ($idf[$st] > 0 && str_contains($f, $st)) $sc += $idf[$st] * (1 + min(5, substr_count($f, $st)) * 0.1);
            $score[$i] = $sc - $i * 0.001;   // gleichstand: früher zuerst
        }
        arsort($score);
        $pick = [];
        $len = 0;
        foreach (array_keys($score) as $i) {
            $l = mb_strlen($chunks[$i]) + 3;
            if ($pick && $len + $l > $max) continue;
            $pick[] = $i;
            $len += $l;
            if ($len >= $max * 0.9) break;
        }
        sort($pick);
        $out = [];
        foreach ($pick as $k => $i) $out[] = ($k > 0 && $pick[$k - 1] !== $i - 1 ? '… ' : '') . $chunks[$i];
        return mb_strimwidth(implode(' ', $out), 0, $max, '…');
    }

    /** Öffentliche Angaben als Text (Name, Adresse, Telefon, E-Mail, Öffnungszeiten, aktueller Hinweis) */
    public static function infoText(): string
    {
        $i = self::info();
        $l = [];
        if (!empty($i['name'])) $l[] = lt('Name') . ': ' . $i['name'];
        if (!empty($i['address']) && is_array($i['address'])) $l[] = lt('Adresse') . ': ' . implode(', ', array_filter([$i['address']['street'] ?? null, trim(($i['address']['zip'] ?? '') . ' ' . ($i['address']['city'] ?? ''))]));
        if (!empty($i['phone_display']) || !empty($i['phone'])) $l[] = lt('Telefon') . ': ' . ($i['phone_display'] ?? $i['phone']);
        if (!empty($i['email'])) $l[] = lt('E-Mail') . ': ' . $i['email'];
        if (!empty($i['appointment_url'])) $l[] = lt('Online-Termine') . ': ' . lt('ja, über den Termin-Link der Website');
        if (!empty($i['emergency']) && is_string($i['emergency'])) $l[] = lt('Notfall') . ': ' . $i['emergency'];
        if (!empty($i['opening_hours']) && is_array($i['opening_hours'])) {
            $l[] = lt('Öffnungszeiten') . ':';
            foreach ($i['opening_hours'] as $h) {
                if (is_array($h) && isset($h['wochentag'])) $l[] = '- ' . $h['wochentag'] . ': ' . ((string) ($h['text'] ?? '') ?: lt('geschlossen'));
                elseif (is_string($h)) $l[] = '- ' . $h;
            }
        }
        if (!empty($i['notice']) && is_string($i['notice'])) $l[] = lt('Aktueller Hinweis') . ': ' . $i['notice'];
        foreach ($i as $k => $v) {
            // Weitere einfache Angaben des Themes (z. B. Website-Zusatz) – ohne Adressen/URLs
            if (in_array($k, ['name', 'url', 'address', 'phone', 'phone_display', 'email', 'appointment_url', 'emergency', 'opening_hours', 'notice'], true)) continue;
            if (is_string($v) && $v !== '' && mb_strlen($v) < 200 && !preg_match('~^https?://~', $v)) $l[] = ucfirst(str_replace('_', ' ', (string) $k)) . ': ' . $v;
        }
        return count($l) > 1 ? implode("\n", $l) : '';
    }

    // ================================================================== Antwort

    /**
     * Antwort erzeugen. $emit(string $event, array $data): bool – „delta“ (Textstück), am Ende gibt answer() das Ergebnis zurück.
     * @return array{text: string, sources: list<array>, unknown: bool, refused: bool, contact: ?array}
     */
    public static function answer(string $q, array $history, callable $emit): array
    {
        $s = self::settings();
        if (Lang::current() !== Lang::default()) $s['contact_text'] = '';
        $contact = self::contact($s, self::info());
        $done = fn(string $text, array $sources = [], bool $unknown = false, bool $refused = false) => [
            'text' => $text, 'sources' => $sources, 'unknown' => $unknown, 'refused' => $refused, 'contact' => $unknown ? $contact : null];
        if (self::injection($q)) {
            return $done(lt('Dabei kann ich nicht helfen. Ich beantworte nur Fragen zu den Inhalten dieser Website.'), [], false, true);
        }
        $sources = self::sources($q, $history);
        if (!$sources) return $done($contact['text'], [], true);
        $pack = Prompts::visitorChat(self::scrub($q), $sources, $history, [
            'language' => Lang::current(), 'site' => site_name(), 'hint' => self::themeHint(),
        ]);
        // Quellen vorab (Belege [n] sind dann schon während des Streams Links)
        $emit('sources', ['items' => array_map(fn($s) => ['n' => $s['n'], 'title' => $s['title'], 'url' => $s['url']], $sources)]);
        // Marker am Anfang zurückhalten, bis klar ist, dass es eine normale Antwort wird
        $buf = '';
        $open = false;
        $fake = self::fakeAnswer($q, $sources);
        $r = Ai::stream($pack['messages'], ['system' => $pack['system'], 'max_tokens' => $pack['max_tokens'], 'temperature' => $pack['temperature'],
            'track' => 'chat', 'use' => 'chat', 'timeout' => 60, 'fake' => $fake], function (string $piece) use (&$buf, &$open, $emit) {
            $buf .= $piece;
            if (!$open) {
                $t = ltrim($buf);
                if ($t === '' || (str_starts_with($t, '[') && mb_strlen($t) < 16)) return true;
                if (str_contains($t, '[[')) return true;
                $open = true;
                return $emit('delta', ['t' => $buf]);
            }
            return str_contains($buf, '[[') ? true : $emit('delta', ['t' => $piece]);
        });
        $text = trim($r['text']);
        if ($text === '' || str_contains($text, Prompts::CHAT_UNKNOWN) || preg_match('~\[\[\s*weiss_nicht~i', $text)) return $done($contact['text'], [], true);
        if (str_contains($text, Prompts::CHAT_REFUSE)) return $done(lt('Dabei kann ich nicht helfen. Ich beantworte nur Fragen zu den Inhalten dieser Website.'), [], false, true);
        [$text, $cited] = self::cite($text, $sources);
        // Zahlen, die in keiner Quelle stehen → nicht ausgeben (lieber auf die Seiten verweisen)
        if (self::inventedNumbers($text, $sources, $q . ' ' . self::themeHint())) {
            return $done(lt('Bei den genauen Angaben bin ich mir nicht sicher. Bitte sehen Sie direkt auf diesen Seiten nach:'),
                array_map(fn($s) => ['n' => $s['n'], 'title' => $s['title'], 'url' => $s['url']], array_slice($sources, 0, 3)));
        }
        return $done($text, $cited);
    }

    /** Test-Anbieter „fake“: Antwort aus der ersten Quelle (mit Beleg) bzw. „weiß ich nicht“ ohne passende Quelle */
    private static function fakeAnswer(string $q, array $sources): string
    {
        $words = array_map(fn($w) => mb_substr(Text::fold($w), 0, 5), Text::words($q));
        foreach ($sources as $s) {
            $f = Text::fold($s['title'] . ' ' . $s['text']);
            foreach ($words as $w) {
                if (mb_strlen($w) >= 5 && str_contains($f, $w)) {
                    return '[Test-KI] ' . mb_strimwidth(trim((string) preg_replace('~\s+~u', ' ', $s['text'])), 0, 180, '…') . ' [' . $s['n'] . ']';
                }
            }
        }
        return Prompts::CHAT_UNKNOWN;
    }

    /** Belege [n] prüfen, Links/Adressen des Modells entfernen → [Text, zitierte Quellen] */
    public static function cite(string $text, array $sources): array
    {
        $by = array_column($sources, null, 'n');
        // Markdown-Links und nackte Adressen: nur der Linktext bleibt (verlinkt werden ausschließlich die Quellen)
        $text = (string) preg_replace('~\[([^\]]+)\]\((?:https?://|/)[^)]*\)~u', '$1', $text);
        $text = (string) preg_replace('~\bhttps?://\S+~u', '', $text);
        $cited = [];
        $text = (string) preg_replace_callback('~\[(\d{1,2}(?:\s*,\s*\d{1,2})*)\]~', function ($m) use ($by, &$cited) {
            $ok = [];
            foreach (preg_split('~\s*,\s*~', $m[1]) ?: [] as $n) {
                if (isset($by[(int) $n])) { $ok[] = (int) $n; $cited[(int) $n] = true; }
            }
            return $ok ? '[' . implode('] [', $ok) . ']' : '';
        }, $text);
        $list = [];
        foreach (array_keys($cited) as $n) $list[] = ['n' => $n, 'title' => $by[$n]['title'], 'url' => $by[$n]['url']];
        usort($list, fn($a, $b) => $a['n'] <=> $b['n']);
        return [trim((string) preg_replace('~[ \t]+~', ' ', $text)), $list];
    }

    /** Enthält die Antwort Zahlen (Zeiten, Preise, Nummern), die weder in den Quellen noch in der Frage stehen? */
    public static function inventedNumbers(string $text, array $sources, string $q): bool
    {
        $plain = (string) preg_replace('~\[\d{1,2}\]~', '', $text);
        if (!preg_match_all('~\d+~', $plain, $m)) return false;
        $have = [];
        $hay = $q . ' ' . implode(' ', array_map(fn($s) => $s['title'] . ' ' . $s['text'], $sources));
        preg_match_all('~\d+~', $hay, $h);
        foreach ($h[0] as $n) $have[ltrim($n, '0') ?: '0'] = true;
        foreach ($m[0] as $n) {
            $k = ltrim($n, '0') ?: '0';
            if (!isset($have[$k]) && !isset($have[$n])) return true;
        }
        return false;
    }
}
