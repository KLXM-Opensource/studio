<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Push\Channels;
use Core\Push\Compose;
use Core\Push\Push;
use Core\Push\Stats;
use Core\Push\Visitor;
use Core\Theme;

/**
 * Verwaltung → Mitteilungen (Funktion „push“): Verfassen (sofort/geplant, Bestätigung mit Empfängerzahl), Verlauf aller Nachrichten
 * (von Hand und automatisch) mit Details, Abbrechen, Duplizieren; Kanäle; Statistik; Website-Einbindung (Banner, Glocke).
 * Rechte: push.view (Verlauf, Kanäle, Statistik), push.send (Verfassen, Kanäle ändern, Website-Einbindung). Aufbau wie die
 * Mediathek bzw. der Feedback-Posteingang: Bereiche in der Seitenleiste (views/push/_nav.php), Liste und Detail daneben.
 */
final class MessagesController extends AdminController
{
    public const BASE = '/admin/mitteilungen';
    public const FOLDERS = ['alle', 'manuell', 'automatisch', 'geplant'];

    private function guard(Request $r, string $perm = 'push.view'): array
    {
        $u = $this->auth($r, $perm);
        if (!Push::on()) throw new HttpException(404);
        return $u;
    }

    private function page(string $view, array $vars, string $section, int $status = 200): Response
    {
        $vars['section'] = $section;
        $vars['canSend'] = can('push.send');
        $vars['counts'] = self::counts();
        $vars['drill'] = Theme::capture(ROOT . '/app/Admin/views/push/_nav.php', $vars);
        $vars['drillTitle'] = __('Mitteilungen');
        $vars['title'] ??= match ($view) { 'compose' => __('Neue Mitteilung'), 'channels' => __('Kanäle'), 'stats' => __('Statistik'), 'website' => __('Auf der Website'), default => __('Mitteilungen') }
            . ' · ' . __('Mitteilungen');
        $vars['css'] = ['css/dashboard.css'];   // Säulen und Achsen der Statistik (Core\Dashboard\Dashboard::bars)
        return $this->view('push/' . $view, $vars, $status);
    }

    /** Zähler der Seitenleiste */
    public static function counts(): array
    {
        $db = app()->db;
        return ['alle' => (int) $db->fetchValue('SELECT COUNT(*) FROM push_messages'),
            'manuell' => (int) $db->fetchValue("SELECT COUNT(*) FROM push_messages WHERE source = 'manual'"),
            'automatisch' => (int) $db->fetchValue("SELECT COUNT(*) FROM push_messages WHERE COALESCE(source, 'auto') = 'auto'"),
            'geplant' => (int) $db->fetchValue("SELECT COUNT(*) FROM push_messages WHERE status = 'scheduled'"),
            'kanaele' => count(Channels::all())];
    }

    // ================================================================= Verlauf

