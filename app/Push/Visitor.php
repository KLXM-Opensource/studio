<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Push;

use Core\Data\Tables;
use Core\Lang;

/**
 * „Benachrichtigungen abonnieren“ für Besucher (Block push_subscribe, im Kit: push_subscribe('aktuelles')).
 * Ausgabe ohne Inline-Skript/-Stil (CSP): Texte als data-*, Verhalten in resources/js/push.js, Aussehen in resources/css/push.css
 * (Variablen --push-*, Farben aus dem Kit über --kit-*). Für alle gleich (Seiten-Cache) – den Zustand (abonniert, nicht unterstützt,
 * gesperrt, iPhone ohne installierte App) ermittelt das Skript im Browser. Nach dem Klick: Hinweis → Abfrage des Browsers → Abo.
 */
final class Visitor
{
    private static bool $assets = false;

    /** Signiertes Merkmal der Website (keine Sitzung, keine Cookies): weist Anfragen an /api/push als von dieser Website aus */
    public static function token(): string
    {
        return substr(hash_hmac('sha256', 'push-visitor|' . site()->key, app()->key()), 0, 32);
    }

    /**
     * HTML für eine Tabelle (Kurzname oder Definition). $o: title, intro, button, unsubscribe, layout ('box'|'inline'), class, edit (Block)
     * → '' wenn die Funktion aus ist oder die Tabelle kein Abo anbietet (in der Verwaltungs-Vorschau mit Hinweis).
     */
    public static function render(array|string|null $table, array $o = []): string
    {
        $t = is_array($table) ? $table : ($table !== null && $table !== '' ? Tables::findContent($table) : (app()->entry['table'] ?? null));
        $preview = app()->editing || (isset(app()->auth) && app()->auth->check());
        if (!$t || !Topics::enabled($t) || !Push::enabled()) {
            return $preview ? '<p class="cms-push-note">' . e(lt('Benachrichtigungen: Für diese Tabelle ist „Besucher können neue Einträge abonnieren“ nicht eingeschaltet (Tabelle → Felder & Einstellungen) bzw. die Funktion „Push-Benachrichtigungen“ ist aus.')) . '</p>' : '';
        }
        $name = (string) ($t['name'] ?? $t['handle']);
        $title = trim((string) ($o['title'] ?? '')) ?: lt('Neue Einträge abonnieren');
        $intro = trim((string) ($o['intro'] ?? '')) ?: lt('Wir benachrichtigen Sie auf diesem Gerät, sobald hier etwas Neues erscheint („{name}“). Ohne Anmeldung und ohne Tracking – gespeichert wird nur die Zustelladresse Ihres Browsers. Abbestellen jederzeit hier.', ['name' => $name]);
        $texts = [
            'subscribe' => trim((string) ($o['button'] ?? '')) ?: lt('Benachrichtigungen abonnieren'),
            'unsubscribe' => trim((string) ($o['unsubscribe'] ?? '')) ?: lt('Abbestellen'),
            'on' => lt('Abonniert – Sie erhalten eine Mitteilung, sobald etwas Neues erscheint.'),
            'off' => lt('Abbestellt. Sie erhalten keine Mitteilungen mehr.'),
            'asking' => lt('Bitte bestätigen Sie die Abfrage Ihres Browsers.'),
            'denied' => lt('Mitteilungen sind für diese Website im Browser blockiert. Sie können sie in den Website-Einstellungen Ihres Browsers erlauben.'),
            'unsupported' => lt('Dieser Browser unterstützt keine Push-Mitteilungen.'),
            'insecure' => lt('Push-Mitteilungen gehen nur über eine sichere Verbindung (https).'),
            'ios' => lt('Auf iPhone und iPad: Website zuerst über „Teilen“ → „Zum Home-Bildschirm“ hinzufügen und von dort öffnen – dann lassen sich Mitteilungen abonnieren.'),
            'error' => lt('Das hat nicht geklappt. Bitte versuchen Sie es später noch einmal.'),
            'busy' => lt('Einen Moment …'),
        ];
        $btn = app()->theme->def['button_class'] ?? 'btn btn--primary';
        $btn2 = app()->theme->def['button_secondary_class'] ?? 'btn btn--secondary';
        $layout = ($o['layout'] ?? 'box') === 'inline' ? 'inline' : 'box';
        $edit = $o['edit'] ?? null;   // Block (Core\Block) für Inline-Bearbeitung der Texte
        $ed = fn(string $f) => $edit instanceof \Core\Block ? $edit->edit($f) : '';
        $attrs = [
            'data-cms-push' => '', 'data-topic' => Topics::topic($t), 'data-key' => Keys::publicB64(), 'data-sw' => url(Push::SW_PATH),
            'data-scope' => url(Push::SW_SCOPE), 'data-api' => url(Push::API), 'data-token' => self::token(), 'data-lang' => Lang::norm(app()->lang),
            'data-texts' => json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'data-btn' => $btn, 'data-btn-off' => $btn2,
        ];
        $h = self::assets() . '<div class="' . e(trim('cms-push cms-push--' . $layout . ' ' . (string) ($o['class'] ?? ''))) . '"';
        foreach ($attrs as $k => $v) $h .= ' ' . $k . ($v === '' ? '' : '="' . e((string) $v) . '"');
        $h .= '>'
            . ($layout === 'box' ? '<span class="cms-push__badge" aria-hidden="true">' . icon('bell-ringing', ['class' => 'cms-push__ico']) . '</span>' : '')
            . '<div class="cms-push__text">'
            . ($layout === 'box' ? '<p class="cms-push__title"' . $ed('title') . '>' . e($title) . '</p>' : '')
            . '<p class="cms-push__intro"' . $ed('intro') . '>' . e($intro) . '</p></div>'
            . '<div class="cms-push__actions"><button type="button" class="' . e($btn . ' cms-push__btn') . '" data-push-btn hidden>' . e($texts['subscribe']) . '</button></div>'
            . '<p class="cms-push__status" data-push-status role="status" aria-live="polite"></p>'
            . '<noscript><p class="cms-push__status">' . e(lt('Zum Abonnieren wird JavaScript benötigt.')) . '</p></noscript>'
            . '</div>';
        return $h;
    }

    /** Stylesheet und Skript einmal je Seite (Kit kann css/push.css mitbringen) */
    private static function assets(): string
    {
        if (self::$assets) return '';
        self::$assets = true;
        $theme = app()->theme;
        $css = $theme->hasAsset('css/push.css') ? $theme->asset('css/push.css') : asset('css/push.css');
        return '<link rel="stylesheet" href="' . e($css) . '"><script src="' . e(asset('js/push.js')) . '" defer></script>';
    }
}
