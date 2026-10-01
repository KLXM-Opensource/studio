<?php
/**
 * Befehle des Kits „praxis“ für bin/console (Core\Kit::commands).
 *
 *   php bin/console praxis:migrate-team [--dry-run] [--page=ID]
 *     Veralteten Block „Teamfoto + Text“ (team_photo) durch allgemeine Blöcke ersetzen:
 *       1. „Bild breit“ (16:9) mit dem Teamfoto – übernimmt Hintergrund, Sprungmarke, Navigation, Abstand oben (eigener Abschnitt)
 *       2. „Fließtext“ (breit) mit Überschrift + Text – darunter, Abstand unten wie bisher
 *       3. nur mit „Ausbildungs-/Stellen-Box“: „Handlungsaufruf“ als Box (dunkel) mit Titel, Text und Button,
 *          „Neben den vorigen Block stellen“ (⅓) → Text ⅔ + Box ⅓ in einem Abschnitt
 *     Nur team_photo-Blöcke werden angefasst; alle anderen Blöcke bleiben Byte für Byte gleich. Entwurf und veröffentlichte
 *     Fassung werden je für sich umgestellt (gleicher Stand → gleiches Ergebnis, die Seite zeigt danach keinen offenen Entwurf).
 *     Version „Team-Abschnitt umgestellt“. Wiederholbar: ohne team_photo-Blöcke ändert sich nichts.
 */
declare(strict_types=1);

/**
 * team_photo-Blöcke einer Blockliste ersetzen. IDs sind abgeleitet (gleiche Eingabe → gleiches Ergebnis): das Bild behält die
 * ID des alten Blocks (Sprungziel im Editor), Text „{id}-text“, Box „{id}-jobs“.
 * @return array{0: array, 1: int} [neue Blöcke, Anzahl ersetzter Blöcke]
 */
if (!function_exists('praxis_migrate_team_blocks')) {
function praxis_migrate_team_blocks(array $blocks): array
{
    $out = [];
    $n = 0;
    foreach ($blocks as $b) {
        if (($b['type'] ?? '') !== 'team_photo') { $out[] = $b; continue; }
        $n++;
        $d = (array) ($b['data'] ?? []);
        $t = (array) ($b['tunes']['section'] ?? []);
        $id = preg_replace('~[^\w\-]~', '', (string) ($b['id'] ?? '')) ?: 'team';
        $bg = (string) ($t['background'] ?? 'white');
        $visible = $t['visible'] ?? true;
        $new = [];
        // 1. Teamfoto – eigener Abschnitt mit Sprungmarke/Navigation des alten Blocks, unten ohne Abstand
        $new[] = ['id' => $id, 'type' => 'image_wide', 'data' => ['variant' => '16-9', 'image' => $d['image'] ?? null, 'caption' => '', 'credit' => ''],
            'tunes' => ['section' => array_replace($t, ['spaceBottom' => 'none', 'row' => ''])]];
        // 2. Überschrift + Text – Abstand oben klein (wie vorher zwischen Foto und Text), unten wie der alte Block
        $new[] = ['id' => $id . '-text', 'type' => 'richtext',
            'data' => ['title_strong' => (string) ($d['title_strong'] ?? ''), 'title_light' => (string) ($d['title_light'] ?? ''), 'text' => (string) ($d['text'] ?? ''), 'width' => 'wide'],
            'tunes' => ['section' => ['background' => $bg, 'visible' => $visible, 'spaceTop' => 'small', 'spaceBottom' => $t['spaceBottom'] ?? 'normal']]];
        // 3. Ausbildung/Stellen als Box neben dem Text (⅓, dunkle Karte wie bisher)
        if (!empty($d['show_jobs_box'])) {
            $label = trim((string) ($d['jobs_button_label'] ?? ''));
            $link = (string) ($d['jobs_link'] ?? '');
            $new[] = ['id' => $id . '-jobs', 'type' => 'cta',
                'data' => ['variant' => 'box', 'title_strong' => (string) ($d['jobs_title_strong'] ?? ''), 'title_light' => (string) ($d['jobs_title_light'] ?? ''),
                    'text' => (string) ($d['jobs_text'] ?? ''), 'buttons' => $label !== '' && $link !== '' ? [['label' => $label, 'link' => $link]] : []],
                'tunes' => ['section' => ['background' => 'dark', 'visible' => $visible, 'row' => '1-3']]];
        }
        foreach (\Core\Pages::sanitizeBlocks($new) as $nb) $out[] = $nb;
    }
    return [$out, $n];
}
}

return [
    'praxis:migrate-team' => [
        'Block „Teamfoto + Text“ in Bild breit + Fließtext + Box (nebeneinander) umstellen [--dry-run] [--page=ID]',
        function (array $args): int {
            $dry = in_array('--dry-run', $args, true);
            $only = 0;
            foreach ($args as $a) if (preg_match('~^--page=(\d+)$~', $a, $m)) $only = (int) $m[1];
            $db = \Core\Pages::db();
            $rows = $db->fetchAll("SELECT id, title, content_draft, content_published FROM pages
                WHERE content_draft LIKE '%\"team_photo\"%' OR content_published LIKE '%\"team_photo\"%' ORDER BY id");
            $changed = 0;
            foreach ($rows as $p) {
                if ($only && (int) $p['id'] !== $only) continue;
                $out = [];
                $log = [];
                foreach (['content_published', 'content_draft'] as $col) {
                    if ($p[$col] === null) continue;
                    // Gleicher Stand wie die veröffentlichte Fassung: dasselbe Ergebnis übernehmen (kein offener Entwurf)
                    if ($col === 'content_draft' && $p['content_draft'] === $p['content_published'] && isset($out['content_published'])) {
                        $out[$col] = $out['content_published'];
                        $log[] = 'Entwurf: gleich (kein offener Entwurf)';
                        continue;
                    }
                    $json = json_decode((string) $p[$col], true);
                    if (!is_array($json) || !is_array($json['blocks'] ?? null)) continue;
                    [$blocks, $n] = praxis_migrate_team_blocks($json['blocks']);
                    if (!$n) continue;
                    $json['blocks'] = $blocks;
                    $out[$col] = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $log[] = ($col === 'content_draft' ? 'Entwurf' : 'veröffentlicht') . ": $n Block „Teamfoto + Text“";
                }
                if (!$out) continue;
                $changed++;
                echo ($dry ? '[dry-run] ' : '') . "Seite {$p['id']} „{$p['title']}“ – " . implode(', ', $log) . "\n";
                if ($dry) continue;
                $db->update('pages', $out + ['updated_at' => now()], 'id = :id', ['id' => (int) $p['id']]);
                \Core\Pages::addRevision((int) $p['id'], (string) ($out['content_draft'] ?? $out['content_published']), null, 'Team-Abschnitt umgestellt');
            }
            if ($changed && !$dry) \Core\PageCache::clear();
            echo $changed ? ($dry ? "Dry-run: $changed Seite(n) würden umgestellt.\n" : "✓ $changed Seite(n) umgestellt (Version „Team-Abschnitt umgestellt“).\n")
                : "Nichts zu tun – kein Block „Teamfoto + Text“ gefunden.\n";
            return 0;
        },
    ],
];
