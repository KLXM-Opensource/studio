<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Csrf;
use Core\Mfa;
use Core\Network\Network;
use Core\Passkeys;
use Core\RateLimiter;
use Core\Totp;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Zwei-Faktor-Anmeldung (TOTP): Code nach dem Passwort abfragen, im Konto einrichten/abschalten, Wiederherstellungscodes.
 * Netzwerk-Konten richten 2FA auf der Netzwerk-Website ein; Schatten-Konten anderer Websites haben keine eigene 2FA.
 */
final class TwoFactorController extends AdminController
{
    private const PENDING_TTL = 300;
    private const MAX_TRIES = 5;

    // ================================================================= Anmeldung: zweiter Schritt

    /** Offene Anmeldung (Passwort geprüft) – Konto in der zuständigen Datenbank. @return array{0: array, 1: ?array, 2: \Core\Database}|null */
    private function pending(): ?array
    {
        $p = app()->session->get('_2fa');
        if (!is_array($p) || time() - (int) $p['at'] > self::PENDING_TTL) {
            app()->session->forget('_2fa');
            return null;
        }
        if (isset($p['net'])) {
            return [$p, Network::accountById((int) $p['net']), Network::db()];
        }
        $row = app()->db->fetch('SELECT * FROM users WHERE id = ?', [(int) ($p['uid'] ?? 0)]);
        return [$p, $row && !(int) $row['disabled'] ? $row : null, app()->db];
    }

    public function challengeForm(Request $r, string $error = ''): Response
    {
        $pending = $this->pending();
        if (!$pending || !$pending[1]) {
            app()->session->forget('_2fa');
            return Response::redirect(url('/admin/login'));
        }
        [, $row, $db] = $pending;
        $f = Mfa::factors($row, $db);
        return $this->view('twofactor/challenge', ['user' => null, 'error' => $error, 'methods' => Mfa::loginMethods($row, $db),
            'recovery' => $f['recovery'] > 0, 'css' => ['css/passkey.css']], $error ? 422 : 200);
    }

    public function challenge(Request $r): Response
    {
        $s = app()->session;
        if (!Csrf::valid($r)) return $this->challengeForm($r, __('Sitzung abgelaufen – bitte erneut versuchen.'));
        $pending = $this->pending();
        if (!$pending) {
            $s->flash('error', __('Die Anmeldung hat zu lange gedauert. Bitte erneut anmelden.'));
            return Response::redirect(url('/admin/login'));
        }
        [$p, $row, $db] = $pending;
        if ($stop = $this->tooMany($r, $p)) return $stop;
        $code = $r->str('code');
        $methods = $row ? Mfa::loginMethods($row, $db) : [];
        $net = isset($p['net']);
        $nl = $net ? Network::limiter() : null;
        $nKey = 'net2fa:' . (int) ($p['net'] ?? 0);
        $ok = null;
        if ($row && (!$nl || !$nl->tooMany($nKey, 10, 900))) {
            // App-Code nur, wenn TOTP angeboten wird; Wiederherstellungscodes immer (auch für Konten nur mit Passkeys)
            $ok = in_array('totp', $methods, true) ? Totp::verifyUser($db, $row, $code, $net ? Network::appKey() : app()->key())
                : Totp::verifyRecovery($db, $row, $code);
        }
        if (!$ok) {
            $nl?->hit($nKey);
            (new RateLimiter(app()->db))->hit($this->ipKey($r));
            return $this->challengeForm($r, in_array('totp', $methods, true) ? __('Der Code stimmt nicht. Bitte den aktuellen Code aus der App eingeben.')
                : __('Der Wiederherstellungscode stimmt nicht.'));
        }
        if ($ok === 'code') Mfa::remember($db, (int) $row['id'], 'totp');
        if ($ok === 'recovery') {
            $s->flash('success', __('Mit einem Wiederherstellungscode angemeldet. Dieser Code ist jetzt verbraucht – erzeugen Sie bei Bedarf neue Codes im Konto.'));
        }
        $to = $this->finish($p, $row, $ok === 'recovery' ? 'recovery' : 'totp');
        return is_string($to) ? Response::redirect($to) : $this->challengeForm($r, $to[0]);
    }

    /** Passkey als zweiter Faktor: Optionen für navigator.credentials.get() (nur Passkeys dieses Kontos und dieser Domain) */
    public function passkeyOptions(Request $r): Response
    {
        if (!Csrf::valid($r)) return Response::json(['error' => __('Sitzung abgelaufen – bitte Seite neu laden.')], 419);
        $pending = $this->pending();
        if (!$pending || !$pending[1]) return Response::json(['error' => __('Die Anmeldung hat zu lange gedauert. Bitte erneut anmelden.'), 'redirect' => url('/admin/login')], 440);
        [, $row, $db] = $pending;
        if (!in_array('passkey', Mfa::loginMethods($row, $db), true)) return Response::json(['error' => __('Für dieses Konto ist hier kein Passkey eingerichtet.')], 422);
        $creds = $db->fetchAll('SELECT credential_id, transports FROM user_passkeys WHERE user_id = ? AND rp_id = ?', [(int) $row['id'], Passkeys::rpId()]);
        return Response::json(['publicKey' => Passkeys::requestOptions('2fa', $creds, false)]);
    }

