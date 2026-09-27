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
 * gleiche Prüfung, danach Ende-zu-Ende verschlüsselt über Core\Data\Inbox::store (ohne öffentlichen Schlüssel kein Formular), keine Uploads.
 */
final class DataForms
{
    public const DEFAULTS = ['enabled' => false, 'fields' => [], 'status' => 'draft', 'notify' => '', 'success' => '', 'submit' => '',
        'uploads' => false, 'upload_mb' => 5];
    /** Feldtypen, die Besucher ausfüllen können (Verknüpfungen, Rich-Text, Karte, Links und Wiederholungen bleiben der Redaktion vorbehalten) */
    public const TYPES = ['text', 'textarea', 'number', 'bool', 'date', 'datetime', 'time', 'select', 'multiselect', 'email', 'tel', 'color', 'iban', 'group'];
    public const UPLOAD_TYPES = ['media', 'file'];
    public const PRIVACY = '_privacy';
    private const UPLOAD_MIMES = ['media' => ['image/jpeg', 'image/png', 'image/webp'], 'file' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf']];

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

    /** Text nach dem Absenden: Block/Tabelle, bei Eingangs-Tabellen sonst der Text des Theme-Formulars */
    public static function successText(array $t): string
    {
        if (Inbox::is($t)) return Inbox::text($t, 'success');
        $s = $t['settings']['form'];
        return $s['success'] !== '' ? $s['success'] : lt('Vielen Dank – Ihre Angaben sind eingegangen.');
    }

    /** Darf der Feldtyp ins öffentliche Formular? */
    public static function eligible(array $f, bool $uploads): bool
    {
        return in_array($f['type'], self::TYPES, true) || ($uploads && in_array($f['type'], self::UPLOAD_TYPES, true));
    }

    /** Felder des Formulars (Reihenfolge wie in der Tabelle): gewählte + alle Pflichtfelder; nichts gewählt = alle geeigneten */
    public static function fields(array $t): array
    {
        $s = $t['settings']['form'];
        $sel = (array) ($s['fields'] ?? []);
        $uploads = !empty($s['uploads']) && !Inbox::is($t);         // Eingang: nie Uploads (Gesundheitsdaten dürfen nicht in die öffentliche Mediathek)
        return array_values(array_filter($t['fields'], fn($f) => self::eligible($f, $uploads)
            && (!$sel || in_array($f['name'], $sel, true) || !empty($f['required']))));
    }

    // ================================================================= Einstellungen (Tables::validate)

    public static function validateSettings(array $s, array $fields, array &$errors, ?array $existing): array
    {
        $existing = ($existing ?? []) + self::DEFAULTS;
        if (!$s) return $existing;                                 // z. B. API ohne Formular-Angaben: unverändert
        $uploads = !empty($s['uploads']);
        $eligible = array_column(array_filter($fields, fn($f) => self::eligible($f, $uploads)), 'name');
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
            'upload_mb' => max(1, min(10, (int) ($s['upload_mb'] ?? 5))),
        ];
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
     * fields_legend (Felder als <fieldset class="dff-set"> mit dieser Überschrift, z. B. „Ihre Angaben“ neben einer eigenen Auswahl).
     */
    public static function render(array $t, array $o = []): string
    {
        $s = $t['settings']['form'];
        $uid = preg_replace('~[^a-z0-9_-]+~i', '-', (string) ($o['uid'] ?? 'dff-' . $t['handle']));
        $values = (array) ($o['values'] ?? []);
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
        $h .= ($legend !== '' ? '<fieldset class="dff-set"><legend class="dff-set__legend">' . e($legend) . '</legend>' : '') . '<div class="dff-grid">';
        foreach ($fields as $f) {
            $h .= $f['type'] === 'group'
                ? self::group(Entries::groupSchema($f, true) + ['label' => Tables::label($f)] + $f, $uid, $values[$f['name']] ?? null, $errors)
                : self::field($f, $uid, $values[$f['name']] ?? null, $errors[$f['name']] ?? null, $s);
        }
        $h .= '</div>' . ($legend !== '' ? '</fieldset>' : '');
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
        if ($inbox) $h .= '<p class="dff-note dff-note--secure">' . e(lt('Ihre Angaben werden verschlüsselt gespeichert und sind nur für uns lesbar.')) . '</p>';
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

    private static function field(array $f, string $uid, mixed $v, ?string $err, array $s): string
    {
        $n = $f['name'];
        $id = $uid . '-' . $n;
        $type = $f['type'];
        $label = e(Tables::label($f));
        $req = !empty($f['required']);
        $mark = $req || !empty($f['required_if']) ? ' <span class="dff-req" aria-hidden="true">*</span>' : '';
        $help = trim((string) ($f['help'] ?? ''));
        if ($type === 'iban' && $help === '') $help = lt('z. B. DE89 3704 0044 0532 0130 00');
        if (in_array($type, self::UPLOAD_TYPES, true)) {
            $help = trim($help . ' ' . lt('{types}, höchstens {mb} MB.', ['types' => $type === 'media' ? 'JPG, PNG, WebP' : 'PDF, JPG, PNG, WebP', 'mb' => $s['upload_mb']]));
        }
        $desc = ($help !== '' ? "$id-h " : '') . "$id-e";
        $aria = ' aria-describedby="' . $desc . '"' . ($err ? ' aria-invalid="true"' : '') . ($req ? ' required aria-required="true"' : '');
        $helpHtml = $help !== '' ? '<p class="dff-help" id="' . $id . '-h">' . e($help) . '</p>' : '';
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
        $control = match ($type) {
            'textarea' => '<textarea id="' . $id . '" name="' . e($n) . '" rows="5" maxlength="5000"' . $aria . '>' . e($sv) . '</textarea>',
            'select' => (function () use ($f, $id, $n, $sv, $aria) {
                $h = '<select id="' . $id . '" name="' . e($n) . '"' . $aria . '><option value="">' . e(lt('– Bitte wählen –')) . '</option>';
                foreach (Fields::options($f) as $k => $l) {
                    $h .= '<option value="' . e((string) $k) . '"' . ((string) $k === $sv ? ' selected' : '') . '>' . e(Tables::optionLabel($f, (string) $k)) . '</option>';
                }
                return $h . '</select>';
            })(),
            'media', 'file' => '<input type="file" id="' . $id . '" name="' . e($n) . '" accept="' . e(implode(',', self::UPLOAD_MIMES[$type])) . '"' . $aria . '>',
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

    // ================================================================= Annahme

    /**
     * Einsendung prüfen und als Eintrag speichern.
     * $o (Erweiterungen mit eigenem Formular auf Basis einer Eingangs-Tabelle, z. B. Buchungen):
     *   check  => fn(array $values): array   weitere Fehler [name => Text] nach der Feldprüfung, vor dem Spamschutz (Token bleibt gültig)
     *   store  => fn(array $values): array   speichert statt Inbox::store (selbst versiegeln, z. B. in einer Transaktion mit Inbox::store) –
     *                                        Rückgabe ['id' => …, 'message' => …] bzw. ['error' => Text] (abgelehnt, ohne Feldfehler)
     *   notify => false                      keine Benachrichtigung des Cores (die Erweiterung schickt eigene E-Mails)
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
            [$file, $err] = self::checkUpload($f, $files[$f['name']] ?? null, (int) $s['upload_mb']);
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
            // Eingang: Werte (nach Prüfung und Bedingungen) versiegeln – Klartext erreicht die Datenbank nie
            if (is_callable($o['store'] ?? null)) {
                $stored = (array) ($o['store'])($values);
                if (!empty($stored['error'])) return ['ok' => false, 'message' => (string) $stored['error']];
                $id = (int) ($stored['id'] ?? 0);
            } else {
                ['id' => $id] = Inbox::store($t, $values);
                $stored = ['id' => $id];
            }
            if (($o['notify'] ?? true) === false) return ['ok' => true, 'message' => (string) ($stored['message'] ?? $success), 'id' => (int) $id, 'stored' => $stored];
            $to = $s['notify'] !== '' ? array_map('trim', explode(',', $s['notify'])) : null;
            Mailer::send('Neue Anfrage: ' . $t['name'],
                "Guten Tag,\n\nüber die Website ist eine neue Anfrage in „{$t['name']}“ eingegangen.\n"
                . 'Die Inhalte sind verschlüsselt gespeichert und können nur im Verwaltungsbereich mit dem ' . term('key') . " gelesen werden:\n\n"
                . absolute_url('/admin/requests?table=' . $t['handle']) . "\n\nDiese E-Mail enthält aus Datenschutzgründen keine Inhalte.\n", $to);
            return ['ok' => true, 'message' => $success, 'id' => (int) $id];
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

        // Benachrichtigung OHNE Inhalte – nur Tabelle und Link zur Verwaltung
        $to = $s['notify'] !== '' ? array_map('trim', explode(',', $s['notify'])) : null;
        Mailer::send('Neuer Eintrag in ' . $t['name'],
            "Guten Tag,\n\nüber das Formular auf der Website ist ein neuer Eintrag in „{$t['name']}“ eingegangen"
            . ($values['status'] === 'draft' ? " (Entwurf – bitte prüfen und freigeben)" : '') . ":\n\n"
            . absolute_url('/admin/data/' . $t['handle'] . '/' . $id) . "\n\nDiese E-Mail enthält aus Datenschutzgründen keine Inhalte.\n", $to);
        return ['ok' => true, 'message' => $success, 'id' => (int) $id];
    }

    /** @return array{0: ?array, 1: ?string} [Datei, Fehler] – nichts gewählt = [null, null] */
    private static function checkUpload(array $f, mixed $file, int $mb): array
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['name'] ?? '') === '') return [null, null];
        $label = Tables::label($f);
        if (($file['error'] ?? 1) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 1) === UPLOAD_ERR_FORM_SIZE || ($file['size'] ?? 0) > $mb * 1024 * 1024) {
            return [null, lt('„{label}“: Die Datei ist größer als {mb} MB.', ['label' => $label, 'mb' => $mb])];
        }
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return [null, lt('„{label}“: Die Datei konnte nicht hochgeladen werden.', ['label' => $label])];
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        if (!in_array($mime, self::UPLOAD_MIMES[$f['type']], true)) {
            return [null, lt('„{label}“: Dieser Dateityp ist nicht erlaubt.', ['label' => $label])];
        }
        return [$file, null];
    }
}
