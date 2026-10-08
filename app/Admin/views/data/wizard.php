<?php
/**
 * Assistent „Neue Tabelle oder neues Formular“ (Core\Data\Wizard, DataController::create/wizard): Schritte 1–3 als einfache
 * POST-Formulare – ohne JavaScript bedienbar. Schritt 4 („Einsetzen“) ist data/place.php.
 * @var string $step  art | vorlage | einstellungen  @var array $st  Zustand  @var array $errors  @var array $presets
 */
use Core\Data\Delivery;
use Core\Data\Purpose;
use Core\Data\Tables;
use Core\Data\Wizard;

$purposes = Purpose::all();
$p = (string) ($st['purpose'] ?? '');
$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error" id="wz-e-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
$canFeatures = \Core\Features::canManage();
$heads = [
    'art' => [__('Was möchten Sie anlegen?'), __('Je nach Art richtet der Assistent die passenden Grundeinstellungen ein – alles lässt sich später unter „Felder & Einstellungen“ ändern.')],
    'vorlage' => [__('Womit möchten Sie beginnen?'), __('Vorlagen bringen passende Felder mit. Sie können danach Felder umbenennen, abwählen und ergänzen.')],
    'einstellungen' => [__('Grundeinstellungen'), __('Das Wichtigste auf einen Blick. Weitere Einstellungen finden Sie später unter „Felder & Einstellungen“.')],
];
?>
<header class="adm-head dt-head wz-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/data')) ?>"><?= e(__('Daten')) ?></a> › <?= e(__('Neu')) ?></p>
    <h1><?= e(__('Neue Tabelle oder neues Formular')) ?></h1></div>
  <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/data/new?expert=1')) ?>"><?= e(__('Ohne Assistent (Expertenmodus)')) ?></a>
</header>

<?= Core\Theme::capture(__DIR__ . '/_wizard_steps.php', ['step' => $step, 'links' => true]) ?>

<form method="post" action="<?= e(url('/admin/data/new')) ?>" class="wz" novalidate aria-labelledby="wz-q">
  <?= csrf_field() ?>
  <input type="hidden" name="step" value="<?= e($step) ?>">
  <?php // Enter in einem Feld: im letzten Schritt nur übernehmen (nicht anlegen), sonst weiter ?>
  <button type="submit" name="go" value="<?= $step === 'einstellungen' ? 'add' : 'next' ?>" class="wz-default" tabindex="-1" aria-hidden="true"></button>
  <div class="wz-intro">
    <h2 id="wz-q" class="wz-q"><?= e($heads[$step][0]) ?></h2>
    <p class="wz-lead"><?= e($heads[$step][1]) ?></p>
  </div>
  <?php if ($errors): ?><div class="adm-flash adm-flash--error" role="alert"><?= e(__('Bitte prüfen:')) ?> <?= e(implode(' ', array_map('strval', $errors))) ?></div><?php endif; ?>