    public function passkey(Request $r): Response
    {
        if (!Csrf::valid($r)) return Response::json(['error' => __('Sitzung abgelaufen – bitte Seite neu laden.')], 419);
        $pending = $this->pending();
        if (!$pending || !$pending[1]) return Response::json(['error' => __('Die Anmeldung hat zu lange gedauert. Bitte erneut anmelden.'), 'redirect' => url('/admin/login')], 440);
        [$p, $row, $db] = $pending;
        if ($stop = $this->tooMany($r, $p)) return Response::json(['error' => __('Zu viele Fehlversuche. Bitte melden Sie sich erneut an.'), 'redirect' => url('/admin/login')], 429);
        $net = isset($p['net']);
        $nl = $net ? Network::limiter() : null;
        $nKey = 'net2fa:' . (int) ($p['net'] ?? 0);
        $in = (array) ($r->post['credential'] ?? []);
        $hash = Passkeys::credentialHash($in);
        $err = $nl && $nl->tooMany($nKey, 10, 900) ? __('Zu viele Fehlversuche. Bitte warten Sie 15 Minuten.')
            : Passkeys::verify($db, '2fa', $in, $hash ? Passkeys::find($db, $hash, (int) $row['id']) : null, Mfa::handle($row));
        if ($err !== null) {
            $nl?->hit($nKey);
            (new RateLimiter(app()->db))->hit($this->ipKey($r));
            return Response::json(['error' => $err], 422);
        }
        Mfa::remember($db, (int) $row['id'], 'passkey');
        $to = $this->finish($p, $row, 'passkey');
        return is_string($to) ? Response::json(['ok' => true, 'redirect' => $to]) : Response::json(['error' => $to[0]], 403);
    }

    private function ipKey(Request $r): string
    {
        return '2fa:' . hash_hmac('sha256', $r->ip(), app()->key());
    }

    /** Versuche je Anmeldung (5) und je IP (10/15 min) – gilt für Codes und Passkeys */
    private function tooMany(Request $r, array $p): ?Response
    {
        $s = app()->session;
        $p['tries'] = (int) $p['tries'] + 1;
        $s->set('_2fa', $p);
        if ($p['tries'] > self::MAX_TRIES || (new RateLimiter(app()->db))->tooMany($this->ipKey($r), 10, 900)) {
            $s->forget('_2fa');
            $s->flash('error', __('Zu viele falsche Codes. Bitte melden Sie sich erneut an.'));
            return Response::redirect(url('/admin/login'));
        }
        return null;
    }

    /** Zweiter Faktor bestätigt → Sitzung starten. @return string|array{0:string} Ziel-Adresse oder [Fehlertext] */
    private function finish(array $p, array $row, string $via): string|array
    {
        $s = app()->session;
        $next = (string) $s->get('_2fa_next', '/admin');
        if (isset($p['net'])) {
            // Netzwerk-Konto auf einer anderen Website: Schatten-Konto anmelden
            if ($err = Network::loginShadow($row, 'login')) {
                $s->forget('_2fa');
                return [$err];
            }
        } else {
            app()->auth->login((int) $row['id']);
            if (Network::isNetworkUser()) {
                Network::log('login', site()->key, (string) $row['email'], $via);
            }
        }
        (new RateLimiter(app()->db))->clear('2fa:' . hash_hmac('sha256', (string) (app()->request?->ip() ?? ''), app()->key()));
        $s->forget('_2fa_next');
        if ($next === '/admin' && Network::isNetworkSite() && Network::isNetworkUser()) $next = '/admin/network';
        return url($next);
    }

    // ================================================================= Konto: einrichten, abschalten, neue Codes

    /** Schatten-Konten verwalten ihre Anmeldung auf der Netzwerk-Website */
    private function own(Request $r): array
    {
        $user = $this->auth($r);
        if (!empty($user['network_uid'])) {
            throw new RedirectException(url('/admin/account'));
        }
        return $user;
    }

