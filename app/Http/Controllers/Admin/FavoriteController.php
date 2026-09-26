<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Favorites;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Favoriten des angemeldeten Benutzers (Core\Favorites). JSON für die Seitenleiste/den Stern,
 * Formulare ohne JavaScript (Konto-Seite) werden zurückgeleitet. CSRF über AdminController::auth().
 */
final class FavoriteController extends AdminController
{
    public function add(Request $r): Response
    {
        $u = $this->auth($r);
        $err = Favorites::add((int) $u['id'], $r->str('url'), $r->str('title'), $r->str('icon'));
        return $this->reply($r, $u, $err, __('Zu Favoriten hinzugefügt'));
    }

    public function remove(Request $r): Response
    {
        $u = $this->auth($r);
        Favorites::remove((int) $u['id'], $r->str('url'));
        return $this->reply($r, $u, null, __('Aus Favoriten entfernt'));
    }

    public function rename(Request $r): Response
    {
        $u = $this->auth($r);
        if (Favorites::title($r->str('title')) === '') {
            return $this->reply($r, $u, __('Bitte einen Namen angeben.'));
        }
        Favorites::rename((int) $u['id'], $r->str('url'), $r->str('title'));
        return $this->reply($r, $u, null, __('Favorit umbenannt'));
    }

    /** order: [url, …] (Ziehen, Alt+↑/↓) oder url + dir=up|down (Formular) */
    public function reorder(Request $r): Response
    {
        $u = $this->auth($r);
        $order = $r->post['order'] ?? null;
        if (is_array($order)) {
            Favorites::reorder((int) $u['id'], array_slice($order, 0, Favorites::MAX));
        } else {
            Favorites::move((int) $u['id'], $r->str('url'), $r->str('dir') === 'up' ? -1 : 1);
        }
        return $this->reply($r, $u, null, __('Reihenfolge gespeichert'));
    }

    private function reply(Request $r, array $u, ?string $err, string $ok = ''): Response
    {
        if ($r->wantsJson()) {
            // ico: Symbolname im Sprite (Core\Icons) – Menü-Schlüssel, Tabellensymbol oder altes Zeichen aufgelöst
            $list = array_map(fn($f) => $f + ['href' => url($f['url']), 'ico' => \Core\Icons::resolve($f['icon'] ?: 'fav')], Favorites::all((int) $u['id']));
            return Response::json(['ok' => $err === null, 'error' => $err, 'message' => $err ?? $ok, 'favorites' => $list], $err === null ? 200 : 422);
        }
        $back = Favorites::normalize($r->str('back')) ?? '/admin/account#favoriten';
        return $this->back($back, $err === null ? 'success' : 'error', $err ?? $ok);
    }
}
