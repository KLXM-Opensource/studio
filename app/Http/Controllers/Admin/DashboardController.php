<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Dashboard\Dashboard;
use Core\Dashboard\Metrics;
use Core\Data\Tables;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Übersicht der Verwaltung (/admin) für die Redaktion: Begrüßung mit Schnellaktionen, Kennzahlen, „Was ist zu tun?“,
 * Statistiken (nachgeladen) und Hilfe. Karten, Daten und Anordnung: Core\Dashboard\Dashboard und Core\Dashboard\Metrics.
 * Die Netzwerk-Übersicht „Alle Websites“ bleibt unter /admin/network (NetworkController).
 */
final class DashboardController extends AdminController
{
    public function index(Request $r): Response
    {
        $user = $this->auth($r);
        $cards = Dashboard::cards($user);
        $checks = Metrics::setupChecks();
        $ext = Dashboard::extensions($user);
        return $this->view('dashboard', [
            'css' => ['css/dashboard.css'],
            'cards' => $cards,
            'figures' => [...Metrics::figures($user), ...$ext['tiles']],
            'todos' => Metrics::todos($user, $checks),
            'recent' => isset($cards['recent']) ? Metrics::recent($user) : null,
            'help' => Dashboard::help($user),
            'actions' => $this->actions(),
            // ohne JavaScript bzw. ?alle=1: nachgeladene Karten sofort ausgeben
            'eager' => $r->str('alle') === '1',
            'customize' => $r->str('anpassen') === '1',
        ]);
    }

    /** Nachgeladene Karte als JSON {html} (GET /admin/api/dashboard/{card}) */
    public function card(Request $r, string $card): Response
    {
        $user = $this->auth($r);
        $cards = Dashboard::cards($user);
        if (!isset($cards[$card])) throw new HttpException(404);
        return self::secure(Response::json(['ok' => true, 'html' => Dashboard::render($card, $user, $cards[$card])]));
    }

    /** Anordnung ändern (POST /admin/api/dashboard/prefs): do = up|down|hide|show|open|close|order|reset, card, order[] */
    public function prefs(Request $r): Response
    {
        $user = $this->auth($r);
        $cards = Dashboard::cards($user);
        $do = $r->str('do');
        $card = $r->str('card');
        if (!in_array($do, ['up', 'down', 'hide', 'show', 'open', 'close', 'order', 'reset'], true) || ($do !== 'reset' && $do !== 'order' && !isset($cards[$card]))) {
            throw new HttpException(422, __('Ungültige Angabe.'));
        }
        $order = array_values(array_filter((array) ($r->post['order'] ?? []), 'is_string'));
        Dashboard::apply($user, $do, $card, array_keys($cards), $order);
        if ($r->wantsJson()) {
            $fresh = Dashboard::cards(app()->db->fetch('SELECT * FROM users WHERE id = ?', [(int) $user['id']]) ?: $user);
            return self::secure(Response::json(['ok' => true, 'order' => array_keys($fresh),
                'hidden' => array_keys(array_filter($fresh, fn($c) => $c['hidden'])), 'closed' => array_keys(array_filter($fresh, fn($c) => $c['closed']))]));
        }
        $msg = $do === 'reset' ? __('Übersicht auf Standard zurückgesetzt') : '';
        return $this->back('/admin' . ($do === 'reset' ? '' : '?anpassen=1') . ($card !== '' && $do !== 'reset' ? '#dash-' . $card : ''), 'success', $msg);
    }

    /** Schnellaktionen der Kopfzeile – nur, was die Rolle darf */
    private function actions(): array
    {
        $a = [];
        if (can('pages.manage') && \Core\Features::on('pages.structure', false)) $a[] = ['href' => '/admin/pages/new', 'label' => __('Neue Seite'), 'icon' => 'plus', 'primary' => true];
        elseif (can('pages.edit')) $a[] = ['href' => '/?edit=1', 'label' => __('Startseite bearbeiten'), 'icon' => 'pencil-simple', 'primary' => true];
        if (can('media.upload') && \Core\Features::on('media')) $a[] = ['href' => '/admin/media', 'label' => __('Medien hochladen'), 'icon' => 'upload-simple'];
        // Eintrag anlegen in der meistgenutzten Tabelle (neue/geänderte Einträge der letzten 30 Tage, sonst die erste)
        $best = null;
        foreach (Metrics::tables() as $t) {
            if (Tables::isShared($t) || \Core\Sources\Sources::all() && $this->fromSource($t)) continue;
            try {
                $n = (int) app()->db->fetchValue("SELECT COUNT(*) FROM {$t['table']} WHERE updated_at >= ?", [Metrics::ago(30)]);
            } catch (\Throwable) {
                $n = 0;
            }
            if ($best === null || $n > $best[1]) $best = [$t, $n];
        }
        if ($best) $a[] = ['href' => '/admin/data/' . $best[0]['handle'] . '/new', 'label' => __('Neu: {name}', ['name' => $best[0]['singular'] ?: $best[0]['name']]), 'icon' => 'plus'];
        $a[] = ['href' => '/', 'label' => __('Website ansehen'), 'icon' => 'arrow-square-out', 'external' => true];
        return $a;
    }

    /** Tabelle wird von einer externen Quelle befüllt (dort legt die Redaktion nichts an) */
    private function fromSource(array $t): bool
    {
        foreach (\Core\Sources\Sources::all() as $s) if (($s['table'] ?? null) === $t['handle']) return true;
        return false;
    }
}
