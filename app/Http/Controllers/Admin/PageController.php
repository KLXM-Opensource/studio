<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\NotFound;
use Core\Pages;

final class PageController extends AdminController
{
    public function index(Request $r): Response
    {
        $this->auth($r, 'pages.edit');
        $lang = \Core\Lang::valid($r->str('lang')) ? $r->str('lang') : \Core\Lang::default();
        return $this->view('pages/index', ['tree' => Pages::tree(false, $lang), 'lang' => $lang,
            'templates' => app()->db->fetchAll("SELECT * FROM pages WHERE type = 'template' AND COALESCE(template_for, '') != ? ORDER BY title", [NotFound::MARK]),
            'notFound' => NotFound::all()]);
    }

    /** Seite „Nicht gefunden (404)“ der Sprache anlegen (Entwurf mit Startinhalten des Kits) bzw. öffnen → Frontend-Editor */
    public function notFound(Request $r): Response
    {
        $this->auth($r, 'pages.manage');
        $lang = \Core\Lang::valid($r->str('lang')) ? $r->str('lang') : \Core\Lang::default();
        $had = NotFound::exact($lang) !== null;
        $page = NotFound::create($lang);
        $this->changed();
        if (!$had) app()->session->flash('success', __('404-Seite angelegt (Entwurf). Inhalte anpassen und veröffentlichen – bis dahin sehen Besucher die Standard-Fehlerseite des Kits.'));
        return Response::redirect(Pages::url($page) . '?edit=1');
    }

    public function create(Request $r): Response
    {
        $this->auth($r, 'pages.manage');
        $parent = ctype_digit($r->str('parent')) ? (int) $r->str('parent') : null;
        $lang = \Core\Lang::valid($r->str('lang')) ? $r->str('lang') : \Core\Lang::default();
        $tpl = \Core\PageTemplates::suggested($parent);
        return $this->view('pages/form', ['page' => null, 'errors' => [], 'old' => ['status' => 'draft', 'parent_id' => $parent, 'menu' => 0, 'lang' => $lang, 'template' => $tpl === null ? '' : (string) $tpl], 'revisions' => []]);
    }

    public function store(Request $r): Response
    {
        $this->auth($r, 'pages.manage');
        [$data, $errors] = $this->validate($r, null);
        if ($errors) {
            return $this->view('pages/form', ['page' => null, 'errors' => $errors, 'old' => $data + ['template' => (string) ($r->post['template'] ?? '')], 'revisions' => []], 422);
        }
        // Vorlage (Core\PageTemplates) oder leere Seite – der Editor zeigt dann den Platzhalter „Leere Seite“
        $tpl = (string) ($r->post['template'] ?? '');
        $blocks = $tpl !== '' && ctype_digit($tpl) ? Pages::sanitizeBlocks(\Core\PageTemplates::blocks((int) $tpl)) : [];
        $sort = (int) app()->db->fetchValue('SELECT COALESCE(MAX(sort), 0) + 10 FROM pages WHERE ' . ($data['parent_id'] ? 'parent_id = ?' : 'parent_id IS NULL'), $data['parent_id'] ? [$data['parent_id']] : []);
        $id = Pages::create($data + ['sort' => $sort], $blocks);
        $this->changed();
        app()->session->flash('success', $blocks ? __('Seite aus der Vorlage angelegt. Jetzt Inhalte anpassen.') : 'Seite angelegt. Jetzt Inhalte im Bearbeitungsmodus hinzufügen.');
        return Response::redirect(Pages::url(Pages::find($id)) . '?edit=1');
    }

    public function edit(Request $r, string $id): Response
    {
        $this->auth($r, 'pages.manage');
        $page = Pages::find((int) $id) ?? throw new HttpException(404);
        // [Platzhalter] dieser Seite oben zeigen (Sprungziel „In der Verwaltung“ aus der Übersicht) – bearbeitet wird im Frontend-Editor
        $ph = \Core\Dashboard\Metrics::placeholders(app()->db, \Core\Dashboard\Metrics::placeholdersOk(), 500, (int) $page['id']);
        return $this->view('pages/form', ['page' => $page, 'errors' => [], 'old' => $page, 'revisions' => Pages::revisions((int) $id),
            'placeholders' => $ph, 'css' => $ph ? ['css/placeholders.css'] : []]);
    }

