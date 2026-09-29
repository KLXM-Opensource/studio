<?php
/**
 * Flip-Kontaktkarte (Vorderseite: Heute/Sprechzeiten/Services; Rückseite: Termin bzw. Formular).
 * Ohne JavaScript führen die Kacheln zu Doctolib bzw. zur eigenständigen Formularseite.
 *
 * Geöffnet/geschlossen berechnet site.js im Browser (Ortszeit der Praxis) – die Seite bleibt zwischenspeicherbar. Beide
 * Varianten stehen im Markup übereinander im selben Rasterfeld (Platz reserviert, kein Layout-Sprung): heutige Zeiten bzw.
 * bei „geschlossen“ die Nummern 116 117 (ärztlicher Bereitschaftsdienst) und 112 (Notruf) – bundesweit feste Nummern.
 * Externe Dienste (Doctolib, externes Rezept/Überweisung): Die Kachel dreht die Karte zu einem Hinweis „Sie verlassen
 * unsere Website …“ mit „Weiter zu …“ / „Abbrechen“; ohne JavaScript bleibt sie ein normaler Link mit sichtbarem ↗.
 */
$today = praxis_today();
$hours = praxis_hours();
$services = praxis_services();
$central = is_editing() ? ' data-central="Praxisdaten"' : '';
$badge = praxis_open_badge('openb--card');
$note = praxis_card_note_html();
$newWin = '<span class="sr-only"> ' . e(lt('(externer Link, öffnet in neuem Fenster)')) . '</span>';
?>
<?php
// Stile der Rückseite und Formular-Stile/-Skripte lädt site.js erst beim Umdrehen (vorgeladen beim Zeigen/Fokussieren)
$lazy = array_merge([theme_asset('css/card-back.css')], array_filter(['rezept', 'ueberweisung'], fn($k) => isset($services[$k]) && empty($services[$k]['external']))
    ? [theme_asset('css/form.css'), theme_asset('js/form.js'), asset('js/legal-dialog.js')] : []);
