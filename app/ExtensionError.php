<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Fehler beim Start einer Erweiterung – mit Name, Phase und schuldiger Datei, statt einer anonymen 500.
 *
 * Erweiterungen werden nicht still übersprungen: Ein Fehler im Manifest (extension.php), im 'boot', in den Datenbank-Schritten
 * oder in 'install' bleibt ein Fehler (die Erweiterung muss ihre optionalen Abhängigkeiten selbst prüfen – siehe
 * php bin/console extensions:check). Der Core sorgt nur dafür, dass klar ist, wer schuld ist:
 *  - Fehlerprotokoll: eine Zeile „[Erweiterung name] Start fehlgeschlagen (boot): Meldung – Datei: extensions/…/src/X.php:12“;
 *  - Kommandozeile: dieselbe Meldung plus Hinweis zum Abschalten (STDERR, Exit 1);
 *  - Website: Fehlerseite 500 „Die Erweiterung „name“ konnte nicht starten“; Details (Meldung, Datei) nur mit 'debug' bzw. in
 *    der Umgebung development – sonst stehen sie im Fehlerprotokoll;
 *  - Verwaltung: der letzte Fehler je Erweiterung (Extensions::failures) unter Funktionen & Erweiterungen und in extensions:list.
 */
final class ExtensionError extends \RuntimeException
{
    /** Phasen: manifest (extension.php laden), boot, migrate (Tabellen/Datenbank-Schritte), install, runtime (Route, Block …) */
    public function __construct(public readonly string $extension, public readonly string $phase, public readonly string $culprit, \Throwable $previous)
    {
        parent::__construct(self::describe($extension, $phase, self::clean($previous->getMessage()), $culprit), 0, $previous);
    }

    /** Fehler einer Erweiterung einpacken: schuldige Datei = erste Stelle im Ordner der Erweiterung (sonst die Fehlerstelle) */
    public static function wrap(string $extension, string $dir, string $phase, \Throwable $e): self
    {
        return $e instanceof self ? $e : new self($extension, $phase, self::culprit($e, $dir), $e);
    }

    /** „datei:zeile“ relativ zur Installation – bevorzugt im Ordner $dir (Fehlerstelle, sonst erster Aufruf aus diesem Ordner) */
    public static function culprit(\Throwable $e, string $dir = ''): string
    {
        $dir = rtrim($dir, '/');
        $frames = array_merge([['file' => $e->getFile(), 'line' => $e->getLine()]], $e->getTrace());
        foreach ($frames as $f) {
            $file = (string) ($f['file'] ?? '');
            if ($dir !== '' && $file !== '' && str_starts_with(self::real($file), self::real($dir) . '/')) {
                return self::rel($dir) . substr(self::real($file), strlen(self::real($dir))) . ':' . (int) ($f['line'] ?? 0);   // auch bei verlinktem Ordner
            }
        }
        return self::rel($e->getFile()) . ':' . $e->getLine();
    }

    /** Welche aktive bzw. installierte Erweiterung steckt hinter einem Fehler? [name, dir] oder null */
    public static function owner(\Throwable $e): ?array
    {
        if ($e instanceof self) return [$e->extension, ''];
        $dirs = [];
        foreach (Extensions::available() as $n => $m) if (!empty($m['dir'])) $dirs[$n] = self::real((string) $m['dir']);
        $frames = array_merge([['file' => $e->getFile()]], $e->getTrace());
        foreach ($frames as $f) {
            $file = self::real((string) ($f['file'] ?? ''));
            if ($file === '') continue;
            foreach ($dirs as $n => $d) if (str_starts_with($file, $d . '/')) return [$n, $d];
        }
        return null;
    }

    public static function describe(string $extension, string $phase, string $message, string $culprit): string
    {
        $what = match ($phase) {
            'manifest' => __('Manifest (extension.php) ließ sich nicht laden'),
            'boot' => __('Start (boot) fehlgeschlagen'),
            'migrate' => __('Datenbank-Schritte fehlgeschlagen'),
            'install' => __('Einrichtung (install) fehlgeschlagen'),
            'runtime' => __('Fehler zur Laufzeit'),
            default => __('Fehler'),
        };
        return __('Erweiterung „{name}“: {what}: {msg} – Datei: {file}', ['name' => $extension, 'what' => $what, 'msg' => $message, 'file' => $culprit]);
    }

    /** Hinweis zum Abschalten, bis der Fehler behoben ist */
    public function hint(): string
    {
        return __('Abschalten, bis der Fehler behoben ist: in der Konfiguration der Website \'extensions\' => [\'{name}\' => false] – bzw. Administration → Funktionen & Erweiterungen. Optionale Pakete prüft php bin/console extensions:check.', ['name' => $this->extension]);
    }

    /** Ausgabe, wenn der Start der Anwendung an einer Erweiterung scheitert (bootstrap.php) – beendet die Anfrage */
    public function render(bool $details): never
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, $this->getMessage() . "\n" . $this->hint() . "\n");
            exit(1);
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
        }
        $h = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
            . '<title>' . $h(__('Die Website ist gerade nicht erreichbar')) . '</title><body><main><h1>' . $h(__('Die Website ist gerade nicht erreichbar')) . '</h1>'
            . '<p>' . $h(__('Die Erweiterung „{name}“ konnte nicht starten. Details stehen im Fehlerprotokoll der Installation.', ['name' => $this->extension])) . '</p>'
            . ($details ? '<p><code>' . $h($this->getMessage()) . '</code></p><p>' . $h($this->hint()) . '</p>' : '')
            . '</main></body></html>';
        exit(1);
    }

    /** Meldung ohne Pfad der Installation, gekürzt; Zugangsdaten in Adressen (user:pass@) unkenntlich */
    public static function clean(string $msg): string
    {
        $msg = str_replace(ROOT . '/', '', $msg);
        $msg = (string) preg_replace('~(://[^:/@\s]+):[^@\s]+@~', '$1:***@', $msg);
        return mb_strimwidth(trim($msg), 0, 400, '…');
    }

    private static function rel(string $file): string
    {
        if (str_starts_with($file, ROOT . '/')) return substr($file, strlen(ROOT) + 1);
        $r = self::real($file);
        $root = self::real(ROOT);
        return str_starts_with($r, $root . '/') ? substr($r, strlen($root) + 1) : $file;
    }

    private static function real(string $p): string
    {
        return $p === '' ? '' : ((string) realpath($p) ?: $p);
    }
}