    public function update(Request $r, string $id): Response
    {
        $this->auth($r, 'pages.manage');
        $page = Pages::find((int) $id) ?? throw new HttpException(404);
        [$data, $errors] = $this->validate($r, $page);
        if ($errors) {
            return $this->view('pages/form', ['page' => $page, 'errors' => $errors, 'old' => $data + $page, 'revisions' => Pages::revisions((int) $id)], 422);
        }
        if ($page['is_home']) {
            $data['slug'] = $page['slug'];
            $data['status'] = 'published';
        }
        if (NotFound::isPage($page)) {   // Seite „Nicht gefunden“: feste Adresse, nie im Menü oder in Suchmaschinen
            $data = ['slug' => $page['slug'], 'parent_id' => null, 'menu' => 0, 'nav_title' => '', 'noindex' => 1] + $data;
        }
        if ($data['status'] === 'published' && $page['status'] !== 'published' && ($open = Pages::openMarkers($page['content_draft'] . ' ' . $data['title'] . ' ' . $data['meta_description']))) {
            $data['status'] = 'draft';   // offene Platzhalter „[bitte ergänzen: …]“ – nicht online stellen
            app()->session->flash('error', __('Nicht veröffentlicht: Die Seite enthält noch {n} Platzhalter, z. B. {list}. Bitte ergänzen oder entfernen.', ['n' => count($open), 'list' => implode(' · ', array_slice($open, 0, 3))]));
        }
        if ($data['status'] === 'published' && $page['content_published'] === null) {
            $data['content_published'] = $page['content_draft'];
            $data['published_at'] = now();
        }
        $data['updated_at'] = now();
        if ($page['is_home']) {
            $data['parent_id'] = null;
        }
        app()->db->update('pages', $data, 'id = :id', ['id' => (int) $id]);
        Pages::rebuildPaths();
        $this->changed();
        return $this->back('/admin/pages/' . $id, 'success', 'Seiteneinstellungen gespeichert.');
    }

    /** Systemadressen ganz oben (Ordner in public/, feste Routen) – zentrale Liste: Core\PublicPaths::RESERVED_SLUGS */
    public const RESERVED_SLUGS = \Core\PublicPaths::RESERVED_SLUGS;

    /** Nur ohne übergeordnete Seite und ohne Sprachpräfix; dazu jeder Ordner, der (noch) in public/ liegt (z. B. kits vor assets:migrate) */
    public static function reservedSlug(string $slug, ?int $parentId, ?string $lang): bool
    {
        return $parentId === null && ($lang === null || $lang === \Core\Lang::default()) && \Core\PublicPaths::isReserved($slug);
    }

    private function validate(Request $r, ?array $page): array
    {
        $title = mb_substr(strip_tags($r->str('title')), 0, 120);
        $slug = $page && NotFound::isPage($page) ? (string) $page['slug'] : Pages::slugify($r->str('slug') ?: $title);
        $parentId = ctype_digit($r->str('parent_id')) && (int) $r->str('parent_id') > 0 ? (int) $r->str('parent_id') : null;
        if ($page && $parentId && ($parentId === (int) $page['id'] || in_array($parentId, Pages::descendantIds((int) $page['id']), true))) {
            $parentId = $page['parent_id'] ? (int) $page['parent_id'] : null;
        }
        $data = [
            'title' => $title,
            'slug' => $slug,
            'meta_description' => mb_substr(strip_tags($r->str('meta_description')), 0, 300),
            'meta_title' => mb_substr(strip_tags($r->str('meta_title')), 0, 120) ?: null,
            'status' => $r->str('status') === 'published' ? 'published' : 'draft',
            'noindex' => $r->str('noindex') === '1' ? 1 : 0,
            'og_image' => ctype_digit($og = (string) ($r->post['x']['og_image'] ?? '')) ? (int) $og : null,
            'parent_id' => $parentId,
            'menu' => $r->str('menu') === '1' ? 1 : 0,
            'nav_title' => mb_substr(strip_tags($r->str('nav_title')), 0, 60),
        ];
        if (!$page) {
            $l = $r->str('lang');
            $data['lang'] = \Core\Lang::valid($l) && $l !== \Core\Lang::default() ? $l : null;
        }
        $errors = [];
        if ($title === '') {
            $errors['title'] = 'Bitte einen Titel angeben.';
        }
        if (!$page || (!$page['is_home'] && !NotFound::isPage($page))) {
            $lang = $page ? ($page['lang'] ?: null) : ($data['lang'] ?? null);
            // Bestehende Seiten dürfen ihre (alte) Adresse behalten – das Formular warnt dann (self::reservedSlug)
            if (self::reservedSlug($slug, $parentId, $lang) && !($page && $slug === $page['slug'] && $parentId === ($page['parent_id'] ? (int) $page['parent_id'] : null))) {
                $errors['slug'] = __('Diese Adresse ist reserviert: Unter /{slug} liegen Dateien oder Funktionen des Systems (z. B. Medien, Assets, Verwaltung) – eine Seite wäre dort nicht erreichbar. Bitte eine andere Adresse wählen.', ['slug' => $slug]);
            } elseif (Pages::slugTaken($slug, $parentId, $page ? (int) $page['id'] : null, $page ? ($page['lang'] ?: null) : ($data['lang'] ?? null))) {
                $errors['slug'] = 'Auf dieser Ebene gibt es schon eine Seite mit dieser Adresse.';
            }
        }
        return [$data, $errors];
    }

