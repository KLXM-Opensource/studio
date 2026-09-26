<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\VideoTools;

use Core\Media;

/**
 * Kommandozeile (je Website --site=key):
 *   video:info <id> [--loudness] [--json]      Analyse, Punktzahl, Empfehlungen
 *   video:optimize <id> --preset=web1080 [--replace] [--wait]   Auftrag anlegen (--wait: sofort in diesem Prozess)
 *   video:work [--all] [--job=ID]              wartende Aufträge abarbeiten (Cron, z. B. jede Minute)
 *   video:jobs [--open] [--cancel=ID] [--retry=ID] [--log=ID]   Aufträge anzeigen/steuern
 */
final class Console
{
    private static function opt(array $args, string $name, ?string $def = null): ?string
    {
        foreach ($args as $a) {
            if ($a === "--$name") return '1';
            if (str_starts_with((string) $a, "--$name=")) return substr((string) $a, strlen($name) + 3);
        }
        return $def;
    }

    private static function pos(array $args): array
    {
        return array_values(array_filter($args, fn($a) => !str_starts_with((string) $a, '--')));
    }

    private static function ready(): bool
    {
        if (!Repo::ready()) { fwrite(STDERR, "Tabellen fehlen – bitte `php bin/console migrate --site=" . site()->key . "`.\n"); return false; }
        if (!\Core\Features::on(VideoTools::FEATURE, false)) fwrite(STDERR, "Hinweis: Funktion „video.tools“ ist auf " . site()->key . " aus ('features' => ['video.tools' => true]).\n");
        return true;
    }

