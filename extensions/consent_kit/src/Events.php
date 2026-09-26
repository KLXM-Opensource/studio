<?php
// SPDX-License-Identifier: MIT
// Ported from FriendsOfREDAXO/consent_kit lib/Events.php (MIT, © KLXM Crossmedia GmbH)
declare(strict_types=1);

namespace MyCms\Consent;

/**
 * Conversions ohne Code: „Wenn Klick auf … / Aufruf der Seite … / Formular gesendet … dann melde Anfrage“.
 * Die Vorlage kennt den Aufruf je Anbieter (Feld „events“); hier entsteht daraus JavaScript, das nur nach Einwilligung
 * läuft – ausgeliefert als Datei von der eigenen Domain (Teil „a“ des Dienstes), nie inline.
 */
final class Events
{
    public const TRIGGERS = ['click' => 'Klick auf', 'page' => 'Aufruf der Seite', 'form' => 'Formular gesendet'];
    public const TYPES = ['lead' => 'Anfrage (Lead)', 'registration' => 'Registrierung', 'appointment' => 'Terminbuchung', 'page_view' => 'Wichtige Seite aufgerufen', 'custom' => 'Eigener Code'];

    /** @return list<array{trigger: string, target: string, event: string, label: string, code: string}> */
    public static function normalize(array $raw): array
    {
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) continue;
            $target = trim((string) ($row['target'] ?? ''));
            $event = isset(self::TYPES[$row['event'] ?? '']) ? (string) $row['event'] : 'custom';
            $code = trim(str_replace("\r\n", "\n", (string) ($row['code'] ?? '')));
            if ($target === '' || ($event === 'custom' && $code === '')) continue;
            $out[] = [
                'trigger' => isset(self::TRIGGERS[$row['trigger'] ?? '']) ? (string) $row['trigger'] : 'click',
                'target' => mb_substr($target, 0, 300),
                'event' => $event,
                'label' => mb_substr(trim((string) ($row['label'] ?? '')), 0, 120),
                'code' => $event === 'custom' ? $code : '',
            ];
        }
        return $out;
    }

    /** JavaScript für alle Ereignisse eines Dienstes ({{param}} bleiben stehen und werden wie die Code-Felder aufgelöst) */
    public static function build(array $rows, array $templates): string
    {
        $parts = [];
        foreach (self::normalize($rows) as $row) {
            $call = $row['event'] === 'custom' ? $row['code'] : (string) ($templates[$row['event']] ?? '');
            if (trim($call) === '') continue;
            $call = str_replace('{{label}}', addcslashes($row['label'], "\\'\"\n\r<>"), $call);
            $fn = 'function () { ' . $call . "\n}";
            $target = (string) json_encode($row['target'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
            $parts[] = match ($row['trigger']) {
                'page' => 'ck.page(' . $target . ', ' . $fn . ');',
                'form' => 'ck.form(' . $target . ', ' . $fn . ');',
                default => 'ck.click(' . $target . ', ' . $fn . ');',
            };
        }
        if (!$parts) return '';
        // Kleine Laufzeit: Klick per Delegation (auch für später eingefügte Inhalte), Formular beim Absenden, Seite beim Laden
        return '(function () { var ck = {'
            . ' run: function (fn) { try { fn(); } catch (e) { console.error("[consent-kit] event", e); } },'
            . ' click: function (sel, fn) { document.addEventListener("click", function (e) { if (e.target.closest && e.target.closest(sel)) ck.run(fn); }); },'
            . ' form: function (sel, fn) { document.addEventListener("submit", function (e) { if (e.target.matches && e.target.matches(sel)) ck.run(fn); }); },'
            . ' page: function (path, fn) { var here = location.pathname + location.search; if (here === path || here.indexOf(path) === 0 || (path.indexOf("/") !== 0 && here.indexOf(path) !== -1)) ck.run(fn); }'
            . " };\n" . implode("\n", $parts) . "\n})();";
    }
}
