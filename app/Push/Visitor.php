<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Push;

use Core\Data\Tables;
use Core\Lang;

/**
 * Push-Abos für Besucher auf der Website:
 *   render()   Block „Benachrichtigungen abonnieren“ (push_subscribe) bzw. push_subscribe() im Kit – eine Tabelle (Knopf an/aus)
 *              oder mehrere Kanäle (Auswahl mit Kästchen; dieselbe Auswahl zeigt später das Abo und ändert bzw. bestellt ab)
 *   overlay()  Banner (oben/unten, schließbar, gemerkt im Browser) und schwebende Glocke mit Kanalauswahl – Einstellung
 *              sys.push_site (Verwaltung → Mitteilungen → Website), eingefügt vor </body> (SiteController)
 * Ausgabe ohne Inline-Skript/-Stil (CSP): Texte als data-*, Verhalten in resources/js/push.js, Aussehen in resources/css/push.css
 * (Variablen --push-*, Farben aus dem Kit über --kit-*). Für alle gleich (Seiten-Cache) – den Zustand (abonniert, nicht
 * unterstützt, gesperrt, iPhone ohne installierte App) ermittelt das Skript im Browser. Abfrage des Browsers nur nach einem Klick.
 */
final class Visitor
{
    private static bool $assets = false;
    private static int $n = 0;

    public const SITE_KEY = 'sys.push_site';

    /** Signiertes Merkmal der Website (keine Sitzung, keine Cookies): weist Anfragen an /api/push als von dieser Website aus */
    public static function token(): string
    {
        return substr(hash_hmac('sha256', 'push-visitor|' . site()->key, app()->key()), 0, 32);
    }

    /** Texte für das Skript (Website-Sprache) */
    private static function texts(array $o): array
    {
        return [
            'subscribe' => trim((string) ($o['button'] ?? '')) ?: lt('Benachrichtigungen abonnieren'),
            'unsubscribe' => trim((string) ($o['unsubscribe'] ?? '')) ?: lt('Abbestellen'),
            'save' => lt('Auswahl speichern'),
            'unsubAll' => lt('Alle abbestellen'),
            'pick' => lt('Bitte wählen Sie mindestens einen Kanal.'),
            'on' => lt('Abonniert – Sie erhalten eine Mitteilung, sobald etwas Neues erscheint.'),
            'saved' => lt('Gespeichert – Sie erhalten Mitteilungen zu den gewählten Themen.'),
            'off' => lt('Abbestellt. Sie erhalten keine Mitteilungen mehr.'),
            'asking' => lt('Bitte bestätigen Sie die Abfrage Ihres Browsers.'),
            'denied' => lt('Mitteilungen sind für diese Website im Browser blockiert. Sie können sie in den Website-Einstellungen Ihres Browsers erlauben.'),
            'unsupported' => lt('Dieser Browser unterstützt keine Push-Mitteilungen.'),
            'insecure' => lt('Push-Mitteilungen gehen nur über eine sichere Verbindung (https).'),
            'ios' => lt('Auf iPhone und iPad: Website zuerst über „Teilen“ → „Zum Home-Bildschirm“ hinzufügen und von dort öffnen – dann lassen sich Mitteilungen abonnieren.'),
            'error' => lt('Das hat nicht geklappt. Bitte versuchen Sie es später noch einmal.'),
            'busy' => lt('Einen Moment …'),
        ];
    }

    /** Gemeinsame data-* der Formulare */
    private static function attrs(array $texts): array
    {
        return ['data-key' => Keys::publicB64(), 'data-sw' => url(Push::SW_PATH), 'data-scope' => url(Push::SW_SCOPE), 'data-api' => url(Push::API),
            'data-token' => self::token(), 'data-lang' => Lang::norm(app()->lang), 'data-texts' => json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'data-btn' => app()->theme->def['button_class'] ?? 'btn btn--primary', 'data-btn-off' => app()->theme->def['button_secondary_class'] ?? 'btn btn--secondary'];
    }