    public function delete(Request $r, string $id): Response
    {
        $this->auth($r, 'pages.manage');
        $page = Pages::find((int) $id) ?? throw new HttpException(404);
        if ($page['is_home']) {
            return $this->back('/admin/pages', 'error', 'Die Startseite kann nicht gelöscht werden.');
        }
        app()->db->query('DELETE FROM revisions WHERE page_id = ?', [(int) $id]);
        app()->db->query('DELETE FROM pages WHERE id = ?', [(int) $id]);
        // Unterseiten rücken eine Ebene nach oben
        app()->db->query('UPDATE pages SET parent_id = ? WHERE parent_id = ?', [$page['parent_id'], (int) $id]);
        Pages::rebuildPaths();
        $this->changed();
        \Core\Extensions::emit(new \Core\Events\PageDeleted($page));   // Erweiterungen (Extension::on)
        if ($r->wantsJson()) {
            return Response::json(['ok' => true]);
        }
        return $this->back('/admin/pages', 'success', '„' . $page['title'] . '“ wurde gelöscht.');
    }

    public function publish(Request $r, string $id): Response
    {
        $user = $this->auth($r, 'pages.publish');
        try {
            Pages::publish((int) $id, (int) $user['id']);
        } catch (\RuntimeException $e) {   // offene Platzhalter „[bitte ergänzen: …]“
            if ($r->wantsJson()) return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
            return $this->back('/admin/pages', 'error', $e->getMessage());
        }
        if ($r->wantsJson()) {
            return Response::json(['ok' => true, 'state' => 'online', 'drafts' => \Core\Review\Drafts::count(), 'message' => __('„{title}“ ist online.', ['title' => (string) (Pages::find((int) $id)['title'] ?? '')])]);
        }
        return $this->back('/admin/pages', 'success', 'Seite veröffentlicht.');
    }

    /**
     * Offline nehmen (Seitenbaum, Werkzeugleiste): Status „Entwurf“, die veröffentlichte Fassung bleibt erhalten (Pages::unpublish).
     * Gegenstück „Online stellen“ ist publish() – mit Platzhalter-Sperre.
     */
    public function offline(Request $r, string $id): Response
    {
        $this->auth($r, 'pages.publish');
        $page = Pages::find((int) $id) ?? throw new HttpException(404);
        if (!Pages::unpublish((int) $id)) {
            $err = __('Die Startseite ist immer online.');
            if ($r->wantsJson()) return Response::json(['ok' => false, 'error' => $err], 422);
            return $this->back('/admin/pages', 'error', $err);
        }
        $msg = __('„{title}“ ist offline. Besucher sehen die Seite nicht mehr.', ['title' => $page['title']]);
        if ($r->wantsJson()) {
            return Response::json(['ok' => true, 'state' => Pages::state(Pages::find((int) $id)), 'drafts' => \Core\Review\Drafts::count(), 'message' => $msg]);
        }
        return $this->back('/admin/pages', 'success', $msg);
    }

