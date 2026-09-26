<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Extensions;
use Core\FeatureInfo;
use Core\FeatureLog;
use Core\Features;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mfa;
use Core\Permissions;
use Core\RateLimiter;

/**
 * Administration → Funktionen & Erweiterungen (/admin/funktionen): Funktionen der Website und installierte Erweiterungen
 * ein- und ausschalten. Recht system.features (Haupt-Admin) bzw. Integratoren/Netzwerk-Administration; in Netzwerk-
 * Installationen sieht die Website-Administration die Seite nur lesend, bis das Netzwerk die Freigabe erteilt.
 * Sicherheitsrelevante Schalter verlangen Bestätigung des Risikos und das Passwort (wie „Passwort bestätigen“ bei Passkeys).
 * Werte aus der Konfiguration (config/sites/{key}.php, config.local.php) gehen vor und sperren den Schalter.
 */
final class FeaturesController extends AdminController
{
    private function guard(Request $r, bool $manage = false): array
    {
        $user = $this->auth($r);
        if (!Features::canView()) throw new HttpException(403, __('Keine Berechtigung.'));
        if ($manage && !Features::canManage()) throw new HttpException(403, __('Freischaltung durch die Agentur – hier nur lesbar.'));
        return $user;
    }

    public function index(Request $r): Response
    {
        $user = $this->guard($r);
        $perms = [];
        foreach (Permissions::catalog() as $group) $perms += $group;
        $state = Features::all();
        $rows = [];
        foreach (Features::catalog() as $key => [$label, $fperms]) {
            $info = FeatureInfo::get($key);
            $dep = Features::REQUIRES[$key] ?? null;
            $lock = Features::lock($key);
            $rows[$info['group']][$key] = [
                'key' => $key, 'label' => __((string) $label), 'on' => (bool) ($state[$key] ?? true), 'lock' => $lock,
                // Schalter steht auf „an“, wirkt aber nicht, weil die Voraussetzung fehlt
                'dormant' => Features::dormant($key),
                'dep' => $dep, 'dependents' => array_keys(array_filter(Features::REQUIRES, fn($d) => $d === $key)),
                'perms' => array_map(fn($p) => $perms[$p] ?? $p, $fperms),
                'extension' => self::extensionOf($key),
            ] + $info;
        }
        $exts = [];
        foreach (Extensions::available() as $name => $m) {
            $exts[$name] = Extensions::meta($name) + ['active' => Extensions::isActive($name), 'enabled' => in_array($name, Extensions::enabled(), true),
                'lock' => Extensions::lock($name), 'requirements' => Extensions::requirements($name), 'health' => Extensions::healthOf($name),
                'contrib' => Extensions::contributions($name), 'usage' => Extensions::usage($name)];
        }
        return $this->view('features', ['title' => __('Funktionen & Erweiterungen'), 'rows' => $rows, 'exts' => $exts,
            'canManage' => Features::canManage(), 'networked' => Features::networked(), 'delegated' => Features::delegated(),
            'integrator' => Features::integrator(), 'log' => FeatureLog::recent(10), 'css' => []]);
    }

    /** Funktion ein- oder ausschalten */
    public function feature(Request $r): Response
    {
        $user = $this->guard($r, true);
        $key = $r->str('key');
        $on = $r->str('on') === '1';
        $back = '/admin/funktionen#f-' . preg_replace('~[^a-z0-9]~', '-', $key);
        $catalog = Features::catalog();
        if (!isset($catalog[$key])) return $this->back('/admin/funktionen', 'error', __('Unbekannte Funktion.'));
        $label = __((string) $catalog[$key][0]);
        if ($lock = Features::lock($key)) {
            return $this->back($back, 'error', __('„{label}“ ist per Konfiguration festgelegt ({file}) – hier nicht änderbar.', ['label' => $label, 'file' => $lock['file']]));
        }
        $old = (bool) (Features::all()[$key] ?? true);
        if ($on && ($dep = Features::REQUIRES[$key] ?? null) && !(Features::all()[$dep] ?? true)) {
            return $this->back($back, 'error', __('„{label}“ braucht zuerst „{dep}“.', ['label' => $label, 'dep' => __((string) ($catalog[$dep][0] ?? $dep))]));
        }
        if ($on && FeatureInfo::risky($key) && ($err = $this->confirm($r, $user))) return $this->back($back, 'error', $err);
        Features::setUi($key, $on);
        if ($key === 'chat' && $on && method_exists(\Core\Chat\Chat::class, 'ensureDefaultChannel')) \Core\Chat\Chat::ensureDefaultChannel();
        FeatureLog::add('feature', $key, $old, $on);
        $this->changed();
        $msg = $on ? __('„{label}“ ist eingeschaltet.', ['label' => $label]) : __('„{label}“ ist ausgeschaltet – Rechte, Menüpunkte und Schnittstellen dazu sind gesperrt.', ['label' => $label]);
        $sleep = array_keys(array_filter(Features::REQUIRES, fn($d, $k) => $d === $key && isset($catalog[$k]), ARRAY_FILTER_USE_BOTH));
        if (!$on && $sleep) $msg .= ' ' . __('Damit ruhen auch: {list}.', ['list' => implode(', ', array_map(fn($k) => __((string) $catalog[$k][0]), $sleep))]);
        return $this->back($back, 'success', $msg);
    }

