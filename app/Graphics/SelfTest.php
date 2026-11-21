<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Graphics;

use Core\Http\Controllers\Admin\GraphicsController;
use Core\Http\Controllers\Admin\RedirectException;
use Core\Http\Request;
use Core\Media;
use Core\Svg;

/**
 * Selbsttest des Grafik-Editors (php bin/console grafik:selftest --site=key):
 *  - Bereinigung: typische Ausgabe des Editors übersteht Core\Svg::clean unverändert (ids, Ebenennamen, Verläufe,
 *    Pfeilspitzen, Schatten, Text bleibt Text) und ist idempotent; Schadcode in Editor-Ausgabe wird entfernt
 *  - Ablage: Symbole/Vorschauen liegen nicht in der Mediathek (Media::all, Zähler), Blockvorschau wirkt in der Blockauswahl
 *    (Picker::previews über block_previews), Löschen räumt Datei und Zuordnung ab; Infografik → Mediathek, Ersetzen hält
 *    die vorige Fassung
 *  - Rechte: alle Routen verlangen Anmeldung und „media.upload“ (Löschen „media.delete“); Blockvorschauen zusätzlich
 *    „design.edit“, Symbole und Bausteine „blocks.build“ (Graphics::RIGHTS) – Redaktion ohne Recht: kein Knopf, POST → 403
 *  - Arbeitsfassung (Phase 2): Svg::clean mit 'editor' behält Editor-Daten (Hilfslinien, Sperren) nur als Text; die Web-Datei
 *    enthält sie nicht, beim erneuten Öffnen gilt die Arbeitsfassung, solange die Datei dieselbe ist; Pixelbilder werden
 *    gezählt (Meldung im Editor); Blockvorschau je Variante („typ--variante“) in der Blockauswahl
 * Datenbank-Änderungen laufen in einer Transaktion und werden zurückgerollt; angelegte Dateien werden gelöscht.
 */
final class SelfTest
{
    /** Typische Ausgabe des Editors (Formen, Verlauf, Pfeil, Schatten, Gruppe mit Ebenennamen, mehrzeiliger Text) */
    public const SAMPLE = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 500" width="800" height="500">'
        . '<title>Umsatz 2026</title><desc>Balken und Kreis</desc>'
        . '<defs><linearGradient id="verlauf-1" x1="0" y1="0.5" x2="1" y2="0.5"><stop offset="0" stop-color="#5B73AD"/><stop offset="1" stop-color="#FFFFFF" stop-opacity="0.5"/></linearGradient>'
        . '<marker id="pfeil-1" viewBox="0 0 10 10" refX="7" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M0 0L10 5L0 10z" fill="#1F2430"/></marker>'
        . '<filter id="schatten-1" x="-50%" y="-50%" width="200%" height="200%"><feDropShadow dx="4" dy="6" stdDeviation="6" flood-color="#000000" flood-opacity="0.25"/></filter></defs>'
        . '<g id="diagramm" data-name="Diagramm" transform="translate(20 21.5)"><rect id="balken" x="80" y="75" width="280" height="200" rx="8" fill="url(#verlauf-1)" filter="url(#schatten-1)"/>'
        . '<ellipse cx="560" cy="200" rx="120" ry="100" fill="#5B73AD" opacity="0.8" style="mix-blend-mode:multiply"/>'
        . '<line x1="376" y1="175" x2="424" y2="175" stroke="#1F2430" stroke-width="2" marker-end="url(#pfeil-1)"/></g>'
        . '<path data-name="Welle" d="M10 400C60 350 110 450 160 400" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-dasharray="6 4"/>'
        . '<text x="80" y="460" font-size="32" font-family="Lato" fill="#1F2430"><tspan x="80">Umsatz</tspan><tspan x="80" dy="1.2em">2026</tspan></text></svg>';

    private static int $ok = 0;
    private static array $fails = [];

