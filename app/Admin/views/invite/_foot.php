<?php
/**
 * Fußzeile der Seiten vor der Anmeldung (Anmelden, Zwei-Faktor, Passwort, Einladung, Links aus E-Mails):
 * links der Rückweg, rechts das Zeichen (Logo der Agentur mit „Technik: KLXM Studio“ bzw. KLXM Studio), darunter auf
 * Wunsch die Support-Zeile der Agentur (System → Branding & Support). Am Telefon untereinander (resources/css/auth.css).
 * @var ?string $footHref Ziel des Rückwegs (Standard: Website)  @var ?string $footLabel  @var ?bool $footSupport
 */
$footHref ??= url('/');
$footLabel ??= __('Zur Website');
$footSupport ??= false;
$footSupportHtml = $footSupport ? \Core\Branding::supportHtml(true) : '';
?>
  <div class="auth-foot<?= \Core\Branding::active() ? ' auth-foot--brand' : '' ?>">
    <p class="adm-muted auth-foot__back"><a href="<?= e($footHref) ?>"><span aria-hidden="true">←</span> <?= e($footLabel) ?></a></p>
    <p class="adm-product auth-foot__mark"><?= cms_mark() ?></p>
    <?php if ($footSupportHtml !== ''): ?><div class="auth-foot__support"><?= $footSupportHtml ?></div><?php endif; ?>
  </div>
