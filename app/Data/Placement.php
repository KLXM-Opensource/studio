<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Layout;
use Core\Links;
use Core\PageCache;
use Core\Pages;
use Core\PageTemplates;
use Core\PublicPaths;

/**
 * „Einsetzen“: Wie kommt eine Datentabelle auf die Website? – Block je Zweck (Formular bzw. Datenliste), Seiten, die die Tabelle
 * schon verwenden, und die beiden Wege aus dem Assistenten bzw. „Felder & Einstellungen → Einsetzen“:
 *   page()   neue Seite mit dem passenden Block anlegen (Eltern-Seite über die Linkauswahl, page:ID; im Menü ja/nein)
 *   append() den Block an eine bestehende Seite anhängen – als Entwurf (veröffentlichen wie jede Änderung)
 * Gleiche Prüfungen wie „Neue Seite“ (reservierte und doppelte Adressen); Seiten entstehen als Entwurf, außer es wird ausdrücklich
 * veröffentlicht (Recht pages.publish).
 */
final class Placement
{
    /** Blocktypen, die eine Tabelle über data.table verwenden (Ausgabe und Formular) */
    public const BLOCKS = ['data_list', 'data_form', 'data_fields', 'calendar', 'upcoming'];

    /** Passender Block für die Website: data_form (Formular, Anfragen, Anmeldung), data_list (Inhalte) oder null (intern) */
    public static function blockType(array $t): ?string
    {
        $p = Purpose::of($t);
        if (Tables::isInbox($t) || $p === 'registration' || $p === 'mail' || $p === 'inbox') return 'data_form';
        if ($p === 'internal') return null;
        return 'data_list';
    }

    /** Anzeigename des Blocks (wie in der Blockauswahl des Editors) */
    public static function blockLabel(?string $type): string
    {
        return match ($type) {
            'data_form' => __('Formular (Datentabelle)'),
            'data_list' => __('Datenliste'),
            'calendar' => __('Kalender'),
            'upcoming' => __('Nächste Termine'),
            'data_fields' => __('Datensatz-Felder'),
            default => (string) $type,
        };
    }

    /** Daten des Blocks für die Tabelle (Überschrift optional) */
    public static function blockData(array $t, ?string $type, string $title = ''): array
    {
        return match ($type) {
            'data_form' => ['title' => $title, 'table' => $t['handle'], 'form_width' => 'text', 'form_align' => 'left'],
            default => ['title' => $title, 'table' => $t['handle'], 'fields' => [], 'layout' => 'cards', 'columns' => '3', 'ratio' => '16:10', 'limit' => 0,
                'link_detail' => ($t['settings']['route'] ?? '') !== ''],
        };
    }

    /**
     * Seiten, die die Tabelle verwenden: Blöcke mit data.table = Kurzname (auch in Layout-Spalten, veröffentlicht oder im Entwurf)
     * sowie die Detailseiten-Vorlage. Je Seite: id, title, url, edit (Bearbeiten-Adresse), status, blocks (Bezeichnungen), template.
     */
    public static function usages(array $t): array
    {
        $out = [];
        $like = '%"table":"' . $t['handle'] . '"%';
        $rows = Pages::db()->fetchAll('SELECT * FROM pages WHERE content_published LIKE ? OR content_draft LIKE ? ORDER BY title', [$like, $like]);
        foreach ($rows as $p) {
            $labels = [];
            foreach (Layout::flatten(array_merge(Pages::blocks($p, false), Pages::blocks($p, true))) as $b) {
                if (!in_array($b['type'] ?? '', self::BLOCKS, true) || (string) ($b['data']['table'] ?? '') !== $t['handle']) continue;
                $labels[self::blockLabel((string) $b['type'])] = true;
            }
            if (!$labels) continue;
            $out[(int) $p['id']] = self::row($p, array_keys($labels), false);
        }
        $tpl = !empty($t['settings']['detail_page_id']) ? Pages::find((int) $t['settings']['detail_page_id']) : null;
        if ($tpl && !isset($out[(int) $tpl['id']])) $out[(int) $tpl['id']] = self::row($tpl, [__('Detailseiten-Vorlage')], true);
        elseif ($tpl) $out[(int) $tpl['id']]['template'] = true;
        return array_values($out);
    }

    private static function row(array $p, array $blocks, bool $template): array
    {
        $type = (string) ($p['type'] ?? 'page');
        $url = $type === 'page' ? Pages::url($p) : (PageTemplates::isTemplatePage($p) ? PageTemplates::editUrl((int) $p['id']) : null);
        $edit = $url !== null && $type === 'page' ? $url . '?edit=1' : $url;
        // Detailseiten-Vorlage: gestaltet wird sie über „Detailseite gestalten“ (Einstellungen → Auf der Website)
        if ($edit === null && !empty($p['template_for'])) $edit = url('/admin/data/' . $p['template_for'] . '/schema#website');
        return ['id' => (int) $p['id'], 'title' => (string) $p['title'], 'url' => $url, 'edit' => $edit,
            'status' => (string) $p['status'], 'type' => $type, 'blocks' => $blocks, 'template' => $template || $type === 'template'];
    }

    /** Seiten-ID aus der Linkauswahl (page:12, page:12#anker) oder einem internen Pfad (/kontakt); sonst null */
    public static function pageRef(string $v): ?int
    {
        $v = trim($v);
        if ($v === '') return null;
        if (preg_match('~^page:(\d+)~', $v, $m)) return Pages::find((int) $m[1]) ? (int) $m[1] : null;
        if (str_starts_with($v, '/') && !str_starts_with($v, '//')) {
            $p = Pages::byPath(trim((string) parse_url($v, PHP_URL_PATH), '/'));
            return $p ? (int) $p['id'] : null;
        }
        return null;
    }

