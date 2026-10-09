<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Features;
use Core\Fields;
use Core\Lang;
use Core\Mailer;
use Core\Media;
use Core\Pages;
use Core\SpamGuard;

/**
 * Öffentliche Formulare für Datentabellen: Besucher legen Einträge an (Funktion „forms.data“).
 *
 * Einstellung je Tabelle (settings.form), Ausgabe über den Kern-Block „data_form“, Annahme unter POST /formular/{tabelle}.
 * Schutz: SpamGuard (signiertes Token, Proof-of-Work, Honeypot, Mindestzeit,
 * Rate-Limit je IP-Hash, Link-/Sperrwortfilter), keine Cookies, keine Session. Prüfung serverseitig mit Fields (Besuchersprache)
 * und den Bedingungen der Felder (Core\Data\Rules). Benachrichtigung ohne Inhalte.
 * Inhaltstabellen: Einträge werden NICHT verschlüsselt gespeichert. Eingangs-Tabellen (settings.kind = inbox, Funktion „requests“):
 * gleiche Prüfung, danach je nach Zustellung (Core\Data\Delivery) Ende-zu-Ende verschlüsselt über Core\Data\Inbox::store und/oder
 * mit vollem Inhalt per E-Mail (ohne öffentlichen Schlüssel kein Formular – er sichert auch den Rückfall). Dateifelder nur bei Zustellung
 * per E-Mail: die Datei geht als Anhang hinaus bzw. versiegelt in den Payload – nie in die Mediathek.
 */
