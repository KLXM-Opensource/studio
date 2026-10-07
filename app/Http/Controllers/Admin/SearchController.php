<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Http\Request;
use Core\Http\Response;
use Core\Media;
use Core\Pages;
use Core\SystemSchema;

/**
 * Übergreifende Suche (Spotlight, ⌘K): Aktionen, Seiten, Datensätze, Medien, Einstellungen, Hilfe.
 * Ohne Suchbegriff: Aktionen und zuletzt geänderte Inhalte.
 */
final class SearchController extends AdminController
{
    public function search(Request $r): Response
    {
        $user = $this->auth($r);
        $q = trim(mb_substr($r->str('q'), 0, 80));
        $isAdmin = in_array($user['role'], ['admin', 'network'], true);
        $match = fn(string ...$hay) => $q === '' || self::score($q, implode(' ', $hay)) > 0;
        $groups = [];

        // Favoriten des Benutzers zuerst (Core\Favorites)
        $items = [];
        foreach (\Core\Favorites::all((int) $user['id']) as $i => $f) {
            $s = self::score($q, $f['title'] . ' ' . rawurldecode($f['url']));
            if ($q !== '' && $s === 0) continue;
            $items[] = ['title' => $f['title'], 'sub' => __('Favorit'), 'url' => url($f['url']), 'icon' => 'star',
                'glyph' => $f['icon'] !== '' ? $f['icon'] : null, 's' => $q === '' ? 1000 - $i : $s];
        }
        if ($items) $groups[] = ['label' => __('Favoriten'), 'items' => self::top($items, $q === '' ? 8 : 6)];

        // Assistent-Chat (Core\AI\Assistant): „Assistent fragen …“ – öffnet das Chat-Fenster mit dem Suchtext (resources/js/_assistant.js)
        if (\Core\AI\Assistant::available() && ($q === '' || mb_strlen($q) >= 3)) {
            $groups[] = ['label' => \Core\AI\Assist::brand(), 'items' => [[
                'title' => $q === '' ? __('Assistent fragen …') : __('Assistent fragen: „{q}“', ['q' => $q]), 'sub' => __('Antwort aus Handbuch und Hilfe, Aktionen mit Bestätigung'),
                'url' => url('/admin/ai/assistent') . ($q !== '' ? '?q=' . rawurlencode($q) : ''), 'ico' => \Core\Icons::resolve('chat-teardrop-dots'), 'cmd' => 'assistant', 'q' => $q,
            ]]];
        }

        // Aktionen
        $actions = [
            [__('Neue Seite'), __('Seite anlegen'), '/admin/pages/new', 'plus', 'neu seite anlegen erstellen'],
            [__('Medien hochladen'), __('Mediathek öffnen'), '/admin/media', 'upload', 'bild foto datei pdf upload hochladen'],
            [app()->theme->settingsTitle(), __('Zentrale Angaben'), '/admin/settings', 'gear', 'einstellungen kontakt adresse ' . project('search_keywords', '')],
            ...(can('requests.read') && \Core\Data\Inbox::readable() ? [[__('Anfragen'), term('requests'), '/admin/requests', 'inbox', 'anfrage formular eingang ' . mb_strtolower(term('requests'))]] : []),
            [__('Website ansehen'), __('Startseite öffnen'), url('/'), 'globe', 'website live ansehen'],
            [__('Handbuch'), __('Hilfe & Anleitungen'), '/admin/hilfe', 'help', 'hilfe handbuch anleitung'],
            ...(\Core\Guide::exists() ? [[__('Projekt-Hinweise'), __('Hinweise zu diesem Projekt'), '/admin/hilfe#projekt', 'lightbulb', 'projekt hinweise agentur anleitung kit']] : []),
        ];
        foreach (Tables::content() as $t) {
            $actions[] = [__('Neu: {name}', ['name' => $t['singular']]), $t['name'], '/admin/data/' . $t['handle'] . '/new', 'plus', 'neu anlegen ' . $t['name'] . ' ' . $t['singular']];
        }
        // Eingangs-Tabellen: nur der Name (Inhalte sind verschlüsselt und werden nie durchsucht)
        foreach (\Core\Data\Inbox::readable() as $t) {
            $actions[] = [$t['name'], __('Anfragen'), '/admin/requests?table=' . $t['handle'], 'inbox', 'anfragen eingang ' . $t['name'] . ' ' . $t['singular']];
        }
        if ($isAdmin) {
            $actions[] = [__('Neue Datentabelle'), __('Eigenen Inhaltstyp anlegen'), '/admin/data/new', 'table', 'tabelle collection daten neu'];
        }
        // Hauptmenü: Bereiche des Kerns und Inhalts-/Werkzeugseiten von Funktionen und Erweiterungen (Core\AdminPages::nav('main'),
        // z. B. Feedback, Buchungen, Animationen) – gleiche Sichtbarkeit wie in der Seitenleiste (layout.php)
        $netUser = \Core\Network\Network::isNetworkUser($user);
        $main = [
            ['/admin/network', __('Netzwerk'), 'network', $netUser && \Core\Network\Network::isNetworkSite(), 'netzwerk websites konten'],
            ['/admin', __('Übersicht'), 'dashboard', true, 'übersicht start dashboard kennzahlen'],
            ['/admin/pages', __('Seiten'), 'pages', can('pages.edit'), 'seiten seitenbaum navigation'],
            ['/admin/entwuerfe', __('Entwürfe'), 'drafts', \Core\Review\Drafts::canView(), 'entwürfe veröffentlichen'],
            ['/admin/data', __('Daten'), 'data', can('data.schema') || (bool) array_filter(Tables::content(), fn($t) => can('data.edit', $t['handle'])), 'daten tabellen einträge'],
            ['/admin/chat', __('Chat'), 'chat', \Core\Chat\Chat::canUse(), 'chat nachrichten'],
            ['/admin/ai', \Core\AI\Assist::brand(), 'ai', \Core\AI\Assist::navVisible(), 'ki assistent texte übersetzen seo'],
            ['/admin/ai/eingereicht', __('Eingereicht'), 'review', \Core\Review\Queue::canReview(), 'eingereicht freigabe prüfen review'],
        ];
        foreach (\Core\AdminPages::nav('main') as [$href, $label, $key, $vis]) $main[] = [$href, $label, $key, $vis, mb_strtolower($label) . ' ' . $href];
        foreach ($main as [$href, $label, $key, $vis, $kw]) {
            if ($vis) $actions[] = [$label, __('Bereich'), $href, $key, $kw];
        }
        // Administration: Punkte der Gruppen „Einstellungen“ und „Werkzeuge“ (Core\AdminPages::groups) – Untertitel = Gruppe wie im Menü
        $kwAdmin = ['system' => 'system einstellungen mail smtp spam icon favicon pwa', 'features' => 'funktionen erweiterungen schalten an aus',
            'prefs' => 'einstellungen funktionen erweiterungen konfiguration', 'users' => 'benutzer nutzer rollen rechte zugang passwort',
            'design' => 'design farben schriften kit', 'stats' => 'statistik bericht'];
        foreach (\Core\AdminPages::groups() as $g) {
            foreach ($g['items'] as [$href, $label, $key]) {
                $actions[] = [$label, $g['label'], $href, $key, ($kwAdmin[$key] ?? '') . ' ' . mb_strtolower($g['label'])];
            }
        }
        // Einstellungs- und Statistikseiten (Core\AdminPages) stehen nicht im Menü – über die Suche bleiben sie direkt erreichbar
        foreach (\Core\AdminPages::ofKind('settings', 'stats') as $ap) {
            $actions[] = [$ap['label'], $ap['description'] !== '' ? $ap['description'] : ($ap['kind'] === 'stats' ? __('Statistiken') : __('Einstellungen der Funktionen')),
                $ap['href'], $ap['icon'], ($ap['kind'] === 'stats' ? 'statistik bericht ' : 'einstellungen konfiguration ') . $ap['description'] . ' ' . $ap['href']];
        }
        $items = [];
        foreach ($actions as [$t, $sub, $href, $icon, $kw]) {
            if ($q === '' || self::score($q, $t . ' ' . $kw) > 0) {
                $items[] = ['title' => $t, 'sub' => $sub, 'url' => str_starts_with($href, '/admin') ? url($href) : $href, 'icon' => $icon, 's' => self::score($q, $t . ' ' . $kw)];
            }
        }
        $groups[] = ['label' => __('Aktionen'), 'items' => self::top($items, $q === '' ? 6 : 5)];

        // Seiten
        $items = [];
        foreach (Pages::flat(true) as $p) {
            $hay = $p['title'] . ' ' . $p['path'] . ' ' . $p['meta_description'];
            if ($q !== '') {
                $hay .= ' ' . strip_tags((string) $p['content_draft']);
            }
            $s = self::score($q, $p['title'] . ' ' . $p['path']) * 2 + self::score($q, $hay);
            if ($q !== '' && $s === 0) continue;
            $nf = \Core\NotFound::isPage($p);   // Seite „Nicht gefunden (404)“: wie eine Seite im Editor öffnen
            $tpl = $p['type'] === 'template' && !$nf;
            $items[] = ['title' => $p['title'], 'sub' => $nf ? __('Nicht gefunden (404)') : ($tpl ? __('Detailseiten-Vorlage') : '/' . ($p['is_home'] ? '' : $p['path']) . ($p['status'] === 'published' ? '' : __(' · Entwurf'))),
                'url' => $tpl ? url('/admin/pages') : Pages::url($p) . '?edit=1', 'icon' => $p['is_home'] ? 'home' : 'page',
                'alt' => $tpl ? null : url('/admin/pages/' . $p['id']), 's' => $s ?: strtotime((string) $p['updated_at'])];
        }
        $groups[] = ['label' => __('Seiten'), 'items' => self::top($items, $q === '' ? 4 : 8)];

        // Datensätze aller Inhaltstabellen (Eingangs-Tabellen nie)
        foreach (Tables::content() as $t) {
            $rows = Entries::query($t, $q === '' ? ['status' => 'all', 'sort' => 'updated_at', 'dir' => 'desc', 'limit' => 3] : ['status' => 'all', 'q' => $q, 'limit' => 6]);
            $items = array_map(fn($e) => ['title' => Entries::title($t, $e), 'sub' => $t['singular'] . ($e['status'] === 'draft' ? __(' · Entwurf') : ''),
                'url' => url('/admin/data/' . $t['handle'] . '/' . $e['id']), 'icon' => 'table', 'glyph' => $t['icon'],
                'alt' => Entries::url($t, $e)], $rows);
            if ($items) $groups[] = ['label' => $t['name'], 'items' => $items];
        }

        // Medien
        $media = $q === '' ? array_slice(Media::all(), 0, 4) : array_slice(Media::all(['q' => $q]), 0, 6);
        $groups[] = ['label' => __('Medien'), 'items' => array_map(fn($m) => [
            'title' => Media::displayName($m), 'sub' => Media::typeLabel($m['mime']) . ' · ' . Media::humanSize((int) $m['size']),
            'url' => url('/admin/media') . '#m' . $m['id'], 'icon' => str_starts_with($m['mime'], 'image/') ? 'image' : 'file',
            'thumb' => str_starts_with($m['mime'], 'image/') ? Media::url($m, 480) : null, 'alt' => Media::url($m)], $media)];

        // Einstellungen (Feldnamen) – nur mit Suchbegriff
        if ($q !== '') {
            $items = [];
            foreach (app()->theme->settingsGroups() as $g) {
                foreach ($g['fields'] as $f) {
                    if (!isset($f['label']) || ($f['type'] ?? '') === 'heading') continue;
                    $s = self::score($q, $f['label'] . ' ' . $g['label']);
                    if ($s) $items[] = ['title' => $f['label'], 'sub' => app()->theme->settingsTitle() . ' › ' . $g['label'], 'url' => url('/admin/settings') . '#' . $g['id'], 'icon' => 'gear', 's' => $s];
                }
            }
            if ($isAdmin) {
                foreach (SystemSchema::groups() as $g) {
                    foreach ($g['fields'] as $f) {
                        if (!isset($f['label'], $f['name'])) continue;
                        $s = self::score($q, $f['label'] . ' ' . $g['label']);
                        if ($s) $items[] = ['title' => $f['label'], 'sub' => 'Grundeinstellungen › ' . $g['label'], 'url' => url('/admin/system') . '#' . $g['id'], 'icon' => 'gear', 's' => $s];
                    }
                }
            }
            $groups[] = ['label' => __('Einstellungen'), 'items' => self::top($items, 6)];
        }

        // Hinweise zu diesem Projekt (Core\Guide) – Titel und Text, nur mit Suchbegriff
        if ($q !== '') {
            $items = [];
            foreach (\Core\Guide::notes() as $n) {
                $s = self::score($q, $n['title']) * 2 + self::score($q, $n['title'] . ' ' . \Core\Guide::plain($n));
                if ($s) $items[] = ['title' => $n['title'], 'sub' => __('Hinweis zum Projekt'), 'url' => \Core\Guide::url($n), 'icon' => 'lightbulb', 's' => $s];
            }
            $groups[] = ['label' => __('Projekt-Hinweise'), 'items' => self::top($items, 4)];
        }

        // Support & Wissensdatenbank (Core\Support): „Problem melden“, Wissensartikel, beantwortete Fragen
        try {
            array_push($groups, ...\Core\Support\Support::spotlight($q, self::score(...)));
        } catch (\Throwable $e) {
            error_log('[support] spotlight: ' . $e->getMessage());
        }

        $groups = array_values(array_filter($groups, fn($g) => $g['items']));
        foreach ($groups as &$g) {
            foreach ($g['items'] as &$i) {
                unset($i['s']);
                // ico: Symbolname im Sprite (Core\Icons) – Tabellensymbol (glyph) vor Art des Treffers (icon)
                $i['ico'] = \Core\Icons::resolve((string) ($i['glyph'] ?? '')) ?? \Core\Icons::resolve((string) ($i['icon'] ?? ''));
                if ($i['ico'] !== null && isset($i['glyph']) && \Core\Icons::resolve((string) $i['glyph']) !== null) $i['glyph'] = null;
            }
        }
        return Response::json(['q' => $q, 'groups' => $groups]);
    }

