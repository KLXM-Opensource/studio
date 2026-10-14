<?php
/**
 * System → Branding & Support (BrandingController, Core\Branding): Logo und Name der Agentur statt der Produktmarke, Support-Angaben.
 * Gilt für die ganze Installation (im Netzwerk für alle Websites).
 * @var array $b  @var bool $network
 */
$s = $b['support'];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/system')) ?>"><?= e(__('System')) ?></a></p><h1><?= e(__('Branding & Support')) ?></h1>
    <p class="adm-muted"><?= e($network
      ? __('Ihr Logo und Ihre Support-Angaben für alle Websites dieser Installation – auf der Anmeldeseite, in der Werkzeugleiste der Website und unter Hilfe & Support. Nur die Netzwerk-Administration kann sie ändern.')
      : __('Ihr Logo und Ihre Support-Angaben – auf der Anmeldeseite, in der Werkzeugleiste der Website und unter Hilfe & Support.')) ?></p></div>
</header>

<form class="set-page brand-page" method="post" action="<?= e(url('/admin/system/branding')) ?>" enctype="multipart/form-data" novalidate>
  <?= csrf_field() ?>
  <section class="set-group" aria-labelledby="br-h">
    <h2 id="br-h" class="set-group__title"><?= e(__('Logo und Name')) ?></h2>
    <div class="adm-card adm-fields">
      <div class="f"><label for="br-name"><?= e(__('Name der Agentur')) ?></label>
        <input id="br-name" name="name" maxlength="80" value="<?= e($b['name']) ?>" placeholder="<?= e(__('z. B. Muster Webdesign')) ?>" aria-describedby="br-name-h">
        <p class="f-help" id="br-name-h"><?= e(__('Erscheint als Text, solange kein Logo hochgeladen ist, und als Beschriftung des Logos.')) ?></p></div>
      <?php foreach (['logo' => __('Logo'), 'logo_dark' => __('Logo für dunkle Darstellung (optional)')] as $k => $label): $url = $b[$k] !== '' ? \Core\Branding::logoUrl($k === 'logo_dark') : null; ?>
      <div class="f brand-logo">
        <label for="br-<?= $k ?>"><?= e($label) ?></label>
        <?php if ($url): ?><p class="brand-logo__prev brand-logo__prev--<?= $k === 'logo_dark' ? 'dark' : 'light' ?>"><img src="<?= e($url) ?>" alt=""></p>
        <label class="f-check"><input type="checkbox" name="remove_<?= $k ?>" value="1"> <span><?= e(__('Entfernen')) ?></span></label><?php endif; ?>
        <input id="br-<?= $k ?>" type="file" name="<?= $k ?>" accept=".svg,.png,.jpg,.jpeg,.webp,image/svg+xml,image/png,image/jpeg,image/webp">
        <p class="f-help"><?= e($k === 'logo' ? __('SVG (empfohlen, wird bereinigt), PNG, JPG oder WebP, höchstens 1 MB. Querformat, wirkt am besten etwa 160 × 40 px.') : __('Für die dunkle Darstellung der Verwaltung – sonst gilt das Logo oben.')) ?></p>
      </div>
      <?php endforeach; ?>
      <div class="f f--bool"><input type="hidden" name="show_product" value="0"><label class="f-check f-check--switch"><input type="checkbox" role="switch" name="show_product" value="1"<?= $b['show_product'] ? ' checked' : '' ?>> <span><?= e(__('Klein „Technik: {product}“ anzeigen', ['product' => CMS_NAME])) ?></span></label></div>
      <?php if (\Core\Branding::active()): ?><p class="brand-preview"><span class="adm-muted"><?= e(__('Vorschau:')) ?></span> <?= cms_mark() ?></p><?php endif; ?>
    </div>
  </section>

  <section class="set-group" aria-labelledby="bs-h">
    <h2 id="bs-h" class="set-group__title"><?= e(__('Support-Angaben')) ?></h2>
    <div class="adm-card adm-fields">
      <p class="f-help"><?= e(__('Erscheinen unter Hilfe & Support, in „Hilfe & Einstieg“ der Übersicht, bei „Neue Meldung“ und als kurze Zeile auf der Anmeldeseite. Leer = nichts davon.')) ?></p>
      <div class="f f--half"><label for="bs-name"><?= e(__('Ansprechpartner oder Team')) ?></label><input id="bs-name" name="support[name]" maxlength="80" value="<?= e($s['name']) ?>" placeholder="<?= e(__('z. B. Support Muster Webdesign')) ?>"></div>
      <div class="f f--half"><label for="bs-phone"><?= e(__('Telefon')) ?></label><input id="bs-phone" type="tel" name="support[phone]" maxlength="40" value="<?= e($s['phone']) ?>"></div>
      <div class="f f--half"><label for="bs-email"><?= e(__('E-Mail')) ?></label><input id="bs-email" type="email" name="support[email]" maxlength="120" value="<?= e($s['email']) ?>"></div>
      <div class="f f--half"><label for="bs-web"><?= e(__('Website')) ?></label><input id="bs-web" name="support[web]" maxlength="200" value="<?= e($s['web']) ?>" placeholder="https://"></div>
      <div class="f"><label for="bs-hours"><?= e(__('Erreichbarkeit (optional)')) ?></label><input id="bs-hours" name="support[hours]" maxlength="120" value="<?= e($s['hours']) ?>" placeholder="<?= e(__('z. B. Mo–Fr 9–17 Uhr')) ?>"></div>
      <div class="f"><label for="bs-note"><?= e(__('Hinweis (optional)')) ?></label><textarea id="bs-note" name="support[note]" rows="3" maxlength="600"><?= e($s['note']) ?></textarea></div>
    </div>
  </section>
  <div class="adm-form-actions"><button class="adm-btn adm-btn--primary"><?= e(__('Speichern')) ?></button></div>
</form>
