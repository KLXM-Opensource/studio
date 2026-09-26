<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * Ergebnis eines Abschnitts (SPF, DMARC, …) in einer allgemeinen Form, die das Skript ohne eigene Texte darstellt:
 *   status   ok | info | warn | error (schlechtester Befund, sofern nicht gesetzt)
 *   score    0–100 (Fehler −25, Warnung −8; für die Gesamtbewertung)
 *   summary  ein Satz
 *   findings [{level, text}]  – Befunde mit Empfehlung
 *   blocks   [{type: kv|table|code|list|chain, …}] – Details
 * Alle Texte sind bereits in der Sprache der Seite (lt()) und werden im Browser nur als Text eingesetzt (kein HTML).
 */
final class Result
{
    public const RANK = ['ok' => 0, 'info' => 1, 'warn' => 2, 'error' => 3];

    public ?string $status = null;
    public string $summary = '';
    public array $findings = [];
    public array $blocks = [];
    public array $data = [];

    public function __construct(public readonly string $section, public string $title = '') {}

    public function ok(string $text): self { return $this->add('ok', $text); }
    public function info(string $text): self { return $this->add('info', $text); }
    public function warn(string $text): self { return $this->add('warn', $text); }
    public function error(string $text): self { return $this->add('error', $text); }

    public function add(string $level, string $text): self
    {
        $this->findings[] = ['level' => isset(self::RANK[$level]) ? $level : 'info', 'text' => $text];
        return $this;
    }

    /** Tabelle „Bezeichnung: Wert“ – Zeilen [label, value, level?] */
    public function kv(string $title, array $rows): self
    {
        $rows = array_values(array_filter($rows, fn($r) => is_array($r) && ($r[1] ?? '') !== '' && ($r[1] ?? null) !== null));
        if ($rows) $this->blocks[] = ['type' => 'kv', 'title' => $title, 'rows' => array_map(fn($r) => [(string) $r[0], (string) $r[1], $r[2] ?? null], $rows)];
        return $this;
    }

    /** Tabelle mit Kopfzeile; Zellen als Text, optional [text, level] */
    public function table(string $title, array $head, array $rows, string $caption = ''): self
    {
        if ($rows) $this->blocks[] = ['type' => 'table', 'title' => $title, 'head' => $head, 'rows' => $rows, 'caption' => $caption];
        return $this;
    }

    /** Eintrag wörtlich (mit Kopier-Knopf) */
    public function code(string $title, string $text, bool $copy = true): self
    {
        if ($text !== '') $this->blocks[] = ['type' => 'code', 'title' => $title, 'text' => $text, 'copy' => $copy];
        return $this;
    }

    /** Aufzählung (z. B. Weiterleitungskette): Einträge [text, level?] */
    public function list(string $title, array $items, bool $ordered = false): self
    {
        if ($items) $this->blocks[] = ['type' => 'list', 'title' => $title, 'ordered' => $ordered, 'items' => array_map(fn($i) => is_array($i) ? [(string) $i[0], $i[1] ?? null] : [(string) $i, null], $items)];
        return $this;
    }

    public function worst(): string
    {
        $w = 'ok';
        foreach ($this->findings as $f) if (self::RANK[$f['level']] > self::RANK[$w]) $w = $f['level'];
        return $w;
    }

    public function score(): int
    {
        $s = 100;
        foreach ($this->findings as $f) $s -= match ($f['level']) { 'error' => 25, 'warn' => 8, default => 0 };
        // Ein Fehler drückt den Abschnitt deutlich, eine Warnung begrenzt ihn – damit die Gesamtnote Probleme nicht „wegmittelt“
        $w = $this->status ?? $this->worst();
        if ($w === 'error') $s = min($s, 40);
        elseif ($w === 'warn') $s = min($s, 80);
        return max(0, $s);
    }

    public function toArray(): array
    {
        // Befunde: Fehler zuerst, dann Warnungen, Hinweise, Positives
        $order = ['error' => 0, 'warn' => 1, 'info' => 2, 'ok' => 3];
        $f = $this->findings;
        usort($f, fn($a, $b) => $order[$a['level']] <=> $order[$b['level']]);
        return ['ok' => true, 'section' => $this->section, 'title' => $this->title, 'status' => $this->status ?? $this->worst(),
            'score' => $this->score(), 'summary' => $this->summary, 'findings' => $f, 'blocks' => $this->blocks] + ($this->data ? ['data' => $this->data] : []);
    }
}
