<?php
/**
 * Verwaltung → Glossar → Teilen (Core\Glossary\Sharing): Glossar mit anderen Websites dieser Installation teilen, einladen,
 * beitreten (mit Abgleich doppelter Begriffe), ausgeblendete Begriffe, verlassen.
 * @var ?array $t  @var array $status  @var bool $manage  @var ?array $plan  @var array $hidden  @var bool $canHide
 */
use Core\Glossary\Glossary;

$base = '/admin/glossar';
$role = $status['role'];
$meta = $status['meta'];
$name = fn(string $k) => \Core\Data\Shared::siteInfo($k, 'glossar')['name'];
$choiceLabels = ['existing' => __('Vorhandenen Begriff nutzen (eigenen nicht übernehmen)'), 'mine' => __('Eigenen übernehmen, den anderen hier ausblenden'), 'both' => __('Beide behalten')];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url($base)) ?>"><?= e(__('Glossar')) ?></a></p><h1><?= e(__('Glossar teilen')) ?></h1>
    <p class="adm-muted"><?= e(__('Ein Glossar für mehrere Websites dieser Installation: Jede Website pflegt ihre eigenen Begriffe und zeigt zusätzlich die der anderen – markiert im Text, in der Übersicht A–Z und mit Detailseite unter der eigenen Adresse. Einzelne fremde Begriffe lassen sich hier ausblenden.')) ?>
      <a href="<?= e(url('/admin/hilfe#glossar-teilen')) ?>"><?= e(__('Handbuch →')) ?></a></p></div>
</header>

<?php if (!$manage): ?>
<p class="adm-inline-box"><?= e(__('Teilen einrichten, beitreten oder verlassen darf, wer die Grundeinstellungen ändern und geteilte Daten verwalten darf.')) ?></p>
<?php endif; ?>

