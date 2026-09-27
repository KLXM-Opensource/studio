<?php
declare(strict_types=1);

namespace Core;

/**
 * Redaktionsnotizen „[# … #]“ – versteckte Hinweise der Redaktion mitten im Inhalt, z. B. „[# bitte ergänzen: Seminartermine #]“.
 *
 *  - Schreibweise: „[#“ + Leerzeichen … „#]“, auch mehrzeilig, auch mehrere je Text, auch in formatiertem Text (Rich Text)
 *    und in jedem Feld eines Blocks (auch in Listen) sowie in Einträgen von Datentabellen.
 *  - Öffentlich nie sichtbar: Der Kern entfernt sie aus der Ausgabe – Kits und Erweiterungen müssen nichts tun:
 *      Blockdaten vor dem Rendern (Theme::makeBlock), die fertige Seite (SiteController, auch Einträge, JSON-LD, Meta-Angaben,
 *      Daten-Skripte), Vorschau/Freigabe (previewHtml), Suchindex (Search\Documents, Search\Text::plain), KI-Kontexte
 *      (Besucher-Chat über den Suchindex, Assistent, Assist), iCal-Feeds, öffentliche Lesezugriffe der API (veröffentlichte
 *      Fassung, publicInfo). Entwürfe und Lesezugriffe der Redaktion (API/MCP, Verwaltung, Content-Sync) behalten sie.
 *  - Redaktion: Angemeldet mit Recht pages.edit (Bearbeiten-Modus und Entwurfsansicht, nicht ?live=1) erscheinen sie als
 *    Hinweis „Notiz: …“ (decorate(), Stil .cms-note in resources/css/editor.css, nicht als HTML bearbeitbar); der Editor
 *    schreibt sie beim Speichern wieder als „[# … #]“ zurück (resources/js/editor.js, _entry_edit.js).
 *  - Ausnahmen: in <code>/<pre> und in `Backticks` bleibt „[# … #]“ stehen (Anleitungen, die die Schreibweise zeigen).
 *  - Übersicht „Was ist zu tun?“ zählt sie (Dashboard\Metrics::placeholders, Art „note“); Umstellen alter Marker:
 *    php bin/console notes:convert [--site=…] [--dry-run]; Selbsttest: notes:selftest.
 *
 * Für Erweiterungen: EditorNotes::strip($text) (Text oder HTML), stripData($array) (verschachtelte Daten),
 * publicHtml($html) (ganze Seite), has($text), find($text) (Liste der Notiztexte).
 */
final class EditorNotes
{
    /** Eine Notiz: „[#“ + Leerraum, Inhalt (auch mehrzeilig, so kurz wie möglich), „#]“ – nicht direkt in `Backticks` */
    public const RX = '~(?<!`)\[#(?:[\s\x{00A0}]|&nbsp;)(.*?)#\](?!`)~su';
    /** Bereiche, in denen nichts ersetzt wird (Code-Beispiele; Formularfelder/Stile sind nie Inhalt) */
    private const KEEP = '~(<(code|pre|style|textarea)\b[^>]*>.*?</\2\s*>)~is';
    /** Zusätzlich beim Hervorheben ausgenommen: Skripte (Editor-Daten!), Titel, Auswahllisten, SVG */
    private const KEEP_EDIT = '~(<(code|pre|style|textarea|script|title|option|svg|template)\b[^>]*>.*?</\2\s*>|<!--.*?-->)~is';
    /** Schlüssel in Blockdaten, die keine Texte sind */
    private const SKIP_KEYS = ['_fx', '_fit', '_bind'];

    /** Notizen zeigen (Redaktion) statt entfernen – gesetzt von SiteController::render und der Editor-Vorschau */
    public static bool $show = false;

    public static function has(?string $s): bool
    {
        return $s !== null && str_contains($s, '[#') && preg_match(self::RX, $s) === 1;
    }