    /** Erweiterung ein- oder ausschalten (nicht destruktiv) */
    public function extension(Request $r): Response
    {
        $user = $this->guard($r, true);
        $name = $r->str('name');
        $on = $r->str('on') === '1';
        $back = '/admin/funktionen#x-' . preg_replace('~[^a-z0-9]~', '-', $name);
        if (!isset(Extensions::available()[$name])) return $this->back('/admin/funktionen#erweiterungen', 'error', __('Unbekannte Erweiterung.'));
        $meta = Extensions::meta($name);
        $old = in_array($name, Extensions::enabled(), true);
        try {
            if ($on) {
                if ($meta['risk'] !== '' && ($err = $this->confirm($r, $user))) return $this->back($back, 'error', $err);
                $new = Extensions::activate($name);
                // Eigene Funktionen der Erweiterung, die Standard AUS sind (z. B. video.tools): mit einschalten, sofern nicht gesperrt
                $enabled = [];
                foreach ($new as $fk) {
                    if (!Features::on($fk, false) && !Features::lock($fk)) {
                        Features::setUi($fk, true);
                        $enabled[] = $fk;
                        FeatureLog::add('feature', $fk, false, true, __('mit Erweiterung {name}', ['name' => $name]));
                    }
                }
                FeatureLog::add('extension', $name, $old, true, $meta['version'] !== '' ? 'v' . $meta['version'] : '');
                $msg = __('„{label}“ ist eingeschaltet. Datenbank-Schritte sind erledigt; Menüpunkte erscheinen ab dem nächsten Seitenaufruf.', ['label' => $meta['label']]);
                if ($enabled) $msg .= ' ' . __('Mit eingeschaltet: {list}.', ['list' => implode(', ', $enabled)]);
            } else {
                Extensions::deactivate($name);
                FeatureLog::add('extension', $name, $old, false);
                $msg = __('„{label}“ ist ausgeschaltet. Daten und Einstellungen bleiben erhalten; Routen, Menüpunkte, Befehle und Website-Skripte der Erweiterung entfallen.', ['label' => $meta['label']]);
            }
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            return $this->back($back, 'error', $e->getMessage());
        }
        $this->changed();
        return $this->back($back, 'success', $msg);
    }

    /** Netzwerk: der Website-Administration das Schalten freigeben (Recht system.features wirkt dann) */
    public function delegate(Request $r): Response
    {
        $this->auth($r);
        if (!Features::networked() || !Features::integrator()) throw new HttpException(403, __('Nur für die Netzwerk-Administration.'));
        $on = $r->str('on') === '1';
        $old = Features::delegated();
        app()->settings->set(Features::DELEGATE_KEY, $on);
        FeatureLog::add('delegate', site()->key, $old, $on);
        return $this->back('/admin/funktionen#freigabe', 'success', $on
            ? __('Die Administration dieser Website darf Funktionen und Erweiterungen jetzt selbst schalten (Recht „Funktionen & Erweiterungen“).')
            : __('Freigabe zurückgenommen – Funktionen und Erweiterungen schaltet wieder nur die Agentur.'));
    }

    /** Bestätigung des Risikos + Passwort (Netzwerk-Konten: Passwort des Netzwerk-Kontos). null = in Ordnung, sonst Fehlertext */
    private function confirm(Request $r, array $user): ?string
    {
        if ($r->str('confirm') !== '1') return __('Bitte bestätigen Sie den Sicherheitshinweis.');
        $row = Mfa::row($user);
        if (!$row) return __('Konto nicht gefunden.');
        [, $uid] = Mfa::store($user);
        $limiter = new RateLimiter(app()->db);
        $key = 'reauth:' . $uid . ':' . (Mfa::isNet($user) ? 'n' : 'l');
        if ($limiter->tooMany($key, 5, 900)) return __('Zu viele Versuche. Bitte warten Sie 15 Minuten.');
        if (!password_verify((string) ($r->post['password'] ?? ''), (string) $row['password_hash'])) {
            $limiter->hit($key);
            return __('Das Passwort ist falsch – nichts geändert.');
        }
        $limiter->clear($key);
        app()->session->set('reauth_at', time());
        return null;
    }

    /** Erweiterung, die eine Funktion angemeldet hat (für die Anzeige) */
    private static function extensionOf(string $key): ?string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (Extensions::active() as $n => $x) {
                foreach ($x->featureKeys as $fk) $map[$fk] = $n;
            }
        }
        return $map[$key] ?? null;
    }
}
