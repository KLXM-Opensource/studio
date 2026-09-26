<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/** Zwischenstand der rekursiven Auswertung */
final class SpfWalk
{
    public int $lookups = 0;
    public int $voids = 0;
    public array $nodes = [];
    public array $errors = [];
    public array $warnings = [];
    public array $visited = [];
    public ?string $all = null;
    public string $record = '';
    /** @var list<array{0: string, 1: int, 2: int, 3: string}> [ip, cidr, depth, term] */
    public array $nets = [];

    public function addNet(string $ip, ?int $cidr, int $depth, string $term): void
    {
        if (count($this->nets) < 500) $this->nets[] = [$ip, $cidr ?? (str_contains($ip, ':') ? 128 : 32), $depth, $term];
    }
}
