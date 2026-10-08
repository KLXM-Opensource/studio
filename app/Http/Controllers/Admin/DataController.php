<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Data\Entries;
use Core\Data\Shared;
use Core\Data\Tables;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Pages;

/**
 * Daten: Tabellen ohne Programmieren anlegen (nur Administration) und Einträge pflegen (Redaktion).
 */
final class DataController extends AdminController
{
    /** Vorlagen für neue Tabellen */
    public const PRESETS = [
        'blog' => ['name' => 'Aktuelles', 'singular' => 'Beitrag', 'icon' => 'newspaper', 'route' => 'aktuelles', 'sort' => ['datum', 'desc'], 'fields' => [
            ['label' => 'Titel', 'type' => 'text', 'required' => 1, 'in_list' => 1, 'searchable' => 1],
            ['label' => 'Datum', 'type' => 'date', 'in_list' => 1, 'width' => 'half'],
            ['label' => 'Kategorie', 'type' => 'select', 'in_list' => 1, 'width' => 'half', 'options' => "Neuigkeiten\nHinweise\nVeranstaltungen"],
            ['label' => 'Teaser', 'type' => 'textarea', 'searchable' => 1, 'help' => 'Kurzer Text für Übersicht und Suchmaschinen (max. 2 Sätze).'],
            ['label' => 'Bild', 'type' => 'media'],
            ['label' => 'Text', 'type' => 'richtext', 'searchable' => 1],
        ], 'title' => 'titel', 'image' => 'bild', 'desc' => 'teaser'],
        'team' => ['name' => 'Team', 'singular' => 'Person', 'icon' => 'users-three', 'route' => 'team', 'sort' => ['sort', 'asc'], 'fields' => [
            ['label' => 'Name', 'type' => 'text', 'required' => 1, 'in_list' => 1, 'searchable' => 1],
            ['label' => 'Funktion', 'type' => 'text', 'in_list' => 1, 'width' => 'half'],
            ['label' => 'Foto', 'type' => 'media', 'width' => 'half'],
            ['label' => 'Über mich', 'type' => 'richtext'],
            ['label' => 'E-Mail', 'type' => 'email', 'width' => 'half'],
            ['label' => 'Telefon', 'type' => 'tel', 'width' => 'half'],
        ], 'title' => 'name', 'image' => 'foto', 'desc' => 'funktion'],
        'products' => ['name' => 'Produkte', 'singular' => 'Produkt', 'icon' => 'package', 'route' => 'produkte', 'sort' => ['name', 'asc'], 'fields' => [
            ['label' => 'Name', 'type' => 'text', 'required' => 1, 'in_list' => 1, 'searchable' => 1],
            ['label' => 'Preis', 'type' => 'number', 'in_list' => 1, 'width' => 'half', 'help' => 'In Euro, z. B. 19.90'],
            ['label' => 'Verfügbar', 'type' => 'bool', 'in_list' => 1, 'width' => 'half'],
            ['label' => 'Kategorie', 'type' => 'select', 'options' => "Allgemein"],
            ['label' => 'Bild', 'type' => 'media'],
            ['label' => 'Kurzbeschreibung', 'type' => 'textarea'],
            ['label' => 'Beschreibung', 'type' => 'richtext', 'searchable' => 1],
        ], 'title' => 'name', 'image' => 'bild', 'desc' => 'kurzbeschreibung'],
        'events' => ['name' => 'Termine', 'singular' => 'Termin', 'icon' => 'calendar-dots', 'route' => 'termine', 'sort' => ['beginn', 'asc'], 'fields' => [
            ['label' => 'Titel', 'type' => 'text', 'required' => 1, 'in_list' => 1, 'searchable' => 1],
            ['label' => 'Beginn', 'type' => 'datetime', 'required' => 1, 'in_list' => 1, 'width' => 'half'],
            ['label' => 'Ende', 'type' => 'datetime', 'width' => 'half'],
            ['label' => 'Ganztägig', 'type' => 'bool', 'width' => 'half'],
            ['label' => 'Wiederholung', 'type' => 'recurrence'],
            ['label' => 'Ort', 'type' => 'text', 'in_list' => 1],
            ['label' => 'Beschreibung', 'type' => 'richtext'],
        ], 'title' => 'titel', 'image' => '', 'desc' => 'ort',
            'calendar' => ['enabled' => true, 'start' => 'beginn', 'end' => 'ende', 'all_day' => 'ganztaegig', 'recurrence' => 'wiederholung',
                'location' => 'ort', 'description' => 'beschreibung', 'duration' => 60, 'feed' => true]],
        'faq' => ['name' => 'Fragen & Antworten', 'singular' => 'Frage', 'icon' => 'question', 'route' => '', 'sort' => ['sort', 'asc'], 'fields' => [
            ['label' => 'Frage', 'type' => 'text', 'required' => 1, 'in_list' => 1, 'searchable' => 1],
            ['label' => 'Antwort', 'type' => 'richtext', 'required' => 1, 'searchable' => 1],
            ['label' => 'Thema', 'type' => 'select', 'in_list' => 1, 'options' => "Allgemein"],
        ], 'title' => 'frage', 'image' => '', 'desc' => ''],
        // Eingang: verschlüsselte Anfragen (Core\Data\Inbox) – Einträge nur über das öffentliche Formular, lesen unter „Anfragen“
        'inbox' => ['name' => 'Anfragen', 'singular' => 'Anfrage', 'icon' => 'envelope-simple', 'route' => '', 'sort' => ['created_at', 'desc'], 'kind' => 'inbox',
            'purpose' => 'inbox', 'fields' => [
            ['label' => 'Vorname', 'type' => 'text', 'required' => 1, 'width' => 'half'],
            ['label' => 'Nachname', 'type' => 'text', 'required' => 1, 'width' => 'half'],
            ['label' => 'E-Mail', 'type' => 'email', 'width' => 'half'],
            ['label' => 'Telefon', 'type' => 'tel', 'width' => 'half'],
            ['label' => 'Anliegen', 'type' => 'textarea', 'required' => 1],
        ], 'title' => '', 'image' => '', 'desc' => ''],
        // Formulare, die nur eine E-Mail schicken: Eingang mit Zustellung „Nur per E-Mail“ (Core\Data\Delivery) – nichts gespeichert
        'contact' => ['name' => 'Kontakt', 'singular' => 'Nachricht', 'icon' => 'paper-plane-tilt', 'route' => '', 'sort' => ['created_at', 'desc'], 'kind' => 'inbox',
            'purpose' => 'mail', 'delivery' => ['mode' => 'mail'], 'form' => ['submit' => 'Nachricht senden', 'success' => 'Vielen Dank für Ihre Nachricht – wir melden uns bei Ihnen.'],
            'fields' => [
            ['label' => 'Name', 'type' => 'text', 'required' => 1],
            ['label' => 'E-Mail', 'type' => 'email', 'required' => 1, 'width' => 'half'],
            ['label' => 'Telefon', 'type' => 'tel', 'width' => 'half'],
            ['label' => 'Nachricht', 'type' => 'textarea', 'required' => 1],
        ], 'title' => '', 'image' => '', 'desc' => ''],
        'callback' => ['name' => 'Rückrufbitte', 'singular' => 'Rückrufbitte', 'icon' => 'phone', 'route' => '', 'sort' => ['created_at', 'desc'], 'kind' => 'inbox',
            'purpose' => 'mail', 'delivery' => ['mode' => 'mail'], 'form' => ['submit' => 'Rückruf anfordern', 'success' => 'Vielen Dank – wir rufen Sie zurück.'],
            'fields' => [
            ['label' => 'Name', 'type' => 'text', 'required' => 1],
            ['label' => 'Telefon', 'type' => 'tel', 'required' => 1, 'width' => 'half'],
            ['label' => 'Am besten erreichbar', 'type' => 'select', 'width' => 'half', 'options' => "vormittags=Vormittags\nnachmittags=Nachmittags\negal=Egal"],
            ['label' => 'Anliegen', 'type' => 'textarea'],
        ], 'title' => '', 'image' => '', 'desc' => ''],
        // Anmeldung: öffentliches Formular, Einträge als Entwurf = Teilnehmerliste in der Verwaltung (nicht verschlüsselt)
        'registration' => ['name' => 'Anmeldungen', 'singular' => 'Anmeldung', 'icon' => 'clipboard-text', 'route' => '', 'sort' => ['created_at', 'asc'],
            'purpose' => 'registration', 'form' => ['submit' => 'Verbindlich anmelden', 'success' => 'Vielen Dank – Ihre Anmeldung ist eingegangen.'], 'fields' => [
            ['label' => 'Vorname', 'type' => 'text', 'required' => 1, 'in_list' => 1, 'width' => 'half'],
            ['label' => 'Nachname', 'type' => 'text', 'required' => 1, 'in_list' => 1, 'width' => 'half'],
            ['label' => 'E-Mail', 'type' => 'email', 'required' => 1, 'in_list' => 1, 'width' => 'half'],
            ['label' => 'Telefon', 'type' => 'tel', 'width' => 'half'],
            ['label' => 'Personen', 'type' => 'number', 'width' => 'half', 'help' => 'Wie viele Personen melden Sie an?'],
            ['label' => 'Bemerkung', 'type' => 'textarea'],
        ], 'title' => 'nachname', 'image' => '', 'desc' => ''],
        // Interne Liste: nur in der Verwaltung (keine Detailseiten, nicht in Suche/Sitemap)
        'internal' => ['name' => 'Interne Liste', 'singular' => 'Eintrag', 'icon' => 'list-checks', 'route' => '', 'sort' => ['faellig_am', 'asc'], 'purpose' => 'internal', 'fields' => [
            ['label' => 'Titel', 'type' => 'text', 'required' => 1, 'in_list' => 1, 'searchable' => 1],
            ['label' => 'Zuständig', 'type' => 'text', 'in_list' => 1, 'width' => 'half'],
            ['label' => 'Fällig am', 'type' => 'date', 'in_list' => 1, 'width' => 'half'],
            ['label' => 'Stand', 'type' => 'select', 'in_list' => 1, 'options' => "offen=Offen\nin_arbeit=In Arbeit\nerledigt=Erledigt"],
            ['label' => 'Notiz', 'type' => 'textarea'],
        ], 'title' => 'titel', 'image' => '', 'desc' => ''],
    ];