final class DataForms
{
    public const DEFAULTS = ['enabled' => false, 'fields' => [], 'status' => 'draft', 'notify' => '', 'success' => '', 'submit' => '',
        'uploads' => false, 'upload_mb' => 5, 'receipt' => self::RECEIPT, 'max' => 0];
    /** Eingangsbestätigung an die absendende Person (z. B. Pflicht beim Widerruf, § 356a BGB): E-Mail-Feld, Betreff, Text, Angaben mitsenden */
    public const RECEIPT = ['enabled' => false, 'field' => '', 'subject' => '', 'text' => '', 'include' => false];
    /** Feldtypen, die Besucher ausfüllen können (Verknüpfungen, Rich-Text, Karte, Links und Wiederholungen bleiben der Redaktion vorbehalten) */
    public const TYPES = ['text', 'textarea', 'number', 'bool', 'date', 'datetime', 'time', 'select', 'multiselect', 'email', 'tel', 'color', 'iban', 'group'];
    public const UPLOAD_TYPES = ['media', 'file'];
    public const PRIVACY = '_privacy';
    /**
     * Dateitypen der Dateifelder (Feld-Einstellung accept): MIME-Typen (erkannt am Inhalt mit finfo) und Dateiendungen.
     * Standard PDF + Bilder. DOCX/ODT nur in Eingangs-Tabellen (Zustellung per E-Mail) – die Mediathek speichert sie nicht.
     * DOCX/ODT sind ZIP-Container: geprüft werden [Content_Types].xml bzw. mimetype; Makros (docm, vbaProject.bin, Basic/, Scripts/) werden abgelehnt.
     */
    public const FILE_KINDS = [
        'pdf' => ['mimes' => ['application/pdf'], 'ext' => ['pdf']],
        'image' => ['mimes' => ['image/jpeg', 'image/png', 'image/webp'], 'ext' => ['jpg', 'jpeg', 'png', 'webp']],
        'docx' => ['mimes' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], 'ext' => ['docx']],
        'odt' => ['mimes' => ['application/vnd.oasis.opendocument.text'], 'ext' => ['odt']],
    ];
    public const FILE_KINDS_DEFAULT = ['pdf', 'image'];
    /** Nur mit Zustellung per E-Mail (Eingang) – nicht in der Mediathek speicherbar */
    public const FILE_KINDS_INBOX = ['docx', 'odt'];
    /** Höchstgröße je Datei (MB) – Grenze der Tabelle und der Felder */
    public const MAX_MB = 10;
    /** Obergrenze der Einsendungen (settings.form.max, nur Inhaltstabellen, z. B. Plätze einer Anmeldung): höchstens so viele */
    public const MAX_ENTRIES = 100000;

    /** Zustand nach dem Absenden ohne JavaScript (Fehler, Werte, Meldung) je Tabelle – liest der Block */
    public static array $state = [];

    public static function key(array $t): string
    {
        return 'data-' . $t['handle'];
    }

    public static function available(): bool
    {
        return Features::on('forms.data', false) && Features::on('data', false);
    }

    /**
     * Nimmt die Tabelle Einsendungen an? $direct = true: über /formular/{handle} bzw. den Block „data_form“ – Eingänge einer Erweiterung
     * mit 'direct_form' => false (z. B. Buchungen) nur über deren eigenes Formular (submit mit 'store').
     */
    public static function enabled(array $t, bool $direct = true): bool
    {
        if (Inbox::is($t)) return Inbox::available() && !empty($t['settings']['form']['enabled']) && (!$direct || Inbox::directForm($t));
        return self::available() && !empty($t['settings']['form']['enabled']);
    }

    /**
     * Obergrenze erreicht? (settings.form.max, nur Inhaltstabellen) – gezählt werden alle Einträge der Tabelle (auch Entwürfe,
     * auch von Hand angelegte). Gelöschte Einträge geben ihren Platz wieder frei.
     */
    public static function full(array $t): bool
    {
        $max = (int) ($t['settings']['form']['max'] ?? 0);
        return $max > 0 && !Inbox::is($t) && Entries::count($t, ['status' => 'all', 'lang' => 'all', 'source' => 'own']) >= $max;
    }

    /** Text nach dem Absenden: Block/Tabelle, bei Eingangs-Tabellen sonst der Text des Theme-Formulars */
    public static function successText(array $t): string
    {
        if (Inbox::is($t)) return Inbox::text($t, 'success');
        $s = $t['settings']['form'];
        return $s['success'] !== '' ? $s['success'] : lt('Vielen Dank – Ihre Angaben sind eingegangen.');
    }

    /** Darf der Feldtyp ins öffentliche Formular? Abschnitte und Freitext (Tables::LAYOUT) immer */
    public static function eligible(array $f, bool $uploads): bool
    {
        return in_array($f['type'], self::TYPES, true) || in_array($f['type'], Tables::LAYOUT, true)
            || ($uploads && in_array($f['type'], self::UPLOAD_TYPES, true));
    }

    /** Datenfelder des Formulars (Reihenfolge wie in der Tabelle): gewählte + alle Pflichtfelder; nichts gewählt = alle geeigneten */
    public static function fields(array $t): array
    {
        return Tables::dataFields(self::items($t));
    }

    /**
     * Alle Elemente des Formulars in der Reihenfolge der Tabelle: Datenfelder wie fields() und dazwischen die gewählten
     * Abschnitte und Freitexte (Tables::LAYOUT). Für die Ausgabe (render) – Annahme und Prüfung nutzen nur fields().
     */
    public static function items(array $t): array
    {
        $s = $t['settings']['form'];
        // Eingang: Dateien nur bei Zustellung per E-Mail (Anhang bzw. versiegelt) – Gesundheitsdaten nie in die öffentliche Mediathek
        $inbox = Inbox::is($t);
        $uploads = !empty($s['uploads']) && (!$inbox || Delivery::mails($t));
        return array_values(array_filter(Tables::allFields($t), fn($f) => self::eligible($f, $uploads) && (!$inbox || $f['type'] !== 'media')
            && self::selected($t, $f)));
    }

    /**
     * Elemente in Abschnitte gliedern: [[Abschnitt|null, [Elemente …]], …] – Felder vor dem ersten Abschnitt stehen ohne Abschnitt.
     * Leere Abschnitte (nichts dahinter oder alle Felder abgewählt und kein Freitext) entfallen.
     */
    public static function sections(array $items): array
    {
        $out = [[null, []]];
        foreach ($items as $f) {
            if (($f['type'] ?? '') === 'section') { $out[] = [$f, []]; continue; }
            $out[count($out) - 1][1][] = $f;
        }
        return array_values(array_filter($out, fn($g) => $g[1] !== []));
    }

    /** Freitext im Formular: formatierter Text (Whitelist Core\Sanitizer), ohne Beschriftung */
    public static function contentHtml(array $f): string
    {
        $html = rich((string) ($f['text'] ?? ''));
        return trim(strip_tags($html)) === '' ? '' : '<div class="dff-text prose">' . $html . '</div>';
    }

    /**
     * Steht das Feld in der Auswahl „Felder im Formular“? Nichts gewählt = alle; Pflichtfelder, Abschnitte und Freitext immer
     * (Gestaltung gibt es nur fürs Formular; ein Abschnitt, dessen Felder alle abgewählt sind, entfällt – sections()).
     * Eingang (die Felder bilden das Formular): Felder, die bei der letzten Auswahl noch nicht zur Wahl standen (settings.form.known –
     * z. B. im selben Schritt neu angelegt), sind dabei, bis jemand sie abwählt. Ältere Einstellungen ohne known: nicht gewählte
     * Dateifelder sind dabei – sie wurden nach der Auswahl angelegt und gingen sonst still verloren.
     */
    public static function selected(array $t, array $f): bool
    {
        $s = (array) ($t['settings']['form'] ?? []);
        $sel = (array) ($s['fields'] ?? []);
        if (!$sel || in_array($f['name'], $sel, true) || !empty($f['required']) || Tables::isLayout($f)) return true;
        if (!Inbox::is($t)) return false;
        $known = $s['known'] ?? null;
        return is_array($known) ? !in_array($f['name'], $known, true) : ($f['type'] ?? '') === 'file';
    }

    /** Erlaubte Dateitypen eines Upload-Feldes (Schlüssel aus FILE_KINDS); Bildfeld: nur Bilder, ohne Eingang kein DOCX/ODT */
    public static function fileKinds(array $f, bool $inbox): array
    {
        if (($f['type'] ?? '') === 'media') return ['image'];
        $k = array_values(array_intersect(array_keys(self::FILE_KINDS), (array) ($f['accept'] ?? self::FILE_KINDS_DEFAULT)));
        if (!$inbox) $k = array_values(array_diff($k, self::FILE_KINDS_INBOX));
        return $k ?: self::FILE_KINDS_DEFAULT;
    }

    /** Höchstgröße (MB) je Datei für das Feld: eigene Grenze des Feldes, höchstens die der Tabelle */
    public static function fileMb(array $f, array $s): int
    {
        $table = max(1, min(self::MAX_MB, (int) ($s['upload_mb'] ?? 5)));
        $own = (int) ($f['max_mb'] ?? 0);
        return $own > 0 ? min($own, $table) : $table;
    }

    /** Sichtbarer Hinweis unter dem Dateifeld: „PDF oder Bild (JPG, PNG, WebP), höchstens 5 MB.“ (Sprache der Website) */
    public static function fileHint(array $kinds, int $mb): string
    {
        $names = array_map(fn($k) => match ($k) {
            'pdf' => 'PDF', 'image' => lt('Bild (JPG, PNG, WebP)'), 'docx' => 'Word (DOCX)', 'odt' => lt('OpenDocument-Text (ODT)'), default => $k,
        }, $kinds);
        $last = array_pop($names);
        $types = $names ? implode(', ', $names) . ' ' . lt('oder') . ' ' . $last : (string) $last;
        return lt('{types}, höchstens {mb} MB.', ['types' => $types, 'mb' => $mb]);
    }

    /** accept-Attribut: MIME-Typen und Endungen (Auswahldialog – geprüft wird serverseitig am Inhalt) */
    public static function acceptAttr(array $kinds): string
    {
        $a = [];
        foreach ($kinds as $k) {
            foreach (self::FILE_KINDS[$k]['ext'] ?? [] as $x) $a[] = '.' . $x;
            foreach (self::FILE_KINDS[$k]['mimes'] ?? [] as $m) $a[] = $m;
        }
        return implode(',', array_unique($a));
    }

    // ================================================================= Einstellungen (Tables::validate)

    /**
     * settings.form prüfen. $uploads: wirksamer Schalter „Datei-Uploads“ (Eingang: aus der Zustellung abgeleitet – Tables::validate),
     * null = aus $s. $known: Eingang – Felder, die in der Auswahl zur Wahl standen (Felder der gespeicherten Tabelle); siehe selected().
     */
    public static function validateSettings(array $s, array $fields, array &$errors, ?array $existing, ?bool $uploads = null, ?array $known = null): array
    {
        $existing = ($existing ?? []) + self::DEFAULTS;
        if (!$s) return $existing;                                 // z. B. API ohne Formular-Angaben: unverändert
        $uploads ??= !empty($s['uploads']);
        // Auswahl „Felder im Formular“: nur Datenfelder – Abschnitte und Freitext gibt es nur fürs Formular, sie stehen immer darin
        $eligible = array_column(array_filter(Tables::dataFields($fields), fn($f) => self::eligible($f, $uploads)), 'name');
        $sel = array_key_exists('fields', $s)
            ? array_values(array_intersect($eligible, array_map('strval', (array) $s['fields'])))
            : array_values(array_intersect($eligible, (array) $existing['fields']));
        $out = [
            'enabled' => !empty($s['enabled']),
            'fields' => $sel,
            'status' => ($s['status'] ?? '') === 'published' ? 'published' : 'draft',
            'notify' => '',
            'success' => mb_substr(trim(strip_tags((string) ($s['success'] ?? ''))), 0, 500),
            'submit' => mb_substr(trim(strip_tags((string) ($s['submit'] ?? ''))), 0, 60),
            'uploads' => $uploads,
            'upload_mb' => max(1, min(self::MAX_MB, (int) ($s['upload_mb'] ?? $existing['upload_mb']))),
            'receipt' => self::validateReceipt((array) ($s['receipt'] ?? $existing['receipt'] ?? []), $fields, $errors),
            // Obergrenze (0 = keine): Anmeldungen schließen, sobald so viele Einträge da sind – zählt alle Einträge der Tabelle
            'max' => max(0, min(self::MAX_ENTRIES, (int) ($s['max'] ?? $existing['max'] ?? 0))),
        ];
        // Eingang: welche Felder standen zur Wahl? (neue Felder sind im Formular, bis jemand sie abwählt – selected())
        if ($known !== null) $out['known'] = array_key_exists('fields', $s) ? array_values(array_map('strval', $known)) : ($existing['known'] ?? null);
        if (($out['known'] ?? 0) === null) unset($out['known']);
        $mails = array_values(array_filter(array_map('trim', preg_split('~[,;\s]+~', (string) ($s['notify'] ?? '')))));
        foreach ($mails as $m) {
            if (!filter_var($m, FILTER_VALIDATE_EMAIL)) $errors['settings.form'] = __('Benachrichtigung: „{mail}“ ist keine gültige E-Mail-Adresse.', ['mail' => $m]);
        }
        $out['notify'] = implode(', ', array_slice($mails, 0, 5));
        if ($out['enabled']) {
            foreach ($fields as $f) {
                if (!empty($f['required']) && !self::eligible($f, $uploads)) {
                    $errors['settings.form'] = __('Pflichtfeld „{label}“ kann im öffentlichen Formular nicht ausgefüllt werden – Pflicht aufheben oder das Formular ausschalten.', ['label' => $f['label']]);
                }
            }
            if (!$eligible) $errors['settings.form'] = __('Die Tabelle hat keine Felder, die Besucher ausfüllen können.');        }
        return $out;
    }

    // ================================================================= Ausgabe

    /** Datenschutzseite: Einstellung des Themes (privacy_page / datenschutz_seite) oder Seite „datenschutz“ */
    public static function privacyUrl(): string
    {
        $p = self::privacyPage();
        return $p ? Pages::url($p) : '';
    }

    /** Datenschutzseite (in der Sprache $lang bzw. der aktuellen) oder null – auch für den Dialog (Core\LegalDialog) */
    public static function privacyPage(?string $lang = null): ?array
    {
        $lang ??= Lang::current();
        foreach ((array) (app()->theme->def['privacy_settings'] ?? ['privacy_page', 'datenschutz_seite']) as $k) {
            $id = (int) setting($k);
            if ($id && ($p = Pages::find($id))) {
                return Lang::multi() ? (Pages::translations($p)[$lang] ?? $p) : $p;
            }
        }
        $p = app()->db->fetch("SELECT * FROM pages WHERE slug IN ('datenschutz', 'datenschutzerklaerung', 'privacy') AND status = 'published' ORDER BY id LIMIT 1");
        if ($p && Lang::multi()) $p = Pages::translations($p)[$lang] ?? $p;
        return $p ?: null;
    }

    /**
     * Formular-HTML (theme-neutral, Klassen dff-*). $o: uid, submit (Beschriftung), success (Text nach dem Absenden),
     * values, errors, message, sent, challenge (Token direkt einbetten – nur auf der nicht zwischengespeicherten Formularseite).
     * Für Erweiterungen (eigenes Formular auf Basis der Tabelle, z. B. Buchungen): action (Adresse statt /formular/{handle}; das Skript
     * holt das Token unter {action}/challenge), prepend (fertiges HTML vor den Feldern, z. B. Terminauswahl; Fehler unter data-cf),
     * hidden ([name => wert]), server_message (true: nach dem Absenden die Meldung des Servers zeigen statt des festen Textes),
     * fields_legend (Felder als <fieldset class="dff-set"> mit dieser Überschrift, z. B. „Ihre Angaben“ neben einer eigenen Auswahl),
     * locked ([feld => wert]: Textfeld vorbelegt und schreibgeschützt, z. B. „Stelle“ im Bewerbungsformular einer Stellenseite –
     * den endgültigen Wert setzt der Server, siehe Core\Data\Jobs::applyPost).
     */
    public static function render(array $t, array $o = []): string
    {
        $s = $t['settings']['form'];
        $uid = preg_replace('~[^a-z0-9_-]+~i', '-', (string) ($o['uid'] ?? 'dff-' . $t['handle']));
        $locked = array_map('strval', array_filter((array) ($o['locked'] ?? []), 'is_scalar'));
        $values = $locked + (array) ($o['values'] ?? []);
        $errors = (array) ($o['errors'] ?? []);
        $fields = self::fields($t);
        $inbox = Inbox::is($t);
        $success = trim((string) ($o['success'] ?? '')) ?: self::successText($t);
        if ($inbox && !\Core\FormCrypto::ready()) {
            return '<p class="dff-off" role="note">' . e(lt('Das Online-Formular ist noch nicht eingerichtet. Bitte rufen Sie uns an.')) . '</p>';
        }
        if (!empty($o['sent'])) {
            return '<div class="dff-done" role="status" tabindex="-1"><p>' . e($success) . '</p></div>';
        }
        if (self::full($t)) {
            return '<p class="dff-off" role="note">' . e(lt('Die Anmeldung ist leider ausgebucht – es sind keine Plätze mehr frei.')) . '</p>';
        }
        $btn = app()->theme->def['button_class'] ?? 'btn btn--primary';
        $challenge = $o['challenge'] ?? null;
        $upload = $s['uploads'] && array_filter($fields, fn($f) => in_array($f['type'], self::UPLOAD_TYPES, true));
        $cond = Rules::client($fields);

        $action = (string) ($o['action'] ?? '') !== '' ? (string) $o['action'] : url('/formular/' . $t['handle']);
        $h = '<form class="dff" method="post" action="' . e($action) . '" data-dff novalidate'
            . ($upload ? ' enctype="multipart/form-data"' : '')
            . ($cond ? ' data-cond="' . e(json_encode($cond, JSON_UNESCAPED_UNICODE)) . '"' : '')
            . ' data-success="' . e(!empty($o['server_message']) ? '' : $success) . '" data-failed="' . e(lt('Senden fehlgeschlagen. Bitte prüfen Sie Ihre Verbindung und versuchen Sie es erneut.')) . '"'
            . ' data-check="' . e(lt('Bitte prüfen Sie die markierten Felder.')) . '">';
        // Fehlerübersicht (auch ohne JavaScript): Links zu den Feldern
        $h .= '<div class="dff-summary" role="alert" tabindex="-1" data-dff-summary' . ($errors || !empty($o['message']) ? '' : ' hidden') . '>'
            . '<p class="dff-summary__title">' . e((string) ($o['message'] ?? '') ?: lt('Bitte prüfen Sie die markierten Felder.')) . '</p><ul>';
        foreach ($errors as $n => $msg) {
            $h .= '<li><a href="#' . e($uid . '-' . str_replace('.', '-', (string) $n)) . '">' . e($msg) . '</a></li>';
        }
        $h .= '</ul></div>';
        $h .= '<p class="dff-note">' . e(lt('Felder mit * sind Pflichtfelder.')) . '</p>';
        $h .= (string) ($o['prepend'] ?? '');
        foreach ((array) ($o['hidden'] ?? []) as $hn => $hv) {
            if (preg_match('~^[a-z_][a-z0-9_]{0,40}$~i', (string) $hn)) $h .= '<input type="hidden" name="' . e((string) $hn) . '" value="' . e((string) $hv) . '">';
        }
        $legend = trim((string) ($o['fields_legend'] ?? ''));
        $h .= $legend !== '' ? '<fieldset class="dff-set"><legend class="dff-set__legend">' . e($legend) . '</legend>' : '';
        // Abschnitte (Tables::LAYOUT): je Abschnitt ein <fieldset> mit <legend> (Zwischenüberschrift ohne Rahmen oder Gruppe mit Rahmen),
        // Beschreibung darunter; Freitext an seiner Stelle im Raster. Felder vor dem ersten Abschnitt ohne Fieldset.
        foreach (self::sections(self::items($t)) as [$sec, $items]) {
            if ($sec) {
                $sid = $uid . '-' . $sec['name'];
                $desc = trim((string) ($sec['help'] ?? ''));
                $h .= '<fieldset class="dff-sec dff-sec--' . (($sec['style'] ?? '') === 'fieldset' ? 'fieldset' : 'heading') . '" id="' . e($sid) . '"'
                    . ($desc !== '' ? ' aria-describedby="' . e($sid) . '-d"' : '') . '><legend class="dff-sec__title">' . emphasis(Tables::label($sec)) . '</legend>'
                    . ($desc !== '' ? '<p class="dff-sec__desc" id="' . e($sid) . '-d">' . emphasis($desc) . '</p>' : '');   // Abschnitt: *Wort* wie in Überschriften
            }
            $h .= '<div class="dff-grid">';
            foreach ($items as $f) {
                $h .= match ($f['type']) {
                    'content' => self::contentHtml($f),
                    'group' => self::group(Entries::groupSchema($f, true) + ['label' => Tables::label($f)] + $f, $uid, $values[$f['name']] ?? null, $errors),
                    default => self::field($f, $uid, $values[$f['name']] ?? null, $errors[$f['name']] ?? null, $s, $inbox, isset($locked[$f['name']])),
                };
            }
            $h .= '</div>' . ($sec ? '</fieldset>' : '');
        }
        $h .= $legend !== '' ? '</fieldset>' : '';
        // Datenschutz (Pflicht)
        $pid = $uid . '-' . self::PRIVACY;
        $perr = $errors[self::PRIVACY] ?? null;
        $purl = self::privacyUrl();
        $ptext = e(lt('Ich habe die {link} gelesen.'));
        $plink = $purl !== '' ? '<a href="' . e($purl) . '" target="_blank" rel="noopener"' . \Core\LegalDialog::attrs() . '>' . e(lt('Datenschutzhinweise')) . '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span></a>' : e(lt('Datenschutzhinweise'));
        $h .= '<div class="dff-f dff-f--check dff-f--req" data-cf="' . self::PRIVACY . '"><label class="dff-check"><input type="checkbox" id="' . $pid . '" name="' . self::PRIVACY . '" value="1" required aria-required="true" aria-describedby="' . $pid . '-e"'
            . ($perr ? ' aria-invalid="true"' : '') . (!empty($values[self::PRIVACY]) ? ' checked' : '') . '> <span>' . str_replace('{link}', $plink, $ptext) . ' <span class="dff-req" aria-hidden="true">*</span></span></label>'
            . '<p class="dff-err" id="' . $pid . '-e"' . ($perr ? '' : ' hidden') . '>' . e($perr ?? '') . '</p></div>';
        // Spamschutz: Honeypot, Token, Proof-of-Work
        if (setting('sys.spam_honeypot', true)) {
            $h .= '<div class="dff-hp" aria-hidden="true"><label>Website <input type="text" name="' . SpamGuard::HONEYPOT . '" tabindex="-1" autocomplete="off"></label></div>';
        }
        $h .= '<input type="hidden" name="_token" value="' . e($challenge['token'] ?? '') . '"><input type="hidden" name="_pow" value="">'
            . ($challenge ? '<input type="hidden" name="_difficulty" value="' . (int) $challenge['difficulty'] . '">' : '')
            . (Lang::multi() ? '<input type="hidden" name="_lang" value="' . e(Lang::current()) . '">' : '');
        $submit = trim((string) ($o['submit'] ?? '')) ?: ($inbox ? Inbox::text($t, 'submit') : ($s['submit'] !== '' ? $s['submit'] : ''));
        $h .= '<p class="dff-actions"><button type="submit" class="' . e($btn) . '">' . e($submit !== '' ? $submit : lt('Absenden')) . '</button></p>';
        if ($inbox) $h .= '<p class="dff-note dff-note--secure">' . e(Delivery::mode($t) === 'mail' ? lt('Ihre Angaben werden verschlüsselt übertragen und nicht auf der Website gespeichert.')
            : lt('Ihre Angaben werden verschlüsselt gespeichert und sind nur für uns lesbar.')) . '</p>';
        if (SpamGuard::difficulty() > 0) {
            $h .= '<noscript><p class="dff-note">' . e(lt('Hinweis: Für die Sicherheitsprüfung wird JavaScript benötigt.')) . '</p></noscript>';
        }
        $h .= '</form><div class="dff-done" role="status" tabindex="-1" hidden><p></p></div>';
        return $h . self::script();
    }

    private static bool $script = false;

    /** Skript einmal je Seite (Bedingungen, Proof-of-Work, Absenden ohne Neuladen) – nicht im Bearbeiten-Modus */
    private static function script(): string
    {
        if (self::$script || app()->editing) return '';
        self::$script = true;
        return '<script type="module" src="' . e(asset('js/dataform.js')) . '"></script>';
    }

    private static function field(array $f, string $uid, mixed $v, ?string $err, array $s, bool $inbox = false, bool $locked = false): string
    {
        $n = $f['name'];
        $id = $uid . '-' . $n;
        $type = $f['type'];
        $label = e(Tables::label($f));
        $req = !empty($f['required']);
        $mark = $req || !empty($f['required_if']) ? ' <span class="dff-req" aria-hidden="true">*</span>' : '';
        $help = trim((string) ($f['help'] ?? ''));
        if ($type === 'iban' && $help === '') $help = lt('z. B. DE89 3704 0044 0532 0130 00');
        $kinds = in_array($type, self::UPLOAD_TYPES, true) ? self::fileKinds($f, $inbox) : [];
        // Dateifeld: Hilfetext und automatischer Hinweis (Dateitypen, Größe) als eigene Absätze – beide über aria-describedby verbunden
        $kindHint = $kinds ? self::fileHint($kinds, self::fileMb($f, $s)) : '';
        $desc = ($help !== '' ? "$id-h " : '') . ($kindHint !== '' ? "$id-k " : '') . "$id-e";
        $aria = ' aria-describedby="' . $desc . '"' . ($err ? ' aria-invalid="true"' : '') . ($req ? ' required aria-required="true"' : '');
        $helpHtml = ($help !== '' ? '<p class="dff-help" id="' . $id . '-h">' . e($help) . '</p>' : '')
            . ($kindHint !== '' ? '<p class="dff-help dff-help--file" id="' . $id . '-k">' . e($kindHint) . '</p>' : '');
        $errHtml = '<p class="dff-err" id="' . $id . '-e"' . ($err ? '' : ' hidden') . '>' . e($err ?? '') . '</p>';
        $cls = 'dff-f' . (($f['width'] ?? '') === 'half' ? ' dff-f--half' : '') . ($req ? ' dff-f--req' : '') . ($err ? ' dff-f--error' : '');
        $sv = is_scalar($v) ? (string) $v : '';

        if ($type === 'bool') {
            return '<div class="' . $cls . ' dff-f--check" data-cf="' . e($n) . '"><label class="dff-check"><input type="checkbox" id="' . $id . '" name="' . e($n) . '" value="1"'
                . ($v && $v !== '0' ? ' checked' : '') . $aria . '> <span>' . $label . $mark . '</span></label>' . $helpHtml . $errHtml . '</div>';
        }
        if ($type === 'multiselect') {
            $sel = array_map('strval', is_array($v) ? $v : []);
            $h = '<fieldset class="' . $cls . ' dff-f--multi" id="' . $id . '" data-cf="' . e($n) . '" aria-describedby="' . $desc . '"><legend>' . $label . $mark . '</legend><div class="dff-options">';
            foreach (Fields::options($f) as $k => $l) {
                $h .= '<label class="dff-check"><input type="checkbox" name="' . e($n) . '[]" value="' . e((string) $k) . '"' . (in_array((string) $k, $sel, true) ? ' checked' : '') . '> <span>'
                    . e(Tables::optionLabel($f, (string) $k)) . '</span></label>';
            }
            return $h . '</div>' . $helpHtml . $errHtml . '</fieldset>';
        }
        $auto = match (true) {
            $type === 'email' => 'email',
            $type === 'tel' => 'tel',
            in_array($n, ['name', 'vollstaendiger_name', 'full_name'], true) => 'name',
            in_array($n, ['vorname', 'first_name'], true) => 'given-name',
            in_array($n, ['nachname', 'last_name'], true) => 'family-name',
            in_array($n, ['firma', 'unternehmen', 'company', 'organisation'], true) => 'organization',
            in_array($n, ['strasse', 'adresse', 'street'], true) => 'street-address',
            in_array($n, ['plz', 'postleitzahl', 'zip'], true) => 'postal-code',
            in_array($n, ['ort', 'stadt', 'city'], true) => 'address-level2',
            default => 'off',
        };
        if ($locked && in_array($type, ['text', 'textarea', 'select'], true)) {
            // Vorbelegt und schreibgeschützt (lesbar, fokussierbar, wird mitgesendet) – z. B. die Stelle im Bewerbungsformular
            return '<div class="' . $cls . ' dff-f--locked" data-cf="' . e($n) . '"><label for="' . $id . '">' . $label . '</label>'
                . (mb_strlen($sv) > 60
                    ? '<textarea id="' . $id . '" name="' . e($n) . '" rows="' . min(4, (int) ceil(mb_strlen($sv) / 40)) . '" readonly aria-readonly="true" aria-describedby="' . $id . '-e">' . e($sv) . '</textarea>'
                    : '<input type="text" id="' . $id . '" name="' . e($n) . '" value="' . e($sv) . '" readonly aria-readonly="true" aria-describedby="' . $id . '-e">')
                . '<p class="dff-err" id="' . $id . '-e" hidden></p></div>';
        }
        $control = match ($type) {
            'textarea' => '<textarea id="' . $id . '" name="' . e($n) . '" rows="5" maxlength="5000"' . $aria . '>' . e($sv) . '</textarea>',
            'select' => (function () use ($f, $id, $n, $sv, $aria) {
                $h = '<select id="' . $id . '" name="' . e($n) . '"' . $aria . '><option value="">' . e(lt('– Bitte wählen –')) . '</option>';
                foreach (Fields::options($f) as $k => $l) {
                    $h .= '<option value="' . e((string) $k) . '"' . ((string) $k === $sv ? ' selected' : '') . '>' . e(Tables::optionLabel($f, (string) $k)) . '</option>';
                }
                return $h . '</select>';
            })(),
            'media', 'file' => '<input type="file" id="' . $id . '" name="' . e($n) . '" accept="' . e(self::acceptAttr($kinds)) . '" data-max-bytes="' . (self::fileMb($f, $s) * 1048576) . '"'
                . ' data-too-large="' . e(lt('„{label}“: Die Datei ist größer als {mb} MB.', ['label' => Tables::label($f), 'mb' => self::fileMb($f, $s)])) . '"' . $aria . '>',
            'iban' => '<input type="text" id="' . $id . '" name="' . e($n) . '" value="' . e($sv !== '' ? \Core\Iban::format($sv) : '') . '" maxlength="42" autocomplete="off" spellcheck="false" autocapitalize="characters" data-iban' . $aria . '>',
            'datetime' => '<input type="datetime-local" id="' . $id . '" name="' . e($n) . '" value="' . e(str_replace(' ', 'T', $sv)) . '"' . $aria . '>',
            'color' => '<input type="color" id="' . $id . '" name="' . e($n) . '" value="' . e($sv ?: '#000000') . '"' . $aria . '>',
            default => '<input type="' . match ($type) { 'email' => 'email', 'tel' => 'tel', 'number' => 'number', 'date' => 'date', 'time' => 'time', default => 'text' } . '" id="' . $id . '" name="' . e($n) . '" value="' . e($sv) . '"'
                . ($type === 'number' ? ' step="any" inputmode="decimal"' : '') . (in_array($type, ['text', 'email', 'tel'], true) ? ' maxlength="255" autocomplete="' . $auto . '"' : '') . $aria . '>',
        };
        return '<div class="' . $cls . '" data-cf="' . e($n) . '"><label for="' . $id . '">' . $label . $mark . '</label>' . $helpHtml . $control . $errHtml . '</div>';
    }

    /**
     * Wiederholbare Gruppe als Fieldset: je Zeile ein Fieldset „{item} {n}“ mit den Unterfeldern, „+ …“ und „Entfernen“
     * (resources/js/_group.js). Ohne JavaScript: max(min, 3) Zeilen (höchstens max); leere Zeilen verwirft der Server.
     * $f: Definition des Feldes, ergänzt um Entries::groupSchema($f, true) und label. $errors: alle Fehler des Formulars
     * („feld“ = Anzahl, „feld.zeile.unterfeld“ = Wert). $c: Klassen (Themes, z. B. praxis/partials/form.php).
     */
    public static function group(array $f, string $uid, mixed $v, array $errors, array $c = []): string
    {
        $c +=['f' => 'dff-f', 'half' => 'dff-f--half', 'full' => '', 'req' => 'dff-f--req', 'err' => 'dff-err', 'help' => 'dff-help', 'check' => 'dff-check', 'opt' => ''];
        $n = $f['name'];
        $id = $uid . '-' . $n;
        $item = (string) $f['item_label'];
        $min = (int) $f['min'];
        $max = max(1, (int) $f['max']);
        $rows = is_array($v) ? array_values(array_filter($v, 'is_array')) : [];
        $count = $rows ? min(max(count($rows), $min, 1), max($max, count($rows))) : min(max($min, 3), $max);
        $req = !empty($f['required']) || $min > 0;
        $err = $errors[$n] ?? null;
        $help = trim((string) ($f['help'] ?? ''));
        if ($help !== '') $help = lt($help);                          // Hilfetext der Definition, übersetzbar über die Website-Texte des Kits
        $mark = fn(bool $r) => $r ? ($c['opt'] === '' ? ' <span class="dff-req" aria-hidden="true">*</span>' : '') : ($c['opt'] !== '' ? ' <span class="' . e($c['opt']) . '">' . e(lt('(optional)')) . '</span>' : '');
        $h = '<fieldset class="' . e($c['f'] . ' ' . $c['full'] . ($req ? ' ' . $c['req'] : '')) . ' dff-group" id="' . $id . '" data-cf="' . e($n) . '" data-group="' . e($n) . '"'
            . ' data-rows-min="' . max($min, 1) . '" data-rows-max="' . $max . '" data-say="' . e(lt('{item} {n} hinzugefügt.', ['item' => $item]) . '|' . lt('{item} {n} entfernt.', ['item' => $item])) . '"'
            . ' aria-describedby="' . ($help !== '' ? "$id-h " : '') . "$id-e" . '">'
            . '<legend>' . e((string) $f['label']) . $mark($req) . '</legend>'
            . ($help !== '' ? '<p class="' . e($c['help']) . '" id="' . $id . '-h">' . e($help) . '</p>' : '')
            . '<p class="' . e($c['err']) . '" id="' . $id . '-e"' . ($err ? '' : ' hidden') . '>' . e($err ?? '') . '</p><div class="dff-rows" data-rows>';
        $js = $rows ? $count : max($min, 1);                           // weitere leere Zeilen nur ohne JavaScript (<noscript>)
        for ($i = 0; $i < $count; $i++) {
            $row = (array) ($rows[$i] ?? []);
            if ($i === $js) $h .= '<noscript>';
            $h .= '<fieldset class="dff-row" data-row><legend class="dff-row__t">' . e($item) . ' <span data-row-n>' . ($i + 1) . '</span></legend><div class="dff-row__grid">';
            foreach ($f['fields'] as $sf) {
                $sn = $sf['name'];
                $sid = "$id-$i-$sn";
                $name = e($n) . '[' . $i . '][' . e($sn) . ']';
                $sv = is_scalar($row[$sn] ?? null) ? (string) $row[$sn] : '';
                $se = $errors["$n.$i.$sn"] ?? null;
                $sreq = !empty($sf['required']);
                $aria = ' aria-describedby="' . $sid . '-e"' . ($se ? ' aria-invalid="true"' : '') . ($sreq ? ' required aria-required="true"' : '');
                $wrap = trim($c['f'] . ' ' . (($sf['width'] ?? '') === 'half' ? $c['half'] : $c['full']) . ($sreq ? ' ' . $c['req'] : ''));
                $errP = '<p class="' . e($c['err']) . '" id="' . $sid . '-e"' . ($se ? '' : ' hidden') . '>' . e($se ?? '') . '</p>';
                if ($sf['type'] === 'bool') {
                    $h .= '<div class="' . e($wrap) . '" data-ef="' . e("$n.$i.$sn") . '"><label class="' . e($c['check']) . '"><input type="checkbox" id="' . $sid . '" name="' . $name . '" value="1"'
                        . ($sv !== '' && $sv !== '0' ? ' checked' : '') . $aria . '> <span>' . e($sf['label']) . $mark($sreq) . '</span></label>' . $errP . '</div>';
                    continue;
                }
                $ctl = match ($sf['type']) {
                    'textarea' => '<textarea id="' . $sid . '" name="' . $name . '" rows="2" maxlength="1000"' . $aria . '>' . e($sv) . '</textarea>',
                    'select' => '<select id="' . $sid . '" name="' . $name . '"' . $aria . '><option value="">' . e(lt('– Bitte wählen –')) . '</option>'
                        . implode('', array_map(fn($k, $l) => '<option value="' . e((string) $k) . '"' . ((string) $k === $sv ? ' selected' : '') . '>' . e((string) $l) . '</option>', array_keys($sf['options'] ?? []), $sf['options'] ?? []))
                        . '</select>',
                    'iban' => '<input type="text" id="' . $sid . '" name="' . $name . '" value="' . e($sv !== '' ? \Core\Iban::format($sv) : '') . '" maxlength="42" autocomplete="off" spellcheck="false" autocapitalize="characters" data-iban' . $aria . '>',
                    default => '<input type="' . match ($sf['type']) { 'email' => 'email', 'tel' => 'tel', 'number' => 'number', 'date' => 'date', default => 'text' } . '" id="' . $sid . '" name="' . $name . '" value="' . e($sv) . '"'
                        . ($sf['type'] === 'number' ? ' step="any" inputmode="decimal"' : ' maxlength="255"') . ' autocomplete="off"' . $aria . '>',
                };
                $h .= '<div class="' . e($wrap) . '" data-ef="' . e("$n.$i.$sn") . '"><label for="' . $sid . '">' . e($sf['label']) . $mark($sreq) . '</label>' . $ctl . $errP . '</div>';
            }
            $h .= '</div><button type="button" class="dff-row__rm" data-row-rm hidden>' . e(lt('Entfernen')) . '<span class="sr-only"> ' . e($item) . ' <span data-row-n>' . ($i + 1) . '</span></span></button></fieldset>';
        }
        if ($count > $js) $h .= '</noscript>';
        $add = (string) $f['add_label'] !== '' ? (string) $f['add_label'] : lt('{item} hinzufügen', ['item' => $item]);
        return $h . '</div><button type="button" class="dff-row__add" data-row-add hidden><span aria-hidden="true">+</span> ' . e($add) . '</button>'
            . '<p class="sr-only" aria-live="polite" data-live></p></fieldset>';
    }

    /** Einstellung „Eingangsbestätigung“ prüfen: E-Mail-Feld muss es geben, Texte gekürzt, ohne HTML */
    private static function validateReceipt(array $r, array $fields, array &$errors): array
    {
        $mails = array_column(array_filter($fields, fn($f) => ($f['type'] ?? '') === 'email'), 'name');
        $enabled = !empty($r['enabled']);
        // Erstes E-Mail-Feld nur vorbelegen, wenn die Bestätigung an ist: ausgeschaltet bleibt „kein Feld“ (Standard, auch für Tabellen
        // ohne gespeicherte Einstellung – Tables::find ergänzt RECEIPT) erhalten, sonst wäre validate(toInput($t)) nicht stabil.
        $out = [
            'enabled' => $enabled,
            'field' => in_array((string) ($r['field'] ?? ''), $mails, true) ? (string) $r['field'] : ($enabled ? ($mails[0] ?? '') : ''),
            'subject' => mb_substr(trim(strip_tags((string) ($r['subject'] ?? ''))), 0, 150),
            'text' => mb_substr(trim(strip_tags((string) ($r['text'] ?? ''))), 0, 3000),
            'include' => !empty($r['include']),
        ];
        if ($out['enabled'] && $out['field'] === '') $errors['settings.form'] = __('Eingangsbestätigung: Die Tabelle braucht ein Feld vom Typ „E-Mail“.');
        return $out;
    }

    /**
     * Eingangsbestätigung an die angegebene Adresse (Einstellung „receipt“) – nach dem erfolgreichen Speichern bzw. Zustellen.
     * Mit „include“ stehen die eigenen Angaben in der E-Mail (Text, Zeitpunkt des Eingangs, Website) – sonst nur Text und Zeitpunkt.
     */
    private static function sendReceipt(array $t, array $values, array $fields): void
    {
        $r = (array) ($t['settings']['form']['receipt'] ?? []) + self::RECEIPT;
        if (!$r['enabled'] || $r['field'] === '') return;
        $to = trim((string) ($values[$r['field']] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return;
        $name = (string) (setting((string) project('name_setting', 'org_name')) ?: site()->label());
        $subject = $r['subject'] !== '' ? lt($r['subject']) : lt('Eingangsbestätigung: {name}', ['name' => (string) ($t['singular'] ?? $t['name'])]);
        $body = lt('Guten Tag,') . "\n\n" . ($r['text'] !== '' ? lt($r['text']) : lt('wir bestätigen den Eingang Ihrer Angaben.')) . "\n\n"
            . lt('Eingegangen am {date} um {time} Uhr.', ['date' => date_local(time(), 'short'), 'time' => date('H:i')]) . "\n";
        if ($r['include']) {
            $lines = [];
            foreach ($fields as $f) {
                $n = (string) ($f['name'] ?? '');
                if ($n === '' || !array_key_exists($n, $values) || in_array($f['type'] ?? '', ['file', 'media', 'group'], true)) continue;
                $v = $values[$n];
                $v = match ($f['type'] ?? '') {
                    'bool' => $v ? lt('Ja') : lt('Nein'),
                    'select' => Tables::optionLabel($f, (string) $v),
                    'multiselect' => implode(', ', array_map(fn($k) => Tables::optionLabel($f, (string) $k), (array) $v)),
                    default => trim((string) (is_array($v) ? implode(', ', $v) : $v)),
                };
                if ($v !== '') $lines[] = Tables::label($f) . ': ' . $v;
            }
            if ($lines) $body .= "\n" . lt('Ihre Angaben:') . "\n" . implode("\n", $lines) . "\n";
        }
        $body .= "\n" . lt('Mit freundlichen Grüßen') . "\n" . $name . "\n" . absolute_url('/') . "\n";
        $err = Mailer::send($subject, $body, [$to]);
        if ($err !== null) error_log('[Formular ' . $t['handle'] . '] Eingangsbestätigung nicht gesendet: ' . $err);
    }

    // ================================================================= Annahme

    /**
     * Einsendung prüfen und als Eintrag speichern.
     * $o (Erweiterungen mit eigenem Formular auf Basis einer Eingangs-Tabelle, z. B. Buchungen):
     *   check  => fn(array $values): array   weitere Fehler [name => Text] nach der Feldprüfung, vor dem Spamschutz (Token bleibt gültig)
     *   store  => fn(array $values): array   speichert statt Inbox::store (selbst versiegeln, z. B. in einer Transaktion mit Inbox::store) –
     *                                        Rückgabe ['id' => …, 'message' => …] bzw. ['error' => Text] (abgelehnt, ohne Feldfehler)
     *   notify => false                      keine Benachrichtigung des Cores (die Erweiterung schickt eigene E-Mails) – die Zustellung
     *                                        mit Inhalt (Delivery, Modus „System und E-Mail“) läuft trotzdem
     * @return array{ok: bool, message?: string, errors?: array, id?: int, stored?: array}
     */
    public static function submit(array $t, array $post, array $files, string $ip, array $o = []): array
    {
        if (!self::enabled($t, !isset($o['store']))) {
            return ['ok' => false, 'message' => lt('Dieses Formular ist derzeit nicht verfügbar.')];
        }
        $inbox = Inbox::is($t);
        if ($inbox && !\Core\FormCrypto::ready()) {
            return ['ok' => false, 'message' => lt('Das Online-Formular ist noch nicht eingerichtet. Bitte rufen Sie uns an.')];
        }
        $s = $t['settings']['form'];
        $fields = self::fields($t);
        $success = self::successText($t);
        // Nur Felder des Formulars annehmen; Bild-/Dateifelder nie als ID (nur als hochgeladene Datei)
        $in = [];
        foreach ($fields as $f) {
            if (!in_array($f['type'], self::UPLOAD_TYPES, true) && array_key_exists($f['name'], $post)) $in[$f['name']] = $post[$f['name']];
        }
        $errors = [];
        $uploads = [];
        foreach ($fields as $f) {
            if (!in_array($f['type'], self::UPLOAD_TYPES, true)) continue;
            [$file, $err] = self::checkUpload($f, $files[$f['name']] ?? null, self::fileMb($f, $s), self::fileKinds($f, $inbox));
            if ($err) $errors[$f['name']] = $err;
            if ($file) { $uploads[$f['name']] = $file; $in[$f['name']] = '0'; }   // Platzhalter für die Pflichtprüfung
        }
        $schema = [];
        foreach ($fields as $f) {
            $def = ['name' => $f['name'], 'label' => Tables::label($f), 'type' => $f['type'], 'required' => $f['required'], 'options' => $f['options'] ?? []];
            if (in_array($f['type'], ['text', 'email', 'tel'], true)) $def['max'] = 255;
            if ($f['type'] === 'textarea') $def['max'] = 5000;
            if ($f['type'] === 'group') $def = Entries::groupSchema($f, true) + $def;
            $schema[] = $def;
        }
        $schema[] = ['name' => self::PRIVACY, 'label' => lt('Datenschutzhinweise'), 'type' => 'bool', 'required' => true];
        $in[self::PRIVACY] = $post[self::PRIVACY] ?? '';
        Fields::$site = true;
        try {
            [$values, $err2] = Fields::sanitize($schema, $in);
            $labelled = array_map(fn($f) => ['label' => Tables::label($f)] + $f, $fields);
            [$values, $err2] = Rules::apply($labelled, $values, $err2);
        } finally {
            Fields::$site = false;
        }
        $errors += $err2;
        if (is_callable($o['check'] ?? null)) {
            $values2 = $values;
            unset($values2[self::PRIVACY]);
            $errors += (array) ($o['check'])($values2);
        }
        foreach ($uploads as $n => $_) {
            if (!array_key_exists($n, $values) || $values[$n] === null) unset($uploads[$n]);   // Feld ausgeblendet → Datei verwerfen
        }
        $mailFiles = [];
        if ($inbox && $uploads && !$errors) {
            // Eingang mit Zustellung per E-Mail: Dateien nur im Speicher (Anhang bzw. versiegelt), Gesamtgrenze der E-Mail
            foreach ($uploads as $n => $file) {
                $mailFiles[$n] = ['name' => (string) ($file['name'] ?? 'datei'), 'size' => (int) ($file['size'] ?? 0),
                    'type' => (string) $file['mime'], 'data' => (string) file_get_contents($file['tmp_name'])];
            }
            if ($msg = Delivery::tooLarge($t, $mailFiles)) {
                foreach ($uploads as $n => $_) $errors[$n] = $msg;
            }
        }
        if ($errors) {
            if (isset($errors[self::PRIVACY])) $errors[self::PRIVACY] = lt('Bitte bestätigen Sie, dass Sie die Datenschutzhinweise gelesen haben.');
            foreach ($uploads as $n => $_) $errors[$n] ??= lt('Bitte wählen Sie die Datei erneut aus.');
            // Reihenfolge wie im Formular (Fehlerübersicht)
            $order = array_flip([...array_column($fields, 'name'), self::PRIVACY]);
            $pos = fn($k) => $order[explode('.', (string) $k)[0]] ?? -1;           // Zeilen einer Gruppe („feld.1.unterfeld“) beim Feld; Fehler aus 'check' (prepend) zuerst
            uksort($errors, fn($a, $b) => $pos($a) <=> $pos($b));
            return ['ok' => false, 'errors' => $errors, 'message' => lt('Bitte prüfen Sie die markierten Felder.')];
        }

        // Spamschutz erst nach der Feldprüfung – so bleibt das Token bei Eingabefehlern gültig (wie Core\Forms)
        $spam = SpamGuard::check(self::key($t), $post, $ip);
        if (!$spam['ok']) {
            return !empty($spam['silent']) ? ['ok' => true, 'message' => $success] : ['ok' => false, 'message' => $spam['error']];
        }

        unset($values[self::PRIVACY]);
        if ($inbox) {
            // Eingang: Werte (nach Prüfung und Bedingungen) versiegeln – Klartext erreicht die Datenbank nie – und/oder mit vollem Inhalt
            // per E-Mail zustellen (Core\Data\Delivery: Modus system | both | mail, Rückfall = verschlüsselt sichern)
            $acc = Delivery::accept($t, $values, $mailFiles, is_callable($o['store'] ?? null) ? $o['store'] : null);
            if (!$acc['ok']) return ['ok' => false, 'message' => (string) ($acc['error'] ?? lt('Senden fehlgeschlagen.'))];
            $stored = (array) ($acc['stored'] ?? []);
            $id = (int) $acc['id'];
            self::sendReceipt($t, $values, $fields);
            if (($o['notify'] ?? true) === false) return ['ok' => true, 'message' => (string) ($stored['message'] ?? $success), 'id' => $id, 'stored' => $stored];
            // Push-Mitteilung an die Redaktion (Konto → Benachrichtigungen, ohne Inhalte; Core\Push)
            \Core\Push\Hooks::request($t);
            // Inhalt per E-Mail zugestellt (bzw. Rückfall mit eigener Warnung) → keine zusätzliche inhaltsfreie Benachrichtigung
            if (Delivery::mode($t) !== 'system') return ['ok' => true, 'message' => $success, 'id' => $id];
            $to = $s['notify'] !== '' ? array_map('trim', explode(',', $s['notify'])) : null;
            Mailer::send('Neue Anfrage: ' . $t['name'],
                "Guten Tag,\n\nüber die Website ist eine neue Anfrage in „{$t['name']}“ eingegangen.\n"
                . 'Die Inhalte sind verschlüsselt gespeichert und können nur im Verwaltungsbereich mit dem ' . term('key') . " gelesen werden:\n\n"
                . absolute_url('/admin/requests?table=' . $t['handle']) . "\n\nDiese E-Mail enthält aus Datenschutzgründen keine Inhalte.\n", $to);
            return ['ok' => true, 'message' => $success, 'id' => (int) $id];
        }
        // Obergrenze: nach der Prüfung, damit Eingaben nicht verloren gehen, wenn gerade der letzte Platz vergeben wurde
        if (self::full($t)) {
            return ['ok' => false, 'message' => lt('Die Anmeldung ist leider ausgebucht – es sind keine Plätze mehr frei.')];
        }
        foreach ($uploads as $n => $file) {
            [$m, $err] = Media::import($file['tmp_name'], (string) ($file['name'] ?? 'datei'), '', [
                'require_alt' => false, 'tags' => 'formular', 'title' => mb_substr($t['singular'] . ': ' . basename((string) ($file['name'] ?? '')), 0, 180),
            ]);
            if (!$m) return ['ok' => false, 'errors' => [$n => lt('Die Datei konnte nicht gespeichert werden.')], 'message' => lt('Bitte prüfen Sie die markierten Felder.')];
            $values[$n] = (int) $m['id'];
        }
        $values['status'] = $s['status'] === 'published' ? 'published' : 'draft';
        if (Lang::multi()) $values['lang'] = Lang::current();
        Fields::$site = true;
        try {
            [$id, $saveErrors] = Entries::save($t, null, $values);
        } finally {
            Fields::$site = false;
        }
        if ($saveErrors) {
            return ['ok' => false, 'errors' => array_intersect_key($saveErrors, array_flip(array_column($fields, 'name'))), 'message' => lt('Bitte prüfen Sie die markierten Felder.')];
        }

        self::sendReceipt($t, $values, $fields);
        \Core\Push\Hooks::formEntry($t, (int) $id, (string) $values['status']);   // Push an die Redaktion (Core\Push)
        // Benachrichtigung OHNE Inhalte – nur Tabelle und Link zur Verwaltung
        $to = $s['notify'] !== '' ? array_map('trim', explode(',', $s['notify'])) : null;
        Mailer::send('Neuer Eintrag in ' . $t['name'],
            "Guten Tag,\n\nüber das Formular auf der Website ist ein neuer Eintrag in „{$t['name']}“ eingegangen"
            . ($values['status'] === 'draft' ? " (Entwurf – bitte prüfen und freigeben)" : '') . ":\n\n"
            . absolute_url('/admin/data/' . $t['handle'] . '/' . $id) . "\n\nDiese E-Mail enthält aus Datenschutzgründen keine Inhalte.\n", $to);
        return ['ok' => true, 'message' => $success, 'id' => (int) $id];
    }

    /**
     * @param list<string> $kinds erlaubte Dateitypen (FILE_KINDS)
     * @return array{0: ?array, 1: ?string} [Datei (+ mime: erkannter Typ), Fehler] – nichts gewählt = [null, null]
     */
    private static function checkUpload(array $f, mixed $file, int $mb, array $kinds): array
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') return [null, null];
        $label = Tables::label($f);
        if (($file['error'] ?? 1) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 1) === UPLOAD_ERR_FORM_SIZE || ($file['size'] ?? 0) > $mb * 1024 * 1024) {
            return [null, lt('„{label}“: Die Datei ist größer als {mb} MB.', ['label' => $label, 'mb' => $mb])];
        }
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return [null, lt('„{label}“: Die Datei konnte nicht hochgeladen werden.', ['label' => $label])];
        }
        if (filesize($file['tmp_name']) > $mb * 1024 * 1024) {
            return [null, lt('„{label}“: Die Datei ist größer als {mb} MB.', ['label' => $label, 'mb' => $mb])];
        }
        $mime = self::sniff((string) $file['tmp_name'], (string) $file['name'], $kinds);
        if ($mime === null) {
            return [null, lt('„{label}“: Dieser Dateityp ist nicht erlaubt.', ['label' => $label]) . ' ' . self::fileHint($kinds, $mb)];
        }
        return [$file + ['mime' => $mime], null];
    }

    /**
     * Dateityp am Inhalt erkennen (finfo) und mit der Endung abgleichen – das accept-Attribut ist nur ein Hinweis für den Dialog.
     * DOCX/ODT: ZIP mit [Content_Types].xml (Word-Dokument, keine Makros, kein vbaProject.bin) bzw. mimetype
     * „application/vnd.oasis.opendocument.text“ (keine Basic-/Scripts-Makros). Rückgabe: MIME-Typ für den Anhang oder null (abgelehnt).
     */
    public static function sniff(string $path, string $name, array $kinds): ?string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        foreach ($kinds as $k) {
            $def = self::FILE_KINDS[$k] ?? null;
            if (!$def || !in_array($ext, $def['ext'], true)) continue;
            $ok = match ($k) {
                'pdf' => $mime === 'application/pdf' && str_starts_with(ltrim((string) file_get_contents($path, false, null, 0, 1024)), '%PDF-'),
                'image' => in_array($mime, $def['mimes'], true) && @getimagesize($path) !== false,
                'docx', 'odt' => in_array($mime, [...$def['mimes'], 'application/zip', 'application/octet-stream'], true) && self::officeOk($path, $k),
                default => false,
            };
            if ($ok) return $k === 'image' ? $mime : $def['mimes'][0];
        }
        return null;
    }

    /** ZIP-Container eines Office-Dokuments prüfen (ohne zu entpacken; nur kleine Steuerdateien werden gelesen) */
    private static function officeOk(string $path, string $kind): bool
    {
        if (!class_exists(\ZipArchive::class)) return false;
        $z = new \ZipArchive();
        if ($z->open($path, \ZipArchive::RDONLY) !== true) return false;
        try {
            if ($z->numFiles < 1 || $z->numFiles > 5000) return false;
            for ($i = 0; $i < $z->numFiles; $i++) {
                $n = strtolower((string) $z->getNameIndex($i));
                // Makros: Word (vbaProject.bin, auch umbenannte .docm), OpenDocument (Basic/, Scripts/)
                if (str_ends_with($n, 'vbaproject.bin') || str_starts_with($n, 'basic/') || str_starts_with($n, 'scripts/')) return false;
            }
            if ($kind === 'odt') {
                return trim((string) $z->getFromName('mimetype', 200)) === 'application/vnd.oasis.opendocument.text';
            }
            $ct = (string) $z->getFromName('[Content_Types].xml', 256 * 1024);
            return str_contains($ct, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml')
                && !preg_match('~macroEnabled|vbaProject~i', $ct);
        } finally {
            $z->close();
        }
    }
}
