<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * PHP-API-Referenz aus dem Code (Reflection): alle Klassen unter app/ (Namespace Core\) mit ihren öffentlichen Methoden, Signatur und
 * erster Zeile des Doc-Kommentars. Immer aktuell – Entwicklerhandbuch → PHP-API-Referenz und `php bin/console docs:api` (Markdown).
 * Klassen in PUBLIC sind die Schnittstellen für Erweiterungen und Kits; alles andere ist intern (kann sich ändern).
 */
final class ApiReference
{
    /** Schnittstellen für Erweiterungen und Kits (stabil) */
    public const PUBLIC = [
        'Core\\Extension', 'Core\\Extensions', 'Core\\Db\\Table', 'Core\\Database', 'Core\\Http\\Request', 'Core\\Http\\Response',
        'Core\\Http\\Router', 'Core\\AdminUi', 'Core\\Fields', 'Core\\Format', 'Core\\Charts', 'Core\\Pdf', 'Core\\Cron', 'Core\\Live', 'Core\\Media',
        'Core\\Pages', 'Core\\Data\\Entries', 'Core\\Data\\Tables', 'Core\\Features', 'Core\\Settings', 'Core\\Mailer', 'Core\\Sanitizer', 'Core\\Icons',
        'Core\\Theme', 'Core\\Kit', 'Core\\Lang', 'Core\\I18n', 'Core\\Csrf', 'Core\\Sites', 'Core\\Site', 'Core\\Push\\Push', 'Core\\AI\\Ai', 'Core\\Search\\Search',
    ];

    /** Gehört die Klasse zur stabilen Schnittstelle? (PUBLIC sowie alle typisierten Ereignisse unter Core\Events) */
    public static function isPublic(string $cls): bool
    {
        return in_array($cls, self::PUBLIC, true) || str_starts_with($cls, 'Core\\Events\\');
    }

    /** Gruppen nach Namespace: [Namespace => [Klassenname => ['doc' => …, 'kind' => class|interface|enum|trait, 'methods' => [[name, signature, doc, static]], 'consts' => [...]]]] */
    public static function build(bool $all = true): array
    {
        $out = [];
        foreach (self::classes() as $cls) {
            if (!$all && !self::isPublic($cls)) continue;
            try {
                $rc = new \ReflectionClass($cls);
            } catch (\Throwable) {
                continue;
            }
            if ($rc->isAnonymous()) continue;
            $methods = [];
            foreach ($rc->getMethods(\ReflectionMethod::IS_PUBLIC) as $m) {
                if ($m->getDeclaringClass()->getName() !== $cls || str_starts_with($m->getName(), '__') && $m->getName() !== '__construct') continue;
                $methods[] = ['name' => $m->getName(), 'sig' => self::signature($m), 'doc' => self::summary((string) $m->getDocComment()), 'static' => $m->isStatic()];
            }
            usort($methods, fn($a, $b) => strcmp($a['name'], $b['name']));
            $consts = [];
            foreach ($rc->getReflectionConstants() as $c) {
                if ($c->isPublic() && $c->getDeclaringClass()->getName() === $cls) $consts[] = $c->getName();
            }
            $ns = $rc->getNamespaceName();
            $out[$ns][$cls] = ['short' => $rc->getShortName(), 'doc' => self::summary((string) $rc->getDocComment(), 3),
                'kind' => $rc->isInterface() ? 'interface' : ($rc->isEnum() ? 'enum' : ($rc->isTrait() ? 'trait' : 'class')),
                'file' => str_replace(ROOT . '/', '', (string) $rc->getFileName()), 'methods' => $methods, 'consts' => $consts,
                'public' => self::isPublic($cls)];
        }
        ksort($out);
        foreach ($out as &$list) ksort($list);
        return $out;
    }

