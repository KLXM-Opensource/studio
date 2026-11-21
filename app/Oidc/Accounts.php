<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Oidc;

use Core\Database;
use Core\Network\Network;

/**
 * Konten aus einer Anmeldung per OpenID Connect.
 *
 *  - Zuordnung nur über Issuer + „sub“ (Tabelle user_identities) – nie allein über die E-Mail-Adresse. Ein bestehendes Konto
 *    mit eigenem Passwort wird nie übernommen. Verknüpft wird per E-Mail nur ein vorab freigegebenes Konto desselben Anbieters
 *    (users.idp gesetzt, noch ohne Verknüpfung, `php bin/console oidc:allow <email>`), und nur mit email_verified.
 *  - Konten des Anbieters (users.idp) haben kein lokales Passwort ('!oidc' ist kein gültiger Hash), keine eigenen Passkeys
 *    und keine eigene Zwei-Faktor-Einrichtung; Sperren (disabled) und Löschen bleiben lokal möglich.
 *  - Rolle „network“: Konto in der Netzwerk-Datenbank (Netzwerk-Konto) – danach Einmal-Anmeldung auf allen Websites.
 */
final class Accounts
{
    public const TABLE = 'user_identities';
    public const NO_PASSWORD = '!oidc';

    public static function ensureTable(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::TABLE . " (id $pk, user_id INT NOT NULL, provider VARCHAR(20) NOT NULL,
            issuer VARCHAR(191) NOT NULL, subject VARCHAR(191) NOT NULL, email VARCHAR(191) NULL, created_at VARCHAR(25) NULL,
            last_login VARCHAR(25) NULL)$tail");
        if ($my) {
            try { $db->pdo->exec('CREATE UNIQUE INDEX user_identities_sub ON ' . self::TABLE . ' (issuer, subject)'); } catch (\Throwable) {}
        } else {
            $db->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS user_identities_sub ON ' . self::TABLE . ' (issuer, subject)');
        }
    }

    /** Konto wird vom Anbieter verwaltet (keine lokalen Anmeldedaten) */
    public static function managed(?array $u): bool
    {
        return $u !== null && trim((string) ($u['idp'] ?? '')) !== '';
    }

    /** Zeile eines Kontos mit Spalte idp (Auth::user() liest sie mit) */
    public static function managedId(int $uid, ?Database $db = null): bool
    {
        try {
            return self::managed(($db ?? app()->db)->fetch('SELECT idp FROM users WHERE id = ?', [$uid]));
        } catch (\Throwable) {
            return false;
        }
    }

    /** Darf sich diese Identität anmelden? (Bedingungen des Anbieters) @throws OidcException */
    public static function admissible(array $p, array $c): string
    {
        if ($p['mfa'] !== 'trust' && !Client::strongAmr($c)) {
            throw new OidcException(__('Der Anbieter hat keine Anmeldung mit zweitem Faktor bestätigt. Bitte melden Sie sich dort mit Passkey oder Code an.'));
        }
        if ($p['claim'] !== '') {
            $v = $c[$p['claim']] ?? null;
            $have = array_map('strval', is_array($v) ? $v : ($v === null || $v === false || $v === '' ? [] : [(string) $v]));
            if ($p['claim_values'] ? !array_intersect($have, $p['claim_values']) : !$have) {
                throw new OidcException(__('Ihr Konto beim Anbieter ist für diese Website nicht freigegeben.'));
            }
        }
        $email = strtolower(trim((string) ($c['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
            throw new OidcException(__('Der Anbieter hat keine gültige E-Mail-Adresse übermittelt (Scope „email“).'));
        }
        if (array_key_exists('email_verified', $c) && $c['email_verified'] !== true && $c['email_verified'] !== 'true') {
            throw new OidcException(__('Die E-Mail-Adresse ist beim Anbieter nicht bestätigt.'));
        }
        if ($p['domains'] && !in_array(substr(strrchr($email, '@') ?: '', 1), $p['domains'], true)) {
            throw new OidcException(__('Diese E-Mail-Adresse ist für die Anmeldung über den Anbieter nicht zugelassen.'));
        }
        return $email;
    }

    /**
     * Konto zur Identität finden bzw. anlegen. @return array Zeile aus users (Netzwerk-Datenbank bei Rolle network)
     * @throws OidcException
     */
    public static function resolve(array $p, array $c): array
    {
        $email = self::admissible($p, $c);
        $network = $p['role'] === 'network';
        $db = $network ? Network::db() : app()->db;
        self::ensureTable($db);
        $iss = $p['issuer'];
        $sub = (string) $c['sub'];
        $name = mb_substr(trim((string) ($c['name'] ?? trim(($c['given_name'] ?? '') . ' ' . ($c['family_name'] ?? '')))), 0, 120);
        $link = $db->fetch('SELECT * FROM ' . self::TABLE . ' WHERE issuer = ? AND subject = ?', [$iss, $sub]);
        $row = $link ? $db->fetch('SELECT * FROM users WHERE id = ?', [(int) $link['user_id']]) : null;
        if ($link && !$row) {
            $db->query('DELETE FROM ' . self::TABLE . ' WHERE id = ?', [(int) $link['id']]);   // Konto wurde gelöscht → neu anlegen bzw. abweisen
            $link = null;
        }
        if (!$row) {
            $byMail = $db->fetch('SELECT * FROM users WHERE LOWER(email) = ?', [$email]);
            if ($byMail) {
                $linked = (int) $db->fetchValue('SELECT COUNT(*) FROM ' . self::TABLE . ' WHERE user_id = ?', [(int) $byMail['id']]);
                // Nur ein vorab freigegebenes Konto desselben Anbieters ohne Verknüpfung – nie ein Konto mit eigenem Passwort
                if ((string) ($byMail['idp'] ?? '') !== $p['id'] || $linked || (string) $byMail['password_hash'] !== self::NO_PASSWORD
                    || ($network && $byMail['role'] !== 'network')) {
                    throw new OidcException(__('Für diese E-Mail-Adresse gibt es hier bereits ein Konto mit eigener Anmeldung. Bitte melden Sie sich damit an oder bitten Sie die Administration, es zu entfernen.'));
                }
                $row = $byMail;
            } elseif (!$p['jit']) {
                throw new OidcException(__('Für Ihr Konto ist hier noch kein Zugang freigegeben.'));
            } else {
                $id = $db->insert('users', ['email' => $email, 'name' => $name, 'password_hash' => self::NO_PASSWORD, 'role' => $p['role'],
                    'idp' => $p['id'], 'created_at' => now()]);
                $row = $db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
                \Core\SecurityLog::write('oidc-account');   // Nachvollziehbarkeit; Integritätsprüfung zeigt das Konto als Info (Core\Integrity::oidcAccount)
                if ($network) Network::log('account.create', null, $email, 'oidc');
                elseif (\Core\Extensions::listens('user.created')) \Core\Extensions::emit(new \Core\Events\UserCreated($id, $email, (string) $p['role'], 'oidc'));
            }
            $db->insert(self::TABLE, ['user_id' => (int) $row['id'], 'provider' => $p['id'], 'issuer' => $iss, 'subject' => $sub, 'email' => $email, 'created_at' => now()]);
        }
        if (!$row) throw new OidcException(__('Das Konto konnte nicht angelegt werden.'));
        if ((int) ($row['disabled'] ?? 0)) throw new OidcException(__('Dieses Konto ist gesperrt.'));
        if (!self::managed($row) || ($network && $row['role'] !== 'network')) {
            throw new OidcException(__('Dieses Konto wird hier lokal verwaltet und kann sich nicht über den Anbieter anmelden.'));
        }
        // Name und Adresse folgen dem Anbieter (Adresse nur, wenn sie hier frei ist)
        $upd = [];
        if ($name !== '' && $name !== (string) $row['name']) $upd['name'] = $name;
        if ($email !== strtolower((string) $row['email']) && !$db->fetchValue('SELECT COUNT(*) FROM users WHERE LOWER(email) = ? AND id != ?', [$email, (int) $row['id']])) $upd['email'] = $email;
        if ($upd) $db->update('users', $upd, 'id = :id', ['id' => (int) $row['id']]);
        $db->query('UPDATE ' . self::TABLE . ' SET last_login = ?, email = ? WHERE issuer = ? AND subject = ?', [now(), $email, $iss, $sub]);
        return array_replace($row, $upd);
    }

    /** Konto vorab freigeben (ohne Just-in-time-Anlage): Platzhalter mit idp, verknüpft sich bei der ersten Anmeldung */
    public static function allow(array $p, string $email, string $name = ''): int
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException(__('Ungültige E-Mail-Adresse.'));
        $db = $p['role'] === 'network' ? Network::db() : app()->db;
        if ($db->fetchValue('SELECT COUNT(*) FROM users WHERE LOWER(email) = ?', [$email])) throw new \InvalidArgumentException(__('Diese E-Mail-Adresse hat hier bereits ein Konto.'));
        return $db->insert('users', ['email' => $email, 'name' => $name, 'password_hash' => self::NO_PASSWORD, 'role' => $p['role'], 'idp' => $p['id'], 'created_at' => now()]);
    }

    /** Zugang entziehen: Verknüpfungen lösen und sperren (Sitzungen enden über auth_ver) */
    public static function revoke(Database $db, int $uid): void
    {
        $db->query('DELETE FROM ' . self::TABLE . ' WHERE user_id = ?', [$uid]);
        $db->query('UPDATE users SET disabled = 1, auth_ver = auth_ver + 1 WHERE id = ? AND idp IS NOT NULL', [$uid]);
    }
}
