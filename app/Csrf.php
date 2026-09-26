<?php
declare(strict_types=1);

namespace Core;

/** CSRF-Schutz für alle Admin-POSTs (sessionbasiert). */
final class Csrf
{
    public static function token(): string
    {
        $s = app()->session;
        $t = $s->get('_csrf');
        if (!$t) {
            $t = bin2hex(random_bytes(32));
            $s->set('_csrf', $t);
        }
        return $t;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function valid(Http\Request $r): bool
    {
        $sent = (string) ($r->post['_csrf'] ?? $r->server['HTTP_X_CSRF_TOKEN'] ?? '');
        $t = app()->session->get('_csrf');
        return is_string($t) && $sent !== '' && hash_equals($t, $sent);
    }
}