    /** Einfache Trefferbewertung: alle Wörter enthalten, Wortanfang zählt mehr */
    /** Adresse → Koordinaten (für Kartenfelder; serverseitig über den zentralen Proxy) */
    public function geocode(Request $r): Response
    {
        $this->auth($r);
        $q = trim(mb_substr($r->str('q'), 0, 200));
        $limiter = new \Core\RateLimiter(app()->db);
        // Nominatim erlaubt höchstens 1 Anfrage pro Sekunde
        if ($limiter->tooMany('geocode', 1, 1)) {
            usleep(1_100_000);
        }
        $limiter->hit('geocode');
        return Response::json(['results' => \Core\Maps::geocode($q)]);
    }

    private static function score(string $q, string $hay): int
    {
        if ($q === '') return 1;
        $hay = mb_strtolower($hay);
        $s = 0;
        foreach (preg_split('~\s+~', mb_strtolower($q)) as $w) {
            if ($w === '') continue;
            $pos = mb_strpos($hay, $w);
            if ($pos === false) return 0;
            $s += $pos === 0 ? 30 : (preg_match('~(^|[\s/\-›(])' . preg_quote($w, '~') . '~u', $hay) ? 20 : 8);
        }
        return $s;
    }

    private static function top(array $items, int $n): array
    {
        usort($items, fn($a, $b) => ($b['s'] ?? 0) <=> ($a['s'] ?? 0));
        return array_slice($items, 0, $n);
    }
}
