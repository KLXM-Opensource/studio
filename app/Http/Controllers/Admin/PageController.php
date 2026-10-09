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
            // Detailseiten-Vorlagen – ohne 404-Seite und Seitenvorlagen (eigene Karten)
            'templates' => app()->db->fetchAll("SELECT * FROM pages WHERE type = 'template' AND COALESCE(template_for, '') NOT IN (?, ?) ORDER BY title", [NotFound::MARK, \Core\PageTemplates::MARK]),
            'pageTemplates' => can('system.manage') ? \Core\PageTemplates::all() : [],
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
        if (\Core\PageTemplates::isTemplatePage($page) && !can('system.manage')) throw new HttpException(403, __('Seitenvorlagen bearbeitet nur die Administration.'));
        // [Platzhalter] dieser Seite oben zeigen (Sprungziel „In der Verwaltung“ aus der Übersicht) – bearbeitet wird im Frontend-Editor
        $ph = \Core\Dashboard\Metrics::placeholders(app()->db, \Core\Dashboard\Metrics::placeholdersOk(), 500, (int) $page['id']);
        return $this->view('pages/form', ['page' => $page, 'errors' => [], 'old' => $page, 'revisions' => Pages::revisions((int) $id),
            'placeholders' => $ph, 'css' => $ph ? ['css/placeholders.css'] : []]);
    }

    public function update(Request $r, string $id): Response
    {
        $this->auth($r, 'pages.manage');
        $page = Pages::find((int) $id) ?? throw new HttpException(404);
        if (\Core\PageTemplates::isTemplatePage($page) && !can('system.manage')) throw new HttpException(403, __('Seitenvorlagen bearbeitet nur die Administration.'));
        [$data, $errors, $warn] = $this->saveSettings($r, $page);
        if ($errors) {
            return $this->view('pages/form', ['page' => $page, 'errors' => $errors, 'old' => $data + $page, 'revisions' => Pages::revisions((int) $id)], 422);
        }
        if ($warn !== null) app()->session->flash('error', $warn);
        return $this->back('/admin/pages/' . $id, 'success', 'Seiteneinstellungen gespeichert.');
    }

    /**
     * Seiteneinstellungen prüfen und speichern – ein Weg für das Formular der Verwaltung (update) und die Website
     * (apiSettingsSave, Werkzeug „Seiteneinstellungen“ Core\PageSettingsTool). Startseite: feste Adresse, immer online;
     * 404-Seite: feste Adresse, nie im Menü/Index. Status nur mit Recht pages.publish änderbar. Adressänderungen legen über
     * Pages::rebuildPaths() automatisch Weiterleitungen an (Core\Redirects), der Seiten-Cache wird geleert.
     * @return array{0: array, 1: array<string,string>, 2: ?string} [Daten, Fehler je Feld, Hinweis (z. B. nicht veröffentlicht)]
     */
    private function saveSettings(Request $r, array $page): array
    {
        $id = (int) $page['id'];
        [$data, $errors] = $this->validate($r, $page);
        if ($errors) return [$data, $errors, null];
        $warn = null;
        if ($page['is_home']) {
            $data['slug'] = $page['slug'];
            $data['status'] = 'published';
        }
        if (NotFound::isPage($page)) {   // Seite „Nicht gefunden“: feste Adresse, nie im Menü oder in Suchmaschinen
            $data = ['slug' => $page['slug'], 'parent_id' => null, 'menu' => 0, 'nav_title' => '', 'noindex' => 1] + $data;
        }
        if (!can('pages.publish')) $data['status'] = $page['status'];   // Online/Offline nur mit Recht „Veröffentlichen“
        if ($data['status'] === 'published' && $page['status'] !== 'published' && ($open = Pages::openMarkers($page['content_draft'] . ' ' . $data['title'] . ' ' . $data['meta_description']))) {
            $data['status'] = 'draft';   // offene Platzhalter „[bitte ergänzen: …]“ – nicht online stellen
            $warn = __('Nicht veröffentlicht: Die Seite enthält noch {n} Platzhalter, z. B. {list}. Bitte ergänzen oder entfernen.', ['n' => count($open), 'list' => implode(' · ', array_slice($open, 0, 3))]);
        }
        if ($data['status'] === 'published' && $page['content_published'] === null) {
            $data['content_published'] = $page['content_draft'];
            $data['published_at'] = now();
        }
        $data['updated_at'] = now();
        if ($page['is_home']) {
            $data['parent_id'] = null;
        }
        app()->db->update('pages', $data, 'id = :id', ['id' => $id]);
        Pages::rebuildPaths();
        $this->changed();
        \Core\Activity::log('settings', 'page', $id, (string) ($data['title'] ?? $page['title']), ['lang' => $page['lang'] ?? null]);   // Aktionslog
        return [$data, [], $warn];
    }

    /** Systemadressen ganz oben (Ordner in public/, feste Routen) – zentrale Liste: Core\PublicPaths::RESERVED_SLUGS */
    public const RESERVED_SLUGS = \Core\PublicPaths::RESERVED_SLUGS;

    /** Nur ohne übergeordnete Seite und ohne Sprachpräfix; dazu jeder Ordner bzw. jede Datei in public/ */
    public static function reservedSlug(string $slug, ?int $parentId, ?string $lang): bool
    {
        return $parentId === null && ($lang === null || $lang === \Core\Lang::default()) && \Core\PublicPaths::isReserved($slug);
    }

    // ------------------------------------------------------------------ Neue Seite von der Website aus (Werkzeug Core\PageTool)

    /** Seitenbaum für „Neue Seite“: flach in Baumreihenfolge, nur Seiten der Sprache der aktuellen Seite (ohne Vorlagen) */
    public function apiTree(Request $r): Response
    {
        $this->auth($r, 'pages.manage');
        $lang = \Core\Lang::valid($r->str('lang')) ? \Core\Lang::norm($r->str('lang')) : null;
        $tpls = \Core\PageTemplates::all();
        $out = [];
        $walk = function (array $nodes) use (&$walk, &$out, $tpls) {
            foreach ($nodes as $n) {
                $p = $n['page'];
                if (NotFound::isPage($p)) continue;
                $sug = null;   // Vorlage, die unter dieser Seite vorgeschlagen wird (Seitenvorlagen → „Vorschlagen unter“)
                foreach ($tpls as $t) if ($t['parents'] && \Core\PagePicker::matches($t['parents'], $p)) { $sug = $t['i']; break; }
                $out[] = ['id' => (int) $p['id'], 'parent' => $p['parent_id'] ? (int) $p['parent_id'] : null, 'depth' => $n['depth'],
                    'title' => (string) (($p['nav_title'] ?? '') ?: $p['title']), 'status' => (string) $p['status'], 'menu' => (bool) $p['menu'],
                    'home' => (bool) $p['is_home'], 'path' => '/' . ltrim((string) ($p['path'] ?? ''), '/'), 'suggest' => $sug];
                $walk($n['children']);
            }
        };
        $walk(Pages::tree(false, $lang));
        return Response::json(['ok' => true, 'pages' => $out,
            'templates' => array_map(fn($t) => ['i' => $t['i'], 'label' => $t['label'], 'description' => $t['description']], $tpls)]);
    }

    /**
     * Seite anlegen (JSON): title, slug (optional), parent (ID | leer = oberste Ebene), position 'end' | 'before' | 'after' mit
     * anchor (ID einer Seite derselben Ebene), template (Index | leer), menu (bool), lang. Immer als Entwurf; Antwort: Adresse im
     * Bearbeiten-Modus. Gleiche Prüfungen wie das Formular der Verwaltung (Titel, reservierte und doppelte Adressen).
     */
    public function apiCreate(Request $r): Response
    {
        $this->auth($r, 'pages.manage');
        $p = $r->post;
        $parent = (int) ($p['parent'] ?? 0);
        $parentPage = $parent ? Pages::find($parent) : null;
        if ($parent && (!$parentPage || $parentPage['type'] !== 'page')) return Response::json(['ok' => false, 'errors' => ['parent' => __('Übergeordnete Seite nicht gefunden.')]], 422);
        $lang = (string) ($p['lang'] ?? '');
        if ($parentPage) $lang = (string) ($parentPage['lang'] ?: \Core\Lang::default());
        $req = new Request('POST', $r->path, [], ['title' => (string) ($p['title'] ?? ''), 'slug' => (string) ($p['slug'] ?? ''),
            'parent_id' => $parentPage ? (string) $parent : '', 'menu' => !empty($p['menu']) ? '1' : '0', 'lang' => $lang, 'status' => 'draft'], [], $r->server);
        [$data, $errors] = $this->validate($req, null);
        if ($errors) return Response::json(['ok' => false, 'errors' => $errors], 422);
        $tpl = (string) ($p['template'] ?? '');
        $blocks = $tpl !== '' && ctype_digit($tpl) ? Pages::sanitizeBlocks(\Core\PageTemplates::blocks((int) $tpl)) : [];
        $sort = (int) app()->db->fetchValue('SELECT COALESCE(MAX(sort), 0) + 10 FROM pages WHERE ' . ($data['parent_id'] ? 'parent_id = ?' : 'parent_id IS NULL'), $data['parent_id'] ? [$data['parent_id']] : []);
        $id = Pages::create($data + ['sort' => $sort], $blocks);
        // Position zwischen Geschwistern (vor/nach einer Seite derselben Ebene); sonst am Ende
        $pos = (string) ($p['position'] ?? 'end');
        $anchor = (int) ($p['anchor'] ?? 0);
        if ($anchor && in_array($pos, ['before', 'after'], true)) {
            Pages::move($id, $data['parent_id'], 0, $pos === 'before' ? $anchor : null, $pos === 'after' ? $anchor : null);
        }
        $this->changed();
        $page = Pages::find($id);
        return Response::json(['ok' => true, 'id' => $id, 'url' => Pages::url($page) . '?edit=1', 'title' => $page['title']]);
    }

    // ------------------------------------------------------------------ Seiteneinstellungen auf der Website (Werkzeug Core\PageSettingsTool)

    /** Seite für die Seiteneinstellungen – nur echte Seiten bzw. die 404-Seite; Seitenvorlagen nur für die Administration */
    private function settingsPage(Request $r, string $id): array
    {
        $this->auth($r, 'pages.manage');
        $page = Pages::find((int) $id) ?? throw new HttpException(404);
        if (\Core\PageTemplates::isTemplatePage($page) && !can('system.manage')) throw new HttpException(403, __('Seitenvorlagen bearbeitet nur die Administration.'));
        if (($page['type'] ?? 'page') !== 'page' && !NotFound::isPage($page) && !\Core\PageTemplates::isTemplatePage($page)) throw new HttpException(404);
        return $page;
    }

    /** Werte und Hinweise für das Formular „Seiteneinstellungen“ auf der Website (JSON) */
    private function settingsJson(array $page): array
    {
        $nf = NotFound::isPage($page);
        $og = $page['og_image'] ? \Core\Media::find((int) $page['og_image']) : null;
        $url = Pages::url($page);
        $parent = $page['parent_id'] ? (int) $page['parent_id'] : null;
        return [
            'page' => ['id' => (int) $page['id'], 'title' => (string) $page['title'], 'slug' => (string) $page['slug'],
                'meta_title' => (string) ($page['meta_title'] ?? ''), 'meta_description' => (string) ($page['meta_description'] ?? ''),
                'og_image' => $og ? (int) $og['id'] : null, 'status' => (string) $page['status'], 'menu' => (bool) $page['menu'],
                'nav_title' => (string) ($page['nav_title'] ?? ''), 'noindex' => (bool) $page['noindex'],
                'home' => (bool) $page['is_home'], 'notFound' => $nf, 'published' => $page['content_published'] !== null],
            'og' => $og ? ['id' => (int) $og['id'], 'thumb' => str_starts_with((string) $og['mime'], 'image/') ? \Core\Media::url($og, 480) : null,
                'label' => (string) ($og['alt'] ?: \Core\Media::displayName($og))] : null,
            'url' => $url, 'prefix' => substr($url, 0, (int) strrpos(rtrim($url, '/'), '/') + 1),
            'reserved' => !$page['is_home'] && !$nf && self::reservedSlug((string) $page['slug'], $parent, $page['lang'] ?: null),
            // Adresse ändern: alte Adresse leitet automatisch weiter (Core\Redirects), sofern eingeschaltet und die Seite schon online war
            'autoRedirect' => \Core\Redirects\Redirects::enabled() && (bool) app()->settings->get(\Core\Redirects\Redirects::SET_AUTO, true),
            'canPublish' => can('pages.publish'),
            'ai' => \Core\AI\Assist::available('text'),
            'titleMax' => \Core\AI\Assist::titleBudget(), 'descMax' => 160,
            'suffix' => trim(\Core\AI\Assist::titleSuffix(), ' |'),
        ];
    }

    /** Seiteneinstellungen lesen (JSON) */
    public function apiSettings(Request $r, string $id): Response
    {
        $page = $this->settingsPage($r, $id);
        return Response::json(['ok' => true] + $this->settingsJson($page));
    }

    /**
     * Seiteneinstellungen speichern (JSON): title, slug, meta_title, meta_description, og_image (ID | null), status, menu,
     * nav_title, noindex – nur übergebene Felder ändern sich, der Rest bleibt (z. B. übergeordnete Seite). Gleicher Weg wie das
     * Formular der Verwaltung (saveSettings). Antwort: neue Werte, Adresse, Titel/Beschreibung/Vorschaubild für den <head>.
     */
    public function apiSettingsSave(Request $r, string $id): Response
    {
        $page = $this->settingsPage($r, $id);
        $p = $r->post;
        $post = ['title' => (string) $page['title'], 'slug' => (string) $page['slug'], 'meta_title' => (string) ($page['meta_title'] ?? ''),
            'meta_description' => (string) ($page['meta_description'] ?? ''), 'status' => (string) $page['status'],
            'noindex' => $page['noindex'] ? '1' : '0', 'parent_id' => $page['parent_id'] ? (string) $page['parent_id'] : '',
            'menu' => $page['menu'] ? '1' : '0', 'nav_title' => (string) ($page['nav_title'] ?? ''), 'x' => ['og_image' => (string) ($page['og_image'] ?? '')]];
        foreach (['title', 'slug', 'meta_title', 'meta_description', 'status', 'nav_title'] as $k) {
            if (array_key_exists($k, $p) && is_scalar($p[$k])) $post[$k] = (string) $p[$k];
        }
        foreach (['menu', 'noindex'] as $k) if (array_key_exists($k, $p)) $post[$k] = !empty($p[$k]) ? '1' : '0';
        if (array_key_exists('og_image', $p)) $post['x']['og_image'] = is_scalar($p['og_image']) ? (string) $p['og_image'] : '';
        $oldUrl = Pages::url($page);
        [, $errors, $warn] = $this->saveSettings(new Request('POST', $r->path, [], $post, [], $r->server), $page);
        if ($errors) return Response::json(['ok' => false, 'errors' => $errors, 'error' => implode(' ', $errors)], 422);
        $fresh = Pages::find((int) $page['id']);
        $out = $this->settingsJson($fresh);
        try {   // <head> der offenen Seite ohne Neuladen nachführen (Titel, Beschreibung, Vorschaubild)
            $seo = \Core\Seo::forPage($fresh);
            $out['seo'] = ['title' => $seo['title'], 'description' => $seo['description'], 'og_image' => $seo['og_image'], 'canonical' => $seo['canonical']];
        } catch (\Throwable $e) {
            $out['seo'] = null;
        }
        $moved = $out['url'] !== $oldUrl;
        $msg = __('Seiteneinstellungen gespeichert.');
        if ($moved) $msg .= ' ' . ($out['autoRedirect'] && ($fresh['status'] === 'published' || $fresh['published_at'] !== null) ? __('Die alte Adresse leitet auf die neue weiter.') : __('Neue Adresse: {url}', ['url' => $out['url']]));
        return Response::json(['ok' => true, 'message' => $msg, 'warning' => $warn, 'moved' => $moved,
            'state' => Pages::state($fresh), 'drafts' => \Core\Review\Drafts::count()] + $out);
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
        if (\Core\PageTemplates::isTemplatePage($page) && !can('system.manage')) throw new HttpException(403, __('Seitenvorlagen bearbeitet nur die Administration.'));
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
        // Versionen (JSON): Entwurf angelegt – die Seite öffnet danach im Editor
        if ($r->wantsJson()) return Response::json(['ok' => true, 'message' => __('Stand wiederhergestellt – als Entwurf. Prüfen und dann veröffentlichen.'), 'url' => Pages::url(Pages::find((int) $id)) . '?edit=1']);
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

    /**
     * Vorschau für die Seitenleiste im Seitenbaum (ohne Werkzeugleiste, noindex): ?stand=live = veröffentlichte Fassung,
     * sonst Arbeitsstand (Entwurf). Seitenvorlagen nur für die Administration.
     */
    public function preview(Request $r, string $id): Response
    {
        $this->auth($r, 'pages.edit');
        $page = Pages::find((int) $id) ?? throw new HttpException(404);
        if (\Core\PageTemplates::isTemplatePage($page) && !can('system.manage')) throw new HttpException(403);
        if (($page['type'] ?? 'page') === 'template' && !\Core\PageTemplates::isTemplatePage($page) && !NotFound::isPage($page)) throw new HttpException(404);
        $live = $r->str('stand') === 'live' && $page['content_published'] !== null;
        $site = new \Core\Http\Controllers\SiteController();
        return $site->respond($site->previewHtml($page, !$live), true)->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store, private');
    }

    /** Seitenbaum „⋯ → Nicht indexieren / Indexieren erlauben“ (JSON) – wie der Haken in den Seiteneinstellungen */
    public function noindex(Request $r, string $id): Response
    {
        $this->auth($r, 'pages.manage');
        $page = Pages::find((int) $id) ?? throw new HttpException(404);
        if ($page['is_home'] || ($page['type'] ?? 'page') !== 'page') return Response::json(['ok' => false, 'error' => __('Für diese Seite nicht möglich.')], 422);
        $on = empty($page['noindex']);
        app()->db->update('pages', ['noindex' => $on ? 1 : 0, 'updated_at' => now()], 'id = :id', ['id' => (int) $id]);
        $this->changed();
        return Response::json(['ok' => true, 'noindex' => $on]);
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
