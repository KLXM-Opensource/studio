<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Avatar;
use Core\Data\Tables;
use Core\Http\Request;
use Core\Http\Response;
use Core\Live;
use Core\Pages;
use Core\Presence;

/**
 * Präsenz der Redaktion (Core\Presence) – Anbindung an Studio: Seiten (type „page“, id = Seiten-ID) und Einträge (type „entry“,
 * id = „{tabelle}:{id}“). Antworten nach dem dokumentierten Schema (Entwicklerhandbuch → Präsenz):
 *
 *   POST /admin/api/presence  {type, id, label?, url?, leave?, peek?}  → {on, me, here: [Person…], last: ?Stand, live: Abo}
 *   GET  /admin/api/presence                                    → {on, me, items: [{type, id, label, url, …Person}]} (diese Website)
 *   Person = {user, name, initials, avatar, since, at, self}    Stand = {user, name, at, self} (zuletzt gespeichert, aus den Versionen)
 *
 * Einstellung sys.presence (Grundeinstellungen → Allgemein, Standard an). Besucher sehen nie etwas; der Seiten-Cache bleibt unberührt.
 */
final class PresenceController extends AdminController
{
    public static function on(): bool
    {
        return (bool) app()->settings->get('sys.presence', true);
    }

    /** Texte für presence.mjs (Verwaltungssprache) */
    public static function texts(): array
    {
        return ['editing1' => __('{names} bearbeitet gerade'), 'editingN' => __('{names} bearbeiten gerade'), 'last' => __('Zuletzt gespeichert: {name}, {ago}'),
            'now' => __('gerade eben'), 'min' => __('vor {n} Min.'), 'hour' => __('vor {n} Std.'), 'day' => __('am {date}'),
            'conflict' => __('{name} hat inzwischen gespeichert'), 'conflictShort' => __('Inzwischen gespeichert'), 'conflictTitle' => __('Inzwischen von {name} gespeichert'),
            'conflictBody' => __('Seit Sie dies geöffnet haben, hat {name} gespeichert ({ago}). Wenn Sie jetzt speichern, kann das diese Änderungen überschreiben – der vorige Stand bleibt als Version erhalten.'),
            'conflictOk' => __('Trotzdem speichern'), 'cancel' => __('Abbrechen'),
            'locked' => __('{name} bearbeitet gerade – nur Ansehen'), 'lockedTitle' => __('Gesperrt: {name} bearbeitet gerade'),
            'lockedBody' => __('Solange {name} diese Seite bearbeitet, können Sie sie nur ansehen. Die Sperre endet, sobald {name} fertig ist.'),
            'take' => __('Übernehmen'), 'takeAsk' => __('Bearbeitung von {name} übernehmen?'),
            'takeBody' => __('{name} kann danach nicht mehr speichern; nicht gespeicherte Änderungen von {name} gehen nicht verloren, werden aber auch nicht übernommen.'),
            'taken' => __('{name} hat die Bearbeitung übernommen'), 'takenBody' => __('Sie können nicht mehr speichern. Kopieren Sie wichtige, noch nicht gespeicherte Änderungen, bevor Sie die Seite verlassen.'),
            'lockedNotePage' => __('{name} bearbeitet diese Seite gerade. Sie sehen nur die Ansicht – Speichern ist gesperrt, bis {name} fertig ist.'),
            'lockedNoteEntry' => __('{name} bearbeitet diesen Eintrag gerade. Sie sehen nur die Ansicht – Speichern ist gesperrt, bis {name} fertig ist.'),
            'freeNote' => __('Wieder frei – niemand bearbeitet gerade.'), 'editNow' => __('Jetzt bearbeiten'), 'ok' => __('Verstanden')];
    }

    /** Attribute für [data-presence-here] in der Werkzeugleiste (Seite bzw. Eintrag) – leer, wenn aus oder ohne Objekt */
    public static function hereAttrs(array $b): string
    {
        if (!self::on() || !app()->auth->check()) return '';
        $kind = (string) ($b['kind'] ?? '');
        if (in_array($kind, ['entry', 'content'], true) && !empty($b['table']['handle']) && !empty($b['entry']['id'])) {
            [$type, $id, $label] = ['entry', $b['table']['handle'] . ':' . (int) $b['entry']['id'], (string) ($b['entryTitle'] ?? '')];
        } elseif (!empty($b['page']['id'])) {
            [$type, $id, $label] = ['page', (string) (int) $b['page']['id'], (string) ($b['page']['title'] ?? '')];
        } else {
            return '';
        }
        if (!self::allowed($type, $id)) return '';
        // Link zum Ort (Übersichten, Netzwerk): Seite im Editor bzw. Detailseite des Eintrags
        $link = $type === 'page' ? Pages::url($b['page']) . '?edit=1' : (string) ($b['entryUrl'] ?? '');
        return ' data-presence-here data-presence-endpoint="' . e(url('/admin/api/presence')) . '" data-presence-type="' . e($type) . '" data-presence-id="' . e($id) . '"'
            . ' data-presence-label="' . e($label) . '" data-presence-url="' . e($link) . '" data-presence-csrf="' . e(\Core\Csrf::token()) . '"'
            . ' data-presence-live="' . e(url('/api/live')) . '" data-presence-texts="' . e(json_encode(self::texts(), JSON_UNESCAPED_UNICODE)) . '"'
            . (\Core\EditLock::blockedFor($type, $id) ? ' data-presence-locked="1"' : '');   // Bearbeitungssperre: nur Ansehen, auch mit ?edit
    }

