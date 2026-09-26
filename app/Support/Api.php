<?php
declare(strict_types=1);

namespace Core\Support;

use Core\Api\ApiError;
use Core\Features;
use Core\Permissions;

/**
 * Support für REST-API und MCP (eine Implementierung, zwei Schnittstellen):
 *   GET /api/v1/kb?q=…            veröffentlichte Wissensartikel, die für diese Website sichtbar sind
 *   MCP search_knowledge / report_issue
 */
final class Api
{
    private static function enabled(): void
    {
        if (!Features::on('support', false)) throw new ApiError(404, 'Support ist auf dieser Website abgeschaltet.');
    }

    /** @return array{query:string,articles:list<array>} */
    public static function knowledge(string $q, int $limit = 10): array
    {
        self::enabled();
        $q = trim(mb_substr($q, 0, 200));
        $limit = max(1, min(50, $limit));
        $rows = $q === '' ? Knowledge::list([], $limit)['rows'] : Knowledge::search($q, $limit);
        return ['query' => $q, 'articles' => array_values(array_map(fn($a) => [
            'id' => (int) $a['id'], 'title' => $a['title'], 'tags' => Support::tagList((string) $a['tags']),
            'excerpt' => Markdown::plain($a['body'], 240), 'body' => $a['body'], 'body_format' => 'markdown-lite',
            'updated_at' => $a['updated_at'], 'url' => absolute_url('/admin/support/wissen/' . $a['id']),
        ], array_filter($rows, fn($a) => $a['status'] === 'published')))];
    }

    /** Meldung im Namen des Benutzers, dem das Token gehört */
    public static function report(array $token, array $args): array
    {
        self::enabled();
        $uid = $token['user_id'] ?? null;
        $user = $uid !== null ? app()->db->fetch('SELECT id, email, name, role FROM users WHERE id = ?', [(int) $uid]) : null;
        if (!$user) throw new ApiError(403, 'report_issue braucht ein Token, das einem Benutzer gehört (API & MCP → Token mit Benutzer anlegen).');
        if (!Permissions::allows(Permissions::role((string) $user['role']), 'support.report')) {
            throw new ApiError(403, 'Die Rolle des Token-Benutzers darf keine Probleme melden (Recht support.report).');
        }
        $title = trim((string) ($args['title'] ?? ''));
        $body = trim((string) ($args['description'] ?? $args['body'] ?? ''));
        if (mb_strlen($title) < 4 || mb_strlen($body) < 10) throw new ApiError(422, 'title (≥ 4 Zeichen) und description (≥ 10 Zeichen) angeben.');
        $res = Tickets::create([
            'title' => $title, 'body' => $body,
            'category' => in_array($args['category'] ?? '', Support::CATEGORIES, true) ? $args['category'] : 'frage',
            'priority' => ($args['priority'] ?? '') === 'dringend' ? 'dringend' : 'normal',
        ], [], ['site' => site()->key . ' (' . Support::siteLabel(site()->key) . ')', 'cms' => CMS_NAME . ' ' . CMS_VERSION,
            'browser' => 'API/MCP-Token „' . ($token['name'] ?? '') . '“', 'environment' => environment()], $user);
        return ['id' => $res['id'], 'status' => 'neu', 'url' => absolute_url('/admin/support/meldung/' . $res['id']),
            'hint' => 'Das Support-Team antwortet in der Verwaltung unter Support › Meine Meldungen und per E-Mail.'];
    }
}
