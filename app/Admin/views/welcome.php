<?php
/**
 * Willkommen-Bildschirm beim Erststart (Admin\WelcomeController, Core\Onboarding): nächste Schritte, Kit und Startinhalte wählen.
 * @var array $kits name → [label, description]  @var string $kit  @var string $seed  @var array $errors  @var bool $canSetup
 * @var bool $network  @var string $siteKey  @var array $me
 */
$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error" id="wel-err-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
$name = trim((string) ($me['name'] ?? '')) ?: (string) ($me['email'] ?? '');
?>
<div class="wel">
  <p class="adm-brand adm-brand--auth adm-brand--product"><?= cms_mark() ?><small><?= e(__('Ersteinrichtung')) ?></small></p>
  <div class="adm-card wel__card">
    <h1><?= e(__('Willkommen, {name}!', ['name' => $name])) ?></h1>
    <p class="wel__lead"><?= e(__('KLXM Studio läuft. Bevor die Website online geht, legen Sie zwei Dinge fest – beides lässt sich später ändern:')) ?></p>
    <ol class="wel__steps">
      <li><strong><?= e(__('Kit wählen')) ?></strong> – <?= e(__('das Gestaltungs- und Funktionspaket der Website: Blöcke, Design, Musterseiten.')) ?></li>
      <li><strong><?= e(__('Startinhalte')) ?></strong> – <?= e(__('mit Musterseiten zum Ausprobieren oder leer zum eigenen Aufbau.')) ?></li>
      <li><strong><?= e(__('Einrichten')) ?></strong> – <?= e(__('danach führt die Übersicht mit einer Checkliste weiter: Verschlüsselung, E-Mail-Versand, Domain, Angaben der Website.')) ?></li>
    </ol>
    <?php if (!$canSetup): ?>
    <p class="adm-inline-box"><?= e(__('Kit und Startinhalte wählt die Administration. Bis dahin sehen Besucher den Hinweis „Diese Website wird gerade eingerichtet“.')) ?></p>
    <?php else: ?>
    <form method="post" action="<?= e(url('/admin/willkommen')) ?>" class="wel__form" novalidate>
      <?= csrf_field() ?>
      <?= $err('form') ?>
      <fieldset class="wel__set<?= isset($errors['kit']) ? ' f--error' : '' ?>"<?= isset($errors['kit']) ? ' aria-describedby="wel-err-kit"' : '' ?>>
        <legend><span class="wel__num" aria-hidden="true">1</span><?= e(__('Welches Kit soll die Website nutzen?')) ?></legend>
        <?= $err('kit') ?>
        <p class="wel__hint"><?= e(__('Unsicher? Alle Kits mit Vorschau für Desktop und Handy, hell und dunkel:')) ?> <a href="<?= e(\Core\I18n::locale() === 'en' ? 'https://studio.klxm.de/en/kits' : 'https://studio.klxm.de/kit') ?>" target="_blank" rel="noopener"><?= e(__('Kits ansehen')) ?><span class="sr-only"> <?= e(__('(öffnet in neuem Tab)')) ?></span> ↗</a></p>
        <?php $groups = ['general' => [__('Allgemein'), __('Für jede Art von Website – Unternehmen, Organisationen, Projekte.')], 'branch' => [__('Für Branchen und Themen'), __('Mit passenden Blöcken, Datentabellen und Musterseiten für einen bestimmten Zweck.')], 'dev' => [__('Für Entwickler'), __('Ausgangspunkt für eigene Kits.')]];
        foreach ($groups as $g => [$gTitle, $gText]): $list = array_filter($kits, fn($i) => $i['category'] === $g); if (!$list) continue; ?>
        <div class="wel__group">
          <h3 class="wel__group-h"><?= e($gTitle) ?> <span><?= e($gText) ?></span></h3>
          <div class="wel__kits">
            <?php uasort($list, fn($a, $b) => $b['recommended'] <=> $a['recommended']); foreach ($list as $k => $info): ?>
            <label class="wel__kit<?= $info['recommended'] ? ' wel__kit--rec' : '' ?>">
              <input type="radio" name="kit" value="<?= e($k) ?>"<?= $k === $kit ? ' checked' : '' ?> required>
              <span class="wel__kit-body">
                <span class="wel__kit-name"><?= e($info['label']) ?><?php if ($info['recommended']): ?> <span class="wel__rec"><?= e(__('Empfohlen')) ?></span><?php endif; ?></span>
                <?php if ($info['description'] !== ''): ?><span class="wel__kit-desc"><?= e($info['description']) ?></span><?php endif; ?>
                <code class="wel__kit-key"><?= e($k) ?></code>
              </span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </fieldset>
      <fieldset class="wel__set<?= isset($errors['content']) ? ' f--error' : '' ?>"<?= isset($errors['content']) ? ' aria-describedby="wel-err-content"' : '' ?>>
        <legend><span class="wel__num" aria-hidden="true">2</span><?= e(__('Mit Startinhalten beginnen?')) ?></legend>
        <?= $err('content') ?>
        <div class="wel__opts">
          <label class="wel__opt">
            <input type="radio" name="content" value="full"<?= $seed === 'full' ? ' checked' : '' ?> required>
            <span><strong><?= e(__('Mit Startinhalten')) ?></strong><span><?= e(__('Musterseiten, Beispieltexte und – je nach Kit – Demo-Bilder. Ideal zum Kennenlernen; alles lässt sich später ändern oder löschen.')) ?></span></span>
          </label>
          <label class="wel__opt">
            <input type="radio" name="content" value="empty"<?= $seed === 'empty' ? ' checked' : '' ?> required>
            <span><strong><?= e(__('Ohne Startinhalte')) ?></strong><span><?= e(__('Eine leere Startseite sowie Impressum und Datenschutz als Vorlagen – Sie bauen die Website selbst auf.')) ?></span></span>
          </label>
        </div>
      </fieldset>
      <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Website einrichten')) ?></button>
    </form>
    <?php endif; ?>
    <div class="wel__more">
      <h2><?= e(__('Gut zu wissen')) ?></h2>
      <ul>
        <li><?= e(__('Bis zur Einrichtung sehen Besucher den Hinweis „Diese Website wird gerade eingerichtet“ – die Website wird nicht von Suchmaschinen erfasst.')) ?></li>
        <?php if ($network): ?>
        <li><?= e(__('Weitere Websites legen Sie in der Netzwerk-Übersicht an („Neue Website“) – dort lassen sich Kit und Startinhalte auch gleich vorgeben.')) ?> <a href="<?= e(url('/admin/network#neu')) ?>"><?= e(__('Zur Netzwerk-Übersicht')) ?></a></li>
        <?php endif; ?>
        <li><?= e(__('Das Kit lässt sich später unter Grundeinstellungen wechseln; Startinhalte werden nur beim ersten Einrichten eingespielt.')) ?></li>
        <li><?= e(__('Anleitungen: Handbuch & Hilfe in der Verwaltung, für Technik das Entwicklerhandbuch.')) ?> <a href="<?= e(url('/admin/hilfe')) ?>"><?= e(__('Zur Hilfe')) ?></a></li>
      </ul>
    </div>
    <form method="post" action="<?= e(url('/admin/logout')) ?>" class="wel__logout"><?= csrf_field() ?><button type="submit" class="adm-btn adm-btn--small adm-btn--ghost"><?= e(__('Abmelden')) ?></button></form>
  </div>
</div>
