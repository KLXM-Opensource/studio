<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Media;

/**
 * Mediathek: Oberfläche + JSON-API.
 *
 * Upload in Stücken (chunked, je 1 MB): POST /admin/media/chunk (mehrfach) → POST /admin/media/finalize.
 * So funktionieren auch große Dateien bei knappen PHP-Limits (upload_max_filesize/post_max_size).
 * Der Alt-Text wird beim Finalisieren verbindlich geprüft.
 */
final class MediaController extends AdminController
{
    private const CHUNK_MAX = 2 * 1024 * 1024;

    /**
     * Geteilte Medien: mit Parameter pool arbeitet die Aktion im Pool statt in der Mediathek der Website.
     * Ändern im Pool nur mit „media.shared“. Gibt eine Fehlerantwort zurück oder null.
     */
    private function scope(Request $r, bool $write): ?Response
    {
        $pool = $r->str('pool');
        if ($pool === '') return null;
        if (!\Core\MediaPools::available($pool)) {
            return Response::json(['ok' => false, 'error' => 'Diesen Medien-Pool nutzt die Website nicht.'], 404);
        }
        if ($write && !\Core\MediaPools::canEdit($pool)) return $this->poolDenied();
        Media::usePool($pool);
        return null;
    }

    private function poolDenied(): Response
    {
        return Response::json(['ok' => false, 'error' => __('Diesen geteilten Pool darf diese Website nur verwenden. Ändern können Personen mit dem Recht „Geteilte Medien pflegen“ auf Websites, die zum Pflegen freigegeben sind.')], 403);
    }

    /** Verweis auf eine Pool-Datei → Änderungen gehen an die Pool-Datei (nur mit „media.shared“) */
    private function target(int $id, bool $write): int|Response
    {
        $m = Media::find($id);
        if ($m && Media::pool() === null && !empty($m['pool_ref'])) {
            if ($write && !\Core\MediaPools::canEdit($m['_pool'])) return $this->poolDenied();
            $pid = (int) $m['_pool_id'];
            Media::usePool($m['_pool']);
            return $pid;
        }
        return $id;
    }

    /** Vorhandene Dateien der Website in einen Pool verschieben (bleiben überall verwendet) */
    public function share(Request $r): Response
    {
        $this->auth($r, 'media.upload');
        $pool = (string) ($r->post['target'] ?? '');
        if (!\Core\MediaPools::canEdit($pool)) return $this->poolDenied();
        $done = 0;
        $errors = [];
        foreach (array_filter(array_map('intval', (array) ($r->post['ids'] ?? []))) as $id) {
            try { Media::shareToPool($id, $pool); $done++; } catch (\RuntimeException $e) { $errors[] = $e->getMessage(); }
        }
        $this->changed();
        return Response::json(['ok' => $done > 0 || !$errors, 'shared' => $done, 'errors' => array_values(array_unique($errors)),
            'error' => $done === 0 && $errors ? $errors[0] : null]);
    }

    /** Pool-Datei auf dieser Website verwenden → Verweis-Eintrag (lokale ID) */
    public function useShared(Request $r): Response
    {
        $this->auth($r);
        $pool = $r->str('pool');
        if (!\Core\MediaPools::available($pool)) {
            return Response::json(['ok' => false, 'error' => 'Unbekannter Medien-Pool.'], 404);
        }
        try {
            $id = \Core\MediaPools::mirror($pool, (int) $r->str('id'));
        } catch (\RuntimeException $e) {
            return Response::json(['ok' => false, 'error' => $e->getMessage()], 404);
        }
        return Response::json(['ok' => true, 'item' => Media::toJson(Media::find($id))]);
    }

    public function index(Request $r): Response
    {
        $this->auth($r);
        return $this->view('media', [
            'css' => [],
            'maxMb' => (int) app()->config->get('media.max_upload_mb', 50),
        ]);
    }

    // ------------------------------------------------------------------ Liste / Details

