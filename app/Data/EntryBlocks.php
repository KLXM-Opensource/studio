<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Data;

use Core\Fields;
use Core\Media;
use Core\Pages;
use Core\Search\Documents;

/**
 * Feldtyp „Inhaltsblöcke“ (blocks): ein Eintrag hat eine eigene Folge von Blöcken des Kits – gespeichert wie der Inhalt einer Seite
 * (Liste {id, type, data, tunes}, bereinigt über Pages::sanitizeBlocks). Erlaubte Blocktypen je Feld ('blocks' => [typ, …], leer =
 * Standard: Inhalt und Medien). Eingabe in der Verwaltung: Blöcke mit den Feldern wie in der Seitenleiste des Seiten-Editors
 * (renderInput, resources/js/admin.js initBlockFields). Auf der Detailseite setzt der Kern-Block „Inhaltsblöcke des Eintrags“
 * (entry_blocks) sie als eigene Abschnitte der Seite ein (Core\Theme::renderBlocks).
 */
final class EntryBlocks
{
    public const TYPE = 'blocks';
    public const MAX = 40;
    /** Nie in Tabellen: Daten-Blöcke, Layout, der Platzhalter selbst */
    private const NEVER = ['entry_blocks', 'layout', 'not_found'];

    /** Wählbare Blöcke [typ => Bezeichnung] – einfügbar, ohne Daten-Blöcke */
    public static function choices(): array
    {
        $out = [];
        foreach (app()->theme->blocks() as $type => $b) {
            if (in_array($type, self::NEVER, true) || ($b['group'] ?? '') === 'Daten' || str_starts_with((string) $type, 'data_')) continue;
            if (($b['insertable'] ?? true) === false || !\Core\Features::allowsBlock((string) $type)) continue;
            $out[(string) $type] = (string) ($b['label'] ?? $type);
        }
        return $out;
    }

    /** Vorbelegung: Blöcke der Gruppen „Inhalt“ und „Medien“ */
    public static function defaults(): array
    {
        $theme = app()->theme;
        return array_values(array_filter(array_keys(self::choices()), fn($t) => in_array($theme->block($t)['group'] ?? 'Inhalt', ['Inhalt', 'Medien'], true)));
    }

    /** Erlaubte Blocktypen eines Feldes (in der Reihenfolge des Kits) */
    public static function allowed(array $f): array
    {
        $choices = array_keys(self::choices());
        $sel = array_values(array_filter(array_map('strval', (array) ($f['blocks'] ?? []))));
        return array_values($sel ? array_intersect($choices, $sel) : array_intersect($choices, self::defaults()));
    }

    /** Gespeicherter Wert (JSON oder Liste) → Liste der Blöcke */
    public static function items(mixed $raw): array
    {
        if (is_string($raw)) $raw = json_decode($raw, true);
        return is_array($raw) ? array_values(array_filter($raw, fn($b) => is_array($b) && !empty($b['type']))) : [];
    }

    /**
     * Eingabe bereinigen: Formular ({i: {type, id, tunes (JSON), data}}) oder gespeicherte Liste → Blöcke wie auf Seiten.
     * Nur erlaubte Typen, höchstens MAX.
     */
    public static function sanitize(array $f, mixed $raw): array
    {
        $allowed = array_flip(self::allowed($f));
        $list = [];
        foreach (is_array($raw) ? $raw : self::items($raw) as $b) {
            if (!is_array($b) || !isset($allowed[(string) ($b['type'] ?? '')])) continue;
            if (is_string($b['tunes'] ?? null)) $b['tunes'] = json_decode($b['tunes'], true) ?: [];
            $list[] = $b;
            if (count($list) >= self::MAX) break;
        }
        return Pages::sanitizeBlocks($list);
    }

    /** Darstellung (eigene Abschnitte des Kits) */
    public static function render(array $blocks): string
    {
        return $blocks ? app()->theme->renderBlocks($blocks) : '';
    }

    /** Text für die Suche */
    public static function text(mixed $raw): string
    {
        [$head, $text] = Documents::blocksText(self::items($raw));
        return trim((string) preg_replace('~\s+~u', ' ', implode(' ', array_merge($head, $text))));
    }