    public function index(Request $r): Response
    {
        $this->guard($r);
        $folder = in_array($r->str('ordner'), self::FOLDERS, true) ? $r->str('ordner') : 'alle';
        $where = match ($folder) {
            'manuell' => "WHERE source = 'manual'", 'automatisch' => "WHERE COALESCE(source, 'auto') = 'auto'", 'geplant' => "WHERE status = 'scheduled'", default => '',
        };
        $order = $folder === 'geplant' ? 'scheduled_at ASC' : 'COALESCE(scheduled_at, 0) DESC, id DESC';
        $rows = app()->db->fetchAll("SELECT * FROM push_messages $where ORDER BY " . ($folder === 'geplant' ? $order : 'id DESC') . ' LIMIT 300');
        $sel = null;
        $detail = [];
        if (($id = (int) $r->str('id')) && ($sel = app()->db->fetch('SELECT * FROM push_messages WHERE id = ?', [$id]))) {
            $detail = [
                'queued' => (int) app()->db->fetchValue("SELECT COUNT(*) FROM push_queue WHERE message_id = ? AND status = 'queued'", [$id]),
                'errors' => app()->db->fetchAll("SELECT error, http_code, COUNT(*) AS n FROM push_queue WHERE message_id = ? AND error IS NOT NULL AND status != 'sent'
                    GROUP BY error, http_code ORDER BY n DESC LIMIT 5", [$id]),
                'author' => $sel['user_id'] ? app()->db->fetch('SELECT name, email FROM users WHERE id = ?', [(int) $sel['user_id']]) : null,
            ];
        }
        return $this->page('index', ['folder' => $folder, 'rows' => $rows, 'sel' => $sel, 'detail' => $detail], 'verlauf:' . $folder);
    }

    public function cancel(Request $r, string $id): Response
    {
        $this->guard($r, 'push.send');
        $ok = Compose::cancel((int) $id);
        return $this->back(self::BASE . '?id=' . (int) $id, $ok ? 'success' : 'error', $ok ? __('Geplante Mitteilung abgebrochen.') : __('Diese Mitteilung ist nicht mehr geplant.'));
    }

    // ================================================================= Verfassen

    public function compose(Request $r): Response
    {
        $this->guard($r, 'push.send');
        $old = ['title' => '', 'body' => '', 'link' => '', 'image' => '', 'topics' => [], 'roles' => [], 'users' => [], 'when' => 'now', 'at' => ''];
        if (($from = (int) $r->str('von')) && ($m = app()->db->fetch('SELECT * FROM push_messages WHERE id = ?', [$from]))) $old = Compose::prefill($m) + $old;
        if (($k = $r->str('kanal')) !== '') $old['topics'] = [$k];
        return $this->page('compose', ['old' => $old, 'errors' => [], 'confirm' => null], 'neu');
    }

    public function send(Request $r): Response
    {
        $user = $this->guard($r, 'push.send');
        $in = (array) ($r->post['m'] ?? []);
        [$d, $errors] = Compose::validate($in);
        $old = $in + ['topics' => [], 'roles' => [], 'users' => []];
        if ($errors || ($r->post['confirm'] ?? '') !== '1' || ($r->post['back'] ?? '') === '1') {
            $confirm = !$errors && ($r->post['back'] ?? '') !== '1' ? ['data' => $d, 'reach' => Compose::reach($d['targets'])] : null;
            return $this->page('compose', ['old' => $old, 'errors' => $errors, 'confirm' => $confirm], 'neu', $errors ? 422 : 200);
        }
        $id = Compose::create($d, (int) $user['id']);
        $msg = $d['at'] ? __('Mitteilung geplant für {when}.', ['when' => fmt()->datetime($d['at'])]) : __('Mitteilung wird gesendet.');
        return $this->back(self::BASE . '?id=' . $id . ($d['at'] ? '&ordner=geplant' : ''), 'success', $msg);
    }

    /** Erreichbare Geräte für eine Empfängerauswahl (JSON, live im Formular) */
    public function reach(Request $r): Response
    {
        $this->guard($r, 'push.send');
        $q = $r->query;
        [$d] = Compose::validate(['title' => 'x', 'topics' => (array) ($q['topics'] ?? []), 'roles' => (array) ($q['roles'] ?? []), 'users' => (array) ($q['users'] ?? [])]);
        return Response::json(['ok' => true] + Compose::reach($d['targets']));
    }

    // ================================================================= Kanäle

    public function channels(Request $r): Response
    {
        $this->guard($r);
        $sel = null;
        if ($r->str('neu') === '1') $sel = ['new' => true];
        elseif (($id = (int) $r->str('id')) && ($c = Channels::find($id))) $sel = $c;
        return $this->page('channels', ['sel' => $sel, 'errors' => [], 'old' => []], 'kanaele');
    }

    public function channelSave(Request $r, ?string $id = null): Response
    {
        $this->guard($r, 'push.send');
        $cur = $id !== null ? Channels::find((int) $id) : null;
        if ($id !== null && !$cur) throw new HttpException(404);
        [$cid, $errors] = Channels::save($cur, $r->post);
        if ($errors) return $this->page('channels', ['sel' => $cur ?? ['new' => true], 'errors' => $errors, 'old' => $r->post], 'kanaele', 422);
        return $this->back(self::BASE . '/kanaele?id=' . $cid, 'success', __('Kanal gespeichert.'));
    }

    public function channelArchive(Request $r, string $id): Response
    {
        $this->guard($r, 'push.send');
        $c = Channels::find((int) $id) ?? throw new HttpException(404);
        $on = $c['archived_at'] === null;
        Channels::archive($c, $on);
        return $this->back(self::BASE . '/kanaele?id=' . (int) $id, 'success', $on ? __('Kanal archiviert – er nimmt keine neuen Abos mehr an.') : __('Kanal wieder aktiv.'));
    }

    // ================================================================= Statistik, Website

    public function stats(Request $r): Response
    {
        $this->guard($r);
        $topic = $r->str('kanal');
        $all = Channels::all();
        if ($topic !== '' && !isset($all[$topic]) && $topic !== Stats::STAFF) $topic = '';
        $days = in_array((int) $r->str('tage'), [7, 30, 90], true) ? (int) $r->str('tage') : 30;
        return $this->page('stats', ['topic' => $topic, 'days' => $days, 'channels' => $all], 'statistik');
    }

    public function website(Request $r): Response
    {
        $this->guard($r);
        return $this->page('website', ['cfg' => Visitor::siteConfig(), 'errors' => []], 'website');
    }

    public function websiteSave(Request $r): Response
    {
        $this->guard($r, 'push.send');
        Visitor::saveSiteConfig($r->post);
        $this->changed();   // Seiten-Cache: Banner/Glocke stecken in den gespeicherten Seiten
        return $this->back(self::BASE . '/website', 'success', __('Website-Einbindung gespeichert.'));
    }
}
