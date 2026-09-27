<?php /** Eigenständige Formularseite /anfrage/{form} (Fallback ohne JS / direkte Links) – Eingangs-Tabelle, verschlüsselt. @var array $def  @var ?array $table */ ?>
<section class="sec bg-gray formpage" aria-labelledby="formpage-title">
  <div class="wrap wrap--narrow">
    <p class="eyebrow"><?= e(lt('Online-Service')) ?></p>
    <h1 id="formpage-title" class="h2"><?= e(lt($def['label'])) ?><span class="dot">.</span></h1>
    <?php if (!empty($def['intro'])): ?><p class="lead"><?= e($def['intro']) ?></p><?php endif; ?>
    <div class="formpage__card">
      <?php if ($sent): ?>
        <div class="pform__done" role="status">
          <span aria-hidden="true" class="pform__check">✓</span>
          <p class="pform__done-title"><?= e($message) ?></p>
          <p class="pform__done-text"><?= e(lt('Bearbeitung: {time}. Bei Rückfragen melden wir uns telefonisch.', ['time' => setting('bearbeitungsfrist_text') ?: lt('[Frist – noch zu bestätigen]')])) ?></p>
          <a class="btn btn--primary" href="<?= e(url('/')) ?>"><?= e(lt('Zur Startseite')) ?></a>
        </div>
      <?php else: ?>
        <?php if ($message): ?><p class="pform__msg is-error" role="alert"><?= e($message) ?></p><?php endif; ?>
        <?= app()->theme->partial('form', ['form' => $form, 'compact' => false, 'values' => $values, 'errors' => $errors, 'challenge' => $challenge]) ?>
      <?php endif; ?>
    </div>
    <p class="formpage__alt"><?= praxis_fill(lt('Lieber persönlich? Rufen Sie uns an: {phone}'), ['phone' => '<a href="' . e(praxis_phone_href()) . '">' . e(praxis_phone()) . '</a>']) ?></p>
  </div>
</section>
