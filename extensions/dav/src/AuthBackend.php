<?php
declare(strict_types=1);

namespace Klxm\Dav;

/**
 * HTTP Basic: Benutzername = E-Mail des Kontos, Passwort = persönliches App-Passwort (nicht das Login-Passwort).
 * Abo-Token des Core (abo-…) als Passwort: Benutzername beliebig, nur lesen, nur die gebundene Kontakte-Tabelle.
 */
final class AuthBackend extends \Sabre\DAV\Auth\Backend\AbstractBasic
{
    public function __construct()
    {
        $this->realm = 'KLXM Studio CalDAV/CardDAV';
    }

    protected function validateUserPass($username, $password)
    {
        return Dav::login((string) $username, (string) $password);
    }

    /** Principal = principals/{e-mail in Kleinbuchstaben} (unabhängig von der Schreibweise bei der Anmeldung) */
    public function check(\Sabre\HTTP\RequestInterface $request, \Sabre\HTTP\ResponseInterface $response)
    {
        [$ok, $info] = parent::check($request, $response);
        return $ok ? [true, 'principals/' . strtolower((string) Dav::$user['email'])] : [$ok, $info];
    }
}