    /**
     * Formular „Benachrichtigungen abonnieren“. $table: Kurzname/Definition einer Tabelle (null = Tabelle der Detailseite);
     * $o['channels']: Liste von Kanälen (Themen) → Auswahl mit Kästchen (hat Vorrang vor $table). Weitere $o: title, intro, button,
     * unsubscribe, layout ('box'|'inline'|'panel'), class, edit (Block), id. → '' wenn nichts abonnierbar ist (Vorschau: Hinweis).
     */
    public static function render(array|string|null $table, array $o = []): string
    {
        $preview = app()->editing || (isset(app()->auth) && app()->auth->check());
        // Nur in der Vorschau der Redaktion ein Hinweis, Besucher sehen nichts
        $note = fn(string $why = 'table') => $preview ? '<p class="cms-push-note">' . e($why === 'off'
            ? lt('Benachrichtigungen abonnieren: Die Funktion „Push-Benachrichtigungen“ ist auf dieser Website aus (Verwaltung → Funktionen & Erweiterungen). Besucher sehen hier nichts.')
            : lt('Benachrichtigungen abonnieren: Für diese Tabelle ist „Besucher können neue Einträge abonnieren“ nicht eingeschaltet (Tabelle → Felder & Einstellungen) bzw. kein Kanal gewählt. Besucher sehen hier nichts.')) . '</p>' : '';
        if (!Push::enabled()) return $note('off');
        $public = Channels::all(true);
        $channels = array_values(array_filter(array_map('strval', (array) ($o['channels'] ?? [])), fn($t) => isset($public[$t])));
        if (!$channels) {
            $t = is_array($table) ? $table : ($table !== null && $table !== '' ? Tables::findContent($table) : (app()->entry['table'] ?? null));
            if (!$t || !Topics::enabled($t)) return $note();
            $channels = [Topics::topic($t)];
        }
        $multi = count($channels) > 1;
        $first = $public[$channels[0]];
        $title = trim((string) ($o['title'] ?? '')) ?: lt('Neue Einträge abonnieren');
        $intro = trim((string) ($o['intro'] ?? '')) ?: ($multi
            ? lt('Wählen Sie, worüber wir Sie auf diesem Gerät benachrichtigen. Ohne Anmeldung und ohne Tracking – gespeichert wird nur die Zustelladresse Ihres Browsers. Ändern oder abbestellen jederzeit hier.')
            : lt('Wir benachrichtigen Sie auf diesem Gerät, sobald hier etwas Neues erscheint („{name}“). Ohne Anmeldung und ohne Tracking – gespeichert wird nur die Zustelladresse Ihres Browsers. Abbestellen jederzeit hier.', ['name' => $first['name']]));
        $texts = self::texts($o);
        $layout = in_array($o['layout'] ?? 'box', ['inline', 'panel'], true) ? (string) $o['layout'] : 'box';
        $edit = $o['edit'] ?? null;
        $ed = fn(string $f) => $edit instanceof \Core\Block ? $edit->edit($f) : '';
        $uid = (string) ($o['id'] ?? 'cms-push-' . (++self::$n));
        $attrs = ['data-cms-push' => '', 'data-topic' => $channels[0], 'data-topics' => json_encode($channels)] + self::attrs($texts);
        $h = self::assets() . '<div class="' . e(trim('cms-push cms-push--' . $layout . ($multi ? ' cms-push--multi' : '') . ' ' . (string) ($o['class'] ?? ''))) . '"';
        foreach ($attrs as $k => $v) $h .= ' ' . $k . ($v === '' ? '' : '="' . e((string) $v) . '"');
        $h .= '>'
            . ($layout === 'box' ? '<span class="cms-push__badge" aria-hidden="true">' . icon('bell-ringing', ['class' => 'cms-push__ico']) . '</span>' : '')
            . '<div class="cms-push__text">'
            . ($layout === 'box' ? '<p class="cms-push__title" id="' . e($uid) . '-t"' . $ed('title') . '>' . e($title) . '</p>' : '')
            . '<p class="cms-push__intro"' . $ed('intro') . '>' . e($intro) . '</p></div>';
        if ($multi) {
            $h .= '<fieldset class="cms-push__channels" data-push-channels><legend class="cms-push__sr">' . e(lt('Themen')) . '</legend>';
            foreach ($channels as $i => $topic) {
                $c = $public[$topic];
                $id = $uid . '-' . $i;
                $h .= '<label class="cms-push__ch" for="' . e($id) . '"><input type="checkbox" id="' . e($id) . '" value="' . e($topic) . '" data-push-ch>'
                    . '<span><span class="cms-push__chname">' . e($c['name']) . '</span>'
                    . ($c['description'] !== '' ? '<span class="cms-push__chdesc">' . e($c['description']) . '</span>' : '') . '</span></label>';
            }
            $h .= '</fieldset>';
        }
        $h .= '<div class="cms-push__actions"><button type="button" class="' . e($attrs['data-btn'] . ' cms-push__btn') . '" data-push-btn hidden>' . e($texts['subscribe']) . '</button>'
            . ($multi ? '<button type="button" class="cms-push__link" data-push-off hidden>' . e($texts['unsubAll']) . '</button>' : '') . '</div>'
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

    // ================================================================= Banner und Glocke

    /** Einstellung der Website (sys.push_site) mit Vorgaben */
    public static function siteConfig(): array
    {
        $c = (array) app()->settings->get(self::SITE_KEY, []);
        return [
            'banner' => !empty($c['banner']), 'bell' => !empty($c['bell']),
            'title' => (string) ($c['title'] ?? ''), 'text' => (string) ($c['text'] ?? ''), 'button' => (string) ($c['button'] ?? ''),
            'position' => ($c['position'] ?? 'bottom') === 'top' ? 'top' : 'bottom',
            'bell_position' => ($c['bell_position'] ?? 'right') === 'left' ? 'left' : 'right',
            'channels' => array_values(array_map('strval', (array) ($c['channels'] ?? []))),
            'delay' => max(0, min(120, (int) ($c['delay'] ?? 8))),
            'second' => !array_key_exists('second', $c) || !empty($c['second']),
        ];
    }

    /** Speichern aus dem Formular (Verwaltung → Mitteilungen → Website) */
    public static function saveSiteConfig(array $in): void
    {
        $topics = array_keys(Channels::all(true));
        $clean = fn(string $k, int $max) => trim(strip_tags(mb_substr((string) ($in[$k] ?? ''), 0, $max)));
        app()->settings->set(self::SITE_KEY, [
            'banner' => !empty($in['banner']) && $in['banner'] !== '0', 'bell' => !empty($in['bell']) && $in['bell'] !== '0',
            'title' => $clean('title', 80), 'text' => $clean('text', 240), 'button' => $clean('button', 40),
            'position' => ($in['position'] ?? '') === 'top' ? 'top' : 'bottom', 'bell_position' => ($in['bell_position'] ?? '') === 'left' ? 'left' : 'right',
            'channels' => array_values(array_intersect(array_map('strval', (array) ($in['channels'] ?? [])), $topics)),
            'delay' => max(0, min(120, (int) ($in['delay'] ?? 8))), 'second' => !empty($in['second']) && $in['second'] !== '0',
        ]);
    }

    /** Kanäle für Banner und Glocke: gewählte öffentliche, sonst alle öffentlichen */
    public static function siteChannels(?array $cfg = null): array
    {
        $cfg ??= self::siteConfig();
        $public = array_keys(Channels::all(true));
        $sel = array_values(array_intersect($cfg['channels'], $public));
        return $sel ?: $public;
    }

    /** Banner + Glocke (unsichtbar ausgeliefert; das Skript entscheidet, ob und wann sie erscheinen) → '' wenn aus */
    public static function overlay(): string
    {
        try {
            if (!Push::enabled()) return '';
            $cfg = self::siteConfig();
            if (!$cfg['banner'] && !$cfg['bell']) return '';
            $channels = self::siteChannels($cfg);
            if (!$channels) return '';
        } catch (\Throwable $e) {
            error_log('[push] Banner/Glocke: ' . $e->getMessage());
            return '';
        }
        $title = $cfg['title'] !== '' ? $cfg['title'] : lt('Nichts mehr verpassen?');
        $text = $cfg['text'] !== '' ? $cfg['text'] : lt('Auf Wunsch benachrichtigen wir Sie auf diesem Gerät über Neuigkeiten – ohne Anmeldung, jederzeit abbestellbar.');
        $btn = app()->theme->def['button_class'] ?? 'btn btn--primary';
        $panel = self::render(null, ['channels' => $channels, 'layout' => 'panel', 'id' => 'cms-pushp', 'button' => count($channels) > 1 ? '' : lt('Benachrichtigungen abonnieren')]);
        if ($panel === '' || str_contains($panel, 'cms-push-note')) return '';
        $h = '<div class="cms-pushsite" data-cms-push-site hidden data-banner="' . ($cfg['banner'] ? '1' : '0') . '" data-bell="' . ($cfg['bell'] ? '1' : '0') . '"'
            . ' data-position="' . e($cfg['position']) . '" data-bell-pos="' . e($cfg['bell_position']) . '" data-delay="' . (int) $cfg['delay'] . '" data-second="' . ($cfg['second'] ? '1' : '0') . '">';
        if ($cfg['banner']) {
            $h .= '<section class="cms-pushbar cms-pushbar--' . e($cfg['position']) . '" data-push-banner hidden aria-labelledby="cms-pushbar-t">'
                . '<span class="cms-pushbar__ico" aria-hidden="true">' . icon('bell-ringing', ['class' => 'cms-push__ico']) . '</span>'
                . '<div class="cms-pushbar__text"><p class="cms-pushbar__title" id="cms-pushbar-t">' . e($title) . '</p><p class="cms-pushbar__intro">' . e($text) . '</p></div>'
                . '<div class="cms-pushbar__actions"><button type="button" class="' . e($btn) . '" data-push-open aria-haspopup="dialog" aria-controls="cms-pushpanel">'
                . e($cfg['button'] !== '' ? $cfg['button'] : lt('Auswählen …')) . '</button>'
                . '<button type="button" class="cms-push__link" data-push-dismiss>' . e(lt('Nein, danke')) . '</button></div></section>';
        }
        if ($cfg['bell']) {
            $h .= '<button type="button" class="cms-pushbell cms-pushbell--' . e($cfg['bell_position']) . '" data-push-bell hidden aria-haspopup="dialog" aria-expanded="false"'
                . ' aria-controls="cms-pushpanel" title="' . e(lt('Benachrichtigungen')) . '"><span class="cms-push__sr">' . e(lt('Benachrichtigungen')) . '</span>'
                . icon('bell-ringing', ['class' => 'cms-pushbell__ico']) . '</button>';
        }
        $h .= '<div class="cms-pushpanel cms-pushpanel--' . e($cfg['bell_position']) . '" id="cms-pushpanel" data-push-panel role="dialog" aria-modal="false" aria-labelledby="cms-pushpanel-t" hidden>'
            . '<div class="cms-pushpanel__head"><p class="cms-pushpanel__title" id="cms-pushpanel-t">' . e(lt('Benachrichtigungen')) . '</p>'
            . '<button type="button" class="cms-pushpanel__close" data-push-close aria-label="' . e(lt('Schließen')) . '">×</button></div>'
            . $panel . '</div></div>';
        return $h;
    }

    /** In eine fertige Seite einfügen (vor </body>) */
    public static function inject(string $html): string
    {
        $o = self::overlay();
        if ($o === '' || ($pos = strripos($html, '</body>')) === false) return $html;
        return substr($html, 0, $pos) . $o . substr($html, $pos);
    }
}