<?php if ($step === 'art'): ?>
  <fieldset class="wz-cards"<?= isset($errors['purpose']) ? ' aria-describedby="wz-e-purpose"' : '' ?>>
    <legend class="adm-sr"><?= e(__('Was möchten Sie anlegen?')) ?></legend>
    <?php foreach ($purposes as $k => $x): $why = Purpose::unavailable($k); ?>
    <label class="wz-card<?= $why ? ' is-off' : '' ?>">
      <input type="radio" name="purpose" value="<?= e($k) ?>"<?= $p === $k ? ' checked' : '' ?><?= $why ? ' disabled' : '' ?> aria-describedby="wz-d-<?= e($k) ?>">
      <span class="wz-card__ico wz-ico--<?= e($k) ?>" aria-hidden="true"><?= icon($x['icon']) ?></span>
      <span class="wz-card__body">
        <strong class="wz-card__title"><?= e($x['label']) ?><?php if ($k === 'source'): ?> <span class="wz-card__tag"><?= e(__('eigener Ablauf')) ?></span><?php endif; ?></strong>
        <span class="wz-card__lead" id="wz-d-<?= e($k) ?>"><?= e($x['lead']) ?> <span class="wz-card__ex"><?= e(__('z. B. {examples}', ['examples' => $x['examples']])) ?></span><?php if ($why): ?> <span class="wz-card__why"><?= e($why) ?></span><?php endif; ?></span>
      </span>
    </label>
    <?php endforeach; ?>
  </fieldset>
  <?= $err('purpose') ?>
  <?php if ($canFeatures && array_filter(array_keys($purposes), fn($k) => Purpose::unavailable($k) !== null)): ?>
  <p class="wz-note"><?= e(__('Ausgegraute Arten lassen sich unter Funktionen & Erweiterungen einschalten.')) ?> <a href="<?= e(url('/admin/funktionen')) ?>"><?= e(__('Funktionen öffnen')) ?></a></p>
  <?php endif; ?>

  <?php if (\Core\AI\Assist::available('text')): ?>
  <details class="wz-ai"<?= isset($errors['ai']) || ($st['ai'] ?? '') !== '' ? ' open' : '' ?>>
    <summary><span class="kia-spark" aria-hidden="true"><?= icon('sparkle') ?></span> <?= e(__('Lieber beschreiben? KLXM AI schlägt Art und Felder vor')) ?></summary>
    <div class="wz-ai__body">
      <label for="wz-ai-text"><?= e(__('Beschreiben Sie kurz, was Sie brauchen')) ?></label>
      <textarea id="wz-ai-text" name="ai_text" rows="3" maxlength="2000" placeholder="<?= e(__('z. B. Ein Rückrufformular mit Name, Telefon und Wunschzeit, das an die Anmeldung geht')) ?>"><?= e((string) ($st['ai'] ?? '')) ?></textarea>
      <?= $err('ai') ?>
      <p class="f-help"><?= e(__('Die Beschreibung geht an den eingestellten KI-Anbieter. Sie prüfen den Vorschlag im nächsten Schritt, bevor etwas angelegt wird.')) ?></p>
      <div><button type="submit" name="go" value="ai" class="adm-btn kia-btn"><span class="kia-spark" aria-hidden="true"><?= icon('sparkle') ?></span> <?= e(__('Vorschlag holen')) ?></button></div>
    </div>
  </details>
  <?php endif; ?>

  <div class="wz-nav">
    <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/data')) ?>"><?= e(__('Abbrechen')) ?></a>
    <button type="submit" name="go" value="next" class="adm-btn adm-btn--primary"><?= e(__('Weiter')) ?> <span aria-hidden="true">→</span></button>
  </div>

<?php elseif ($step === 'vorlage'): $keys = Purpose::presetKeys($p, $presets); $cur = isset($st['fields']) ? (($st['preset'] ?? '') === '' ? '_empty' : (string) $st['preset']) : ($keys[0] ?? '_empty'); ?>
  <p class="wz-chosen"><span class="wz-chosen__ico wz-ico--<?= e($p) ?>" aria-hidden="true"><?= icon($purposes[$p]['icon']) ?></span> <?= e($purposes[$p]['label']) ?> · <a href="<?= e(url('/admin/data/new?schritt=art')) ?>"><?= e(__('ändern')) ?></a></p>
  <fieldset class="wz-cards wz-cards--tpl">
    <legend class="adm-sr"><?= e(__('Vorlage')) ?></legend>
    <?php foreach ($keys as $k): $x = $presets[$k]; ?>
    <label class="wz-card">
      <input type="radio" name="preset" value="<?= e($k) ?>"<?= $cur === $k ? ' checked' : '' ?> aria-describedby="wz-t-<?= e($k) ?>">
      <span class="wz-card__ico" aria-hidden="true"><?= icon($x['icon']) ?></span>
      <span class="wz-card__body"><strong class="wz-card__title"><?= e(__($x['name'])) ?></strong>
        <span class="wz-card__lead" id="wz-t-<?= e($k) ?>"><?= e(implode(', ', array_map(fn($f) => __($f['label']), array_slice($x['fields'], 0, 5)))) ?><?= count($x['fields']) > 5 ? ' …' : '' ?></span></span>
    </label>
    <?php endforeach; ?>
    <label class="wz-card wz-card--empty">
      <input type="radio" name="preset" value="_empty"<?= $cur === '_empty' ? ' checked' : '' ?> aria-describedby="wz-t-empty">
      <span class="wz-card__ico" aria-hidden="true"><?= icon('plus') ?></span>
      <span class="wz-card__body"><strong class="wz-card__title"><?= e(__('Leer beginnen')) ?></strong>
        <span class="wz-card__lead" id="wz-t-empty"><?= e(in_array($p, ['mail', 'inbox', 'registration'], true) ? __('Name, E-Mail, Telefon, Nachricht – ergänzen Sie den Rest.') : __('Nur ein Titel-Feld – die übrigen Felder legen Sie selbst an.')) ?></span></span>
    </label>
  </fieldset>
  <?= $err('preset') ?>
  <div class="wz-nav">
    <button type="submit" name="go" value="back" class="adm-btn adm-btn--ghost"><span aria-hidden="true">←</span> <?= e(__('Zurück')) ?></button>
    <button type="submit" name="go" value="next" class="adm-btn adm-btn--primary"><?= e(__('Weiter')) ?> <span aria-hidden="true">→</span></button>
  </div>

