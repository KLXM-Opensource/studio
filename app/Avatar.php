<?php
declare(strict_types=1);

namespace Core;

/**
 * Profilbilder der Verwaltung (Konto → Profilbild).
 *  - Gespeichert installationsweit je E-Mail-Adresse: storage/avatars/{sha256(email), 32 Zeichen}.webp, 256 × 256 px,
 *    mittig quadratisch zugeschnitten, EXIF-Drehung berücksichtigt. Netzwerk-Konten haben so auf allen Websites dasselbe Bild.
 *  - Ausgeliefert nur mit Anmeldung über /admin/avatar/{user-id}?v={zeitstempel} (privat, lange cachebar; nie öffentlich).
 *  - Ohne Bild: Initialen auf einer Farbe aus der E-Mail-Adresse (html()).
 */
final class Avatar
{
    public const SIZE = 256;
    public const MAX_BYTES = 8 * 1024 * 1024;
    private const TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    /** Anzahl Farbverläufe der Initialen (admin.css .adm-ava--c0 … c7) */
    private const COLORS = 8;

    private static function dir(): string
    {
        return ROOT . '/storage/avatars';
    }

    public static function path(string $email): string
    {
        return self::dir() . '/' . substr(hash('sha256', strtolower(trim($email))), 0, 32) . '.webp';
    }

    /** Zeitstempel des Bildes (0 = keins) */
    public static function version(array $user): int
    {
        $f = self::path((string) ($user['email'] ?? ''));
        return is_file($f) ? (int) filemtime($f) : 0;
    }

    public static function url(array $user): ?string
    {
        $v = self::version($user);
        return $v ? url('/admin/avatar/' . (int) $user['id']) . '?v=' . $v : null;
    }

    public static function initials(array $user): string
    {
        $n = trim((string) ($user['name'] ?? ''));
        if ($n === '') $n = (string) strtok((string) ($user['email'] ?? '?'), '@');
        $parts = preg_split('~[\s.\-_]+~u', $n, -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $s = mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '');
        return mb_strtoupper($s);
    }

    /** Bild oder Initialen; $cls z. B. 'adm-ava adm-ava--s' */
    public static function html(array $user, string $cls = 'adm-ava'): string
    {
        if ($u = self::url($user)) {
            return '<img class="' . e($cls) . '" src="' . e($u) . '" alt="" width="' . self::SIZE . '" height="' . self::SIZE . '" decoding="async">';
        }
        // Farbe fest je Person (aus der E-Mail-Adresse), Verlauf per Klasse – CSP erlaubt keine Inline-Stile
        $c = hexdec(substr(md5(strtolower((string) ($user['email'] ?? ''))), 0, 4)) % self::COLORS;
        return '<span class="' . e($cls) . ' adm-ava--ini adm-ava--c' . $c . '" aria-hidden="true">' . e(self::initials($user)) . '</span>';
    }

    /** Hochgeladenes Bild übernehmen → null oder Fehlertext */
    public static function store(array $user, array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return __('Bitte ein Bild auswählen.');
        }
        if ((int) $file['size'] > self::MAX_BYTES) return __('Das Bild ist zu groß (höchstens 8 MB).');
        $tmp = (string) $file['tmp_name'];
        $info = @getimagesize($tmp);
        if (!$info || !in_array($info['mime'], self::TYPES, true)) return __('Bitte ein JPG-, PNG-, WebP- oder GIF-Bild wählen.');
        if ($info[0] * $info[1] > 40_000_000) return __('Das Bild hat zu viele Pixel.');
        $img = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png' => @imagecreatefrompng($tmp),
            'image/webp' => @imagecreatefromwebp($tmp),
            'image/gif' => @imagecreatefromgif($tmp),
        };
        if (!$img) return __('Das Bild lässt sich nicht lesen.');
        if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
            $o = (int) (@exif_read_data($tmp)['Orientation'] ?? 1);
            $img = match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img } ?: $img;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $s = min($w, $h);
        $out = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // transparente PNGs auf Weiß
        imagecopyresampled($out, $img, 0, 0, intdiv($w - $s, 2), intdiv($h - $s, 2), self::SIZE, self::SIZE, $s, $s);
        if (!is_dir(self::dir())) @mkdir(self::dir(), 0775, true);
        $f = self::path((string) $user['email']);
        $part = $f . '.' . bin2hex(random_bytes(4));
        if (!@imagewebp($out, $part, 85) || !@rename($part, $f)) {
            @unlink($part);
            return __('Das Bild konnte nicht gespeichert werden.');
        }
        return null;
    }

    public static function remove(array $user): void
    {
        @unlink(self::path((string) $user['email']));
    }
}
