<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Glossary;

use Core\Data\Entries;
use Core\Data\Shared;
use Core\Data\Tables;
use Core\Lang;
use Core\PageCache;
use Core\Sites;

/**
 * Geteiltes Glossar: ein Glossar für mehrere Websites dieser Installation – gebaut auf den geteilten Datentabellen
 * (Core\Data\Shared, Schlüssel = Kurzname „glossar“). Kein eigener Mechanismus: Speicher storage/shared/glossar/, Herkunft je
 * Begriff (origin_site), Auswahl je Website (share_picks: ausblenden), Detailseiten jeder Website unter /glossar/{slug}.
 *
 *  - Teilen (Eigentümer): share() verschiebt die lokale Tabelle in den geteilten Speicher (IDs bleiben – Verweise entry:glossar:{id}
 *    wirken weiter), schaltet „Beteiligte sehen sich gegenseitig“ und die automatische Übernahme ein und lädt Websites ein.
 *  - Beitreten (eingeladene Website): plan() zeigt doppelte Begriffe (Begriff/Varianten ohne Groß-/Kleinschreibung und Akzente,
 *    je Sprache), join() übernimmt die eigenen Begriffe mit origin_site = diese Website – je Doppel nach Wahl: „existing“
 *    (vorhandenen Begriff nutzen, eigenen nicht übernehmen; Verweise zeigen auf den vorhandenen), „mine“ (eigenen übernehmen,
 *    den anderen hier ausblenden) oder „both“. Neue IDs werden in Seiten, Versionen und Erklärungen umgeschrieben.
 *  - Lesen: Glossary::terms() fragt die Tabelle mit der Quelle „site“ ab – eigene Begriffe (auch Entwürfe) + veröffentlichte der
 *    übrigen Websites, ohne die hier ausgeblendeten. Markierung, Übersicht, Detailseiten, Quick-Glossar und Linkauswahl lesen so.
 *  - Verlassen: leave() – Mitglied: wieder eigene Tabelle mit den eigenen Begriffen (+ Kopien der zuletzt gezeigten fremden);
 *    Eigentümer: Teilen beenden, sobald keine andere Website mehr beteiligt ist.
 *  - Rechte: Teilen, Einladen, Beitreten, Verlassen = Recht „Grundeinstellungen“ (system.manage) + „Geteilte Daten verwalten“
 *    (Shared::canManage). Ausblenden = Recht data.publish auf die Tabelle. Bearbeiten nur eigene Begriffe (wie alle geteilten Tabellen).
 */
final class Sharing
{
    public const KEY = Glossary::HANDLE;
    public const CHOICES = ['existing', 'mine', 'both'];

