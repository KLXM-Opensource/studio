<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/**
 * Typisiertes Ereignis des Cores für Erweiterungen (Extension::on, Extensions::emit) – unveränderlich.
 *
 *   $x->on(PageSaved::class, fn(PageSaved $e) => Log::write($e->id, $e->state));
 *   $x->on('page.saved', fn(array $page, ?int $userId) => …);   // Altform: Name + bisherige Argumente (legacyArgs())
 *   $x->on('page.saved', fn(PageSaved $e) => …);                 // Name als Alias, Ereignis-Objekt (am Typ erkannt)
 *
 * Gemeinsame Angaben (readonly): $table (Seiten: 'pages', Einträge: Kurzname der Tabelle), $id, $lang (Sprache, nie null),
 * $userId (wer – null = System, Kommandozeile ohne Konto), $state ('draft' = Arbeitsstand, 'live' = veröffentlichte Fassung).
 * Array-Zugriff liest Eigenschaften und – für alte Aufrufer – Felder des Datensatzes: $e['id'], $e['title'] (nur lesen).
 */
abstract class Event implements \ArrayAccess
{
    /** Name des Ereignisses (Alias für Extension::on) */
    public const NAME = '';

    /** Alle typisierten Ereignisse: Name → Klasse */
    public const ALL = [
        'page.saved' => PageSaved::class, 'page.published' => PagePublished::class, 'page.unpublished' => PageUnpublished::class,
        'page.discarded' => PageDiscarded::class, 'page.deleted' => PageDeleted::class,
        'entry.saved' => EntrySaved::class, 'entry.published' => EntryPublished::class, 'entry.unpublished' => EntryUnpublished::class,
        'entry.deleted' => EntryDeleted::class,
    ];

    public readonly ?int $userId;

    public function __construct(?int $userId = null)
    {
        $this->userId = $userId ?? self::currentUser();
    }

    public function name(): string
    {
        return static::NAME;
    }

    /** Argumente der Altform (Listener mit dem Namen statt der Klasse, z. B. fn(array $page, ?int $userId)) */
    abstract public function legacyArgs(): array;

    /** Datensatz (Seite bzw. Eintrag) für den Array-Zugriff alter Aufrufer */
    abstract protected function record(): array;

    /** Klasse zu einem Namen ('page.saved' → PageSaved::class) bzw. null */
    public static function classFor(string $name): ?string
    {
        if (isset(self::ALL[$name])) return self::ALL[$name];
        return is_subclass_of($name, self::class) ? $name : null;
    }

    private static function currentUser(): ?int
    {
        try {
            $id = app()->auth->user()['id'] ?? null;
            return $id ? (int) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, (string) $offset) || array_key_exists((string) $offset, $this->record());
    }

    public function offsetGet(mixed $offset): mixed
    {
        return property_exists($this, (string) $offset) ? $this->{(string) $offset} : ($this->record()[(string) $offset] ?? null);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Ereignisse sind unveränderlich (' . static::class . ').');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Ereignisse sind unveränderlich (' . static::class . ').');
    }
}
