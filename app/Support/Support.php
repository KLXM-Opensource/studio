<?php
declare(strict_types=1);

namespace Core\Support;

use Core\Database;
use Core\Features;
use Core\Site;
use Core\Sites;

/**
 * Support & Wissensdatenbank – zentral für alle Websites einer Installation.
 *
 *   storage/support/support.sqlite   Meldungen, Antworten, Wissensartikel, Fragen & Antworten, Suche (FTS5)
 *   storage/support/files/           Bildschirmfotos (nie öffentlich – nur über /admin/support/datei/{id})
 *
 * Wie die geteilten Medien (Core\MediaPools) öffnet jede Website dieselbe Datenbank. Personen werden über
 * „Website:Benutzer-ID“ erkannt (z. B. „demo:3“), weil Benutzer je Website getrennt sind.
 *
 * Rollen:
 *   Support-Team  = Features::integrator() (Agentur/Netzwerk-Administration) oder Recht „support.manage“ auf der
 *                   Hauptwebsite (config 'network_site') → sieht und bearbeitet alle Meldungen aller Websites.
 *   Website-Admin = „support.manage“ auf einer anderen Website → sieht alle Meldungen der eigenen Website und antwortet.
 *   Redaktion     = „support.report“ (melden, eigene Meldungen) und „support.answer“ (Fragen stellen, antworten).
 */
final class Support
{
    public const STATUSES = ['neu', 'in_arbeit', 'rueckfrage', 'geloest', 'geschlossen'];
    public const OPEN = ['neu', 'in_arbeit', 'rueckfrage'];
    public const CATEGORIES = ['frage', 'fehler', 'wunsch'];
    public const PRIORITIES = ['normal', 'dringend'];

    private static ?Database $db = null;
    private static ?bool $staff = null;

    public static function dir(string $sub = ''): string
    {
        return ROOT . '/storage/support' . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }

    /** Zentrale Datenbank (legt Tabellen, Suchindex und die Startartikel beim ersten Öffnen an) */
    public static function db(): Database
    {
        if (self::$db === null) {
            $fresh = !is_file(self::dir('support.sqlite'));
            self::$db = new Database(['driver' => 'sqlite', 'path' => self::dir('support.sqlite')]);
            @chmod(self::dir(), 0770);
            self::migrate(self::$db);
            if ($fresh) Seed::run();
        }
        return self::$db;
    }

    /** Für Tests / nach Wiederherstellen */
    public static function reset(): void
    {
        self::$db = null;
        self::$staff = null;
    }

