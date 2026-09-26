<?php
declare(strict_types=1);

namespace Core\Search;

use Core\AI\Ai;
use Core\Sites;

/**
 * Kommandozeile der Suche und KI (bin/console):
 *   search:index [--all] [--full] [--no-vectors] [--kb]   Index abgleichen (Standard: nur Änderungen)
 *   search:index --help [--no-vectors]                    Hilfe-Index des Assistenten (Core\AI\HelpIndex)
 *   search:status [--all]                                 Stand des Index, Vektoren, KI-Anbieter
 *   search:query "<text>" [--lang=en]                     Testsuche (wie Besucher, ohne Zähler)
 *   ai:test [--cap=text|embed|vision]                     KI-Anbieter je Fähigkeit prüfen (Standard: alle konfigurierten)
 */
final class Console
{
    public static function run(string $cmd, array $args, string $script): int
    {
        if (in_array('--all', $args, true)) {
            $rest = array_values(array_filter($args, fn($a) => $a !== '--all'));
            $fail = 0;
            foreach (array_keys(Sites::all()) as $k) {
                echo "── $k\n";
                passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . $cmd . ' ' . implode(' ', array_map('escapeshellarg', $rest)) . ' --site=' . escapeshellarg($k), $code);
                $fail = $fail ?: $code;
            }
            return $fail;
        }
        $pos = array_values(array_filter($args, fn($a) => !str_starts_with($a, '--')));
        $opt = fn(string $name) => in_array('--' . $name, $args, true);
        return match ($cmd) {
            'search:index' => $opt('help') ? self::help(!$opt('no-vectors')) : self::index($opt('full'), !$opt('no-vectors'), $opt('kb')),
            'search:status' => self::status(),
            'search:query' => self::query(implode(' ', $pos), $args),
            'ai:test' => self::aiTest(array_values(array_filter(array_map(fn($a) => str_starts_with($a, '--cap=') ? substr($a, 6) : null, $args)))),
            default => 1,
        };
    }

    /** Hilfe-Index des Assistenten (Core\AI\HelpIndex): Handbuch, Technik, Tutorials – Stichwörter + Vektoren */
    private static function help(bool $vectors): int
    {
        $r = \Core\AI\HelpIndex::rebuild($vectors, fn($m) => print($m . "\n"));
        echo "Hilfe-Index (" . site()->key . "): {$r['sections']} Abschnitte, {$r['vectors']} neue Vektoren.\n";
        if ($r['error']) { fwrite(STDERR, "KI-Anbieter: {$r['error']} (Stichwortsuche funktioniert weiter)\n"); return 2; }
        return 0;
    }

    private static function index(bool $full, bool $vectors, bool $kb): int
    {
        if ($kb) {
            $cfg = Ai::installationConfig();
            if (!$cfg['enabled'] || $cfg['embeddings'] === '') { fwrite(STDERR, "Keine KI mit Embeddings in config/config.local.php ('ai').\n"); return 1; }
            $n = (new SupportSemantic($cfg))->reindex(fn($m) => print($m . "\n"));
            echo "Wissensdatenbank: $n Abschnitte eingebettet ({$cfg['provider']}:{$cfg['embeddings']}).\n";
            return 0;
        }
        if (!Search::enabled()) { echo "Suche ist auf dieser Website abgeschaltet (features: search).\n"; return 0; }
        $s = Search::process(force: true, full: $full, vectors: $vectors, log: fn($m) => print($m . "\n"));
        if ($s === null) { fwrite(STDERR, "Abgleich läuft bereits (Sperre " . Search::dir() . "/.lock).\n"); return 1; }
        printf("Suchindex aktuell (%s): %d Dokumente, %d neu, %d geändert, %d entfernt%s – %.2f s\n", site()->key, $s['docs'], $s['added'], $s['updated'], $s['removed'],
            $vectors && Search::semanticActive() ? sprintf(', Vektoren: %d Dokumente/%d Abschnitte%s', $s['vectors'], $s['chunks'], $s['pending'] ? ", {$s['pending']} ausstehend" : '') : '', $s['seconds']);
        if ($s['vector_error']) { fwrite(STDERR, "KI-Anbieter: {$s['vector_error']} (Stichwortsuche funktioniert weiter)\n"); return 2; }
        return 0;
    }

    private static function status(): int
    {
        $st = Search::status();
        $cfg = Ai::config();
        echo "Website " . site()->key . ': Suche ' . ($st['enabled'] ? 'an' : 'aus') . ', semantisch ' . ($st['semantic'] ? 'an' : 'aus') . "\n";
        foreach ($st['languages'] as $l => $x) {
            printf("  %-5s %4d Dokumente, Stichwort-Index %4d%s\n", $l, $x['docs'], $x['keyword'], $st['semantic'] ? sprintf(', Vektoren %d', $x['vectors']) : '');
        }
        printf("  Stand: %s%s · Abschnitte: %d · Größe: %s · %s\n", $st['synced_at'] ?? 'nie', $st['dirty'] ? ' (Änderungen vorgemerkt)' : '',
            $st['chunks'], \Core\Media::humanSize((int) $st['bytes']), $st['dir']);
        if ($st['error']) echo "  Fehler: {$st['error']}\n";
        if ($st['vector_error']) echo "  KI-Fehler: {$st['vector_error']}\n";
        $e = Ai::capability('embed', $cfg);
        echo '  KI (Embeddings): ' . ($e['configured'] ? $e['provider'] . ' · ' . $e['endpoint'] . ' · ' . $e['model'] . ($e['external'] ? ' · EXTERN' : ' · lokal') . (Ai::enabled('embed') ? '' : ' · für diese Website aus') : 'nicht konfiguriert') . "\n";
        return 0;
    }

    private static function query(string $q, array $args): int
    {
        foreach ($args as $a) {
            if (str_starts_with($a, '--lang=') && \Core\Lang::valid(substr($a, 7))) app()->lang = substr($a, 7);
        }
        $r = Search::query($q, ['count_miss' => false]);
        printf("%d Treffer (%s%s)\n", $r['total'], $r['mode'], $r['fallback'] ? ', Rückfall auf Stichwortsuche' : '');
        foreach ($r['items'] as $i => $it) {
            printf("%2d. [%s|%s] %s\n    %s\n    %s\n", $i + 1, $it['badge'], $it['match'], strip_tags($it['title']), $it['url'], mb_strimwidth(strip_tags($it['snippet']), 0, 140, '…'));
        }
        return 0;
    }

    private static function aiTest(array $caps): int
    {
        $cfg = Ai::config();
        if (!$cfg['configured']) { fwrite(STDERR, "Kein KI-Anbieter konfiguriert (Grundeinstellungen → KI oder config 'ai').\n"); return 1; }
        $site = Ai::siteSettings();
        echo 'Website ' . site()->key . ': KI ' . ($site['enabled'] ? 'an' : 'aus') . ' · Funktion „ai“ ' . (\Core\Features::on('ai', false) ? 'an' : 'aus') . "\n";
        $fail = 0;
        foreach ($caps ?: array_keys(Ai::CAPS) as $cap) {
            if (!isset(Ai::CAPS[$cap])) { fwrite(STDERR, "Unbekannte Fähigkeit „{$cap}“ (text, embed, vision, transcribe).\n"); return 1; }
            $c = Ai::capability($cap, $cfg);
            if (!$c['configured'] && !$caps) continue;
            $t = Ai::test($cap, $cfg);
            printf("  %-6s %-8s %-28s %s%s%s%s\n", $cap, $t['provider'] ?: '–', $t['model'] ?: '–', $t['ok'] ? 'OK' : 'FEHLER', $t['ms'] !== null ? " · {$t['ms']} ms" : '',
                $t['dims'] ? " · {$t['dims']} Dimensionen" : ($t['text'] !== null ? ' · „' . $t['text'] . '“' : ''), $t['error'] ? ' · ' . $t['error'] : '');
            echo '         ' . $t['endpoint'] . ($t['external'] ? ' (extern – Inhalte verlassen den Server)' : ' (lokal)') . ($site['caps'][$cap] ? '' : ' · auf dieser Website ausgeschaltet') . "\n";
            $fail += $t['ok'] ? 0 : 1;
        }
        return $fail ? 1 : 0;
    }
}
