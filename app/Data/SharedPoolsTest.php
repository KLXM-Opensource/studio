<?php
declare(strict_types=1);

namespace Core\Data;

use Core\MediaPools;

/**
 * Selbsttest (Teil von `data:selftest`): Medien-Pools geteilter Tabellen („data-{key}“) entstehen nur bei Bedarf und
 * Shared::cleanupPools() entfernt nur leere, automatisch angelegte Pools von Tabellen ohne Bild-/Dateifelder.
 * Legt vorübergehende geteilte Tabellen (nur diese Website beteiligt) und Pools an und entfernt sie danach vollständig.
 */
final class SharedPoolsTest
{
    /** @return array{ok: int, fails: list<string>} */
    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = 'Geteilte Pools – ' . $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $sfx = bin2hex(random_bytes(3));
        $keys = [];
        $pools = [];
        $has = fn(string $key) => is_file(MediaPools::dir(Shared::poolKey($key)) . '/pool.json');
        $make = function (string $key, array $fields) use (&$keys, &$pools): void {
            [$def, $errors] = Tables::validate(['name' => 'Selbsttest ' . $key, 'handle' => $key, 'fields' => $fields, '_shared_owner' => site()->key]);
            if ($errors) throw new \RuntimeException('Tabelle „' . $key . '“: ' . json_encode($errors, JSON_UNESCAPED_UNICODE));
            $keys[] = $key;
            $pools[] = Shared::poolKey($key);
            Shared::create($def, []);
        };
        try {
            // 1. Ohne Bild-/Dateifeld: kein Pool (auch nicht beim Ändern der Einstellungen)
            $plain = 'stp_text_' . $sfx;
            $make($plain, [['label' => 'Begriff', 'type' => 'text', 'required' => 1], ['label' => 'Erklärung', 'type' => 'richtext']]);
            $eq('ohne Medienfeld kein Pool', $has($plain), false);
            Shared::update($plain, ['label' => 'Selbsttest umbenannt']);
            $eq('Einstellungen ändern legt keinen Pool an', $has($plain), false);
            $eq('hasMediaFields ohne Medienfeld', Shared::hasMediaFields($plain), false);

            // 2. Mit Bildfeld: Pool sofort, markiert und der Tabelle zugeordnet
            $img = 'stp_bild_' . $sfx;
            $make($img, [['label' => 'Titel', 'type' => 'text', 'required' => 1], ['label' => 'Foto', 'type' => 'media']]);
            $pk = Shared::poolKey($img);
            $eq('mit Bildfeld Pool angelegt', $has($img), true);
            $eq('Pool markiert (shared_table)', MediaPools::meta($pk)['shared_table'] ?? null, $img);
            $eq('Pool gehört zur Tabelle', Shared::poolTable($pk), $img);
            $eq('Pool nutzt diese Website', in_array(site()->key, (array) MediaPools::meta($pk)['sites'], true), true);

            // 3. Dateifeld später ergänzt (Schema speichern) → Pool entsteht dann
            $late = 'stp_spaet_' . $sfx;
            $make($late, [['label' => 'Titel', 'type' => 'text', 'required' => 1]]);
            $eq('vor dem Dateifeld kein Pool', $has($late), false);
            $t = Tables::sharedTable($late) ?? throw new \RuntimeException('Tabelle „' . $late . '“ nicht lesbar.');
            $in = Tables::toInput($t);
            $in['fields'][] = ['id' => '', 'label' => 'Anhang', 'name' => '', 'type' => 'file', 'accept' => ['pdf']];
            [$def, $errors] = Tables::validate($in, $t);
            $eq('Dateifeld ergänzt ohne Fehler', $errors, []);
            Tables::update($t, $def);
            $eq('Dateifeld ergänzt → Pool angelegt', $has($late), true);

            // 4. Aufräumen: nur leere, automatisch angelegte Pools von Tabellen ohne Medienfelder
            $legacy = 'stp_alt_' . $sfx;      // früheres Verhalten: leerer Pool „Daten: …“ ohne Markierung
            $make($legacy, [['label' => 'Begriff', 'type' => 'text', 'required' => 1]]);
            MediaPools::create(Shared::poolKey($legacy), 'Daten: Selbsttest', [site()->key]);
            $full = 'stp_voll_' . $sfx;       // automatischer Pool mit Datei → bleibt
            $make($full, [['label' => 'Begriff', 'type' => 'text', 'required' => 1]]);
            Shared::ensurePool($full, true);
            MediaPools::db(Shared::poolKey($full))->insert('media', ['file' => 'selbsttest.jpg', 'original_name' => 'selbsttest.jpg', 'mime' => 'image/jpeg', 'created_at' => now()]);
            $user = 'stp_frei_' . $sfx;       // gleicher Kurzname, aber selbst angelegt (eigene Bezeichnung) → bleibt
            $make($user, [['label' => 'Begriff', 'type' => 'text', 'required' => 1]]);
            MediaPools::create(Shared::poolKey($user), 'Markenbilder', [site()->key]);
            $orphan = 'stp_datei_' . $sfx;    // automatischer Pool, Datenbank leer, aber Datei im Ordner → bleibt
            $make($orphan, [['label' => 'Begriff', 'type' => 'text', 'required' => 1]]);
            Shared::ensurePool($orphan, true);
            file_put_contents(MediaPools::mediaDir(Shared::poolKey($orphan)) . '/rest.jpg', 'x');

            $want = [Shared::poolKey($legacy)];
            $eq('Probelauf meldet nur den leeren automatischen Pool', Shared::cleanupPools(true, $keys), $want);
            $eq('Probelauf entfernt nichts', $has($legacy), true);
            $eq('Aufräumen entfernt nur den leeren automatischen Pool', Shared::cleanupPools(false, $keys), $want);
            $eq('leerer Pool entfernt', $has($legacy), false);
            $eq('Pool mit Datei bleibt', $has($full), true);
            $eq('selbst angelegter Pool bleibt', $has($user), true);
            $eq('Pool mit Datei im Ordner bleibt', $has($orphan), true);
            $eq('Pool einer Tabelle mit Bildfeld bleibt', [$has($img), $has($late)], [true, true]);
            $eq('Protokoll', str_contains((string) @file_get_contents(MediaPools::dir('_removed') . '/removed.log'), Shared::poolKey($legacy)), true);
            $eq('zweiter Lauf ändert nichts (idempotent)', Shared::cleanupPools(false, $keys), []);
        } catch (\Throwable $e) {
            $fails[] = 'Geteilte Pools – Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            self::remove($keys, $pools, $sfx);
        }
        return ['ok' => $ok, 'fails' => $fails];
    }

    /** Vorübergehende Tabellen, Pools, Sicherungen und Protokollzeilen restlos entfernen */
    private static function remove(array $keys, array $pools, string $sfx): void
    {
        $rm = function (string $p) use (&$rm): void {
            if (is_dir($p) && !is_link($p)) {
                foreach (array_diff(scandir($p) ?: [], ['.', '..']) as $f) $rm("$p/$f");
                @rmdir($p);
            } elseif (file_exists($p) || is_link($p)) {
                @unlink($p);
            }
        };
        foreach ($keys as $k) {
            if (Shared::validKey($k) && str_contains($k, $sfx)) $rm(Shared::dir($k));
            app()->settings->delete('shared.' . $k);
        }
        foreach ($pools as $pk) {
            if (!str_contains($pk, $sfx)) continue;
            $rm(MediaPools::dir($pk));
            $rm(MediaPools::mediaDir($pk));
            foreach (glob(MediaPools::dir('_removed') . '/' . $pk . '-*') ?: [] as $d) $rm($d);
        }
        $log = MediaPools::dir('_removed') . '/removed.log';
        if (is_file($log)) {
            $lines = array_filter(file($log) ?: [], fn($l) => !str_contains($l, $sfx));
            $lines ? file_put_contents($log, implode('', $lines), LOCK_EX) : @unlink($log);
        }
        if (is_dir(MediaPools::dir('_removed')) && !array_diff(scandir(MediaPools::dir('_removed')) ?: [], ['.', '..'])) @rmdir(MediaPools::dir('_removed'));
        MediaPools::forget();
        Shared::flush();
        Tables::flush();
    }
}
