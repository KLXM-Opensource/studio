<?php /** Menü-Schaltfläche (mobil) – ohne JavaScript ausgeblendet, das Menü steht dann unter dem Kopf. */ ?>
<button type="button" class="menu-btn" aria-expanded="false" aria-controls="site-nav" data-label-open="<?= e(lt('Menü')) ?>" data-label-close="<?= e(lt('Schließen')) ?>">
  <span class="menu-btn__bars" aria-hidden="true"></span><span class="menu-btn__label"><?= e(lt('Menü')) ?></span>
</button>
