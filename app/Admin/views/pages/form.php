<?php
use Core\Fields;
$isNew = $page === null;
$nf = !$isNew && \Core\NotFound::isPage($page);   // Seite „Nicht gefunden (404)“: feste Adresse, kein Menü, immer noindex
$err = fn($k) => isset($errors[$k]) ? '<p class="f-error" id="' . $k . '-e">' . e($errors[$k]) . '</p>' : '';
$inv = fn($k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="' . $k . '-e"' : '';
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/pages' . ($nf ? '#sonderseiten' : ''))) ?>">Seiten</a><?= $nf ? ' · ' . e(__('Nicht gefunden (404)')) : '' ?></p><h1><?= $isNew ? 'Neue Seite' : e($page['title']) ?></h1></div>
  <?php if (!$isNew): ?><a class="adm-btn adm-btn--primary" href="<?= e(\Core\Pages::url($page)) ?>?edit=1">Inhalte bearbeiten</a><?php endif; ?>
</header>

<?php if (!empty($placeholders)): // [Platzhalter] dieser Seite (Core\Dashboard\Metrics) – Inhalte bearbeitet der Frontend-Editor, daher dorthin je Block ?>
<section class="adm-card ph-card" id="platzhalter" aria-labelledby="ph-h">
  <h2 id="ph-h"><?= icon('brackets-curly') ?> <?= e(count($placeholders) === 1 ? __('1 Platzhalter auf dieser Seite') : __('{n} Platzhalter auf dieser Seite', ['n' => count($placeholders)])) ?></h2>
  <p class="adm-muted"><?= e(__('Die Texte der Blöcke bearbeiten Sie direkt auf der Website: „Im Frontend bearbeiten“ öffnet den Editor am passenden Block.')) ?></p>
  <?= \Core\Theme::capture(ROOT . '/app/Admin/views/pages/_placeholders.php', ['hits' => $placeholders, 'back' => '/admin/pages/' . (int) $page['id'] . '#platzhalter', 'showPage' => false, 'backend' => false]) ?>
</section>
<?php endif; ?>

<div class="adm-grid2 adm-grid2--wide">
  <form class="adm-card" method="post" action="<?= e(url($isNew ? '/admin/pages/new' : '/admin/pages/' . $page['id'])) ?>" novalidate>
    <?= csrf_field() ?>
    <?php if ($isNew): ?><input type="hidden" name="lang" value="<?= e($old['lang'] ?? '') ?>"><?php endif; ?>
    <h2>Seiteneinstellungen<?php if (\Core\Lang::multi()): ?> <span class="adm-badge"><?= e(strtoupper(\Core\Lang::norm($isNew ? ($old['lang'] ?? null) : $page['lang']))) ?></span><?php endif; ?></h2>
    <div class="f<?= isset($errors['title']) ? ' f--error' : '' ?>"><label for="title">Titel <span class="req">*</span></label>
      <input id="title" name="title" required maxlength="120" value="<?= e($old['title'] ?? '') ?>"<?= $inv('title') ?>><?= $err('title') ?></div>
    <?php if ($nf): ?>
    <p class="adm-inline-box"><?= e(__('Sonderseite „Nicht gefunden (404)“: erscheint bei jeder Adresse, die es nicht gibt – mit Status 404, nie im Menü, in der Sitemap oder in Suchmaschinen. Zum Bearbeiten öffnen Sie {path}.', ['path' => \Core\Pages::url($page) . '?edit=1'])) ?></p>
    <?php endif; ?>
    <?php if ($isNew || (!$page['is_home'] && !$nf)):
      $exclude = $isNew ? [] : array_merge([(int) $page['id']], \Core\Pages::descendantIds((int) $page['id']));
      $parentPath = !empty($old['parent_id']) && ($pp = \Core\Pages::find((int) $old['parent_id'])) ? $pp['path'] . '/' : ''; ?>
    <div class="f"><label for="parent_id">Übergeordnete Seite</label>
      <select id="parent_id" name="parent_id"><option value="">– oberste Ebene –</option>
        <?php foreach (\Core\Pages::flat() as $fp): if ($fp['is_home'] || in_array((int) $fp['id'], $exclude, true)) continue; ?>
        <option value="<?= (int) $fp['id'] ?>"<?= (int) ($old['parent_id'] ?? 0) === (int) $fp['id'] ? ' selected' : '' ?>><?= str_repeat('  ', (int) $fp['depth']) . ((int) $fp['depth'] ? '└ ' : '') . e($fp['title']) ?></option>
        <?php endforeach; ?>
      </select><p class="f-help">Unterseiten erhalten die Adresse der übergeordneten Seite als Präfix, z. B. /leistungen/vorsorge.</p></div>
    <?php if ($isNew && ($__tpls = \Core\PageTemplates::all())):
      // Vorschlag je übergeordneter Seite (für den Wechsel im Formular, admin.js [data-tpl-map])
      $__map = [];
      foreach (\Core\Pages::flat() as $fp) { $sg = \Core\PageTemplates::suggested((int) $fp['id']); if ($sg !== null) $__map[(int) $fp['id']] = $sg; }
      $__cur = (string) ($old['template'] ?? ''); ?>
    <fieldset class="f tpl-pick" data-tpl-map="<?= e(json_encode($__map)) ?>">
      <legend><?= e(__('Vorlage')) ?></legend>
      <div class="tpl-pick__grid">
        <label class="tpl-pick__opt"><input class="sr-only" type="radio" name="template" value=""<?= $__cur === '' ? ' checked' : '' ?>>
          <span class="tpl-pick__ico" aria-hidden="true"><?= icon('browser') ?></span>
          <span class="tpl-pick__txt"><b><?= e(__('Leere Seite')) ?></b><small><?= e(__('Ohne Blöcke beginnen.')) ?></small></span></label>
        <?php foreach ($__tpls as $__t): ?>
        <label class="tpl-pick__opt"><input class="sr-only" type="radio" name="template" value="<?= (int) $__t['i'] ?>"<?= $__cur === (string) $__t['i'] ? ' checked' : '' ?>>
          <span class="tpl-pick__ico" aria-hidden="true"><?= icon($__t['icon'] ?: 'stamp') ?></span>
          <span class="tpl-pick__txt"><b><?= e($__t['label']) ?></b><?php if ($__t['description'] !== ''): ?><small><?= e($__t['description']) ?></small><?php endif; ?></span></label>
        <?php endforeach; ?>
      </div>
      <p class="f-help"><?= e(__('Die Blöcke der Vorlage werden übernommen und lassen sich danach frei ändern.')) ?></p>
    </fieldset>
    <?php endif; ?>
    <div class="f<?= isset($errors['slug']) ? ' f--error' : '' ?>"><label for="slug">Adresse (URL)</label>
      <div class="adm-prefix"><span><?= e(site_url()) ?>/<?= e($parentPath) ?></span><input id="slug" name="slug" value="<?= e($old['slug'] ?? '') ?>" placeholder="wird aus dem Titel erzeugt"<?= $inv('slug') ?>></div><?= $err('slug') ?>
      <?php if (!isset($errors['slug']) && !empty($page) && \Core\Http\Controllers\Admin\PageController::reservedSlug((string) $page['slug'], $page['parent_id'] ? (int) $page['parent_id'] : null, $page['lang'] ?: null)): ?>
      <p class="f-warn" role="status"><?= e(__('Achtung: Unter /{slug} liegen Dateien des Systems – diese Seite ist dort für Besucher nicht erreichbar. Bitte eine andere Adresse wählen.', ['slug' => $page['slug']])) ?></p>
      <?php endif; ?></div>
    <?php endif; ?>
    <div class="f"><label for="meta_title"><?= e(__('Titel für Suchmaschinen (optional)')) ?></label>
      <input id="meta_title" name="meta_title" maxlength="120" data-max="<?= \Core\AI\SeoCheck::TITLE_MAX - mb_strlen(\Core\AI\Assist::titleSuffix()) ?>" value="<?= e($old['meta_title'] ?? '') ?>" placeholder="<?= e($old['title'] ?? '') ?>" aria-describedby="meta_title-h">
      <p class="f-help" id="meta_title-h"><?= e(__('Leer = Seitentitel. Suchmaschinen zeigen etwa 60 Zeichen; angehängt wird „{suffix}“.', ['suffix' => trim(\Core\AI\Assist::titleSuffix(), ' |') ?: '–'])) ?></p></div>
    <div class="f"><label for="meta_description">Beschreibung für Suchmaschinen</label>
      <textarea id="meta_description" name="meta_description" rows="3" maxlength="300" data-max="160"><?= e($old['meta_description'] ?? '') ?></textarea>
      <p class="f-help">Ideal 120–160 Zeichen. Leer = Standard aus <?= e(app()->theme->settingsTitle()) ?> → SEO.</p></div>
    <?= Fields::renderField(['name' => 'og_image', 'label' => 'Vorschaubild (soziale Netzwerke)', 'type' => 'media'], $old['og_image'] ?? null, [], 'x') ?>
    <?php if ($isNew || !$page['is_home']): ?>
    <div class="f"><label for="status">Status</label>
      <select id="status" name="status"><option value="draft"<?= ($old['status'] ?? '') !== 'published' ? ' selected' : '' ?>>Entwurf (nicht öffentlich)</option><option value="published"<?= ($old['status'] ?? '') === 'published' ? ' selected' : '' ?>>Online</option></select></div>
    <?php endif; ?>
    <?php if ($isNew || (!$page['is_home'] && !$nf)): ?>
    <div class="f f--bool"><input type="hidden" name="menu" value="0"><label class="f-check"><input type="checkbox" name="menu" value="1"<?= !empty($old['menu']) ? ' checked' : '' ?>> <span>Im Hauptmenü zeigen</span></label></div>
    <div class="f"><label for="nav_title">Beschriftung im Menü (optional)</label><input id="nav_title" name="nav_title" maxlength="60" value="<?= e($old['nav_title'] ?? '') ?>" placeholder="<?= e($old['title'] ?? 'wie der Titel') ?>"></div>
    <?php endif; ?>
    <?php if (!$nf): ?>
    <div class="f f--bool"><input type="hidden" name="noindex" value="0"><label class="f-check"><input type="checkbox" name="noindex" value="1"<?= !empty($old['noindex']) ? ' checked' : '' ?>> <span>Nicht in Suchmaschinen / Sitemap aufnehmen</span></label></div>
    <?php endif; ?>
    <div class="adm-form-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= $isNew ? 'Seite anlegen' : 'Speichern' ?></button></div>
  </form>

  <?php if (!$isNew): ?>
  <div>
    <?= /* KI-Assistent & SEO-Check (Core\AI) */ \Core\Theme::capture(ROOT . '/app/Admin/views/ai/_page.php', ['page' => $page]) ?>
    <?= /* Erweiterungen (Extension::pagePanel), z. B. Feedback & Freigabe */ \Core\Extensions::pagePanels($page) ?>
    <?php if (!$nf && \Core\Features::on('landings') && can('system.manage')): $lps = \Core\Landings::forPage($page); // Landingpages mit eigenen Domains (Core\Landings) ?>
    <section class="adm-card" aria-labelledby="pg-landing-h">
      <h2 id="pg-landing-h"><?= e(__('Landingpage-Domain')) ?></h2>
      <?php if ($lps): ?>
      <ul class="adm-list">
        <?php foreach ($lps as $lp): ?>
        <li><a href="<?= e((string) $lp->absUrl($page)) ?>" target="_blank" rel="noopener noreferrer"><?= e((string) $lp->absUrl($page)) ?></a>
          <span class="adm-muted"><?= e($lp->mode === 'own' ? __('Canonical: Landing-Domain') : __('Canonical: Hauptdomain')) ?><?= $lp->active ? '' : ' · ' . e(__('inaktiv')) ?></span>
          <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/landingpages/' . $lp->id)) ?>"><?= e(__('Bearbeiten')) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <p class="adm-muted"><?= e(__('Diese Seite (mit Unterseiten) unter einer eigenen Domain zeigen, z. B. für eine Kampagne.')) ?></p>
      <a class="adm-btn adm-btn--small" href="<?= e(url('/admin/landingpages/new?page=' . (int) $page['id'])) ?>"><?= e(__('Landingpage anlegen')) ?></a>
      <?php endif; ?>
    </section>
    <?php endif; ?>
    <section class="adm-card">
      <h2>Versionen</h2>
      <?php if (\Core\Pages::hasUnpublished($page) && $page['content_published'] !== null): ?>
      <div class="adm-inline-box adm-discard">
        <strong>Unveröffentlichte Änderungen</strong>
        <p class="adm-muted">Der Entwurf weicht von der Online-Fassung ab. Verwerfen setzt ihn auf die veröffentlichte Fassung zurück; der Entwurf bleibt unten als Version gesichert.</p>
        <form method="post" action="<?= e(url('/admin/pages/' . $page['id'] . '/discard')) ?>" data-confirm="Entwurf verwerfen?"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--danger">Entwurf verwerfen</button></form>
      </div>
      <?php endif; ?>
      <p class="adm-muted">Die letzten <?= (int) app()->config->get('revisions', 20) ?> Stände. Wiederherstellen legt einen Entwurf an.</p>
      <ul class="adm-list adm-list--rev">
        <?php foreach ($revisions as $i => $rv): ?>
        <li><span><?= e(date('d.m.Y H:i', strtotime($rv['created_at']))) ?> · <?= e($rv['note']) ?><small><?= e($rv['email'] ?? 'System') ?></small></span>
          <?php if ($i > 0): ?><form method="post" action="<?= e(url('/admin/pages/' . $page['id'] . '/restore/' . $rv['id'])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost">Wiederherstellen</button></form><?php else: ?><span class="adm-badge">aktuell</span><?php endif; ?></li>
        <?php endforeach; ?>
        <?php if (!$revisions): ?><li class="adm-muted">Noch keine Versionen.</li><?php endif; ?>
      </ul>
    </section>
    <?php if (!$page['is_home']): ?>
    <section class="adm-card adm-card--danger">
      <h2>Seite löschen</h2>
      <form method="post" action="<?= e(url('/admin/pages/' . $page['id'] . '/delete')) ?>" data-confirm="Seite „<?= e($page['title']) ?>“ endgültig löschen?"><?= csrf_field() ?><button class="adm-btn adm-btn--danger">Endgültig löschen</button></form>
    </section>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
