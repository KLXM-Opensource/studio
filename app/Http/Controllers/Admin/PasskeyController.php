<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Csrf;
use Core\Database;
use Core\Mfa;
use Core\Network\Network;
use Core\Passkeys;
use Core\RateLimiter;
use Core\Totp;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Passkeys (Core\Passkeys, Richtlinie Core\Mfa): Anmeldung ohne Passwort (Schaltfläche und Autofill auf der Anmeldeseite),
 * Passkeys im Konto hinzufügen, umbenennen, löschen; Auswahl bei verlangter Einrichtung; Wiederherstellungscodes nach dem ersten Passkey.
 * JSON-Endpunkte mit CSRF (Header X-CSRF-Token), Challenge in der Sitzung. Skript: resources/js/passkey.js.
 */
final class PasskeyController extends AdminController
{
    // ================================================================= Anmeldung ohne Passwort

    /**
     * Anmeldung ohne Passwort auf dieser Website angeboten? Richtlinie der Website; Netzwerk-Konten brauchen zusätzlich
     * die Freigabe in der Richtlinie des Netzwerks (geprüft in login()).
     */
    public static function passwordlessOffered(): bool
    {
        return Passkeys::available() && Mfa::policy()['passwordless'];
    }

    public function loginOptions(Request $r): Response
    {
        if (!Csrf::valid($r)) return Response::json(['error' => __('Sitzung abgelaufen – bitte Seite neu laden.')], 419);
        if (!self::passwordlessOffered()) return Response::json(['error' => __('Die Anmeldung mit Passkey ist auf dieser Website ausgeschaltet.')], 403);
        return Response::json(['publicKey' => Passkeys::requestOptions('login', [], true)]);
    }

    public function login(Request $r): Response
    {
        if (!Csrf::valid($r)) return Response::json(['error' => __('Sitzung abgelaufen – bitte Seite neu laden.')], 419);
        $limiter = new RateLimiter(app()->db);
        $key = 'login:' . hash_hmac('sha256', $r->ip(), app()->key());   // dieselbe Begrenzung wie die Passwort-Anmeldung
        if ($limiter->tooMany($key, 8, 900)) return Response::json(['error' => __('Zu viele Anmeldeversuche. Bitte warten Sie 15 Minuten.')], 429);
        $fail = function (string $msg, int $status = 422) use ($limiter, $key): Response {
            $limiter->hit($key);
            return Response::json(['error' => $msg], $status);
        };
        $in = (array) ($r->post['credential'] ?? []);
        $hash = Passkeys::credentialHash($in);
        if ($hash === null) return $fail(__('Die Antwort des Geräts ist unvollständig.'));
        // Konto zum Passkey: zuerst lokale Konten dieser Website, dann Netzwerk-Konten (Passkey für diese Domain)
        $db = app()->db;
        $cred = Passkeys::find($db, $hash);
        $net = false;
        if (!$cred && !Network::isNetworkSite()) {
            try {
                $db = Network::db();
                $cred = Passkeys::find($db, $hash);
                $net = (bool) $cred;
            } catch (\Throwable) {
                $cred = null;
            }
        }
        $row = $cred ? $db->fetch('SELECT * FROM users WHERE id = ?', [(int) $cred['user_id']]) : null;
        if ($net && ($row['role'] ?? '') !== 'network') $row = null;
        if (!$row) {
            Passkeys::verify($db, 'login', $in, null, '');   // Challenge verbrauchen
            return $fail(__('Dieser Passkey ist hier nicht (mehr) eingerichtet. Bitte mit E-Mail-Adresse und Passwort anmelden.'));
        }
        if (!Mfa::allowed($row)['passwordless']) {
            Passkeys::verify($db, 'login', $in, null, '');
            return $fail(__('Die Anmeldung ohne Passwort ist für Ihr Konto ausgeschaltet. Bitte mit Passwort anmelden.'), 403);
        }
        if ($err = Passkeys::verify($db, 'login', $in, $cred, Mfa::handle($row), true)) return $fail($err);
        if ((int) ($row['disabled'] ?? 0)) return $fail(__('Dieses Konto ist gesperrt.'), 403);
        $limiter->clear($key);
        Mfa::remember($db, (int) $row['id'], 'passkey');
        if ($net) {
            if ($err = Network::loginShadow($row, 'login')) return Response::json(['error' => $err], 403);
        } else {
            app()->auth->login((int) $row['id']);
            if (Network::isNetworkUser()) Network::log('login', site()->key, (string) $row['email'], 'passkey');
        }
        $next = $r->str('next');
        $safe = preg_match('~^/(?!/)[\w\-/]*$~', $next) ? $next : '/admin';
        if ($safe === '/admin' && Network::isNetworkSite() && Network::isNetworkUser()) $safe = '/admin/network';
        return Response::json(['ok' => true, 'redirect' => url($safe)]);
    }