    public function list(Request $r): Response
    {
        $this->auth($r);
        if ($e = $this->scope($r, false)) return $e;
        $f = [
            'q' => $r->str('q'), 'kind' => $r->str('kind'), 'tag' => $r->str('tag'),
            'collection' => (int) $r->str('collection'), 'noalt' => $r->str('noalt') === '1',
            // Prüf-Filter der Seitenleiste „Prüfen“
            'missing_lang' => $r->str('missing_lang'), 'notitle' => $r->str('notitle') === '1',
            'nocaptions' => $r->str('nocaptions') === '1', 'notranscript' => $r->str('notranscript') === '1',
            'check' => $r->str('check'),   // Prüf-Filter einer Erweiterung (Extension::mediaChecks)
        ];
        return Response::json([
            'items' => array_map([Media::class, 'toJson'], Media::all($f)),
            'tags' => Media::allTags(),
            'collections' => array_map(fn($c) => ['id' => (int) $c['id'], 'name' => $c['name'], 'count' => (int) $c['count']], Media::collections()),
            'ratios' => Media::ratios(),
            // Prüf-Filter aktiver Erweiterungen: Schlüssel, Bezeichnung, Symbol, Art (Anzahl in counts.x_{schlüssel})
            'ext_checks' => array_values(array_map(fn($k, $c) => ['key' => $k, 'label' => $c['label'], 'icon' => $c['icon'], 'kind' => $c['kind'], 'extension' => $c['extension']],
                array_keys(\Core\Extensions::mediaChecks()), \Core\Extensions::mediaChecks())),
            'counts' => Media::counts(),
            // Weitere Inhaltssprachen für Alt-Text/Titel-Übersetzungen
            'languages' => (object) array_diff_key(\Core\Lang::all(), [\Core\Lang::default() => 1]),
            // Geteilte Medien: verfügbare Pools, aktueller Pool, darf die Person dort ändern?
            'pools' => array_map(fn($k, $l) => ['key' => $k, 'label' => $l, 'edit' => \Core\MediaPools::canEdit($k)], array_keys(\Core\MediaPools::forSite()), \Core\MediaPools::forSite()),
            'pool' => Media::pool(),
            'can_edit' => Media::pool() === null ? can('media.upload') : \Core\MediaPools::canEdit(Media::pool()),
            'can_share' => (bool) array_filter(array_keys(\Core\MediaPools::forSite()), [\Core\MediaPools::class, 'canEdit']),
            // „Prüfen“: Untertitel-Funktion an? Schnellzugänge in den Bereich KLXM AI (nur mit eingeschalteter KI)
            'ai' => [
                'captions' => \Core\MediaTracks::enabled(),
                'alt' => \Core\AI\Assist::available('vision') && can('media.upload') ? url('/admin/ai/alt-texte') : null,
                'subtitles' => \Core\AI\MediaJobs::canTranscribe() && can('media.upload') ? url('/admin/ai/untertitel') : null,
            ],
        ]);
    }

    public function detail(Request $r, string $id): Response
    {
        $this->auth($r);
        if ($e = $this->scope($r, false)) return $e;
        $m = Media::find((int) $id) ?? throw new HttpException(404);
        return Response::json(Media::toJson($m) + [
            'collections' => Media::collectionIds((int) $id),
            'usages' => Media::usages((int) $id),
            'edit_engine' => \Core\ImageEdit::engine(),   // Entzerren mit Imagick oder GD (Hinweis im Bildeditor)
        ]);
    }

    /**
     * Automatisches Vorschaubild eines Videos (Core\VideoThumbs) – lazy aus Raster, Liste, Auswahl und Feldern.
     * Nur mit Anmeldung + „media.upload“, nur Videos der Mediathek (bzw. des Pools), keine weiteren Parameter.
     * Antwort: {ok, thumb, large} | {ok: false, busy: true} (später erneut) | {ok: false} (Platzhalter behalten).
     */
    public function thumb(Request $r, string $id): Response
    {
        $this->auth($r, 'media.upload');
        if ($e = $this->scope($r, false)) return $e;
        $m = ctype_digit($id) ? Media::find((int) $id) : null;
        if (!$m || !\Core\VideoThumbs::isVideo($m)) throw new HttpException(404);
        $j = Media::toJson($m);
        if (!$j['thumb'] && \Core\VideoThumbs::pending($m)) {
            @set_time_limit(30);
            $res = \Core\VideoThumbs::generate($m);
            if ($res === 'busy') {
                return Response::json(['ok' => false, 'busy' => true]);
            }
            Media::forget((int) $id);
            $j = Media::toJson(Media::find((int) $id));
        }
        return Response::json($j['thumb'] ? ['ok' => true, 'thumb' => $j['thumb'], 'large' => $j['large'], 'poster' => $j['poster']] : ['ok' => false]);
    }