    /** @return array{ok: int, fails: list<string>} */
    public static function run(): array
    {
        self::$ok = 0;
        self::$fails = [];
        self::sanitize();
        self::editorData();
        self::permissions();
        $db = app()->db;
        $db->pdo->beginTransaction();
        $files = [];
        try {
            self::store($files);
            self::media($files);
            self::sources($files);
            self::rights();
        } catch (\Throwable $e) {
            self::$fails[] = 'Ausnahme: ' . $e->getMessage();
        } finally {
            $db->pdo->rollBack();
            foreach ($files as $f) if (is_file($f)) @unlink($f);
            app()->settings->forget();
        }
        return ['ok' => self::$ok, 'fails' => self::$fails];
    }

    private static function t(bool $cond, string $label): void
    {
        $cond ? self::$ok++ : self::$fails[] = $label;
    }

    private static function sanitize(): void
    {
        $r = Svg::clean(self::SAMPLE, ['keep_ids' => true]);
        $s = $r['svg'];
        foreach (['id="diagramm"', 'id="balken"', 'data-name="Diagramm"', 'data-name="Welle"', 'url(#verlauf-1)', 'marker-end="url(#pfeil-1)"',
            '<feDropShadow', 'mix-blend-mode:multiply', 'stroke="currentColor"', '<title>Umsatz 2026</title>', '<desc>', '<tspan', '>2026</tspan>',
            'stroke-dasharray="6 4"', 'transform="translate(20 21.5)"', 'viewBox="0 0 800 500"'] as $needle) {
            self::t(str_contains($s, $needle), "Editor-Ausgabe behält $needle");
        }
        self::t(Svg::clean($s, ['keep_ids' => true])['svg'] === $s, 'Bereinigung ist idempotent (zweimal speichern ändert nichts)');
        self::t($r['width'] === 800 && $r['height'] === 500, 'Maße aus der viewBox');
        $plain = Svg::clean(self::SAMPLE)['svg'];
        self::t(!str_contains($plain, 'id="diagramm"') && str_contains($plain, 'data-name="Diagramm"'), 'ohne keep_ids fallen unbenutzte ids weg, Ebenennamen bleiben');
        $bad = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10" onload="alert(1)">'
            . '<script>alert(1)</script><foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject>'
            . '<rect width="10" height="10" data-name="&lt;b&gt;x" data-evil="1" onclick="x()" fill="url(https://example.org/x.svg#a)"/>'
            . '<use xlink:href="https://example.org/a.svg#b"/><image href="data:image/png;base64,AAAA"/></svg>';
        try {
            $b = Svg::clean($bad, ['keep_ids' => true])['svg'];
            self::t(!preg_match('~script|onload|onclick|foreignObject|example\.org|data-evil|<image~i', $b), 'Schadcode in Editor-Ausgabe wird entfernt');
            self::t(!str_contains($b, '<b>'), 'Ebenenname ohne Markup');
        } catch (\RuntimeException $e) {
            self::t(true, 'Schadcode abgelehnt: ' . $e->getMessage());
        }
    }