    /**
     * Vorlagen, die auf dieser Website angeboten werden (Eingang nur mit Funktion „requests“). Dazu „Stellenangebote“
     * (Google for Jobs, Core\Data\Jobs) nach „Termine“ und der passende Eingang „Bewerbungen“.
     */
    public static function presets(): array
    {
        $all = [];
        foreach (self::PRESETS as $k => $p) {
            $all[$k] = $p;
            if ($k === 'events') $all['jobs'] = \Core\Data\Jobs::preset();
        }
        $all['applications'] = \Core\Data\Jobs::applicationsPreset() + ['purpose' => 'registration'];
        // Eingänge nur mit Funktion „requests“, „nur per E-Mail“ zusätzlich mit „requests.mail“, Anmeldung mit Formularen für Datentabellen
        return array_filter($all, fn($p) => match (true) {
            ($p['kind'] ?? 'content') === 'inbox' && ($p['delivery']['mode'] ?? '') === 'mail' && ($p['purpose'] ?? '') === 'mail' => \Core\Data\Delivery::available(),
            ($p['kind'] ?? 'content') === 'inbox' => \Core\Data\Inbox::available(),
            isset($p['form']) => \Core\Data\DataForms::available(),
            default => true,
        });
    }

    /** Eingangs-Tabellen haben keine Eintragsverwaltung hier – sie werden unter „Anfragen“ gelesen */
    private function noInbox(array $t, Request $r): void
    {
        if (!Tables::isInbox($t)) return;
        if ($r->isPost()) throw new HttpException(403, __('Eingangs-Tabelle: Einträge entstehen nur über das öffentliche Formular (verschlüsselt).'));
        throw new RedirectException(url('/admin/requests?table=' . rawurlencode($t['handle'])));
    }

    /**
     * Alle Daten-Seiten (außer Eingängen) bekommen die Bereichsnavigation „Daten“ (views/data/_nav.php):
     * das Layout stellt sie links anstelle der Hauptnavigation dar (Drill-down, resources/js/_drill.js).
     */
    protected function view(string $view, array $vars = [], int $status = 200): Response
    {
        $t = $vars['t'] ?? $vars['table'] ?? null;
        if (!$t || !Tables::isInbox($t)) {
            $cur = match ($view) {
                'data/index' => 'index',
                'data/entries' => empty($vars['cal']) ? 'list' : 'calendar',
                'data/entry' => $vars['e'] === null ? 'new-entry' : 'entry',
                'data/schema' => $t ? 'schema' : 'new',
                'data/wizard' => 'new',
                'data/place' => 'place',
                // Geteilte Tabellen: fremde Einträge, Vorschläge, Anzeige-Einstellungen
                'data/foreign' => 'src-' . ($vars['src'] ?? ''),
                'data/foreign_entry' => 'src-' . ($vars['src'] ?? 'owner'),
                'data/display' => 'display',
                'data/external_entry' => 'entry',
                default => '',
            };
            $vars['drill'] ??= \Core\Theme::capture(ROOT . '/app/Admin/views/data/_nav.php', ['cur' => $cur, 'active' => $t]);
            $vars['drillTitle'] ??= __('Daten');
        }
        return parent::view($view, $vars, $status);
    }

    private function table(string $handle): array
    {
        return Tables::find($handle) ?? throw new HttpException(404);
    }

    // ================================================================= Übersicht & Tabellen-Designer

