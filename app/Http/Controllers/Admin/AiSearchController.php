<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\AI\Ai;
use Core\AI\Probe;
use Core\AI\Profiles;
use Core\Features;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Search\AdminSettings;
use Core\Search\Search;
use Core\Search\Stats;

/** Grundeinstellungen → Suche (Index neu aufbauen, Zähler leeren) und → KI (Verbindung testen, Verbindungen und Verwendung der Installation). */
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

    /**
     * Verbindungen und Verwendung der Installation (Core\AI\Profiles) – eine Route, Aktion über „op“:
     *   profile   Verbindung anlegen/ändern (nur Agentur)          delete  Verbindung löschen (nur Agentur)
     *   assign    Zuordnungen und Zeitlimits speichern (nur Agentur)
     *   check     Verbindung prüfen → JSON          models  Modelle der Verbindung → JSON
     * check/models: gespeicherte Verbindung („id“) für die Administration; ungespeicherte Werte (Dialog „Verbindung hinzufügen“)
     * nur für die Agentur. Begrenzung: 20 Prüfungen je Minute und Person. Antworten enthalten nie Schlüssel.
     */
    public function provider(Request $r): Response
    {
        $user = $this->auth($r, 'system.manage');
        if (!Features::on('ai')) throw new HttpException(404);
        $op = $r->str('op') ?: 'assign';
        if ($op === 'check' || $op === 'models') return $this->probe($r, $user, $op);
        if (!Features::integrator()) throw new HttpException(403, __('Verbindungen und Modelle legt die Agentur bzw. Netzwerk-Administration fest.'));
        $locks = Profiles::locks(Ai::fileLayer());
        if ($op === 'profile') {
            $id = $r->str('id');
            [$saved, $errors] = Profiles::saveProfile($id !== '' ? $id : null, (array) ($r->post['p'] ?? []), $locks);
            if ($errors) return $this->back('/admin/system#ki', 'error', implode(' ', $errors));
            return $this->back('/admin/system#ki-p-' . $saved, 'success', __('Verbindung gespeichert. Mit „Verbindung prüfen“ testen.'));
        }
        if ($op === 'delete') {
            $id = $r->str('id');
            if (isset($locks['profiles'][$id])) return $this->back('/admin/system#ki', 'error', __('Diese Verbindung ist in einer Konfigurationsdatei festgelegt.'));
            $hit = Profiles::deleteProfile($id);
            return $this->back('/admin/system#ki', 'success', $hit
                ? __('Verbindung gelöscht. Ohne Zuordnung: {list}.', ['list' => implode(', ', array_map(fn($p) => __(Profiles::PURPOSES[$p]), $hit))])
                : __('Verbindung gelöscht.'));
        }
        if ($op === 'assign') {
            $res = Profiles::saveAssign((array) ($r->post['ai'] ?? []), Ai::raw(), $locks);
            if ($res['errors']) return $this->back('/admin/system#ki-use', 'error', implode(' ', $res['errors']));
            \Core\PageCache::clear();
            if ($res['embed_changed']) {
                app()->session->flash('info', __('Das Embedding-Modell hat sich geändert: Der Suchindex berechnet alle Vektoren neu (beim nächsten Abgleich bzw. mit „Index neu aufbauen“). Bis dahin findet die semantische Suche weniger.'));
            }
            return $this->back('/admin/system#ki-use', 'success', __('Verwendung gespeichert.'));
        }
        throw new HttpException(400);
    }

    /** „Verbindung prüfen“ / „Modelle anzeigen“ (JSON) */
    private function probe(Request $r, array $user, string $op): Response
    {
        $limiter = new \Core\RateLimiter(app()->db);
        $key = 'aiprobe:' . (int) ($user['id'] ?? 0);
        if ($limiter->tooMany($key, 20, 60)) return Response::json(['ok' => false, 'status' => 'error', 'message' => __('Zu viele Prüfungen – bitte eine Minute warten.')], 429);
        $limiter->hit($key);
        $id = $r->str('id');
        $profiles = Ai::config()['profiles'];
        $p = $id !== '' ? ($profiles[$id] ?? null) : null;
        $in = (array) ($r->post['p'] ?? []);
        if ($in) {
            // Werte aus dem Formular (noch nicht gespeichert) – nur die Agentur; leerer Schlüssel = gespeicherter Schlüssel
            if (!Features::integrator()) throw new HttpException(403);
            $base = rtrim(trim((string) ($in['base_url'] ?? '')), '/');
            if ($err = Profiles::urlError($base)) return Response::json(['ok' => false, 'status' => 'blocked', 'message' => $err]);
            $key = trim((string) ($in['api_key'] ?? ''));
            $p = Profiles::normalize($id !== '' ? $id : 'neu', ['type' => (string) ($in['type'] ?? ''), 'base_url' => $base, 'region' => (string) ($in['region'] ?? ''),
                'api_key' => $key === '-' ? '' : ($key !== '' ? $key : (string) ($p['api_key'] ?? '')),
                'whisper_bin' => (string) ($in['whisper_bin'] ?? ''), 'model_path' => (string) ($in['model_path'] ?? ''), 'ffmpeg' => (string) ($in['ffmpeg'] ?? '')]);
        }
        if (!$p) return Response::json(['ok' => false, 'status' => 'error', 'message' => __('Unbekannte Verbindung.')], 404);
        $res = $op === 'check' ? Probe::check($p) : Probe::models($p);
        if ($op === 'check' && $id !== '' && !$in) Profiles::rememberCheck($id, $res);
        $res['profile'] = ['id' => $id, 'label' => $p['label'], 'type' => $p['type']];
        return Response::json($res);
    }
}
