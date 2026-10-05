<?php
declare(strict_types=1);

namespace Core;

use Core\Data\Entries;
use Core\Data\EntryEdit;
use Core\Data\Shared;

/**
 * Redaktions-Werkzeugleiste auf der Website (nur angemeldet) – ein Aufbau für alle Themes und Modi.
 *
 *   links   Marke (→ Verwaltung) + Kontext: Symbol, Art, Titel, EIN Status-Chip (mit Erklärung)
 *   Mitte   Modus-Umschalter „Ansehen · Bearbeiten (· Vorlage)“
 *   rechts  Aktionen des Modus (Bearbeiten | Abbrechen · Speichern · Veröffentlichen) + Suche + Menü „⋯“
 *
 * Arten: page (Seite), entry (Detailseite eines Eintrags, Bearbeiten direkt im Text ohne Neuladen),
 * template (Detailseiten-Vorlage im Seiten-Editor, ?edit=1 auf einer Detailseite).
 * Kern-Fragment „toolbar“ (nur Kern, app/Views/fragments/toolbar.php): Kits rufen im Layout $theme->partial('toolbar', $toolbar)
 * auf; eigene Kit-Dateien dafür werden ignoriert (Core\Fragments). Verhalten: resources/js/_bar.js, Aussehen: editor.shadow.css.
 */
final class Toolbar
{
    public static function render(array $vars): string
    {
        if (!app()->auth->check() || empty($vars['page'])) return '';
        return Theme::capture(ROOT . '/app/Views/toolbar.php', ['bar' => self::context($vars)]);
    }

    /**
     * Netzwerk-Konten: andere Websites der Installation für „Website wechseln“ im Menü ⋯ (Einmal-Anmeldung über
     * /admin/network/open mit path „/“ – landet angemeldet auf der Startseite der Ziel-Website). Sonst leer.
     * @return list<array{key:string, label:string, home:bool}>
     */
    private static function netSites(): array
    {
        $u = app()->auth->user();
        if (!$u || !\Core\Network\Network::isNetworkUser($u)) return [];
        $out = [];
        foreach (\Core\Sites::all() as $k => $c) {
            if ($k === site()->key) continue;
            $out[] = ['key' => (string) $k, 'label' => (new \Core\Site((string) $k, $c))->label(), 'home' => $k === \Core\Network\Network::siteKey()];
        }
        usort($out, fn($a, $b) => [$b['home'], mb_strtolower($a['label'])] <=> [$a['home'], mb_strtolower($b['label'])]);
        return $out;
    }

