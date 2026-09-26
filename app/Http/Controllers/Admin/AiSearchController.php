<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\AI\Ai;
use Core\Features;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Search\AdminSettings;
use Core\Search\Search;
use Core\Search\Stats;

/** Grundeinstellungen → Suche (Index neu aufbauen, Zähler leeren) und → KI (Verbindung testen, Anbieter der Installation). */
final class AiSearchController extends AdminController
{
    public function rebuild(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        if (!Search::enabled()) throw new HttpException(404);
        try {
            // Stichwort-Index sofort, Vektoren mit Zeitbudget (Rest nach der Antwort bzw. per Cron)
            $s = Search::process(force: true, full: true, budget: 20.0);
        } catch (\Throwable $e) {
            return $this->back('/admin/system#suche', 'error', __('Suchindex: {error}', ['error' => $e->getMessage()]));
        }
        if ($s === null) return $this->back('/admin/system#suche', 'info', __('Der Suchindex wird gerade abgeglichen – bitte gleich noch einmal ansehen.'));
        $msg = __('Suchindex neu aufgebaut: {n} Dokumente in {s} s.', ['n' => $s['docs'], 's' => str_replace('.', ',', (string) $s['seconds'])]);
        if (Search::semanticActive()) {
            $msg .= ' ' . ($s['vector_error'] ? __('KI-Anbieter: {error}', ['error' => $s['vector_error']])
                : ($s['pending'] ? __('{n} Dokumente bekommen ihre Vektoren im Hintergrund.', ['n' => $s['pending']]) : __('Vektoren aktuell.')));
        }
        return $this->back('/admin/system#suche', $s['vector_error'] ? 'error' : 'success', $msg);
    }

    public function missesClear(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        Stats::clear();
        return $this->back('/admin/system#suche', 'success', __('Liste geleert.'));
    }

    public function test(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        if (!Features::on('ai')) throw new HttpException(404);
        $cap = $r->str('cap');
        if (!isset(Ai::CAPS[$cap])) return $this->back('/admin/system#ki', 'error', __('Unbekannte Fähigkeit.'));
        $t = Ai::test($cap);
        $what = __(Ai::CAPS[$cap]) . ' (' . $t['provider'] . ' · ' . $t['model'] . ')';
        if (!$t['ok']) return $this->back('/admin/system#ki', 'error', __('Verbindung fehlgeschlagen – {what}: {error}', ['what' => $what, 'error' => $t['error']]));
        $detail = $t['dims'] ? __('{n} Dimensionen', ['n' => $t['dims']]) : '„' . $t['text'] . '“';
        return $this->back('/admin/system#ki', 'success', __('Verbindung in Ordnung – {what}: {detail}, {ms} ms.', ['what' => $what, 'detail' => $detail, 'ms' => $t['ms']]));
    }

    public function provider(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        if (!Features::integrator()) throw new HttpException(403, __('Den KI-Anbieter legt die Agentur bzw. Netzwerk-Administration fest.'));
        $errors = AdminSettings::saveProvider((array) ($r->post['ai'] ?? []));
        if ($errors) return $this->back('/admin/system#ki', 'error', implode(' ', $errors));
        return $this->back('/admin/system#ki', 'success', __('KI-Anbieter gespeichert. Mit „Verbindung testen“ prüfen.'));
    }
}
