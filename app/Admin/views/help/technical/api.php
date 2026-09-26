<?php /** Entwicklerhandbuch · REST-API (Endpunkte live aus Core\Api\OpenApi) · @var array $openapi */ ?>
  <p class="lead">JSON-API unter <code><?= e($api) ?></code>. Beschreibung als <a href="<?= e($api) ?>/openapi.json" target="_blank" rel="noopener">OpenAPI 3.1</a> – importierbar in Postman, Insomnia, Swagger UI oder als Tool-Definition für Agenten.</p>
  <h3>Authentifizierung</h3>
  <ul>
    <li>Tokens erzeugt die Administration unter <a href="<?= e(url('/admin/api-tokens')) ?>">API &amp; MCP</a> (Recht <code>api.manage</code>). Berechtigungen: <span class="tag tag--read">read</span> oder <span class="tag tag--write">write</span>; optional mit Ablaufdatum (gilt einschließlich dieses Tages). Modus für Schreibzugriffe: „Direkt übernehmen“ oder „Zur Freigabe“ (Standard für neue Tokens) – dann antworten schreibende Endpunkte mit <b>HTTP 202</b> und einem Verweis auf <code>GET /changes/{id}</code> (siehe <a href="#freigabe">Eingereicht</a>).</li>
    <li>Header <code>Authorization: Bearer cms_…</code> (alternativ <code>X-Api-Key: cms_…</code>). Tokens werden nur als SHA-256-Hash gespeichert.</li>
    <li>Rate-Limit: <?= \Core\Api\Tokens::RATE_LIMIT ?> Anfragen je <?= \Core\Api\Tokens::RATE_WINDOW / 60 ?> Minuten und Token (HTTP 429).</li>
    <li><code>GET /public</code> ist ohne Token abrufbar (Name, URL und was das Kit über <code>project.public_info</code> freigibt, ggf. Öffnungszeiten und Hinweis).</li>
  </ul>
  <h3>Konventionen</h3>
  <ul>
    <li>Erfolg: <code>{"data": …}</code> · Fehler: <code>{"error": {"status", "message", "errors"?}}</code> mit 401 (inkl. <code>WWW-Authenticate: Bearer</code>)/403/404/409/413/422/429/500; Einreichungen zur Freigabe: 202.</li>
    <li>Seiten per ID, Slug oder <code>home</code>. Inhaltsänderungen werden als <b>Entwurf</b> mit Revision gespeichert; <code>"publish": true</code> bzw. <code>POST /pages/{page}/publish</code> veröffentlicht.</li>
    <li>Blöcke: <code>{id, type, data, section}</code> – Felder laut <code>GET /block-types</code>. Fehlende Pflichtfelder blockieren Entwürfe nicht, werden aber als <code>warnings</code> gemeldet.</li>
    <li>Einstellungs-Updates sind Teil-Updates (<code>PATCH</code>) und gelten sofort; Validierung wie im Admin.</li>
  </ul>
  <h3>Sprachen, Karten, Multi-Site</h3>
  <ul>
    <li><b>Sprachkontext:</b> <code>?lang=en</code> an jeder Anfrage. Dann gelten Pfade und <code>home</code> in dieser Sprache, <code>GET /pages</code> und <code>GET /data/{tabelle}</code> filtern darauf (ohne <code>lang</code>: alle Sprachen mit <code>lang</code>/<code>_lang</code> und <code>translations</code>/<code>_translations</code>), neue Seiten und Einträge entstehen in dieser Sprache, Medientexte kommen übersetzt.</li>
    <li><b>Einstellungen je Sprache:</b> <code>GET /settings?lang=en</code> liefert die Werte wie auf der englischen Website plus <code>_translated</code>; <code>PATCH /settings?lang=en</code> ändert nur übersetzbare Felder (<code>translatable</code> in <code>/settings/schema</code>), leere Werte = Rückfall.</li>
    <li><b>Übersetzen:</b> <code>POST /pages/{page}/translate</code> bzw. <code>POST /data/{tabelle}/{id}/translate</code> mit <code>{"lang": "en"}</code> legt eine verknüpfte Kopie als Entwurf an.</li>
    <li><b>Medien:</b> <code>PATCH /media/{id}</code> mit <code>{"i18n": {"en": {"alt": "…", "title": "…"}}}</code>.</li>
    <li><b>Karten:</b> <code>GET /geocode?q=Adresse</code> → Koordinaten; <code>value</code> direkt in Felder vom Typ <code>geo</code> schreiben.</li>
    <li><b>Multi-Site:</b> Tokens gehören zu einer Website; die API arbeitet immer mit der Website der aufgerufenen Domain. <code>GET /me</code> nennt <code>site</code>, Kit und Sprachen.</li>
  </ul>
  <h3>Endpunkte</h3>
  <div class="doc-scroll"><table class="doc-table">
    <tr><th>Methode</th><th>Pfad</th><th>Beschreibung</th></tr>
    <?php foreach ($openapi['paths'] as $path => $ops): foreach ($ops as $m => $op): if ($m === 'parameters') continue;
      $write = in_array($m, ['post', 'put', 'patch', 'delete'], true); ?>
    <tr><td><span class="m m--<?= $m ?>"><?= strtoupper($m) ?></span></td><td><code><?= e($path) ?></code></td>
      <td><?= e($op['summary']) ?> <?= isset($op['security']) && $op['security'] === [] ? '<span class="tag">öffentlich</span>' : ($write ? '<span class="tag tag--write">write</span>' : '') ?></td></tr>
    <?php endforeach; endforeach; ?>
  </table></div>
  <p>Seiten akzeptieren beim Anlegen und Ändern <code>parent</code>, <code>menu</code>, <code>nav_title</code>, <code>position</code>; Unterseiten per ID adressieren. Einträge: <code>GET /data/{tabelle}/{id}</code> nimmt ID oder Slug, <code>PATCH</code>/<code>DELETE</code> nur die numerische ID.</p>
  <h3>Beispiele</h3>
  <pre><code># Englische Fassung der Startseite anlegen und einen Text übersetzen
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"lang":"en"}' <?= e($api) ?>/pages/home/translate
curl -X PATCH -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"data":{"title":"Welcome"}}' "<?= e($api) ?>/pages/home/blocks/BLOCK_ID?lang=en"