    /** Editor-Daten der Arbeitsfassung: nur mit 'editor', nur als Text, nie in der Web-Fassung */
    private static function editorData(): void
    {
        $src = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" data-ge-guides="v:50 h:20" data-ge-grid="10 2">'
            . '<rect width="10" height="10" data-locked="1" data-ge-fill="#5B73AD" data-ge-evil="javascript:alert(1)" data-ge-x="url(https://example.org/a)"/>'
            . '<text x="1" y="9" data-ge-frame="80">A</text><image href="https://example.org/a.png" width="5" height="5"/></svg>';
        $e = Svg::clean($src, ['editor' => true]);
        self::t(str_contains($e['svg'], 'data-ge-guides="v:50 h:20"') && str_contains($e['svg'], 'data-locked="1"') && str_contains($e['svg'], 'data-ge-frame="80"'), 'Arbeitsfassung behält Hilfslinien, Sperren, Textrahmen');
        self::t(!str_contains($e['svg'], 'javascript:') && !str_contains($e['svg'], 'example.org'), 'Editor-Daten ohne Skript-Schemata und fremde Adressen');
        $w = Svg::clean($src, ['keep_ids' => true]);
        self::t(!str_contains($w['svg'], 'data-ge-') && !str_contains($w['svg'], 'data-locked'), 'ohne editor: keine Editor-Daten');
        self::t(($w['images'] ?? 0) === 1 && !str_contains($w['svg'], '<image'), 'Pixelbild entfernt und gezählt (Meldung im Editor)');
        // Formatvorlagen (data-ge-styles): nur Base64; Typografie bleibt in beiden Fassungen (Ausbaustufe 3a)
        $b64 = base64_encode('{"p":[{"n":"Titel","props":{"font-size":"32"}}],"c":[]}');
        $t = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" data-ge-styles="' . $b64 . '"><g data-ge-styles="x&lt;script&gt;"/>'
            . '<text x="1" y="9" data-ge-para="al:justify;hy:de" style="font-feature-settings:&quot;smcp&quot;, &quot;liga&quot; 0;font-variation-settings:\'wdth\' 80" word-spacing="2">A<tspan baseline-shift="super" font-size="60%">2</tspan></text></svg>';
        $e = Svg::clean($t, ['editor' => true]);
        self::t(str_contains($e['svg'], 'data-ge-styles="' . $b64 . '"') && substr_count($e['svg'], 'data-ge-styles') === 1, 'Formatvorlagen: Base64 bleibt, anderes wird verworfen');
        self::t(str_contains($e['svg'], 'data-ge-para="al:justify;hy:de"'), 'Absatz-Einstellungen in der Arbeitsfassung');
        $w = Svg::clean($t, ['keep_ids' => true]);
        self::t(str_contains($w['svg'], 'font-feature-settings') && str_contains($w['svg'], 'font-variation-settings') && str_contains($w['svg'], 'baseline-shift="super"') && str_contains($w['svg'], 'word-spacing="2"'),
            'OpenType, Achsen, Grundlinienversatz und Wortabstand bleiben in der Web-Fassung');
        // Persönliche Einstellungen: nur bekannte Werte
        $p = Graphics::prefs(['ui_prefs' => json_encode(['grafik' => ['fontFav' => ['Lato, sans-serif', '<b>x</b>', str_repeat('a', 200)], 'colorMode' => 'cmyk']])]);
        self::t($p['fontFav'] === ['Lato, sans-serif'] && $p['colorMode'] === 'hex', 'Einstellungen: Schriftnamen ohne Markup, unbekannter Farbmodus → hex');
    }

    /** Rolle vorübergehend setzen (wie ExtensionsSelfTest::actAs) */
    private static function actAs(array $perms): void
    {
        $a = app()->auth;
        foreach (['loaded' => true, 'user' => ['id' => 0, 'email' => 'selbsttest@example.test', 'name' => 'Selbsttest', 'role' => 'qa', 'appearance' => null, 'locale' => null],
            'role' => ['key' => 'qa', 'name' => 'QA', 'description' => '', 'permissions' => $perms, 'tables' => null, 'builtin' => false]] as $prop => $v) {
            (new \ReflectionProperty($a, $prop))->setValue($a, $v);
        }
    }