    private static function migrate(Database $db): void
    {
        $pk = 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tables = [
            'issues' => "id $pk, site VARCHAR(40) NOT NULL, reporter_id INT NOT NULL, reporter_key VARCHAR(80) NOT NULL, reporter_name TEXT, reporter_email TEXT,
                title TEXT NOT NULL, body TEXT NOT NULL, category VARCHAR(20) NOT NULL DEFAULT 'frage', priority VARCHAR(20) NOT NULL DEFAULT 'normal',
                status VARCHAR(20) NOT NULL DEFAULT 'neu', assignee_key VARCHAR(80) NULL, assignee_name TEXT NULL, context_json TEXT NULL,
                kb_article_id INT NULL, created_at VARCHAR(25) NOT NULL, updated_at VARCHAR(25) NOT NULL,
                public_at VARCHAR(25) NULL, public_by VARCHAR(80) NULL, any_at VARCHAR(25) NULL, any_by VARCHAR(80) NULL",
            'issue_posts' => "id $pk, issue_id INT NOT NULL, author_key VARCHAR(80) NOT NULL, author_name TEXT, author_staff INT NOT NULL DEFAULT 0,
                kind VARCHAR(20) NOT NULL DEFAULT 'reply', internal INT NOT NULL DEFAULT 0, body TEXT, meta TEXT NULL, created_at VARCHAR(25) NOT NULL",
            'files' => "id $pk, issue_id INT NOT NULL, post_id INT NULL, internal INT NOT NULL DEFAULT 0, name TEXT, stored TEXT NOT NULL, mime VARCHAR(40) NOT NULL,
                size INT NOT NULL DEFAULT 0, width INT NULL, height INT NULL, uploaded_by VARCHAR(80), created_at VARCHAR(25) NOT NULL",
            'seen' => "user_key VARCHAR(80) NOT NULL, issue_id INT NOT NULL, seen_at VARCHAR(25) NOT NULL, PRIMARY KEY (user_key, issue_id)",
            'staff' => "user_key VARCHAR(80) NOT NULL PRIMARY KEY, site VARCHAR(40) NOT NULL, user_id INT NOT NULL, name TEXT, email TEXT,
                notify INT NOT NULL DEFAULT 1, last_seen_at VARCHAR(25)",
            'articles' => "id $pk, title TEXT NOT NULL, body TEXT NOT NULL, tags TEXT NOT NULL DEFAULT '', visibility VARCHAR(10) NOT NULL DEFAULT 'all',
                sites TEXT NOT NULL DEFAULT '', status VARCHAR(12) NOT NULL DEFAULT 'published', author_key VARCHAR(80), author_name TEXT,
                editor_key VARCHAR(80), editor_name TEXT, source_issue_id INT NULL, views INT NOT NULL DEFAULT 0, helpful_yes INT NOT NULL DEFAULT 0,
                helpful_no INT NOT NULL DEFAULT 0, created_at VARCHAR(25) NOT NULL, updated_at VARCHAR(25) NOT NULL",
            'article_revisions' => "id $pk, article_id INT NOT NULL, title TEXT, body TEXT, tags TEXT, visibility VARCHAR(10), sites TEXT,
                editor_key VARCHAR(80), editor_name TEXT, note TEXT, created_at VARCHAR(25) NOT NULL",
            'article_feedback' => "article_id INT NOT NULL, user_key VARCHAR(80) NOT NULL, helpful INT NOT NULL, created_at VARCHAR(25), PRIMARY KEY (article_id, user_key)",
            'questions' => "id $pk, site VARCHAR(40) NOT NULL, author_key VARCHAR(80) NOT NULL, author_name TEXT, author_staff INT NOT NULL DEFAULT 0,
                title TEXT NOT NULL, body TEXT NOT NULL, tags TEXT NOT NULL DEFAULT '', visibility VARCHAR(10) NOT NULL DEFAULT 'all',
                accepted_answer_id INT NULL, score INT NOT NULL DEFAULT 0, answers INT NOT NULL DEFAULT 0, views INT NOT NULL DEFAULT 0,
                created_at VARCHAR(25) NOT NULL, updated_at VARCHAR(25) NOT NULL, activity_at VARCHAR(25) NOT NULL",
            'answers' => "id $pk, question_id INT NOT NULL, site VARCHAR(40) NOT NULL, author_key VARCHAR(80) NOT NULL, author_name TEXT, author_staff INT NOT NULL DEFAULT 0,
                body TEXT NOT NULL, score INT NOT NULL DEFAULT 0, created_at VARCHAR(25) NOT NULL, updated_at VARCHAR(25) NOT NULL",
            'votes' => "target VARCHAR(1) NOT NULL, target_id INT NOT NULL, user_key VARCHAR(80) NOT NULL, value INT NOT NULL DEFAULT 1, created_at VARCHAR(25),
                PRIMARY KEY (target, target_id, user_key)",
            'mail_log' => "id $pk, to_addr TEXT, subject TEXT, status TEXT, site VARCHAR(40), created_at VARCHAR(25) NOT NULL",
        ];
        foreach ($tables as $name => $cols) {
            $db->pdo->exec("CREATE TABLE IF NOT EXISTS $name ($cols)");
        }
        foreach ([
            'CREATE INDEX IF NOT EXISTS issues_site ON issues(site, status)',
            'CREATE INDEX IF NOT EXISTS issues_reporter ON issues(reporter_key)',
            'CREATE INDEX IF NOT EXISTS posts_issue ON issue_posts(issue_id)',
            'CREATE INDEX IF NOT EXISTS answers_q ON answers(question_id)',
        ] as $sql) $db->pdo->exec($sql);
        Search::migrate($db);
    }