    /** Alle Klassennamen unter app/ (PSR-4 Core\ → app/), ohne Ansichten und Block-Vorlagen */
    public static function classes(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(ROOT . '/app', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            $rel = substr($f->getPathname(), strlen(ROOT . '/app/'));
            if (!str_ends_with($rel, '.php') || preg_match('~^(Admin/views|Views|Blocks/[a-z_]+\.php$)~', $rel) || $rel === 'helpers.php' || $rel === 'bootstrap.php' || $rel === 'routes.php') continue;
            if (!preg_match('~^[A-Z][A-Za-z0-9/]*\.php$~', $rel)) continue;   // Klassen: Dateiname = Klassenname
            $cls = 'Core\\' . str_replace('/', '\\', substr($rel, 0, -4));
            if (class_exists($cls) || interface_exists($cls) || enum_exists($cls) || trait_exists($cls)) $out[] = $cls;
        }
        sort($out);
        return $out;
    }

    /** Signatur „name(Type $a, ?int $b = 5): Rückgabe“ */
    public static function signature(\ReflectionMethod|\ReflectionFunction $m): string
    {
        $ps = [];
        foreach ($m->getParameters() as $p) {
            $s = ($p->hasType() ? self::type($p->getType()) . ' ' : '') . ($p->isPassedByReference() ? '&' : '') . ($p->isVariadic() ? '...' : '') . '$' . $p->getName();
            if ($p->isDefaultValueAvailable()) {
                try {
                    $s .= ' = ' . ($p->isDefaultValueConstant() ? (string) $p->getDefaultValueConstantName() : self::value($p->getDefaultValue()));
                } catch (\Throwable) {
                }
            }
            $ps[] = $s;
        }
        return $m->getName() . '(' . implode(', ', $ps) . ')' . ($m->hasReturnType() ? ': ' . self::type($m->getReturnType()) : '');
    }

    private static function type(?\ReflectionType $t): string
    {
        return $t === null ? '' : preg_replace('~\\\\?Core\\\\~', '', (string) $t);
    }

    private static function value(mixed $v): string
    {
        return match (true) {
            $v === null => 'null', is_bool($v) => $v ? 'true' : 'false', is_array($v) => $v === [] ? '[]' : '[…]',
            is_string($v) => "'" . (mb_strlen($v) > 24 ? mb_substr($v, 0, 24) . '…' : $v) . "'", default => (string) $v,
        };
    }

    /** Erste Absatz-Zeile(n) eines Doc-Kommentars (ohne @-Angaben) */
    public static function summary(string $doc, int $lines = 1): string
    {
        if ($doc === '') return '';
        $text = [];
        foreach (preg_split('~\R~', (string) preg_replace('~^/\*\*|\*/$~', '', trim($doc))) ?: [] as $l) {
            $l = trim((string) preg_replace('~^\s*\*\s?~', '', $l));
            if (str_starts_with($l, '@')) break;
            if ($l === '') { if ($text) break; continue; }
            $text[] = $l;
            if (count($text) >= $lines) break;
        }
        return implode(' ', $text);
    }

    /** Markdown für docs/php-api.md (GitHub) */
    public static function markdown(): string
    {
        $data = self::build();
        $n = array_sum(array_map('count', $data));
        $md = "# PHP-API-Referenz – KLXM Studio " . CMS_VERSION . "\n\n"
            . "Automatisch erzeugt aus dem Code (`php bin/console docs:api`) – nicht von Hand bearbeiten. {$n} Klassen.\n"
            . "Schnittstellen für Erweiterungen und Kits sind mit **API** markiert; alles andere ist intern und kann sich ändern.\n"
            . "Weitere Schnittstellen: REST-API (OpenAPI unter `/api/v1/openapi.json`), MCP-Server, Kommandozeile – Entwicklerhandbuch in der Verwaltung.\n\n";
        foreach ($data as $ns => $list) {
            $md .= "## {$ns}\n\n";
            foreach ($list as $cls => $c) {
                $md .= "### " . ($c['public'] ? '**API** ' : '') . "`{$c['short']}` ({$c['kind']}, `{$c['file']}`)\n\n" . ($c['doc'] !== '' ? $c['doc'] . "\n\n" : '');
                if ($c['consts']) $md .= 'Konstanten: ' . implode(', ', array_map(fn($k) => "`{$k}`", $c['consts'])) . "\n\n";
                foreach ($c['methods'] as $m) $md .= '- `' . ($m['static'] ? 'static ' : '') . $m['sig'] . '`' . ($m['doc'] !== '' ? ' – ' . $m['doc'] : '') . "\n";
                $md .= "\n";
            }
        }
        return $md;
    }
}
