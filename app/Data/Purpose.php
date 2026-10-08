<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Features;

/**
 * Verwendungszweck einer Datentabelle (settings.purpose) – wofür die Tabelle da ist. Steuert den Assistenten „Neue Tabelle“
 * (Vorlagen, Grundeinstellungen, „Einsetzen“) und welche Bereiche „Felder & Einstellungen“ zuerst zeigt. Am Speichern und an den
 * Rechten ändert der Zweck nichts – die Art (settings.kind: content | inbox) bleibt die technische Grundlage.
 *
 *   content      Inhalte auf der Website (Liste, optional Detailseiten) – z. B. Aktuelles, Team, Termine, Verzeichnis
 *   mail         Formular, das nur eine E-Mail schickt – Eingang mit Zustellung „Nur per E-Mail“ (Core\Data\Delivery), nichts gespeichert
 *   inbox        Anfragen sammeln & bearbeiten – Eingang, Ende-zu-Ende verschlüsselt, Status, Löschfrist (Core\Data\Inbox)
 *   registration Anmeldung/Bewerbung – öffentliches Formular + Teilnehmerliste (Inhaltstabelle, Einträge als Entwurf) bzw. Eingang
 *   internal     Interne Liste – nicht auf der Website, nicht in der Suche
 *   source       Aus externer Quelle – Einträge kommen aus einem Feed/einer API (Core\Sources)
 *
 * Ältere Tabellen ohne Angabe bekommen den Zweck beim Lesen abgeleitet (derive) – gespeichert wird er erst beim nächsten Speichern.
 */
final class Purpose
{
    public const KEYS = ['content', 'mail', 'inbox', 'registration', 'internal', 'source'];
    /** Zwecke, die eine Eingangs-Tabelle (kind = inbox) haben kann – die übrigen sind Inhaltstabellen */
    public const INBOX = ['mail', 'inbox', 'registration'];
    public const CONTENT = ['content', 'registration', 'internal', 'source'];

    /** Bereiche von „Felder & Einstellungen“ (Reihenfolge der Seitenleiste) */
    public const SECTIONS = ['allgemein', 'felder', 'website', 'formular', 'benachrichtigungen', 'suche', 'kalender', 'einsetzen', 'erweitert'];

    /** Beschriftung, Symbol, Erklärung und Beispiele je Zweck */
    public static function all(): array
    {
        return [
            'content' => ['label' => __('Inhalte auf der Website'), 'icon' => 'newspaper',
                'lead' => __('Einträge erscheinen als Liste auf einer Seite – auf Wunsch mit eigener Detailseite je Eintrag.'),
                'examples' => __('Aktuelles, Team, Termine, Verzeichnis, Produkte')],
            'mail' => ['label' => __('Formular, das nur eine E-Mail schickt'), 'icon' => 'paper-plane-tilt',
                'lead' => __('Jede Einsendung geht per E-Mail an Sie. Auf der Website wird nichts gespeichert – keine Detailseite, kein Eingang.'),
                'examples' => __('Kontakt, Rückrufbitte')],
            'inbox' => ['label' => __('Anfragen sammeln & bearbeiten'), 'icon' => 'tray',
                'lead' => __('Einsendungen landen Ende-zu-Ende verschlüsselt im Eingang „Anfragen“ – mit Status, Benachrichtigung ohne Inhalte und Löschfrist.'),
                'examples' => __('Terminwunsch, Rezeptbestellung, Beratungsanfrage')],
            'registration' => ['label' => __('Anmeldung oder Bewerbung'), 'icon' => 'clipboard-text',
                'lead' => __('Formular und Teilnehmerliste in der Verwaltung, Bestätigung per E-Mail an die Absender – auf Wunsch mit Obergrenze.'),
                'examples' => __('Kursanmeldung, Veranstaltung, Bewerbungen')],
            'internal' => ['label' => __('Interne Liste'), 'icon' => 'lock',
                'lead' => __('Nur für die Verwaltung: nicht auf der Website, nicht in Suche und Sitemap.'),
                'examples' => __('Kontakte, Inventar, Aufgaben')],
            'source' => ['label' => __('Aus externer Quelle'), 'icon' => 'plugs-connected',
                'lead' => __('Einträge aus einem Feed, einer API oder OpenImmo übernehmen und regelmäßig abgleichen.'),
                'examples' => __('RSS-Feed, Immobilien, Veranstaltungskalender')],
        ];
    }

    public static function label(string $p): string
    {
        return self::all()[$p]['label'] ?? self::all()['content']['label'];
    }

    public static function valid(mixed $p): bool
    {
        return is_string($p) && in_array($p, self::KEYS, true);
    }

    /** Zweck einer Tabelle: gespeichert oder (ältere Tabellen) abgeleitet */
    public static function of(array $t): string
    {
        $p = $t['settings']['purpose'] ?? null;
        return self::valid($p) && self::fits($p, $t['settings']) ? $p : self::derive((array) ($t['settings'] ?? []));
    }