    // ------------------------------------------------------------------ Rechte

    /** Funktion „support“ auf dieser Website an (Integratoren: immer) */
    public static function enabled(): bool
    {
        return Features::on('support');
    }

    public static function canReport(): bool
    {
        self::ensureRoles();
        return self::enabled() && (self::isStaff() || can('support.report'));
    }

    /** Wissensdatenbank und Fragen lesen, abstimmen, „hilfreich?“ */
    public static function canRead(): bool
    {
        return self::canReport() || (self::enabled() && (can('support.answer') || can('support.manage')));
    }

    /** Fragen stellen und beantworten */
    public static function canAnswer(): bool
    {
        self::ensureRoles();
        return self::enabled() && (self::isStaff() || can('support.answer'));
    }

    /** Support-Team: alle Websites, Status, Zuweisung, interne Notizen, Wissensartikel, Moderation */
    public static function isStaff(): bool
    {
        if (self::$staff !== null) return self::$staff;
        if (!isset(app()->auth) || !app()->auth->user()) return false;
        $network = \Core\Network\Network::isNetworkSite();
        return self::$staff = Features::integrator() || ($network && can('support.manage'));
    }

    /** Alle Meldungen der eigenen Website sehen und beantworten (Website-Administration) */
    public static function isSiteManager(): bool
    {
        return self::isStaff() || (self::enabled() && can('support.manage'));
    }