# Standort für die Karte ermitteln
curl -H "Authorization: Bearer $TOKEN" "<?= e($api) ?>/geocode?q=Hauptstra%C3%9Fe%201%2C%20Musterstadt"

# Öffnungszeiten lesen (nur wenn das Kit sie anbietet)
curl -H "Authorization: Bearer $TOKEN" <?= e($api) ?>/hours

# Telefonnummer ändern (sofort wirksam)
curl -X PATCH -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"telefon":"+49 211 123456"}' <?= e($api) ?>/settings

# Öffnungszeiten ersetzen
curl -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '[
  {"tag":"Mo","von":"09:00","bis":"18:00","pause_von":"13:00","pause_bis":"14:00"},
  {"tag":"Sa","von":"10:00","bis":"14:00"}
]' <?= e($api) ?>/hours

# Hinweisbox oben auf der Startseite einfügen und veröffentlichen
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{
  "type":"notice","position":1,"publish":true,
  "data":{"items":[{"style":"important","title":"Geschlossen","text":"Am 3.10. geschlossen."}]}
}' <?= e($api) ?>/pages/home/blocks

# Bild hochladen
curl -X POST -H "Authorization: Bearer $TOKEN" -F file=@team.jpg -F alt="Das Team" -F tags="team" <?= e($api) ?>/media

# Zuschnitt 16:9 setzen (Anteile des Originals)
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"ratio":"16:9","rect":{"x":0.1,"y":0.2,"w":0.8,"h":0.45}}' <?= e($api) ?>/media/12/crop</code></pre>
  <p>Bilder brauchen einen Alt-Text (mind. 3 Zeichen) oder <code>decorative=true</code>. Mediathek-Filter: <code>?kind=image|pdf|video|audio|all&amp;q=&amp;tag=&amp;collection=</code> sowie die Prüf-Filter <code>noalt</code>, <code>missing_lang</code>, <code>notitle</code>, <code>nocaptions</code>, <code>notranscript</code>. MCP-Tools: <code>list_media</code>, <code>upload_media</code>, <code>update_media</code>, <code>crop_media</code>.</p>