    public function index(Request $r): Response
    {
        $this->auth($r);
        $tables = array_map(fn($t) => $t + ['count' => Entries::count($t, ['status' => 'all', 'source' => 'own'])],
            array_values(array_filter(Tables::content(), fn($t) => can('data.edit', $t['handle']) || can('data.schema'))));
        // Eingangs-Tabellen: nur Anzahl (Metadaten); Felder ändern mit data.schema, lesen unter „Anfragen“
        $inboxes = array_map(fn($t) => $t + ['count' => \Core\Data\Inbox::count($t), 'new' => \Core\Data\Inbox::count($t, 'neu')],
            array_values(array_filter(\Core\Data\Inbox::tables(), fn($t) => can('data.schema') || can('requests.read', $t['handle']))));
        return $this->view('data/index', ['tables' => $tables, 'inboxes' => $inboxes, 'presets' => self::presets()]);
    }

    /**
     * Neue Tabelle: Assistent (Zweck → Vorlage → Grundeinstellungen → Einsetzen, Core\Data\Wizard). ?expert=1 = bisheriger
     * Tabellen-Designer („Ohne Assistent“), ?preset=… (Vorlagen der Übersicht) springt mit Zweck und Vorlage zu den Grundeinstellungen.
     * Der Zustand liegt in der Sitzung; ohne ?schritt beginnt der Assistent neu.
     */
    public function create(Request $r): Response
    {
        $this->auth($r, 'data.schema');
        if ($r->str('expert') === '1') {
            return $this->view('data/schema', ['table' => null, 'def' => self::presetDef($r->str('preset')), 'errors' => []]);
        }
        $step = $r->str('schritt');
        if ($step === '' || !in_array($step, ['art', 'vorlage', 'einstellungen'], true)) {
            $st = [];
            $preset = $r->str('preset');
            if ($preset !== '' && isset(self::presets()[$preset])) {
                $st = \Core\Data\Wizard::start(\Core\Data\Purpose::ofPreset(self::presets()[$preset]), $preset);
                $step = 'einstellungen';
            } else {
                $step = 'art';
            }
            app()->session->set(self::WIZARD, $st);
        }
        return $this->wizardView($step, (array) app()->session->get(self::WIZARD, []));
    }

    /** Sitzungsschlüssel des Assistenten */
    private const WIZARD = 'dt_wizard';

    private function wizardView(string $step, array $st, array $errors = [], int $status = 200): Response
    {
        // Ohne Zweck bzw. Vorlage gibt es die späteren Schritte nicht (z. B. Lesezeichen, abgelaufene Sitzung)
        if ($step !== 'art' && empty($st['purpose'])) $step = 'art';
        if ($step === 'einstellungen' && !isset($st['fields'])) $step = 'vorlage';
        return $this->view('data/wizard', ['step' => $step, 'st' => $st, 'errors' => $errors, 'presets' => self::presets()], $status);
    }

    /**
     * Schritte des Assistenten absenden (ohne JavaScript bedienbar): step = aktueller Schritt, go = next | back | ai | add | up:N | down:N.
     */
    public function wizard(Request $r): Response
    {
        $this->auth($r, 'data.schema');
        $st = (array) app()->session->get(self::WIZARD, []);
        $step = $r->str('step');
        $go = $r->str('go') ?: 'next';
        $W = \Core\Data\Wizard::class;
        if ($step === 'art') {
            if ($go === 'ai') {
                // KLXM AI: Beschreibung → Zweck (Wortstämme) + Felder (Tabellen-Generator) → gleich zu den Grundeinstellungen
                if (!\Core\AI\Assist::available('text')) return $this->wizardView('art', $st, ['ai' => __('Der KI-Assistent ist für Sie auf dieser Website nicht verfügbar.')], 403);
                $text = trim($r->str('ai_text'));
                try {
                    $res = \Core\AI\Generator::proposeTable($text);
                    \Core\AI\Assist::log('tablegen', 'wizard', __('Assistent „Neue Tabelle“'));
                } catch (\Core\AI\AiException $e) {
                    return $this->wizardView('art', $st + ['ai' => $text], ['ai' => $e->getMessage()], 422);
                }
                $st = $W::fromAi((array) $res['def'], $text);
                app()->session->set(self::WIZARD, $st);
                return Response::redirect(url('/admin/data/new?schritt=einstellungen'));
            }
            $purpose = $r->str('purpose');
            if (!\Core\Data\Purpose::valid($purpose)) return $this->wizardView('art', $st, ['purpose' => __('Bitte wählen Sie, was Sie anlegen möchten.')], 422);
            if ($purpose === 'source') return Response::redirect(url('/admin/quellen/new'));
            if ($why = \Core\Data\Purpose::unavailable($purpose)) return $this->wizardView('art', $st, ['purpose' => $why], 422);
            if (($st['purpose'] ?? '') !== $purpose) $st = ['purpose' => $purpose];
            app()->session->set(self::WIZARD, $st);
            return Response::redirect(url('/admin/data/new?schritt=vorlage'));
        }
        if (empty($st['purpose'])) return Response::redirect(url('/admin/data/new'));
        if ($step === 'vorlage') {
            if ($go === 'back') return Response::redirect(url('/admin/data/new?schritt=art'));
            $preset = $r->str('preset');
            $keys = \Core\Data\Purpose::presetKeys($st['purpose'], self::presets());
            if ($preset !== '_empty' && !in_array($preset, $keys, true)) return $this->wizardView('vorlage', $st, ['preset' => __('Bitte eine Vorlage wählen oder leer beginnen.')], 422);
            $preset = $preset === '_empty' ? '' : $preset;
            // Gleiche Vorlage wie zuvor: bisherige Eingaben behalten
            if (!isset($st['fields']) || ($st['preset'] ?? null) !== $preset) $st = $W::start($st['purpose'], $preset);
            app()->session->set(self::WIZARD, $st);
            return Response::redirect(url('/admin/data/new?schritt=einstellungen'));
        }
        if ($step === 'einstellungen' && isset($st['fields'])) {
            $st = $W::apply($st, $r->post, $go);
            app()->session->set(self::WIZARD, $st);
            if ($go === 'back') return Response::redirect(url('/admin/data/new?schritt=' . ($st['preset'] === '_ai' ? 'art' : 'vorlage')));
            if ($go !== 'next') return Response::redirect(url('/admin/data/new?schritt=einstellungen') . (preg_match('~^(up|down):(\d+)$~', $go, $m) ? '#wf-' . ($m[1] === 'up' ? (int) $m[2] - 1 : (int) $m[2] + 1) : '#wf-new'));
            $res = $W::create($st);
            if ($res['errors']) return $this->wizardView('einstellungen', $st, $res['errors'], 422);
            app()->session->forget(self::WIZARD);
            $this->changed();
            $t = $res['table'];
            app()->session->set('dt_wizard_done', ['handle' => $t['handle'], 'list' => !empty($st['list'])]);
            return $this->back('/admin/data/' . $t['handle'] . '/einsetzen?neu=1', 'success', __('„{name}“ ist angelegt. Jetzt noch einsetzen – dann ist es auf der Website.', ['name' => $t['name']]));
        }
        return Response::redirect(url('/admin/data/new'));
    }

    // ================================================================= Einsetzen (Core\Data\Placement)

