<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

use Core\Media;

/**
 * Startinhalte „praxis“: Hero-Foto der Startseite (demo/beratung.jpg) in die Mediathek übernehmen und im Hero-Block eintragen.
 * Foto: erwinbosman, Pixabay – https://pixabay.com/de/photos/arzt-patient-beratung-alter-klinik-10350071/ (Pixabay-Inhaltslizenz).
 * Ohne Foto (z. B. Datei entfernt) zeigt der Hero den animierten Verlauf – die Variante „Hintergrundbild“ fällt darauf zurück.
 */
function praxis_demo_photo(): void
{
    $file = __DIR__ . '/../demo/beratung.jpg';
    if (!is_file($file)) return;
    $tmp = tempnam(sys_get_temp_dir(), 'px') . '.jpg';
    copy($file, $tmp);   // Media::import verschiebt bzw. verarbeitet die Datei – das Original im Kit bleibt
    [$m, $err] = Media::import($tmp, 'praxis-beratung.jpg', 'Ärztin oder Arzt notiert sich etwas im Gespräch mit einem älteren Patienten (Beispielbild)', [
        'title' => 'Beratungsgespräch (Beispielbild)', 'tags' => 'praxis-demo',
        'credit' => 'Foto: erwinbosman / Pixabay – https://pixabay.com/de/photos/arzt-patient-beratung-alter-klinik-10350071/',
    ]);
    @unlink($tmp);
    if (!$m) { error_log('praxis: Hero-Foto nicht übernommen – ' . $err); return; }

    $home = app()->db->fetch('SELECT id, content_published FROM pages WHERE is_home = 1 ORDER BY id DESC LIMIT 1');
    if (!$home) return;
    $json = json_decode((string) $home['content_published'], true);
    if (!is_array($json['blocks'] ?? null)) return;
    foreach ($json['blocks'] as &$bl) {
        if (($bl['type'] ?? '') === 'hero' && empty($bl['data']['bg_image'])) $bl['data']['bg_image'] = (int) $m['id'];
    }
    unset($bl);
    $enc = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    app()->db->query('UPDATE pages SET content_published = ?, content_draft = ? WHERE id = ?', [$enc, $enc, (int) $home['id']]);
}
