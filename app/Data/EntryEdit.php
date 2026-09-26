<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Block;
use Core\Lang;
use Core\Network\Network;

/**
 * Einträge direkt auf der Website bearbeiten (nur angemeldete Redaktion).
 *
 *  - Detailseiten: Werkzeugleiste mit Eintrag, Status, „Eintrag bearbeiten“ (Seitenleiste mit allen Feldern),
 *    „In der Verwaltung öffnen“; Text-, Mehrzeilen- und Rich-Text-Felder sind direkt im Text editierbar.
 *  - Datenlisten: Stift je Eintrag und „+ Neuer Eintrag“.
 *
 * Alles hier erzeugt nur für angemeldete Nutzer mit Recht „data.edit“ auf die Tabelle Markup – Besucher
 * (und der Seiten-Cache) sehen unverändertes HTML. Das Verhalten steckt in resources/js/_entry_edit.js (Teil von admin.js).
 *
 * Markierungen für eigene Detailvorlagen: entry_edit_attr($table, $entry, 'feld') am Element, das den Wert enthält.
 */
final class EntryEdit
{
    /** Inline editierbare Feldtypen → Bearbeitungsart */
    private const MODES = ['text' => 'plain', 'textarea' => 'lines', 'richtext' => 'rich'];

    /** Darf der angemeldete Nutzer diese Tabelle überhaupt auf der Website bearbeiten? */
    public static function canTable(array $t): bool
    {
        return app()->auth->check() && !Tables::isInbox($t) && can('data.edit', $t['handle']);
    }

    /** Darf der Nutzer diesen Eintrag ändern? (fremd in geteilter Tabelle: nein; ohne Veröffentlichungsrecht nur Entwürfe) */
    public static function canEdit(array $t, array $e): bool
    {
        return self::reason($t, $e) === null;
    }

    /** Warum nicht? null = bearbeitbar */
    public static function reason(array $t, array $e): ?string
    {
        if (!self::canTable($t)) return __('Für diese Tabelle fehlt Ihrer Rolle die Berechtigung.');
        if (Shared::isForeign($t, $e)) return __('Dieser Eintrag gehört zu einer anderen Website und lässt sich hier nicht ändern.');
        // Externe Quellen (Core\Sources): Inhalte kommen aus der Quelle
        if (!empty($e['id']) && \Core\Sources\Sources::isExternal($t, (int) $e['id'])) return __('Dieser Eintrag stammt aus einer externen Quelle und wird bei jedem Abruf aktualisiert – Änderungen bitte in der Quelle vornehmen.');
        if (($e['status'] ?? '') === 'published' && !can('data.publish', $t['handle'])) return __('Veröffentlichte Einträge darf Ihre Rolle nicht ändern.');
        return null;
    }

    /** Aktiver Eintrag der Detailseite, falls er hier bearbeitet werden darf */
    public static function current(): ?array
    {
        $ctx = app()->entry;
        return app()->entryEdit && $ctx && self::canEdit($ctx['table'], $ctx['entry']) ? $ctx : null;
    }

    public static function endpoint(array $t, ?array $e = null): string
    {
        return url('/admin/api/entries/' . $t['handle'] . '/' . ($e ? (int) $e['id'] : 'new'));
    }

    public static function adminUrl(array $t, ?array $e = null): string
    {
        return url('/admin/data/' . $t['handle'] . '/' . ($e ? (int) $e['id'] : 'new'));
    }

    /** Bearbeitungsart eines Tabellenfeldes ('' = nicht inline) – _title steht für das Titelfeld */
    public static function mode(array $t, string $field): string
    {
        if ($field === '_title') $field = (string) $t['settings']['title_field'];
        $f = $field !== '' ? Tables::field($t, $field) : null;
        return $f ? (self::MODES[$f['type']] ?? '') : '';
    }