    /** Wie kommt die Tabelle auf die Website? Block, Seite anlegen, in bestehende Seite einfügen, Verwendungen. ?neu=1: Schritt 4 */
    public function place(Request $r, string $handle): Response
    {
        $this->auth($r);
        $t = $this->table($handle);
        if (!can('data.schema') && !can('data.edit', $handle) && !(Tables::isInbox($t) && can('requests.manage', $handle))) throw new HttpException(403);
        $done = (array) app()->session->get('dt_wizard_done', []);
        $new = $r->str('neu') === '1' && ($done['handle'] ?? '') === $t['handle'];
        return $this->view('data/place', ['t' => $t, 'new' => $new, 'usages' => \Core\Data\Placement::usages($t), 'errors' => [], 'old' => []]);
    }

    public function placeSave(Request $r, string $handle): Response
    {
        $this->auth($r);
        $t = $this->table($handle);
        $action = $r->str('action');
        $uid = (int) (app()->auth->user()['id'] ?? 0) ?: null;
        $publish = $r->str('publish') === '1' && can('pages.publish');
        $back = '/admin/data/' . $handle . '/einsetzen' . ($r->str('neu') === '1' ? '?neu=1' : '');
        if ($action === 'page') {
            if (!can('pages.manage')) throw new HttpException(403, __('Seiten anlegen darf Ihre Rolle nicht.'));
            $res = \Core\Data\Placement::page($t, ['title' => $r->str('title'), 'parent' => $r->str('parent'), 'menu' => $r->str('menu') === '1',
                'publish' => $publish, 'template' => $r->str('template') === '1'], $uid);
        } elseif ($action === 'append') {
            if (!can('pages.edit') && !can('pages.manage')) throw new HttpException(403, __('Seiten bearbeiten darf Ihre Rolle nicht.'));
            $res = \Core\Data\Placement::append($t, $r->str('target'), $uid, $publish);
        } else {
            throw new HttpException(422);
        }
        if ($res['errors']) {
            app()->session->flash('error', __('Bitte prüfen: {msg}', ['msg' => implode(' ', $res['errors'])]));
            $done = (array) app()->session->get('dt_wizard_done', []);
            return $this->view('data/place', ['t' => $this->table($handle), 'new' => $r->str('neu') === '1' && ($done['handle'] ?? '') === $handle,
                'usages' => \Core\Data\Placement::usages($t), 'errors' => $res['errors'], 'old' => $r->post + ['action' => $action]], 422);
        }
        $this->changed();
        $page = Pages::find((int) $res['id']);
        app()->session->set('dt_placed', ['handle' => $handle, 'page' => (int) $res['id']]);
        $msg = $action === 'page'
            ? ($publish ? __('Seite „{title}“ angelegt und veröffentlicht.', ['title' => $page['title']]) : __('Seite „{title}“ als Entwurf angelegt – prüfen und veröffentlichen.', ['title' => $page['title']]))
            : ($publish ? __('Block in „{title}“ eingefügt und veröffentlicht.', ['title' => $page['title']]) : __('Block in „{title}“ eingefügt (Entwurf) – prüfen und veröffentlichen.', ['title' => $page['title']]));
        return $this->back($back, 'success', $msg);
    }

    /** Definition einer neuen Tabelle aus einer Vorlage (leer = nur Titel) – auch für geteilte Tabellen (Grundeinstellungen) */
    public static function presetDef(string $preset): array
    {
        $p = self::presets()[$preset] ?? null;
        $def = ['name' => $p['name'] ?? '', 'singular' => $p['singular'] ?? '', 'icon' => $p['icon'] ?? 'table', 'handle' => '',
            'description' => '', 'fields' => $p ? $p['fields'] : [['label' => 'Titel', 'type' => 'text', 'required' => 1, 'in_list' => 1, 'searchable' => 1]],
            'settings' => ['route' => $p['route'] ?? '', 'title_field' => $p['title'] ?? '', 'image_field' => $p['image'] ?? '',
                'description_field' => $p['desc'] ?? '', 'sort_field' => $p['sort'][0] ?? 'sort', 'sort_dir' => $p['sort'][1] ?? 'asc', 'workflow' => true,
                'calendar' => \Core\Features::on('calendar') ? ($p['calendar'] ?? []) : [], 'kind' => $p['kind'] ?? 'content',
                'purpose' => $p ? \Core\Data\Purpose::ofPreset($p) : 'content']];
        if (!empty($p['schema_type'])) $def['settings']['schema_type'] = $p['schema_type'];
        if (isset($p['jobs'])) $def['settings']['jobs'] = $p['jobs'] + \Core\Data\Jobs::DEFAULTS;
        if (($p['kind'] ?? '') === 'inbox') {
            $def['settings']['form'] = ['enabled' => true] + (array) ($p['form'] ?? []) + \Core\Data\DataForms::DEFAULTS;
            $def['settings']['inbox'] = (array) ($p['inbox'] ?? []) + \Core\Data\Inbox::DEFAULTS;
            if (isset($p['delivery'])) $def['settings']['inbox']['delivery'] = (array) $p['delivery'] + \Core\Data\Delivery::DEFAULTS;
        } elseif (isset($p['form'])) {
            // Inhaltstabelle mit öffentlichem Formular (Anmeldung): Einträge als Entwurf
            $def['settings']['form'] = ['enabled' => true, 'status' => 'draft'] + (array) $p['form'] + \Core\Data\DataForms::DEFAULTS;
        }
        if (in_array($def['settings']['purpose'], ['internal', 'registration'], true) && ($p['kind'] ?? 'content') !== 'inbox') {
            $def['settings']['noindex'] = true;
            $def['settings']['search'] = ['enabled' => '0'];
        }
        foreach ($def['fields'] as &$f) {
            $f['name'] = Tables::normName($f['label']);
        }
        unset($f);
        return $def;
    }

    public function store(Request $r): Response
    {
        $this->auth($r, 'data.schema');
        [$def, $errors] = Tables::validate($r->post);
        if ($errors) {
            return $this->view('data/schema', ['table' => null, 'def' => $def, 'errors' => $errors], 422);
        }
        Tables::create($def);
        $t = Tables::find($def['handle']);
        // Stellenangebote: Bewerbungs-Eingang „Bewerbungen“ anlegen bzw. vorhandenen nutzen (Auswahl „neu anlegen“)
        if (\Core\Data\Jobs::is($t) && \Core\Data\Jobs::config($t)['form'] === '_new') {
            $form = \Core\Data\Jobs::ensureForm('bewerbungen');
            \Core\Data\Jobs::linkForm($t, $form['handle'] ?? '');
            $t = Tables::find($def['handle']);
        }
        if (Tables::isInbox($t)) {
            return $this->back('/admin/data/' . $def['handle'] . '/schema', 'success', __('Eingang „{name}“ angelegt. Formular mit dem Block „Formular (Datentabelle)“ auf einer Seite einfügen – eingegangene Anfragen lesen Sie unter „Anfragen“.', ['name' => $def['name']]));
        }
        if ($r->str('with_template') === '1' && $def['settings']['route'] !== '') {
            $this->makeTemplate($t);
        }
        return $this->back('/admin/data/' . $def['handle'], 'success', 'Tabelle „' . $def['name'] . '“ angelegt. Jetzt den ersten Eintrag anlegen.');
    }

