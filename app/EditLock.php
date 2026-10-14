<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Bearbeitungssperre (Grundeinstellung sys.edit_lock, Standard aus; braucht die Präsenz sys.presence).
 *
 * Gesperrt hält, wer ein Objekt (Seite „page:{id}“, Eintrag „entry:{tabelle}:{id}“) am längsten ohne Unterbrechung bearbeitet –
 * abgeleitet aus Core\Presence (Herzschlag ~15 s, frei spätestens TTL Sekunden nach dem Schließen). Keine eigene Tabelle, keine
 * hängenden Sperren. Durchgesetzt in Pages::saveDraft/publish und Entries::save für jede handelnde Person (Verwaltung, Website,
 * KI, REST/MCP über setActor); ohne Person (Besucherformular, Abgleich, Kommandozeile) gilt keine Sperre.
 * Administratoren übernehmen mit take(): ihr Eintrag rückt vor den bisherigen Inhaber, der beim nächsten Herzschlag davon erfährt.
 */
final class EditLock
{
    /** Handelnde Person außerhalb einer Anmeldung (REST/MCP: Token-Nutzer; 0 = Token ohne Person → gilt immer als fremd) */
    private static ?int $actor = null;

    public static function on(): bool
    {
        try {
            return (bool) app()->settings->get('sys.edit_lock', false) && (bool) app()->settings->get('sys.presence', true);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function setActor(?int $userId): void
    {
        self::$actor = $userId;
    }

    /** Handelnde Person der REST-API/MCP (nur dort gesetzt) */
    public static function apiActor(): ?int
    {
        return self::$actor;
    }

    /** Wer handelt gerade? null = niemand (System) */
    public static function actor(): ?int
    {
        $u = isset(app()->auth) ? app()->auth->user() : null;
        return $u ? (int) $u['id'] : self::$actor;
    }

    /** Inhaber der Sperre (aktiver Präsenz-Eintrag mit dem frühesten „seit“) oder null */
    public static function holder(string $type, string $id): ?array
    {
        if (!self::on()) return null;
        $list = Presence::list(['site' => site()->key, 'type' => $type, 'id' => $id]);
        usort($list, fn($a, $b) => [(int) $a['since'], (string) $a['user']] <=> [(int) $b['since'], (string) $b['user']]);
        return $list[0] ?? null;
    }

    /** Sperrt jemand anderes das Objekt für diese Person? → Präsenz-Eintrag des Inhabers, sonst null */
    public static function blockedFor(string $type, string $id, ?int $userId = null): ?array
    {
        $userId ??= self::actor();
        if ($userId === null) return null;   // System (Abgleich, Besucherformular, Kommandozeile)
        $h = self::holder($type, $id);
        return $h && (string) $h['user'] !== (string) $userId ? $h : null;
    }

    /** Meldung für eine Sperre */
    public static function message(array $holder): string
    {
        return __('{name} bearbeitet das gerade – Speichern ist gesperrt, bis die Bearbeitung beendet ist.', ['name' => (string) ($holder['name'] ?? '?')]);
    }

    /** Speichern verhindern, wenn jemand anderes sperrt (HTTP 423) */
    public static function assert(string $type, string $id, ?int $userId): void
    {
        if ($userId === null) return;   // ohne Person (Zeitplan, Import, Kommandozeile)
        if ($h = self::blockedFor($type, $id, $userId)) throw new EditLocked(self::message($h), $h);
    }

    /** Darf die Person eine fremde Sperre übernehmen? (Administration) */
    public static function canTake(): bool
    {
        $u = app()->auth->user();
        return $u !== null && in_array((string) ($u['role'] ?? ''), ['admin', 'network'], true);
    }

    /** Sperre übernehmen: eigener Präsenz-Eintrag (muss bestehen) rückt vor den bisherigen Inhaber */
    public static function take(string $type, string $id, int $userId): bool
    {
        $h = self::holder($type, $id);
        if (!$h || (string) $h['user'] === (string) $userId) return false;
        Presence::setSince(['site' => site()->key, 'type' => $type, 'id' => $id, 'user' => (string) $userId], (int) $h['since'] - 1);
        Activity::log('lock_taken', $type, (int) (preg_replace('~^.*:~', '', $id)), (string) ($h['label'] ?? ''), ['from' => (string) ($h['name'] ?? '')]);
        return true;
    }

    /** Selbsttest (presence:selftest): Inhaber, Sperre beim Speichern, System ohne Sperre, Übernehmen, abgeschaltet – Transaktion wird zurückgerollt */
    public static function selftest(): array
    {
        $ok = 0; $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = 'Sperre – ' . $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $s = app()->settings;
        $prev = [$s->get('sys.edit_lock', false), $s->get('sys.presence', true)];
        $pdo = app()->db->pdo;
        $pdo->beginTransaction();
        $a = 990001; $b = 990002;
        $id = '';
        try {
            $s->set('sys.edit_lock', true); $s->set('sys.presence', true);
            $id = (string) Pages::create(['slug' => 'sperre-selbsttest', 'title' => 'Sperre', 'sort' => 9999]);
            $who = fn(int $u) => ['site' => site()->key, 'type' => 'page', 'id' => $id, 'user' => (string) $u];
            Presence::beat($who($a), ['name' => 'Anna'], time() - 30);
            Presence::beat($who($b), ['name' => 'Ben']);
            $eq('Inhaber = wer zuerst bearbeitet', (string) (self::holder('page', $id)['user'] ?? ''), (string) $a);
            $eq('für Ben gesperrt', (string) (self::blockedFor('page', $id, $b)['name'] ?? ''), 'Anna');
            $eq('für Anna frei', self::blockedFor('page', $id, $a), null);
            $eq('Speichern durch Ben abgelehnt (423)', (function () use ($id, $b) { try { Pages::saveDraft((int) $id, [], $b); return 0; } catch (EditLocked $e) { return $e->getCode(); } })(), 423);
            $eq('Speichern durch Anna erlaubt', (function () use ($id, $a) { try { Pages::saveDraft((int) $id, [], $a); return true; } catch (EditLocked) { return false; } })(), true);
            $eq('ohne Person (Zeitplan, Import) keine Sperre', (function () use ($id) { try { Pages::saveDraft((int) $id, [], null); return true; } catch (EditLocked) { return false; } })(), true);
            self::setActor(0);
            $eq('API-Token ohne Person gilt als fremd', self::blockedFor('page', $id, self::apiActor()) !== null, true);
            self::setActor(null);
            $eq('Übernehmen', self::take('page', $id, $b), true);
            $eq('nach Übernahme: Ben hält', (string) (self::holder('page', $id)['user'] ?? ''), (string) $b);
            $eq('nach Übernahme: Anna gesperrt', (string) (self::blockedFor('page', $id, $a)['name'] ?? ''), 'Ben');
            $s->set('sys.edit_lock', false);
            $eq('abgeschaltet: keine Sperre', self::blockedFor('page', $id, $a), null);
        } finally {
            if ($id !== '') foreach ([$a, $b] as $u) Presence::leave(['site' => site()->key, 'type' => 'page', 'id' => $id, 'user' => (string) $u]);
            $pdo->rollBack();
            $s->set('sys.edit_lock', $prev[0]); $s->set('sys.presence', $prev[1]);
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