    // ================================================================= Konto: Passkeys verwalten

    /** Konto, Datenbank und Richtlinie (Schatten-Konten: Netzwerk-Konto in der Netzwerk-Datenbank) */
    private function owner(Request $r): array
    {
        $user = $this->auth($r);
        $row = Mfa::row($user);
        if (!$row) throw new HttpException(404);
        [$db, $uid] = Mfa::store($user);
        return ['user' => $user, 'row' => $row, 'db' => $db, 'uid' => $uid, 'local' => $db === app()->db, 'pol' => Mfa::allowed($user)];
    }

    private function json(Request $r, string $msg, int $status = 422): Response
    {
        return Response::json(['error' => $msg], $status);
    }

    /** Optionen für navigator.credentials.create() */
    public function options(Request $r): Response
    {
        $o = $this->owner($r);
        if (!$o['pol']['passkey']) return $this->json($r, __('Passkeys sind auf dieser Website nicht freigegeben.'), 403);
        if (!Passkeys::available()) return $this->json($r, __('Passkeys brauchen eine sichere Verbindung (HTTPS) und einen Domainnamen.'));
        if (!Mfa::recentAuth()) {
            return $this->json($r, __('Bitte bestätigen Sie zuerst Ihr Passwort.'), 403);
        }
        $limiter = new RateLimiter(app()->db);
        if ($limiter->tooMany('2fa-setup:' . $o['uid'], 10, 900)) return $this->json($r, __('Zu viele Versuche. Bitte warten Sie 15 Minuten.'), 429);
        $exclude = array_column($o['db']->fetchAll('SELECT credential_id FROM user_passkeys WHERE user_id = ? AND rp_id = ?', [$o['uid'], Passkeys::rpId()]), 'credential_id');
        $name = Passkeys::cleanName($r->str('name'));
        return Response::json(['publicKey' => Passkeys::creationOptions(Mfa::handle($o['user']), (string) $o['row']['email'],
            (string) ($o['row']['name'] ?: $o['row']['email']), $exclude, ['name' => $name])]);
    }

    /** Antwort von navigator.credentials.create() speichern */
    public function store(Request $r): Response
    {
        $o = $this->owner($r);
        if (!$o['pol']['passkey']) return $this->json($r, __('Passkeys sind auf dieser Website nicht freigegeben.'), 403);
        $name = (string) (Passkeys::pending()['name'] ?? '');
        $before = Mfa::factors($o['row'], $o['db']);
        $res = Passkeys::register($o['db'], $o['uid'], (array) ($r->post['credential'] ?? []), $name);
        if (is_string($res)) {
            (new RateLimiter(app()->db))->hit('2fa-setup:' . $o['uid']);
            return $this->json($r, $res);
        }
        $this->log('passkey.add', (string) $o['row']['email'], $res['name'] . ' · ' . Passkeys::rpId());
        // Erster zweiter Faktor: andere Sitzungen beenden, Wiederherstellungscodes einmal anzeigen
        $first = !$before['totp'] && $before['passkeys'] === 0;
        if ($first && $o['local']) $this->keepSession($o['uid']);
        if ($first && $before['recovery'] === 0) {
            [$codes, $hashes] = Totp::recoveryCodes();
            $o['db']->update('users', ['totp_recovery' => $hashes], 'id = :id', ['id' => $o['uid']]);
            app()->session->set('_pk_codes', $codes);
            return Response::json(['ok' => true, 'redirect' => url('/admin/account/2fa/codes')]);
        }
        app()->session->flash('success', __('Passkey „{name}“ hinzugefügt.', ['name' => $res['name']]));
        if (Mfa::satisfied($o['user'])) app()->session->forget('2fa_setup');
        return Response::json(['ok' => true, 'redirect' => url('/admin/account#passkeys')]);
    }

