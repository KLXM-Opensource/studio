<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Tasks;

use Core\Cli;

/**
 * Kommandozeile der Aufgaben (Core\Tasks\Tasks) – je Website (--site=…):
 *   tasks:add "Titel" [--text=…] [--link=page:12] [--to=email|ID|role:editor|all] [--due=JJJJ-MM-TT] [--priority=high|normal|low] [--as=email]
 *   tasks:list [--status=open|done|all] [--all]
 *   tasks:done <id> [--comment=…] [--as=email]
 *   tasks:reopen <id> [--comment=…]
 *   tasks:import-guide [--dry-run]      Projekt-Hinweise mit „aufgabe: ja“ als Aufgaben für alle übernehmen (je Hinweis einmal)
 *   tasks:remind [--all]               Erinnerung an fällige Aufgaben (Cron „tasks.remind“; wirkt nur mit eingeschalteter Erinnerung)
 *   tasks:selftest
 * --as=email: im Namen dieses Kontos (Verlauf, keine Mail an sich selbst); sonst „Konsole“. Die Konsole prüft keine Rechte.
 */
final class Console
{
    public static function run(string $cmd, array $args, string $console): int
    {
        if (Cli::flag($args, 'all') && in_array($cmd, ['tasks:list', 'tasks:remind'], true)) return Cli::eachSite($cmd, $args);
        try {
            return match ($cmd) {
                'tasks:add' => self::add($args),
                'tasks:list' => self::list($args),
                'tasks:done', 'tasks:reopen' => self::done($cmd, $args),
                'tasks:import-guide' => self::importGuide($args),
                'tasks:remind' => self::remind(),
                'tasks:selftest' => self::selftest(),
                default => 1,
            };
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            return 1;
        }
    }

    private static function actor(array $args): array
    {
        $as = Cli::opt($args, 'as');
        if ($as === null || $as === '') return Tasks::actor(null, 'cli', __('Konsole'));
        $u = app()->db->fetch('SELECT * FROM users WHERE LOWER(email) = ?', [mb_strtolower($as)]) ?? throw new \InvalidArgumentException("Konto „{$as}“ nicht gefunden.");
        return Tasks::actor($u, 'cli');
    }

    private static function add(array $args): int
    {
        $title = implode(' ', array_slice(Cli::positional($args), 0));
        [$vals, $err] = Tasks::clean(['title' => $title, 'body' => str_replace('\n', "\n", (string) Cli::opt($args, 'text')), 'link' => (string) Cli::opt($args, 'link'),
            'assignee' => (string) Cli::opt($args, 'to'), 'due' => (string) Cli::opt($args, 'due'), 'priority' => Cli::opt($args, 'priority') ?? 'normal']);
        if ($err) {
            foreach ($err as $f => $m) fwrite(STDERR, "  $f: $m\n");
            fwrite(STDERR, "Aufruf: tasks:add \"Titel\" [--text=…] [--link=page:12] [--to=email|role:editor|all] [--due=JJJJ-MM-TT] [--priority=high] --site=…\n");
            return 1;
        }
        $id = Tasks::create($vals, self::actor($args), ['source' => 'cli']);
        echo "Aufgabe #$id angelegt (" . site()->key . '): ' . $vals['title'] . ' · ' . Tasks::assigneeLabel($vals) . ($vals['due'] ? ' · fällig ' . $vals['due'] : '') . "\n";
        return 0;
    }

    private static function list(array $args): int
    {
        $status = Cli::opt($args, 'status') ?? 'open';
        $admin = ['id' => 0, 'role' => 'admin', '_test' => 1];
        $rows = Tasks::list($admin, in_array($status, ['open', 'done', 'all'], true) ? $status : 'open');
        if (!$rows) echo '  Keine Aufgaben (' . site()->key . ", $status).\n";
        foreach ($rows as $t) {
            printf("  %-6s %-5s %-6s %-10s %-24s %s%s\n", $t['id'] !== null ? '#' . $t['id'] : 'Entw.', $t['status'] === 'done' ? 'erl.' : 'offen',
                $t['priority'], $t['due'] ?? '–', mb_strimwidth(Tasks::assigneeLabel($t), 0, 24, '…'), mb_strimwidth((string) $t['title'], 0, 60, '…'),
                $t['link'] ? '  → ' . $t['link'] : '');
            if ($t['status'] === 'done') echo '         erledigt ' . $t['done_at'] . ' von ' . $t['done_label'] . ($t['done_comment'] ? ': ' . $t['done_comment'] : '') . "\n";
        }
        return 0;
    }

    private static function done(string $cmd, array $args): int
    {
        $id = (int) (Cli::positional($args)[0] ?? 0);
        $t = $id ? Tasks::find($id) : null;
        if (!$t) { fwrite(STDERR, "Aufgabe #$id nicht gefunden (" . site()->key . ").\n"); return 1; }
        $comment = (string) Cli::opt($args, 'comment');
        if ($cmd === 'tasks:done') {
            if ($t['status'] === 'done') { echo "Aufgabe #$id ist schon erledigt.\n"; return 0; }
            Tasks::done($id, self::actor($args), $comment);
            echo "Aufgabe #$id erledigt.\n";
        } else {
            if ($t['status'] === 'open') { echo "Aufgabe #$id ist offen.\n"; return 0; }
            Tasks::reopen($id, self::actor($args), $comment);
            echo "Aufgabe #$id wieder offen.\n";
        }
        return 0;
    }

    private static function importGuide(array $args): int
    {
        $dry = Cli::flag($args, 'dry-run');
        $rows = Tasks::importGuide($dry, self::actor($args));
        if (!$rows) echo '  Keine Projekt-Hinweise mit „aufgabe: ja“ (' . site()->key . ").\n";
        $new = 0;
        foreach ($rows as $r) {
            if ($r['state'] === 'neu') $new++;
            printf("  %-10s %-28s %s\n", $r['state'] === 'neu' ? ($dry ? 'würde neu' : 'angelegt') : 'vorhanden', $r['key'], $r['title']);
        }
        echo ($dry ? 'Probelauf – nichts geändert. ' : '') . "$new neue Aufgabe(n) für alle" . ($dry ? ' würden angelegt' : '') . ".\n";
        return 0;
    }

    private static function remind(): int
    {
        $r = Notify::remind();
        echo $r['off'] ? 'Aufgaben (' . site()->key . "): Erinnerung ist ausgeschaltet (Verwaltung → Aufgaben → Erinnerung).\n"
            : 'Aufgaben (' . site()->key . "): {$r['tasks']} fällige Aufgabe(n), {$r['mails']} E-Mail(s).\n";
        return 0;
    }

    private static function selftest(): int
    {
        $res = Tasks::selftest();
        foreach ($res['fails'] as $f) echo "  FEHLER: $f\n";
        echo "  {$res['ok']} Prüfungen bestanden, " . count($res['fails']) . " fehlgeschlagen\n";
        return $res['fails'] ? 1 : 0;
    }
}