    /** Darf die angemeldete Person das Teilen des Glossars einrichten bzw. beitreten/verlassen? */
    public static function canManage(): bool
    {
        try {
            return can('system.manage') && Shared::canManage();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Nur sinnvoll mit mehreren Websites in der Installation */
    public static function available(): bool
    {
        return Sites::multi();
    }

    /**
     * Zustand dieser Website: role = local (nicht geteilt) | owner | member | invited (eingeladen, noch nicht beigetreten) |
     * none (es gibt ein geteiltes Glossar, diese Website ist nicht eingeladen)
     */
    public static function status(): array
    {
        $meta = Shared::meta(self::KEY);
        $site = site()->key;
        $role = match (true) {
            !$meta => 'local',
            $meta['owner'] === $site => 'owner',
            in_array($site, $meta['members'], true) => 'member',
            in_array($site, $meta['invited'], true) => 'invited',
            default => 'none',
        };
        $sites = [];
        foreach (array_keys(Sites::all()) as $k) if ($k !== $site) $sites[$k] = Shared::siteInfo($k)['name'];
        return ['role' => $role, 'meta' => $meta, 'sites' => $sites, 'site' => $site,
            'owner' => $meta ? Shared::siteInfo($meta['owner'], self::KEY)['name'] : null, 'local' => self::localTable() !== null];
    }

    /** Lokale (nicht geteilte) Glossar-Tabelle dieser Website */
    public static function localTable(): ?array
    {
        $row = app()->db->fetch('SELECT id FROM data_tables WHERE handle = ?', [self::KEY]);
        if (!$row) return null;
        Tables::flush();
        $t = Tables::find((int) $row['id']);
        return $t && !Tables::isShared($t) && Glossary::fieldsOk($t) ? $t : null;
    }

    // ================================================================= Doppelte Begriffe

    /** Vergleichsform: ohne Groß-/Kleinschreibung, Akzente/Umlaute (ä → a, ß → ss), Leer- und Satzzeichen („E-Mail“ = „email“) */
    public static function norm(string $s): string
    {
        $s = mb_strtolower(trim(html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $s = str_replace('ß', 'ss', $s);
        if (class_exists(\Normalizer::class)) {
            $s = (string) preg_replace('~\p{Mn}+~u', '', (string) \Normalizer::normalize($s, \Normalizer::FORM_D));
        } else {
            $s = strtr($s, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ç' => 'c', 'ñ' => 'n', 'č' => 'c', 'š' => 's', 'ž' => 'z']);
        }
        return (string) preg_replace('~[^\p{L}\p{N}]+~u', '', $s);
    }

    /** Vergleichsformen eines Begriffs (Begriff + Varianten) */
    public static function keys(array $t): array
    {
        $out = [];
        foreach ([(string) $t['term'], ...array_map('strval', (array) ($t['variants'] ?? []))] as $v) {
            $n = self::norm($v);
            if (mb_strlen($n) >= 2) $out[$n] = $v;
        }
        return $out;
    }

    /**
     * Doppelte Begriffe: je Begriff aus $mine die Begriffe aus $theirs derselben Sprache mit gleicher Vergleichsform (Begriff oder
     * Variante). @return list<array{term: array, matches: list<array>, on: string}>
     */
    public static function duplicates(array $mine, array $theirs): array
    {
        $index = [];
        foreach ($theirs as $x) {
            foreach (array_keys(self::keys($x)) as $k) $index[(string) ($x['lang'] ?? '') . "\x1F" . $k][(int) $x['id']] = $x;
        }
        $out = [];
        foreach ($mine as $t) {
            $matches = [];
            $on = '';
            foreach (self::keys($t) as $k => $label) {
                foreach ($index[(string) ($t['lang'] ?? '') . "\x1F" . $k] ?? [] as $id => $x) {
                    $matches[$id] ??= $x;
                    if ($on === '') $on = $label;
                }
            }
            if ($matches) $out[] = ['term' => $t, 'matches' => array_values($matches), 'on' => $on];
        }
        return $out;
    }

    /** Begriff aus einer Rohzeile (ohne Detailadresse) – für Vergleiche */
    private static function plain(array $e): array
    {
        return ['id' => (int) $e['id'], 'term' => trim(html_entity_decode(strip_tags((string) ($e['begriff'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'short' => Glossary::short((string) ($e['kurz'] ?? '')), 'variants' => Glossary::splitVariants((string) ($e['varianten'] ?? '')),
            'lang' => Lang::norm($e['lang'] ?? null), 'slug' => (string) ($e['slug'] ?? ''), 'status' => (string) ($e['status'] ?? 'published'),
            'origin' => (string) ($e['origin_site'] ?? site()->key)];
    }

    /** Veröffentlichte Begriffe der übrigen Websites im geteilten Glossar (direkt aus dem Speicher – auch vor dem Beitreten) */
    public static function sharedTerms(): array
    {
        $meta = Shared::meta(self::KEY);
        if (!$meta) return [];
        $sites = array_values(array_diff(Shared::participants($meta), [site()->key]));
        if (!$sites) return [];
        $rows = Shared::db(self::KEY)->fetchAll('SELECT * FROM data_' . self::KEY . " WHERE status = 'published' AND origin_site IN ("
            . implode(',', array_fill(0, count($sites), '?')) . ') ORDER BY begriff', $sites);
        return array_map(fn($r) => self::plain($r) + ['origin_name' => Shared::siteInfo((string) $r['origin_site'], self::KEY)['name']], $rows);
    }

    /** Begriffe der lokalen Tabelle (alle, auch Entwürfe) */
    public static function localTerms(): array
    {
        $t = self::localTable();
        return $t ? array_map(fn($r) => self::plain($r), app()->db->fetchAll("SELECT * FROM {$t['table']} ORDER BY begriff")) : [];
    }

    /** Vorschau des Beitretens: eigene Begriffe, Begriffe der anderen, Doppel */
    public static function plan(): array
    {
        $mine = self::localTerms();
        $theirs = self::sharedTerms();
        return ['local' => count($mine), 'shared' => count($theirs), 'dupes' => self::duplicates($mine, $theirs)];
    }

    // ================================================================= Teilen, Einladen, Beitreten, Verlassen

    /** Glossar dieser Website teilen (diese Website wird Eigentümerin) und Websites einladen. @return list<string> */
    public static function share(array $invite = []): array
    {
        if ($m = Shared::meta(self::KEY)) {
            throw new \InvalidArgumentException(__('In dieser Installation gibt es schon ein geteiltes Glossar (Eigentümer: {site}).', ['site' => Shared::siteInfo($m['owner'], self::KEY)['name']]));
        }
        if (!self::localTable()) throw new \InvalidArgumentException(__('Bitte zuerst das Glossar einrichten.'));
        $log = Shared::shareLocal(self::KEY, [], false, ['members_see_members' => true]);
        // Alle sehen alles: Eigentümer übernimmt Begriffe der Mitglieder automatisch, Mitglieder zeigen sich gegenseitig (join)
        Shared::update(self::KEY, ['label' => __('Glossar'), 'auto' => ['enabled' => true, 'sites' => [], 'where' => []], 'invited' => $invite]);
        self::done();
        if ($invite) $log[] = __('Eingeladen: {sites}. Diese Websites treten in ihren Glossar-Einstellungen bei.', ['sites' => implode(', ', array_map(fn($s) => Shared::siteInfo($s)['name'], Shared::meta(self::KEY)['invited']))]);
        return $log;
    }

    /** Einladungen setzen (nur Eigentümer) */
    public static function invite(array $sites): void
    {
        $meta = Shared::meta(self::KEY) ?? throw new \InvalidArgumentException(__('Das Glossar ist nicht geteilt.'));
        if ($meta['owner'] !== site()->key) throw new \InvalidArgumentException(__('Einladen kann nur die Website, der das geteilte Glossar gehört.'));
        Shared::update(self::KEY, ['invited' => $sites]);
    }

    /**
     * Beitreten (nur auf Einladung). $choices: [lokale Begriffs-ID => existing|mine|both] für die Doppel aus plan() – ohne Angabe
     * „existing“ (vorhandenen Begriff nutzen). @return list<string>
     */
    public static function join(array $choices = [], string $by = ''): array
    {
        $meta = Shared::meta(self::KEY) ?? throw new \InvalidArgumentException(__('Es gibt kein geteiltes Glossar in dieser Installation.'));
        if (in_array(site()->key, Shared::participants($meta), true)) throw new \InvalidArgumentException(__('Diese Website nutzt das geteilte Glossar schon.'));
        if (!Shared::isInvited(self::KEY)) throw new \InvalidArgumentException(__('Diese Website ist nicht eingeladen – die Website, der das Glossar gehört ({site}), lädt sie ein.', ['site' => Shared::siteInfo($meta['owner'], self::KEY)['name']]));
        $skip = [];
        $hide = [];
        if (self::localTable()) {
            foreach (self::plan()['dupes'] as $d) {
                $id = (int) $d['term']['id'];
                $choice = in_array($choices[$id] ?? '', self::CHOICES, true) ? $choices[$id] : 'existing';
                if ($choice === 'existing') $skip[$id] = (int) $d['matches'][0]['id'];
                elseif ($choice === 'mine') foreach ($d['matches'] as $x) $hide[] = (int) $x['id'];
            }
            $map = [];
            // Hinweis „IDs in Blockinhalten werden nicht umgeschrieben“ gilt hier nicht – rewriteRefs() stellt die Verweise um
            $log = array_values(array_diff(Shared::shareLocal(self::KEY, [], true, [], $map, $skip),
                [__('Hinweis: Eintrags-IDs in Blockinhalten (z. B. Filterwerte) werden nicht umgeschrieben – Filter nach Namen/Slugs funktionieren weiter.')]));
            $moved = array_filter($map, fn($new, $old) => (int) $new !== (int) $old, ARRAY_FILTER_USE_BOTH);
            if ($moved) {
                $n = self::rewriteRefs($moved);
                if ($n) $log[] = __('Verweise auf Begriffe in {n} Inhalten auf die neuen Nummern umgestellt.', ['n' => $n]);
            }
        } else {
            Shared::addMember(self::KEY);
            $log = [__('Diese Website ist jetzt beteiligt.')];
        }
        Shared::saveLocal(self::KEY, ['owner' => true, 'members' => 'all', 'sites' => [], 'where' => []]);
        self::done();
        if ($hide && ($t = Glossary::table())) {
            $n = Shared::setPick($t, array_values(array_unique($hide)), 'hidden', $by);
            $log[] = __('{n} Begriffe anderer Websites auf dieser Website ausgeblendet (eigener Begriff gilt).', ['n' => $n]);
        }
        // Detailseiten-Vorlage und Übersicht dieser Website (falls noch nicht da)
        [$msgs] = Glossary::install();
        foreach ($msgs as $m) if ($m !== __('Tabelle „Glossar“ ist schon da.')) $log[] = $m;
        self::done();
        return $log;
    }

    /** Verlassen bzw. (Eigentümer, ohne Mitglieder) Teilen beenden. $keepForeign: Kopien der zuletzt gezeigten fremden Begriffe behalten */
    public static function leave(bool $keepForeign = true): array
    {
        $meta = Shared::meta(self::KEY) ?? throw new \InvalidArgumentException(__('Das Glossar ist nicht geteilt.'));
        if ($meta['owner'] === site()->key) {
            if ($meta['members']) {
                throw new \InvalidArgumentException(__('Teilen beenden geht erst, wenn keine andere Website mehr beteiligt ist. Beteiligt: {sites} – diese verlassen das Glossar in ihren Glossar-Einstellungen (sie behalten dabei eine Kopie).',
                    ['sites' => implode(', ', array_map(fn($s) => Shared::siteInfo($s, self::KEY)['name'], $meta['members']))]));
            }
            $log = Shared::unshare(self::KEY);
        } else {
            $log = Shared::leave(self::KEY, $keepForeign);
        }
        self::done();
        PageCache::clear();
        return $log;
    }

    /** Begriff auf dieser Website aus- bzw. wieder einblenden (nur fremde Begriffe) */
    public static function hide(int $id, bool $hide, string $by = ''): int
    {
        $t = Glossary::table();
        if (!$t || !Tables::isShared($t)) throw new \InvalidArgumentException(__('Das Glossar ist nicht geteilt.'));
        $n = Shared::setPick($t, [$id], $hide ? 'hidden' : null, $by);
        Glossary::flush();
        return $n;
    }

    /** Ausgeblendete fremde Begriffe dieser Website */
    public static function hidden(): array
    {
        $t = Glossary::table();
        if (!$t || !Tables::isShared($t)) return [];
        $out = [];
        foreach (Entries::query($t, ['status' => 'published', 'lang' => 'all', 'source' => 'all', 'pick' => 'hidden', 'limit' => 1000, 'sort' => 'begriff', 'dir' => 'asc']) as $e) {
            $out[] = Glossary::term($t, $e);
        }
        return $out;
    }

    /**
     * Verweise entry:glossar:{alt} → entry:glossar:{neu} umschreiben: Seiten (Arbeitsstand + veröffentlicht), Versionen und
     * Texte der eigenen Begriffe im geteilten Speicher. @return int Anzahl geänderter Datensätze
     */
    public static function rewriteRefs(array $map): int
    {
        if (!$map) return 0;
        $rx = '~entry:' . self::KEY . ':(\d+)(?!\d)~';
        $fn = fn(?string $s) => $s === null || !str_contains($s, 'entry:' . self::KEY . ':') ? $s
            : (string) preg_replace_callback($rx, fn($m) => 'entry:' . self::KEY . ':' . ($map[(int) $m[1]] ?? $m[1]), $s);
        $n = 0;
        $db = app()->db;
        foreach ($db->fetchAll("SELECT id, content_draft, content_published FROM pages WHERE content_draft LIKE ? OR content_published LIKE ?", ['%entry:' . self::KEY . ':%', '%entry:' . self::KEY . ':%']) as $p) {
            $upd = array_filter(['content_draft' => $fn($p['content_draft']), 'content_published' => $fn($p['content_published'])], fn($v, $k) => $v !== $p[$k], ARRAY_FILTER_USE_BOTH);
            if ($upd) { $db->update('pages', $upd, 'id = :id', ['id' => (int) $p['id']]); $n++; }
        }
        try {
            foreach ($db->fetchAll('SELECT id, blocks_json FROM revisions WHERE blocks_json LIKE ?', ['%entry:' . self::KEY . ':%']) as $r) {
                $v = $fn($r['blocks_json']);
                if ($v !== $r['blocks_json']) { $db->update('revisions', ['blocks_json' => $v], 'id = :id', ['id' => (int) $r['id']]); $n++; }
            }
        } catch (\Throwable) {
            // ältere Installation ohne Versionen
        }
        // Erklärungen der eigenen Begriffe (Links auf andere Begriffe)
        $t = Tables::sharedTable(self::KEY);
        if ($t) {
            $cols = array_column(array_filter($t['fields'], fn($f) => in_array($f['type'], ['richtext', 'textarea', 'text'], true)), 'name');
            $sdb = Shared::db(self::KEY);
            foreach ($sdb->fetchAll("SELECT * FROM {$t['table']} WHERE origin_site = ?", [site()->key]) as $r) {
                $upd = [];
                foreach ($cols as $c) if (($v = $fn($r[$c] ?? null)) !== ($r[$c] ?? null)) $upd[$c] = $v;
                if ($upd) { $sdb->update($t['table'], $upd, 'id = :id', ['id' => (int) $r['id']]); $n++; }
            }
        }
        PageCache::clear();
        return $n;
    }

    private static function done(): void
    {
        Tables::flush();
        Glossary::flush();
        Shared::clearCaches(self::KEY);
    }
}
