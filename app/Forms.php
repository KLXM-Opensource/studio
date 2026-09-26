<?php
declare(strict_types=1);

namespace Core;

use Core\Data\DataForms;
use Core\Data\Inbox;
use Core\Data\Tables;

/**
 * Theme-Formulare (theme.php → 'forms', z. B. Rezept / Überweisung) – Kompatibilitätsschicht für Themes.
 *
 * Seit der Umstellung auf Eingangs-Tabellen (Core\Data\Inbox) ist 'forms' nur noch eine Vorlage: Beim ersten Start
 * entsteht je Formular eine verschlüsselte Eingangs-Tabelle (Felder aus dem Repeater der Einstellungen). Diese Klasse
 * bildet die alte API (enabled, mode, externalUrl, fields, submit) auf diese Tabelle ab – damit bestehende
 * Theme-Vorlagen (Service-Kacheln, form-page, partials/form) und die Adressen /anfrage/{form} unverändert funktionieren.
 * Der Kern selbst nutzt sie nicht mehr.
 */
final class Forms
{
    /** Name der Datenschutz-Checkbox in Theme-Formularen (wird auf DataForms::PRIVACY abgebildet) */
    public const PRIVACY_FIELD = 'datenschutz';

    /** Eingangs-Tabelle des Formulars */
    public static function table(string $key): ?array
    {
        return Inbox::forForm($key);
    }

    /** Theme-Definition, ergänzt um Titel/Einleitung/Erfolgstext der Tabelle (übersetzt) */
    public static function def(string $key): ?array
    {
        $d = app()->theme->forms()[$key] ?? null;
        if (!$d) return null;
        if ($t = self::table($key)) {
            $d['label'] = Inbox::text($t, 'title');
            $d['intro'] = Inbox::text($t, 'intro');
            $d['success'] = Inbox::text($t, 'success');
            $d['table'] = $t['handle'];
        }
        return $d;
    }

    /** Angeboten? Schalter des Themes (z. B. rezept_aktiv) + Funktion „requests“ + (intern) Tabelle mit eingeschaltetem Formular */
    public static function enabled(string $key): bool
    {
        $d = app()->theme->forms()[$key] ?? null;
        if (!$d || !Inbox::available() || (isset($d['enabled']) && !app()->settings->get($d['enabled']))) return false;
        if (self::mode($key) === 'external') return self::externalUrl($key) !== '';
        $t = self::table($key);
        return $t !== null && DataForms::enabled($t);
    }

    /** 'internal' (eigenes Formular) oder 'external' (Link zu Dienst) */
    public static function mode(string $key): string
    {
        $d = app()->theme->forms()[$key] ?? null;
        return $d && !empty($d['mode']) ? (string) app()->settings->get($d['mode'], 'internal') : 'internal';
    }

    public static function externalUrl(string $key): string
    {
        $d = app()->theme->forms()[$key] ?? null;
        return $d && !empty($d['external_url']) ? (string) app()->settings->get($d['external_url'], '') : '';
    }

    /** Felder im Format der Theme-Vorlagen: name, label, type, required, options (Schlüssel => Text), width (half|full) */
    public static function fields(string $key): array
    {
        $t = self::table($key);
        if (!$t) return [];
        // Wiederholbare Gruppe: zusätzlich fields (Unterfelder), min, max, item_label, add_label – Ausgabe mit DataForms::group()
        return array_map(fn($f) => ($f['type'] === 'group' ? \Core\Data\Entries::groupSchema($f, true) : []) + [
            'name' => $f['name'], 'label' => Tables::label($f), 'type' => $f['type'], 'required' => !empty($f['required']),
            'options' => in_array($f['type'], ['select', 'multiselect'], true)
                ? array_combine(array_map('strval', array_keys((array) ($f['options'] ?? []))), array_map(fn($k) => Tables::optionLabel($f, (string) $k), array_keys((array) ($f['options'] ?? []))))
                : [],
            'width' => ($f['width'] ?? '') === 'half' ? 'half' : 'full', 'help' => (string) ($f['help'] ?? ''), 'max' => 300,
        ], DataForms::fields($t));
    }

    /**
     * Einsendung über /anfrage/{form} (Theme-Formular) – geprüft, spamgeschützt und verschlüsselt wie jedes Eingangs-Formular.
     * @return array{ok: bool, errors?: array, message?: string}
     */
    public static function submit(string $key, array $post, string $ip, array $files = []): array
    {
        $t = self::table($key);
        if (!$t || !self::enabled($key) || self::mode($key) !== 'internal') {
            return ['ok' => false, 'message' => lt('Dieses Formular ist derzeit nicht verfügbar.')];
        }
        $post[DataForms::PRIVACY] = $post[self::PRIVACY_FIELD] ?? ($post[DataForms::PRIVACY] ?? '');
        unset($post[self::PRIVACY_FIELD]);
        // Ältere Vorlagen senden bei Auswahlfeldern den Text statt des Schlüssels
        foreach (DataForms::fields($t) as $f) {
            if ($f['type'] !== 'select' || !isset($post[$f['name']]) || !is_string($post[$f['name']])) continue;   // Gruppen senden immer Schlüssel
            $v = trim($post[$f['name']]);
            if ($v === '' || array_key_exists($v, (array) ($f['options'] ?? []))) continue;
            foreach ((array) ($f['options'] ?? []) as $k => $label) {
                $all = array_merge([$label], array_column(array_map(fn($o) => ['l' => $o[$k] ?? null], (array) ($f['options_i18n'] ?? [])), 'l'));
                if (in_array($v, array_filter($all), true)) { $post[$f['name']] = (string) $k; break; }
            }
        }
        $res = DataForms::submit($t, $post, $files, $ip);
        if (isset($res['errors'][DataForms::PRIVACY])) {
            $res['errors'][self::PRIVACY_FIELD] = $res['errors'][DataForms::PRIVACY];
            unset($res['errors'][DataForms::PRIVACY]);
        }
        return array_diff_key($res, ['id' => 1]);
    }
}