    public function rename(Request $r, string $id): Response
    {
        $o = $this->owner($r);
        $ok = Passkeys::rename($o['db'], $o['uid'], (int) $id, $r->str('name'));
        return $this->back('/admin/account#passkeys', $ok ? 'success' : 'error', $ok ? __('Passkey umbenannt.') : __('Bitte einen Namen angeben.'));
    }

    public function delete(Request $r, string $id): Response
    {
        $o = $this->owner($r);
        if (!Mfa::recentAuth()) return $this->back('/admin/account#passkeys', 'error', __('Bitte bestätigen Sie zuerst Ihr Passwort.'));
        $pk = $o['db']->fetch('SELECT id, name, rp_id FROM user_passkeys WHERE id = ? AND user_id = ?', [(int) $id, $o['uid']]);
        if (!$pk) return $this->back('/admin/account#passkeys', 'error', __('Passkey nicht gefunden.'));
        // Pflicht muss erfüllt bleiben (letzter Passkey bei „nur Passkey“ bzw. ohne App)
        $req = Mfa::requirement($o['row']) ?? (Mfa::isNet($o['row']) ? (Mfa::netPolicy()['passkey_only'] ? 'passkey' : 'any') : null);
        if ($req !== null) {
            $left = Passkeys::count($o['db'], $o['uid']) - 1;
            $pol = $o['pol'];
            $still = $req === 'passkey' ? $pol['passkey'] && $left > 0 : ($pol['passkey'] && $left > 0) || ($pol['totp'] && (int) $o['row']['totp_enabled']);
            if (!$still) return $this->back('/admin/account#passkeys', 'error', __('Das ist Ihr letzter zweiter Faktor – für Ihr Konto vorgeschrieben. Richten Sie zuerst einen anderen ein.'));
        }
        Passkeys::delete($o['db'], $o['uid'], (int) $id);
        if ($o['local']) {
            app()->db->query('UPDATE users SET auth_ver = auth_ver + 1 WHERE id = ?', [$o['uid']]);
            $this->keepSession($o['uid']);
        }
        $this->log('passkey.remove', (string) $o['row']['email'], $pk['name'] . ' · ' . $pk['rp_id']);
        return $this->back('/admin/account#passkeys', 'success', __('Passkey „{name}“ gelöscht.', ['name' => $pk['name']]));
    }

    /** Passwort bestätigen (für Passkeys verwalten nach mehr als 15 Minuten seit der Anmeldung) */
    public function reauth(Request $r): Response
    {
        $o = $this->owner($r);
        $to = self::reauthBack($r->str('back'));
        $limiter = new RateLimiter(app()->db);
        $key = 'reauth:' . $o['uid'] . ':' . ($o['local'] ? 'l' : 'n');
        if ($limiter->tooMany($key, 5, 900)) return $this->back($to, 'error', __('Zu viele Versuche. Bitte warten Sie 15 Minuten.'));
        if (!password_verify((string) ($r->post['password'] ?? ''), (string) $o['row']['password_hash'])) {
            $limiter->hit($key);
            return $this->back($to, 'error', __('Das Passwort ist falsch.'));
        }
        $limiter->clear($key);
        app()->session->set('reauth_at', time());
        return $this->back($to, 'success', $r->str('back') === 'anmeldedaten' ? __('Passwort bestätigt – für 15 Minuten.') : __('Passwort bestätigt – Sie können jetzt Passkeys verwalten.'));
    }

    /** Ziel nach der Bestätigung (nur feste Anker im Konto bzw. die Einrichtung) */
    private static function reauthBack(string $back): string
    {
        return match ($back) {
            'choose' => '/admin/account/2fa/choose',
            'anmeldedaten' => '/admin/account#anmeldedaten',
            default => '/admin/account#passkeys',
        };
    }