    /**
     * Zweck aus den übrigen Einstellungen ableiten (Tabellen von vor dem Assistenten): Eingang → „nur E-Mail“ bzw. „Anfragen“,
     * Inhaltstabelle mit Detailseiten → Inhalte, ohne Detailseiten mit öffentlichem Formular → Anmeldung, sonst intern.
     * Ändert nichts an der Tabelle – nur die Reihenfolge der Bereiche und die Hinweise unter „Einsetzen“.
     */
    public static function derive(array $s): string
    {
        if (($s['kind'] ?? 'content') === 'inbox') return (($s['inbox']['delivery']['mode'] ?? 'system') === 'mail') ? 'mail' : 'inbox';
        if (trim((string) ($s['route'] ?? '')) !== '') return 'content';
        if (!empty($s['form']['enabled'])) return 'registration';
        return 'internal';
    }

    /** Passt der Zweck zur Art der Tabelle (Eingang/Inhalt) und – im Eingang – zur Zustellung? */
    public static function fits(string $p, array $s): bool
    {
        $inbox = ($s['kind'] ?? 'content') === 'inbox';
        if (!in_array($p, $inbox ? self::INBOX : self::CONTENT, true)) return false;
        if ($inbox && $p !== 'registration') {
            return ($p === 'mail') === ((($s['inbox']['delivery']['mode'] ?? 'system')) === 'mail');
        }
        return true;
    }

    /**
     * Zweck beim Speichern (Tables::validate): gewählt und passend → übernehmen, sonst der bisherige (falls passend), sonst abgeleitet.
     * So bleibt ein bewusst gewählter Zweck erhalten; ändert sich die Zustellung eines Eingangs, folgt „nur E-Mail“ ⇄ „Anfragen“.
     */
    public static function clean(mixed $in, array $settings, ?array $existing): string
    {
        foreach ([$in, $existing['settings']['purpose'] ?? null] as $p) {
            if (self::valid($p) && self::fits($p, $settings)) return $p;
            // Eingang: „Anfragen“ ⇄ „nur E-Mail“ folgt der Zustellung
            if (in_array($p, ['mail', 'inbox'], true) && ($settings['kind'] ?? '') === 'inbox') return self::derive($settings);
        }
        return self::derive($settings);
    }

    /** Art der Tabelle für einen Zweck (Anmeldung: je nach Vorlage – Bewerbungen sind ein Eingang) */
    public static function kind(string $p): string
    {
        return in_array($p, ['mail', 'inbox'], true) ? 'inbox' : 'content';
    }

    /**
     * Kann der Zweck auf dieser Website gewählt werden? null = ja, sonst der Grund (für den Assistenten, mit Link zum Einschalten).
     * „Nur E-Mail“ braucht die Funktionen „Anfragen“ und „Anfragen per E-Mail zustellen“, „Anfragen“ die Funktion „Anfragen“,
     * Anmeldung das öffentliche Formular für Datentabellen, „Externe Quelle“ die Funktion „Externe Quellen“.
     */
    public static function unavailable(string $p): ?string
    {
        return match ($p) {
            'mail' => Delivery::available() ? null : (!Inbox::available() ? __('Dafür braucht es die Funktion „Anfragen“ (Funktionen & Erweiterungen).')
                : __('Dafür braucht es die Funktion „Anfragen per E-Mail zustellen“ (Funktionen & Erweiterungen).')),
            'inbox' => Inbox::available() ? null : __('Dafür braucht es die Funktion „Anfragen“ (Funktionen & Erweiterungen).'),
            'registration' => DataForms::available() ? null : __('Dafür braucht es die Funktion „Formulare für Datentabellen“ (Funktionen & Erweiterungen).'),
            'source' => Features::on('sources') ? null : __('Dafür braucht es die Funktion „Externe Quellen“ (Funktionen & Erweiterungen).'),
            default => null,
        };
    }

    /** Vorlagen (Schlüssel aus DataController::presets()) je Zweck – der Assistent zeigt nur die passenden */
    public static function presetKeys(string $p, array $presets): array
    {
        return array_keys(array_filter($presets, fn($x) => self::ofPreset($x) === $p));
    }

    /** Zweck einer Vorlage: angegeben oder aus der Art */
    public static function ofPreset(array $p): string
    {
        if (self::valid($p['purpose'] ?? null)) return $p['purpose'];
        return ($p['kind'] ?? 'content') === 'inbox' ? 'inbox' : 'content';
    }

