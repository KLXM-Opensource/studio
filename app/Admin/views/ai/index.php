<?php
/** Bereich „KLXM Ai“ – Übersicht: Status je Fähigkeit, Tageslimit, Schnellzugriffe; ohne Einrichtung eine Erklärung. */
use Core\AI\Ai;
use Core\AI\Assist;
use Core\AI\Center;

$brand = Assist::brand();
$site = Ai::siteSettings();
$cfg = Ai::config();
$on = ['text' => Assist::available('text'), 'vision' => Assist::available('vision')];
$any = $on['text'] || $on['vision'];
$q = Assist::quota();
$labels = ['text' => __('Texte: schreiben, übersetzen, SEO'), 'vision' => __('Bilder: Alt-Texte'), 'embed' => __('Suche: semantisch')];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('KI-Assistent')) ?></p><h1 class="kia-brand"><?= Assist::logo() ?> <?= e($brand) ?></h1>
    <p class="adm-muted"><?= e(__('Schreiben, übersetzen, Suchmaschinen-Angaben und Alt-Texte – die KI macht Vorschläge, Sie prüfen und entscheiden.')) ?></p></div>
</header>

<?php if (!$any): ?>
<section class="adm-card kia-setup" aria-labelledby="kia-setup-h">
  <?php if ($cfg['configured'] && !$site['enabled']): // Anbieter der Installation steht – nur diese Website ist aus ?>
  <h2 id="kia-setup-h"><?= e(__('KI ist auf dieser Website ausgeschaltet')) ?></h2>
  <p><?= e(__('Der KI-Anbieter der Installation ist eingerichtet ({provider}). Für diese Website ist die KI aber noch nicht eingeschaltet.', ['provider' => ucfirst((string) ($cfg['provider'] ?? ''))])) ?></p>
  <ol class="kia-steps">
    <li><?= e(__('Unter Grundeinstellungen → KI „KI für diese Website“ einschalten und die gewünschten Fähigkeiten (Texte, Bilder, Suche, Sprache → Text) wählen.')) ?></li>
    <li><?= e(__('Festlegen, welche Rollen sie nutzen dürfen (Recht „KI-Funktionen nutzen“).')) ?></li>
  </ol>
  <?php else: ?>
  <h2 id="kia-setup-h"><?= e(__('Noch nicht eingerichtet')) ?></h2>
  <p><?= e(!$cfg['configured'] ? __('Für diese Installation ist noch kein KI-Anbieter festgelegt.') : __('Die Fähigkeiten „Texte“ und „Bilder“ sind ausgeschaltet oder ohne Modell.')) ?></p>
  <ol class="kia-steps">
    <li><?= e(__('Anbieter wählen: ein eigener Ollama-Server (Daten bleiben im Haus) oder ein EU-Anbieter wie Mistral AI mit Auftragsverarbeitungsvertrag.')) ?></li>
    <li><?= e(__('Unter Grundeinstellungen → KI den Anbieter eintragen und „Verbindung testen“.')) ?></li>
    <li><?= e(__('KI für die Website einschalten und festlegen, welche Rollen sie nutzen dürfen (Recht „KI-Funktionen nutzen“).')) ?></li>
  </ol>
  <?php endif; ?>
  <?php if (can('system.manage')): ?><a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/system#ki')) ?>"><?= e(__('Zu Grundeinstellungen → KI')) ?></a>
  <?php else: ?><p class="adm-muted"><?= e(__('Die Einrichtung übernimmt Ihre Administration bzw. Agentur.')) ?></p><?php endif; ?>
</section>
<?php else:
  $gaps = Assist::available('text') && \Core\Lang::multi() ? Center::translationGaps() : [];
  $open = 0; foreach ($gaps as $g) $open += count($g['pages']) + count($g['entries']) + count($g['media']) + $g['settings'];
  $noalt = can('media.upload') ? count(Center::missingAlt()) : 0;
  $seo = 0; if (can('pages.manage')) foreach (\Core\AI\SeoCheck::overview()['pages'] as $r) if ($r['missing']) $seo++;