    /**
     * Bestehende Rollen einmalig ergänzen: „support.report“ für alle, „support.answer“ für die Redaktion.
     * Danach frei änderbar unter Benutzer & Rollen (je Website, Merker in den Einstellungen).
     */
    public static function ensureRoles(): void
    {
        static $done = false;
        if ($done || !isset(app()->settings)) return;
        $done = true;
        if ((int) app()->settings->get('sys.support_roles', 0) >= 1) return;
        try {
            foreach (app()->db->fetchAll('SELECT rkey, permissions_json FROM roles') as $r) {
                $p = json_decode((string) $r['permissions_json'], true) ?: [];
                if (in_array('*', $p, true)) continue;
                $add = ['support.report'];
                if (in_array($r['rkey'], ['editor'], true)) $add[] = 'support.answer';
                $new = array_values(array_unique([...$p, ...$add]));
                if ($new !== $p) app()->db->update('roles', ['permissions_json' => json_encode($new)], 'rkey = :k', ['k' => $r['rkey']]);
            }
            app()->settings->set('sys.support_roles', 1);
        } catch (\Throwable $e) {
            error_log('[support] roles: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ Person

    /** Kennung der angemeldeten Person: „Website:ID“ */
    public static function me(): string
    {
        $u = app()->auth->user();
        return site()->key . ':' . (int) ($u['id'] ?? 0);
    }

    public static function myName(): string
    {
        $u = app()->auth->user() ?? [];
        return (string) (($u['name'] ?? '') ?: ($u['email'] ?? ''));
    }

    /** Support-Team-Mitglied vormerken (für Zuweisung und Benachrichtigung) */
    public static function registerStaff(): void
    {
        if (!self::isStaff()) return;
        $u = app()->auth->user();
        $db = self::db();
        $key = self::me();
        $row = $db->fetch('SELECT user_key, last_seen_at FROM staff WHERE user_key = ?', [$key]);
        if ($row && substr((string) $row['last_seen_at'], 0, 10) === date('Y-m-d')) return;
        if ($row) {
            $db->update('staff', ['name' => self::myName(), 'email' => (string) $u['email'], 'last_seen_at' => now()], 'user_key = :k', ['k' => $key]);
        } else {
            $db->insert('staff', ['user_key' => $key, 'site' => site()->key, 'user_id' => (int) $u['id'], 'name' => self::myName(),
                'email' => (string) $u['email'], 'notify' => 1, 'last_seen_at' => now()]);
        }
    }

    /** @return list<array{user_key:string,name:string,email:string,site:string,notify:int}> */
    public static function staff(): array
    {
        return self::db()->fetchAll('SELECT * FROM staff ORDER BY name');
    }

    /** Anzeigename einer Person für die Betrachtenden – fremde Websites bleiben anonym (Datenschutz) */
    public static function authorLabel(string $key, ?string $name, bool $staff = false): string
    {
        if ($staff) return trim(($name ?: __('Support')) . ' · ' . __('Support-Team'));
        [$site] = explode(':', $key, 2) + [''];
        if ($site === site()->key || self::isStaff()) {
            return (string) ($name ?: __('Unbekannt')) . (self::isStaff() && $site !== site()->key ? ' · ' . self::siteLabel($site) : '');
        }
        return __('Redaktion einer anderen Website');
    }

    // ------------------------------------------------------------------ Websites

    /** [key => Bezeichnung] */
    public static function sites(): array
    {
        $out = [];
        foreach (Sites::all() as $k => $cfg) {
            $out[$k] = (string) ($cfg['label'] ?? ($cfg['hosts'][0] ?? ($k === Site::DEFAULT ? (app()->site->isDefault() ? site_name() : __('Hauptwebsite')) : $k)));
        }
        return $out;
    }

    public static function siteLabel(string $key): string
    {
        return self::sites()[$key] ?? $key;
    }

    /** Absolute Adresse auf einer bestimmten Website (für E-Mails) */
    public static function urlOn(string $site, string $path): string
    {
        if ($site === site()->key) return absolute_url($path);
        $host = (string) ((Sites::all()[$site]['hosts'] ?? [])[0] ?? '');
        if ($host === '') return absolute_url($path);
        $scheme = (app()->request && app()->request->isSecure()) || !str_contains($host, 'localhost') ? 'https://' : 'http://';
        return $scheme . $host . url($path);
    }

    // ------------------------------------------------------------------ Beschriftungen

    public static function statusLabel(string $s): string
    {
        return match ($s) {
            'neu' => __('Neu'), 'in_arbeit' => __('In Arbeit'), 'rueckfrage' => __('Wartet auf Rückmeldung'),
            'geloest' => __('Gelöst'), 'geschlossen' => __('Geschlossen'), default => $s,
        };
    }

    public static function categoryLabel(string $c): string
    {
        return match ($c) { 'frage' => __('Frage'), 'fehler' => __('Fehler'), 'wunsch' => __('Wunsch'), default => $c };
    }

    public static function priorityLabel(string $p): string
    {
        return $p === 'dringend' ? __('Dringend') : __('Normal');
    }

    public static function visibilityLabel(string $v): string
    {
        return match ($v) { 'all' => __('Alle Websites'), 'sites' => __('Bestimmte Websites'), 'staff' => __('Nur Support-Team'), 'site' => __('Nur meine Website'), default => $v };
    }

    /** Tags normalisieren: klein, ohne Sonderzeichen, höchstens 8 */
    public static function tags(string|array $in): array
    {
        $list = is_array($in) ? $in : preg_split('~[,;#\n]+~', $in);
        $out = [];
        foreach ($list as $t) {
            $t = mb_strtolower(trim((string) $t));
            $t = preg_replace('~[^\p{L}\p{N}\- ]+~u', '', $t);
            $t = trim(preg_replace('~\s+~', '-', $t), '-');
            if ($t !== '' && mb_strlen($t) <= 30) $out[$t] = true;
        }
        return array_map('strval', array_slice(array_keys($out), 0, 8));
    }

    public static function tagString(array $tags): string
    {
        return $tags ? ',' . implode(',', $tags) . ',' : '';
    }

    public static function tagList(string $stored): array
    {
        return array_values(array_filter(explode(',', $stored)));
    }

    // ------------------------------------------------------------------ Zähler (Navigation)

    /** Ungelesenes für die Navigation: eigene Meldungen mit neuer Antwort + (Team) neue/aktualisierte Meldungen */
    public static function navCount(): int
    {
        try {
            if (!self::canReport()) return 0;
            return Tickets::unreadCount();
        } catch (\Throwable $e) {
            error_log('[support] nav: ' . $e->getMessage());
            return 0;
        }
    }

    // ------------------------------------------------------------------ E-Mail

    /** Benachrichtigung über den Mailer der Website (respektiert 'mail_redirect' außerhalb der Produktion) */
    public static function mail(array $to, string $subject, string $text): void
    {
        $to = array_values(array_unique(array_filter($to, fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL))));
        if (!$to) return;
        try {
            $err = \Core\Mailer::send($subject, $text . "\n\n—\n" . __('Automatische Nachricht des Supports ({name}).', ['name' => CMS_NAME]), $to);
        } catch (\Throwable $e) {
            $err = $e->getMessage();
        }
        $status = $err === null ? (environment() === 'production' ? 'sent' : 'redirected/logged (' . environment() . ')') : 'error: ' . $err;
        try {
            self::db()->insert('mail_log', ['to_addr' => implode(', ', $to), 'subject' => $subject, 'status' => mb_substr($status, 0, 300), 'site' => site()->key, 'created_at' => now()]);
        } catch (\Throwable) {}
    }

    // ------------------------------------------------------------------ Spotlight (⌘K)

    /** Gruppen für die übergreifende Suche (SearchController) */
    public static function spotlight(string $q, callable $score): array
    {
        if (!self::canRead()) return [];
        $groups = [];
        $actions = [];
        if (self::canReport()) $actions[] = [__('Problem melden'), __('Support'), '/admin/support/neu', 'plus', 'hilfe problem melden fehler support ticket bug wunsch'];
        if (self::canAnswer()) $actions[] = [__('Frage stellen'), __('Fragen & Antworten'), '/admin/support/fragen/neu', 'help', 'frage stellen hilfe community'];
        $actions[] = [__('Wissensdatenbank'), __('Anleitungen & Lösungen'), '/admin/support/wissen', 'help', 'wissen wissensdatenbank knowledge anleitung lösung faq'];
        $actions[] = [__('Meine Meldungen'), __('Support'), '/admin/support', 'inbox', 'support meldungen tickets'];
        $items = [];
        foreach ($actions as [$t, $sub, $href, $icon, $kw]) {
            $s = $score($q, $t . ' ' . $kw);
            if ($q === '' || $s > 0) $items[] = ['title' => $t, 'sub' => $sub, 'url' => url($href), 'icon' => $icon, 's' => $s];
        }
        if (mb_strlen($q) >= 2) {
            $hits = [];
            foreach (Knowledge::search($q, 5) as $a) {
                $hits[] = ['title' => $a['title'], 'sub' => __('Wissensartikel'), 'url' => url('/admin/support/wissen/' . $a['id']), 'icon' => 'help'];
            }
            foreach (Questions::search($q, 4) as $x) {
                $hits[] = ['title' => $x['title'], 'sub' => __('Frage') . ' · ' . __('{n} Antworten', ['n' => (int) $x['answers']]) . ($x['accepted_answer_id'] ? ' ✓' : ''),
                    'url' => url('/admin/support/fragen/' . $x['id']), 'icon' => 'help'];
            }
            if ($hits) $groups[] = ['label' => __('Wissensdatenbank'), 'items' => $hits];
        }
        if ($items) {
            usort($items, fn($a, $b) => $b['s'] <=> $a['s']);
            array_unshift($groups, ['label' => __('Hilfe & Support'), 'items' => array_slice($items, 0, $q === '' ? 2 : 4)]);
        }
        return $groups;
    }
}
