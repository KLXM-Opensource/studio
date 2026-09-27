<?php /** Kontakt – Inhalte aus den Praxisdaten. @var \Core\Block $b  @var array $d */
$hours = praxis_hours();
$phoneHref = praxis_phone_href();
$termin = praxis_services()['termin'] ?? null;
$c = $b->central();
// Zeile nur mit Wert – fehlt er, sieht die Redaktion im Bearbeiten-Modus eine Notiz, Besucher sehen die Zeile nicht
$row = fn(string $dt, string $dd, string $missing) => $dd !== '' || is_editing()
    ? '<div><dt>' . e($dt) . '</dt><dd>' . ($dd !== '' ? $dd : e(praxis_note($missing))) . '</dd></div>' : '';
$mail = (string) setting('email');
?>
<div class="wrap">
  <?= praxis_heading($b, 'h2', 'h2 h2--contact') ?>
  <div class="contact">
    <div class="contact__card contact__card--primary" data-reveal="up"<?= $c ?>>
      <h3 class="eyebrow-h"><?= e(lt('Telefon und Termin')) ?></h3>
      <?= praxis_phone_link('contact__phone') ?>
      <dl class="contact__list">
        <?= $row(lt('Telefonisch erreichbar'), e((string) setting('telefonische_erreichbarkeit')), lt('[Telefonische Erreichbarkeit]')) ?>
        <?= $row(lt('Fax'), e(phone_display((string) setting('fax'))), lt('[Faxnummer]')) ?>
        <?= $row(lt('E-Mail'), filled($mail) ? '<a href="mailto:' . e($mail) . '">' . e($mail) . '</a>' : '', lt('[E-Mail-Adresse]')) ?>
      </dl>
      <?php if ($termin): ?>
      <a class="btn btn--white contact__cta" href="<?= e($termin['href']) ?>"<?= ext_attrs($termin['href']) ?>><?= e(setting('termin_button') ?: lt('Online-Termin')) ?><?= filled(setting('doctolib_url')) ? '' : ' ' . e(praxis_note(lt('[Terminlink]'))) ?> <span aria-hidden="true">→</span></a>
      <?php endif; ?>
    </div>
    <div class="contact__card" data-reveal="up" data-delay="80"<?= $c ?>>
      <div class="contact__hhead"><h3 class="eyebrow-h eyebrow-h--accent"><?= e(lt('Sprechzeiten')) ?></h3><?= praxis_open_badge('openb--contact') ?></div>
      <?= app()->theme->partial('hours-table', ['class' => 'htable--card']) ?>
      <?php if (filled(\Core\EditorNotes::strip((string) setting('sprechzeiten_hinweis'))) || (is_editing() && setting('sprechzeiten_hinweis'))): ?><p class="contact__note"><?= e(setting('sprechzeiten_hinweis')) ?></p><?php endif; ?>
    </div>
    <div class="contact__card" id="anfahrt" data-reveal="up" data-delay="160"<?= $c ?>>
      <h3 class="eyebrow-h eyebrow-h--accent"><?= e(lt('Anfahrt')) ?></h3>
      <address class="contact__address"><?= e(setting('praxis_name') ?: praxis_note(lt('[Name der Praxis]'))) ?><br>
        <span><?= e(setting('strasse') ?: praxis_note(lt('[Straße und Hausnummer]'))) ?><br><?= e(filled(setting('plz')) ? trim(setting('plz') . ' ' . setting('ort')) : praxis_note(lt('[PLZ {city}]', ['city' => setting('ort') ?: lt('Ort')]))) ?></span></address>
      <dl class="contact__list contact__list--plain">
        <?= $row(lt('Bus und Bahn'), e((string) setting('oepnv_text')), lt('[Haltestelle und Linien]')) ?>
        <?= $row(lt('Parken'), e((string) setting('parken_text')), lt('[Parkmöglichkeiten]')) ?>
        <?= $row(lt('Barrierefreiheit'), e((string) setting('barrierefreiheit_text')), lt('[Hinweise zu Zugang, Aufzug, Stufen]')) ?>
      </dl>
    </div>
  </div>
  <?php if ($d['show_map'] && setting('karte_aktiv')): ?>
    <?= app()->theme->partial('map') ?>
  <?php endif; ?>
  <?php if ($d['note']): ?><p class="contact__foot"<?= $b->edit('note') ?>><?= e($d['note']) ?></p><?php endif; ?>
</div>