    public function save(Request $r, string $id): Response
    {
        $this->auth($r, 'media.upload');
        if ($e = $this->scope($r, true)) return $e;
        $t = $this->target((int) $id, true);
        if ($t instanceof Response) return $t;
        $id = (string) $t;
        $m = Media::find((int) $id) ?? throw new HttpException(404);
        $p = $r->post;
        $decorative = !empty($p['decorative']);
        $alt = mb_substr(trim(strip_tags((string) ($p['alt'] ?? $m['alt']))), 0, 250);
        if (str_starts_with($m['mime'], 'image/') && !$decorative && mb_strlen($alt) < 3) {
            return Response::json(['ok' => false, 'error' => 'Bitte einen Alt-Text angeben oder das Bild als „dekorativ“ markieren.'], 422);
        }
        $upd = [
            'alt' => $alt, 'decorative' => $decorative ? 1 : 0,
            'title' => mb_substr(trim(strip_tags((string) ($p['title'] ?? ''))), 0, 180),
            'credit' => mb_substr(trim(strip_tags((string) ($p['credit'] ?? ''))), 0, 250),
            'tags' => Media::tagString($p['tags'] ?? ''),
            'updated_at' => now(),
        ];
        if (isset($p['i18n'])) {
            $upd['i18n'] = Media::cleanTranslations($p['i18n']);
        }
        if (isset($p['focus']['x'], $p['focus']['y'])) {
            $upd['focus_x'] = max(0, min(100, (int) $p['focus']['x']));
            $upd['focus_y'] = max(0, min(100, (int) $p['focus']['y']));
        }
        // Bild anpassen (Core\ImageFx): nur wenn mitgeschickt – leer = Original
        if (array_key_exists('adjust', $p) && str_starts_with((string) $m['mime'], 'image/')) {
            $adj = \Core\ImageFx::normalize($p['adjust']);
            if ($adj === null || $adj === \Core\ImageFx::NONE) {
                return Response::json(['ok' => false, 'error' => __('Ungültige Bildanpassung.')], 422);
            }
            $upd['adjust'] = $adj !== '' ? $adj : null;
        }
        Media::db()->update('media', $upd, 'id = :id', ['id' => (int) $id]);
        if (isset($p['collections']) && is_array($p['collections'])) {
            Media::setCollections((int) $id, $p['collections']);
        }
        Media::forget((int) $id);
        $this->changed();
        return Response::json(['ok' => true, 'item' => Media::toJson(Media::find((int) $id))]);
    }

    /** Bild anpassen für alle Verwendungen ({adjust: "sepia s120"} – leer = Original), Core\ImageFx */
    public function adjust(Request $r, string $id): Response
    {
        $this->auth($r, 'media.upload');
        if ($e = $this->scope($r, true)) return $e;
        $t = $this->target((int) $id, true);
        if ($t instanceof Response) return $t;
        $m = Media::find((int) $t) ?? throw new HttpException(404);
        if (!str_starts_with((string) $m['mime'], 'image/')) {
            return Response::json(['ok' => false, 'error' => __('Nur Bilder lassen sich anpassen.')], 422);
        }
        $adj = \Core\ImageFx::normalize($r->post['adjust'] ?? '');
        if ($adj === null || $adj === \Core\ImageFx::NONE) {
            return Response::json(['ok' => false, 'error' => __('Ungültige Bildanpassung.')], 422);
        }
        Media::db()->update('media', ['adjust' => $adj !== '' ? $adj : null, 'updated_at' => now()], 'id = :id', ['id' => (int) $t]);
        Media::forget((int) $t);
        $this->changed();
        return Response::json(['ok' => true, 'item' => Media::toJson(Media::find((int) $t))]);
    }

    /** Bild im Rahmen: Standard für alle Verwendungen ({fit: "contain blur"} – leer = automatisch), Core\ImageFit */
    public function fit(Request $r, string $id): Response
    {
        $this->auth($r, 'media.upload');
        if ($e = $this->scope($r, true)) return $e;
        $t = $this->target((int) $id, true);
        if ($t instanceof Response) return $t;
        $m = Media::find((int) $t) ?? throw new HttpException(404);
        if (!str_starts_with((string) $m['mime'], 'image/')) {
            return Response::json(['ok' => false, 'error' => __('Nur für Bilder.')], 422);
        }
        $fit = \Core\ImageFit::normalize($r->post['fit'] ?? '');
        if ($fit === null) {
            return Response::json(['ok' => false, 'error' => __('Ungültige Einstellung für „Darstellung im Rahmen“.')], 422);
        }
        Media::db()->update('media', ['fit' => $fit !== '' ? $fit : null, 'updated_at' => now()], 'id = :id', ['id' => (int) $t]);
        Media::forget((int) $t);
        $this->changed();
        return Response::json(['ok' => true, 'item' => Media::toJson(Media::find((int) $t))]);
    }