<?php else: /* einstellungen */
  $kind = ($st['kind'] ?? 'content') === 'inbox' ? 'inbox' : 'content';
  $types = Wizard::types($st);
  $mode = Wizard::mailMode($st);
  $hasMail = (bool) array_filter($st['fields'], fn($f) => $f['on'] && $f['type'] === 'email');
  $sw = fn(string $name, bool $on, string $label, string $help = '', string $id = '') => '<div class="f f--bool"><input type="hidden" name="' . e($name) . '" value="0">'
      . '<label class="f-check"><input type="checkbox" role="switch" name="' . e($name) . '" value="1"' . ($on ? ' checked' : '') . ($id !== '' ? ' id="' . e($id) . '"' : '') . '> <span>' . e($label) . '</span></label>'
      . ($help !== '' ? '<p class="f-help">' . e($help) . '</p>' : '') . '</div>';
?>
  <p class="wz-chosen"><span class="wz-chosen__ico wz-ico--<?= e($p) ?>" aria-hidden="true"><?= icon($purposes[$p]['icon']) ?></span> <?= e($purposes[$p]['label']) ?>
    <?php if (($st['preset'] ?? '') === '_ai'): ?> · <?= e(__('Vorschlag von KLXM AI')) ?><?php elseif (($st['preset'] ?? '') !== ''): ?> · <?= e(__('Vorlage „{name}“', ['name' => __($presets[$st['preset']]['name'] ?? $st['preset'])])) ?><?php else: ?> · <?= e(__('leer begonnen')) ?><?php endif; ?>
    · <a href="<?= e(url('/admin/data/new?schritt=' . (($st['preset'] ?? '') === '_ai' ? 'art' : 'vorlage'))) ?>"><?= e(__('ändern')) ?></a></p>

  <div class="set-groups wz-groups">
    <section class="set-group" aria-labelledby="wz-g-name">
      <h3 class="set-group__title" id="wz-g-name"><?= e(__('Name')) ?></h3>
      <div class="set-list">
        <div class="f f--inline"><label for="wz-name"><?= e(__('Name (Mehrzahl)')) ?> <span class="req">*</span></label>
          <p class="f-help"><?= e(__('So heißt es in der Verwaltung, z. B. „Kontakt“, „Aktuelles“.')) ?></p>
          <input id="wz-name" name="name" value="<?= e($st['name']) ?>" required maxlength="60"<?= isset($errors['name']) ? ' aria-invalid="true" aria-describedby="wz-e-name"' : '' ?>><?= $err('name') ?></div>
        <div class="f f--inline"><label for="wz-sing"><?= e(__('Einzahl')) ?></label>
          <p class="f-help"><?= e(__('Für Knöpfe wie „+ Beitrag“.')) ?></p>
          <input id="wz-sing" name="singular" value="<?= e($st['singular']) ?>" maxlength="60" placeholder="<?= e(__('z. B. Nachricht')) ?>"></div>
      </div>
    </section>

    <section class="set-group" aria-labelledby="wz-g-fields">
      <h3 class="set-group__title" id="wz-g-fields"><?= e($kind === 'inbox' || $p === 'registration' ? __('Felder des Formulars') : __('Felder')) ?></h3>
      <p class="set-group__note"><?= e(__('Abgewählte Felder werden nicht angelegt. Bezeichnungen und Typen können Sie hier ändern; Hilfetexte, Bedingungen und mehr später unter „Felder & Einstellungen“.')) ?></p>
      <div class="set-list wz-fields">
        <?php $n = count($st['fields']); foreach ($st['fields'] as $i => $f): $fid = 'wf-' . $i; ?>
        <div class="wz-field<?= $f['on'] ? '' : ' is-off' ?>" id="<?= $fid ?>" role="group" aria-label="<?= e(__('Feld {n}: {label}', ['n' => $i + 1, 'label' => $f['label']])) ?>">
          <label class="wz-field__on" title="<?= e(__('Übernehmen')) ?>"><input type="checkbox" name="f[<?= $i ?>][on]" value="1"<?= $f['on'] ? ' checked' : '' ?>><span class="adm-sr"><?= e(__('„{label}“ übernehmen', ['label' => $f['label']])) ?></span></label>
          <div class="wz-field__main">
            <input class="wz-field__label" name="f[<?= $i ?>][label]" value="<?= e($f['label']) ?>" maxlength="60" aria-label="<?= e(__('Bezeichnung')) ?>">
            <select class="wz-field__type" name="f[<?= $i ?>][type]" aria-label="<?= e(__('Feldtyp von „{label}“', ['label' => $f['label']])) ?>">
              <?php foreach ($types as $ty): ?><option value="<?= e($ty) ?>"<?= $f['type'] === $ty ? ' selected' : '' ?>><?= e(__(Tables::TYPES[$ty][0])) ?></option><?php endforeach; ?>
            </select>
            <label class="f-check wz-field__req"><input type="checkbox" name="f[<?= $i ?>][required]" value="1"<?= $f['required'] ? ' checked' : '' ?>> <span><?= e(__('Pflicht')) ?></span></label>
            <?php if (in_array($f['type'], ['select', 'multiselect'], true)): ?>
            <label class="wz-field__opts"><span><?= e(__('Auswahlmöglichkeiten (eine pro Zeile)')) ?></span>
              <textarea name="f[<?= $i ?>][options]" rows="3"><?= e($f['options']) ?></textarea></label>
            <?php else: ?><input type="hidden" name="f[<?= $i ?>][options]" value="<?= e($f['options']) ?>"><?php endif; ?>
          </div>
          <div class="wz-field__move">
            <button type="submit" name="go" value="up:<?= $i ?>" class="dt-iconbtn"<?= $i === 0 ? ' disabled' : '' ?> aria-label="<?= e(__('„{label}“ nach oben', ['label' => $f['label']])) ?>">↑</button>
            <button type="submit" name="go" value="down:<?= $i ?>" class="dt-iconbtn"<?= $i === $n - 1 ? ' disabled' : '' ?> aria-label="<?= e(__('„{label}“ nach unten', ['label' => $f['label']])) ?>">↓</button>
          </div>
        </div>
        <?php endforeach; ?>
        <div class="wz-field wz-field--new" id="wf-new">
          <span class="wz-field__on" aria-hidden="true"><?= icon('plus') ?></span>
          <div class="wz-field__main">
            <input class="wz-field__label" name="new_label" value="" maxlength="60" placeholder="<?= e(__('Neues Feld, z. B. Firma')) ?>" aria-label="<?= e(__('Bezeichnung des neuen Feldes')) ?>">
            <select class="wz-field__type" name="new_type" aria-label="<?= e(__('Feldtyp des neuen Feldes')) ?>">
              <?php foreach ($types as $ty): ?><option value="<?= e($ty) ?>"><?= e(__(Tables::TYPES[$ty][0])) ?></option><?php endforeach; ?>
            </select>
            <button type="submit" name="go" value="add" class="adm-btn adm-btn--small"><?= e(__('Hinzufügen')) ?></button>
          </div>
        </div>
      </div>
      <?= $err('fields') ?>
    </section>

    <?php if ($p === 'content' || $p === 'source'): ?>
    <section class="set-group" aria-labelledby="wz-g-web">
      <h3 class="set-group__title" id="wz-g-web"><?= e(__('Auf der Website')) ?></h3>
      <div class="set-list">
        <?= $sw('list', !empty($st['list']), __('Übersicht als Liste auf einer Seite'), __('Im nächsten Schritt legen Sie dafür eine Seite mit dem Block „Datenliste“ an – oder fügen ihn in eine bestehende Seite ein.')) ?>
        <?= $sw('detail', !empty($st['detail']), __('Eigene Detailseite je Eintrag'), __('Sinnvoll bei längeren Texten (Beiträge, Termine, Personen). Kurze Einträge wie Fragen & Antworten brauchen keine.')) ?>
        <div class="f f--inline"><label for="wz-route"><?= e(__('Adresse der Detailseiten')) ?></label>
          <p class="f-help"><?= e(__('Leer = aus dem Namen. Nur mit Detailseiten.')) ?></p>
          <div class="adm-prefix"><span>/</span><input id="wz-route" name="route" value="<?= e((string) $st['route']) ?>" placeholder="<?= e(Tables::normName($st['name']) !== '' ? str_replace('_', '-', Tables::normName($st['name'])) : 'aktuelles') ?>" maxlength="60"></div></div>
        <?php if (\Core\Push\Push::on()): ?>
        <?= $sw('push', !empty($st['push']), __('Besucher können neue Einträge abonnieren (Push)'), __('Nur mit Detailseiten. Den Knopf zeigt der Block „Benachrichtigungen abonnieren“.')) ?>
        <?php endif; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($kind === 'inbox' || $p === 'registration'): ?>
    <section class="set-group" aria-labelledby="wz-g-form">
      <h3 class="set-group__title" id="wz-g-form"><?= e($kind === 'inbox' ? __('Zustellung & Benachrichtigung') : __('Formular & Benachrichtigung')) ?></h3>
      <div class="set-list">
        <?php if ($kind === 'inbox' && count(Wizard::modes($st)) > 1): ?>
        <div class="f f--inline"><label for="wz-mode"><?= e(__('Wohin gehen die Einsendungen?')) ?></label>
          <select id="wz-mode" name="mode">
            <?php foreach (Wizard::modes($st) as $m): ?><option value="<?= e($m) ?>"<?= $mode === $m ? ' selected' : '' ?>><?= e(Delivery::modeLabel($m)) ?></option><?php endforeach; ?>
          </select></div>
        <?php endif; ?>
        <?php if ($kind === 'inbox' && ($mode !== 'system' || count(Wizard::modes($st)) > 1)): ?>
        <div class="f f--inline"><label for="wz-to"><?= e(__('E-Mail an')) ?><?= $mode !== 'system' ? ' <span class="req">*</span>' : '' ?></label>
          <p class="f-help"><?= e($p === 'mail' ? __('Jede Einsendung geht mit vollem Inhalt an diese Adressen (mehrere mit Komma).') : __('Nur bei Zustellung per E-Mail: Inhalt geht an diese Adressen (mehrere mit Komma).')) ?></p>
          <input id="wz-to" type="text" inputmode="email" name="to" value="<?= e((string) $st['to']) ?>" placeholder="anfragen@example.org" autocomplete="off" spellcheck="false"<?= isset($errors['to']) ? ' aria-invalid="true" aria-describedby="wz-e-to"' : '' ?>><?= $err('to') ?></div>
        <?php endif; ?>
        <?php if ($mode === 'system' || $kind === 'content'): ?>
        <div class="f f--inline"><label for="wz-notify"><?= e(__('Benachrichtigung an')) ?></label>
          <p class="f-help"><?= e(__('Hinweis auf eine neue Einsendung – ohne Inhalte, nur mit Link. Leer = Empfänger aus den Grundeinstellungen.')) ?></p>
          <input id="wz-notify" type="text" inputmode="email" name="notify" value="<?= e((string) $st['notify']) ?>" placeholder="<?= e(__('leer = Standard')) ?>" autocomplete="off" spellcheck="false"></div>
        <?php endif; ?>
        <?= $sw('receipt', !empty($st['receipt']), __('Bestätigung per E-Mail an die Absender'), $hasMail ? __('Kurze Eingangsbestätigung ohne die eingegebenen Angaben (Text später anpassbar).') : __('Braucht ein Feld vom Typ „E-Mail“ im Formular.')) ?>
        <?= $err('receipt') ?>
        <?php if ($kind === 'content'): ?>
        <div class="f f--inline"><label for="wz-max"><?= e(__('Obergrenze (Plätze)')) ?></label>
          <p class="f-help"><?= e(__('0 = keine. Danach zeigt das Formular „ausgebucht“.')) ?></p>
          <input id="wz-max" type="number" name="max" min="0" max="<?= \Core\Data\DataForms::MAX_ENTRIES ?>" value="<?= (int) $st['max'] ?>"></div>
        <?php else: ?>
        <div class="f f--inline"><label for="wz-ret"><?= e(__('Löschfrist (Tage)')) ?></label>
          <p class="f-help"><?= e($mode === 'mail' ? __('Gilt nur für Einsendungen, die nach einem Zustellfehler verschlüsselt gesichert wurden.') : __('Erledigte Anfragen werden nach so vielen Tagen gelöscht (0 = nie).')) ?></p>
          <input id="wz-ret" type="number" name="retention" min="0" max="3650" value="<?= (int) $st['retention'] ?>"></div>
        <?php endif; ?>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Spamschutz')) ?></span>
          <span class="set-row__sub"><?= e(__('Mehrstufig und ohne Cookies – immer eingeschaltet.')) ?></span></div><div class="set-row__ctl"><span class="adm-badge"><?= e(__('Aktiv')) ?></span></div></div>
      </div>
      <p class="set-group__note"><?= e(match (true) {
          $p === 'mail' => __('Datenschutz: Inhalte gehen per E-Mail hinaus und werden auf der Website nicht gespeichert (nur ein Zustellprotokoll ohne Inhalte). Ohne S/MIME-Zertifikat ist die E-Mail nur auf dem Transportweg verschlüsselt – für Gesundheitsdaten lieber „Anfragen sammeln“ wählen oder S/MIME einrichten. Scheitert der Versand, wird die Einsendung verschlüsselt gesichert.'),
          $kind === 'inbox' => __('Datenschutz: Alle Angaben werden Ende-zu-Ende verschlüsselt gespeichert und sind nur unter „Anfragen“ mit dem geheimen Schlüssel lesbar.'),
          default => __('Datenschutz: Anmeldungen werden nicht verschlüsselt gespeichert und erscheinen als Entwurf in der Teilnehmerliste – nicht auf der Website, nicht in der Suche. Für vertrauliche Angaben lieber „Anfragen sammeln“ wählen.'),
      }) ?></p>
      <?php if ($kind === 'inbox' && !\Core\FormCrypto::ready()): ?>
      <p class="adm-flash adm-flash--error"><?= e(__('Noch kein Schlüssel für verschlüsselte Formulare – bis dahin zeigt das Formular „noch nicht eingerichtet“.')) ?> <?php if (can('system.manage')): ?><a href="<?= e(url('/admin/system#keys')) ?>"><?= e(__('Schlüssel erzeugen')) ?></a><?php endif; ?></p>
      <?php endif; ?>
      <?php if ($kind === 'inbox' && $mode !== 'system' && ($tr = Delivery::transport()) && $tr['level'] !== 'ok'): ?>
      <p class="<?= $tr['level'] === 'error' ? 'adm-flash adm-flash--error' : 'dt-note' ?>"><?= e(__('Versand:')) ?> <?= e($tr['text']) ?></p>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($p === 'internal'): ?>
    <p class="wz-note"><?= icon('lock') ?> <?= e(__('Interne Listen haben keine Detailseiten, kein Formular und erscheinen nicht in Suche und Sitemap. Einträge pflegen Sie unter Daten.')) ?></p>
    <?php endif; ?>
  </div>

  <div class="wz-nav">
    <button type="submit" name="go" value="back" class="adm-btn adm-btn--ghost"><span aria-hidden="true">←</span> <?= e(__('Zurück')) ?></button>
    <button type="submit" name="go" value="next" class="adm-btn adm-btn--primary"><?= e($kind === 'inbox' || $p === 'registration' ? __('Formular anlegen') : __('Tabelle anlegen')) ?> <span aria-hidden="true">→</span></button>
  </div>
<?php endif; ?>
</form>