    public function schema(Request $r, string $handle): Response
    {
        $this->auth($r, 'data.schema');
        $t = $this->table($handle);
        $this->schemaGuard($t);
        return $this->view('data/schema', ['table' => $t, 'def' => self::shownPurpose($t), 'errors' => []]);
    }

    /**
     * Zweck für die Anzeige: Ältere Tabellen ohne gespeicherten Zweck, die abgeleitet „intern“ wären, aber auf der Website als
     * Datenliste o. Ä. vorkommen (z. B. Fragen & Antworten ohne Detailseiten), gelten als „Inhalte“ – gespeichert beim nächsten Speichern.
     */
    private static function shownPurpose(array $t): array
    {
        if (!empty($t['purpose_derived']) && $t['settings']['purpose'] === 'internal' && \Core\Data\Placement::usages($t)) $t['settings']['purpose'] = 'content';
        return $t;
    }

    /** Geteilte Tabellen: Felder ändert nur die Eigentümer-Website (bzw. ein Integrator) */
    private function schemaGuard(array $t): void
    {
        if (!Shared::canSchema($t)) {
            throw new HttpException(403, __('Die Felder dieser geteilten Tabelle legt die Website „{site}“ fest.', ['site' => Shared::siteInfo($t['shared']['owner'], $t['shared']['key'])['name']]));
        }
    }

    public function update(Request $r, string $handle): Response
    {
        $this->auth($r, 'data.schema');
        $t = $this->table($handle);
        $this->schemaGuard($t);
        [$def, $errors, $askDrop] = self::saveSchema($t, $r->post, $r->str('confirm_drop') === '1');
        if ($errors) {
            return $this->view('data/schema', ['table' => $t, 'def' => $def + ['handle' => $t['handle']], 'errors' => $errors, 'askDrop' => $askDrop], 422);
        }
        $t = $this->table($handle);
        if (\Core\Data\Jobs::is($t) && \Core\Data\Jobs::config($t)['form'] === '_new') {
            \Core\Data\Jobs::linkForm($t, \Core\Data\Jobs::ensureForm('bewerbungen')['handle'] ?? '');
        }
        $this->changed();
        // Zurück in den zuletzt offenen Bereich (Seitenleiste „Felder & Einstellungen“)
        $tab = preg_match('~^[a-z]{3,20}$~', $r->str('_tab')) ? '#' . $r->str('_tab') : '';
        return $this->back('/admin/data/' . $handle . '/schema' . $tab, 'success', 'Tabelle gespeichert.');
    }

    /**
     * Felder und Einstellungen einer bestehenden Tabelle prüfen und speichern – gemeinsamer Weg des Tabellen-Designers und der
     * Seitenleiste „Felder bearbeiten“ im Seiten-Editor (FormFieldsController). Fallen Felder samt Inhalten weg, wird erst mit
     * $confirmDrop gespeichert (sonst Fehler „_drop“ und $askDrop = true).
     * @return array{0: array, 1: array, 2: bool} [def, errors, askDrop]
     */
    public static function saveSchema(array $t, array $in, bool $confirmDrop): array
    {
        [$def, $errors] = Tables::validate($in, $t);
        if ($errors) return [$def, $errors, false];
        $dropped = Tables::droppedFields($t, $def);
        if ($dropped && !$confirmDrop) {
            $errors['_drop'] = __('Beim Speichern werden diese Felder samt Inhalten gelöscht: {fields}. Zum Bestätigen „Felder wirklich löschen“ anhaken.',
                ['fields' => implode(', ', array_column($dropped, 'label'))]);
            return [$def, $errors, true];
        }
        Tables::update($t, $def);
        return [$def, [], false];
    }

    /** Eingang → Zustellung: Testmail mit erfundener Anfrage an die gespeicherten Empfänger (Core\Data\Delivery::test) */
    public function deliveryTest(Request $r, string $handle): Response
    {
        $this->auth($r, 'data.schema');
        $t = $this->table($handle);
        if (!Tables::isInbox($t)) throw new HttpException(404);
        $this->auth($r, 'requests.manage', $t['handle']);
        $back = '/admin/data/' . $handle . '/schema#zustellung';
        if (!\Core\Data\Delivery::mails($t)) {
            return $this->back($back, 'error', __('Die gespeicherte Einstellung stellt nicht per E-Mail zu – bitte zuerst „Im System und per E-Mail“ oder „Nur per E-Mail“ wählen und speichern.'));
        }
        $res = \Core\Data\Delivery::test($t);
        return $res['ok']
            ? $this->back($back, 'success', __('Testmail an {to} gesendet{smime} (Message-ID {id}).', ['to' => implode(', ', $res['to']),
                'smime' => $res['smime'] ? ' – ' . __('S/MIME-verschlüsselt') : '', 'id' => '<' . $res['message_id'] . '>']))
            : $this->back($back, 'error', __('Testmail nicht gesendet: {error}', ['error' => (string) $res['error']]));
    }

    public function destroy(Request $r, string $handle): Response
    {
        $this->auth($r, 'data.schema');
        $t = $this->table($handle);
        if (Tables::isShared($t)) {
            return $this->back('/admin/data/' . $handle . '/schema', 'error', __('Geteilte Tabellen löscht man nicht hier: Mitglieder entfernen bzw. die Freigabe beenden unter Grundeinstellungen → Geteilte Daten.'));
        }
        if ($r->str('confirm') !== $t['handle']) {
            return $this->back('/admin/data/' . $handle . '/schema', 'error', 'Zum Löschen bitte den Kurznamen „' . $t['handle'] . '“ eintippen.');
        }
        Tables::delete($t);
        return $this->back('/admin/data', 'success', 'Tabelle „' . $t['name'] . '“ samt Einträgen gelöscht.');
    }

    /** Detailseiten-Vorlage anlegen und im Editor öffnen */
    public function template(Request $r, string $handle): Response
    {
        $this->auth($r, 'data.schema');
        $t = $this->table($handle);
        $this->noInbox($t, $r);
        if ($t['settings']['route'] === '') {
            return $this->back('/admin/data/' . $handle . '/schema', 'error', 'Bitte zuerst eine Adresse (URL-Basis) festlegen.');
        }
        $tplId = $t['settings']['detail_page_id'] && Pages::find((int) $t['settings']['detail_page_id']) ? (int) $t['settings']['detail_page_id'] : $this->makeTemplate($t);
        $t = $this->table($handle);
        $first = Entries::query($t, ['status' => 'all', 'limit' => 1, 'source' => 'site'])[0] ?? null;
        if (!$first) {
            return $this->back('/admin/data/' . $handle . '/new', 'success', 'Vorlage angelegt. Legen Sie einen ersten Eintrag an – dann können Sie die Detailseite mit echten Inhalten gestalten.');
        }
        return Response::redirect((string) Entries::url($t, $first) . '?edit=1');
    }

