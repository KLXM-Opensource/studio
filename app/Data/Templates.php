<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Http\Controllers\Admin\DataController;
use Core\PageCache;
use Core\Pages;
use Core\Redirects\Redirects;
use Core\Sanitizer;

/**
 * Tabellen aus Vorlagen über die Kommandozeile (php bin/console data:template …) und Übernahme einer alten Stellenseite
 * in die Stellen-Tabelle (jobs:from-page). Beides wiederholbar: Vorhandenes bleibt, fehlende Teile werden ergänzt.
 * Die Vorlagen selbst stehen in DataController::presets() (wie „Daten → Neue Tabelle aus Vorlage“).
 */
final class Templates
{
    /** Kurznamen der Vorlagen ohne Angabe von --handle */
    private const HANDLES = ['jobs' => 'stellen', 'applications' => 'bewerbungen', 'events' => 'termine', 'blog' => 'aktuelles', 'inbox' => 'anfragen'];

    /**
     * Tabelle aus einer Vorlage anlegen bzw. ergänzen. $o: handle, name, slug (Adresse der Detailseiten, '' = keine),
     * form (Stellen: Kurzname des Bewerbungs-Eingangs, 'new' = „bewerbungen“ anlegen/nutzen, 'none' = keiner), detail (bool),
     * list (bool). $log erhält eine Zeile je Schritt. Rückgabe: ['ok' => bool, 'table' => ?array]
     */
    public static function install(string $preset, array $o, callable $log, bool $dry = false): array
    {
        $presets = DataController::presets();
        if (!isset($presets[$preset])) {
            $log('Unbekannte Vorlage „' . $preset . '“. Vorhanden: ' . implode(', ', array_keys($presets)));
            return ['ok' => false, 'table' => null];
        }
        $p = $presets[$preset];
        $handle = Tables::normName((string) ($o['handle'] ?? '') ?: (self::HANDLES[$preset] ?? $p['name']));
        $pfx = $dry ? '[dry-run] ' : '';
        $t = Tables::find($handle);
        if ($t) {
            if (($p['kind'] ?? 'content') === 'inbox' ? !Tables::isInbox($t) : Tables::isInbox($t)) {
                $log("! „{$handle}“ gibt es schon als andere Art von Tabelle – bitte --handle=… wählen.");
                return ['ok' => false, 'table' => $t];
            }
            $log("= Tabelle „{$t['name']}“ ({$handle}) gibt es schon – Felder und Einträge bleiben unverändert.");
        } else {
            $def = DataController::presetDef($preset);
            $def['handle'] = $handle;
            if (($o['name'] ?? '') !== '') $def['name'] = (string) $o['name'];
            if (array_key_exists('slug', $o) && $o['slug'] !== null) $def['settings']['route'] = (string) $o['slug'];
            if (Jobs::TYPE === ($def['settings']['schema_type'] ?? '')) {
                $def['settings']['jobs']['form'] = '';   // Eingang wird unten angelegt bzw. verknüpft
            }
            [$clean, $errors] = Tables::validate($def);
            if ($errors) {
                $log('! Vorlage ungültig: ' . implode(' ', $errors));
                return ['ok' => false, 'table' => null];
            }
            $log($pfx . "+ Tabelle „{$clean['name']}“ ({$handle}) aus Vorlage „{$p['name']}“" . ($clean['settings']['route'] !== '' ? ", Adresse /{$clean['settings']['route']}/…" : ''));
            if (!$dry) {
                Tables::create($clean);
                $t = Tables::find($handle);
            }
        }
        // Stellen: Bewerbungs-Eingang anlegen bzw. nutzen und verknüpfen
        $isJobs = $t ? Jobs::is($t) : (($p['schema_type'] ?? '') === Jobs::TYPE);
        if ($isJobs) {
            $want = (string) ($o['form'] ?? '');
            $cur = $t ? (string) Jobs::config($t)['form'] : '';
            if ($want === 'none') {
                if ($cur !== '') { $log($pfx . '- Bewerbungsformular gelöst'); if (!$dry) Jobs::linkForm($t, ''); }
            } elseif ($want !== '' || $cur === '' || $cur === '_new') {
                $fh = $want === '' || $want === 'new' ? 'bewerbungen' : Tables::normName($want);
                if ($dry && $cur === $fh) {
                    $log("= Bewerbungsformular: {$cur}");
                } elseif ($dry) {
                    $x = Tables::find($fh);
                    $log($pfx . ($x ? "~ Eingang „{$x['name']}“ ({$fh}) als Bewerbungsformular" . (Tables::field($x, Jobs::FIELD) ? '' : ' + Feld „Stelle“') : "+ Eingang „Bewerbungen“ ({$fh})"));
                } elseif ($form = Jobs::ensureForm($fh, $log)) {
                    if ($cur !== $form['handle']) { Jobs::linkForm($t, $form['handle']); $log("✓ Bewerbungsformular: „{$form['name']}“ ({$form['handle']})"); }
                    else $log("= Bewerbungsformular: „{$form['name']}“ ({$form['handle']})");
                }
            } else {
                $log("= Bewerbungsformular: {$cur}");
            }
        }
        if ($t) $t = Tables::find($handle);
        // Übersichtsseite (vor der Vorlage – die bekommt dann den Zurück-Link)
        if (!empty($o['list'])) {
            $route = $t ? (string) $t['settings']['route'] : (string) ($o['slug'] ?? ($p['route'] ?? ''));
            if ($route === '') $log('! Keine Übersichtsseite: die Tabelle hat keine Adresse (--slug=…).');
            elseif (($lp = Pages::byPath($route))) $log("= Übersichtsseite /{$route} gibt es schon („{$lp['title']}“)" . ($t && str_contains((string) ($lp['content_draft'] ?? $lp['content_published']), '"table":"' . $t['handle'] . '"')
                ? ' – mit Datenliste.' : ' – bitte dort den Block „Datenliste“ einsetzen, falls noch nicht geschehen.'));
            elseif (str_contains($route, '/')) $log("! Übersichtsseite /{$route}: mehrstufige Adresse – bitte die Seite im Seitenbaum anlegen.");
            else {
                $log($pfx . "+ Übersichtsseite /{$route} (Datenliste, nicht im Menü)");
                if (!$dry && $t) Jobs::is($t) ? Jobs::makeListPage($t) : self::makeListPage($t);
            }
        }
        if (!empty($o['detail'])) {
            $route = $t ? (string) $t['settings']['route'] : '';
            $has = $t && !empty($t['settings']['detail_page_id']) && Pages::find((int) $t['settings']['detail_page_id']);
            if ($t && $route === '') $log('! Keine Detailseite: die Tabelle hat keine Adresse (--slug=…).');
            elseif ($has) $log('= Detailseiten-Vorlage gibt es schon (Seite ' . $t['settings']['detail_page_id'] . ').');
            else {
                $log($pfx . '+ Detailseiten-Vorlage' . ($isJobs ? ' (Kopf, Eckdaten, Beschreibung, Bewerbung #bewerben)' : ''));
                if (!$dry && $t) DataController::makeTemplate(Tables::find($handle));
            }
        }
        if (!$dry) PageCache::clear();
        return ['ok' => true, 'table' => $t ? Tables::find($handle) : null];
    }