    /** Bild im Rahmen: Farben des Kits als Vorschläge für den Hintergrund beim Einpassen */
    public function fitOptions(Request $r): Response
    {
        $this->auth($r);
        return Response::json(['ok' => true, 'presets' => \Core\ImageFit::presets()]);
    }

    /** Zuschnitt für ein Bildformat festlegen ({ratio, rect: {x,y,w,h}} – rect null = entfernen) */
    public function crop(Request $r, string $id): Response
    {
        $this->auth($r, 'media.upload');
        if ($e = $this->scope($r, true)) return $e;
        $t = $this->target((int) $id, true);
        if ($t instanceof Response) return $t;
        $id = (string) $t;
        $ratio = (string) ($r->post['ratio'] ?? '');
        $rect = is_array($r->post['rect'] ?? null) ? $r->post['rect'] : null;
        [$m, $err] = Media::setCrop((int) $id, $ratio, $rect);
        if (!$m) {
            return Response::json(['ok' => false, 'error' => $err], 422);
        }
        $this->changed();
        $src = Media::sources($m, $ratio);
        return Response::json(['ok' => true, 'item' => Media::toJson($m), 'sources' => $src]);
    }

    /**
     * Bild bearbeiten ({edit: {quad, quad_fit, flip_h, flip_v, rot, angle, fill, crop}} – leer/null = Original), Core\ImageEdit.
     * Pool-Dateien werden im Pool bearbeitet (wirkt auf allen Websites, die sie nutzen).
     */
    public function edit(Request $r, string $id): Response
    {
        $this->auth($r, 'media.upload');
        if ($e = $this->scope($r, true)) return $e;
        $t = $this->target((int) $id, true);
        if ($t instanceof Response) return $t;
        [$m, $err] = Media::setEdit((int) $t, $r->post['edit'] ?? null);
        if (!$m) {
            return Response::json(['ok' => false, 'error' => $err], 422);
        }
        $this->changed();
        if ((int) $t !== (int) $id) {
            // Verweis auf eine Pool-Datei: Antwort mit der ID dieser Website (Oberfläche arbeitet mit ihr weiter)
            Media::usePool(null);
            $m = Media::find((int) $id) ?? $m;
        }
        return Response::json(['ok' => true, 'item' => Media::toJson($m)]);
    }

    public function delete(Request $r, string $id): Response
    {
        $this->auth($r, 'media.delete');
        if ($e = $this->scope($r, true)) return $e;
        // Verwendete Dateien nicht löschen (Seiten, Datensätze, Einstellungen – Media::usages)
        if ($msg = Media::deleteBlocked((int) $id)) {
            return $r->wantsJson() ? Response::json(['ok' => false, 'error' => $msg, 'usages' => Media::usages((int) $id)], 409) : $this->back('/admin/media', 'error', $msg);
        }
        Media::delete((int) $id);
        return $r->wantsJson() ? Response::json(['ok' => true]) : $this->back('/admin/media', 'success', 'Datei gelöscht.');
    }