?>
<div class="adm-stats kia-stats">
  <?php if ($q['cap'] > 0): ?>
  <div class="adm-stat"><strong><?= (int) $q['left'] ?></strong><span><?= e(__('KI-Aufrufe heute übrig (von {cap})', ['cap' => $q['cap']])) ?></span>
    <span class="kia-bar" role="progressbar" aria-valuenow="<?= (int) $q['used'] ?>" aria-valuemin="0" aria-valuemax="<?= (int) $q['cap'] ?>" aria-label="<?= e(__('Tageslimit genutzt')) ?>"><progress max="<?= (int) $q['cap'] ?>" value="<?= min((int) $q['used'], (int) $q['cap']) ?>"></progress></span></div>
  <?php else: ?><div class="adm-stat"><strong><?= (int) $q['used'] ?></strong><span><?= e(__('KI-Aufrufe heute (ohne Limit)')) ?></span></div><?php endif; ?>
  <?php if (\Core\Review\Queue::canReview()): ?><a class="adm-stat<?= \Core\Review\Queue::pendingCount() ? ' adm-stat--hot' : '' ?>" href="<?= e(url('/admin/ai/eingereicht')) ?>"><strong><?= \Core\Review\Queue::pendingCount() ?></strong><span><?= e(__('Änderungen zur Freigabe')) ?></span></a><?php endif; ?>
  <?php if ($gaps): ?><a class="adm-stat" href="<?= e(url('/admin/ai/uebersetzen')) ?>"><strong><?= $open ?></strong><span><?= e(__('fehlende Übersetzungen')) ?></span></a><?php endif; ?>
  <?php if (can('pages.manage')): ?><a class="adm-stat" href="<?= e(url('/admin/ai/seo')) ?>"><strong><?= $seo ?></strong><span><?= e(__('Seiten ohne Beschreibung')) ?></span></a><?php endif; ?>
  <?php if (can('media.upload')): ?><a class="adm-stat" href="<?= e(url('/admin/ai/alt-texte')) ?>"><strong><?= $noalt ?></strong><span><?= e(__('Bilder ohne Alt-Text')) ?></span></a><?php endif; ?>
</div>

<div class="adm-grid2">
  <section class="adm-card" aria-labelledby="kia-st-h">
    <h2 id="kia-st-h"><?= e(__('Status')) ?></h2>
    <ul class="kia-status">
      <?php foreach (['text', 'vision', 'embed'] as $c): $cap = Ai::capability($c); $act = Ai::enabled($c); ?>
      <li class="kia-status__row"><span class="kia-chk kia-chk--<?= $act ? 'ok' : 'info' ?>"><span class="kia-chk__i" aria-hidden="true"><?= $act ? '✓' : '–' ?></span><span><strong><?= e($labels[$c]) ?></strong>
        <small class="adm-muted"><?= $act ? e(__('aktiv')) . ' · ' . e($cap['model']) . ' · ' . e($cap['external'] ? __('externer Anbieter') : __('bleibt auf dem Server')) : e(__('aus')) ?></small></span></span></li>
      <?php endforeach; ?>
    </ul>
    <p class="adm-muted kia-small"><?= e(__('Gezählt werden nur Aufrufe und Dauer – Inhalte werden nicht gespeichert.')) ?></p>
  </section>
  <section class="adm-card" aria-labelledby="kia-go-h">
    <h2 id="kia-go-h"><?= e(__('Schnellzugriff')) ?></h2>
    <ul class="kia-quick">
      <?php if ($on['text']): ?><li><a href="<?= e(url('/admin/ai/texte')) ?>"><span aria-hidden="true">✎</span><span><strong><?= e(__('Text schreiben')) ?></strong><small><?= e(__('Freier Auftrag mit Seite oder Eintrag als Zusammenhang, Ergebnis als Entwurf einfügen')) ?></small></span></a></li><?php endif; ?>
      <?php if ($gaps): ?><li><a href="<?= e(url('/admin/ai/uebersetzen')) ?>"><span aria-hidden="true">⇄</span><span><strong><?= e(__('Übersetzen')) ?></strong><small><?= e(__('Fehlende Übersetzungen je Sprache prüfen und nacharbeiten')) ?></small></span></a></li><?php endif; ?>
      <?php if (can('pages.manage')): ?><li><a href="<?= e(url('/admin/ai/seo')) ?>"><span aria-hidden="true">⌕</span><span><strong><?= e(__('SEO-Übersicht')) ?></strong><small><?= e(__('Beschreibungen für Suchmaschinen ergänzen')) ?></small></span></a></li><?php endif; ?>
      <?php if (can('media.upload')): ?><li><a href="<?= e(url('/admin/ai/alt-texte')) ?>"><span aria-hidden="true">▣</span><span><strong><?= e(__('Alt-Texte')) ?></strong><small><?= e(__('Bilder ohne Beschreibung nacheinander prüfen')) ?></small></span></a></li><?php endif; ?>
    </ul>
  </section>
</div>
<section class="adm-card" aria-labelledby="kia-where-h">
  <h2 id="kia-where-h"><?= e(__('Wo hilft der Assistent noch?')) ?></h2>
  <ul class="kia-where">
    <li><?= e(__('„✦ KI“ in jeder Formatierungsleiste und an Textfeldern: verbessern, kürzen, erweitern, einfacher, korrigieren, Ton ändern, freier Auftrag.')) ?></li>
    <li><?= e(__('Seiteneinstellungen und Einträge: SEO-Check, SEO-Vorschläge, Teaser, „Aus Deutsch übersetzen“.')) ?></li>
    <li><?= e(__('Mediathek: Alt-Text beim Hochladen und in der Info-Spalte vorschlagen.')) ?></li>
    <li><?= e(__('Support und Tabellen-Designer: Antwortvorschläge, Wissensartikel überarbeiten, Felder vorschlagen.')) ?></li>
  </ul>
  <p class="kia-rule"><?= e(__('Die KI erfindet keine Fakten: Fehlende Angaben erscheinen als [bitte ergänzen: …]. Bitte jeden Vorschlag prüfen.')) ?></p>
</section>
<?php endif;