    /**
     * Attribute für ein Element, das den Wert eines Feldes enthält (nur im Bearbeitungsmodus der Detailseite, sonst '').
     * $mode: plain (einzeilig), lines (mehrzeilig, Zeilenumbrüche), rich (formatierter Text) – Standard nach Feldtyp.
     */
    public static function attr(array $t, array $e, string $field, ?string $mode = null): string
    {
        if (!app()->entryEdit || !self::canEdit($t, $e)) return '';
        $ctx = app()->entry;
        if (!$ctx || $ctx['table']['handle'] !== $t['handle'] || (int) $ctx['entry']['id'] !== (int) $e['id']) return '';
        if ($field === '_title') $field = (string) $t['settings']['title_field'];
        $auto = self::mode($t, $field);
        if ($auto === '') return '';
        $mode ??= $auto;
        if (!in_array($mode, ['plain', 'lines', 'rich'], true) || ($mode === 'rich' && $auto !== 'rich')) return '';
        $f = Tables::field($t, $field);
        return ' data-entry-field="' . e($field) . '" data-entry-mode="' . e($mode) . '" data-entry-label="' . e((string) ($f['label'] ?? $field)) . '"';
    }

    /**
     * Block einer Detailvorlage: an den Datensatz gebundenes Blockfeld (⛓) → Feld des Eintrags inline bearbeiten.
     * Nur wenn Quelle und Darstellung verlustfrei zusammenpassen (sonst bleibt es bei der Seitenleiste).
     */
    public static function blockAttr(Block $b, string $path): string
    {
        $ctx = app()->entry;
        if (!$ctx || str_contains($path, '.')) return '';
        $src = $b->data['_bind'][$path] ?? null;
        if (!is_string($src) || $src === '') return '';
        $t = $ctx['table'];
        $field = $src === '_title' ? (string) $t['settings']['title_field'] : $src;
        $srcMode = self::mode($t, $field);
        $target = '';
        foreach ($b->def['fields'] ?? [] as $f) {
            if (($f['name'] ?? '') === $path) { $target = (string) ($f['type'] ?? 'text'); break; }
        }
        $mode = match (true) {
            $srcMode === 'plain' && in_array($target, ['text', 'textarea', 'richtext'], true) => 'plain',
            $srcMode === 'rich' && $target === 'richtext' => 'rich',
            // Mehrzeilig: im formatierten Text als Absatz mit Umbrüchen; sonst nur, solange der Wert einzeilig ist
            $srcMode === 'lines' && ($target === 'richtext' || !str_contains((string) ($ctx['entry'][$field] ?? ''), "\n")) => 'lines',
            default => '',
        };
        return $mode === '' ? '' : self::attr($t, $ctx['entry'], $field, $mode);
    }

    /** Stift „Eintrag bearbeiten“ in Listen (Link zur Verwaltung als Rückfall ohne Skript) */
    public static function button(array $t, array $e, string $title = ''): string
    {
        if (!app()->dataEdit || !self::canEdit($t, $e)) return '';
        $label = __('„{title}“ bearbeiten', ['title' => $title !== '' ? $title : Entries::title($t, $e)]);
        return '<a class="cms-entry-pencil" href="' . e(self::adminUrl($t, $e)) . '" data-entry-edit="' . e(self::endpoint($t, $e)) . '"'
            . ' aria-label="' . e($label) . '" title="' . e($label) . '"><svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">'
            . '<path d="M11.1 2.2a1.6 1.6 0 0 1 2.3 0l.4.4a1.6 1.6 0 0 1 0 2.3L6 12.7l-3.3.8.8-3.3z" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg></a>';
    }

    /** „+ Neuer Eintrag“ für Datenlisten */
    public static function newButton(array $t): string
    {
        if (!app()->dataEdit || !self::canTable($t)) return '';
        return '<p class="cms-entry-new"><a class="cms-entry-newbtn" href="' . e(self::adminUrl($t)) . '" data-entry-edit="' . e(self::endpoint($t)) . '">+ '
            . e(__('Neuer Eintrag')) . '<span class="cms-entry-newbtn__t"> · ' . e($t['singular'] ?: $t['name']) . '</span></a></p>';
    }

