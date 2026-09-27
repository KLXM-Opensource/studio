<?php
/**
 * Flip-Kontaktkarte (Vorderseite: Heute/Sprechzeiten/Services; Rückseite: Termin bzw. Formular).
 * Ohne JavaScript führen die Kacheln zu Doctolib bzw. zur eigenständigen Formularseite.
 */
$today = praxis_today();
$hours = praxis_hours();
$services = praxis_services();
$central = is_editing() ? ' data-central="Praxisdaten"' : '';
?>
<?php
// Formular-Stile und -Skripte der Rückseite lädt site.js erst beim Umdrehen (Startseite bleibt schlank)
$lazy = array_filter(['rezept', 'ueberweisung'], fn($k) => isset($services[$k]) && empty($services[$k]['external']))
    ? [theme_asset('css/form.css'), theme_asset('js/form.js'), asset('js/legal-dialog.js')] : [];
?>
<div class="flip" data-flipcard<?= $lazy ? ' data-assets="' . json_attr($lazy) . '"' : '' ?>>
  <div class="flip__inner">
    <aside class="card card--front" aria-label="<?= e(lt('Schnellkontakt')) ?>"<?= $central ?>>
      <div class="card__top">
        <span class="card__today"><?= e(lt('Heute')) ?> · <span data-today><?= e($today['name']) ?></span></span>
        <?= praxis_phone_link('card__phone') ?>
      </div>
      <div>
        <?= praxis_open_badge('openb--card') ?>
        <p class="card__hours"><?= implode('<br>', array_map('e', $today['lines'])) ?></p>
        <?php if ($today['note']): ?><p class="card__hours-note"><?= e($today['note']) ?></p><?php endif; ?>
        <?php if ($hours): ?>
        <details class="popover">
          <summary><?= e(lt('Alle Öffnungszeiten')) ?> <span aria-hidden="true">▾</span></summary>
          <div class="popover__panel"><?= app()->theme->partial('hours-table', ['class' => 'htable--pop']) ?></div>
        </details>
        <?php endif; ?>
      </div>
      <?php if ($services): ?>
      <div class="card__services card__services--<?= count($services) ?>">
        <?php foreach ($services as $key => $s): ?>
        <a class="svc" href="<?= e($s['href']) ?>"<?= empty($s['external']) ? ' data-flip="' . e($key) . '"' : '' ?><?= ext_attrs($s['href']) ?>>
          <span aria-hidden="true" class="svc__icon">↻</span>
          <span class="svc__label"><?= e($s['label']) ?><span class="svc__sub"><?= e($s['sub']) ?></span></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <p class="card__note"><?= e(setting('notfall_kurz')) ?></p>
    </aside>

    <div class="card card--back" role="region" aria-labelledby="flip-title" hidden>
      <div class="card__head">
        <h2 class="card__title" id="flip-title" tabindex="-1"><span data-flip-title></span><span class="dot">.</span></h2>
        <button type="button" class="btn-back" data-flip-back>← <?= e(lt('Zurück')) ?></button>
      </div>
      <?php if (isset($services['termin'])): ?>
      <div class="card__panel" data-panel="termin" data-title="<?= e($services['termin']['title']) ?>" hidden>
        <p class="card__text"><?= e(setting('termin_hinweis') ?: lt('Wählen Sie Ärztin oder Arzt, Terminart und einen freien Termin – rund um die Uhr über Doctolib.')) ?></p>
        <a class="btn-wide" href="<?= e($services['termin']['href']) ?>"<?= ext_attrs($services['termin']['href']) ?>><?= e(lt('Termin bei Doctolib buchen')) ?> <span aria-hidden="true">↗</span></a>
        <p class="card__small"><?= e(lt('Externer Link zu Doctolib{url}. Akute Beschwerden bitte telefonisch.', ['url' => filled(setting('doctolib_url')) ? '' : ' ' . praxis_note(lt('[Praxis-URL]'))])) ?></p>
      </div>
      <?php endif; ?>
      <?php foreach (['rezept', 'ueberweisung'] as $key): if (!isset($services[$key]) || !empty($services[$key]['external'])) continue; ?>
      <div class="card__panel" data-panel="<?= $key ?>" data-title="<?= e($services[$key]['title']) ?>" hidden>
        <?= app()->theme->partial('form', ['form' => $key, 'compact' => true]) ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