    public static function makeTemplate(array $t): int
    {
        if (\Core\Data\Jobs::is($t)) return \Core\Data\Jobs::makeTemplate($t);   // Stellen: Eckdaten + Bewerbungsformular
        // Kopf: Titel, Datum (erstes Datumsfeld, sonst Veröffentlichung), Auswahlfelder, Bild – darunter die übrigen Felder
        $h = $t['handle'] . '.';
        $st = $t['settings'];
        $date = null;
        $selects = [];
        foreach ($t['fields'] as $f) {
            if ($f['type'] === 'date' && !$date) $date = $f['name'];
            if ($f['type'] === 'select') $selects[] = $f['name'];
        }
        // Kalender: Zeitraum + Wiederholung (_when) statt einzelner Datumsfelder
        $cal = \Core\Data\Calendar::enabled($t) ? \Core\Data\Calendar::config($t) : null;
        $head = array_merge([$h . '_title', $h . ($cal ? '_when' : ($date ?? 'published_at'))], array_map(fn($n) => $h . $n, $selects),
            $st['image_field'] ? [$h . $st['image_field']] : []);
        $used = array_merge([$st['title_field'], $st['image_field'], $date], $selects,
            $cal ? [$cal['start'], $cal['end'], $cal['all_day'], $cal['recurrence']] : []);
        $body = array_map(fn($f) => $h . $f['name'], array_values(array_filter($t['fields'], fn($f) => !in_array($f['name'], $used, true))));
        $blocks = [
            ['id' => bin2hex(random_bytes(5)), 'type' => 'data_fields', 'data' => ['table' => $t['handle'], 'layout' => 'head', 'fields' => $head,
                'back_label' => 'Zur Übersicht', 'back_link' => '/' . $st['route'], 'ratio' => '16:9'],
                'tunes' => ['section' => ['spaceBottom' => 'small']]],
            ['id' => bin2hex(random_bytes(5)), 'type' => 'data_fields', 'data' => ['table' => $t['handle'], 'layout' => 'prose', 'fields' => $body, 'ratio' => '16:9'],
                'tunes' => ['section' => ['spaceTop' => 'none']]],
        ];
        $id = Pages::create(['slug' => '_vorlage-' . $t['handle'], 'title' => $t['name'] . ' – Detailseite', 'type' => 'template',
            'template_for' => $t['handle'], 'status' => 'published', 'noindex' => 0], Pages::sanitizeBlocks($blocks));
        Tables::setDetailPage($t, $id);   // geteilte Tabellen: Vorlage gehört dieser Website
        return $id;
    }

    // ================================================================= Einträge

    public function entries(Request $r, string $handle): Response
    {
        $this->auth($r);
        $t = $this->table($handle);
        $this->noInbox($t, $r);
        if (!can('data.edit', $handle)) throw new HttpException(403, __('Für diese Tabelle fehlt Ihrer Rolle die Berechtigung.'));
        $status = in_array($r->str('status'), ['published', 'draft'], true) ? $r->str('status') : 'all';
        $sort = $r->str('sort') ?: $t['settings']['sort_field'];
        $dir = $r->str('dir') ?: $t['settings']['sort_dir'];
        $per = (int) $t['settings']['per_page'];
        $page = max(1, (int) $r->str('seite'));
        $lang = \Core\Lang::valid($r->str('lang')) ? $r->str('lang') : \Core\Lang::default();
        // Geteilte Tabellen: hier nur die eigenen Einträge (fremde unter „Vom …“/„Von …“, siehe foreign())
        $o = ['status' => $status, 'q' => $r->str('q'), 'sort' => $sort, 'dir' => $dir, 'lang' => $lang, 'source' => 'own'];
        // Kalender-Tabellen: Ansicht „Liste | Kalender“ (?view=…, je Tabelle in der Sitzung gemerkt)
        $cal = null;
        if (\Core\Data\Calendar::enabled($t)) {
            $key = 'dt_view.' . $t['handle'];
            if (in_array($r->str('view'), ['list', 'calendar'], true)) app()->session->set($key, $r->str('view'));
            if (app()->session->get($key, 'list') === 'calendar') {
                $tz = \Core\Data\Calendar::tz();
                $today = new \DateTimeImmutable('today', $tz);
                $month = preg_match('~^(\d{4})-(0[1-9]|1[0-2])$~', $r->str('monat'), $m) ? $r->str('monat') : $today->format('Y-m');
                $first = new \DateTimeImmutable($month . '-01', $tz);
                $gridStart = $first->modify('-' . ((int) $first->format('N') - 1) . ' days');
                $next = $first->modify('+1 month');
                $gridEnd = $next->modify('+' . ((8 - (int) $next->format('N')) % 7) . ' days');
                $cal = ['month' => $month, 'first' => $first, 'next' => $next, 'gridStart' => $gridStart, 'gridEnd' => $gridEnd, 'today' => $today,
                    'occ' => \Core\Data\Calendar::occurrences($t, $gridStart, $gridEnd, ['status' => $status, 'lang' => $lang, 'q' => $r->str('q'), 'source' => 'own'])];
            }
        }
        $total = Entries::count($t, $o);
        $rows = Entries::query($t, $o + ['limit' => $per, 'offset' => ($page - 1) * $per]);
        return $this->view('data/entries', ['t' => $t, 'rows' => $rows, 'total' => $total, 'status' => $status,
            'q' => $r->str('q'), 'sort' => $sort, 'dir' => $dir, 'page' => $page, 'pages' => (int) ceil($total / $per), 'lang' => $lang, 'cal' => $cal,
            'counts' => ['all' => Entries::count($t, ['status' => 'all', 'lang' => $lang, 'source' => 'own']), 'published' => Entries::count($t, ['status' => 'published', 'lang' => $lang, 'source' => 'own']), 'draft' => Entries::count($t, ['status' => 'draft', 'lang' => $lang, 'source' => 'own'])],
        ]);
    }

    public function entryNew(Request $r, string $handle): Response
    {
        $this->auth($r);
        $t = $this->table($handle);
        $this->noInbox($t, $r);
        if (!can('data.edit', $handle)) throw new HttpException(403, __('Für diese Tabelle fehlt Ihrer Rolle die Berechtigung.'));
        // Kalender: „Neuer Termin“ an einem Tag der Monatsansicht (?start=JJJJ-MM-TT)
        $values = [];
        if (\Core\Data\Calendar::enabled($t) && preg_match('~^\d{4}-\d{2}-\d{2}$~', $r->str('start')) && strtotime($r->str('start'))) {
            $sf = \Core\Data\Calendar::config($t)['start'];
            $values[$sf] = (Tables::field($t, $sf)['type'] ?? '') === 'datetime' ? $r->str('start') . ' 09:00' : $r->str('start');
        }
        // Stellenangebot: „Veröffentlicht am“ = heute
        if (\Core\Data\Jobs::is($t) && ($df = \Core\Data\Jobs::f($t, 'date_posted')) !== '') $values[$df] = \Core\Data\Jobs::today();
        return $this->view('data/entry', ['t' => $t, 'e' => null, 'values' => $values, 'errors' => []]);
    }