<section class="adm-card gls-share" aria-labelledby="gls-share-h">
  <h2 id="gls-share-h"><?= e(__('Stand')) ?></h2>
  <?php if ($role === 'local'): ?>
    <p><?= e(__('Das Glossar dieser Website ist nicht geteilt.')) ?></p>
    <?php if (!$status['local']): ?>
      <p class="adm-muted"><?= e(__('Bitte zuerst das Glossar einrichten.')) ?></p>
    <?php elseif ($manage): ?>
    <form method="post" action="<?= e(url($base . '/teilen')) ?>">
      <?= csrf_field() ?><input type="hidden" name="do" value="share">
      <h3><?= e(__('Glossar mit anderen Websites dieser Installation teilen')) ?></h3>
      <p class="adm-muted"><?= e(__('Diese Website wird Eigentümerin: Ihre Begriffe ziehen in den gemeinsamen Speicher (Adressen und Links bleiben gleich). Eingeladene Websites treten in ihren Glossar-Einstellungen selbst bei – dabei werden doppelte Begriffe abgeglichen.')) ?></p>
      <fieldset class="pl-sites"><legend><?= e(__('Einladen')) ?></legend>
        <?php foreach ($status['sites'] as $k => $label): ?><label class="f-check"><input type="checkbox" name="invite[]" value="<?= e($k) ?>"> <span><?= e($label) ?></span></label><?php endforeach; ?>
      </fieldset>
      <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Glossar teilen')) ?></button>
    </form>
    <?php endif; ?>

  <?php elseif ($role === 'none'): ?>
    <p><?= e(__('„{site}“ teilt ein Glossar in dieser Installation. Diese Website ist nicht eingeladen – die Einladung spricht „{site}“ in deren Glossar-Einstellungen aus.', ['site' => $status['owner']])) ?></p>

  <?php elseif ($role === 'invited'): ?>
    <p><?= e(__('„{site}“ lädt diese Website ein, das gemeinsame Glossar zu nutzen.', ['site' => $status['owner']])) ?></p>
    <?php if ($plan): ?>
    <p class="gls-stats"><span><b><?= (int) $plan['local'] ?></b> <?= e(__('eigene Begriffe'))?></span> · <span><b><?= (int) $plan['shared'] ?></b> <?= e(__('Begriffe der anderen Websites')) ?></span> · <span><b><?= count($plan['dupes']) ?></b> <?= e(__('doppelt')) ?></span></p>
    <?php endif; ?>
    <?php if ($manage): ?>
    <form method="post" action="<?= e(url($base . '/teilen')) ?>">
      <?= csrf_field() ?><input type="hidden" name="do" value="join">
      <?php if ($plan && $plan['dupes']): ?>
      <h3><?= e(__('Doppelte Begriffe abgleichen')) ?></h3>
      <p class="adm-muted"><?= e(__('Diese Begriffe gibt es schon bei anderen Websites (gleicher Begriff oder gleiche Variante, ohne Rücksicht auf Groß-/Kleinschreibung und Akzente). Bitte je Begriff wählen – nichts wird stillschweigend doppelt angelegt.')) ?></p>
      <div class="adm-card adm-card--flush rd-tablewrap">
        <table class="adm-table gls-dupes">
          <thead><tr><th scope="col"><?= e(__('Eigener Begriff')) ?></th><th scope="col"><?= e(__('Vorhanden')) ?></th><th scope="col"><?= e(__('Entscheidung')) ?></th></tr></thead>
          <tbody>
          <?php foreach ($plan['dupes'] as $d): $id = (int) $d['term']['id']; ?>
            <tr>
              <td><b><?= e($d['term']['term']) ?></b><?php if ($d['term']['status'] !== 'published'): ?> <span class="adm-badge adm-badge--muted"><?= e(__('Entwurf')) ?></span><?php endif; ?>
                <span class="gls-alt"><?= e($d['term']['short']) ?></span></td>
              <td><?php foreach ($d['matches'] as $x): ?><p><b><?= e($x['term']) ?></b> <span class="adm-badge"><?= e(__('von {site}', ['site' => $x['origin_name']])) ?></span>
                <span class="gls-alt"><?= e($x['short']) ?></span></p><?php endforeach; ?>
                <span class="adm-muted"><?= e(__('Gleich: „{v}“', ['v' => $d['on']])) ?></span></td>
              <td><fieldset class="gls-choice"><legend class="sr-only"><?= e(__('Entscheidung für „{t}“', ['t' => $d['term']['term']])) ?></legend>
                <?php foreach ($choiceLabels as $k => $l): ?><label class="f-check"><input type="radio" name="choice[<?= $id ?>]" value="<?= e($k) ?>"<?= $k === 'existing' ? ' checked' : '' ?>> <span><?= e($l) ?></span></label><?php endforeach; ?>
              </fieldset></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <p class="adm-muted"><?= e(__('Beim Beitreten ziehen die eigenen Begriffe in den gemeinsamen Speicher; Links auf Begriffe in Ihren Seiten werden umgestellt. Die bisherige Tabelle bleibt als Sicherung erhalten.')) ?></p>
      <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Beitreten')) ?></button>
    </form>
    <?php endif; ?>

  <?php else: /* owner | member */ ?>
    <p><?= $role === 'owner' ? e(__('Das Glossar ist geteilt – diese Website ist Eigentümerin.')) : e(__('Diese Website nutzt das geteilte Glossar von „{site}“.', ['site' => $status['owner']])) ?></p>
    <ul class="gls-sites">
      <li><?= e(__('Eigentümer: {site}', ['site' => $status['owner']])) ?></li>
      <li><?= e(__('Beteiligt: {sites}', ['sites' => implode(', ', array_map($name, $meta['members'])) ?: '–'])) ?></li>
      <?php if ($meta['invited']): ?><li><?= e(__('Eingeladen (noch nicht beigetreten): {sites}', ['sites' => implode(', ', array_map($name, $meta['invited']))])) ?></li><?php endif; ?>
    </ul>
    <p class="adm-muted"><?= e(__('Neue Begriffe gehören der Website, auf der sie angelegt werden, und erscheinen bei allen Beteiligten. Ändern lässt sich ein Begriff nur auf seiner Website.')) ?>
      <?php if ($t): ?><a href="<?= e(url('/admin/data/' . $t['handle'] . '/display')) ?>"><?= e(__('Anzeige und Canonical einstellen')) ?></a><?php endif; ?></p>

    <?php if ($role === 'owner' && $manage): $others = array_diff_key($status['sites'], array_flip($meta['members'])); ?>
    <form method="post" action="<?= e(url($base . '/teilen')) ?>">
      <?= csrf_field() ?><input type="hidden" name="do" value="invite">
      <fieldset class="pl-sites"><legend><?= e(__('Einladen')) ?></legend>
        <?php if (!$others): ?><p class="adm-muted"><?= e(__('Alle Websites sind beteiligt.')) ?></p><?php endif; ?>
        <?php foreach ($others as $k => $label): ?><label class="f-check"><input type="checkbox" name="invite[]" value="<?= e($k) ?>"<?= in_array($k, $meta['invited'], true) ? ' checked' : '' ?>> <span><?= e($label) ?></span></label><?php endforeach; ?>
      </fieldset>
      <?php if ($others): ?><button class="adm-btn adm-btn--small" type="submit"><?= e(__('Einladungen speichern')) ?></button><?php endif; ?>
    </form>
    <?php endif; ?>

    <?php if ($manage): ?>
    <details class="sh-unshare">
      <summary><?= e($role === 'owner' ? __('Teilen beenden …') : __('Geteiltes Glossar verlassen …')) ?></summary>
      <?php if ($role === 'owner' && $meta['members']): ?>
        <p class="adm-muted"><?= e(__('Teilen beenden geht erst, wenn keine andere Website mehr beteiligt ist. Die übrigen Websites verlassen das Glossar in ihren Glossar-Einstellungen – sie behalten dabei eine Kopie.')) ?></p>
      <?php else: ?>
      <form method="post" action="<?= e(url($base . '/teilen')) ?>">
        <?= csrf_field() ?><input type="hidden" name="do" value="leave">
        <p class="adm-muted"><?= e($role === 'owner'
            ? __('Das Glossar wird wieder eine eigene Tabelle dieser Website. Die geteilten Daten bleiben als Sicherung erhalten.')
            : __('Das Glossar wird wieder eine eigene Tabelle dieser Website mit Ihren Begriffen (gleiche Adressen und Links). Ihre Begriffe erscheinen danach nicht mehr bei den anderen Websites.')) ?></p>
        <?php if ($role === 'member'): ?><label class="f-check"><input type="checkbox" name="copies" value="1" checked> <span><?= e(__('Begriffe der anderen Websites, die hier zu sehen sind, als Kopie behalten')) ?></span></label><?php endif; ?>
        <div class="adm-row"><input name="confirm" placeholder="glossar" aria-label="<?= e(__('Zum Bestätigen „glossar“ eintippen')) ?>" autocomplete="off">
          <button class="adm-btn adm-btn--small adm-btn--danger" type="submit"><?= e($role === 'owner' ? __('Teilen beenden') : __('Verlassen')) ?></button></div>
      </form>
      <?php endif; ?>
    </details>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php if (in_array($role, ['owner', 'member'], true)): ?>
<section class="adm-card" aria-labelledby="gls-hidden-h">
  <h2 id="gls-hidden-h"><?= e(__('Auf dieser Website ausgeblendet')) ?> <small class="adm-muted">(<?= count($hidden) ?>)</small></h2>
  <?php if (!$hidden): ?><p class="adm-muted"><?= e(__('Keine. Begriffe anderer Websites blenden Sie in der Begriffsliste des Glossars aus („Ausblenden“).')) ?></p><?php endif; ?>
  <ul class="gls-hidden">
    <?php foreach ($hidden as $x): ?>
    <li><b><?= e($x['term']) ?></b> <span class="adm-badge"><?= e(__('von {site}', ['site' => $name($x['origin'])])) ?></span>
      <?php if ($canHide): ?><form method="post" action="<?= e(url($base . '/ausblenden')) ?>" class="gls-inline"><?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $x['id'] ?>"><input type="hidden" name="state" value="show"><input type="hidden" name="back" value="teilen">
        <button class="adm-btn adm-btn--small adm-btn--ghost" type="submit"><?= e(__('Wieder zeigen')) ?></button></form><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