    /** Texte aller Notizen (ohne HTML, Leerraum zusammengefasst) – außerhalb von Code-Bereichen */
    public static function find(?string $s): array
    {
        if ($s === null || !str_contains($s, '[#')) return [];
        $out = [];
        foreach (self::split((string) $s, self::KEEP) as [$part, $keep]) {
            if ($keep || !preg_match_all(self::RX, $part, $m)) continue;
            foreach ($m[1] as $t) {
                $t = self::label($t);
                if ($t !== '') $out[] = $t;
            }
        }
        return $out;
    }

    /** Notiztext für Anzeige/Übersicht: ohne Tags und Entities, Leerraum zusammengefasst */
    public static function label(string $inner): string
    {
        $t = html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim((string) preg_replace('~[\s\x{00A0}]+~u', ' ', $t));
    }

    /**
     * Notizen entfernen – Klartext oder HTML (Rich Text). Tags innerhalb einer Notiz bleiben stehen (die Struktur bleibt
     * gültig), ein Leerzeichen davor fällt mit weg, Absätze/Listenpunkte, die nur aus einer Notiz bestanden, verschwinden.
     */
    public static function strip(?string $s): string
    {
        $s = (string) $s;
        if (!str_contains($s, '[#')) return $s;
        $out = '';
        $changed = false;
        foreach (self::split($s, self::KEEP) as [$part, $keep]) {
            if ($keep || !str_contains($part, '[#')) { $out .= $part; continue; }
            $new = preg_replace_callback('~[ \t]?' . substr(self::RX, 1, -3) . '~su', function ($m) {
                preg_match_all('~<[^<>]+>~', $m[0], $tags);
                return "\x1A" . implode('', $tags[0]);
            }, $part);
            if ($new === null || $new === $part) { $out .= $part; continue; }
            $changed = true;
            // Leere Hüllen (Absatz, Listenpunkt, Hinweis-Absatz) aus reinen Notizen entfernen
            $new = (string) preg_replace('~<(p|li|h[1-6]|blockquote)\b[^>]*>(?:[\s\x{00A0}]|&nbsp;|<br\s*/?>|\x1A)*\x1A(?:[\s\x{00A0}]|&nbsp;|<br\s*/?>|\x1A)*</\1>~iu', '', $new);
            $out .= str_replace("\x1A", '', $new);
        }
        if (!$changed) return $s;
        // Ursprünglich ohne Rand-Leerraum → Ergebnis auch („[# Notiz #] Titel“ → „Titel“)
        if (trim($s) === $s) $out = trim($out);
        return $out;
    }

    /** Notizen in verschachtelten Daten (Blockdaten, Listen) entfernen – Bild-/Rahmen-Einstellungen bleiben unberührt */
    public static function stripData(mixed $v): mixed
    {
        if (is_string($v)) return str_contains($v, '[#') ? self::strip($v) : $v;
        if (!is_array($v)) return $v;
        foreach ($v as $k => $x) {
            if (is_string($k) && in_array($k, self::SKIP_KEYS, true)) continue;
            if (is_string($x)) {
                if (str_contains($x, '[#')) $v[$k] = self::strip($x);
            } elseif (is_array($x)) {
                $v[$k] = self::stripData($x);
            }
        }
        return $v;
    }

    /** Ganze Seite für Besucher: Notizen überall entfernen (Text, Attribute, Meta-Angaben, JSON-LD, Daten-Skripte) */
    public static function publicHtml(string $html): string
    {
        return str_contains($html, '[#') ? self::strip($html) : $html;
    }

    /**
     * Ansicht der Redaktion: Notizen im sichtbaren Text als Hinweis „Notiz: …“ zeigen (nicht in Attributen, Skripten, Titel).
     * Der Hinweis ist nicht bearbeitbar (contenteditable=false) und trägt den Text in data-cms-note – der Editor macht beim
     * Speichern daraus wieder „[# … #]“.
     */
    public static function decorate(string $html): string
    {
        if (!str_contains($html, '[#')) return $html;
        $out = '';
        foreach (self::split($html, self::KEEP_EDIT) as [$part, $keep]) {
            if ($keep || !str_contains($part, '[#')) { $out .= $part; continue; }
            $out .= (string) preg_replace_callback(self::RX, function ($m) use ($part) {
                [$all, $pos] = $m[0];
                // In einem Tag (Attributwert)? Dann stehen lassen – dort ist kein Hinweis möglich
                $before = substr($part, 0, $pos);
                $lt = strrpos($before, '<');
                if ($lt !== false && ($gt = strrpos($before, '>')) < $lt) return $all;
                preg_match_all('~<[^<>]+>~', $all, $tags);
                return self::badge(self::label($m[1][0])) . implode('', $tags[0]);
            }, $part, -1, $n, PREG_OFFSET_CAPTURE);
        }
        return $out;
    }

