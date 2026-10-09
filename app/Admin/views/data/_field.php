<?php
/** Eine Feldzeile im Tabellen-Designer. @var int|string $i  @var array $f  @var array $tables  @var ?string $err  @var ?array $types (erlaubte Typen)  @var ?bool $inbox */
use Core\Data\Tables;
$types ??= array_keys(Tables::TYPES);
$inbox ??= false;
$noForm ??= [];
$n = 'fields[' . $i . ']';
$opts = is_array($f['options'] ?? null) ? implode("\n", array_map(fn($k, $v) => Tables::normName((string) $v) === (string) $k ? $v : "$k=$v", array_keys($f['options']), $f['options'])) : (string) ($f['options'] ?? '');
$type = $f['type'] ?? 'text';
?>
<li class="dt-field<?= $err ? ' is-error' : '' ?>" data-field data-type="<?= e($type) ?>">
  <input type="hidden" name="<?= $n ?>[id]" value="<?= e($f['id'] ?? '') ?>">
  <span class="dt-field__icon" aria-hidden="true" data-type-icon><?= icon(Tables::TYPES[$type][2] ?? 'text-t') ?></span>
  <div class="dt-field__main">
    <div class="dt-field__row">
      <label class="dt-in dt-in--label"><span>Bezeichnung</span><input name="<?= $n ?>[label]" value="<?= e($f['label'] ?? '') ?>" placeholder="z. B. Titel" data-label></label>
      <label class="dt-in dt-in--name"><span>Kurzname</span><input name="<?= $n ?>[name]" value="<?= e($f['name'] ?? '') ?>" pattern="[a-z][a-z0-9_]*" data-name<?= !empty($f['id']) ? ' data-locked' : '' ?>></label>
      <label class="dt-in dt-in--type"><span>Typ</span><select name="<?= $n ?>[type]" data-type>
        <?php foreach (Tables::TYPES as $k => [$l]): if (!in_array($k, $types, true) && $k !== $type) continue; ?><option value="<?= e($k) ?>"<?= $k === $type ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
      </select></label>
    </div>
    <?php if ($noForm): ?><p class="dt-field__noform" data-show-for="<?= e(implode(' ', $noForm)) ?>"<?= in_array($type, $noForm, true) ? '' : ' hidden' ?>><?= icon('warning') ?> <?= e(__('Erscheint nicht im öffentlichen Formular – Besucher können diesen Feldtyp nicht ausfüllen (nur die Redaktion). Für Fragen an Besucher z. B. „Ja / Nein“, „Auswahl“ oder „Text (mehrzeilig)“ wählen.')) ?></p><?php endif; ?>
    <div class="dt-field__row dt-field__flags" data-hide-for="<?= e(implode(' ', Tables::LAYOUT)) ?>"<?= Tables::isLayout($f) ? ' hidden' : '' ?>>
      <?php foreach ($inbox ? ['required' => 'Pflichtfeld'] : ['required' => 'Pflichtfeld', 'in_list' => 'In der Liste zeigen', 'searchable' => 'Durchsuchbar'] as $k => $l): ?>
      <label class="f-check"><input type="checkbox" name="<?= $n ?>[<?= $k ?>]" value="1"<?= !empty($f[$k]) ? ' checked' : '' ?>> <span><?= $l ?></span></label>
      <?php endforeach; ?>
      <label class="f-check"><input type="checkbox" name="<?= $n ?>[width]" value="half"<?= ($f['width'] ?? '') === 'half' ? ' checked' : '' ?>> <span>Halbe Breite</span></label>
    </div>
    <?php
    // Gestaltung des Formulars (keine Spalte): Abschnitt – Darstellung; Freitext – formatierter Text (Formatierungsleiste wie im Rich-Text-Feld)
    $sid = 'dts-' . preg_replace('~[^a-z0-9_]~i', '', (string) $i);
    $text = (string) ($f['text'] ?? '');
    ?>
    <div class="dt-field__extra" data-show-for="section"<?= $type === 'section' ? '' : ' hidden' ?>>
      <label class="dt-in"><span><?= e(__('Darstellung')) ?></span><select name="<?= $n ?>[style]">
        <option value="heading"<?= ($f['style'] ?? 'heading') !== 'fieldset' ? ' selected' : '' ?>><?= e(__('Zwischenüberschrift')) ?></option>
        <option value="fieldset"<?= ($f['style'] ?? '') === 'fieldset' ? ' selected' : '' ?>><?= e(__('Gruppe mit Rahmen')) ?></option>
      </select></label>
      <p class="f-help"><?= e(__('Die folgenden Felder gehören zu diesem Abschnitt – bis zum nächsten Abschnitt. Die Bezeichnung ist die Überschrift im Formular, der Hilfetext steht darunter. Speichert keine Daten.')) ?></p>
    </div>
    <div class="dt-field__extra" data-show-for="content"<?= $type === 'content' ? '' : ' hidden' ?>>
      <div class="dt-in dt-in--rich"><span id="<?= $sid ?>-l"><?= e(__('Text im Formular')) ?></span>
        <div class="rte" data-mode="richtext"><div class="rte-bar" role="toolbar" aria-label="<?= e(__('Formatierung')) ?>"></div><div class="rte-area" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="<?= $sid ?>-l"><?= \Core\Sanitizer::block($text) ?></div><input type="hidden" name="<?= $n ?>[text]" value="<?= e($text) ?>"></div></div>
      <p class="f-help"><?= e(__('Erscheint an dieser Stelle im Formular, z. B. als Hinweis zwischen den Feldern. Die Bezeichnung dient nur hier im Designer als Name. Speichert keine Daten.')) ?></p>
    </div>
    <div class="dt-field__extra" data-show-for="select multiselect">
      <label class="dt-in"><span>Auswahlmöglichkeiten (eine pro Zeile)</span><textarea name="<?= $n ?>[options]" rows="3" placeholder="Neuigkeiten&#10;Hinweise&#10;Veranstaltungen"><?= e($opts) ?></textarea></label>
    </div>
    <div class="dt-field__extra" data-show-for="relation relations">
      <label class="dt-in"><span>Verknüpfte Tabelle</span><select name="<?= $n ?>[target]"><option value="">– wählen –</option>
        <?php foreach ($tables as $h => $l): ?><option value="<?= e($h) ?>"<?= ($f['target'] ?? '') === $h ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
    </div>
    <?php
    // Wiederholbare Gruppe: Unterfelder (Bezeichnung, Kurzname, Typ, Pflicht, halbe Breite, Auswahlmöglichkeiten) + Anzahl und Beschriftungen
    $subRow = function (string $k, array $sf) use ($n): string {
        $p = $n . '[fields][' . $k . ']';
        $st = $sf['type'] ?? 'text';
        $sopts = is_array($sf['options'] ?? null) ? implode("\n", array_map(fn($a, $b) => Tables::normName((string) $b) === (string) $a ? $b : "$a=$b", array_keys($sf['options']), $sf['options'])) : (string) ($sf['options'] ?? '');
        $h = '<li class="dt-sub" data-sub><div class="dt-sub__row">'
            . '<label class="dt-in dt-in--label"><span>' . e(__('Bezeichnung')) . '</span><input name="' . $p . '[label]" value="' . e($sf['label'] ?? '') . '" data-sub-label></label>'
            . '<label class="dt-in dt-in--name"><span>' . e(__('Kurzname')) . '</span><input name="' . $p . '[name]" value="' . e($sf['name'] ?? '') . '" pattern="[a-z][a-z0-9_]*" data-sub-name' . (!empty($sf['name']) ? ' data-touched="1"' : '') . '></label>'
            . '<label class="dt-in dt-in--type"><span>' . e(__('Typ')) . '</span><select name="' . $p . '[type]" data-sub-type>';
        foreach (Tables::GROUP_TYPES as $gt) $h .= '<option value="' . $gt . '"' . ($gt === $st ? ' selected' : '') . '>' . e(Tables::TYPES[$gt][0]) . '</option>';
        $h .= '</select></label>'
            . '<span class="dt-sub__tools"><button type="button" class="cms-iconbtn dt-iconbtn" data-sub-move="-1" aria-label="' . e(__('Unterfeld nach oben')) . '">↑</button>'
            . '<button type="button" class="cms-iconbtn dt-iconbtn" data-sub-move="1" aria-label="' . e(__('Unterfeld nach unten')) . '">↓</button>'
            . '<button type="button" class="cms-iconbtn dt-iconbtn dt-iconbtn--danger" data-sub-remove aria-label="' . e(__('Unterfeld entfernen')) . '">✕</button></span></div>'
            . '<div class="dt-sub__row dt-field__flags"><label class="f-check"><input type="checkbox" name="' . $p . '[required]" value="1"' . (!empty($sf['required']) ? ' checked' : '') . '> <span>' . e(__('Pflichtfeld')) . '</span></label>'
            . '<label class="f-check"><input type="checkbox" name="' . $p . '[width]" value="half"' . (($sf['width'] ?? '') === 'half' ? ' checked' : '') . '> <span>' . e(__('Halbe Breite')) . '</span></label></div>'
            . '<label class="dt-in" data-sub-opts' . ($st === 'select' ? '' : ' hidden') . '><span>' . e(__('Auswahlmöglichkeiten (eine pro Zeile)')) . '</span><textarea name="' . $p . '[options]" rows="3">' . e($sopts) . '</textarea></label>';
        foreach ((array) ($sf['labels'] ?? []) as $lc => $lv) $h .= '<input type="hidden" name="' . $p . '[labels][' . e((string) $lc) . ']" value="' . e((string) $lv) . '">';
        foreach ((array) ($sf['options_i18n'] ?? []) as $lc => $ov) foreach ((array) $ov as $ok => $olv) $h .= '<input type="hidden" name="' . $p . '[options_i18n][' . e((string) $lc) . '][' . e((string) $ok) . ']" value="' . e((string) $olv) . '">';
        return $h . '</li>';
    };
    $subs = is_array($f['fields'] ?? null) ? array_values($f['fields']) : [];
    ?>
    <div class="dt-field__extra dt-group" data-show-for="group">
      <fieldset class="dt-group__subs"><legend><?= e(__('Unterfelder je Eintrag')) ?></legend>
        <ol class="dt-subs" data-subs><?php foreach ($subs as $k => $sf) echo $subRow((string) $k, (array) $sf); ?></ol>
        <template data-sub-template><?= $subRow('__s__', ['type' => 'text']) ?></template>
        <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-sub-add>+ <?= e(__('Unterfeld')) ?></button>
      </fieldset>
      <div class="dt-group__opts">
        <label class="dt-in"><span><?= e(__('Mindestens')) ?></span><input type="number" name="<?= $n ?>[min]" min="0" max="20" value="<?= e((string) ($f['min'] ?? '')) ?>" placeholder="<?= e(__('1 bei Pflicht, sonst 0')) ?>"></label>
        <label class="dt-in"><span><?= e(__('Höchstens')) ?></span><input type="number" name="<?= $n ?>[max]" min="1" max="50" value="<?= e((string) ($f['max'] ?? 10)) ?>"></label>
        <label class="dt-in"><span><?= e(__('Bezeichnung eines Eintrags')) ?></span><input name="<?= $n ?>[item_label]" value="<?= e($f['item_label'] ?? '') ?>" placeholder="<?= e(__('z. B. Medikament')) ?>"></label>
        <label class="dt-in"><span><?= e(__('Beschriftung „Hinzufügen“')) ?></span><input name="<?= $n ?>[add_label]" value="<?= e($f['add_label'] ?? '') ?>" placeholder="<?= e(__('z. B. Weiteres Medikament')) ?>"></label>
      </div>
      <p class="f-help"><?= e(__('Besucher können im Formular Zeilen hinzufügen und entfernen. Leere Zeilen werden ignoriert. Bedingungen anderer Felder können nur prüfen, ob die Gruppe ausgefüllt ist.')) ?></p>
    </div>
    <?php
    // Datei: erlaubte Dateitypen (geprüft am Inhalt, nicht nur an der Endung) und eigene Höchstgröße – DOCX/ODT nur im Eingang (per E-Mail)
    $kindLabels = ['pdf' => __('PDF'), 'image' => __('Bilder (JPG, PNG, WebP)'), 'docx' => __('Word (DOCX)'), 'odt' => __('OpenDocument-Text (ODT)')];
    $acc = (array) ($f['accept'] ?? \Core\Data\DataForms::FILE_KINDS_DEFAULT);
    $fid = 'dtf-' . preg_replace('~[^a-z0-9_]~i', '', (string) $i);
    ?>
    <div class="dt-field__extra" data-show-for="file">
      <fieldset class="dt-kinds"><legend><?= e(__('Erlaubte Dateitypen')) ?></legend>
        <input type="hidden" name="<?= $n ?>[accept][]" value="">
        <?php foreach ($kindLabels as $k => $l): if (!$inbox && in_array($k, \Core\Data\DataForms::FILE_KINDS_INBOX, true)) continue; ?>
        <label class="f-check"><input type="checkbox" name="<?= $n ?>[accept][]" value="<?= $k ?>"<?= in_array($k, $acc, true) ? ' checked' : '' ?>> <span><?= e($l) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <label class="dt-in dt-in--mb"><span><?= e(__('Höchstgröße (MB, optional)')) ?></span><input type="number" name="<?= $n ?>[max_mb]" min="1" max="<?= \Core\Data\DataForms::MAX_MB ?>" value="<?= !empty($f['max_mb']) ? (int) $f['max_mb'] : '' ?>" placeholder="<?= e(__('wie Tabelle')) ?>" aria-describedby="<?= $fid ?>-kh"></label>
      <p class="f-help" id="<?= $fid ?>-kh"><?= e($inbox
          ? __('Geprüft wird der Inhalt der Datei, nicht nur die Endung; Word-Dateien mit Makros (DOCM) werden abgelehnt. Leer = Grenze der Tabelle; mehr als die Tabelle erlaubt, geht nicht. Dateien werden nur bei Zustellung per E-Mail angenommen – als Anhang bzw. versiegelt.')
          : __('Gilt für Uploads über das öffentliche Formular; geprüft wird der Inhalt der Datei, nicht nur die Endung. Leer = Grenze der Tabelle; mehr als die Tabelle erlaubt, geht nicht. Word und OpenDocument gibt es nur in Eingangs-Tabellen (Zustellung per E-Mail) – die Mediathek speichert sie nicht.')) ?></p>
    </div>
    <div class="dt-field__extra" data-show-for="iban">
      <input type="hidden" name="<?= $n ?>[mask]" value="0">
      <label class="f-check"><input type="checkbox" name="<?= $n ?>[mask]" value="1"<?= ($f['mask'] ?? true) ? ' checked' : '' ?>> <span><?= e(__('In Listen maskieren')) ?></span></label>
      <p class="f-help"><?= e(__('Zeigt in der Eintragsliste und auf der Website nur Land, Prüfziffer und die letzten zwei Zeichen (DE89 **** … 00).')) ?></p>
    </div>
    <?php
    // Bedingungen: anzeigen wenn · Pflicht wenn · Vergleich (Core\Data\Rules)
    $all ??= [];
    $self = (string) ($f['name'] ?? '');
    $nCond = count($f['visible_if']['rules'] ?? []) + count($f['required_if']['rules'] ?? []) + count($f['compare'] ?? []);
    $rules = fn(string $kind, string $group, array $list) => implode('', array_map(fn($r, $k) => Core\Theme::capture(__DIR__ . '/_rule.php',
        ['p' => $n . '[' . $group . ']' . ($kind === 'cmp' ? '' : '[rules]') . '[' . $k . ']', 'r' => $r, 'kind' => $kind, 'all' => $all, 'self' => $self]), $list, array_keys($list)));
    ?>
    <details class="dt-cond" data-cond<?= $nCond ? ' open' : '' ?> data-hide-for="<?= e(implode(' ', Tables::LAYOUT)) ?>"<?= Tables::isLayout($f) ? ' hidden' : '' ?>>
      <summary><?= e(__('Bedingungen')) ?> <span class="dt-cond__count" data-cond-count><?= $nCond ? '(' . $nCond . ')' : '' ?></span></summary>
      <?php foreach (['visible_if' => __('Feld nur anzeigen, wenn …'), 'required_if' => __('Pflichtfeld, wenn …')] as $g => $legend): $grp = (array) ($f[$g] ?? []); ?>
      <fieldset class="dt-cond__grp" data-cond-grp="<?= $g ?>">
        <legend><?= e($legend) ?></legend>
        <label class="dt-cond__mode" data-cond-mode<?= count($grp['rules'] ?? []) > 1 ? '' : ' hidden' ?>><span class="sr-only"><?= e(__('Verknüpfung der Bedingungen')) ?></span>
          <select name="<?= $n ?>[<?= $g ?>][mode]">
            <option value="all"<?= ($grp['mode'] ?? 'all') === 'all' ? ' selected' : '' ?>><?= e(__('alle Bedingungen treffen zu')) ?></option>
            <option value="any"<?= ($grp['mode'] ?? '') === 'any' ? ' selected' : '' ?>><?= e(__('eine davon trifft zu')) ?></option>
          </select></label>
        <ol class="dt-rules" data-rules><?= $rules('cond', $g, (array) ($grp['rules'] ?? [])) ?></ol>
        <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-rule-add="<?= $g ?>">+ <?= e(__('Bedingung')) ?></button>
      </fieldset>
      <?php endforeach; ?>
      <fieldset class="dt-cond__grp" data-cond-grp="compare" data-show-for="<?= e(implode(' ', Core\Data\Rules::COMPARE_TYPES)) ?>">
        <legend><?= e(__('Wert vergleichen')) ?></legend>
        <ol class="dt-rules" data-rules><?= $rules('cmp', 'compare', (array) ($f['compare'] ?? [])) ?></ol>
        <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-rule-add="compare">+ <?= e(__('Vergleich')) ?></button>
      </fieldset>
    </details>
    <label class="dt-in dt-in--help" data-hide-for="content"<?= $type === 'content' ? ' hidden' : '' ?>><span>Hilfetext (optional)</span><input name="<?= $n ?>[help]" value="<?= e($f['help'] ?? '') ?>"></label>
    <?php if (\Core\Lang::multi()): ?>
    <details class="dt-trans"<?= !empty($f['labels']) || !empty($f['options_i18n']) ? ' open' : '' ?>><summary><?= e(__('Übersetzungen')) ?></summary>
      <?php foreach (\Core\Lang::all() as $lc => $ll): if ($lc === \Core\Lang::default()) continue;
        $tOpts = implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys((array) ($f['options_i18n'][$lc] ?? [])), (array) ($f['options_i18n'][$lc] ?? []))); ?>
      <div class="dt-trans__lang">
        <label class="dt-in"><span><?= e(__('Bezeichnung')) ?> (<?= e($ll) ?>)</span><input name="<?= $n ?>[labels][<?= e($lc) ?>]" value="<?= e($f['labels'][$lc] ?? '') ?>" placeholder="<?= e($f['label'] ?? '') ?>"></label>
        <label class="dt-in" data-show-for="select multiselect"><span><?= e(__('Auswahltexte')) ?> (<?= e($ll) ?>) – <?= e(__('je Zeile „kurzname=Text“')) ?></span><textarea name="<?= $n ?>[options_i18n][<?= e($lc) ?>]" rows="3" placeholder="<?= e(implode("\n", array_map(fn($k) => "$k=…", array_keys((array) ($f['options'] ?? []))))) ?>"><?= e($tOpts) ?></textarea></label>
      </div>
      <?php endforeach; ?>
    </details>
    <?php endif; ?>
    <?php if ($err): ?><p class="f-error"><?= e($err) ?></p><?php endif; ?>
  </div>
  <div class="dt-field__tools">
    <button type="button" class="cms-iconbtn dt-iconbtn" data-move="-1" aria-label="Nach oben">↑</button>
    <button type="button" class="cms-iconbtn dt-iconbtn" data-move="1" aria-label="Nach unten">↓</button>
    <button type="button" class="cms-iconbtn dt-iconbtn dt-iconbtn--danger" data-remove-field aria-label="Feld entfernen">✕</button>
  </div>
</li>
