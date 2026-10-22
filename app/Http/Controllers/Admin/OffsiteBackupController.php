<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Backup\Offsite;
use Core\Backup\Vault;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Permissions;

/**
 * Grundeinstellungen → Sicherung außer Haus (/admin/system/sicherung, Core\Backup\Offsite) – nur wer die Installation verwaltet:
 * Ziele (WebDAV, SFTP, FTPS/FTP, Ordner) anlegen, prüfen, löschen; „Jetzt sichern“ (geplante Aufgabe backup.offsite in die
 * Warteschlange); Schlüssel der Sicherungen anzeigen.
 */
final class OffsiteBackupController extends AdminController
{
    private const BASE = '/admin/system/sicherung';

    private function guard(Request $r): array
    {
        $user = $this->auth($r, 'system.manage');
        if (!Permissions::installationAdmin()) throw new HttpException(403, __('Nur wer die Installation verwaltet, richtet die Sicherung außer Haus ein.'));
        return $user;
    }

    public function index(Request $r): Response
    {
        $this->guard($r);
        $edit = (string) ($r->query['ziel'] ?? '');
        return $this->view('system/sicherung', ['title' => __('Sicherung außer Haus'), 'targets' => Offsite::targets(), 'state' => Offsite::state(),
            'edit' => $edit === 'neu' ? [] : (Offsite::targets()[$edit] ?? null), 'editId' => $edit, 'local' => Offsite::latestLocal(),
            'fingerprint' => Vault::hasKey() ? Vault::fingerprint() : null, 'key' => null, 'errors' => []]);
    }

    public function save(Request $r): Response
    {
        $this->guard($r);
        $id = (string) ($r->post['id'] ?? '');
        $in = $r->post;
        foreach (['enabled', 'insecure', 'code'] as $b) $in[$b] = !empty($r->post[$b]);
        [$newId, $err] = Offsite::save($id !== '' && isset(Offsite::targets()[$id]) ? $id : null, $in);
        if ($err) {
            return $this->view('system/sicherung', ['title' => __('Sicherung außer Haus'), 'targets' => Offsite::targets(), 'state' => Offsite::state(),
                'edit' => $r->post, 'editId' => $id ?: 'neu', 'local' => Offsite::latestLocal(), 'fingerprint' => Vault::hasKey() ? Vault::fingerprint() : null,
                'key' => null, 'errors' => $err], 422);
        }
        Vault::key();   // Schlüssel spätestens jetzt anlegen, damit er sich vor der ersten Sicherung aufbewahren lässt
        $test = Offsite::transport(Offsite::targets()[$newId])->test();
        return $this->back(self::BASE, $test === null ? 'success' : 'error', $test === null
            ? __('Gespeichert – Verbindung geprüft.') : __('Gespeichert, aber die Verbindung klappt nicht: {msg}', ['msg' => $test]));
    }

    public function test(Request $r, string $id): Response
    {
        $this->guard($r);
        $t = Offsite::targets()[$id] ?? null;
        if (!$t) throw new HttpException(404);
        $err = Offsite::transport($t)->test();
        return $this->back(self::BASE, $err === null ? 'success' : 'error', $err === null ? __('Verbindung zu „{label}“ in Ordnung.', ['label' => $t['label']]) : $t['label'] . ': ' . $err);
    }

    public function delete(Request $r, string $id): Response
    {
        $this->guard($r);
        Offsite::remove($id);
        return $this->back(self::BASE, 'success', __('Ziel entfernt. Sicherungen am Ziel bleiben dort erhalten.'));
    }

    public function run(Request $r): Response
    {
        $user = $this->guard($r);
        if (!Offsite::enabled()) return $this->back(self::BASE, 'error', __('Kein eingeschaltetes Ziel.'));
        \Core\Cron::enqueue('backup.offsite', (string) ($user['email'] ?? ''));
        return $this->back(self::BASE, 'success', __('Sicherung gestartet – sie läuft im Hintergrund (Geplante Aufgaben) und ist meist in wenigen Minuten fertig.'));
    }

    /** Schlüssel anzeigen (POST, nur auf dieser Seite, nie in Protokollen) */
    public function key(Request $r): Response
    {
        $this->guard($r);
        return $this->view('system/sicherung', ['title' => __('Sicherung außer Haus'), 'targets' => Offsite::targets(), 'state' => Offsite::state(),
            'edit' => null, 'editId' => '', 'local' => Offsite::latestLocal(), 'fingerprint' => Vault::fingerprint(), 'key' => Vault::export(Vault::key()), 'errors' => []]);
    }
}
