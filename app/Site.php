<?php
declare(strict_types=1);

namespace Core;

/**
 * Eine Website der Installation (Multi-Site).
 *
 * Eine Installation = ein Code-Stand (app/, kits/, vendor/, public/assets) für beliebig viele Websites.
 * Jede Website hat eigene Domains, eine eigene Datenbank (SQLite-Datei oder eigene MySQL-Datenbank),
 * eigene Medien, eigene Benutzer, eigenen Cache und eigene Schlüssel – Inhalte sind vollständig getrennt.
 *
 * Konfiguration: config/sites/{key}.php (außerhalb des Webroots, nicht versioniert)
 *   return [
 *     'hosts'       => ['www.kunde.de', 'kunde.de'],   // Domains (optional mit :port für lokale Tests)
 *     'landing_hosts' => ['kampagne.de'],               // optional: Domains für Landingpages (Core\Landings, site:hosts --landing)
 *     'theme'       => 'praxis',                        // Kit beim Erststart (danach: Grundeinstellungen); 'kit' geht ebenso
 *     'themes'      => ['praxis'],                      // optional: erlaubte Kits dieser Website; 'kits' geht ebenso
 *     'app_key'     => '…', 'setup_token' => '…',      // eigene Schlüssel (legt site:create an)
 *     'db'          => [...],                           // optional, Standard: storage/sites/{key}/database/site.sqlite
 *   ] + beliebige Werte aus config/config.php, die für diese Website abweichen.
 *
 * Die Website „default“ nutzt die bisherigen Pfade (storage/, public/media) – bestehende
 * Einzel-Installationen laufen ohne Änderung weiter.
 */
final class Site
{
    public const DEFAULT = 'default';

    public function __construct(public readonly string $key, public readonly array $cfg = []) {}

    public function isDefault(): bool
    {
        return $this->key === self::DEFAULT;
    }

    /** @return list<string> */
    public function hosts(): array
    {
        return array_values(array_map('strtolower', (array) ($this->cfg['hosts'] ?? [])));
    }

    /** Weitere Domains für Landingpages (Core\Landings) – gehören zur Website, sind aber nicht ihre Hauptadresse */
    public function landingHosts(): array
    {
        return array_values(array_map('strtolower', (array) ($this->cfg['landing_hosts'] ?? [])));
    }

    public function label(): string
    {
        return (string) ($this->cfg['label'] ?? ($this->hosts()[0] ?? $this->key));
    }

    /** Beschreibbarer Datenordner (Datenbank, Seiten-Cache, Uploads, Logs) */
    public function storage(string $sub = ''): string
    {
        $base = $this->isDefault() ? ROOT . '/storage' : ROOT . '/storage/sites/' . $this->key;
        return $base . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }

    /** Öffentlicher Medienordner (Dateisystem) */
    public function mediaDir(string $sub = ''): string
    {
        $base = $this->isDefault() ? ROOT . '/public/media' : ROOT . '/public/sites/' . $this->key . '/media';
        return $base . ($sub !== '' ? '/' . ltrim($sub, '/') : '');
    }

    /** Öffentliche Adresse im Medienordner */
    public function mediaUrl(string $path = ''): string
    {
        $base = base_path() . ($this->isDefault() ? '/media' : '/sites/' . $this->key . '/media');
        return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }

    /** Theme beim Erststart bzw. wenn keines gewählt ist */
    public function defaultTheme(): string
    {
        $t = (string) (($this->cfg['kit'] ?? '') ?: ($this->cfg['theme'] ?? ''));   // 'kit' (neu) oder 'theme'
        $allowed = $this->allowedThemes();
        if ($t !== '' && isset($allowed[$t])) return $t;
        return (string) (array_key_first($allowed) ?? '');
    }

    /** Themes, die diese Website verwenden darf [name => Bezeichnung] */
    public function allowedThemes(): array
    {
        $all = Theme::available();
        $only = (array) (($this->cfg['kits'] ?? []) ?: ($this->cfg['themes'] ?? []));   // 'kits' (neu) oder 'themes'
        return $only ? array_intersect_key($all, array_flip($only)) : $all;
    }
}