    public function discard(Request $r, string $id): Response
    {
        $user = $this->auth($r, 'pages.edit');
        $page = Pages::find((int) $id) ?? throw new HttpException(404);
        if (!Pages::discardDraft((int) $id, (int) $user['id'])) {
            return $this->back('/admin/pages/' . $id, 'error', 'Diese Seite wurde noch nie veröffentlicht – es gibt keine Fassung, auf die zurückgesetzt werden kann.');
        }
        $this->changed();
        $to = match ($r->str('back')) { 'list' => '/admin/pages', 'page' => Pages::url($page), default => '/admin/pages/' . $id };
        if ($r->str('back') === 'page') {
            app()->session->flash('success', 'Entwurf verworfen.');
            return Response::redirect($to);
        }
        return $this->back($to, 'success', 'Entwurf von „' . $page['title'] . '“ verworfen. Der verworfene Stand ist unter „Versionen“ gesichert.');
    }

    public function restore(Request $r, string $id, string $rev): Response
    {
        $user = $this->auth($r, 'pages.edit');
        $row = app()->db->fetch('SELECT * FROM revisions WHERE id = ? AND page_id = ?', [(int) $rev, (int) $id]) ?? throw new HttpException(404);
        $blocks = json_decode((string) $row['blocks_json'], true)['blocks'] ?? [];
        Pages::saveDraft((int) $id, Pages::sanitizeBlocks($blocks), (int) $user['id'], 'Wiederhergestellt (Stand ' . $row['created_at'] . ')');
        return $this->back('/admin/pages/' . $id, 'success', 'Stand wiederhergestellt – als Entwurf. Prüfen und dann veröffentlichen.');
    }

    // ------------------------------------------------------------------ Seitenbaum (JSON)

    /** Verschieben: {parent_id: ?int, before_id?: int, after_id?: int, index?: int} – before_id/after_id (Nachbarseite) vor index */
    public function move(Request $r, string $id): Response
    {
        $this->auth($r, 'pages.manage');
        $parent = isset($r->post['parent_id']) && $r->post['parent_id'] !== null && $r->post['parent_id'] !== '' ? (int) $r->post['parent_id'] : null;
        $ref = fn(string $k) => isset($r->post[$k]) && (int) $r->post[$k] > 0 ? (int) $r->post[$k] : null;
        $err = Pages::move((int) $id, $parent, (int) ($r->post['index'] ?? 0), $ref('before_id'), $ref('after_id'));
        return $err ? Response::json(['ok' => false, 'error' => $err], 422) : Response::json(['ok' => true, 'url' => Pages::url(Pages::find((int) $id))]);
    }

    /** Schnelländerungen: menu (bool), status */
    public function quick(Request $r, string $id): Response
    {
        $this->auth($r, 'pages.manage');
        $page = Pages::find((int) $id) ?? throw new HttpException(404);
        $upd = [];
        if (array_key_exists('menu', $r->post)) $upd['menu'] = !empty($r->post['menu']) ? 1 : 0;
        if ($upd) {
            app()->db->update('pages', $upd, 'id = :id', ['id' => (int) $id]);
            $this->changed();
        }
        return Response::json(['ok' => true]);
    }

    /** Seite duplizieren (als Entwurf, direkt dahinter) */
    public function duplicate(Request $r, string $id): Response
    {
        $this->auth($r, 'pages.manage');
        $p = Pages::find((int) $id) ?? throw new HttpException(404);
        $slug = $p['slug'] . '-kopie';
        for ($n = 2; Pages::slugTaken($slug, $p['parent_id'] ? (int) $p['parent_id'] : null); $n++) $slug = $p['slug'] . '-kopie-' . $n;
        $blocks = Pages::blocks($p, true);
        $newId = Pages::create(['slug' => $slug, 'title' => $p['title'] . ' (Kopie)', 'status' => 'draft', 'parent_id' => $p['parent_id'],
            'meta_description' => $p['meta_description'], 'sort' => (int) $p['sort'] + 5, 'noindex' => $p['noindex']], $blocks);
        $this->changed();
        return Response::json(['ok' => true, 'id' => $newId]);
    }

    /** Übersetzung anlegen (JSON): {lang} → neue Seite als Entwurf */
    public function translate(Request $r, string $id): Response
    {
        $this->auth($r, 'pages.manage');
        try {
            $p = Pages::translate((int) $id, (string) ($r->post['lang'] ?? ''));
        } catch (\RuntimeException $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        $this->changed();
        return Response::json(['ok' => true, 'id' => (int) $p['id'], 'url' => Pages::url($p) . '?edit=1']);
    }
}
