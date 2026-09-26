<?php /** Kontakt – Inhalte aus den Praxisdaten. @var \Core\Block $b  @var array $d */
$hours = praxis_hours();
$phoneHref = praxis_phone_href();
$termin = praxis_services()['termin'] ?? null;
$c = $b->central();
?>
<div class="wrap">
  <?= praxis_heading($b, 'h2', 'h2 h2--contact') ?>
  <div class="contact">
    <div class="contact__card contact__card--primary" data-reveal="up"<?= $c ?>>
      <h3 class="eyebrow-h"><?= e(lt('Telefon und Termin')) ?></h3>
      <a class="contact__phone" href="<?= e($phoneHref) ?>"><?= e(praxis_phone()) ?></a>
      <dl class="contact__list">
        <div><dt><?= e(lt('Telefonisch erreichbar')) ?></dt><dd><?= e(setting('telefonische_erreichbarkeit') ?: lt('[Telefonische Erreichbarkeit]')) ?></dd></div>
        <div><dt><?= e(lt('Fax')) ?></dt><dd><?= e(phone_display((string) setting('fax')) ?: lt('[Faxnummer]')) ?></dd></div>
        <div><dt><?= e(lt('E-Mail')) ?></dt><dd><?php $mail = (string) setting('email'); ?><?php if (filled($mail)): ?><a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a><?php else: ?><a href="mailto:"><?= e($mail ?: lt('[E-Mail-Adresse]')) ?></a><?php endif; ?></dd></div>
      </dl>
      <?php if ($termin): ?>
      <a class="btn btn--white contact__cta" href="<?= e($termin['href']) ?>"<?= ext_attrs($termin['href']) ?>><?= e(setting('termin_button') ?: lt('Online-Termin')) ?><?= filled(setting('doctolib_url')) ? '' : ' ' . e(lt('[Terminlink]')) ?> <span aria-hidden="true">→</span></a>
      <?php endif; ?>
    </div>
    <div class="contact__card" data-reveal="up" data-delay="80"<?= $c ?>>
      <div class="contact__hhead"><h3 class="eyebrow-h eyebrow-h--accent"><?= e(lt('Sprechzeiten')) ?></h3><?= praxis_open_badge('openb--contact') ?></div>
      <?= app()->theme->partial('hours-table', ['class' => 'htable--card']) ?>
      <?php if (setting('sprechzeiten_hinweis')): ?><p class="contact__note"><?= e(setting('sprechzeiten_hinweis')) ?></p><?php endif; ?>
    </div>
    <div class="contact__card" id="anfahrt" data-reveal="up" data-delay="160"<?= $c ?>>
      <h3 class="eyebrow-h eyebrow-h--accent"><?= e(lt('Anfahrt')) ?></h3>
      <address class="contact__address"><?= e(setting('praxis_name') ?: lt('[Name der Gemeinschaftspraxis]')) ?><br>
        <span><?= e(setting('strasse') ?: lt('[Straße und Hausnummer]')) ?><br><?= e(filled(setting('plz')) ? trim(setting('plz') . ' ' . setting('ort')) : lt('[PLZ {city}]', ['city' => setting('ort') ?: lt('Ort')])) ?></span></address>
      <dl class="contact__list contact__list--plain">
        <div><dt><?= e(lt('Bus und Bahn')) ?></dt><dd><?= e(setting('oepnv_text') ?: lt('[Haltestelle und Linien]')) ?></dd></div>
        <div><dt><?= e(lt('Parken')) ?></dt><dd><?= e(setting('parken_text') ?: lt('[Parkmöglichkeiten]')) ?></dd></div>
        <div><dt><?= e(lt('Barrierefreiheit')) ?></dt><dd><?= e(setting('barrierefreiheit_text') ?: lt('[Hinweise zu Zugang, Aufzug, Stufen]')) ?></dd></div>
      </dl>
    </div>
  </div>
  <?php if ($d['show_map'] && setting('karte_aktiv')): ?>
    <?= app()->theme->partial('map') ?>
  <?php endif; ?>
  <?php if ($d['note']): ?><p class="contact__foot"<?= $b->edit('note') ?>><?= e($d['note']) ?></p><?php endif; ?>
</div>