    /** Bearbeiten auf der Ursprungs-Website (fremder Eintrag einer geteilten Tabelle): [url, sso?] */
    public static function originEdit(array $t, array $e): ?array
    {
        if (!Shared::isForeign($t, $e)) return null;
        $site = (string) $e['origin_site'];
        $path = '/admin/data/' . $t['handle'] . '/' . (int) $e['id'];
        // Netzwerk-Administration: Anmeldung per Einmal-Token (POST /admin/network/open), sonst normaler Link zur Verwaltung dort
        if (Network::isNetworkUser() && isset(\Core\Sites::all()[$site])) return ['url' => url('/admin/network/open'), 'site' => $site, 'path' => $path, 'sso' => true];
        $info = Shared::siteInfo($site, $t['shared']['key']);
        return $info['url'] !== '' ? ['url' => $info['url'] . $path, 'site' => $site, 'name' => $info['name'], 'sso' => false] : null;
    }

    /**
     * Werkzeugleiste für Detailseiten außerhalb des Vorlagen-Editors – für ältere Theme-Partials mit eigener Seiten-Leiste
     * (entry_toolbar()). Neue Partials rufen nur cms_toolbar() auf; beides rendert Core\Toolbar (app/Views/toolbar.php).
     */
    public static function toolbar(array $vars): string
    {
        $ctx = app()->entry;
        if (!$ctx || !empty($vars['editing']) || !app()->auth->check()) return '';
        return \Core\Toolbar::render($vars);
    }

    /** Texte für das Skript (in der Sprache der Verwaltung) */
    public static function texts(): array
    {
        return [
            'dirty' => __('Ungespeicherte Änderungen'),
            'saving' => __('Speichere …'),
            'saved' => __('Gespeichert {time}'),
            'published' => __('Veröffentlicht {time}'),
            'drafted' => __('Als Entwurf gespeichert {time}'),
            'error' => __('Fehler beim Speichern'),
            'invalid' => __('Bitte prüfen Sie die markierten Felder – es wurde nichts gespeichert.'),
            'discard' => __('Ungespeicherte Änderungen verwerfen?'),
            'leave' => __('Die Seitenleiste enthält ungespeicherte Änderungen. Trotzdem schließen?'),
            'loading' => __('Lade Felder …'),
            'failed' => __('Laden fehlgeschlagen: {error}'),
            'session' => __('Ihre Sitzung ist abgelaufen. Bitte in einem neuen Tab anmelden und erneut speichern.'),
            'confirmPublish' => __('Eintrag jetzt veröffentlichen? Er ist dann für alle Besucher sichtbar.'),
            'confirmDraft' => __('Eintrag auf Entwurf setzen? Besucher sehen ihn dann nicht mehr.'),
            'publishOk' => __('Veröffentlichen'),
            'draftOk' => __('Als Entwurf'),
            'pending' => __('Gespeichert. Die Vorschau zeigt die Änderung, sobald die Seite gespeichert und neu geladen ist.'),
            'editHint' => __('Klicken zum Bearbeiten: {label}'),
            'close' => __('Schließen'),
            'cancel' => __('Abbrechen'),
            'discardTitle' => __('Änderungen verwerfen?'),
            'discardPanel' => __('Die Seitenleiste enthält Änderungen, die noch nicht gespeichert sind. „Verwerfen“ schließt sie ohne zu speichern – der Eintrag bleibt, wie er zuletzt gespeichert wurde.'),
            'keep' => __('Weiter bearbeiten'),
            'discardBtn' => __('Verwerfen'),
            'saveExit' => __('Speichern & beenden'),
            'panel' => __('Eintrag bearbeiten'),
            'format' => __('Formatierung'),
        ];
    }

    /** Konfiguration für das Skript auf Detailseiten */
    public static function config(array $t, array $e): array
    {
        return [
            'table' => $t['handle'], 'id' => (int) $e['id'], 'status' => (string) $e['status'],
            'endpoint' => self::endpoint($t, $e), 'workflow' => (bool) $t['settings']['workflow'],
            'publish' => can('data.publish', $t['handle']), 'csrf' => \Core\Csrf::token(), 'texts' => self::texts(),
            'lang' => Lang::multi() ? Lang::norm($e['lang'] ?? null) : null,
        ];
    }
}