    /** Verwendete Medien (Bilder, Dateien, Links „media:ID“) */
    public static function mediaIds(mixed $raw): array
    {
        return Media::idsInBlocks(self::items($raw));
    }

    /** Kurzfassung für Listen, z. B. „3 Blöcke: Fließtext, Galerie“ */
    public static function summary(mixed $raw): string
    {
        $items = self::items($raw);
        if (!$items) return '';
        $labels = array_values(array_unique(array_map(fn($b) => (string) (app()->theme->block((string) $b['type'])['label'] ?? $b['type']), $items)));
        return (count($items) === 1 ? __('1 Block') : __('{n} Blöcke', ['n' => count($items)])) . ': ' . implode(', ', array_slice($labels, 0, 4));
    }

    // ------------------------------------------------------------------ Eingabe in der Verwaltung

    /** Feld „Inhaltsblöcke“ im Formular: Blöcke mit ihren Feldern, „+ Block hinzufügen“ mit den erlaubten Typen */
    public static function renderInput(array $f, string $name, mixed $value, array $errors, string $path, string $label, string $errHtml): string
    {
        $types = self::allowed($f);
        $theme = app()->theme;
        $h = '<div class="f f--blocks" data-blocks data-name="' . e($name) . '"><span class="f-label">' . $label . '</span>'
            . (!empty($f['help']) ? '<p class="f-help">' . e((string) $f['help']) . '</p>' : '') . '<div class="blk-items" data-blk-items>';
        $list = self::items($value);
        foreach ($list as $i => $b) {
            // Mehrere Blöcke: zugeklappt mit Auszug im Titel (übersichtlich); mit Fehlern offen
            $open = count($list) === 1 || (bool) array_filter(array_keys($errors), fn($k) => str_starts_with((string) $k, "$path.$i."));
            if (in_array((string) $b['type'], $types, true) || $theme->block((string) $b['type'])) $h .= self::item($name, (string) $i, $b, $errors, "$path.$i", $open);
        }
        $h .= '</div>';
        foreach ($types as $t) $h .= '<template data-blk-tpl="' . e($t) . '">' . self::item($name, '__blk__', ['type' => $t, 'data' => []], [], '') . '</template>';
        $h .= '<div class="blk-add"><button type="button" class="btn btn--small" data-blk-add aria-haspopup="menu" aria-expanded="false">' . icon('plus') . ' ' . e(__('Block hinzufügen')) . '</button>'
            . '<div class="blk-menu" role="menu" hidden>';
        foreach ($types as $t) {
            $def = $theme->block($t);
            $h .= '<button type="button" role="menuitem" data-blk-type="' . e($t) . '">' . \Core\Icons::render((string) ($def['icon'] ?? 'squares-four')) . '<span>' . e((string) ($def['label'] ?? $t)) . '</span></button>';
        }
        return $h . '</div></div>' . $errHtml . '</div>';
    }

