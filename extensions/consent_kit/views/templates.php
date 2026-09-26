<?php
/** Vorlagen zur Auswahl (Suche + Gruppenfilter, ohne JavaScript: vollständige Liste). @var array $presets  @var array $existing */
use MyCms\Consent\Compiler;
use MyCms\Consent\I18nLang;
use MyCms\Consent\Repository;

$lang = I18nLang::admin();
?>
<section class="adm-card">
  <h2><?= e(__('Dienst aus Vorlage')) ?></h2>
  <p class="adm-muted"><?= e(__('{n} Vorlagen mit Cookies, Laufzeiten und Quellen (geprüft gegen die Dokumentation der Anbieter). Nach dem Hinzufügen sind nur noch Kennungen einzutragen; der Dienst startet ausgeschaltet.', ['n' => count($presets)])) ?></p>
  <div class="ck-filter" data-ck-filter hidden>
    <label class="sr-only" for="ck-q"><?= e(__('Vorlagen durchsuchen')) ?></label>
    <input type="search" id="ck-q" placeholder="<?= e(__('Suchen, z. B. Matomo, Maps, Pixel …')) ?>" data-ck-q>
    <label class="sr-only" for="ck-g"><?= e(__('Gruppe')) ?></label>
    <select id="ck-g" data-ck-g><option value=""><?= e(__('Alle Gruppen')) ?></option>
      <?php foreach (Repository::GROUPS as $k => [$n]): ?><option value="<?= e($k) ?>"><?= e(__($n)) ?></option><?php endforeach; ?></select>
    <span class="adm-muted" aria-live="polite" data-ck-count></span>
  </div>
  <ul class="ck-presets">
    <?php foreach ($presets as $k => $p): ?>
    <li data-group="<?= e((string) $p['group']) ?>" data-text="<?= e(mb_strtolower($p['name'] . ' ' . $k . ' ' . ($p['provider'] ?? ''))) ?>">
      <div>
        <strong><?= e((string) $p['name']) ?></strong>
        <span class="adm-badge adm-badge--muted"><?= e(__(Repository::GROUPS[$p['group']][0] ?? $p['group'])) ?></span>
        <?php if (($p['origin'] ?? '') === 'own'): ?><span class="adm-badge"><?= e(__('eigene')) ?></span><?php endif; ?>
        <?php if ($p['params'] ?? []): ?><span class="adm-badge adm-badge--draft"><?= e(__('braucht: {list}', ['list' => implode(', ', array_map(fn($d) => Compiler::pick((array) ($d['label'] ?? []), $lang), $p['params']))])) ?></span><?php endif; ?>
        <p class="adm-muted"><?= e(Compiler::pick((array) ($p['description'] ?? []), $lang)) ?></p>
      </div>
      <?php if (isset($existing[$k])): ?>
      <p><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/consent/service/' . $existing[$k])) ?>"><?= e(__('Bereits angelegt – öffnen')) ?></a>
        <a class="adm-link" href="<?= e(url('/admin/consent/service/new?preset=' . rawurlencode($k))) ?>"><?= e(__('weitere Instanz')) ?></a></p>
      <?php else: ?>
      <p><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/consent/service/new?preset=' . rawurlencode($k))) ?>"><?= e(__('Hinzufügen')) ?><span class="sr-only">: <?= e((string) $p['name']) ?></span></a></p>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