?>
<div class="flip" data-flipcard data-assets="<?= json_attr($lazy) ?>">
  <div class="flip__inner">
    <aside class="card card--front" aria-label="<?= e(lt('Schnellkontakt')) ?>"<?= $central ?>>
      <div class="card__top">
        <span class="card__today"><?= e(lt('Heute')) ?> · <span data-today><?= e($today['name']) ?></span></span>
        <?= praxis_phone_link('card__phone') ?>
      </div>
      <div class="card__main">
        <?= $badge ?>
        <div class="card__now">
          <div class="card__open" data-now-open>
            <?php if ($today['seg']): ?>
            <p class="card__hours"><?php foreach ($today['seg'] as [$from, $to]): ?><?= praxis_range_html($from, $to) ?><span class="card__uhr"><?= e(lt('Uhr')) ?></span><?php endforeach; ?></p>
            <?php else: ?>
            <p class="card__hours card__hours--text"><?= implode('<br>', array_map('e', $today['lines'])) ?></p>
            <?php endif; ?>
            <?php if ($today['note']): ?><p class="card__hours-note"><?= e($today['note']) ?></p><?php endif; ?>
          </div>
          <?php if ($badge): ?>
          <div class="card__sos" data-now-closed hidden>
            <p class="card__reopen"><span><?= e(lt('Wir öffnen wieder')) ?></span> <b data-reopen></b></p>
            <p class="card__urgent"><?= e(lt('In dringenden Notfällen:')) ?></p>
            <ul class="card__sos-list">
              <li><a class="sos" href="tel:116117"><span class="sos__label"><?= e(lt('Ärztlicher Bereitschaftsdienst')) ?></span><span class="sos__num">116 117</span></a></li>
              <li><a class="sos sos--112" href="tel:112"><span class="sos__label"><?= e(lt('Notruf bei Lebensgefahr')) ?></span><span class="sos__num">112</span></a></li>
            </ul>
          </div>
          <?php endif; ?>
        </div>
        <?php if ($hours): ?>
        <details class="popover">
          <summary><?= e(lt('Alle Öffnungszeiten')) ?> <span aria-hidden="true" class="popover__chev"></span></summary>
          <div class="popover__panel"><?= app()->theme->partial('hours-table', ['class' => 'htable--pop']) ?></div>
        </details>
        <?php endif; ?>
      </div>
      <?php if ($services): ?>
      <div class="card__services card__services--<?= count($services) ?>">
        <?php foreach ($services as $key => $s): $ext = is_external($s['href']); ?>
        <a class="svc<?= $ext ? ' svc--ext' : '' ?>" href="<?= e($s['href']) ?>" data-flip="<?= e($key) ?>"<?= ext_attrs($s['href']) ?>>
          <span class="svc__icon"><?= praxis_icon($key, 24, 1.8) ?></span>
          <?php if ($ext): ?><span class="svc__ext"><?= praxis_icon('extern', 14, 2.2) ?></span><?php endif; ?>
          <span class="svc__label"><?= e($s['label']) ?><span class="svc__sub"><?= e($s['sub']) ?></span></span><?= $ext ? $newWin : '' ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($note !== ''): ?><p class="card__note"><?= $note ?></p><?php endif; ?>
    </aside>

    <div class="card card--back" role="region" aria-labelledby="flip-title" hidden>
      <div class="card__head">
        <h2 class="card__title" id="flip-title" tabindex="-1"><span data-flip-title></span></h2>
        <button type="button" class="btn-back" data-flip-back>← <?= e(lt('Zurück')) ?></button>
      </div>
      <?php foreach ($services as $key => $s):
          $ext = is_external($s['href']);
          if ($key !== 'termin' && !$ext) continue;
          // Termin (Doctolib) bzw. externer Dienst: Hinweis „Sie verlassen unsere Website“ + Bestätigung
          $name = $key === 'termin' ? 'Doctolib' : '';
          $host = $ext ? praxis_host($s['href']) : '';
          $target = $name !== '' ? $name : $host;
      ?>
      <div class="card__panel" data-panel="<?= e($key) ?>" data-title="<?= e($s['title']) ?>" hidden>
        <?php if ($key === 'termin'): ?>
        <p class="card__text"><?= e(setting('termin_hinweis') ?: lt('Wählen Sie Ärztin oder Arzt, Terminart und einen freien Termin – rund um die Uhr über Doctolib.')) ?></p>
        <?php endif; ?>
        <?php if ($ext): ?>
        <p class="leave" id="leave-<?= e($key) ?>"><span class="leave__icon"><?= praxis_icon('extern', 18, 2.2) ?></span><span><?= e($name !== ''
            ? lt('Sie verlassen unsere Website und wechseln zu {name} ({host}). Dort gelten deren Datenschutzbestimmungen.', ['name' => $name, 'host' => $host])
            : lt('Sie verlassen unsere Website und wechseln zu {host}. Dort gelten deren Datenschutzbestimmungen.', ['host' => $host])) ?></span></p>
        <div class="card__actions">
          <a class="btn-wide" href="<?= e($s['href']) ?>"<?= ext_attrs($s['href']) ?> aria-describedby="leave-<?= e($key) ?>" data-leave><?= e(lt('Weiter zu {name}', ['name' => $target])) ?><?= $newWin ?> <?= praxis_icon('extern', 18, 2.2) ?></a>
          <button type="button" class="btn-ghost" data-flip-back><?= e(lt('Abbrechen')) ?></button>
        </div>
        <?php if ($key === 'termin'): ?><p class="card__small"><?= e(lt('Akute Beschwerden bitte telefonisch.')) ?></p><?php endif; ?>
        <?php else: ?>
        <a class="btn-wide" href="<?= e($s['href']) ?>"><?= e(lt('Termin bei Doctolib buchen')) ?> <span aria-hidden="true">→</span></a>
        <p class="card__small"><?= e(lt('Akute Beschwerden bitte telefonisch.')) ?> <?= e(praxis_note(lt('[Praxis-URL]'))) ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php foreach (['rezept', 'ueberweisung'] as $key): if (!isset($services[$key]) || !empty($services[$key]['external'])) continue; ?>
      <div class="card__panel" data-panel="<?= $key ?>" data-title="<?= e($services[$key]['title']) ?>" hidden>
        <?= app()->theme->partial('form', ['form' => $key, 'compact' => true]) ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