    public static function info(array $args): int
    {
        if (!self::ready()) return 1;
        $m = Repo::video((int) (self::pos($args)[0] ?? 0));
        if (!$m) { fwrite(STDERR, "Aufruf: video:info <medien-id> [--loudness] [--json]\n"); return 1; }
        if (self::opt($args, 'loudness') && Ffmpeg::available()) Repo::setLoudness($m, Ffmpeg::loudness(Repo::path($m)));
        $meta = Repo::meta($m, true);
        if (self::opt($args, 'json')) { echo json_encode(['id' => (int) $m['id']] + $meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n"; return 0; }
        $i = $meta['info'];
        $v = Analyzer::video($i);
        printf("#%d %s (%s, %s)\n", $m['id'], Media::displayName($m), $m['original_name'], Media::humanSize((int) $m['size']));
        printf("  Quelle:     %s%s\n", ($i['source'] ?? '') === 'ffprobe' ? 'ffprobe' : 'PHP (ohne ffmpeg)', Ffmpeg::available() ? '' : ' – ffmpeg/ffprobe nicht gefunden');
        printf("  Dauer:      %s\n", isset($i['duration']) ? Jobs::clock((int) ($i['duration'] * 1000)) . sprintf(' (%.2f s)', $i['duration']) : '?');
        if ($v) printf("  Video:      %s %s × %s, %s fps, %s Mbit/s\n", strtoupper((string) $v['codec']), $v['width'] ?? '?', $v['height'] ?? '?', $v['fps'] ?? '?', $v['bitrate'] ? Analyzer::mbit((int) $v['bitrate']) : '?');
        foreach (Analyzer::audio($i) as $a) printf("  Audio:      %s, %s Kanäle, %s Hz%s\n", strtoupper((string) $a['codec']), $a['channels'] ?? '?', $a['sample_rate'] ?? '?', $a['bitrate'] ? ', ' . (int) ($a['bitrate'] / 1000) . ' kbit/s' : '');
        if (!Analyzer::audio($i)) echo "  Audio:      keine Tonspur\n";
        printf("  faststart:  %s\n", ($i['faststart'] ?? null) === null ? '?' : (($i['faststart']) ? 'ja' : 'nein'));
        if ($meta['loud']) printf("  Lautheit:   %.1f LUFS, Spitze %s dBFS\n", $meta['loud']['lufs'], $meta['loud']['peak'] ?? '?');
        printf("  Punktzahl:  %d/100%s\n", $meta['assess']['score'], $meta['assess']['optimized'] ? ' – optimiert' : '');
        foreach ($meta['assess']['items'] as $it) printf("   %s %s%s\n", ['ok' => '✓', 'info' => '·', 'warn' => '!', 'err' => '✗'][$it['level']] ?? '-', $it['text'], $it['preset'] ? ' → Preset ' . $it['preset'] : '');
        return 0;
    }

    public static function optimize(array $args): int
    {
        if (!self::ready()) return 1;
        $m = Repo::video((int) (self::pos($args)[0] ?? 0));
        $preset = (string) self::opt($args, 'preset', '');
        if (!$m || !Presets::exists($preset)) {
            fwrite(STDERR, "Aufruf: video:optimize <medien-id> --preset=" . implode('|', array_keys(Presets::all())) . " [--replace] [--wait]\n");
            return 1;
        }
        try {
            $wait = (bool) self::opt($args, 'wait');
            $j = Jobs::create('optimize', $m, [], $preset, self::opt($args, 'replace') ? 'replace' : 'new', null, !$wait);
        } catch (JobException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            return 1;
        }
        echo "Auftrag #{$j['id']} angelegt: {$j['label']}\n";
        if (!$wait) return 0;
        Jobs::work(fn(string $s) => print($s . "\n"), $j['id']);
        $j = Jobs::get($j['id']);
        echo ($j['status'] === 'done' ? '✓ ' : '✗ ') . $j['message'] . "\n";
        return $j['status'] === 'done' ? 0 : 1;
    }

    public static function work(array $args): int
    {
        if (self::opt($args, 'all')) {
            $fail = 0;
            foreach (\Core\Sites::all() as $k => $c) {
                // eingeschaltet per Konfiguration oder in der Verwaltung (Funktionen & Erweiterungen)
                $on = method_exists(\Core\Extensions::class, 'enabledForSite') ? \Core\Extensions::enabledForSite((string) $k, 'video_tools')
                    : in_array('video_tools', (array) (\Core\Network\Network::config($k)->get('extensions', [])), true);
                if (!$on) continue;
                passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOT . '/bin/console') . ' video:work --site=' . escapeshellarg((string) $k), $code);
                $fail = $fail ?: $code;
            }
            return $fail;
        }
        if (!Repo::ready()) return 0;
        Jobs::cleanTmp();
        $only = self::opt($args, 'job');
        $n = Jobs::work(fn(string $s) => print($s . "\n"), $only !== null ? (int) $only : null);
        echo 'Video-Aufträge erledigt (' . site()->key . "): $n\n";
        return 0;
    }

    public static function jobs(array $args): int
    {
        if (!self::ready()) return 1;
        if (($id = self::opt($args, 'cancel')) !== null) { Jobs::cancel((int) $id); echo "Auftrag #$id: Abbruch angefordert.\n"; return 0; }
        if (($id = self::opt($args, 'retry')) !== null) {
            try { $j = Jobs::retry((int) $id); } catch (JobException $e) { fwrite(STDERR, $e->getMessage() . "\n"); return 1; }
            echo $j ? "Auftrag #$id erneut eingereiht.\n" : "Auftrag #$id lässt sich nicht wiederholen.\n";
            return $j ? 0 : 1;
        }
        if (($id = self::opt($args, 'log')) !== null) { echo (string) (Jobs::raw((int) $id)['log'] ?? ''), "\n"; return 0; }
        $list = Jobs::list(['open' => (bool) self::opt($args, 'open')], 50);
        if (!$list) { echo "Keine Aufträge.\n"; return 0; }
        foreach ($list as $j) {
            printf("#%-5d %-9s %3d%%  %-38s %-32s %s\n", $j['id'], $j['status'], $j['progress'], mb_strimwidth($j['label'], 0, 38, '…'),
                mb_strimwidth($j['media'], 0, 32, '…'), mb_strimwidth($j['message'], 0, 80, '…'));
        }
        return 0;
    }
}
