<?php
/**
 * Lizenzen & Danksagungen (Handbuch & Hilfe): Projektlizenz MIT und THIRD-PARTY-NOTICES.md
 * (gerendert von HelpController::noticesHtml – die Datei im Projekt ist die einzige Quelle).
 * @var string $notices  @var list<array{0: string, 1: string}> $toc  @var string $license  @var string $copyright
 */
$helpTab = 'licenses';
include ROOT . '/app/Admin/views/support/_helptabs.php';
?>
<div class="doc doc-licenses">
  <div class="adm-head">
    <div>
      <h1><?= e(__('Lizenzen & Danksagungen')) ?></h1>
      <p class="adm-muted"><?= e(__('{name} ist Open Source unter der MIT-Lizenz. Wir danken allen, deren Software, Schriften, Symbole, Daten und Medien wir verwenden – ihre Lizenzen stehen unten.', ['name' => CMS_NAME])) ?></p>
    </div>
  </div>

  <div class="doc-layout">
    <div>
      <section class="doc-ch" id="l-project">
        <h2><?= e(__('Projektlizenz')) ?></h2>
        <p><?= e(__('Die MIT-Lizenz erlaubt es, {name} ohne Einschränkung zu nutzen, anzupassen, weiterzugeben und zu verkaufen – auch kommerziell und in Kundenprojekten. Bedingung ist nur, dass der Copyright- und Lizenzhinweis erhalten bleibt. Eigene Kits und Erweiterungen dürfen unter jeder beliebigen Lizenz stehen. Mitgelieferte Drittsoftware behält ihre eigene Lizenz (siehe unten).', ['name' => CMS_NAME])) ?></p>
        <?php if ($copyright !== ''): ?><pre class="doc-license"><?= e(trim($copyright)) ?></pre><?php endif; ?>
        <?php if ($license !== ''): ?>
        <details>
          <summary><?= e(__('Volltext der MIT-Lizenz (englisch)')) ?></summary>
          <pre class="doc-license"><?= e($license) ?></pre>
        </details>
        <?php endif; ?>
      </section>
      <?php if ($notices !== ''): ?>
      <p class="adm-muted"><?= e(__('Die folgenden Angaben stammen aus THIRD-PARTY-NOTICES.md im Projektverzeichnis (englisch). Die Lizenztexte liegen neben den ausgelieferten Dateien.')) ?></p>
      <?= $notices ?>
      <?php else: ?>
      <p class="adm-muted"><?= e(__('THIRD-PARTY-NOTICES.md fehlt in dieser Installation.')) ?></p>
      <?php endif; ?>
      <?php $fontsLic = \Core\Fonts::licensesSection(); /* Installierte Schriften (Grundeinstellungen → Schriften, Core\Fonts) – automatisch */ ?>
      <?= $fontsLic ?>
    </div>
    <nav class="doc-toc" aria-label="<?= e(__('Inhalt')) ?>">
      <p><?= e(__('Inhalt')) ?></p>
      <ol>
        <li><a href="#l-project"><?= e(__('Projektlizenz')) ?></a></li>
        <?php foreach ($toc as [$id, $label]): ?><li><a href="#<?= e($id) ?>"><?= e(str_replace('`', '', (string) preg_replace('~^\d+\.\s*~', '', $label))) ?></a></li><?php endforeach; ?>
        <?php if ($fontsLic !== ''): ?><li><a href="#l-installed-fonts"><?= e(__('Installierte Schriften')) ?></a></li><?php endif; ?>
      </ol>
    </nav>
  </div>
</div>
