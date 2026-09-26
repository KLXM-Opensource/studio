<?php /** Menü-Schaltfläche (Mobilmenü bzw. Vollbild-Menü „Minimal“) – ohne JavaScript ausgeblendet, das Menü ist dann sichtbar. */ ?>
<button type="button" class="menu-btn" aria-expanded="false" aria-controls="site-nav" data-label-open="<?= e(lt('Menü')) ?>" data-label-close="<?= e(lt('Schließen')) ?>">
  <span class="menu-btn__bars" aria-hidden="true"></span><span class="menu-btn__label"><?= e(lt('Menü')) ?></span>
</button>
