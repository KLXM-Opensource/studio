<?php
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\AppIcons;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Favicon, Web-App-Manifest, Service Worker und Offline-Seite.
 * Der Service Worker wird nur in der installierten App registriert (Start über start_url ?pwa=1) –
 * normale Besucher im Browser bekommen nichts auf ihrem Gerät gespeichert.
 */
final class PwaController
{
    /** /favicon.ico und /apple-touch-icon.png (werden von Browsern direkt im Wurzelverzeichnis gesucht) */
    public function icon(Request $r): Response
    {
        // Landing-Domain mit eigenem Favicon (Core\Landings)
        if (($l = \Core\Landings::current()) && ($fav = AppIcons::landingIcon($l))) {
            return Response::redirect($fav['url'], 302)->header('Cache-Control', 'public, max-age=3600');
        }
        AppIcons::ensure();
        $file = str_ends_with($r->path, '.ico') ? 'favicon.ico' : 'icon-180.png';
        $path = AppIcons::dir() . '/' . $file;
        if (!is_file($path)) {
            throw new HttpException(404);
        }
        return (new Response((string) file_get_contents($path)))
            ->header('Content-Type', $file === 'favicon.ico' ? 'image/x-icon' : 'image/png')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    public function manifest(Request $r): Response
    {
        if (!app()->settings->get('sys.pwa', true)) {
            throw new HttpException(404);
        }
        AppIcons::ensure();
        return (new Response(json_encode(AppIcons::manifest(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)))
            ->header('Content-Type', 'application/manifest+json; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /** Service Worker – bei ausgeschaltetem Offline-Modus ein „Abschalter“, der sich selbst entfernt */
    public function serviceWorker(Request $r): Response
    {
        $on = app()->settings->get('sys.pwa', true) && app()->settings->get('sys.pwa_offline', true);
        if ($on) {
            $theme = app()->theme;
            $precache = array_values(array_filter([
                url('/offline'),
                $theme->asset('css/site.css'),
                $theme->asset('css/blocks.css'),
                $theme->asset('js/site.js'),
                ...array_map([$theme, 'fontUrl'], (array) ($theme->def['fonts']['preload'] ?? [])),
                ...\Core\Design::preloadUrls(),   // Kit-Schriften mit 'preload' (Schriften-Manager)
                AppIcons::url('icon-192.png'),
            ]));
            $version = substr(md5(CMS_VERSION . json_encode($precache) . AppIcons::version()), 0, 10);
            $js = strtr((string) file_get_contents(ROOT . '/resources/sw/sw.js'), [
                '__VERSION__' => $version,
                '__PRECACHE__' => json_encode($precache, JSON_UNESCAPED_SLASHES),
                '__OFFLINE__' => json_encode(url('/offline'), JSON_UNESCAPED_SLASHES),
                '__BASE__' => json_encode(base_path(), JSON_UNESCAPED_SLASHES),
            ]);
            // Push-Benachrichtigungen (Core\Push): dieselben Handler wie /push-sw.js – falls ein Abo an dieser Registrierung hängt
            if (\Core\Push\Push::enabled()) {
                $js .= "\n" . strtr((string) file_get_contents(ROOT . '/resources/sw/push.js'), [
                    '__PUSH_KEY__' => json_encode(\Core\Push\Keys::publicB64()),
                    '__PUSH_RENEW__' => json_encode(url(\Core\Push\Push::API . '/renew'), JSON_UNESCAPED_SLASHES),
                ]);
            }
        } else {
            $js = "self.addEventListener('install',()=>self.skipWaiting());self.addEventListener('activate',e=>e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(n=>/^(klxm-studio|mycms)-/.test(n)).map(n=>caches.delete(n)))).then(()=>self.registration.unregister())));";
        }
        return (new Response($js))
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'no-cache')
            ->header('Service-Worker-Allowed', base_path() . '/');
    }

    /** Offline-Seite des Themes (vom Service Worker vorab gespeichert) */
    public function offline(Request $r): Response
    {
        $html = app()->theme->render('offline', []);
        return (new Response($html))
            ->header('Cache-Control', 'public, max-age=0, must-revalidate')
            ->header('X-Robots-Tag', 'noindex')
            ->header('Content-Security-Policy', "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    }
}