    /**
     * Bereiche von „Felder & Einstellungen“: [Hauptbereiche, weitere Bereiche]. Eingänge haben keine Website-, Such- und Kalender-
     * Einstellungen (sie geben nichts aus); bei den übrigen Zwecken stehen nicht passende Bereiche unter „Weitere Bereiche“.
     */
    public static function sections(string $p, bool $inbox): array
    {
        $cal = Features::on('calendar');
        if ($inbox) return [['allgemein', 'felder', 'formular', 'benachrichtigungen', 'einsetzen', 'erweitert'], []];
        $main = match ($p) {
            'registration' => ['allgemein', 'felder', 'formular', 'benachrichtigungen', 'einsetzen', 'erweitert'],
            'internal' => ['allgemein', 'felder', 'erweitert'],
            default => ['allgemein', 'felder', 'website', 'suche', 'benachrichtigungen', 'einsetzen', 'erweitert'],
        };
        if ($cal && $p !== 'internal' && $p !== 'registration') array_splice($main, array_search('suche', $main, true) + 1, 0, ['kalender']);
        $more = array_values(array_diff(self::SECTIONS, $main, $cal ? [] : ['kalender']));
        return [$main, $more];
    }

    /**
     * Selbsttest (data:selftest): Ableitung, Passung, Bereinigung beim Speichern und Bereiche je Zweck – ohne Datenbank.
     * @return array{ok: int, fails: string[]}
     */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $mail = ['kind' => 'inbox', 'inbox' => ['delivery' => ['mode' => 'mail']]];
        $sys = ['kind' => 'inbox', 'inbox' => ['delivery' => ['mode' => 'system']]];
        $eq('Ableitung: Eingang nur per E-Mail', self::derive($mail), 'mail');
        $eq('Ableitung: Eingang im System', self::derive($sys), 'inbox');
        $eq('Ableitung: Eingang ohne Zustellung', self::derive(['kind' => 'inbox']), 'inbox');
        $eq('Ableitung: Inhalte mit Detailseiten', self::derive(['kind' => 'content', 'route' => 'aktuelles']), 'content');
        $eq('Ableitung: ohne Detailseiten mit Formular', self::derive(['kind' => 'content', 'route' => '', 'form' => ['enabled' => true]]), 'registration');
        $eq('Ableitung: ohne Detailseiten, ohne Formular', self::derive(['kind' => 'content', 'route' => '']), 'internal');
        $eq('Ableitung: ältere Tabelle ohne Angaben', self::derive([]), 'internal');
        $eq('Passt: Inhalte in Inhaltstabelle', self::fits('content', ['kind' => 'content']), true);
        $eq('Passt nicht: „nur E-Mail“ in Inhaltstabelle', self::fits('mail', ['kind' => 'content']), false);
        $eq('Passt nicht: intern im Eingang', self::fits('internal', $sys), false);
        $eq('Passt: Anmeldung im Eingang (Bewerbungen)', self::fits('registration', $sys), true);
        $eq('Passt nicht: „nur E-Mail“ mit Speicherung', self::fits('mail', $sys), false);
        $eq('Speichern: gewählter Zweck bleibt', self::clean('internal', ['kind' => 'content', 'route' => 'x'], null), 'internal');
        $eq('Speichern: unbekannter Zweck → abgeleitet', self::clean('quatsch', ['kind' => 'content', 'route' => 'x'], null), 'content');
        $eq('Speichern: bisheriger Zweck bleibt ohne Angabe', self::clean(null, ['kind' => 'content', 'route' => 'x'], ['settings' => ['purpose' => 'source']]), 'source');
        $eq('Speichern: Zustellung geändert → „Anfragen“', self::clean('mail', $sys, ['settings' => ['purpose' => 'mail']]), 'inbox');
        $eq('Speichern: Zustellung „nur E-Mail“ → „nur E-Mail“', self::clean('inbox', $mail, null), 'mail');
        $eq('Speichern: Art gewechselt (Inhalt → Eingang)', self::clean('content', $sys, ['settings' => ['purpose' => 'content']]), 'inbox');
        $eq('of(): gespeichert', self::of(['settings' => ['kind' => 'content', 'purpose' => 'source']]), 'source');
        $eq('of(): unpassend gespeichert → abgeleitet', self::of(['settings' => ['kind' => 'content', 'route' => '', 'purpose' => 'mail']]), 'internal');
        [$main, $more] = self::sections('mail', true);
        $eq('Bereiche Eingang: keine Website/Suche/Kalender', array_values(array_intersect($main + $more, ['website', 'suche', 'kalender'])), []);
        $eq('Bereiche Eingang: Einsetzen dabei', in_array('einsetzen', $main, true), true);
        [$main, $more] = self::sections('internal', false);
        $eq('Bereiche intern: Website unter „Weitere“', [in_array('website', $main, true), in_array('website', $more, true)], [false, true]);
        [$main, $more] = self::sections('content', false);
        $eq('Bereiche Inhalte: alle Bereiche erreichbar', count(array_unique([...$main, ...$more])) >= count(self::SECTIONS) - (Features::on('calendar') ? 0 : 1), true);
        foreach (self::KEYS as $k) $eq("Beschriftung {$k}", self::label($k) !== '', true);
        return ['ok' => $ok, 'fails' => $fails];
    }
}