    /** Attribute für Übersichten ([data-presence-list] bzw. [data-presence-board]) */
    public static function listAttrs(string $type = '', string $prefix = ''): string
    {
        if (!self::on()) return '';
        return ' data-presence-endpoint="' . e(url('/admin/api/presence')) . '" data-presence-live="' . e(url('/api/live')) . '"'
            . ($type !== '' ? ' data-presence-type="' . e($type) . '" data-presence-prefix="' . e($prefix) . '"' : '')
            . ' data-presence-texts="' . e(json_encode(self::texts(), JSON_UNESCAPED_UNICODE)) . '"';
    }

    private static function person(array $u): array
    {
        return ['name' => (string) ($u['name'] ?: $u['email']), 'initials' => Avatar::initials($u), 'avatar' => (string) (Avatar::url($u) ?? '')];
    }

    /** Darf die Person das Objekt bearbeiten? (sonst keine Meldung) */
    private static function allowed(string $type, string $id): bool
    {
        if ($type === 'page') return ctype_digit($id) && can('pages.edit') && Pages::find((int) $id) !== null;
        if ($type === 'entry' && preg_match('~^([a-z][a-z0-9_]{0,59}):(\d+)$~', $id, $m)) return can('data.edit') && Tables::find($m[1]) !== null;
        return false;
    }

    /** Zuletzt gespeichert (Versionen): Person und Zeitpunkt */
    private static function last(string $type, string $id, int $me): ?array
    {
        $db = app()->db;
        $row = $type === 'page'
            ? $db->fetch('SELECT user_id, created_at FROM revisions WHERE page_id = ? ORDER BY id DESC LIMIT 1', [(int) $id])
            : (preg_match('~^(.+):(\d+)$~', $id, $m) ? $db->fetch('SELECT user_id, created_at FROM entry_revisions WHERE tbl = ? AND entry_id = ? ORDER BY id DESC LIMIT 1', [$m[1], (int) $m[2]]) : null);
        if (!$row) return null;
        $u = $row['user_id'] ? $db->fetch('SELECT id, name, email FROM users WHERE id = ?', [(int) $row['user_id']]) : null;
        return ['user' => (string) ($row['user_id'] ?? ''), 'name' => $u ? (string) ($u['name'] ?: $u['email']) : '', 'at' => (int) strtotime((string) $row['created_at']),
            'self' => $u && (int) $u['id'] === $me];
    }

    private static function out(array $e, int $me): array
    {
        return ['user' => (string) $e['user'], 'name' => (string) ($e['name'] ?? ''), 'initials' => (string) ($e['initials'] ?? ''), 'avatar' => (string) ($e['avatar'] ?? ''),
            'since' => (int) $e['since'], 'at' => (int) $e['at'], 'self' => (string) $e['user'] === (string) $me];
    }

    public function beat(Request $r): Response
    {
        $u = $this->auth($r);
        if (!self::on()) return Response::json(['on' => false]);
        $in = $r->post;   // JSON-Körper liest Request bereits nach post
        $type = (string) ($in['type'] ?? ''); $id = (string) ($in['id'] ?? '');
        if (!self::allowed($type, $id)) return Response::json(['on' => true, 'error' => 'Unbekannt'], 404);
        $me = (int) $u['id'];
        $who = ['site' => site()->key, 'type' => $type, 'id' => $id, 'user' => (string) $me];
        if (!empty($in['leave'])) { Presence::leave($who); return Response::json(['on' => true, 'left' => true]); }
        // Bearbeitungssperre übernehmen (Administration): melden und vor den bisherigen Inhaber rücken
        $take = !empty($in['take']) && \Core\EditLock::on() && \Core\EditLock::canTake();
        // peek: nur nachsehen (Ansehen-Modus) – ohne sich selbst als „bearbeitet gerade“ zu melden
        if (empty($in['peek']) || $take) Presence::beat($who, self::person($u) + ['label' => (string) ($in['label'] ?? ''), 'url' => (string) ($in['url'] ?? '')]);
        if ($take) \Core\EditLock::take($type, $id, $me);
        $here = array_map(fn($e) => self::out($e, $me), Presence::list(['site' => site()->key, 'type' => $type, 'id' => $id]));
        return Response::json(['on' => true, 'me' => (string) $me, 'here' => $here, 'last' => self::last($type, $id, $me),
            'lock' => self::lock($type, $id, $me), 'live' => Live::token(['presence:' . site()->key])])->header('Cache-Control', 'no-store');
    }

    /** Stand der Bearbeitungssperre: {on, by: Person|null (fremder Inhaber), mine, canTake} */
    private static function lock(string $type, string $id, int $me): array
    {
        if (!\Core\EditLock::on()) return ['on' => false];
        $h = \Core\EditLock::holder($type, $id);
        $mine = $h && (string) $h['user'] === (string) $me;
        return ['on' => true, 'by' => $h && !$mine ? self::out($h, $me) : null, 'mine' => $mine, 'canTake' => \Core\EditLock::canTake()];
    }

    public function index(Request $r): Response
    {
        $u = $this->auth($r);
        if (!self::on()) return Response::json(['on' => false, 'items' => []]);
        $me = (int) $u['id'];
        $items = array_map(fn($e) => ['type' => (string) $e['type'], 'id' => (string) $e['id'], 'label' => (string) ($e['label'] ?? ''), 'url' => (string) ($e['url'] ?? '')] + self::out($e, $me),
            Presence::list(['site' => site()->key]));
        return Response::json(['on' => true, 'me' => (string) $me, 'items' => $items, 'live' => Live::token(['presence:' . site()->key])])->header('Cache-Control', 'no-store');
    }
}