    /** Sammelaktionen: tag, untag, collect, uncollect, delete */
    public function bulk(Request $r): Response
    {
        $this->auth($r);
        if ($e = $this->scope($r, true)) return $e;
        $ids = array_values(array_filter(array_map('intval', (array) ($r->post['ids'] ?? []))));
        $action = (string) ($r->post['action'] ?? '');
        if (!can($action === 'delete' ? 'media.delete' : 'media.upload')) {
            return Response::json(['ok' => false, 'error' => __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.')], 403);
        }
        if (!$ids) {
            return Response::json(['ok' => false, 'error' => 'Keine Dateien ausgewählt.'], 422);
        }
        $kept = [];
        foreach ($ids as $id) {
            $m = Media::find($id);
            if (!$m) continue;
            if ($action === 'tag' || $action === 'untag') {
                $tag = Media::normTag((string) ($r->post['tag'] ?? ''));
                if ($tag === '') continue;
                $tags = Media::tagList($m['tags'] ?? '');
                $tags = $action === 'tag' ? array_merge($tags, [$tag]) : array_diff($tags, [$tag]);
                Media::db()->update('media', ['tags' => Media::tagString($tags), 'updated_at' => now()], 'id = :id', ['id' => $id]);
                Media::forget($id);
            } elseif ($action === 'delete') {
                if (Media::usages($id)) { $kept[] = trim(Media::title($m)) ?: (string) $m['original_name']; continue; }   // verwendet: bleibt
                Media::delete($id);
            }
        }
        $cid = (int) ($r->post['collection'] ?? 0);
        if ($action === 'collect' && $cid) {
            Media::addToCollection($cid, $ids);
        } elseif ($action === 'uncollect' && $cid) {
            Media::removeFromCollection($cid, $ids);
        }
        $this->changed();
        if ($kept) {
            return Response::json(['ok' => true, 'kept' => count($kept), 'message' => __('{n} verwendete Datei(en) nicht gelöscht: {names}. Bitte zuerst dort entfernen, wo sie verwendet werden.',
                ['n' => count($kept), 'names' => implode(', ', array_slice($kept, 0, 5)) . (count($kept) > 5 ? ' …' : '')])]);
        }
        return Response::json(['ok' => true]);
    }

    // ------------------------------------------------------------------ Sammlungen

    public function collectionCreate(Request $r): Response
    {
        $this->auth($r, 'media.upload');
        if ($e = $this->scope($r, true)) return $e;
        $name = trim((string) ($r->post['name'] ?? ''));
        if ($name === '') {
            return Response::json(['ok' => false, 'error' => 'Bitte einen Namen angeben.'], 422);
        }
        $id = Media::createCollection($name, (string) ($r->post['description'] ?? ''));
        return Response::json(['ok' => true, 'id' => $id]);
    }

    public function collectionUpdate(Request $r, string $id): Response
    {
        $this->auth($r, 'media.upload');
        if ($e = $this->scope($r, true)) return $e;
        $name = mb_substr(trim(strip_tags((string) ($r->post['name'] ?? ''))), 0, 120);
        if ($name !== '') {
            Media::db()->update('media_collections', ['name' => $name], 'id = :id', ['id' => (int) $id]);
        }
        $this->changed();
        return Response::json(['ok' => true]);
    }

    public function collectionDelete(Request $r, string $id): Response
    {
        $this->auth($r, 'media.delete');
        if ($e = $this->scope($r, true)) return $e;
        Media::db()->query('DELETE FROM media_collection_items WHERE collection_id = ?', [(int) $id]);
        Media::db()->query('DELETE FROM media_collections WHERE id = ?', [(int) $id]);
        $this->changed();
        return Response::json(['ok' => true]);
    }

    // ------------------------------------------------------------------ Upload in Stücken

    private function tmpDir(): string
    {
        $dir = site()->storage('uploads');
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        // Verwaiste Teil-Uploads nach 24 h entfernen
        if (random_int(1, 20) === 1) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                if (filemtime($f) < time() - 86400) @unlink($f);
            }
        }
        return $dir;
    }

    private function uploadPath(array $user, string $uploadId): string
    {
        if (!preg_match('~^[a-zA-Z0-9\-]{8,64}$~', $uploadId)) {
            throw new HttpException(422, 'Ungültige Upload-ID.');
        }
        return $this->tmpDir() . '/' . (int) $user['id'] . '-' . $uploadId;
    }