    public function entryEdit(Request $r, string $handle, string $id): Response
    {
        $this->auth($r);
        $t = $this->table($handle);
        $this->noInbox($t, $r);
        if (!can('data.edit', $handle)) throw new HttpException(403, __('Für diese Tabelle fehlt Ihrer Rolle die Berechtigung.'));
        $e = Entries::find($t, (int) $id) ?? throw new HttpException(404);
        if (Shared::isForeign($t, $e)) {
            // Fremder Eintrag einer geteilten Tabelle: nur lesen, ausblenden/hervorheben, übernehmen (Eigentümer)
            if (!Shared::visibleAll($t, $e)) throw new HttpException(404);
            return $this->view('data/foreign_entry', ['t' => $t, 'e' => $e, 'src' => $e['origin_site'] === $t['shared']['owner'] ? 'owner' : 'members']);
        }
        // Aus einer externen Quelle (Core\Sources): nur lesen, ein-/ausblenden – Inhalte kommen aus der Quelle
        if ($origin = \Core\Sources\Sources::origin($t, (int) $e['id'])) {
            return $this->view('data/external_entry', ['t' => $t, 'e' => $e, 'origin' => $origin]);
        }
        return $this->view('data/entry', ['t' => $t, 'e' => $e, 'values' => $e, 'errors' => []]);
    }

    public function entrySave(Request $r, string $handle, ?string $id = null): Response
    {
        $this->auth($r);
        $t = $this->table($handle);
        $this->noInbox($t, $r);
        if (!can('data.edit', $handle)) throw new HttpException(403, __('Für diese Tabelle fehlt Ihrer Rolle die Berechtigung.'));
        $in = (array) ($r->post['f'] ?? []);
        $in['slug'] = $r->str('slug');
        $in['status'] = $r->str('status');
        if (Tables::isShared($t)) {
            if ($id !== null && ($cur = Entries::find($t, (int) $id)) && Shared::isForeign($t, $cur)) {
                throw new HttpException(403, __('Dieser Eintrag gehört zu einer anderen Website und lässt sich hier nicht ändern.'));
            }
            // Mitglieder: „dem Verband vorschlagen“
            if (!Shared::isOwner($t) && array_key_exists('suggest', $r->post)) $in['_suggest'] = $r->str('suggest') === '1';
        }
        if ($id === null && $r->str('lang') !== '') {
            $in['lang'] = $r->str('lang');
        }
        // Ohne Veröffentlichungsrecht: nur Entwürfe; veröffentlichte Einträge bleiben unangetastet
        if (!can('data.publish', $handle)) {
            $existing = $id !== null ? Entries::find($t, (int) $id) : null;
            if ($existing && $existing['status'] === 'published') {
                app()->session->flash('error', __('Veröffentlichte Einträge darf Ihre Rolle nicht ändern.'));
                return Response::redirect(url("/admin/data/$handle/$id"));
            }
            $in['status'] = 'draft';
        }
        [$newId, $errors] = Entries::save($t, $id !== null ? (int) $id : null, $in);
        if ($errors) {
            app()->session->flash('error', 'Bitte prüfen Sie die markierten Felder – es wurde nichts gespeichert.');
            $e = $id !== null ? Entries::find($t, (int) $id) : null;
            return $this->view('data/entry', ['t' => $t, 'e' => $e, 'values' => $in + ($e ?? []), 'errors' => $errors], 422);
        }
        $this->changed();
        $to = $r->str('then') === 'new' ? "/admin/data/$handle/new" : ($r->str('then') === 'list' ? "/admin/data/$handle" : "/admin/data/$handle/$newId");
        return $this->back($to, 'success', $t['singular'] . ' gespeichert.');
    }

    public function entryDelete(Request $r, string $handle, string $id): Response
    {
        $this->auth($r);
        if (!can('data.delete', $handle)) throw new HttpException(403, __('Löschen ist Ihrer Rolle nicht erlaubt.'));
        $this->noInbox($this->table($handle), $r);
        $t = $this->table($handle);
        if (($e = Entries::find($t, (int) $id)) && Shared::isForeign($t, $e)) {
            throw new HttpException(403, __('Dieser Eintrag gehört zu einer anderen Website und lässt sich hier nicht ändern.'));
        }
        Entries::delete($t, (int) $id);
        return $this->back("/admin/data/$handle", 'success', 'Eintrag gelöscht.');
    }

    /** Übersetzung eines Eintrags anlegen oder öffnen */
    public function translate(Request $r, string $handle, string $id): Response
    {
        $this->auth($r);
        if (!can('data.edit', $handle)) throw new HttpException(403, __('Für diese Tabelle fehlt Ihrer Rolle die Berechtigung.'));
        $t = $this->table($handle);
        $this->noInbox($t, $r);
        try {
            $e = Entries::translate($t, (int) $id, $r->str('lang'));
        } catch (\RuntimeException $ex) {
            return $this->back("/admin/data/$handle/$id", 'error', $ex->getMessage());
        }
        return $this->back("/admin/data/$handle/{$e['id']}", 'success', __('Übersetzung angelegt – bitte Texte übersetzen und veröffentlichen.'));
    }

    /** Eintrag nur mit Titel anlegen (Verknüpfungsfelder: „+ Neu“) */
    public function quick(Request $r, string $handle): Response
    {
        $this->auth($r);
        if (!can('data.edit', $handle)) return Response::json(['ok' => false, 'error' => __('Für diese Tabelle fehlt Ihrer Rolle die Berechtigung.')], 403);
        $t = $this->table($handle);
        if (Tables::isInbox($t)) return Response::json(['ok' => false, 'error' => __('Eingangs-Tabelle: Einträge entstehen nur über das öffentliche Formular (verschlüsselt).')], 403);
        [$id, $err] = Entries::quickCreate($t, (string) ($r->post['title'] ?? ''));
        return $id ? Response::json(['ok' => true, 'id' => $id, 'title' => Entries::title($t, Entries::find($t, $id))])
            : Response::json(['ok' => false, 'error' => $err], 422);
    }

