<?php
/**
 * Übersicht aller Symbole (Core\Icons, Phosphor duotone) nach Themen – für Redaktion (Symbolauswahl) und Theme-Entwicklung.
 * Filter: resources/js/_iconpicker.js (initIconGallery). Symbole als Platzhalter (data-lz): das Sprite eines Themas lädt erst,
 * wenn es ins Bild scrollt (lazyIcons in resources/js/_icons.js). @var array $catalog
 */
$en = str_starts_with(\Core\I18n::locale(), 'en');
$helpTab = 'icons';
include ROOT . '/app/Admin/views/support/_helptabs.php';
$total = count($catalog['icons']);
?>
<div class="adm-head">
  <div>
    <h1><?= e(__('Symbole')) ?></h1>
    <p class="adm-muted"><?= e(__('{n} Symbole in {t} Themen – zweifarbig (eine Farbe mit durchscheinender Fläche). Wählbar z. B. als Symbol einer Datentabelle unter „Felder & Einstellungen“.', ['n' => $total, 't' => count($catalog['topics'])])) ?></p>
  </div>
</div>

<div class="icg" data-icon-gallery>
  <div class="icg__bar">
    <label class="icp__search icg__search"><?= icon('magnifying-glass') ?><input type="search" data-icon-filter placeholder="<?= e(__('Suchen, z. B. Kalender, Team, Arzt …')) ?>" aria-label="<?= e(__('Symbole durchsuchen')) ?>" autocomplete="off" spellcheck="false"></label>
    <p class="icg__count adm-muted" aria-live="polite" data-icon-count></p>
  </div>
  <nav class="icg__topics" aria-label="<?= e(__('Themen')) ?>">
    <?php foreach ($catalog['topics'] as $tp): ?><a href="#sym-<?= e($tp['key']) ?>"><?= e($en ? $tp['en'] : $tp['de']) ?></a><?php endforeach; ?>
  </nav>
  <?php foreach ($catalog['topics'] as $tp): ?>
  <section class="icg__topic adm-card" id="sym-<?= e($tp['key']) ?>" aria-labelledby="sym-<?= e($tp['key']) ?>-h">
    <h2 id="sym-<?= e($tp['key']) ?>-h"><?= e($en ? $tp['en'] : $tp['de']) ?> <small class="adm-muted"><?= count($tp['icons']) ?></small></h2>
    <ul class="icg__grid">
      <?php foreach ($tp['icons'] as $n): [$de, $enl, $kw, $tags] = $catalog['icons'][$n] + [3 => '']; ?>
      <li class="icg__item" data-search="<?= e(mb_strtolower("$de $enl $kw $tags")) ?>"><span class="icg__ico"><svg class="ico" aria-hidden="true" focusable="false" width="1em" height="1em" data-lz="<?= e($n) ?>"></svg></span><span class="icg__label"><?= e($en ? $enl : $de) ?></span><code><?= e($n) ?></code></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endforeach; ?>
  <p class="icg__empty adm-muted" data-icon-empty hidden><?= e(__('Keine Symbole gefunden.')) ?></p>
  <p class="adm-muted icg__license"><?= e(__('Symbole: Phosphor Icons (Stil „duotone“), MIT-Lizenz.')) ?> <a href="<?= e(base_path() . '/assets/icons/LICENSE.txt') ?>"><?= e(__('Lizenz')) ?></a> · <a href="<?= e(url('/admin/hilfe/technik#symbole')) ?>"><?= e(__('Technische Dokumentation')) ?></a></p>
</div>
