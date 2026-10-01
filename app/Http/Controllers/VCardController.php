<?php
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\Data\Entries;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Lang;
use Core\VCard;

/**
 * Öffentliche Visitenkarten (Core\VCard, vCard 3.0):
 *   GET /vcard.vcf                       Organisation der Website (404 ohne Telefon, E-Mail und Adresse)
 *   GET /vcard/{handle}/{slug}.vcf       Person aus einer Tabelle mit Schema-Typ „Person“ (nur veröffentlichte Einträge)
 * ?lang=en wählt die Sprache (Öffnungszeiten-Notiz, übersetzte Einträge). Im Wartungsmodus nur für Angemeldete.
 */
final class VCardController
{
    public function org(Request $r): Response
    {
        $this->guard($r);
        $body = VCard::org() ?? throw new HttpException(404);
        return VCard::response($body, VCard::orgFile());
    }

    public function person(Request $r, string $handle, string $slug): Response
    {
        $this->guard($r);
        $t = VCard::personTable($handle) ?? throw new HttpException(404);
        $e = Entries::bySlug($t, $slug, true, Lang::current(), 'site') ?? throw new HttpException(404);
        return VCard::response(VCard::person($t, $e), VCard::fileName(Entries::title($t, $e)) . '.vcf');
    }

    private function guard(Request $r): void
    {
        $app = app();
        if ($app->settings->get('sys.maintenance') && !$app->auth->check()) throw new HttpException(404);
        $lang = strtolower((string) ($r->query['lang'] ?? ''));
        if ($lang !== '' && Lang::valid($lang)) $app->lang = $lang;
    }
}