    /** Alles, was die Ansicht braucht (Rechte, Adressen, Zustände) */
    public static function context(array $v): array
    {
        $page = $v['page'];
        $editing = !empty($v['editing']);
        $live = !empty($v['live']);
        $dirty = !empty($v['dirty']);
        $ctx = app()->entry;
        $kind = $ctx ? ($editing ? 'template' : 'entry') : 'page';
        $hasPage = !empty($page['id']);
        $b = [
            'kind' => $kind, 'editing' => $editing, 'live' => $live, 'page' => $page, 'hasPage' => $hasPage,
            'canEditPages' => can('pages.edit'), 'canPublish' => can('pages.publish'), 'canManage' => can('pages.manage'),
            'canSettings' => can('settings.edit'), 'settingsTitle' => app()->theme->settingsTitle(),
            'ai' => \Core\AI\Assist::client(), 'aiBrand' => \Core\AI\Assist::brand(),
            'help' => url('/admin/hilfe') . '#' . ($kind === 'page' ? 'bearbeiten' : 'daten'),
            'origin' => null, 'site' => '', 'others' => [],
            'netSites' => self::netSites(),
        ];

        if ($ctx) {
            $t = $ctx['table'];
            $e = $ctx['entry'];
            $url = (string) Entries::url($t, $e);
            $foreign = Shared::isForeign($t, $e);
            $canTable = EntryEdit::canTable($t);
            $b += [
                'table' => $t, 'entry' => $e, 'entryUrl' => $url, 'entryTitle' => Entries::title($t, $e),
                'entryDraft' => ($e['status'] ?? '') !== 'published', 'foreign' => $foreign,
                'canTable' => $canTable, 'adminUrl' => EntryEdit::adminUrl($t, $e),
                // Direkt bearbeiten: im Vorlagen-Editor ist app()->entryEdit aus – das Recht zählt trotzdem (Umschalter)
                'entryEditable' => $kind === 'entry' ? app()->entryEdit : EntryEdit::canEdit($t, $e),
                'panel' => EntryEdit::canEdit($t, $e) ? EntryEdit::endpoint($t, $e) : null,
            ];
            $b['entryPub'] = $b['entryEditable'] && $t['settings']['workflow'] && can('data.publish', $t['handle']);
            if ($foreign) {
                $b['origin'] = $canTable ? EntryEdit::originEdit($t, $e) : null;
                $b['site'] = Shared::siteInfo((string) $e['origin_site'], $t['shared']['key'])['name'];
            }
            if ($kind === 'template') {
                foreach (Entries::query($t, ['status' => 'all', 'limit' => 50]) as $o) {
                    if ($u = Entries::url($t, $o)) {
                        $b['others'][] = ['url' => $u . '?edit=1', 'title' => Entries::title($t, $o), 'current' => (int) $o['id'] === (int) $e['id']];
                    }
                }
            }
        }

        // Adressen der Modi
        $pageUrl = $hasPage ? Pages::url($page) : url('/');
        // Beim Umschalten Ansehen ↔ Bearbeiten die übrigen Parameter behalten (Blättern ?seite=2, Filter) – sonst landet man auf Seite 1
        $keep = array_diff_key(app()->request?->query ?? [], ['edit' => 1, 'live' => 1]);
        $qs = fn(bool $edit) => ($q = http_build_query($keep + ($edit ? ['edit' => 1] : []))) !== '' ? '?' . $q : '';
        $viewUrl = $ctx ? $b['entryUrl'] : $pageUrl;
        $b['viewUrl'] = $viewUrl;
        $b['pageEditUrl'] = $viewUrl . '?edit=1';
        $b['canDiscard'] = $hasPage && ($page['content_published'] ?? null) !== null;

        // Modus und EIN Status
        $b['mode'] = $kind === 'template' ? 'template' : ($editing ? 'edit' : 'view');
        if ($live) {
            $status = 'live';
        } elseif ($kind === 'entry') {
            // offline = war schon online (published_at gesetzt), Status „Entwurf“
            $status = $b['entryDraft'] ? (!empty($e['published_at']) ? 'offline' : 'draft') : 'published';
        } elseif (!$hasPage) {
            $status = '';
        } elseif (($page['status'] ?? '') !== 'published') {
            $status = ($page['content_published'] ?? null) !== null ? 'offline' : 'draft';
        } else {
            $status = $dirty ? 'changed' : 'published';
        }
        $b['status'] = $status;
        $b['dirtyDraft'] = $dirty;

        // Umschalter: [key, Beschriftung, Symbol, Adresse|null (= ohne Neuladen), aktiv]
        $modes = [];
        if ($kind === 'page') {
            if ($hasPage) {
                $modes[] = ['view', __('Ansehen'), 'eye', $pageUrl . $qs(false), !$editing];
                if ($b['canEditPages']) $modes[] = ['edit', __('Bearbeiten'), 'pencil-simple', $pageUrl . $qs(true), $editing];
            }
        } else {
            $modes[] = ['view', __('Ansehen'), 'eye', $kind === 'entry' ? null : $viewUrl, $kind === 'entry'];
            if ($b['entryEditable']) $modes[] = ['edit', __('Bearbeiten'), 'pencil-simple', $kind === 'entry' ? null : $viewUrl . '#cms-bearbeiten', false];
            if ($b['canEditPages'] && !$live) $modes[] = ['template', __('Vorlage'), 'squares-four', $viewUrl . '?edit=1', $kind === 'template'];
        }
        $b['modes'] = count($modes) > 1 ? $modes : [];

        $b['config'] = [
            'kind' => $kind, 'mode' => $b['mode'], 'status' => $status, 'viewUrl' => $viewUrl,
            'hasPublished' => $b['canDiscard'], 'texts' => self::texts($kind),
        ];
        // Status-Chip: „Offline nehmen“ / „Online stellen“ ohne Neuladen (_bar.js). Seiten: Recht pages.publish, nicht die
        // Startseite; Einträge: Freigabe-Ablauf + data.publish, nur eigene (nicht von einer anderen Website geteilte) Einträge.
        $b['toggle'] = null;
        if (!$live && $kind === 'page' && $hasPage && $b['canPublish'] && empty($page['is_home'])) {
            $b['toggle'] = ['off' => url('/admin/pages/' . (int) $page['id'] . '/offline'), 'on' => url('/admin/pages/' . (int) $page['id'] . '/publish'),
                'pending' => $dirty];
        } elseif (!$live && $kind === 'entry' && $b['entryPub'] && !$b['foreign']) {
            $bulk = url('/admin/data/' . $t['handle'] . '/bulk');
            $b['toggle'] = ['off' => $bulk, 'on' => $bulk, 'id' => (int) $e['id'], 'pending' => false];
        }
        if ($b['toggle']) {
            $b['config']['toggle'] = $b['toggle'] + ['csrf' => Csrf::token()];
        }
        // Erweiterungen (Extension::toolbar): Einträge im Menü „⋯“, Skripte nach der Leiste, Zusatz im Veröffentlichen-Dialog
        $b['ext'] = Extensions::toolbar($b);
        // Werkzeuge (Core\FrontendTools): Bearbeiten-Modus bzw. mit modes view auch beim Ansehen, nur mit Recht – Module lädt erst der Klick
        $b['tools'] = FrontendTools::forBar($b);
        if ($b['ext']['notes']) {
            $b['config']['texts']['publishBody'] = trim($b['config']['texts']['publishBody'] . "\n\n" . implode("\n", $b['ext']['notes']));
        }
        return $b;
    }

