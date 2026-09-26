<?php
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\Proxy;

/** /proxy/{quelle}/{pfad} – externe Inhalte über die eigene Domain (siehe Core\Proxy) */
final class ProxyController
{
    public function handle(Request $r, string $source, string $path = ''): Response
    {
        return Proxy::handle($source, $path, $r->server);
    }
}
