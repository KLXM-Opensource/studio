<?php
declare(strict_types=1);

namespace Core\Support;

use Core\Http\Request;

/**
 * Meldungen („Issues“) der Redaktion an das Support-Team – zentral für alle Websites.
 *
 * Sichtbarkeit: Support-Team alle · Website-Administration (support.manage) alle der eigenen Website ·
 * Meldende ihre eigenen. Interne Notizen (und deren Anhänge) sieht nur das Support-Team.
 * Ungelesen: je Person und Meldung (Tabelle seen) gegen die letzte öffentliche (bzw. fürs Team: jede) Aktivität.
 */
final class Tickets
{
    public const MAX_FILES = 5;
    public const MAX_BYTES = 8 * 1024 * 1024;
    public const IMAGE_TYPES = [IMAGETYPE_JPEG => ['image/jpeg', 'jpg'], IMAGETYPE_PNG => ['image/png', 'png'],
        IMAGETYPE_WEBP => ['image/webp', 'webp'], IMAGETYPE_GIF => ['image/gif', 'gif']];

    // ------------------------------------------------------------------ Sichtbarkeit

    /** WHERE-Bedingung für die Meldungen, die die angemeldete Person sehen darf */
    private static function scope(string $alias = 'i'): array
    {
        if (Support::isStaff()) return ['1 = 1', []];
        if (Support::isSiteManager()) return ["$alias.site = ?", [site()->key]];
        return ["$alias.reporter_key = ?", [Support::me()]];
    }

    public static function canView(array $issue): bool
    {
        if (Support::isStaff()) return true;
        if (Support::isSiteManager() && $issue['site'] === site()->key) return true;
        return $issue['reporter_key'] === Support::me();
    }

    public static function isReporter(array $issue): bool
    {
        return $issue['reporter_key'] === Support::me();
    }

    public static function find(int $id): ?array
    {
        $row = Support::db()->fetch('SELECT * FROM issues WHERE id = ?', [$id]);
        return $row && self::canView($row) ? $row : null;
    }

    // ------------------------------------------------------------------ Liste

