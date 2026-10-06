<?php
/**
 * Kopfbereich – Variante aus Verwaltung → Design → „Kopfbereich“ (design('header')): inline | centered | split | floating | rail.
 *
 * Intrinsisch statt Breakpoints: Der Kopf ist ein Container (container-type: inline-size). Die Klasse fit-NN nennt die
 * geschätzte Breite in rem, die das Menü in einer Zeile braucht (galerie_nav_fit); ist der Kopf mindestens so breit,
 * zeigt die Container-Query das Menü in der Leiste, sonst die Menü-Schaltfläche. site.js prüft zusätzlich, ob das Menü
 * wirklich passt (is-overflow). „Seitenleiste“: Der Kopf steht als Leiste links, sobald neben dem Inhalt Platz ist
 * (Flex-Umbruch in layout.php) – sonst wie „Leiste“ oben.
 *
 * Menü-Schaltfläche → Seitenblatt (#mnav) als HTML-popover: funktioniert ohne JavaScript (Escape, Klick daneben schließt,
 * Scrollen gesperrt per :has()); site.js ergänzt Fokusfalle, inerten Rest der Seite und den Fokus beim Öffnen.
 */
$variant = preg_replace('~[^a-z]~', '', (string) design('header')) ?: 'inline';
$menu = \Core\HeaderActions::menu(galerie_menu());   // Stil „Menüpunkt“: Ziel des Handlungsaufrufs nicht doppelt im Menü
$langs = language_links();
$cta = galerie_header_cta();
// Kopfbereich-Aktionen (Design → „Kopfbereich: Suche & Aktionen“, Core\HeaderActions): Suche, Handlungsaufruf, Kontakt-Chip …
$actions = header_actions('bar', ['compact' => true]);
$searchMenu = \Core\Search\Search::form('menu');
$brandHref = !empty(app()->currentPage['is_home']) ? '#main' : url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$fit = galerie_nav_fit($menu, $cta, $langs, $actions !== '', $variant);
$hasSheet = $menu || $langs || $cta || $searchMenu !== '';
?>
<header class="hdr hdr--<?= e($variant) ?> <?= e($fit) ?>" data-header>
  <?php // Infoleiste (Design → „Infoleiste über dem Kopfbereich“): kurzer Text, Telefon, E-Mail, Social Media
  if (($meta = (string) design('topbar')) !== '' && $meta !== 'off'):
      $mText = trim((string) setting('topbar_text')); $mPhone = galerie_phone(); $mTel = galerie_phone_href(); $mMail = galerie_email(); $mSocial = galerie_social(); ?>
  <div class="hdr__meta">
    <div class="hdr__meta-in">
      <?php if ($mText !== ''): ?><p class="hdr__meta-text"><?= e($mText) ?></p><?php endif; ?>
      <ul class="hdr__meta-list" role="list">
        <?php if ($mPhone !== '' && $mTel): ?><li><a href="<?= e($mTel) ?>"><?= icon('phone') ?><span><?= e($mPhone) ?></span></a></li><?php endif; ?>
        <?php if ($mMail !== ''): ?><li class="hdr__meta-mail"><a href="mailto:<?= e($mMail) ?>"><?= icon('envelope-simple') ?><span><?= e($mMail) ?></span></a></li><?php endif; ?>
        <?php foreach ($mSocial as $s): ?><li class="hdr__meta-social"><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener me"><?= e($s['label']) ?><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a></li><?php endforeach; ?>
      </ul>
    </div>
  </div>
  <?php endif; ?>
  <div class="hdr__bar">
    <?= app()->theme->partial('brand', ['href' => $brandHref, 'class' => 'hdr__brand']) ?>
    <?= header_actions('center') ?>
    <?php if ($menu || $langs): ?>
    <div class="hdr__nav">
      <?php if ($menu): ?><nav class="hnav" aria-label="<?= e(lt('Hauptnavigation')) ?>"><?= galerie_nav_inline($menu) ?></nav><?php endif; ?>
      <?php if ($langs): ?><?= header_actions_lang($langs, 'hdr__lang') ?><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="hdr__tools">
      <?= $actions ?>
      <?php if ($hasSheet): ?>
      <button type="button" class="menu-btn" popovertarget="mnav" aria-controls="mnav" aria-haspopup="dialog">
        <span class="menu-btn__bars" aria-hidden="true"></span><span class="menu-btn__label"><?= e(lt('Menü')) ?></span>
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?= header_actions('below', ['wrap' => 'hdr__below']) ?>
</header>
