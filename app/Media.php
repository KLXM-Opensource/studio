<?php
declare(strict_types=1);

namespace Core;

/**
 * Medienverwaltung.
 *
 *  - Bilder werden mit GD neu kodiert (entfernt eingebetteten Schadcode/EXIF) und in
 *    480/800/1200/1600/2400 px als WebP (+ AVIF, falls verfügbar) erzeugt.
 *  - SVG (Funktion media.svg): nur bereinigt und optimiert gespeichert (Core\Svg), ohne Größen – Media::url() liefert immer die SVG.
 *  - Dateinamen sind zufällig, Endungen fest vorgegeben – in /public/media kann nie
 *    ausführbarer Code landen (wichtig, da wir auf .htaccess verzichten).
 *  - Alt-Text ist bei Bildern Pflicht, außer das Bild ist ausdrücklich „dekorativ“.
 *  - Dateien lassen sich ersetzen: ID, Alt-Text und alle Verwendungen bleiben erhalten.
 *  - Fokuspunkt (x/y in %) steuert den Bildausschnitt bei object-fit: cover.
 *  - Tags (Freitext) und Sammlungen zum Ordnen.
 *  - Bild anpassen (Effekte, Sättigung …): zerstörungsfrei per CSS-Klassen, Spalte adjust (Core\ImageFx).
 *  - Bild bearbeiten (Entzerren, Drehen, Spiegeln, Ausrichten, Zuschneiden): zerstörungsfrei, Spalte edit_json –
 *    bearbeitete Fassung und Größen werden aus dem unveränderten Original erzeugt (Core\ImageEdit, setEdit()).
 */
final class Media
{
    public const IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    public const FILE_MIMES = ['application/pdf' => 'pdf', 'video/mp4' => 'mp4', 'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a'];

    private static array $cache = [];
    /** Geteilter Pool, in dem gerade gearbeitet wird (null = Mediathek der Website) */
    private static ?string $pool = null;
    /** Prüf-Filter (Mediathek „Prüfen“, API/MCP) – Parameter von MISSING_LANG_SQL: JSON-Pfad '$."en".alt' */
    private const MISSING_LANG_SQL = "m.mime LIKE 'image/%' AND m.decorative = 0 AND COALESCE(json_extract(m.i18n, ?), '') IN ('', '\"\"')";
    // Dekorative Videos (Hintergrund-/Stimmungsvideo ohne Informationsgehalt) brauchen keine Untertitel
    private const NOCAPTIONS_SQL = "m.mime LIKE 'video/%' AND m.decorative = 0 AND NOT EXISTS (SELECT 1 FROM media_tracks t WHERE t.media_id = m.id AND t.status = 'published' AND t.kind IN ('subtitles', 'captions'))";
    private const NOTRANSCRIPT_SQL = "m.mime LIKE 'audio/%' AND (m.transcripts IS NULL OR m.transcripts NOT LIKE '%\"status\":\"published\"%')";

    /** Ab jetzt im Pool (bzw. mit null wieder in der Website) arbeiten – gilt für die laufende Anfrage */
    public static function usePool(?string $key): void
    {
        self::$pool = $key;
        self::$cache = [];
        MediaPools::forget();
    }

    public static function pool(): ?string
    {
        return self::$pool;
    }

    public static function db(): Database
    {
        return self::$pool !== null ? MediaPools::db(self::$pool) : app()->db;
    }

    public static function dir(): string
    {
        return self::$pool !== null ? MediaPools::mediaDir(self::$pool) : site()->mediaDir();
    }

    /** Absoluter Pfad der Originaldatei – auch für Verweise auf geteilte Pools (_pool aus find()) */
    public static function path(array $m): string
    {
        $pool = $m['_pool'] ?? self::$pool;
        return ($pool !== null ? MediaPools::mediaDir($pool) : site()->mediaDir()) . '/' . $m['file'];
    }

    /** Öffentliche Adresse einer Datei relativ zum Medienordner des Datensatzes (Website oder Pool) */
    private static function publicUrl(array $m, string $rel): string
    {
        $pool = $m['_pool'] ?? self::$pool;
        return $pool !== null ? MediaPools::url($pool, $rel) : site()->mediaUrl($rel);
    }

    public static function find(int $id): ?array
    {
        if (!array_key_exists($id, self::$cache)) {
            $m = self::db()->fetch('SELECT * FROM media WHERE id = ?', [$id]);
            if ($m && self::$pool !== null) {
                $m['_pool'] = self::$pool;
                $m['_pool_id'] = (int) $m['id'];
            } elseif ($m && !empty($m['pool_ref'])) {
                // Verweis auf eine Pool-Datei: Inhalte aus dem Pool, ID von der Website
                [$key, $pid] = explode(':', (string) $m['pool_ref'], 2) + [1 => 0];
                $row = MediaPools::row($key, (int) $pid);
                $m = $row ? ['id' => (int) $m['id'], '_pool' => $key, '_pool_id' => (int) $pid, 'pool_ref' => $m['pool_ref']] + $row : null;
            }
            self::$cache[$id] = $m;
        }
        return self::$cache[$id];
    }

    public static function forget(int $id): void
    {
        unset(self::$cache[$id]);
    }

    /** Erlaubte Nicht-Bild-Typen: FILE_MIMES plus Typen aktiver Erweiterungen (Extension::mediaTypes, z. B. video/webm) */
    public static function fileMimes(): array
    {
        return self::FILE_MIMES + array_map(fn($d) => (string) $d['ext'], Extensions::mediaTypes());
    }

    /**
     * Vorschaubild eines Videos für Themes/Player ohne eigenes Poster: gewähltes Poster einer Erweiterung
     * (Extension::mediaPoster, z. B. video_tools) vor dem automatischen Vorschaubild (Core\VideoThumbs, nur wenn schon
     * erzeugt). Das automatische ist ein Bild-Datensatz ohne ID ('id' => 0, '_auto_poster' => true) – für Media::url(),
     * Media::sources() und Media::pictureOf().
     */
    public static function posterFor(?array $m): ?array
    {
        if (!$m || !str_starts_with((string) ($m['mime'] ?? ''), 'video/')) return null;
        $id = Extensions::poster($m);
        $p = $id ? self::find($id) : null;
        return $p && str_starts_with((string) $p['mime'], 'image/') ? $p : self::autoPoster($m);
    }

    /** Automatisches Vorschaubild eines Videos als Bild-Datensatz (null = noch keins) */
    public static function autoPoster(?array $m): ?array
    {
        $p = $m && VideoThumbs::isVideo($m) ? VideoThumbs::data($m) : null;
        if (!$p) return null;
        $last = end($p['sizes']);
        return [
            'id' => 0, '_auto_poster' => true, '_pool' => $m['_pool'] ?? null, 'mime' => 'image/webp',
            'file' => 'cache/' . $p['base'] . '-' . (int) $last['w'] . '.webp',
            'variants_json' => json_encode(['base' => $p['base'], 'sizes' => $p['sizes']]),
            'width' => (int) $p['W'], 'height' => (int) $p['H'], 'alt' => '', 'decorative' => 1, 'title' => '',
            'original_name' => (string) ($m['original_name'] ?? ''), 'focus_x' => 50, 'focus_y' => 50, 'crops' => null, 'i18n' => null,
        ];
    }