    /** Einfache Übersichtsseite für andere Vorlagen: Datenliste mit Titel, Bild, Beschreibung */
    public static function makeListPage(array $t): ?int
    {
        $route = (string) $t['settings']['route'];
        if ($route === '' || str_contains($route, '/') || Pages::byPath($route)) return null;
        $blocks = Pages::sanitizeBlocks([['id' => bin2hex(random_bytes(5)), 'type' => 'data_list', 'data' => ['title' => $t['name'], 'table' => $t['handle'],
            'fields' => [], 'layout' => 'cards', 'columns' => '3', 'limit' => 0, 'link_detail' => true]]]);
        return Pages::create(['slug' => $route, 'title' => $t['name'], 'status' => 'published', 'menu' => 0], $blocks);
    }

    // ================================================================= Alte Stellenseite übernehmen (jobs:from-page)

    /**
     * Eine Seite mit einer Stellenanzeige (Fließtext-Blöcke, optional Formular-Block) in einen Eintrag der Stellen-Tabelle umwandeln.
     * $o: table (Kurzname, Standard: die einzige Stellen-Tabelle), title (sonst: erste Überschrift ohne „gesucht“ + „(m/w/d)“),
     * slug, employment (Kurznamen mit Komma, sonst aus dem Text erkannt), posted (JJJJ-MM-TT, sonst erste Version der Seite),
     * redirect (bool, Standard true: Seite offline + 301 alte Adresse → Stelle), links (bool, Standard true: Links page:ID → Stelle).
     * Wiederholbar: Gibt es den Eintrag (gleicher Slug) schon, bleibt er unverändert; Weiterleitung und Links werden nur ergänzt.
     */
    public static function fromPage(string $ref, array $o, callable $log, bool $dry = false): bool
    {
        $pfx = $dry ? '[dry-run] ' : '';
        $page = ctype_digit($ref) ? Pages::find((int) $ref) : (Pages::byPath(trim($ref, '/')) ?? Pages::bySlug(trim($ref, '/')));
        if (!$page) { $log("! Seite „{$ref}“ nicht gefunden."); return false; }
        $jobsTables = Jobs::tables();
        $t = ($o['table'] ?? '') !== '' ? Tables::findContent((string) $o['table']) : (count($jobsTables) === 1 ? $jobsTables[0] : null);
        if (!$t || !Jobs::is($t)) {
            $log('! Keine Stellen-Tabelle' . (count($jobsTables) > 1 ? ' eindeutig – bitte --table=… angeben' : ' – zuerst: php bin/console data:template jobs --with-detail-page') . '.');
            return false;
        }
        $pageId = (int) $page['id'];
        $path = '/' . trim((string) ($page['path'] ?? $page['slug']), '/');
        // 1. Inhalt der Seite (veröffentlichte Fassung, sonst Entwurf)
        $blocks = Pages::blocks($page, $page['content_published'] === null);
        $heading = '';
        $html = '';
        $formHandle = '';
        foreach ($blocks as $b) {
            $d = (array) ($b['data'] ?? []);
            if (($b['type'] ?? '') === 'data_form' && ($x = Tables::find((string) ($d['table'] ?? ''))) && Tables::isInbox($x)) { $formHandle = $x['handle']; continue; }
            $strong = trim(strip_tags((string) ($d['title_strong'] ?? $d['title'] ?? '')));
            $light = trim(strip_tags((string) ($d['title_light'] ?? '')));
            $text = (string) ($d['text'] ?? '');
            if ($strong === '' && $light === '' && trim(strip_tags($text)) === '') continue;
            if (!in_array($b['type'] ?? '', ['richtext', 'text', 'text_image', 'text_columns'], true) && trim(strip_tags($text)) === '') continue;
            if ($heading === '' && $strong !== '') {
                $heading = $strong;
                if ($light !== '') $html .= '<p><strong>' . e($light) . '</strong></p>';
            } elseif ($strong !== '' || $light !== '') {
                $html .= '<h2>' . e(trim($strong . ' ' . $light)) . '</h2>';
            }
            $html .= $text;
        }
        $html = (string) preg_replace('~<p>(\s|&nbsp;|<br\s*/?>)*</p>~i', '', Sanitizer::block($html));
        // Überschriften der Anzeige folgen auf der Detailseite direkt auf den Titel (H1): kleinste Ebene → H2
        if (preg_match_all('~<h([2-4])\b~i', $html, $hm) && ($min = min(array_map('intval', $hm[1]))) > 2) {
            $shift = $min - 2;
            $html = (string) preg_replace_callback('~<(/?)h([2-4])\b~i', fn($m) => '<' . $m[1] . 'h' . max(2, (int) $m[2] - $shift), $html);
        }
        if (trim(strip_tags($html)) === '' || $heading === '') { $log("! Seite {$pageId} „{$page['title']}“: keine Stellenanzeige (Überschrift + Text) gefunden."); return false; }
        $title = trim((string) ($o['title'] ?? ''));
        if ($title === '') {
            $title = trim((string) preg_replace('~\s+(gesucht|wanted)\s*[!.]?$~iu', '', $heading));
            if (!preg_match('~\((m/w/d|w/m/d|m/f/d|d/m/w)\)~i', $title)) $title .= ' (m/w/d)';
        }
        // 2. Beschäftigungsart: angegeben oder aus dem Text („Vollzeit oder Teilzeit“ → beide)
        $empField = Tables::field($t, Jobs::f($t, 'employment'));
        $allowed = array_keys((array) ($empField['options'] ?? []));
        $emp = ($o['employment'] ?? '') !== '' ? array_values(array_intersect($allowed, array_map('trim', explode(',', (string) $o['employment'])))) : [];
        if (($o['employment'] ?? '') === '') {
            $plain = mb_strtolower(html_entity_decode(strip_tags($html)));
            foreach (['vollzeit' => 'vollzeit', 'teilzeit' => 'teilzeit', 'minijob' => 'minijob', 'aushilfe' => 'befristet', 'befristet' => 'befristet',
                'ausbildung' => 'ausbildung', 'praktikum' => 'praktikum', 'werkstudent' => 'werkstudium', 'freie mitarbeit' => 'freie_mitarbeit', 'freiberuflich' => 'freie_mitarbeit'] as $word => $key) {
                if (str_contains($plain, $word) && in_array($key, $allowed, true) && !in_array($key, $emp, true)) $emp[] = $key;
            }
        }
        // 3. Veröffentlicht am: erste gespeicherte Version der Seite, sonst Veröffentlichung
        $posted = (string) ($o['posted'] ?? '');
        if (!preg_match('~^\d{4}-\d{2}-\d{2}$~', $posted)) {
            $first = (string) Pages::db()->fetchValue('SELECT MIN(created_at) FROM revisions WHERE page_id = ?', [$pageId]);
            $posted = substr($first !== '' ? $first : (string) ($page['published_at'] ?? now()), 0, 10);
        }
        $slug = Pages::slugify((string) ($o['slug'] ?? '') ?: $title);
        $log("Seite {$pageId} „{$page['title']}“ ({$path}) → Tabelle „{$t['name']}“ ({$t['handle']})");
        $log("  Titel: {$title}");
        $log('  Beschäftigungsart: ' . ($emp ? implode(', ', $emp) : '– (nicht erkannt)') . " · Veröffentlicht am: {$posted} · Gültig bis: – (leer)");
        $log('  Beschreibung: ' . mb_strlen(trim(strip_tags($html))) . ' Zeichen' . ($formHandle !== '' ? " · Formular der Seite: {$formHandle}" : ''));

        // 4. Eintrag anlegen (oder vorhandenen gleichen Slugs behalten)
        $e = Entries::bySlug($t, $slug, false);
        if ($e) {
            $log("= Eintrag gibt es schon (#{$e['id']}, /{$t['settings']['route']}/{$slug}) – unverändert.");
        } else {
            $values = [Jobs::f($t, 'title') => $title, Jobs::f($t, 'description') => $html, Jobs::f($t, 'date_posted') => $posted,
                Jobs::f($t, 'employment') => $emp, 'slug' => $slug, 'status' => 'published'];
            unset($values['']);
            $log($pfx . "+ Eintrag /{$t['settings']['route']}/{$slug} (veröffentlicht)");
            if (!$dry) {
                [$id, $errors] = Entries::save($t, null, $values);
                if ($errors) { $log('! Eintrag nicht gespeichert: ' . implode(' ', array_map('strval', $errors))); return false; }
                $e = Entries::find($t, (int) $id);
                Tables::db($t)->update($t['table'], ['published_at' => $posted . ' 00:00:00', 'created_at' => $posted . ' 00:00:00'], 'id = :id', ['id' => (int) $id]);
            }
        }
        // 5. Formular der Seite als Bewerbungsformular verknüpfen (sofern noch keins gewählt ist) – Feld „Stelle“ ergänzen
        $cur = (string) Jobs::config($t)['form'];
        if ($formHandle !== '' && ($cur === '' || $cur === '_new')) {
            $log($pfx . "~ Bewerbungsformular: Eingang „{$formHandle}“ der Seite" . (Tables::field(Tables::find($formHandle), Jobs::FIELD) ? '' : ' (+ Feld „Stelle“)'));
            if (!$dry && ($form = Jobs::ensureForm($formHandle, $log))) Jobs::linkForm($t, $form['handle']);
        } elseif ($formHandle !== '' && $cur !== $formHandle) {
            $log("= Bewerbungsformular bleibt „{$cur}“ (die Seite nutzte „{$formHandle}“).");
        } elseif ($cur !== '' && $cur !== '_new' && ($x = Tables::find($cur)) && !Tables::field($x, Jobs::FIELD)) {
            if (!$dry) Jobs::ensureForm($cur, $log); else $log($pfx . "~ Eingang „{$cur}“: + Feld „Stelle“");
        }
        $target = $e ? Jobs::ref($t, $e) : 'entry:' . $t['handle'] . ':(neu)';
        // 6. Links auf die alte Seite (page:ID) in Seiten und Einstellungen auf die Stelle umstellen
        if (($o['links'] ?? true) !== false) {
            $pat = '~"page:' . $pageId . '"|\\\\"page:' . $pageId . '\\\\"~';
            foreach (Pages::db()->fetchAll('SELECT id, title, content_draft, content_published FROM pages WHERE id != ? AND (content_draft LIKE ? OR content_published LIKE ?)',
                [$pageId, '%page:' . $pageId . '%', '%page:' . $pageId . '%']) as $p) {
                $upd = [];
                foreach (['content_published', 'content_draft'] as $col) {
                    if ($p[$col] === null || !preg_match($pat, (string) $p[$col])) continue;
                    $upd[$col] = (string) preg_replace_callback($pat, fn($m) => str_replace('page:' . $pageId, $target, $m[0]), (string) $p[$col]);
                }
                if (!$upd) continue;
                $log($pfx . "~ Seite {$p['id']} „{$p['title']}“: Link page:{$pageId} → {$target}");
                if (!$dry && $e) {
                    Pages::db()->update('pages', $upd + ['updated_at' => now()], 'id = :id', ['id' => (int) $p['id']]);
                    Pages::addRevision((int) $p['id'], (string) ($upd['content_draft'] ?? $upd['content_published']), null, 'Link auf Stellenangebot umgestellt');
                }
            }
            foreach (Pages::db()->fetchAll('SELECT skey, value_json FROM settings WHERE value_json LIKE ?', ['%page:' . $pageId . '%']) as $row) {
                if (!preg_match($pat, (string) $row['value_json'])) continue;
                $log($pfx . "~ Einstellung {$row['skey']}: Link page:{$pageId} → {$target}");
                if (!$dry && $e) Pages::db()->update('settings', ['value_json' => preg_replace_callback($pat, fn($m) => str_replace('page:' . $pageId, $target, $m[0]), (string) $row['value_json'])], 'skey = :k', ['k' => $row['skey']]);
            }
        }
        // 7. Alte Seite offline (Inhalt bleibt als Entwurf) + 301 alte Adresse → Stelle
        if (($o['redirect'] ?? true) !== false) {
            if ($page['status'] === 'published') {
                $log($pfx . "~ Seite {$pageId} „{$page['title']}“ offline (Status Entwurf, Inhalt bleibt)");
                if (!$dry && $e) Pages::db()->update('pages', ['status' => 'draft', 'menu' => 0, 'updated_at' => now()], 'id = :id', ['id' => $pageId]);
            }
            if (!Redirects::enabled()) {
                $log('! Funktion „Weiterleitungen“ ist aus – keine 301 von ' . $path . ' (bitte einschalten und erneut ausführen).');
            } elseif (($r = Redirects::bySource($path)) && $r['target'] === $target) {
                $log("= Weiterleitung {$path} → {$target} gibt es schon (#{$r['id']}).");
            } else {
                $log($pfx . ($r ? "~ Weiterleitung #{$r['id']} {$path} → {$target}" : "+ Weiterleitung 301 {$path} → {$target}"));
                if (!$dry && $e) {
                    [$rid, $errors] = Redirects::save(['source' => $path, 'target' => $target, 'code' => 301, 'note' => 'Stellenangebot in die Tabelle „' . $t['name'] . '“ übernommen'], $r ? (int) $r['id'] : null);
                    if ($errors) $log('! Weiterleitung: ' . implode(' ', $errors));
                }
            }
        }
        if (!$dry) PageCache::clear();
        if ($e && !$dry) {
            $problems = Jobs::problems(Tables::find($t['handle']), Entries::find($t, (int) $e['id']));
            $log($problems ? '! Google-Angaben: ' . implode('; ', $problems) : '✓ Google-Angaben vollständig (JobPosting gültig) – ' . (Entries::absUrl($t, $e) ?? ''));
        }
        return true;
    }
}