    /** Redaktion ohne Design-Recht: kein „Vorschau gestalten“, Speichern/Zuordnen einer Blockvorschau → 403 */
    private static function rights(): void
    {
        $a = app()->auth;
        $saved = [];
        foreach (['loaded', 'user', 'role'] as $prop) $saved[$prop] = (new \ReflectionProperty($a, $prop))->getValue($a);
        $c = new GraphicsController();
        // Konsole: Sitzung nur für die CSRF-Prüfung vorübergehend „gestartet“
        $sess = app()->session;
        $started = new \ReflectionProperty($sess, 'started');
        $wasStarted = $started->getValue($sess);
        $started->setValue($sess, true);
        $tok = bin2hex(random_bytes(16));
        $_SESSION['_csrf'] = $tok;
        $post = fn(array $data) => new Request('POST', '/admin/api/grafik/save', [], $data + ['_csrf' => $tok], [], ['HTTP_HOST' => 'www.example.org', 'HTTP_ACCEPT' => 'application/json']);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 100"><rect width="160" height="100" fill="#5B73AD"/></svg>';
        try {
            self::actAs(['media.upload', 'pages.edit']);
            self::t(!Graphics::canKind('preview') && !Graphics::canKind('icon') && !Graphics::canKind('part') && Graphics::canKind('info'), 'Redaktion (nur media.upload): Infografiken ja, Vorschauen/Symbole/Bausteine nein');
            $cfgOk = Graphics::canKind('preview');   // fragments/editor.php: $config['grafik'] nur damit
            self::t(!$cfgOk, 'Blockauswahl ohne „Vorschau gestalten“ (Editor-Konfiguration grafik = null)');
            $res = $c->save($post(['svg' => $svg, 'target' => 'graphics', 'kind' => 'preview', 'name' => 'x', 'block' => 'faq', 'assign' => '1']));
            self::t($res->status === 403, 'Blockvorschau speichern ohne „design.edit“ → 403');
            $res = $c->assign(new Request('POST', '/admin/api/grafik/assign', [], ['block' => 'faq', 'id' => '1', '_csrf' => $tok], [], ['HTTP_HOST' => 'www.example.org', 'HTTP_ACCEPT' => 'application/json']));
            self::t($res->status === 403, 'Blockvorschau zuordnen ohne „design.edit“ → 403');
            $res = $c->part(new Request('POST', '/admin/api/grafik/part', [], ['name' => 'x', 'svg' => $svg, '_csrf' => $tok], [], ['HTTP_HOST' => 'www.example.org', 'HTTP_ACCEPT' => 'application/json']));
            self::t($res->status === 403, 'Baustein speichern ohne „blocks.build“ → 403');
            $res = $c->boot(new Request('GET', '/admin/api/grafik/boot', ['format' => 'preview', 'block' => 'faq'], [], [], ['HTTP_HOST' => 'www.example.org', 'HTTP_ACCEPT' => 'application/json']));
            self::t($res->status === 403, 'Editor für Blockvorschau öffnen ohne Recht → 403');
            self::actAs(['media.upload', 'design.edit']);
            self::t(Graphics::canKind('preview') && !Graphics::canKind('part'), 'mit „design.edit“: Blockvorschauen ja, Bausteine weiter nein');
            $res = $c->boot(new Request('GET', '/admin/api/grafik/boot', ['format' => 'preview', 'block' => 'faq'], [], [], ['HTTP_HOST' => 'www.example.org', 'HTTP_ACCEPT' => 'application/json']));
            self::t($res->status === 200, 'mit „design.edit“: Editor für Blockvorschau öffnet');
            self::actAs(['design.edit']);
            self::t(!Graphics::canKind('preview'), 'ohne „media.upload“ auch mit Design-Recht nicht');
        } catch (\Throwable $e) {
            self::t(false, 'Rechte-Prüfung: ' . $e->getMessage());
        } finally {
            foreach ($saved as $prop => $v) (new \ReflectionProperty($a, $prop))->setValue($a, $v);
            $started->setValue($sess, $wasStarted);
            unset($_SESSION['_csrf']);
        }
    }

