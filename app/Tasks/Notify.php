<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Tasks;

use Core\I18n;
use Core\Mailer;
use Core\Push\Push;
use Core\UiPrefs;

/**
 * Benachrichtigungen zu Aufgaben (Core\Tasks\Tasks):
 *  - neue Zuständigkeit: E-Mail (Core\Mailer, mit Impressum) und Push-Ereignis „tasks.assigned“ an die zuständige Person bzw. die
 *    Personen der Rolle (höchstens MAX, ohne die handelnde Person). „Alle“ benachrichtigt niemanden.
 *  - Erinnerung (tasks:remind, Cron „tasks.remind“): offene Aufgaben, die morgen oder heute fällig sind, einmal je Tag; überfällige
 *    höchstens alle 7 Tage. Nur, wenn die Website es einschaltet (Grundeinstellung sys.tasks_remind, Standard aus).
 * E-Mail je Konto abschaltbar (Konto → Mitteilungen, Core\UiPrefs „tasks.mail“), Push über Konto → Benachrichtigungen.
 * Sprache je Empfänger (Sprache der Verwaltung des Kontos). Wirft nie.
 */
final class Notify
{
    /** Höchstens so viele Empfänger je Aufgabe (Rolle mit vielen Personen) */
    public const MAX = 50;

    /** Neue Zuständigkeit */
    public static function assigned(int $id, array $actor): int
    {
        if (Tasks::$quiet) return 0;
        try {
            $t = Tasks::find($id);
            if (!$t || $t['status'] !== 'open') return 0;
            $to = array_values(array_filter(self::recipients($t), fn($u) => (int) $u['id'] !== (int) ($actor['id'] ?? 0)));
            if (!$to) return 0;
            $n = self::mail($t, $to, 'assigned', $actor['label']);
            Push::notifyUsers(array_map(fn($u) => (int) $u['id'], $to), fn() => [
                'title' => __('Neue Aufgabe: {title}', ['title' => $t['title']]),
                'body' => trim(($t['due'] ? __('fällig am {date}', ['date' => fmt()->date($t['due'])]) . ' · ' : '') . __('von {name}', ['name' => $actor['label']])),
                'url' => '/admin/aufgaben/' . $id, 'tag' => 'task-' . $id,
            ], 'tasks.assigned', ['ttl' => 3 * 86400]);
            return $n;
        } catch (\Throwable $e) {
            error_log('[tasks] notify: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Erinnerung an fällige Aufgaben dieser Website (tasks:remind). Ohne eingeschaltete Erinnerung (sys.tasks_remind) nichts.
     * @return array{tasks: int, mails: int, off: bool}
     */
    public static function remind(?string $today = null): array
    {
        if (!app()->settings->get(Tasks::REMIND_SETTING, false)) return ['tasks' => 0, 'mails' => 0, 'off' => true];
        $today ??= date('Y-m-d');
        $tomorrow = date('Y-m-d', (int) strtotime($today . ' +1 day'));
        $week = date('Y-m-d', (int) strtotime($today . ' -7 days'));
        $n = 0;
        $mails = 0;
        foreach (app()->db->fetchAll("SELECT * FROM tasks WHERE status = 'open' AND due IS NOT NULL AND due <= ? ORDER BY due", [$tomorrow]) as $row) {
            $t = Tasks::hydrate($row);
            $last = (string) ($t['reminded_at'] ?? '');
            // Bald fällig: einmal je Tag; überfällig: höchstens alle 7 Tage
            if ($last === $today || ($t['due'] < $today && $last !== '' && $last > $week)) continue;
            $to = self::recipients($t);
            // „Alle“: Erinnerung an die Person, die die Aufgabe angelegt hat
            if (!$to && $t['created_by'] !== null) $to = self::users([(int) $t['created_by']]);
            if ($to) {
                $mails += self::mail($t, $to, 'remind', '');
                Push::notifyUsers(array_map(fn($u) => (int) $u['id'], $to), fn() => [
                    'title' => Tasks::dueState($t['due'], $today) === 'overdue' ? __('Überfällig: {title}', ['title' => $t['title']]) : __('Bald fällig: {title}', ['title' => $t['title']]),
                    'body' => __('fällig am {date}', ['date' => fmt()->date($t['due'])]), 'url' => '/admin/aufgaben/' . (int) $t['id'], 'tag' => 'task-' . (int) $t['id'],
                ], 'tasks.assigned', ['ttl' => 86400]);
            }
            app()->db->update('tasks', ['reminded_at' => $today], 'id = :id', ['id' => (int) $t['id']]);
            $n++;
        }
        return ['tasks' => $n, 'mails' => $mails, 'off' => false];
    }

    /** Konten, die zuständig sind (Person bzw. alle Personen der Rolle; nicht gesperrt) */
    public static function recipients(array $t): array
    {
        if ($t['assignee_id'] !== null) return self::users([(int) $t['assignee_id']]);
        if ($t['assignee_role'] !== null) {
            return array_slice(app()->db->fetchAll('SELECT id, email, name, role, locale, ui_prefs, disabled FROM users WHERE role = ? AND (disabled IS NULL OR disabled = 0) ORDER BY id',
                [$t['assignee_role']]), 0, self::MAX);
        }
        return [];
    }

    private static function users(array $ids): array
    {
        if (!$ids) return [];
        $in = implode(',', array_map('intval', $ids));
        return app()->db->fetchAll("SELECT id, email, name, role, locale, ui_prefs, disabled FROM users WHERE id IN ($in) AND (disabled IS NULL OR disabled = 0)");
    }

    /** E-Mail an jede Person in ihrer Sprache (nur mit eingeschaltetem Schalter „tasks.mail“) → Anzahl versendet */
    private static function mail(array $t, array $to, string $kind, string $by): int
    {
        $n = 0;
        $before = I18n::locale();
        foreach ($to as $u) {
            if (!filter_var((string) $u['email'], FILTER_VALIDATE_EMAIL) || !UiPrefs::get($u, Tasks::MAIL_PREF, true)) continue;
            $loc = (string) (($u['locale'] ?? '') ?: (app()->settings->get('sys.admin_locale') ?: I18n::SOURCE));
            I18n::setLocale($loc);
            try {
                [$subject, $text] = self::compose($t, $kind, $by);
                $err = Mailer::send($subject, $text, [(string) $u['email']], ['purpose' => 'notify']);
                if ($err === null) $n++; else error_log('[tasks] mail: ' . $err);
            } finally {
                I18n::setLocale($before);
            }
        }
        return $n;
    }

    /** Betreff und Text (ohne Impressum – das hängt Core\Mailer an) @return array{0: string, 1: string} */
    public static function compose(array $t, string $kind, string $by = ''): array
    {
        $subject = $kind === 'remind'
            ? (Tasks::dueState($t['due']) === 'overdue' ? __('Überfällig: {title}', ['title' => $t['title']]) : __('Bald fällig: {title}', ['title' => $t['title']]))
            : __('Neue Aufgabe: {title}', ['title' => $t['title']]);
        $lines = [$kind === 'remind' ? __('Erinnerung an eine Aufgabe auf der Website „{site}“:', ['site' => site_name()])
            : __('{name} hat Ihnen auf der Website „{site}“ eine Aufgabe zugewiesen:', ['name' => $by !== '' ? $by : __('Jemand'), 'site' => site_name()]), '', (string) $t['title']];
        if ($t['due']) $lines[] = __('Fällig: {date}', ['date' => fmt()->date($t['due'])]);
        if ($t['priority'] === 'high') $lines[] = __('Priorität: hoch');
        $lines[] = __('Zuständig: {who}', ['who' => Tasks::assigneeLabel($t)]);
        if ((string) ($t['body'] ?? '') !== '') array_push($lines, '', \Core\Support\Markdown::plain((string) $t['body'], 800));
        array_push($lines, '', __('Öffnen und abhaken: {url}', ['url' => admin_url_abs('/admin/aufgaben/' . (int) $t['id'])]), '',
            __('Diese E-Mails schalten Sie unter Konto → Mitteilungen → Aufgaben ab.'));
        return [$subject, implode("\n", $lines)];
    }
}