    /** Texte für _bar.js (Sprache der Verwaltung) */
    public static function texts(string $kind = 'page'): array
    {
        $entry = $kind === 'entry';
        return [
            'chip' => [
                'published' => [__('Veröffentlicht'), $entry
                    ? __('Besucher sehen diesen Eintrag genau so. Änderungen sind nach dem Speichern sofort online.')
                    : __('Besucher sehen genau diesen Stand. Neue Änderungen werden erst mit „Veröffentlichen“ sichtbar.')],
                'draft' => [__('Entwurf'), $entry
                    ? __('Nur für die angemeldete Redaktion sichtbar. Besucher sehen diesen Eintrag erst nach dem Veröffentlichen.')
                    : __('Nur für die angemeldete Redaktion sichtbar. Besucher sehen diese Seite erst nach dem Veröffentlichen.')],
                'changed' => [__('Geändert – nicht veröffentlicht'),
                    __('Es gibt einen gespeicherten Entwurf mit Änderungen. Besucher sehen noch die zuletzt veröffentlichte Fassung – „Veröffentlichen“ bringt die Änderungen online.')],
                'offline' => [__('Offline'), $entry
                    ? __('War schon online und ist derzeit offline: Besucher sehen diesen Eintrag nicht – nur die angemeldete Redaktion.')
                    : __('War schon online und ist derzeit offline: Besucher erhalten „Nicht gefunden“, Menü, Sitemap und Suche lassen die Seite aus. Nur die angemeldete Redaktion sieht sie.')],
                'unsaved' => [__('Ungespeichert'),
                    __('Es gibt Änderungen, die noch nicht gespeichert sind. „Speichern“ (⌘S / Strg+S) sichert sie, „Abbrechen“ verwirft sie.')],
                'live' => [__('Live-Fassung'), __('Sie sehen die veröffentlichte Fassung – so, wie Besucher die Seite gerade sehen.')],
            ],
            'status' => __('Status: {status}'),
            'save' => __('Speichern'), 'saving' => __('Speichere …'), 'publishing' => __('Veröffentliche …'),
            'savedOk' => __('Gespeichert ✓'), 'dirty' => __('Ungespeicherte Änderungen'),
            'saved' => __('Entwurf gespeichert {time}'), 'published' => __('Veröffentlicht {time}'), 'error' => __('Fehler beim Speichern'),
            'discardTitle' => __('Änderungen verwerfen?'),
            'discardBody' => $entry
                ? __('Sie haben Änderungen, die noch nicht gespeichert sind. „Verwerfen“ stellt den zuletzt gespeicherten Stand des Eintrags wieder her.')
                : __('Sie haben Änderungen, die noch nicht gespeichert sind. „Verwerfen“ stellt den zuletzt gespeicherten Stand wieder her.'),
            'discardNote' => $entry ? '' : __('Ein bereits gespeicherter Entwurf bleibt erhalten, veröffentlicht wird nichts.'),
            'keep' => __('Weiter bearbeiten'), 'discard' => __('Verwerfen'), 'saveExit' => __('Speichern & beenden'),
            'blockTitle' => __('Änderungen an diesem Block verwerfen?'),
            'blockBody' => __('Der Block wird auf den Stand beim Öffnen der Seitenleiste zurückgesetzt. Andere Änderungen an der Seite bleiben erhalten.'),
            'done' => __('Fertig'), 'close' => __('Schließen'), 'cancel' => __('Abbrechen'), 'ok' => __('Bestätigen'),
            'publishTitle' => __('Änderungen jetzt veröffentlichen?'),
            'publishBody' => __('Besucher sehen danach den aktuellen Stand dieser Seite.'),
            'publishOk' => __('Veröffentlichen'),
            'dropTitle' => __('Entwurf verwerfen?'),
            'dropBody' => __('Alle Änderungen seit der letzten Veröffentlichung werden verworfen. Der bisherige Entwurf wird als Version gesichert.'),
            'dropOk' => __('Entwurf verwerfen'),
            // Status-Chip: online/offline umschalten
            'goOffline' => __('Offline nehmen'), 'goOnline' => __('Online stellen'),
            'offlineTitle' => $entry ? __('Eintrag offline nehmen?') : __('Seite offline nehmen?'),
            'offlineBody' => $entry
                ? __('Besucher sehen ihn dann nicht mehr; er verschwindet aus Listen, Sitemap und Suche. Der Inhalt bleibt erhalten – „Online stellen“ bringt ihn zurück.')
                : __('Besucher sehen sie dann nicht mehr (Seite „Nicht gefunden“); sie verschwindet aus Menü, Sitemap und Suche, Weiterleitungen und Links auf diese Seite laufen ins Leere. Die veröffentlichte Fassung bleibt erhalten – „Online stellen“ bringt sie zurück.'),
            'offlineOk' => __('Offline nehmen'),
            'offlineDone' => $entry ? __('Eintrag ist offline.') : __('Seite ist offline.'),
            'onlineDone' => $entry ? __('Eintrag ist online.') : __('Seite ist online.'),
            'pendingHint' => __('Es gibt unveröffentlichte Änderungen – „Veröffentlichen“ stellt die Seite mit diesen Änderungen wieder online.'),
        ];
    }
}