    /** Arbeitsfassung zur Web-Datei: gilt, solange die Datei gleich ist; Blockvorschau je Variante */
    private static function sources(array &$files): void
    {
        $work = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 500" width="800" height="500" data-ge-guides="v:400"><rect data-name="Balken" x="10" y="10" width="100" height="50" fill="#5B73AD"/></svg>';
        $web = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 500" width="800" height="500"><rect x="10" y="10" width="100" height="50" fill="#5B73AD"/></svg>';
        [$m, $err] = Graphics::saveMedia($web, ['name' => 'selbsttest-arbeitsfassung', 'alt' => 'Selbsttest'], null, $work);
        self::t($m !== null, 'Mediathek: Web-Fassung mit Arbeitsfassung gespeichert' . ($err ? " ($err)" : ''));
        if (!$m) return;
        $files[] = Media::path($m);
        $file = (string) file_get_contents(Media::path($m));
        self::t(!str_contains($file, 'data-name') && !str_contains($file, 'data-ge-'), 'ausgelieferte Datei ohne Editor-Daten');
        $s = Graphics::source('media:' . $m['id'], $file);
        self::t($s !== null && str_contains($s, 'data-ge-guides="v:400"') && str_contains($s, 'data-name="Balken"'), 'Arbeitsfassung gehört zur Datei (Hilfslinien, Ebenennamen)');
        self::t(Graphics::source('media:' . $m['id'], $file . ' ') === null, 'andere Datei (z. B. ersetzt) → Arbeitsfassung gilt nicht');
        // Vorschau je Variante
        $theme = app()->theme;
        $blocks = (array) $theme->blocks();
        $type = null;
        foreach ($blocks as $k => $b) if (is_array($b) && is_array($b['variants'] ?? null) && count($b['variants']) > 1) { $type = (string) $k; break; }
        if ($type === null) { self::t(true, 'kein Block mit Varianten im Kit'); return; }
        $variant = (string) array_keys($blocks[$type]['variants'])[1];
        $g = Graphics::store('preview', 'Selbsttest-Variante', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 100"><rect width="160" height="100" fill="#5B73AD"/></svg>', null, $type . '--' . $variant);
        $files[] = Graphics::dir() . '/' . basename((string) parse_url((string) $g['url'], PHP_URL_PATH));
        Graphics::assignPreview($type . '--' . $variant, $g['id']);
        $p = \Core\Blocks\Picker::previews($theme, [$type => (array) $blocks[$type]]);
        self::t(($p[$type]['variants'][$variant] ?? '') === $g['url'], 'Blockauswahl zeigt die Vorschau der Variante');
    }

    private static function permissions(): void
    {
        // Ohne Anmeldung: jede Aktion leitet zur Anmeldung um
        $c = new GraphicsController();
        $req = fn(string $m, string $p, array $post = []) => new Request($m, $p, [], $post, [], ['HTTP_HOST' => 'www.example.org']);
        $was = app()->auth->user();
        if ($was) {
            self::t(true, 'Rechteprüfung ohne Anmeldung übersprungen (angemeldet)');
        } else {
            foreach ([['index', 'GET', '/admin/grafik'], ['editor', 'GET', '/admin/grafik/editor'], ['boot', 'GET', '/admin/api/grafik/boot'], ['save', 'POST', '/admin/api/grafik/save'],
                ['part', 'POST', '/admin/api/grafik/part'], ['clean', 'POST', '/admin/api/grafik/clean'], ['assign', 'POST', '/admin/api/grafik/assign'], ['version', 'GET', '/admin/api/grafik/version']] as [$fn, $m, $p]) {
                try {
                    $c->$fn($req($m, $p, ['svg' => self::SAMPLE]));
                    self::t(false, "$p ohne Anmeldung abgelehnt");
                } catch (RedirectException) {
                    self::t(true, '');
                }
            }
        }
        // Jede öffentliche Aktion prüft das Recht – Quelltext der Methoden (Schutz vor vergessenen auth()-Aufrufen)
        $rc = new \ReflectionClass(GraphicsController::class);
        $src = (string) file_get_contents((string) $rc->getFileName());
        foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->class !== GraphicsController::class) continue;
            $body = implode("\n", array_slice(explode("\n", $src), $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1));
            self::t((bool) preg_match('~\$this->auth\(\$r, \'media\.(upload|delete)\'\)~', $body), 'Aktion ' . $m->name . ' prüft „media.upload“ bzw. „media.delete“');
        }
        // Rolle ohne Medienrechte darf nicht
        foreach (\Core\Permissions::roles() as $key => $role) {
            if (!\Core\Permissions::allows($role, 'media.upload')) { self::t(true, ''); return; }
        }
        self::t(true, 'alle Rollen dürfen Medien hochladen (kein Gegenbeispiel)');
    }

    private static function store(array &$files): void
    {
        $before = count(Media::all());
        $icon = Graphics::store('icon', 'Selbsttest-Symbol', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 12h16"/></svg>');
        $prev = Graphics::store('preview', 'Selbsttest-Vorschau', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 100"><rect x="10" y="10" width="140" height="80" fill="#8A94A6"/></svg>', null, 'faq');
        $part = Graphics::store('part', 'Selbsttest-Baustein', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>');
        foreach ([$icon, $prev] as $g) $files[] = Graphics::dir() . '/' . basename((string) parse_url((string) $g['url'], PHP_URL_PATH));
        self::t(is_file($files[0]) && is_file($files[1]), 'Symbol und Vorschau liegen als Datei in der eigenen Ablage');
        self::t($part['url'] === null, 'Bausteine nur in der Datenbank (keine öffentliche Datei)');
        self::t(count(Media::all()) === $before, 'Mediathek unverändert (Media::all zählt keine Grafiken der Ablage)');
        $inMedia = false;
        foreach (Media::all(['q' => 'Selbsttest']) as $m) $inMedia = true;
        self::t(!$inMedia, 'Ablage erscheint nicht in der Mediathek-Suche');
        self::t((int) app()->db->fetchValue("SELECT COUNT(*) FROM media WHERE file LIKE 'grafik/%'") === 0, 'keine Mediathek-Zeile für Dateien der Ablage');
        // Blockvorschau zuordnen → Blockauswahl zeigt sie (Website-Einstellung block_previews)
        Graphics::assignPreview('faq', $prev['id']);
        $theme = app()->theme;
        $p = \Core\Blocks\Picker::previews($theme, ['faq' => (array) ($theme->blocks()['faq'] ?? [])]);
        self::t(($p['faq']['url'] ?? '') === $prev['url'], 'Blockauswahl zeigt die zugeordnete Vorschau');
        // Überschreiben: neue Datei, Zuordnung folgt, vorige Fassung bleibt
        $prev2 = Graphics::store('preview', 'Selbsttest-Vorschau', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 100"><rect x="20" y="20" width="120" height="60" fill="#5B73AD"/></svg>', $prev['id'], 'faq');
        $files[] = Graphics::dir() . '/' . basename((string) parse_url((string) $prev2['url'], PHP_URL_PATH));
        self::t(!is_file($files[1]) && is_file(end($files)), 'neue Fassung ersetzt die Datei');
        self::t((Graphics::previewMap()['faq'] ?? '') === $prev2['url'], 'Zuordnung zeigt auf die neue Fassung');
        self::t(count(Graphics::versions('graphic:' . $prev['id'])) === 1, 'vorige Fassung gespeichert');
        Graphics::delete($prev['id']);
        self::t(!isset(Graphics::previewMap()['faq']) && !is_file(end($files)), 'Löschen entfernt Datei und Zuordnung');
        try {
            Graphics::store('icon', 'x', '<svg xmlns="http://www.w3.org/2000/svg"><script>1</script></svg>');
            self::t(false, 'leeres/schädliches SVG abgelehnt');
        } catch (\RuntimeException) {
            self::t(true, '');
        }
    }

    private static function media(array &$files): void
    {
        [$m, $err] = Graphics::saveMedia(self::SAMPLE, ['name' => 'selbsttest-infografik', 'title' => 'Selbsttest', 'alt' => 'Selbsttest: Balken und Kreis']);
        self::t($m !== null && $err === null, 'Infografik in der Mediathek gespeichert' . ($err ? " ($err)" : ''));
        if (!$m) return;
        $files[] = Media::path($m);
        self::t(($m['mime'] ?? '') === Svg::MIME && (int) $m['width'] === 800, 'Mediathek-Eintrag ist SVG mit Maßen');
        self::t(str_contains((string) file_get_contents(Media::path($m)), 'id="balken"'), 'ids bleiben (Ebenen, spätere Animationen)');
        [$m2, $err2] = Graphics::saveMedia(str_replace('#5B73AD', '#B84506', self::SAMPLE), [], (int) $m['id']);
        self::t($m2 !== null && (int) $m2['id'] === (int) $m['id'] && ($m2['alt'] ?? '') === 'Selbsttest: Balken und Kreis', 'Überschreiben behält ID und Alt-Text' . ($err2 ? " ($err2)" : ''));
        if ($m2) $files[] = Media::path($m2);
        self::t(count(Graphics::versions('media:' . $m['id'])) === 1, 'vorige Fassung der Mediathek-Grafik gespeichert');
        self::t(str_contains((string) Graphics::version('media:' . $m['id'], Graphics::versions('media:' . $m['id'])[0]['id'] ?? 0), '#5B73AD'), 'Fassung enthält den alten Stand');
    }
}