    /**
     * @param array{mine?:bool,site?:string,status?:string,category?:string,priority?:string,assignee?:string,q?:string,page?:int} $f
     * @return array{rows:list<array>,total:int,pages:int}
     */
    public static function list(array $f, int $per = 25): array
    {
        [$where, $params] = self::scope();
        $w = [$where];
        if (!empty($f['mine'])) { $w[] = 'i.reporter_key = ?'; $params[] = Support::me(); }
        if (($f['site'] ?? '') !== '' && Support::isStaff()) { $w[] = 'i.site = ?'; $params[] = $f['site']; }
        $st = (string) ($f['status'] ?? '');
        if ($st === 'offen') $w[] = "i.status IN ('neu','in_arbeit','rueckfrage')";
        elseif (in_array($st, Support::STATUSES, true)) { $w[] = 'i.status = ?'; $params[] = $st; }
        if (in_array($f['category'] ?? '', Support::CATEGORIES, true)) { $w[] = 'i.category = ?'; $params[] = $f['category']; }
        if (($f['priority'] ?? '') === 'dringend') $w[] = "i.priority = 'dringend'";
        $as = (string) ($f['assignee'] ?? '');
        if ($as === 'ich') { $w[] = 'i.assignee_key = ?'; $params[] = Support::me(); }
        elseif ($as === 'niemand') $w[] = 'i.assignee_key IS NULL';
        elseif ($as !== '' && Support::isStaff()) { $w[] = 'i.assignee_key = ?'; $params[] = $as; }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            if (preg_match('~^#?(\d+)$~', $q, $m)) { $w[] = 'i.id = ?'; $params[] = (int) $m[1]; }
            else {
                foreach (array_slice(preg_split('~\s+~', mb_strtolower($q)), 0, 6) as $word) {
                    $w[] = '(LOWER(i.title) LIKE ? OR LOWER(i.body) LIKE ?)';
                    array_push($params, "%$word%", "%$word%");
                }
            }
        }
        $sql = implode(' AND ', $w);
        $db = Support::db();
        $total = (int) $db->fetchValue("SELECT COUNT(*) FROM issues i WHERE $sql", $params);
        $page = max(1, (int) ($f['page'] ?? 1));
        $me = Support::me();
        $col = Support::isStaff() ? 'any' : 'public';
        $rows = $db->fetchAll("SELECT i.*, s.seen_at,
                (SELECT COUNT(*) FROM issue_posts p WHERE p.issue_id = i.id AND p.kind = 'reply'" . (Support::isStaff() ? '' : ' AND p.internal = 0') . ") AS replies,
                (SELECT COUNT(*) FROM files x WHERE x.issue_id = i.id" . (Support::isStaff() ? '' : ' AND x.internal = 0') . ") AS nfiles
            FROM issues i LEFT JOIN seen s ON s.issue_id = i.id AND s.user_key = ?
            WHERE $sql
            ORDER BY CASE WHEN i.status IN ('neu','in_arbeit','rueckfrage') THEN 0 ELSE 1 END, CASE i.priority WHEN 'dringend' THEN 0 ELSE 1 END, i.updated_at DESC
            LIMIT $per OFFSET " . (($page - 1) * $per), [$me, ...$params]);
        foreach ($rows as &$r) {
            $r['unread'] = $r[$col . '_by'] !== null && $r[$col . '_by'] !== $me && ($r['seen_at'] === null || $r[$col . '_at'] > $r['seen_at']);
        }
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / $per))];
    }

    /** Anzahl je Status in der sichtbaren Menge (für Filter) */
    public static function counts(bool $mine = false): array
    {
        [$where, $params] = self::scope();
        if ($mine) { $where .= ' AND i.reporter_key = ?'; $params[] = Support::me(); }
        $out = array_fill_keys(Support::STATUSES, 0);
        foreach (Support::db()->fetchAll("SELECT status, COUNT(*) n FROM issues i WHERE $where GROUP BY status", $params) as $r) $out[$r['status']] = (int) $r['n'];
        $out['offen'] = $out['neu'] + $out['in_arbeit'] + $out['rueckfrage'];
        $out['alle'] = array_sum(array_intersect_key($out, array_flip(Support::STATUSES)));
        return $out;
    }

    /** Ungelesene Meldungen (Navigation): eigene mit neuer Antwort; Team: neue/aktive Meldungen; Website-Admin: öffentliche Aktivität der Website */
    public static function unreadCount(): int
    {
        $me = Support::me();
        $db = Support::db();
        if (Support::isStaff()) {
            return (int) $db->fetchValue("SELECT COUNT(*) FROM issues i LEFT JOIN seen s ON s.issue_id = i.id AND s.user_key = ?
                WHERE i.status IN ('neu','in_arbeit','rueckfrage') AND i.any_by <> ? AND (s.seen_at IS NULL OR i.any_at > s.seen_at)", [$me, $me]);
        }
        [$where, $params] = self::scope();
        return (int) $db->fetchValue("SELECT COUNT(*) FROM issues i LEFT JOIN seen s ON s.issue_id = i.id AND s.user_key = ?
            WHERE $where AND i.public_by <> ? AND (s.seen_at IS NULL OR i.public_at > s.seen_at)", [$me, ...$params, $me]);
    }

    public static function markSeen(int $id): void
    {
        Support::db()->query('INSERT INTO seen (user_key, issue_id, seen_at) VALUES (?, ?, ?) ON CONFLICT(user_key, issue_id) DO UPDATE SET seen_at = excluded.seen_at',
            [Support::me(), $id, now()]);
    }

    /**
     * Kennzahlen für Übersichten (z. B. Netzwerk-Dashboard): offen, neu, dringend (offen), wartet auf Rückmeldung.
     * Ohne Rechteprüfung – der Aufrufer entscheidet, wer die Zahlen sehen darf. $site = null: alle Websites.
     * @return array{open:int,new:int,urgent:int,waiting:int,total:int}
     */
    public static function stats(?string $site = null): array
    {
        $w = $site !== null ? 'WHERE site = ?' : '';
        $row = Support::db()->fetch("SELECT COUNT(*) total,
                SUM(CASE WHEN status IN ('neu','in_arbeit','rueckfrage') THEN 1 ELSE 0 END) open,
                SUM(CASE WHEN status = 'neu' THEN 1 ELSE 0 END) new,
                SUM(CASE WHEN status IN ('neu','in_arbeit','rueckfrage') AND priority = 'dringend' THEN 1 ELSE 0 END) urgent,
                SUM(CASE WHEN status = 'rueckfrage' THEN 1 ELSE 0 END) waiting
            FROM issues $w", $site !== null ? [$site] : []) ?? [];
        return ['open' => (int) ($row['open'] ?? 0), 'new' => (int) ($row['new'] ?? 0), 'urgent' => (int) ($row['urgent'] ?? 0),
            'waiting' => (int) ($row['waiting'] ?? 0), 'total' => (int) ($row['total'] ?? 0)];
    }

    // ------------------------------------------------------------------ Anlegen & Verlauf

    /** Automatischer Kontext – wird der meldenden Person vor dem Absenden vollständig angezeigt */
    public static function context(Request $r, string $from = '', string $viewport = ''): array
    {
        $from = trim($from);
        if ($from === '' && ($ref = (string) ($r->server['HTTP_REFERER'] ?? '')) !== '') {
            $p = parse_url($ref);
            if (($p['host'] ?? '') === preg_replace('~:\d+$~', '', $r->host())) $from = ($p['path'] ?? '') . (isset($p['query']) ? '?' . $p['query'] : '');
        }
        // Nur Pfade dieser Website, ohne Sitzungs-/Einmal-Parameter
        if (!preg_match('~^/[^\s]*$~', $from) || str_starts_with($from, '//') || str_contains($from, '/admin/support/neu')) $from = '';
        $from = preg_replace('~([?&])(csrf|_csrf|token|secret|next)=[^&]*~i', '$1', $from);
        $role = app()->auth->role();
        return array_filter([
            'site' => site()->key . ' (' . Support::siteLabel(site()->key) . ')',
            'url' => mb_substr((string) $from, 0, 300),
            'browser' => mb_substr((string) ($r->server['HTTP_USER_AGENT'] ?? ''), 0, 300),
            'viewport' => preg_match('~^\d{2,5}x\d{2,5}(@[\d.]{1,4})?$~', $viewport) ? $viewport : '',
            'cms' => CMS_NAME . ' ' . CMS_VERSION,
            'theme' => app()->theme->label() . (($v = (string) (app()->theme->def['version'] ?? '')) !== '' ? ' ' . $v : ''),
            'role' => (string) ($role['name'] ?? ''),
            'language' => \Core\I18n::locale(),
            'environment' => environment(),
        ], fn($v) => $v !== '');
    }

    public static function contextLabels(): array
    {
        return ['site' => __('Website'), 'url' => __('Aktuelle Seite der Verwaltung'), 'browser' => __('Browser'), 'viewport' => __('Fenstergröße'),
            'cms' => __('CMS-Version'), 'theme' => __('Kit'), 'role' => __('Ihre Rolle'), 'language' => __('Sprache der Verwaltung'), 'environment' => __('Umgebung')];
    }

    /** @return array{id:int,errors:list<string>} */
    public static function create(array $in, array $uploads, array $context, ?array $user = null): array
    {
        // $user: im Namen dieses Benutzers der Website melden (API/MCP), sonst die angemeldete Person
        $u = $user ?? app()->auth->user();
        $now = now();
        $me = site()->key . ':' . (int) $u['id'];
        $db = Support::db();
        $id = $db->insert('issues', [
            'site' => site()->key, 'reporter_id' => (int) $u['id'], 'reporter_key' => $me, 'reporter_name' => (string) (($u['name'] ?? '') ?: $u['email']), 'reporter_email' => (string) $u['email'],
            'title' => mb_substr(trim((string) $in['title']), 0, 160), 'body' => mb_substr(trim((string) $in['body']), 0, 20000),
            'category' => in_array($in['category'] ?? '', Support::CATEGORIES, true) ? $in['category'] : 'frage',
            'priority' => ($in['priority'] ?? '') === 'dringend' ? 'dringend' : 'normal',
            'status' => 'neu', 'context_json' => $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'created_at' => $now, 'updated_at' => $now, 'public_at' => $now, 'public_by' => $me, 'any_at' => $now, 'any_by' => $me,
        ]);
        $errors = $uploads ? self::storeUploads($uploads, $id, null, false) : [];
        if ($user === null) self::markSeen($id);
        $issue = $db->fetch('SELECT * FROM issues WHERE id = ?', [$id]);
        // Support-Team benachrichtigen (config 'support_notify' + Team-Mitglieder mit Benachrichtigung)
        $to = array_merge((array) app()->config->get('support_notify', []), array_column(array_filter(Support::staff(), fn($s) => (int) $s['notify'] === 1), 'email'));
        Support::mail($to, __('Neue Support-Meldung #{id}: {title}', ['id' => $id, 'title' => $issue['title']]),
            __('Website') . ': ' . Support::siteLabel($issue['site']) . "\n"
            . __('Kategorie') . ': ' . Support::categoryLabel($issue['category']) . ' · ' . __('Priorität') . ': ' . Support::priorityLabel($issue['priority']) . "\n"
            . __('Von') . ': ' . $issue['reporter_name'] . "\n\n" . Markdown::plain($issue['body'], 1200) . "\n\n"
            . Support::urlOn(\Core\Network\Network::siteKey(), '/admin/support/meldung/' . $id));
        return ['id' => $id, 'errors' => $errors];
    }

    /** Antwort (öffentlich) oder interne Notiz; optional Status ändern. @return list<string> Fehler bei Anhängen */
    public static function reply(array $issue, string $body, bool $internal, array $uploads = [], ?string $status = null): array
    {
        $staff = Support::isStaff();
        $internal = $internal && $staff;
        $body = mb_substr(trim($body), 0, 20000);
        $errors = [];
        $db = Support::db();
        if ($body !== '' || $uploads) {
            $pid = $db->insert('issue_posts', ['issue_id' => (int) $issue['id'], 'author_key' => Support::me(), 'author_name' => Support::myName(),
                'author_staff' => (int) $staff, 'kind' => $internal ? 'note' : 'reply', 'internal' => (int) $internal, 'body' => $body, 'created_at' => now()]);
            $errors = self::storeUploads($uploads, (int) $issue['id'], $pid, $internal);
            self::touch((int) $issue['id'], !$internal);
            // Meldende antworten auf eine Rückfrage → wieder „In Arbeit“
            if (!$internal && self::isReporter($issue) && $issue['status'] === 'rueckfrage' && $status === null) $status = 'in_arbeit';
        }
        $changed = $status !== null && $status !== $issue['status'] && in_array($status, Support::STATUSES, true);
        if ($changed) self::setStatus($issue, $status, false);
        // Eine Nachricht je Aktion: Antwort (mit neuem Status) oder nur Statuswechsel
        if ((!$internal && ($body !== '' || $uploads)) || $changed) self::notify($issue, $internal ? '' : $body, $changed ? $status : null);
        return $errors;
    }

    public static function setStatus(array $issue, string $status, bool $notify = true): void
    {
        if (!in_array($status, Support::STATUSES, true) || $status === $issue['status']) return;
        $db = Support::db();
        $db->insert('issue_posts', ['issue_id' => (int) $issue['id'], 'author_key' => Support::me(), 'author_name' => Support::myName(),
            'author_staff' => (int) Support::isStaff(), 'kind' => 'status', 'internal' => 0, 'body' => '',
            'meta' => json_encode(['from' => $issue['status'], 'to' => $status]), 'created_at' => now()]);
        $db->update('issues', ['status' => $status], 'id = :id', ['id' => (int) $issue['id']]);
        self::touch((int) $issue['id'], true);
        if ($notify) self::notify($issue, '', $status);
    }

    public static function assign(array $issue, ?string $key): void
    {
        $staff = array_column(Support::staff(), null, 'user_key');
        $key = $key !== null && isset($staff[$key]) ? $key : null;
        if ($key === $issue['assignee_key']) return;
        $db = Support::db();
        $db->update('issues', ['assignee_key' => $key, 'assignee_name' => $key ? $staff[$key]['name'] : null], 'id = :id', ['id' => (int) $issue['id']]);
        $db->insert('issue_posts', ['issue_id' => (int) $issue['id'], 'author_key' => Support::me(), 'author_name' => Support::myName(),
            'author_staff' => 1, 'kind' => 'assign', 'internal' => 1, 'body' => '', 'meta' => json_encode(['to' => $key ? $staff[$key]['name'] : null]), 'created_at' => now()]);
        self::touch((int) $issue['id'], false);
        if ($key && $key !== Support::me()) {
            Support::mail([(string) $staff[$key]['email']], __('Support-Meldung #{id} wurde Ihnen zugewiesen', ['id' => $issue['id']]),
                $issue['title'] . "\n\n" . Support::urlOn((string) $staff[$key]['site'], '/admin/support/meldung/' . $issue['id']));
        }
    }

    private static function touch(int $id, bool $public): void
    {
        $now = now();
        $me = Support::me();
        $data = ['updated_at' => $now, 'any_at' => $now, 'any_by' => $me];
        if ($public) $data += ['public_at' => $now, 'public_by' => $me];
        Support::db()->update('issues', $data, 'id = :id', ['id' => $id]);
    }

    /** E-Mail an die meldende Person (bei Antworten/Status des Teams) bzw. ans Team (bei Antworten der meldenden Person) */
    private static function notify(array $issue, string $body, ?string $status = null): void
    {
        $id = (int) $issue['id'];
        if (self::isReporter($issue)) {
            $to = $issue['assignee_key'] ? array_column(array_filter(Support::staff(), fn($s) => $s['user_key'] === $issue['assignee_key']), 'email')
                : array_merge((array) app()->config->get('support_notify', []), array_column(array_filter(Support::staff(), fn($s) => (int) $s['notify'] === 1), 'email'));
            Support::mail($to, __('Neue Rückmeldung zu Support-Meldung #{id}: {title}', ['id' => $id, 'title' => $issue['title']]),
                Markdown::plain($body, 1200) . "\n\n" . Support::urlOn(\Core\Network\Network::siteKey(), '/admin/support/meldung/' . $id));
            return;
        }
        $text = ($status !== null ? __('Neuer Status: {status}', ['status' => Support::statusLabel($status)]) . "\n\n" : '')
            . ($body !== '' ? Markdown::plain($body, 1500) . "\n\n" : '')
            . Support::urlOn((string) $issue['site'], '/admin/support/meldung/' . $id);
        Support::mail([(string) $issue['reporter_email']], __('Antwort vom Support zu Ihrer Meldung #{id}: {title}', ['id' => $id, 'title' => $issue['title']]), $text);
    }

    /** Verlauf (Antworten, Notizen, Statuswechsel). Interne Einträge nur fürs Team. */
    public static function posts(array $issue): array
    {
        $staff = Support::isStaff();
        return Support::db()->fetchAll('SELECT * FROM issue_posts WHERE issue_id = ?' . ($staff ? '' : ' AND internal = 0') . ' ORDER BY id', [(int) $issue['id']]);
    }

    /** Anhänge [post_id|0 => list] */
    public static function files(array $issue): array
    {
        $rows = Support::db()->fetchAll('SELECT * FROM files WHERE issue_id = ?' . (Support::isStaff() ? '' : ' AND internal = 0') . ' ORDER BY id', [(int) $issue['id']]);
        $out = [];
        foreach ($rows as $f) $out[(int) ($f['post_id'] ?? 0)][] = $f;
        return $out;
    }

    // ------------------------------------------------------------------ Bildschirmfotos

    /** Hochgeladene Dateien (name[]/tmp_name[] aus $_FILES) → Liste einzelner Dateien */
    public static function normalizeFiles(mixed $f): array
    {
        if (!is_array($f) || !isset($f['name'])) return [];
        if (!is_array($f['name'])) return [$f];
        $out = [];
        foreach ($f['name'] as $i => $name) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $out[] = ['name' => $name, 'tmp_name' => $f['tmp_name'][$i] ?? '', 'error' => $f['error'][$i] ?? 1, 'size' => $f['size'][$i] ?? 0];
        }
        return $out;
    }

    /**
     * Nur Bilder (JPEG, PNG, WebP, GIF), höchstens 5 × 8 MB. JPEG/PNG/WebP werden neu kodiert – das entfernt
     * Metadaten wie GPS-Position oder Kameradaten und macht versteckte Inhalte unschädlich.
     * @return list<string> Fehlermeldungen
     */
    public static function storeUploads(array $files, int $issueId, ?int $postId, bool $internal): array
    {
        $errors = [];
        $n = 0;
        foreach ($files as $f) {
            if (($f['error'] ?? 1) === UPLOAD_ERR_NO_FILE) continue;
            $name = mb_substr(basename(str_replace('\\', '/', (string) ($f['name'] ?? 'bild'))), 0, 120);
            if (++$n > self::MAX_FILES) { $errors[] = __('Höchstens {n} Bilder je Nachricht – „{name}“ wurde nicht übernommen.', ['n' => self::MAX_FILES, 'name' => $name]); continue; }
            if (($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name']) && PHP_SAPI !== 'cli') { $errors[] = __('„{name}“ konnte nicht hochgeladen werden.', ['name' => $name]); continue; }
            if ((int) $f['size'] > self::MAX_BYTES) { $errors[] = __('„{name}“ ist größer als {mb} MB.', ['name' => $name, 'mb' => self::MAX_BYTES / 1048576]); continue; }
            $info = @getimagesize((string) $f['tmp_name']);
            if (!$info || !isset(self::IMAGE_TYPES[$info[2]])) { $errors[] = __('„{name}“ ist kein Bild (erlaubt: JPEG, PNG, WebP, GIF).', ['name' => $name]); continue; }
            [$mime, $ext] = self::IMAGE_TYPES[$info[2]];
            $rel = date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
            $dest = Support::dir('files/' . $rel);
            if (!is_dir(dirname($dest))) @mkdir(dirname($dest), 0770, true);
            if (!self::reencode((string) $f['tmp_name'], $dest, $info[2]) && !@copy((string) $f['tmp_name'], $dest)) {
                $errors[] = __('„{name}“ konnte nicht gespeichert werden.', ['name' => $name]);
                continue;
            }
            @chmod($dest, 0640);
            Support::db()->insert('files', ['issue_id' => $issueId, 'post_id' => $postId, 'internal' => (int) $internal, 'name' => $name, 'stored' => $rel,
                'mime' => $mime, 'size' => (int) filesize($dest), 'width' => (int) $info[0], 'height' => (int) $info[1],
                'uploaded_by' => Support::me(), 'created_at' => now()]);
        }
        return $errors;
    }

    public static function reencode(string $src, string $dest, int $type): bool
    {
        if (!function_exists('imagecreatefromstring') || $type === IMAGETYPE_GIF) return false;
        $im = @imagecreatefromstring((string) file_get_contents($src));
        if (!$im) return false;
        $ok = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($im, $dest, 88),
            IMAGETYPE_PNG => (imagesavealpha($im, true) || true) && imagepng($im, $dest, 6),
            IMAGETYPE_WEBP => function_exists('imagewebp') && imagewebp($im, $dest, 88),
            default => false,
        };
        return $ok && is_file($dest);
    }

    /** Anhang, falls die angemeldete Person ihn sehen darf */
    public static function file(int $id): ?array
    {
        $f = Support::db()->fetch('SELECT * FROM files WHERE id = ?', [$id]);
        if (!$f) return null;
        if ((int) $f['internal'] === 1 && !Support::isStaff()) return null;
        $issue = self::find((int) $f['issue_id']);
        return $issue ? $f : null;
    }

    /** Meldung endgültig löschen (Team; z. B. Tests oder Datenschutz-Anfragen) */
    public static function delete(array $issue): void
    {
        $db = Support::db();
        foreach ($db->fetchAll('SELECT stored FROM files WHERE issue_id = ?', [(int) $issue['id']]) as $f) {
            @unlink(Support::dir('files/' . $f['stored']));
        }
        foreach (['files', 'issue_posts', 'seen'] as $t) $db->query("DELETE FROM $t WHERE issue_id = ?", [(int) $issue['id']]);
        $db->query('DELETE FROM issues WHERE id = ?', [(int) $issue['id']]);
    }
}