    /** Hinweis-Element der Redaktionsansicht */
    public static function badge(string $note): string
    {
        return '<span class="cms-note" contenteditable="false" role="note" data-cms-note="' . e($note) . '" title="' . e(__('Redaktionsnotiz – für Besucher unsichtbar')) . '">'
            . '<span class="cms-note__label">' . e(__('Notiz')) . ':</span> ' . e($note) . '</span>';
    }

    /** Text in [Teil, ausgenommen?] zerlegen */
    private static function split(string $s, string $keepRx): array
    {
        if (!preg_match($keepRx, $s)) return [[$s, false]];
        $out = [];
        foreach (preg_split($keepRx, $s, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$s] as $i => $p) {
            // DELIM_CAPTURE liefert je Treffer ganze Fundstelle + Tag-Name (Gruppe 2) – Tag-Namen überspringen
            if ($i % 3 === 2) continue;
            $out[] = [$p, $i % 3 === 1];
        }
        return $out;
    }

    // ================================================================= Selbsttest (php bin/console notes:selftest)

    /** @return array{ok: int, fail: list<string>} */
    public static function selftest(): array
    {
        $ok = 0;
        $fail = [];
        $eq = function (string $name, mixed $got, mixed $want) use (&$ok, &$fail) {
            if ($got === $want) { $ok++; return; }
            $fail[] = $name . ': erwartet ' . json_encode($want, JSON_UNESCAPED_UNICODE) . ', erhalten ' . json_encode($got, JSON_UNESCAPED_UNICODE);
        };
        // Klartext
        $eq('Klartext', self::strip('Kurse ab März [# bitte ergänzen: Termine #].'), 'Kurse ab März.');
        $eq('Anfang', self::strip('[# Platzhalter #] Titel'), 'Titel');
        $eq('Mehrere', self::strip('A [# eins #] B [# zwei #] C'), 'A B C');
        $eq('Mehrzeilig', self::strip("Text\n[# Zeile 1\nZeile 2 #]\nweiter"), "Text\n\nweiter");
        $eq('Ganz Notiz', self::strip('[# nur Notiz #]'), '');
        $eq('Nicht gierig', self::strip('a [# x #] b #] c'), 'a b #] c');
        // Nicht anfassen
        foreach (['[Platzhalter]', '[*Betonung*]', '[Musik]', 'Liste [1] und [#anker]', 'C#] und [# ohne Ende', 'Code: `[# Beispiel #]`', 'a[#b#]c'] as $s) {
            $eq('unberührt ' . $s, self::strip($s), $s);
        }
        $eq('Code bleibt', self::strip('<p>Schreibweise <code>[# Notiz #]</code>[# weg #]</p>'), '<p>Schreibweise <code>[# Notiz #]</code></p>');
        // Rich Text
        $eq('Rich Text', self::strip('<p>Hallo <b>Welt</b> [# bitte <i>prüfen</i> #]</p>'), '<p>Hallo <b>Welt</b><i></i></p>');
        $eq('Leerer Absatz', self::strip('<p class="t-note">[# bitte prüfen: Impressum #]</p><p>Text</p>'), '<p>Text</p>');
        $eq('Entity-Leerzeichen', self::strip('<p>Text&nbsp;[#&nbsp;x #]</p>'), '<p>Text&nbsp;</p>');
        $eq('Geschütztes Leerzeichen', self::strip("Text [#\u{00A0}x #]"), 'Text');
        // Verschachtelte Blockdaten
        $data = ['title' => 'Seminare [# Platzhalter #]', 'items' => [['text' => '<p>A [# b #]</p>', 'image' => 5], ['q' => 'ok']], '_fit' => ['x' => '[# y #]'], 'n' => 3];
        $eq('Blockdaten', self::stripData($data), ['title' => 'Seminare', 'items' => [['text' => '<p>A</p>', 'image' => 5], ['q' => 'ok']], '_fit' => ['x' => '[# y #]'], 'n' => 3]);
        // Finden, Seite, Hinweis
        $eq('find', self::find("A [# eins #] <code>[# nicht #]</code> [# zwei\n<b>fett</b> #]"), ['eins', 'zwei fett']);
        $eq('Attribut öffentlich', self::publicHtml('<img alt="Logo [# tauschen #]"><p>x</p>'), '<img alt="Logo"><p>x</p>');
        $eq('JSON-LD', self::publicHtml('<script type="application/ld+json">{"name":"Kurs [# Titel prüfen #]"}</script>'), '<script type="application/ld+json">{"name":"Kurs"}</script>');
        $dec = self::decorate('<p data-edit="text">Hallo [# bitte <b>prüfen</b> #]</p><img alt="[# a #]"><script type="application/json">{"t":"[# roh #]"}</script>');
        $eq('Hinweis', str_contains($dec, 'class="cms-note" contenteditable="false" role="note" data-cms-note="bitte prüfen"'), true);
        $eq('Hinweis: Attribut roh', str_contains($dec, 'alt="[# a #]"'), true);
        $eq('Hinweis: Editor-Daten roh', str_contains($dec, '{"t":"[# roh #]"}'), true);
        $eq('Hinweis: escaped', str_contains(self::decorate('[# <img src=x onerror=alert(1)> & "q" #]'), '<img src=x onerror=alert(1)>'), true);   // Tag bleibt als Tag stehen (war schon im Inhalt), Notiztext escaped
        $eq('Hinweis: Text escaped', str_contains(self::badge('<b>"x"</b>'), '&lt;b&gt;&quot;x&quot;&lt;/b&gt;'), true);
        $eq('idempotent', self::decorate($dec), $dec);
        // Umstellung alter Marker (NotesConvert)
        foreach ([
            ['[bitte ergänzen: Datum]', '[# bitte ergänzen: Datum #]'],
            ['Text [bitte prüfen: Impressum übernommen] mehr', 'Text [# bitte prüfen: Impressum übernommen #] mehr'],
            ['[bitte rechtlich prüfen: Hinweis nötig?]', '[# bitte rechtlich prüfen: Hinweis nötig? #]'],
            ['[bitte ergänzen nach Prüfung: weitere Einschränkungen]', '[# bitte ergänzen nach Prüfung: weitere Einschränkungen #]'],
            ['In dringenden Fällen: [bitte ergänzen].', 'In dringenden Fällen: [# bitte ergänzen #].'],
            ['REDAXO für Redaktionen [Platzhalter]', 'REDAXO für Redaktionen [# Platzhalter #]'],
            ['NEU (bitte prüfen)', '[# NEU (bitte prüfen) #]'],
            ['Leistungen · NEU (bitte prüfen)', 'Leistungen · [# NEU (bitte prüfen) #]'],
            ['<p>[Bitte ergänzen: <b>Preis</b>]</p>', '<p>[# Bitte ergänzen: <b>Preis</b> #]</p>'],
            ['Doku: `[bitte ergänzen: …]` bleibt', 'Doku: `[bitte ergänzen: …]` bleibt'],
            ['<code>[bitte prüfen: x]</code>', '<code>[bitte prüfen: x]</code>'],
            ['[# schon umgestellt #]', '[# schon umgestellt #]'],
            ['[# NEU (bitte prüfen) #]', '[# NEU (bitte prüfen) #]'],
            ['[Musik] und [Platzhalter-Text]', '[Musik] und [Platzhalter-Text]'],
        ] as [$in, $want]) {
            $eq('Umstellung ' . $in, NotesConvert::convert($in), $want);
        }
        return ['ok' => $ok, 'fail' => $fail];
    }
}