    /**
     * Mit Passkey bestätigen statt mit dem Passwort (z. B. Konten ohne Passwort aus einer Einladung): Optionen für
     * navigator.credentials.get() mit den Passkeys dieses Kontos für diese Domain, Benutzerprüfung verlangt.
     */
    public function reauthOptions(Request $r): Response
    {
        $o = $this->owner($r);
        if (!Passkeys::available()) return $this->json($r, __('Passkeys brauchen eine sichere Verbindung (HTTPS) und einen Domainnamen.'));
        $key = 'reauth:' . $o['uid'] . ':' . ($o['local'] ? 'l' : 'n');
        if ((new RateLimiter(app()->db))->tooMany($key, 5, 900)) return $this->json($r, __('Zu viele Versuche. Bitte warten Sie 15 Minuten.'), 429);
        $creds = $o['db']->fetchAll('SELECT credential_id, transports FROM user_passkeys WHERE user_id = ? AND rp_id = ?', [$o['uid'], Passkeys::rpId()]);
        if (!$creds) return $this->json($r, __('Für dieses Konto ist hier kein Passkey eingerichtet.'));
        return Response::json(['publicKey' => Passkeys::requestOptions('reauth', $creds, true)]);
    }

    /** Antwort prüfen → Bestätigung gilt 15 Minuten (reauth_at, Mfa::recentAuth) */
    public function reauthPasskey(Request $r): Response
    {
        $o = $this->owner($r);
        $limiter = new RateLimiter(app()->db);
        $key = 'reauth:' . $o['uid'] . ':' . ($o['local'] ? 'l' : 'n');
        if ($limiter->tooMany($key, 5, 900)) return $this->json($r, __('Zu viele Versuche. Bitte warten Sie 15 Minuten.'), 429);
        $in = (array) ($r->post['credential'] ?? []);
        $hash = Passkeys::credentialHash($in);
        $err = Passkeys::verify($o['db'], 'reauth', $in, $hash ? Passkeys::find($o['db'], $hash, $o['uid']) : null, Mfa::handle($o['user']));
        if ($err !== null) {
            $limiter->hit($key);
            return $this->json($r, $err);
        }
        $limiter->clear($key);
        Mfa::remember($o['db'], $o['uid'], 'passkey');
        app()->session->set('reauth_at', time());
        app()->session->flash('success', __('Mit Passkey bestätigt – für 15 Minuten.'));
        return Response::json(['ok' => true, 'redirect' => url(self::reauthBack($r->str('back')))]);
    }

    // ================================================================= Einrichtung (verlangt), Wiederherstellungscodes

    public function choose(Request $r): Response
    {
        $user = $this->auth($r);
        $row = Mfa::row($user);
        $need = app()->session->get('2fa_setup');
        $req = Mfa::requirement($row ?? $user);
        $next = $r->str('next');
        return $this->view('twofactor/choose', ['pol' => Mfa::allowed($user), 'req' => $req, 'need' => $need,
            'forced' => $need && Mfa::due($need), 'until' => is_array($need) ? (int) ($need['until'] ?? 0) : 0,
            'factors' => $row ? Mfa::factors($row) : ['totp' => false, 'passkeys' => 0, 'here' => 0, 'recovery' => 0],
            'next' => preg_match('~^/admin(/[\w\-/]*)?$~', $next) ? $next : '/admin', 'recent' => Mfa::recentAuth(), 'available' => Passkeys::available(),
            'css' => ['css/passkey.css']]);
    }

    public function codes(Request $r): Response
    {
        $this->auth($r);
        $codes = app()->session->get('_pk_codes');
        app()->session->forget('_pk_codes');
        if (!is_array($codes) || !$codes) return $this->back('/admin/account#passkeys');
        return $this->view('twofactor/codes', ['codes' => $codes, 'fresh' => true, 'passkey' => true]);
    }

    // ================================================================= Hilfen

    private function keepSession(int $uid): void
    {
        app()->session->regenerate();
        app()->session->set('auth_ver', (int) app()->db->fetchValue('SELECT auth_ver FROM users WHERE id = ?', [$uid]));
    }

    /** Protokoll: Netzwerk-Protokoll mit Kürzel der Website (wie „2FA zurücksetzen“) */
    private function log(string $action, string $email, string $detail): void
    {
        try {
            Network::log($action, site()->key, $email, $detail);
        } catch (\Throwable) {
        }
    }
}