    /**
     * Neue Seite mit dem passenden Block: $o title, parent (Linkwert, leer = oberste Ebene), menu (bool), publish (bool),
     * template (bool: Detailseiten-Vorlage anlegen, falls die Tabelle Detailseiten hat und noch keine Vorlage).
     * @return array{id: ?int, errors: array<string, string>}
     */
    public static function page(array $t, array $o, ?int $userId = null): array
    {
        $type = self::blockType($t);
        if ($type === null) return ['id' => null, 'errors' => ['place' => __('Interne Listen erscheinen nicht auf der Website.')]];
        $title = mb_substr(trim(strip_tags((string) ($o['title'] ?? ''))), 0, 120);
        $errors = [];
        if ($title === '') $errors['title'] = __('Bitte einen Titel für die Seite angeben.');
        $parentRaw = trim((string) ($o['parent'] ?? ''));
        $parent = $parentRaw === '' ? null : self::pageRef($parentRaw);
        if ($parentRaw !== '' && ($parent === null || (Pages::find($parent)['type'] ?? '') !== 'page')) {
            $errors['parent'] = __('Übergeordnete Seite nicht gefunden – bitte über „Auswählen …“ eine Seite wählen oder das Feld leeren.');
        }
        $slug = Pages::slugify($title);
        if (!$errors) {
            if ($parent === null && PublicPaths::isReserved($slug)) {
                $errors['title'] = __('Diese Adresse ist reserviert: Unter /{slug} liegen Dateien oder Funktionen des Systems. Bitte einen anderen Titel wählen.', ['slug' => $slug]);
            } elseif (Pages::slugTaken($slug, $parent)) {
                $errors['title'] = __('Auf dieser Ebene gibt es schon eine Seite mit der Adresse „{slug}“ – bitte einen anderen Titel wählen oder den Block in die bestehende Seite einfügen.', ['slug' => $slug]);
            }
        }
        if ($errors) return ['id' => null, 'errors' => $errors];
        $blocks = Pages::sanitizeBlocks([['id' => bin2hex(random_bytes(5)), 'type' => $type, 'data' => self::blockData($t, $type, $title)]]);
        $sort = (int) Pages::db()->fetchValue('SELECT COALESCE(MAX(sort), 0) + 10 FROM pages WHERE ' . ($parent ? 'parent_id = ?' : 'parent_id IS NULL'), $parent ? [$parent] : []);
        $id = Pages::create(['slug' => $slug, 'title' => $title, 'status' => 'draft', 'parent_id' => $parent, 'menu' => !empty($o['menu']) ? 1 : 0,
            'sort' => $sort, 'noindex' => 0], $blocks);
        Pages::addRevision($id, (string) Pages::find($id)['content_draft'], $userId, __('Angelegt über „Einsetzen“ ({table})', ['table' => $t['name']]));
        if (!empty($o['template']) && $type === 'data_list') self::ensureTemplate($t);
        if (!empty($o['publish'])) Pages::publish($id, $userId);
        PageCache::clear();
        return ['id' => $id, 'errors' => []];
    }

    /**
     * Block an eine bestehende Seite anhängen (am Ende, im Entwurf). $ref: Linkwert (page:ID) bzw. interner Pfad.
     * @return array{id: ?int, errors: array<string, string>}
     */
    public static function append(array $t, string $ref, ?int $userId = null, bool $publish = false): array
    {
        $type = self::blockType($t);
        if ($type === null) return ['id' => null, 'errors' => ['place' => __('Interne Listen erscheinen nicht auf der Website.')]];
        $id = self::pageRef($ref);
        $page = $id ? Pages::find($id) : null;
        if (!$page || !in_array($page['type'] ?? 'page', ['page'], true)) {
            return ['id' => null, 'errors' => ['target' => __('Bitte über „Auswählen …“ eine Seite wählen.')]];
        }
        $blocks = Pages::blocks($page, true);
        $blocks[] = ['id' => bin2hex(random_bytes(5)), 'type' => $type, 'data' => self::blockData($t, $type, '')];
        Pages::saveDraft($id, Pages::sanitizeBlocks($blocks), $userId, __('Block „{block}“ eingefügt ({table})', ['block' => self::blockLabel($type), 'table' => $t['name']]));
        if ($publish) Pages::publish($id, $userId);
        PageCache::clear();
        return ['id' => $id, 'errors' => []];
    }

    /** Detailseiten-Vorlage anlegen, falls die Tabelle Detailseiten hat und noch keine (gültige) Vorlage */
    public static function ensureTemplate(array $t): ?int
    {
        if (($t['settings']['route'] ?? '') === '' || Tables::isInbox($t)) return null;
        $cur = !empty($t['settings']['detail_page_id']) ? Pages::find((int) $t['settings']['detail_page_id']) : null;
        if ($cur) return (int) $cur['id'];
        return \Core\Http\Controllers\Admin\DataController::makeTemplate($t);
    }

    /** Beschreibung des Linkziels für Meldungen (Titel der Seite) */
    public static function describe(string $ref): string
    {
        try {
            return (string) (Links::describe($ref)['label'] ?? $ref);
        } catch (\Throwable) {
            return $ref;
        }
    }
}