    private static function item(string $name, string $idx, array $b, array $errors, string $path, bool $open = true): string
    {
        $type = (string) $b['type'];
        $def = app()->theme->block($type) ?? ['label' => $type, 'fields' => []];
        $data = array_replace(Fields::defaults($def['fields'] ?? []), is_array($b['data'] ?? null) ? $b['data'] : []);
        $n = $name . '[' . $idx . ']';
        $fields = '';
        if (!empty($def['variants'])) {
            $fields .= Fields::renderField(['name' => 'variant', 'label' => __('Variante'), 'type' => 'select', 'required' => true, 'options' => $def['variants']],
                isset($def['variants'][$data['variant'] ?? '']) ? $data['variant'] : array_key_first($def['variants']), [], $n . '[data]');
        }
        $fields .= Fields::renderForm($def['fields'] ?? [], $data, $errors, $n . '[data]', $path);
        $label = (string) ($def['label'] ?? $type);
        $peek = self::peek($def['fields'] ?? [], $data);
        return '<fieldset class="rep-item blk-item' . ($open ? '' : ' is-collapsed') . '" data-blk-item data-type="' . e($type) . '"><legend>'
            . '<button type="button" class="rep-toggle" data-blk="toggle" aria-expanded="' . ($open ? 'true' : 'false') . '"><span class="rep-chev" aria-hidden="true"></span>'
            . '<span class="rep-title">' . \Core\Icons::render((string) ($def['icon'] ?? 'squares-four')) . ' ' . e($label)
            . ($peek !== '' ? ' <span class="blk-peek">– ' . e($peek) . '</span>' : '') . '</span></button></legend>'
            . '<div class="rep-tools"><button type="button" class="icon-btn" data-blk="up" aria-label="' . e(__('Nach oben')) . '">↑</button>'
            . '<button type="button" class="icon-btn" data-blk="down" aria-label="' . e(__('Nach unten')) . '">↓</button>'
            . '<button type="button" class="icon-btn icon-btn--danger" data-blk="remove" aria-label="' . e(__('Block „{label}“ entfernen', ['label' => $label])) . '">✕</button></div>'
            . '<input type="hidden" name="' . e($n) . '[type]" value="' . e($type) . '">'
            . '<input type="hidden" name="' . e($n) . '[id]" value="' . e((string) ($b['id'] ?? '')) . '">'
            . '<input type="hidden" name="' . e($n) . '[tunes]" value="' . e((string) json_encode($b['tunes'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE)) . '">'
            . '<div class="rep-fields blk-fields">' . $fields . '</div></fieldset>';
    }

    /** Blöcke des aufgerufenen Eintrags für den Kern-Block entry_blocks (data: table, field „tabelle.feld“) – leer ohne Eintrag */
    public static function forContext(array $data): array
    {
        $ctx = app()->entry;
        if (!$ctx) return [];
        $t = $ctx['table'];
        if (!empty($data['table']) && $data['table'] !== $t['handle']) return [];
        $want = (string) ($data['field'] ?? '');
        $name = str_starts_with($want, $t['handle'] . '.') ? substr($want, strlen($t['handle']) + 1) : (array_key_first(self::fieldsOf($t)) ?? '');
        $f = $name !== '' ? Tables::field($t, $name) : null;
        if (!$f || $f['type'] !== self::TYPE) return [];
        // eigene IDs je Einbindung (Anker, Abschnitts-IDs) – eindeutig gegenüber den Blöcken der Vorlage
        return array_map(fn($b) => ['id' => 'e' . $ctx['entry']['id'] . '-' . $b['id']] + $b, self::items($ctx['entry'][$name] ?? null));
    }

    /** Kurzer Auszug für den Titel eines zugeklappten Blocks: erstes gefülltes Text-Feld (Überschrift vor Text) */
    private static function peek(array $fields, array $data): string
    {
        foreach (['title', 'heading', 'text', 'eyebrow'] as $pref) {
            foreach ($fields as $f) {
                if (($f['name'] ?? '') !== $pref || !in_array($f['type'] ?? 'text', ['text', 'textarea', 'richtext', 'inline'], true)) continue;
                $v = trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags((string) ($data[$pref] ?? '')), ENT_QUOTES)));
                if ($v !== '') return mb_strimwidth($v, 0, 60, '…');
            }
        }
        return '';
    }

    /** Bearbeiten auf der Detailseite: Attribute für die Hülle der eingesetzten Blöcke (Knopf „Bearbeiten“ → Seitenleiste am Feld) */
    public static function editAttr(array $data): string
    {
        $ctx = app()->entry;
        if (!$ctx) return '';
        $t = $ctx['table'];
        $want = (string) ($data['field'] ?? '');
        $name = str_starts_with($want, $t['handle'] . '.') ? substr($want, strlen($t['handle']) + 1) : (array_key_first(self::fieldsOf($t)) ?? '');
        return $name !== '' ? EntryEdit::attr($t, $ctx['entry'], $name) : '';
    }

    /** Für den Kern-Block entry_blocks: Felder dieses Typs einer Tabelle [name => Bezeichnung] */
    public static function fieldsOf(?array $t): array
    {
        $out = [];
        foreach ((array) ($t['fields'] ?? []) as $f) if (($f['type'] ?? '') === self::TYPE) $out[(string) $f['name']] = (string) $f['label'];
        return $out;
    }
}