    /** Sammelaktionen und Sortierung (JSON) */
    public function bulk(Request $r, string $handle): Response
    {
        $this->auth($r);
        $need = match ($r->post['action'] ?? '') { 'publish', 'draft' => 'data.publish', 'delete' => 'data.delete', default => 'data.edit' };
        if (!can($need, $handle)) return Response::json(['ok' => false, 'error' => __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.')], 403);
        $t = $this->table($handle);
        if (Tables::isInbox($t)) return Response::json(['ok' => false, 'error' => __('Eingangs-Tabelle: Anfragen unter „Anfragen“ bearbeiten.')], 403);
        $ids = array_map('intval', (array) ($r->post['ids'] ?? []));
        match ($r->post['action'] ?? '') {
            'publish' => Entries::setStatus($t, $ids, 'published'),
            'draft' => Entries::setStatus($t, $ids, 'draft'),
            'delete' => array_map(fn($id) => Entries::delete($t, $id), $ids),
            'reorder' => Entries::reorder($t, $ids),
            default => throw new HttpException(422, 'Unbekannte Aktion.'),
        };
        // drafts: Zähler „Entwürfe“ der Seitenleiste (Status-Knopf der Eintragsliste aktualisiert ihn ohne Neuladen)
        return $r->wantsJson() ? Response::json(['ok' => true, 'drafts' => \Core\Review\Drafts::count()]) : $this->back("/admin/data/$handle", 'success', 'Erledigt.');
    }

    // ================================================================= Geteilte Tabellen (Core\Data\Shared)

    private function sharedTable(Request $r, string $handle): array
    {
        $t = $this->table($handle);
        if (!Tables::isShared($t)) throw new HttpException(404);
        if (!can('data.edit', $handle)) throw new HttpException(403, __('Für diese Tabelle fehlt Ihrer Rolle die Berechtigung.'));
        return $t;
    }

    /** Fremde Einträge: ?src=owner (vom Eigentümer), members (von den übrigen Websites), suggestions (Vorschläge, nur Eigentümer) */
    public function foreign(Request $r, string $handle): Response
    {
        $this->auth($r);
        $t = $this->sharedTable($r, $handle);
        $owner = Shared::isOwner($t);
        $src = in_array($r->str('src'), ['owner', 'members', 'suggestions'], true) ? $r->str('src') : ($owner ? 'members' : 'owner');
        if ($src === 'owner' && $owner) $src = 'members';
        if ($src === 'suggestions' && !$owner) throw new HttpException(404);
        if ($src === 'members' && !$owner && !$t['shared']['members_see_members']) throw new HttpException(404);
        $filter = in_array($r->str('pick'), ['visible', 'featured', 'hidden', 'rejected', 'none'], true) ? $r->str('pick') : '';
        $o = ['status' => 'published', 'lang' => 'all', 'q' => $r->str('q'), 'sort' => 'updated_at', 'dir' => 'desc', 'limit' => 200,
            'source' => $src === 'suggestions' ? 'members' : $src];
        if ($src === 'suggestions') $o['suggested'] = $filter === '' ? 'pending' : true;
        if ($filter !== '' && $filter !== 'none') $o['pick'] = $filter;
        if ($r->str('origin') !== '') $o['origin'] = $r->str('origin');
        $rows = Entries::query($t, $o);
        if ($filter === 'none') $rows = array_values(array_filter($rows, fn($e) => $e['_pick'] === null));
        // Was zeigt diese Website? (Quelle „site“) – für die Spalte „Auf dieser Website“
        $shown = array_flip(array_column(Entries::query($t, ['status' => 'published', 'lang' => 'all', 'source' => 'site', 'ids' => array_column($rows, 'id') ?: [0]]), 'id'));
        Shared::touch($t);
        return $this->view('data/foreign', ['t' => $t, 'rows' => $rows, 'src' => $src, 'filter' => $filter, 'q' => $r->str('q'), 'shown' => $shown,
            'origin' => $r->str('origin')]);
    }

    /** Auswahl setzen: übernehmen/ablehnen (Eigentümer), ausblenden, hervorheben, zurücksetzen */
    public function pick(Request $r, string $handle): Response
    {
        $this->auth($r);
        $t = $this->sharedTable($r, $handle);
        if (!can('data.publish', $handle)) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        $ids = array_map('intval', (array) ($r->post['ids'] ?? []));
        $state = $r->str('state');
        if (preg_match('~^(\d+):(\w+)$~', $r->str('one'), $m)) [$ids, $state] = [[(int) $m[1]], $m[2]];
        $back = $r->str('back') !== '' && str_starts_with($r->str('back'), '/admin/data/') ? $r->str('back') : "/admin/data/$handle/shared";
        try {
            $n = Shared::setPick($t, $ids, $state === 'reset' ? null : $state, (string) (app()->auth->user()['email'] ?? ''));
        } catch (\InvalidArgumentException $ex) {
            return $r->wantsJson() ? Response::json(['ok' => false, 'error' => $ex->getMessage()], 422) : $this->back($back, 'error', $ex->getMessage());
        }
        $msg = match ($state) {
            'visible' => __('Übernommen – erscheint jetzt auf dieser Website.'), 'rejected' => __('Vorschlag abgelehnt.'),
            'featured' => __('Hervorgehoben.'), 'hidden' => __('Auf dieser Website ausgeblendet.'), default => __('Auswahl zurückgesetzt.'),
        };
        return $r->wantsJson() ? Response::json(['ok' => true, 'count' => $n]) : $this->back($back, 'success', $msg . ($n > 1 ? ' (' . $n . ')' : ''));
    }

    /** Anzeige auf dieser Website: welche fremden Einträge erscheinen (Eigentümer, Mitglieder, Filter, Verlinkung) */
    public function display(Request $r, string $handle): Response
    {
        $this->auth($r);
        $t = $this->sharedTable($r, $handle);
        Shared::touch($t);
        return $this->view('data/display', ['t' => $t, 'cfg' => Shared::localConfig($t['shared']['key']), 'meta' => Shared::meta($t['shared']['key'])]);
    }

    public function displaySave(Request $r, string $handle): Response
    {
        $this->auth($r);
        $t = $this->sharedTable($r, $handle);
        if (!can('data.publish', $handle)) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        $key = $t['shared']['key'];
        if (Shared::isOwner($t)) {
            // Eigentümer: automatische Übernahme nach Regeln (für alle Websites der Tabelle gespeichert)
            Shared::update($key, ['auto' => ['enabled' => $r->str('auto_enabled') === '1', 'sites' => (array) ($r->post['auto_sites'] ?? []),
                'where' => self::whereRows($r->post['auto_where'] ?? [])]]);
            Shared::saveLocal($key, ['link_origin' => false, 'canonical' => $r->str('canonical') === 'self' ? 'self' : 'origin']);
        } else {
            $members = in_array($r->str('members'), ['off', 'all', 'selected'], true) ? $r->str('members') : 'off';
            Shared::saveLocal($key, [
                'owner' => $r->str('owner') === '1', 'members' => $members,
                'sites' => array_values(array_intersect((array) ($r->post['sites'] ?? []), $t['shared']['members'])),
                'where' => Shared::cleanWhere($t, self::whereRows($r->post['where'] ?? [])),
                'link_origin' => $r->str('link_origin') === '1',
                'canonical' => $r->str('canonical') === 'self' ? 'self' : 'origin',
            ]);
        }
        $this->changed();
        return $this->back("/admin/data/$handle/display", 'success', __('Anzeige gespeichert.'));
    }

    /** Filterzeilen aus dem Formular: where[i][field|op|value] → [[feld, op, wert], …] */
    private static function whereRows(mixed $rows): array
    {
        $out = [];
        foreach ((array) $rows as $w) {
            if (!is_array($w)) continue;
            $out[] = [(string) ($w['field'] ?? ''), (string) ($w['op'] ?? '='), (string) ($w['value'] ?? '')];
        }
        return $out;
    }
}