    public function setup(Request $r, string $error = ''): Response
    {
        $user = $this->own($r);
        $row = app()->db->fetch('SELECT * FROM users WHERE id = ?', [$user['id']]);
        if ((int) $row['totp_enabled']) {
            return $this->back('/admin/account#zwei-faktor');
        }
        if (!Mfa::allowed($row)['totp']) {
            return $this->back(Mfa::setupPath($row), 'error', __('Die Authenticator-App ist auf dieser Website nicht freigegeben.'));
        }
        if (Mfa::requirement($row) === 'passkey') {
            return $this->back('/admin/account/2fa/choose', 'error', __('Für Ihr Konto ist ein Passkey vorgeschrieben.'));
        }
        $secret = (string) app()->session->get('_totp_new', '');
        if ($secret === '') {
            $secret = Totp::newSecret();
            app()->session->set('_totp_new', $secret);
        }
        $issuer = trim(preg_replace('~[:/]+~', ' ', site_name())) ?: CMS_NAME;
        $uri = Totp::uri($secret, (string) $user['email'], $issuer);
        return $this->view('twofactor/setup', ['secret' => $secret, 'qr' => Totp::qrDataUri($uri), 'error' => $error,
            'forced' => (bool) app()->session->get('2fa_setup')], $error ? 422 : 200);
    }

    public function enable(Request $r): Response
    {
        $user = $this->own($r);
        if (!Mfa::allowed($user)['totp'] || Mfa::requirement($user) === 'passkey') return $this->back('/admin/account#zwei-faktor');
        $secret = (string) app()->session->get('_totp_new', '');
        $limiter = new RateLimiter(app()->db);
        $key = '2fa-setup:' . (int) $user['id'];
        if ($limiter->tooMany($key, 10, 900)) return $this->setup($r, __('Zu viele Versuche. Bitte warten Sie 15 Minuten.'));
        $step = $secret !== '' ? Totp::match($secret, $r->str('code')) : null;
        if ($step === null) {
            $limiter->hit($key);
            return $this->setup($r, __('Der Code stimmt nicht. Prüfen Sie die Uhrzeit des Geräts und geben Sie den aktuellen Code ein.'));
        }
        [$codes, $hashes] = Totp::recoveryCodes();
        app()->db->query('UPDATE users SET totp_secret = ?, totp_recovery = ?, totp_last = ?, totp_enabled = 1, auth_ver = auth_ver + 1 WHERE id = ?',
            [Totp::seal($secret, app()->key()), $hashes, $step, $user['id']]);
        $this->keepSession((int) $user['id']);
        app()->session->forget('_totp_new');
        if (Mfa::satisfied($user)) app()->session->forget('2fa_setup');   // „nur Passkey“: App allein genügt nicht
        if (Network::isNetworkUser()) Network::log('2fa.enable', site()->key);
        return $this->view('twofactor/codes', ['codes' => $codes, 'fresh' => true]);
    }

    public function disable(Request $r): Response
    {
        $user = $this->own($r);
        $row = app()->db->fetch('SELECT * FROM users WHERE id = ?', [$user['id']]);
        // Pflicht bleibt ohne App erfüllt? (z. B. mit Passkey) – sonst nicht abschaltbar
        $req = Mfa::requirement($row);
        if ($req !== null && !Mfa::satisfied(['totp_enabled' => 0] + $row, $req, app()->db)) {
            return $this->back('/admin/account#zwei-faktor', 'error', __('Für Ihre Rolle ist die Zwei-Faktor-Anmeldung vorgeschrieben.'));
        }
        if (!password_verify((string) ($r->post['password'] ?? ''), (string) $row['password_hash'])) {
            return $this->back('/admin/account#zwei-faktor', 'error', __('Das Passwort ist falsch.'));
        }
        // Wiederherstellungscodes bleiben, solange Passkeys eingerichtet sind
        $keep = Passkeys::count(app()->db, (int) $user['id']) > 0;
        app()->db->query('UPDATE users SET totp_secret = NULL, ' . ($keep ? '' : 'totp_recovery = NULL, ') . 'totp_last = NULL, totp_enabled = 0, auth_ver = auth_ver + 1 WHERE id = ?', [$user['id']]);
        $this->keepSession((int) $user['id']);
        return $this->back('/admin/account#zwei-faktor', 'success', __('Zwei-Faktor-Anmeldung ausgeschaltet.'));
    }

    public function recovery(Request $r): Response
    {
        $user = $this->own($r);
        $row = app()->db->fetch('SELECT * FROM users WHERE id = ?', [$user['id']]);
        if (!(int) $row['totp_enabled'] && !Passkeys::count(app()->db, (int) $user['id'])) return $this->back('/admin/account#zwei-faktor');
        if (!password_verify((string) ($r->post['password'] ?? ''), (string) $row['password_hash'])) {
            return $this->back('/admin/account#zwei-faktor', 'error', __('Das Passwort ist falsch.'));
        }
        [$codes, $hashes] = Totp::recoveryCodes();
        app()->db->update('users', ['totp_recovery' => $hashes], 'id = :id', ['id' => $user['id']]);
        return $this->view('twofactor/codes', ['codes' => $codes, 'fresh' => false]);
    }

    /** Eigene Sitzung nach auth_ver-Erhöhung behalten (alle anderen Sitzungen des Kontos enden) */
    private function keepSession(int $uid): void
    {
        app()->session->regenerate();
        app()->session->set('auth_ver', (int) app()->db->fetchValue('SELECT auth_ver FROM users WHERE id = ?', [$uid]));
    }
}
