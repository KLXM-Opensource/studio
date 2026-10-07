<?php /** Eigenständige Formularseite /anfrage/{form} (Fallback ohne JS / direkte Links) – Eingangs-Tabelle, verschlüsselt. @var array $def  @var ?array $table */
// Darstellung „Klassisch“: ursprüngliches Layout (templates/form-page-classic.php)
if (praxis_classic()) { include __DIR__ . '/form-page-classic.php'; return; }
$secure = trim(\Core\EditorNotes::strip((string) setting('formular_hinweis'))) ?: lt('Übermittlung verschlüsselt – Ihre Angaben sind nur in der Praxis lesbar.');
$sos = trim(\Core\EditorNotes::strip((string) setting('notfall_kurz'))); ?>
<section class="sec bg-gray formpage" aria-labelledby="formpage-title">
  <div class="wrap formpage__wrap">
    <div class="formpage__aside">
      <p class="eyebrow"><?= e(lt('Online-Service')) ?></p>
      <h1 id="formpage-title" class="h2 h2--m"><?= e(lt($def['label'])) ?><span class="dot">.</span></h1>
      <?php if (!empty($def['intro'])): ?><p class="lead"><?= e($def['intro']) ?></p><?php endif; ?>
      <ol class="formpage__steps">
        <li><b><?= e(lt('Formular ausfüllen')) ?></b><span><?= e(lt('Nur das Nötigste – Pflichtfelder sind markiert.')) ?></span></li>
        <li><b><?= e(lt('Sicher absenden')) ?></b><span><?= e($secure) ?></span></li>
        <li><b><?= e(lt('Wir kümmern uns')) ?></b><span><?= e(praxis_done_text()) ?></span></li>
      </ol>
      <?php if ($sos !== ''): ?><p class="formpage__sos"><span aria-hidden="true">!</span><?= e(lt('Nicht für Notfälle:')) ?> <?= e($sos) ?></p><?php endif; ?>
      <?php if (praxis_has_phone()): ?><p class="formpage__alt"><?= praxis_fill(lt('Lieber persönlich? Rufen Sie uns an: {phone}'), ['phone' => praxis_phone_link()]) ?></p><?php endif; ?>
    </div>
    <div class="formpage__card">
      <?php if ($sent): ?>
        <div class="pform__done" role="status">
          <span aria-hidden="true" class="pform__check">✓</span>
          <p class="pform__done-title"><?= e($message) ?></p>
          <p class="pform__done-text"><?= e(praxis_done_text()) ?></p>
          <a class="btn btn--primary" href="<?= e(url('/')) ?>"><?= e(lt('Zur Startseite')) ?></a>
        </div>
      <?php else: ?>
        <?php if ($message): ?><p class="pform__msg is-error" role="alert"><?= e($message) ?></p><?php endif; ?>
        <?= app()->theme->partial('form', ['form' => $form, 'compact' => false, 'values' => $values, 'errors' => $errors, 'challenge' => $challenge]) ?>
      <?php endif; ?>
    </div>
  </div>
</section>