    /** Nimmt ein Stück entgegen (multipart: chunk, upload_id, index, total, size) */
    public function chunk(Request $r): Response
    {
        $user = $this->auth($r, 'media.upload');
        if ($e = $this->scope($r, true)) return $e;
        $path = $this->uploadPath($user, $r->str('upload_id'));
        $index = (int) $r->str('index');
        $total = (int) $r->str('total');
        $size = (int) $r->str('size');
        $file = $r->files['chunk'] ?? null;
        if (!$file || ($file['error'] ?? 1) !== UPLOAD_ERR_OK || $file['size'] > self::CHUNK_MAX) {
            return Response::json(['ok' => false, 'error' => 'Teil-Upload fehlgeschlagen.'], 422);
        }
        if ($size > Media::maxBytes() || $total < 1 || $total > 1000) {
            return Response::json(['ok' => false, 'error' => 'Datei ist zu groß (max. ' . (int) app()->config->get('media.max_upload_mb', 50) . ' MB).'], 413);
        }
        $meta = is_file("$path.json") ? (json_decode((string) file_get_contents("$path.json"), true) ?: []) : ['next' => 0];
        if ($index !== (int) $meta['next']) {
            return Response::json(['ok' => false, 'error' => 'Reihenfolge der Teile stimmt nicht.', 'expected' => $meta['next']], 409);
        }
        file_put_contents("$path.part", (string) file_get_contents($file['tmp_name']), $index === 0 ? LOCK_EX : FILE_APPEND | LOCK_EX);
        if (filesize("$path.part") > Media::maxBytes()) {
            @unlink("$path.part");
            @unlink("$path.json");
            return Response::json(['ok' => false, 'error' => 'Datei ist zu groß.'], 413);
        }
        $meta = ['next' => $index + 1, 'total' => $total, 'size' => $size];
        file_put_contents("$path.json", json_encode($meta));
        return Response::json(['ok' => true, 'complete' => $index + 1 === $total]);
    }

    /** Schließt einen Upload ab: prüft Alt-Text, importiert bzw. ersetzt die Datei */
    public function finalize(Request $r): Response
    {
        $user = $this->auth($r, 'media.upload');
        if ($e = $this->scope($r, true)) return $e;
        $p = $r->post;
        $path = $this->uploadPath($user, (string) ($p['upload_id'] ?? ''));
        $meta = is_file("$path.json") ? (json_decode((string) file_get_contents("$path.json"), true) ?: []) : [];
        if (!is_file("$path.part") || ($meta['next'] ?? 0) !== ($meta['total'] ?? -1)) {
            return Response::json(['ok' => false, 'error' => 'Upload unvollständig.'], 422);
        }
        if (filesize("$path.part") !== (int) ($meta['size'] ?? -1)) {
            @unlink("$path.part");
            @unlink("$path.json");
            return Response::json(['ok' => false, 'error' => 'Datei beschädigt übertragen – bitte erneut hochladen.'], 422);
        }
        $name = (string) ($p['name'] ?? 'datei');
        $alt = mb_substr(trim(strip_tags((string) ($p['alt'] ?? ''))), 0, 250);
        $opt = [
            'decorative' => !empty($p['decorative']), 'title' => (string) ($p['title'] ?? ''),
            'tags' => (string) ($p['tags'] ?? ''), 'collection' => (int) ($p['collection'] ?? 0),
        ];
        try {
            if (!empty($p['replace_id'])) {
                $replaceOpt = ['reset_focus' => !empty($p['reset_focus'])];
                if ($alt !== '') $replaceOpt['alt'] = $alt;
                [$m, $err] = Media::replace((int) $p['replace_id'], "$path.part", $name, $replaceOpt);
            } else {
                [$m, $err] = Media::import("$path.part", $name, $alt, $opt);
            }
        } finally {
            @unlink("$path.part");
            @unlink("$path.json");
        }
        if (!$m) {
            return Response::json(['ok' => false, 'error' => $err], 422);
        }
        $this->changed();
        // Editor.js-kompatibel: {success, file}
        return Response::json(['ok' => true, 'success' => 1, 'file' => Media::toJson($m)]);
    }

    /** Einfacher Upload (Fallback ohne JavaScript / Editor.js-Endpoint) */
    public function upload(Request $r): Response
    {
        $this->auth($r, 'media.upload');
        if ($e = $this->scope($r, true)) return $e;
        $file = $r->files['image'] ?? $r->files['file'] ?? null;
        $alt = mb_substr(strip_tags($r->str('alt')), 0, 250);
        if (!$file) {
            $msg = 'Keine Datei empfangen (evtl. größer als das PHP-Upload-Limit).';
            return $r->wantsJson() ? Response::json(['success' => 0, 'error' => $msg], 422) : $this->back('/admin/media', 'error', $msg);
        }
        [$m, $err] = Media::upload($file, $alt, ['decorative' => $r->str('decorative') === '1']);
        if ($r->wantsJson()) {
            return $m ? Response::json(['success' => 1, 'file' => Media::toJson($m)]) : Response::json(['success' => 0, 'error' => $err], 422);
        }
        return $m ? $this->back('/admin/media', 'success', 'Datei hochgeladen.') : $this->back('/admin/media', 'error', $err);
    }
}
