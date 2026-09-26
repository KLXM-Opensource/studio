<?php
declare(strict_types=1);

namespace MyCms\Dav;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Einstieg für /dav/… : sabre/dav schreibt die Antwort selbst (Status, Kopfzeilen, XML).
 * Der Core erhält danach Response::alreadySent() und sendet nichts mehr.
 *
 *   /dav/principals/{e-mail}/              Principal (current-user-principal)
 *   /dav/calendars/{e-mail}/{tabelle}/     Kalender (Tabellen mit „Als Kalender nutzen“)
 *   /dav/addressbooks/{e-mail}/{tabelle}/  Adressbücher (Tabellen mit „Als Adressbuch nutzen“)
 */
final class Server
{
    public static function handle(Request $r): Response
    {
        if (!Dav::enabled()) throw new HttpException(404);
        $principals = new Principals();
        $tree = [
            new \Sabre\CalDAV\Principal\Collection($principals, 'principals'),
            new \Sabre\CalDAV\CalendarRoot($principals, new CalendarBackend()),
            new \Sabre\CardDAV\AddressBookRoot($principals, new CardBackend()),
        ];
        $server = new \Sabre\DAV\Server($tree);
        $server->setBaseUri(self::base());
        $server->addPlugin(new \Sabre\DAV\Auth\Plugin(new AuthBackend()));
        $acl = new \Sabre\DAVACL\Plugin();
        $acl->hideNodesFromListings = true;
        $acl->allowUnauthenticatedAccess = false;
        $server->addPlugin($acl);
        $server->addPlugin(new \Sabre\CalDAV\Plugin());
        $server->addPlugin(new \Sabre\CardDAV\Plugin());
        if (app()->config->get('debug')) $server->addPlugin(new \Sabre\DAV\Browser\Plugin());
        // Fehler von sabre/dav nicht als PHP-Warnungen ausgeben (Clients erwarten sauberes XML)
        $server->debugExceptions = (bool) app()->config->get('debug');
        $server->start();
        return Response::alreadySent();
    }

    /** Basis-Adresse, z. B. /dav/ (bzw. /index.php/dav/ ohne URL-Rewrite) */
    public static function base(): string
    {
        return rtrim(url('/dav'), '/') . '/';
    }

    /** /.well-known/caldav und /.well-known/carddav → /dav/ (RFC 6764) */
    public static function wellKnown(Request $r): Response
    {
        if (!Dav::enabled()) throw new HttpException(404);
        return Response::redirect(self::base(), 301);
    }
}
