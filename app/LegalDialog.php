<?php
declare(strict_types=1);

namespace Core;

use Core\Data\DataForms;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Rechtstexte im Dialog: Der Link „Datenschutzhinweise“ an Formularen öffnet die Seite über dem Formular statt in einem
 * neuen Tab – Eingaben bleiben erhalten. GET /_legal/privacy?lang=xx liefert Titel + bereinigtes HTML der Datenschutzseite
 * (Überschriften, Absätze, Listen, Links; ohne Knöpfe, Bilder, Formulare), unabhängig vom Seitenaufbau des Kits.
 * Auf der Website: Link mit LegalDialog::attrs() – resources/js/_legal.js öffnet <dialog class="ldlg">; ohne
 * JavaScript oder bei Fehlern bleibt es ein normaler Link.
 */
final class LegalDialog
{
    /** Attribute für den Link zur Datenschutzseite (Quelle des Dialogs, Stylesheet) */
    public static function attrs(string $kind = 'privacy'): string
    {
        return ' data-legal-src="' . e(url('/_legal/' . $kind) . '?lang=' . rawurlencode(Lang::current())) . '"'
            . ' data-legal-css="' . e(asset('css/legal-dialog.css')) . '"';
    }

    public static function handle(Request $r, string $kind): Response
    {
        $lang = (string) ($r->query['lang'] ?? '');
        if (!isset(Lang::all()[$lang])) $lang = Lang::default();
        $page = $kind === 'privacy' ? DataForms::privacyPage($lang) : null;
        if (!$page || $page['status'] !== 'published') {
            return Response::json(['ok' => false], 404);
        }
        return Response::json([
            'ok' => true,
            'title' => (string) $page['title'],
            'html' => self::fragment($page),
            'url' => Pages::url($page),
            'close' => lt('Schließen'),
            'open' => lt('Als Seite öffnen'),
        ])->header('Cache-Control', 'public, max-age=300')->header('X-Robots-Tag', 'noindex');
    }

    /** Textinhalt der veröffentlichten Blöcke: Einleitung, Überschriften, Fließtext (HTML bereinigt), Einträge (z. B. FAQ) */
    public static function fragment(array $page): string
    {
        $h = '';
        $text = function (string $v): string {
            $v = trim($v);
            if ($v === '') return '';
            return str_contains($v, '<') ? Sanitizer::block($v) : '<p>' . nl2br(e($v), false) . '</p>';
        };
        $title = fn(string $v) => preg_replace('~\*([^*]+)\*~u', '$1', trim($v)) ?? trim($v);
        foreach (Pages::blocks($page) as $b) {
            $d = (array) ($b['data'] ?? []);
            if ($b['type'] === 'hero') {   // Seitentitel steht im Dialogkopf; nur die Einleitung
                $h .= $text((string) ($d['text'] ?? $d['intro'] ?? ''));
                continue;
            }
            if (trim((string) ($d['title'] ?? '')) !== '') $h .= '<h2>' . e($title((string) $d['title'])) . '</h2>';
            foreach (['intro', 'lead', 'text', 'body', 'content', 'html'] as $k) {
                if (is_string($d[$k] ?? null)) $h .= $text($d[$k]);
            }
            foreach ((array) ($d['items'] ?? []) as $it) {
                if (!is_array($it)) continue;
                $head = '';
                foreach (['title', 'q', 'question', 'name', 'label'] as $k) if (trim((string) ($it[$k] ?? '')) !== '') { $head = (string) $it[$k]; break; }
                if ($head !== '') $h .= '<h3>' . e($title($head)) . '</h3>';
                foreach (['a', 'answer', 'text', 'body', 'content'] as $k) if (is_string($it[$k] ?? null)) $h .= $text($it[$k]);
            }
        }
        return $h;
    }
}