    public static function kind(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            $mime === 'application/pdf' => 'pdf',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            default => 'file',
        };
    }

    /**
     * Mediathek mit Filtern.
     * Prüf-Filter (Seitenleiste „Prüfen“, API/MCP): noalt, missing_lang (Sprachkürzel: Bild ohne Alt-Text in dieser Sprache),
     * notitle, nocaptions (nicht dekorative Videos ohne veröffentlichte Untertitel), notranscript (Audio ohne veröffentlichtes Transkript),
     * check (Schlüssel eines Prüf-Filters einer Erweiterung, Extension::mediaChecks).
     * @param array{q?: string, kind?: string, tag?: string, collection?: int, noalt?: bool, missing_lang?: string, notitle?: bool, nocaptions?: bool, notranscript?: bool} $f
     */
    public static function all(string|array|null $f = null): array
    {
        if (!is_array($f)) {
            $f = $f === 'image' ? ['kind' => 'image'] : [];
        }
        $sql = 'SELECT m.* FROM media m';
        $where = [];
        $params = [];
        if (!empty($f['collection'])) {
            $sql .= ' JOIN media_collection_items ci ON ci.media_id = m.id AND ci.collection_id = ?';
            $params[] = (int) $f['collection'];
        }
        if (!empty($f['kind'])) {
            $where[] = match ($f['kind']) {
                'image' => "m.mime LIKE 'image/%'",
                'pdf' => "m.mime = 'application/pdf'",
                'video' => "m.mime LIKE 'video/%'",
                'audio' => "m.mime LIKE 'audio/%'",
                'none' => '0 = 1',
                default => '1=1',
            };
        }
        if (!empty($f['tag'])) {
            $where[] = 'm.tags LIKE ?';
            $params[] = '%,' . self::normTag((string) $f['tag']) . ',%';
        }
        if (!empty($f['q'])) {
            $where[] = '(m.original_name LIKE ? OR m.title LIKE ? OR m.alt LIKE ? OR m.tags LIKE ?)';
            $q = '%' . trim((string) $f['q']) . '%';
            array_push($params, $q, $q, $q, $q);
        }
        if (!empty($f['noalt'])) {
            $where[] = "m.mime LIKE 'image/%' AND (m.alt IS NULL OR m.alt = '') AND m.decorative = 0";
        }
        if (!empty($f['missing_lang']) && Lang::valid((string) $f['missing_lang']) && $f['missing_lang'] !== Lang::default()) {
            $where[] = self::MISSING_LANG_SQL;
            $params[] = '$."' . $f['missing_lang'] . '".alt';
        }
        if (!empty($f['notitle'])) {
            $where[] = "(m.title IS NULL OR m.title = '')";
        }
        if (!empty($f['nocaptions'])) {
            $where[] = self::NOCAPTIONS_SQL;
        }
        if (!empty($f['notranscript'])) {
            $where[] = self::NOTRANSCRIPT_SQL;
        }
        // Prüf-Filter aktiver Erweiterungen (Extension::mediaChecks) – SQL kommt aus der Erweiterung, nie aus der Anfrage
        if (!empty($f['check']) && ($c = Extensions::mediaChecks()[(string) $f['check']] ?? null)) {
            $where[] = '(' . $c['where'] . ')';
            array_push($params, ...$c['params']);
        }
        if (self::$pool === null) {
            $where[] = 'm.pool_ref IS NULL';   // Verweise auf Pool-Dateien erscheinen unter „Geteilt“
        }
        $sql .= ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . (!empty($f['collection']) ? ' ORDER BY ci.sort, m.id DESC' : ' ORDER BY m.id DESC');
        $rows = self::db()->fetchAll($sql, $params);
        if (self::$pool !== null) {
            foreach ($rows as &$r) { $r['_pool'] = self::$pool; $r['_pool_id'] = (int) $r['id']; }
        }
        return $rows;
    }

    // ================================================================= Upload / Import / Ersetzen

    /** @return array{0: ?array, 1: ?string} [Datensatz, Fehler] – einfacher Upload (Formular) */
    public static function upload(array $file, string $alt = '', array $opt = []): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
            return [null, 'Upload fehlgeschlagen (Code ' . ($file['error'] ?? '?') . ').'];
        }
        if ($file['size'] > self::maxBytes()) {
            return [null, 'Datei ist größer als ' . (int) app()->config->get('media.max_upload_mb', 50) . ' MB.'];
        }
        return self::import($file['tmp_name'], (string) ($file['name'] ?? 'datei'), $alt, $opt);
    }

    public static function maxBytes(): int
    {
        return (int) app()->config->get('media.max_upload_mb', 50) * 1024 * 1024;
    }

    /**
     * Importiert eine lokale Datei als neues Medium.
     * $opt: decorative (bool), title, credit, tags (string|array), collection (int), require_alt (bool, Standard true)
     * @return array{0: ?array, 1: ?string}
     */
    public static function import(string $path, string $originalName, string $alt = '', array $opt = []): array
    {
        [$data, $err] = self::process($path, $originalName, $alt, $opt);
        if ($err) {
            return [null, $err];
        }
        $id = self::db()->insert('media', $data + [
            'alt' => trim($alt), 'credit' => mb_substr(strip_tags((string) ($opt['credit'] ?? '')), 0, 250),
            'title' => mb_substr(strip_tags((string) ($opt['title'] ?? '')), 0, 180),
            'tags' => self::tagString($opt['tags'] ?? ''),
            'decorative' => !empty($opt['decorative']) ? 1 : 0,
            'focus_x' => 50, 'focus_y' => 50, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if (!empty($opt['collection'])) {
            self::addToCollection((int) $opt['collection'], [$id]);
        }
        PageCache::clear();
        Extensions::emit('media.imported', self::find($id));
        VideoThumbs::queue(self::find($id));   // Video: Vorschaubild nach der Antwort (nur mit ffmpeg)
        return [self::find($id), null];
    }

    /**
     * Ersetzt die Datei eines Mediums. ID, Alt-Text, Tags, Sammlungen und alle
     * Verwendungen bleiben erhalten. Bilder nur durch Bilder, PDFs nur durch PDFs.
     * @return array{0: ?array, 1: ?string}
     */
    public static function replace(int $id, string $path, string $originalName, array $opt = []): array
    {
        $old = self::find($id);
        if (!$old) {
            return [null, 'Medium nicht gefunden.'];
        }
        $newMime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (Svg::detect($path, $newMime, $originalName)) $newMime = Svg::MIME;   // .svg mit text/xml o. Ä.
        if (self::kind($newMime) !== self::kind($old['mime'])) {
            return [null, 'Bitte eine Datei der gleichen Art hochladen (' . self::typeLabel($old['mime']) . ').'];
        }
        $alt = array_key_exists('alt', $opt) ? (string) $opt['alt'] : (string) $old['alt'];
        [$data, $err] = self::process($path, $originalName, $alt, ['decorative' => $old['decorative']] + $opt);
        if ($err) {
            return [null, $err];
        }
        self::deleteFiles($old);
        $data['updated_at'] = now();
        $data['crops'] = null;          // neues Motiv → Zuschnitte neu festlegen
        $data['edit_json'] = null;      // … und nicht bearbeitet (Core\ImageEdit)
        $data['alt'] = trim($alt);
        if (!empty($opt['reset_focus'])) {
            $data['focus_x'] = 50;
            $data['focus_y'] = 50;
        }
        self::db()->update('media', $data, 'id = :id', ['id' => $id]);
        self::forget($id);
        PageCache::clear();
        Extensions::emit('media.replaced', self::find($id), $old);
        VideoThumbs::queue(self::find($id));   // neues Video → neues Vorschaubild
        return [self::find($id), null];
    }

    /** Prüft, konvertiert und speichert die Datei. Liefert Spalten für die DB. */
    private static function process(string $path, string $originalName, string $alt, array $opt): array
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
        if (filesize($path) > self::maxBytes()) {
            return [null, 'Datei ist zu groß (max. ' . (int) app()->config->get('media.max_upload_mb', 50) . ' MB).'];
        }
        $svg = Svg::detect($path, $mime, $originalName);
        if ($svg && !Features::on('media.svg', false)) {
            return [null, __('SVG-Uploads sind auf dieser Website ausgeschaltet (Funktionen & Erweiterungen → „SVG-Grafiken hochladen“).')];
        }
        if ((isset(self::IMAGE_MIMES[$mime]) || $svg) && ($opt['require_alt'] ?? true) && empty($opt['decorative']) && mb_strlen(trim($alt)) < 3) {
            return [null, 'Bitte einen Alt-Text (Bildbeschreibung) angeben oder das Bild als „dekorativ“ markieren.'];
        }
        $sub = date('Y/m');
        $dir = self::dir() . '/' . $sub;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $base = bin2hex(random_bytes(8));
        $width = $height = null;
        $variants = [];

        if ($svg) {
            // Nur die bereinigte Fassung wird gespeichert, nie das Original (Core\Svg)
            try {
                $r = Svg::clean((string) file_get_contents($path));
            } catch (\RuntimeException $e) {
                return [null, $e->getMessage()];
            }
            $rel = "$sub/$base.svg";
            if (file_put_contents(self::dir() . "/$rel", $r['svg']) === false) {
                return [null, 'Datei konnte nicht gespeichert werden.'];
            }
            Svg::guard(self::dir());
            [$width, $height, $mime] = [$r['width'], $r['height'], Svg::MIME];
            $variants = ['svg' => ['in' => $r['in'], 'out' => $r['out'], 'unsafe' => $r['unsafe']]];
        } elseif (isset(self::IMAGE_MIMES[$mime])) {
            $info = @getimagesize($path);
            if (!$info) {
                return [null, 'Bild konnte nicht gelesen werden.'];
            }
            if ($info[0] * $info[1] > 60_000_000) {
                return [null, 'Bild ist zu groß (max. 60 Megapixel).'];
            }
            $img = self::load($path, $mime);
            if (!$img) {
                return [null, 'Bildformat wird nicht unterstützt.'];
            }
            $img = self::orient($img, $path, $mime);
            $width = imagesx($img);
            $height = imagesy($img);
            $ext = $mime === 'image/png' || $mime === 'image/gif' ? 'png' : 'jpg';
            $orig = self::resize($img, min($width, 3200));
            $rel = "$sub/$base.$ext";
            $ext === 'png' ? imagepng($orig, self::dir() . "/$rel", 8) : imagejpeg($orig, self::dir() . "/$rel", 88);
            $variants = self::makeVariants($img, $base, $width);
            // Transparenter Rand (Logos, Freisteller): Standard „Einpassen“ im Rahmen (Core\ImageFit)
            if ($ext === 'png') $variants['alpha'] = ImageFit::edgeAlpha($img);
            $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
        } elseif (isset(self::fileMimes()[$mime])) {
            $head = (string) file_get_contents($path, false, null, 0, 12);
            // Typen von Erweiterungen: Dateianfang prüfen (z. B. WebM/Matroska: 1A 45 DF A3)
            $extType = Extensions::mediaTypes()[$mime] ?? null;
            if ($extType && !isset(self::FILE_MIMES[$mime]) && ($extType['magic'] ?? '') !== '' && !str_starts_with($head, (string) $extType['magic'])) {
                return [null, 'Ungültige Datei (' . ($extType['label'] ?? $mime) . ').'];
            }
            if ($mime === 'application/pdf' && !str_starts_with($head, '%PDF')) {
                return [null, 'Ungültige PDF-Datei.'];
            }
            if ($mime === 'video/mp4' && substr($head, 4, 4) !== 'ftyp') {
                return [null, 'Ungültige MP4-Datei.'];
            }
            // Audio (Untertitel/Transkripte: Core\MediaTracks): MP3 mit ID3-Kopf oder Frame-Sync, M4A = MP4-Container
            if ($mime === 'audio/mpeg' && !str_starts_with($head, 'ID3') && !(ord($head[0] ?? "\0") === 0xFF && (ord($head[1] ?? "\0") & 0xE0) === 0xE0)) {
                return [null, 'Ungültige MP3-Datei.'];
            }
            if (in_array($mime, ['audio/mp4', 'audio/x-m4a'], true) && substr($head, 4, 4) !== 'ftyp') {
                return [null, 'Ungültige M4A-Datei.'];
            }
            if ($mime === 'audio/x-m4a') $mime = 'audio/mp4';
            $rel = "$sub/$base." . self::fileMimes()[$mime];
            if (!copy($path, self::dir() . "/$rel")) {
                return [null, 'Datei konnte nicht gespeichert werden.'];
            }
            if ($mime === 'application/pdf') {
                $variants = ['pages' => self::pdfPageCount(self::dir() . "/$rel")];
            }
        } else {
            return [null, 'Dateityp nicht erlaubt. Erlaubt: JPG, PNG, WebP, GIF, ' . (Features::on('media.svg', false) ? 'SVG, ' : '') . 'PDF, MP4, MP3, M4A' . implode('', array_map(fn($d) => ', ' . ($d['label'] ?? strtoupper((string) $d['ext'])), Extensions::mediaTypes())) . '.'];
        }

        $clean = preg_replace('~[^\w.\- äöüÄÖÜß()]+~u', '', basename($originalName)) ?: 'datei';
        return [[
            'file' => $rel, 'original_name' => mb_substr($clean, 0, 180),
            'width' => $width, 'height' => $height, 'mime' => $mime,
            'size' => filesize(self::dir() . "/$rel"), 'variants_json' => json_encode($variants),
        ], null];
    }

    /** Seitenzahl eines PDFs (größter /Count-Wert des Seitenbaums) */
    private static function pdfPageCount(string $file): ?int
    {
        $raw = (string) @file_get_contents($file, false, null, 0, 8 * 1024 * 1024);
        if (preg_match_all('~/Type\s*/Pages\b[^>]*?/Count\s+(\d+)|/Count\s+(\d+)[^>]*?/Type\s*/Pages\b~s', $raw, $m)) {
            $n = max(array_map('intval', array_filter(array_merge($m[1], $m[2]))) ?: [0]);
            return $n > 0 ? $n : null;
        }
        return null;
    }

    private static function load(string $path, string $mime): ?\GdImage
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            'image/gif' => @imagecreatefromgif($path),
            default => false,
        };
        if (!$img) {
            return null;
        }
        if (!imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }
        imagealphablending($img, true);
        imagesavealpha($img, true);
        return $img;
    }

    private static function orient(\GdImage $img, string $path, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $img;
        }
        // alle acht Werte, auch gespiegelte (2, 4, 5, 7) – Core\ImageEdit::exifOps()
        try {
            return ImageEdit::orient($img, (int) (@exif_read_data($path)['Orientation'] ?? 1));
        } catch (\RuntimeException) {
            return $img;
        }
    }

    private static function resize(\GdImage $img, int $w): \GdImage
    {
        $sw = imagesx($img);
        $sh = imagesy($img);
        if ($w >= $sw) {
            return $img;
        }
        $h = (int) round($sh * $w / $sw);
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $w, $h, $sw, $sh);
        return $dst;
    }

    private static function makeVariants(\GdImage $img, string $base, int $width): array
    {
        $sizes = app()->config->get('media.sizes', [480, 800, 1200, 1600, 2400]);
        $q = (int) app()->config->get('media.quality', 80);
        $out = [];
        $cacheDir = self::dir() . '/cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }
        $targets = array_filter($sizes, fn($s) => $s < $width);
        $targets[] = min($width, max($sizes));
        foreach (array_unique($targets) as $w) {
            $r = self::resize($img, (int) $w);
            imagewebp($r, "$cacheDir/$base-$w.webp", $q);
            $formats = ['webp'];
            if (function_exists('imageavif') && $w <= 1600) {
                if (@imageavif($r, "$cacheDir/$base-$w.avif", max(40, $q - 25), 8)) {
                    $formats[] = 'avif';
                }
            }
            $out[] = ['w' => (int) $w, 'f' => $formats];
        }
        return ['base' => $base, 'sizes' => $out];
    }

    // ================================================================= Ausgabe

    public static function url(array $m, ?int $width = null, string $format = 'webp'): string
    {
        return self::publicUrl($m, self::rel($m, $width, $format));
    }

    /** Absoluter Pfad der Datei, die url() mit denselben Angaben liefert (Website oder Pool) */
    public static function localFile(array $m, ?int $width = null, string $format = 'webp'): string
    {
        $pool = $m['_pool'] ?? self::$pool;
        return ($pool !== null ? MediaPools::mediaDir($pool) : site()->mediaDir()) . '/' . self::rel($m, $width, $format);
    }

    /** Datei relativ zum Medienordner: kleinste Größe ≥ $width im Format, sonst Original bzw. bearbeitete Fassung */
    private static function rel(array $m, ?int $width, string $format): string
    {
        $v = json_decode((string) $m['variants_json'], true) ?: [];
        if ($width && !empty($v['sizes'])) {
            $pick = null;
            foreach ($v['sizes'] as $s) {
                if ($s['w'] >= $width && in_array($format, $s['f'], true)) {
                    $pick = $s['w'];
                    break;
                }
            }
            $pick ??= end($v['sizes'])['w'];
            return 'cache/' . $v['base'] . '-' . $pick . '.' . $format;
        }
        // Bearbeitetes Bild (Core\ImageEdit): die bearbeitete Fassung statt des Originals
        $master = ImageEdit::stored($m)['master'] ?? null;
        return (string) ($master ?: $m['file']);
    }

    /** Adresse der unveränderten Originaldatei (Vorher/Nachher im Bildeditor) */
    public static function originalUrl(array $m): string
    {
        return self::publicUrl($m, $m['file']);
    }

    /** Übersetzungen von Alt-Text und Titel: ['en' => ['alt' => …, 'title' => …]] */
    public static function translations(array $m): array
    {
        $t = json_decode((string) ($m['i18n'] ?? ''), true);
        return is_array($t) ? $t : [];
    }

    /** Übersetzungen speichern (nur aktive Nicht-Standardsprachen; leere Werte = Standardsprache) */
    public static function cleanTranslations(mixed $in): ?string
    {
        $out = [];
        foreach (is_array($in) ? $in : [] as $lang => $v) {
            if (!Lang::valid((string) $lang) || $lang === Lang::default() || !is_array($v)) continue;
            $row = array_filter([
                'alt' => mb_substr(trim(strip_tags((string) ($v['alt'] ?? ''))), 0, 250),
                'title' => mb_substr(trim(strip_tags((string) ($v['title'] ?? ''))), 0, 180),
            ], fn($x) => $x !== '');
            if ($row) $out[$lang] = $row;
        }
        return $out ? json_encode($out, JSON_UNESCAPED_UNICODE) : null;
    }

    /** Alt-Text in der Sprache der Seite (Rückfall: Standardsprache) */
    public static function alt(array $m, ?string $lang = null): string
    {
        if (!empty($m['decorative'])) return '';
        $lang ??= Lang::current();
        return (string) (self::translations($m)[$lang]['alt'] ?? '') ?: (string) $m['alt'];
    }

    /** Titel in der Sprache der Seite (Rückfall: Standardsprache) */
    public static function title(array $m, ?string $lang = null): string
    {
        $lang ??= Lang::current();
        return (string) (self::translations($m)[$lang]['title'] ?? '') ?: (string) ($m['title'] ?? '');
    }

    /** Anzeigename: Titel (in der Sprache der Seite) oder aufbereiteter Dateiname */
    public static function displayName(array $m): string
    {
        if (($t = trim(self::title($m))) !== '') {
            return $t;
        }
        $n = preg_replace('~\.[a-z0-9]{2,4}$~i', '', (string) $m['original_name']);
        return trim(preg_replace('~[_\-]+~', ' ', $n)) ?: 'Datei';
    }

    /** CSS-Klassen für den Fokuspunkt (10-%-Raster, siehe site.css: Klassen fx0…fx100 und fy0…fy100) */
    public static function focusClass(array $m): string
    {
        $x = (int) (round(((int) ($m['focus_x'] ?? 50)) / 10) * 10);
        $y = (int) (round(((int) ($m['focus_y'] ?? 50)) / 10) * 10);
        return $x === 50 && $y === 50 ? '' : "fx$x fy$y";
    }

    /**
     * <picture> mit AVIF/WebP-srcset, width/height gegen CLS, lazy außer $eager.
     * Alt-Text aus der Mediathek (dekorative Bilder: alt="").
     */
    public static function picture(?int $id, string $sizes = '100vw', array $opt = []): string
    {
        $m = $id ? self::find($id) : null;
        return $m ? self::pictureOf($m, $sizes, $opt) : '';
    }

    /** Wie picture(), aber für einen Datensatz (z. B. das automatische Vorschaubild eines Videos, Media::posterFor()) */
    public static function pictureOf(array $m, string $sizes = '100vw', array $opt = []): string
    {
        if (!str_starts_with((string) $m['mime'], 'image/')) {
            return '';
        }
        $ratio = isset($opt['ratio']) ? str_replace('-', ':', (string) $opt['ratio']) : null;
        // Bild im Rahmen (Core\ImageFit): Einpassen/Originalformat zeigen das ganze Bild – ohne Zuschnitt und Fokuspunkt
        $fit = ($opt['fit'] ?? true) !== false ? ImageFit::resolve($m, $ratio, isset($opt['path']) ? (string) $opt['path'] : null) : null;
        $src = self::sources($m, $fit ? null : $ratio);
        $alt = $opt['alt'] ?? self::alt($m);
        // Bild anpassen (Core\ImageFx): Einbindung im Block vor globaler Einstellung – als Klassen (CSP, keine Inline-Styles)
        $classes = trim(($opt['class'] ?? '') . ' ' . ($src['cropped'] || $fit ? '' : self::focusClass($m)) . ' ' . ImageFx::classFor($m));
        $class = $classes !== '' ? ' class="' . e($classes) . '"' : '';
        $loading = !empty($opt['eager']) ? ' fetchpriority="high"' : ' loading="lazy" decoding="async"';
        // Im Bearbeiten-Modus: Kennung für „Anpassen“/„Rahmen“ und (mit Bildformat, beim Füllen) den Inline-Zuschnitt
        $edit = app()->editing && !empty($m['id']) ? ' data-media-id="' . (int) $m['id'] . '"'
            . (isset($opt['path']) ? ' data-media-path="' . e((string) $opt['path']) . '"' : '')
            . ($ratio && !$fit && $m['mime'] !== Svg::MIME ? ' data-ratio="' . e($ratio) . '"' : '')
            . ($ratio ? ' data-frame="' . e($ratio) . '"' : '') : '';
        $pic = $fit ? '<picture class="' . e(ImageFit::pictureClass($fit, $m)) . '">' : '<picture>';
        $sources = '';
        foreach (['avif', 'webp'] as $fmt) {
            if ($src[$fmt] !== '') {
                $sources .= '<source type="image/' . $fmt . '" srcset="' . e($src[$fmt]) . '" sizes="' . e($sizes) . '">';
            }
        }
        return $pic . $sources . '<img src="' . e($src['src']) . '" alt="' . e($alt) . '" width="' . $src['width']
            . '" height="' . $src['height'] . '"' . $class . $loading . $edit . '></picture>';
    }

    /**
     * srcsets für ein Bild – mit festgelegtem Zuschnitt für $ratio, sonst das ganze Bild.
     * @return array{avif: string, webp: string, src: string, width: int, height: int, cropped: bool}
     */
    public static function sources(array $m, ?string $ratio = null): array
    {
        $crop = $ratio ? (self::crops($m)[$ratio] ?? null) : null;
        $v = $crop ?: (json_decode((string) $m['variants_json'], true) ?: []);
        $out = ['avif' => '', 'webp' => '', 'src' => self::url($m), 'width' => (int) $m['width'], 'height' => (int) $m['height'], 'cropped' => (bool) $crop];
        foreach (['avif', 'webp'] as $fmt) {
            $set = [];
            foreach ($v['sizes'] ?? [] as $s) {
                if (in_array($fmt, $s['f'], true)) {
                    $set[] = self::publicUrl($m, 'cache/' . $v['base'] . '-' . $s['w'] . '.' . $fmt) . ' ' . $s['w'] . 'w';
                }
            }
            $out[$fmt] = implode(', ', $set);
        }
        if ($crop) {
            $mid = null;
            foreach ($crop['sizes'] as $s) {
                if ($mid === null || $s['w'] <= 1200) $mid = $s['w'];
            }
            $out['src'] = self::publicUrl($m, 'cache/' . $crop['base'] . '-' . $mid . '.webp');
            $out['width'] = (int) $crop['W'];
            $out['height'] = (int) $crop['H'];
        }
        return $out;
    }

    // ================================================================= Zuschnitt je Bildformat

    /** Anzahl je Art für die Seitenleiste der Mediathek */
    public static function counts(): array
    {
        $r = self::db()->fetch("SELECT COUNT(*) AS alle,
            SUM(CASE WHEN mime LIKE 'image/%' THEN 1 ELSE 0 END) AS image,
            SUM(CASE WHEN mime = 'application/pdf' THEN 1 ELSE 0 END) AS pdf,
            SUM(CASE WHEN mime LIKE 'video/%' THEN 1 ELSE 0 END) AS video,
            SUM(CASE WHEN mime LIKE 'image/%' AND (alt IS NULL OR alt = '') AND decorative = 0 THEN 1 ELSE 0 END) AS noalt,
            SUM(CASE WHEN mime LIKE 'audio/%' THEN 1 ELSE 0 END) AS audio,
            SUM(CASE WHEN title IS NULL OR title = '' THEN 1 ELSE 0 END) AS notitle
            FROM media" . (self::$pool === null ? ' WHERE pool_ref IS NULL' : '')) ?: [];
        $out = array_map('intval', $r);
        // Prüf-Filter mit Unterabfragen (je Sprache, Untertitel, Transkripte) – im Kontext Website bzw. Pool
        $scope = self::$pool === null ? ' AND m.pool_ref IS NULL' : '';
        try {
            $out['nocaptions'] = (int) self::db()->fetchValue('SELECT COUNT(*) FROM media m WHERE ' . self::NOCAPTIONS_SQL . $scope);
            $out['notranscript'] = (int) self::db()->fetchValue('SELECT COUNT(*) FROM media m WHERE ' . self::NOTRANSCRIPT_SQL . $scope);
            foreach (array_keys(Lang::all()) as $l) {
                if ($l === Lang::default()) continue;
                $out['missing_' . $l] = (int) self::db()->fetchValue('SELECT COUNT(*) FROM media m WHERE ' . self::MISSING_LANG_SQL . $scope, ['$."' . $l . '".alt']);
            }
            foreach (Extensions::mediaChecks() as $key => $c) {
                $out['x_' . $key] = (int) self::db()->fetchValue('SELECT COUNT(*) FROM media m WHERE (' . $c['where'] . ')' . $scope, $c['params']);
            }
        } catch (\Throwable $e) {
            error_log('[media] counts: ' . $e->getMessage());
        }
        return $out;
    }

    /** Bildformate des Themes, z. B. ['16:9' => 'Querformat 16:9', …] */
    public static function ratios(): array
    {
        return app()->theme->def['image_ratios'] ?? ['16:9' => '16:9', '4:3' => '4:3', '1:1' => '1:1', '3:4' => '3:4'];
    }

    /** Festgelegte Zuschnitte: ['16:9' => ['x','y','w','h' (Anteile 0–1), 'base', 'sizes', 'W', 'H'], …] */
    public static function crops(array $m): array
    {
        return json_decode((string) ($m['crops'] ?? ''), true) ?: [];
    }

    /**
     * Legt den Zuschnitt eines Bildes für ein Format fest (oder entfernt ihn mit $rect = null).
     * $rect in Anteilen des Originals (x, y, w, h zwischen 0 und 1); das Seitenverhältnis wird exakt erzwungen.
     * Erzeugt eigene AVIF/WebP-Größen, die Website bleibt ohne Inline-Styles (CSP).
     * @return array{0: ?array, 1: ?string}
     */
    public static function setCrop(int $id, string $ratio, ?array $rect): array
    {
        $m = self::find($id);
        if (!$m || !str_starts_with($m['mime'], 'image/')) {
            return [null, 'Nur Bilder lassen sich zuschneiden.'];
        }
        if ($m['mime'] === Svg::MIME) {
            return [null, __('SVG-Grafiken werden immer vollständig gezeigt – ein Zuschnitt ist nicht nötig.')];
        }
        if (!preg_match('~^(\d{1,2}):(\d{1,2})$~', $ratio, $rm) || !isset(self::ratios()[$ratio]) || !(int) $rm[1] || !(int) $rm[2]) {
            return [null, 'Unbekanntes Bildformat.'];
        }
        $crops = self::crops($m);
        if (isset($crops[$ratio])) {
            self::deleteVariantFiles($crops[$ratio]);
            unset($crops[$ratio]);
        }
        if ($rect !== null) {
            // Zuschnitt je Format auf dem bearbeiteten Bild (Core\ImageEdit), sonst auf dem Original
            [$src, $srcMime] = self::editedSource($m);
            $img = self::load($src, $srcMime);
            if (!$img) {
                return [null, 'Bild konnte nicht gelesen werden.'];
            }
            $fw = imagesx($img);
            $fh = imagesy($img);
            $f = fn($k) => max(0.0, min(1.0, (float) ($rect[$k] ?? 0)));
            $ar = (int) $rm[1] / (int) $rm[2];
            // Breite bestimmen, Höhe aus dem Format ableiten, beides ins Bild einpassen
            $pw = max(16, (int) round($f('w') * $fw));
            $ph = (int) round($pw / $ar);
            if ($ph > $fh) { $ph = $fh; $pw = (int) round($ph * $ar); }
            if ($pw > $fw) { $pw = $fw; $ph = (int) round($pw / $ar); }
            $px = (int) round(min($f('x') * $fw, $fw - $pw));
            $py = (int) round(min($f('y') * $fh, $fh - $ph));
            $part = imagecrop($img, ['x' => max(0, $px), 'y' => max(0, $py), 'width' => $pw, 'height' => $ph]);
            if (!$part) {
                return [null, 'Zuschnitt fehlgeschlagen.'];
            }
            $v = self::makeVariants($part, bin2hex(random_bytes(8)) . '-c' . str_replace(':', 'x', $ratio), $pw);
            $crops[$ratio] = [
                'x' => round($px / $fw, 5), 'y' => round($py / $fh, 5), 'w' => round($pw / $fw, 5), 'h' => round($ph / $fh, 5),
                // Größe im Verhältnis zum Original (gespeicherte Datei ist evtl. verkleinert)
                'W' => (int) round($pw / $fw * (int) $m['width']), 'H' => (int) round($ph / $fh * (int) $m['height']),
            ] + $v;
        }
        self::db()->update('media', ['crops' => $crops ? json_encode($crops) : null, 'updated_at' => now()], 'id = :id', ['id' => $id]);
        self::forget($id);
        PageCache::clear();
        return [self::find($id), null];
    }

    // ================================================================= Bild bearbeiten (Core\ImageEdit)

    /** Quelle für Zuschnitte: bearbeitete Fassung, sonst Original. @return array{0: string, 1: string} [Pfad, MIME] */
    private static function editedSource(array $m): array
    {
        $master = ImageEdit::stored($m)['master'] ?? null;
        $dir = substr(self::path($m), 0, -strlen((string) $m['file']));   // Medienordner der Datei (Website oder Pool) mit „/“
        if ($master && is_file($dir . $master)) {
            return [$dir . $master, str_ends_with($master, '.png') ? 'image/png' : 'image/jpeg'];
        }
        return [self::path($m), (string) $m['mime']];
    }

    /**
     * Bearbeitung festlegen (oder mit leerem $edit zurücksetzen) – zerstörungsfrei: aus dem Original entstehen eine
     * bearbeitete Fassung und neue Größen (neue Dateinamen = neue Adressen). Eigene Zuschnitte je Format werden
     * zurückgesetzt (anderes Motiv), Fokuspunkt und Anpassung (CSS) bleiben.
     * @return array{0: ?array, 1: ?string}
     */
    public static function setEdit(int $id, mixed $edit): array
    {
        $m = self::find($id);
        if (!$m || !str_starts_with((string) $m['mime'], 'image/')) {
            return [null, __('Nur Bilder lassen sich bearbeiten.')];
        }
        if ($why = ImageEdit::editable($m)) {
            return [null, $why];
        }
        $ops = ImageEdit::normalize($edit);
        if ($ops === null) {
            return [null, __('Ungültige Bildbearbeitung.')];
        }
        $stored = ImageEdit::stored($m);
        $orig = $stored['orig'] ?? ['w' => (int) $m['width'], 'h' => (int) $m['height']];
        if ($ops === ImageEdit::ops($m) && ($ops === [] || !empty($stored['master']))) {
            return [$m, null];   // unverändert
        }
        $img = self::load(self::path($m), (string) $m['mime']);
        if (!$img) {
            return [null, __('Bild konnte nicht gelesen werden.')];
        }
        @set_time_limit(120);
        $fw = imagesx($img);   // gespeichertes Original (höchstens 3200 px) – Maße der Spalten beziehen sich aufs Hochgeladene
        $fh = imagesy($img);
        $scale = $orig['w'] > 0 ? $orig['w'] / $fw : 1.0;
        $master = null;
        try {
            if ($ops) {
                $img = ImageEdit::apply($img, $ops);
                $ext = str_ends_with((string) $m['file'], '.png') ? 'png' : 'jpg';
                $master = 'cache/' . bin2hex(random_bytes(8)) . '-e.' . $ext;
                @mkdir(self::dir() . '/cache', 0775, true);
                $ok = $ext === 'png' ? imagepng($img, self::dir() . '/' . $master, 8) : imagejpeg($img, self::dir() . '/' . $master, 90);
                if (!$ok) throw new \RuntimeException(__('Bearbeitetes Bild konnte nicht gespeichert werden.'));
            }
            $variants = self::makeVariants($img, bin2hex(random_bytes(8)), imagesx($img));
        } catch (\RuntimeException $e) {
            if ($master) @unlink(self::dir() . '/' . $master);
            return [null, $e->getMessage()];
        }
        // Alte Größen, alte bearbeitete Fassung und Zuschnitte je Format entfernen – das Original bleibt
        self::deleteVariantFiles(json_decode((string) $m['variants_json'], true) ?: []);
        foreach (self::crops($m) as $c) self::deleteVariantFiles($c);
        if (!empty($stored['master'])) @unlink(self::dir() . '/' . $stored['master']);
        // Maße wie beim Hochladen (Original kann größer sein als die gespeicherte Datei), Seitenverhältnis vom Ergebnis
        $w = $ops ? max(1, (int) round(ImageEdit::outputSize($fw, $fh, $ops)[0] * $scale)) : $orig['w'];
        $h = $ops ? max(1, (int) round($w * imagesy($img) / max(1, imagesx($img)))) : $orig['h'];
        self::db()->update('media', [
            'variants_json' => json_encode($variants), 'width' => $w, 'height' => $h, 'crops' => null,
            'edit_json' => $ops ? json_encode(['ops' => $ops, 'orig' => $orig, 'master' => $master], JSON_UNESCAPED_SLASHES) : null,
            'updated_at' => now(),
        ], 'id = :id', ['id' => $id]);
        self::forget($id);
        MediaPools::forget();
        PageCache::clear();
        Extensions::emit('media.edited', self::find($id), $m);
        return [self::find($id), null];
    }

    // ================================================================= Löschen

    /** Alle Dateien eines Mediums relativ zu seinem Medienordner (z. B. zum Kopieren zwischen Pools, siehe Core\Data\Shared) */
    public static function fileList(array $m): array
    {
        return self::files($m);
    }

    /** Alle Dateien eines Mediums relativ zum Medienordner (Original, bearbeitete Fassung, Größen, Zuschnitte) */
    private static function files(array $m): array
    {
        $out = [$m['file']];
        if ($master = ImageEdit::stored($m)['master'] ?? null) $out[] = $master;
        $v = json_decode((string) $m['variants_json'], true) ?: [];
        foreach ([$v, ...self::crops($m)] as $set) {
            foreach ($set['sizes'] ?? [] as $sz) {
                foreach ($sz['f'] ?? [] as $f) $out[] = 'cache/' . $set['base'] . '-' . $sz['w'] . '.' . $f;
            }
        }
        array_push($out, ...VideoThumbs::files($m));   // automatisches Vorschaubild eines Videos
        return array_values(array_unique($out));
    }

    /**
     * Datei der Website in einen geteilten Pool verschieben: Dateien und Angaben (Alt-Texte, Übersetzungen,
     * Tags, Zuschnitte, Fokus) gehen in den Pool, der Eintrag der Website wird zum Verweis – alle bisherigen
     * Verwendungen bleiben gültig. @return int ID im Pool
     */
    public static function shareToPool(int $id, string $pool): int
    {
        if (self::$pool !== null) throw new \RuntimeException('Nur aus der Mediathek der Website.');
        $m = self::find($id) ?? throw new \RuntimeException('Datei nicht gefunden.');
        if (!empty($m['pool_ref'])) throw new \RuntimeException('Die Datei liegt bereits in geteilten Medien.');
        if (!MediaPools::available($pool)) throw new \RuntimeException('Diesen Pool nutzt die Website nicht.');
        $src = site()->mediaDir();
        $dst = MediaPools::mediaDir($pool);
        $files = self::files($m);
        foreach ($files as $rel) {
            if (!is_file("$src/$rel")) continue;
            @mkdir(dirname("$dst/$rel"), 0775, true);
            if (!copy("$src/$rel", "$dst/$rel")) throw new \RuntimeException('Datei konnte nicht kopiert werden.');
        }
        if ($m['mime'] === Svg::MIME) Svg::guard($dst);
        $row = array_filter($m, fn($k) => is_string($k) && $k !== 'id' && $k[0] !== '_' && $k !== 'pool_ref', ARRAY_FILTER_USE_KEY);
        $pid = (int) MediaPools::db($pool)->insert('media', $row + ['updated_at' => now()]);
        self::db()->update('media', ['pool_ref' => "$pool:$pid", 'updated_at' => now()], 'id = :id', ['id' => $id]);
        foreach ($files as $rel) @unlink("$src/$rel");
        MediaTracks::moveToPool($id, $pool, $pid);   // Untertitel wandern mit
        self::forget($id);
        MediaPools::forget();
        PageCache::clear();
        return $pid;
    }

    private static function deleteFiles(array $m): void
    {
        @unlink(self::dir() . '/' . $m['file']);
        if ($master = ImageEdit::stored($m)['master'] ?? null) @unlink(self::dir() . '/' . $master);
        $v = json_decode((string) $m['variants_json'], true) ?: [];
        foreach ($v['sizes'] ?? [] as $s) {
            foreach ($s['f'] as $f) {
                @unlink(self::dir() . '/cache/' . $v['base'] . '-' . $s['w'] . '.' . $f);
            }
        }
        foreach (self::crops($m) as $c) {
            self::deleteVariantFiles($c);
        }
        foreach (VideoThumbs::files($m) as $rel) {
            @unlink(self::dir() . '/' . $rel);
        }
    }

    private static function deleteVariantFiles(array $v): void
    {
        foreach ($v['sizes'] ?? [] as $s) {
            foreach ($s['f'] as $f) {
                @unlink(self::dir() . '/cache/' . $v['base'] . '-' . $s['w'] . '.' . $f);
            }
        }
    }

    public static function delete(int $id): void
    {
        $m = self::find($id);
        if (!$m) {
            // Verweis, dessen Pool-Datei nicht mehr existiert
            self::db()->query('DELETE FROM media WHERE id = ? AND pool_ref IS NOT NULL', [$id]);
            return;
        }
        Extensions::emit('media.deleted', $m);
        if (self::$pool !== null || empty($m['pool_ref'])) {
            self::deleteFiles($m);   // Verweise auf Pool-Dateien löschen nur den Verweis
            MediaTracks::deleteAll($m);
        }
        self::db()->query('DELETE FROM media_collection_items WHERE media_id = ?', [$id]);
        self::db()->query('DELETE FROM media WHERE id = ?', [$id]);
        self::forget($id);
        PageCache::clear();
    }

    // ================================================================= Tags

    public static function normTag(string $t): string
    {
        $t = mb_strtolower(trim(strip_tags($t)));
        return mb_substr(preg_replace('~[,\s]+~u', ' ', $t), 0, 40);
    }

    /** Speicherformat: ",tag eins,tag zwei," (einfach per LIKE durchsuchbar) */
    public static function tagString(string|array $tags): string
    {
        $list = is_array($tags) ? $tags : explode(',', $tags);
        $list = array_values(array_unique(array_filter(array_map(fn($t) => self::normTag((string) $t), $list))));
        return $list ? ',' . implode(',', $list) . ',' : '';
    }

    public static function tagList(?string $stored): array
    {
        return array_values(array_filter(explode(',', (string) $stored), 'strlen'));
    }

    /** Alle Tags mit Anzahl */
    public static function allTags(): array
    {
        $count = [];
        foreach (self::db()->fetchAll("SELECT tags FROM media WHERE tags IS NOT NULL AND tags != ''") as $r) {
            foreach (self::tagList($r['tags']) as $t) {
                $count[$t] = ($count[$t] ?? 0) + 1;
            }
        }
        ksort($count);
        return $count;
    }

    // ================================================================= Sammlungen

    public static function collections(): array
    {
        return self::db()->fetchAll('SELECT c.*, (SELECT COUNT(*) FROM media_collection_items i WHERE i.collection_id = c.id) AS count
            FROM media_collections c ORDER BY c.name');
    }

    public static function collectionIds(int $mediaId): array
    {
        return array_map('intval', array_column(self::db()->fetchAll('SELECT collection_id FROM media_collection_items WHERE media_id = ?', [$mediaId]), 'collection_id'));
    }

    public static function createCollection(string $name, string $description = ''): int
    {
        return self::db()->insert('media_collections', ['name' => mb_substr(trim(strip_tags($name)), 0, 120) ?: 'Sammlung',
            'description' => mb_substr(strip_tags($description), 0, 500), 'created_at' => now()]);
    }

    public static function addToCollection(int $collectionId, array $mediaIds): void
    {
        $max = (int) self::db()->fetchValue('SELECT COALESCE(MAX(sort), 0) FROM media_collection_items WHERE collection_id = ?', [$collectionId]);
        foreach ($mediaIds as $mid) {
            $exists = self::db()->fetchValue('SELECT 1 FROM media_collection_items WHERE collection_id = ? AND media_id = ?', [$collectionId, (int) $mid]);
            if (!$exists) {
                self::db()->insert('media_collection_items', ['collection_id' => $collectionId, 'media_id' => (int) $mid, 'sort' => ++$max]);
            }
        }
        PageCache::clear();
    }

    public static function removeFromCollection(int $collectionId, array $mediaIds): void
    {
        foreach ($mediaIds as $mid) {
            self::db()->query('DELETE FROM media_collection_items WHERE collection_id = ? AND media_id = ?', [$collectionId, (int) $mid]);
        }
        PageCache::clear();
    }

    public static function setCollections(int $mediaId, array $collectionIds): void
    {
        self::db()->query('DELETE FROM media_collection_items WHERE media_id = ?', [$mediaId]);
        foreach (array_unique(array_map('intval', $collectionIds)) as $cid) {
            if ($cid > 0) {
                self::addToCollection($cid, [$mediaId]);
            }
        }
    }

    // ================================================================= Verwendung

    /**
     * Wo wird das Medium verwendet? (Seiten-Blöcke, Einstellungen)
     * @return array<int, array{label: string, url: ?string}>
     */
    public static function usages(int $id): array
    {
        // Pool-Datei: Verwendungen auf dieser Website über ihren Verweis-Eintrag
        if (self::$pool !== null) {
            $local = app()->db->fetchValue('SELECT id FROM media WHERE pool_ref = ?', [self::$pool . ':' . $id]);
            if (!$local) return [];
            $pool = self::$pool;
            self::usePool(null);
            $out = self::usages((int) $local);
            self::usePool($pool);
            return $out;
        }
        $out = [];
        $theme = app()->theme;
        foreach (Pages::all() as $p) {
            if ((int) $p['og_image'] === $id) {
                $out[] = ['label' => $p['title'] . ' → Vorschaubild', 'url' => Pages::url($p)];
            }
            $seen = [];
            foreach (array_merge(Pages::blocks($p, false), Pages::blocks($p, true)) as $b) {
                $def = $theme->block((string) ($b['type'] ?? ''));
                $inBlock = $def && (self::fieldsUse($def['fields'], $b['data'] ?? [], $id) || (int) ($b['tunes']['section']['bgImage'] ?? 0) === $id);
                if ($inBlock && !isset($seen[$b['id']])) {
                    $seen[$b['id']] = true;
                    $out[] = ['label' => $p['title'] . ' → ' . $def['label'], 'url' => Pages::url($p)];
                }
            }
        }
        foreach ($theme->settingsFields() as $f) {
            if (in_array($f['type'] ?? '', ['media', 'file'], true) && (int) setting($f['name']) === $id) {
                $out[] = ['label' => $theme->settingsTitle() . ' → ' . $f['label'], 'url' => null];
            }
        }
        return $out;
    }

    private static function fieldsUse(array $fields, array $data, int $id): bool
    {
        foreach ($fields as $f) {
            $v = $data[$f['name'] ?? ''] ?? null;
            if (in_array($f['type'] ?? '', ['media', 'file'], true) && (int) $v === $id) {
                return true;
            }
            if (($f['type'] ?? '') === 'repeater' && is_array($v)) {
                foreach ($v as $item) {
                    if (is_array($item) && self::fieldsUse($f['fields'] ?? [], $item, $id)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    // ================================================================= Hilfen

    public static function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }
        return max(1, (int) round($bytes / 1024)) . ' KB';
    }

    public static function typeLabel(string $mime): string
    {
        return match (true) {
            $mime === 'application/pdf' => 'PDF',
            $mime === 'video/mp4' => 'MP4',
            $mime === 'audio/mpeg' => 'MP3',
            $mime === 'audio/mp4' => 'M4A',
            $mime === Svg::MIME => 'SVG',
            str_starts_with($mime, 'image/') => strtoupper(substr($mime, 6)) === 'JPEG' ? 'JPG' : strtoupper(substr($mime, 6)),
            isset(Extensions::mediaTypes()[$mime]) => (string) (Extensions::mediaTypes()[$mime]['label'] ?? strtoupper((string) Extensions::mediaTypes()[$mime]['ext'])),
            default => 'Datei',
        };
    }

    public static function pages(array $m): ?int
    {
        $v = json_decode((string) $m['variants_json'], true) ?: [];
        return isset($v['pages']) ? (int) $v['pages'] : null;
    }

    public static function viewerUrl(array $m): ?string
    {
        return $m['mime'] === 'application/pdf' ? url('/pdf/' . $m['id']) : null;
    }

    public static function toJson(array $m): array
    {
        $isImg = str_starts_with($m['mime'], 'image/');
        // Video: gewähltes Poster > automatisches Vorschaubild > (mit ffmpeg) Adresse zum Erzeugen, sonst Platzhalter
        $poster = !$isImg && VideoThumbs::isVideo($m) ? self::posterFor($m) : null;
        $pic = $isImg ? $m : $poster;
        return [
            'id' => (int) $m['id'], 'url' => self::url($m), 'thumb' => $pic ? self::url($pic, 480) : null,
            'large' => $pic ? self::url($pic, 1200) : null,
            'poster' => $poster ? (!empty($poster['_auto_poster']) ? 'auto' : 'chosen') : null,
            'thumb_gen' => !$poster && VideoThumbs::isVideo($m) && VideoThumbs::pending($m)
                ? url('/admin/api/media/' . (int) $m['id'] . '/thumb') . (self::$pool !== null ? '?pool=' . rawurlencode(self::$pool) : '') : null,
            'alt' => (string) $m['alt'], 'decorative' => (bool) ($m['decorative'] ?? false),
            'title' => (string) ($m['title'] ?? ''), 'display' => self::displayName($m),
            'name' => $m['original_name'], 'mime' => $m['mime'], 'kind' => self::kind($m['mime']),
            'width' => (int) $m['width'], 'height' => (int) $m['height'],
            'size' => self::humanSize((int) $m['size']), 'bytes' => (int) $m['size'], 'type' => self::typeLabel($m['mime']),
            'pages' => self::pages($m), 'viewer' => self::viewerUrl($m),
            'credit' => (string) ($m['credit'] ?? ''), 'tags' => self::tagList($m['tags'] ?? ''),
            'focus' => ['x' => (int) ($m['focus_x'] ?? 50), 'y' => (int) ($m['focus_y'] ?? 50)],
            // Bild anpassen (Core\ImageFx): gespeicherte Einstellung, Klassen für die Vorschau, Kurzbeschreibung
            'adjust' => $isImg ? (string) ($m['adjust'] ?? '') : '', 'adjust_label' => $isImg ? ImageFx::label((string) ($m['adjust'] ?? '')) : '',
            // Bild im Rahmen (Core\ImageFit): Standard des Bildes, Kurzbeschreibung, automatischer Standard (SVG, transparenter Rand)
            'fit' => $isImg ? (string) ($m['fit'] ?? '') : '', 'fit_label' => $isImg ? ImageFit::label((string) ($m['fit'] ?? '')) : '',
            'fit_auto' => $isImg ? ($m['mime'] === Svg::MIME || ImageFit::hasAlpha($m, false) ? 'contain' : '') : '',
            // Bild bearbeiten (Core\ImageEdit): Schritte, Hinweis falls nicht bearbeitbar, Original für Vorher/Nachher
            'edit' => $isImg && ($ops = ImageEdit::ops($m)) ? $ops : null,
            'editable' => ImageEdit::editable($m),
            'original' => $isImg ? self::originalUrl($m) : null,
            'original_size' => $isImg ? ImageEdit::stored($m)['orig'] ?? ['w' => (int) $m['width'], 'h' => (int) $m['height']] : null,
            'created_at' => $m['created_at'], 'updated_at' => $m['updated_at'] ?? null,
            'missing_alt' => $isImg && trim((string) $m['alt']) === '' && empty($m['decorative']),
            'i18n' => (object) self::translations($m),
            'pool' => $m['_pool'] ?? null,
            'missing_translations' => $isImg && empty($m['decorative']) && Lang::multi()
                ? array_values(array_filter(array_keys(Lang::all()), fn($l) => $l !== Lang::default() && empty(self::translations($m)[$l]['alt']))) : [],
            // SVG (Core\Svg): keine Größen, kein Fokus/Zuschnitt; Ergebnis der Bereinigung für die Upload-Meldung
            'svg' => $m['mime'] === Svg::MIME,
            'note' => $m['mime'] === Svg::MIME && ($st = json_decode((string) $m['variants_json'], true)['svg'] ?? null) ? Svg::note($st) : null,
            'crops' => $isImg ? array_map(fn($c) => [
                'x' => $c['x'], 'y' => $c['y'], 'w' => $c['w'], 'h' => $c['h'], 'width' => $c['W'], 'height' => $c['H'],
                'thumb' => self::publicUrl($m, 'cache/' . $c['base'] . '-' . $c['sizes'][0]['w'] . '.webp'),
            ], self::crops($m)) : [],
            // Video/Audio: Untertitel und Transkripte (Core\MediaTracks)
            'captions' => MediaTracks::supports($m) ? MediaTracks::summary($m) : null,
            // Angaben aktiver Erweiterungen (Extension::mediaJson), z. B. ext.video_tools
            'ext' => (object) Extensions::mediaJson($m),
        ];
    }
}
