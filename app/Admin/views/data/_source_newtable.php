<?php
/**
 * Zieltabelle „+ Neue Tabelle aus dieser Quelle anlegen …“ (eingebunden in data/source.php): Vorschlag aus den Beispiel-Einträgen
 * der letzten Vorschau (Core\Sources\Mapper::proposeTable) – Name, Kurzname, Adresse, Felder mit Typ und Beispiel, Seiten.
 * „Tabelle anlegen und zuordnen“ (do=newtable) legt die Tabelle an, speichert die Quelle mit Zuordnung und zeigt den Probeabruf.
 * @var array $newTable  @var bool $isNew
 */
use Core\Data\Tables;
use Core\Sources\Mapper;

$nt = $newTable;
?>
<section class="src-newtable" id="src-newtable" aria-labelledby="src-h-nt" tabindex="-1"<?= ($focus ?? null) === 'src-newtable' ? ' data-autofocus' : '' ?>>
  <h3 id="src-h-nt"><?= icon('plus') ?> <?= e(__('Neue Tabelle aus dieser Quelle')) ?></h3>
  <?php if (!empty($nt['unavailable'])): ?>
  <p class="src-empty"><?= icon('info') ?> <span><?= e(__('Für den Vorschlag braucht es die Daten der Quelle: Erst Adresse eintragen und „Vorschau laden“.')) ?></span></p>
  <button class="adm-btn" name="do" value="preview"><?= icon('eye') ?> <?= e(__('Vorschau laden')) ?></button>
  <?php else: ?>
  <?php if (!empty($nt['errors'])): ?>
  <div class="adm-flash adm-flash--error" role="alert"><?= icon('warning') ?> <span><?= e(__('Die Tabelle wurde nicht angelegt:')) ?> <?= e(implode(' ', $nt['errors'])) ?></span></div>
  <?php endif; ?>
  <p class="f-help"><?= e(__('Felder und Typen sind aus den Daten der Quelle vorgeschlagen. Entfernen Sie den Haken bei allem, was Sie nicht brauchen; Bezeichnung und Typ lassen sich ändern. Die Zuordnung wird automatisch ausgefüllt.')) ?></p>
  <div class="adm-fields">
    <div class="f f--half"><label for="nt-name"><?= e(__('Name der Tabelle')) ?></label>
      <input id="nt-name" name="nt[name]" value="<?= e((string) $nt['name']) ?>" maxlength="60" required></div>
    <div class="f f--half"><label for="nt-handle"><?= e(__('Kurzname')) ?></label>
      <input id="nt-handle" name="nt[handle]" value="<?= e((string) $nt['handle']) ?>" maxlength="40" pattern="[a-z][a-z0-9_]+" spellcheck="false" aria-describedby="nt-handle-h">
      <small class="src-sub" id="nt-handle-h"><?= e(__('Nur a–z, 0–9 und _. Gibt es ihn schon, wird _2, _3 … angehängt.')) ?></small></div>
  </div>
  <div class="src-nt-wrap">
    <table class="adm-table src-nt">
      <caption class="sr-only"><?= e(__('Vorgeschlagene Felder')) ?></caption>
      <thead><tr><th scope="col"><?= e(__('Übernehmen')) ?></th><th scope="col"><?= e(__('Bezeichnung')) ?></th><th scope="col"><?= e(__('Typ')) ?></th><th scope="col"><?= e(__('Aus der Quelle (Beispiel)')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($nt['fields'] as $i => $f): $id = 'nt-f' . $i; ?>
        <tr<?= $f['on'] ? '' : ' class="is-off"' ?>>
          <td><input type="checkbox" id="<?= e($id) ?>-on" name="nt[fields][<?= $i ?>][on]" value="1"<?= $f['on'] ? ' checked' : '' ?>>
            <label class="sr-only" for="<?= e($id) ?>-on"><?= e(__('Feld übernehmen: {label}', ['label' => $f['label']])) ?></label></td>
          <td><label class="sr-only" for="<?= e($id) ?>-l"><?= e(__('Bezeichnung für {path}', ['path' => $f['path']])) ?></label>
            <input id="<?= e($id) ?>-l" name="nt[fields][<?= $i ?>][label]" value="<?= e($f['label']) ?>" maxlength="80"></td>
          <td><label class="sr-only" for="<?= e($id) ?>-t"><?= e(__('Typ für {path}', ['path' => $f['path']])) ?></label>
            <select id="<?= e($id) ?>-t" name="nt[fields][<?= $i ?>][type]"><?php foreach (Mapper::NEW_TABLE_TYPES as $ty): ?><option value="<?= e($ty) ?>"<?= $f['type'] === $ty ? ' selected' : '' ?>><?= e(__(Tables::TYPES[$ty][0])) ?></option><?php endforeach; ?></select></td>
          <td><code><?= e($f['path']) ?></code><?php if ($f['sample'] !== ''): ?><small class="src-nt__sample"><?= e(mb_strimwidth($f['sample'], 0, 90, '…')) ?></small><?php endif; ?>
            <input type="hidden" name="nt[fields][<?= $i ?>][path]" value="<?= e($f['path']) ?>"><input type="hidden" name="nt[fields][<?= $i ?>][sample]" value="<?= e(mb_strimwidth($f['sample'], 0, 150, '…')) ?>"></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$nt['fields']): ?><tr><td colspan="4" class="adm-muted"><?= e(__('In der Quelle wurden keine Felder gefunden.')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <fieldset class="src-nt-opts">
    <legend><?= e(__('Seiten auf der Website')) ?></legend>
    <label class="f-check"><input type="checkbox" name="nt[detail]" value="1"<?= !empty($nt['detail']) ? ' checked' : '' ?>> <span><?= e(__('Detailseite für jeden Eintrag anlegen')) ?></span></label>
    <label class="f-check"><input type="checkbox" name="nt[list]" value="1"<?= !empty($nt['list']) ? ' checked' : '' ?>> <span><?= e(__('Übersichtsseite mit allen Einträgen anlegen')) ?></span></label>
    <label class="f-check"><input type="checkbox" name="nt[menu]" value="1"<?= !empty($nt['menu']) ? ' checked' : '' ?>> <span><?= e(__('Übersichtsseite in der Navigation der Website zeigen')) ?></span></label>
    <div class="f src-nt-route"><label for="nt-route"><?= e(__('Adresse der Seiten')) ?></label>
      <div class="adm-row"><span class="adm-muted" aria-hidden="true">/</span><input id="nt-route" name="nt[route]" value="<?= e((string) $nt['route']) ?>" maxlength="60" spellcheck="false" aria-describedby="nt-route-h"></div>
      <small class="src-sub" id="nt-route-h"><?= e(__('Übersicht unter /adresse, Einträge unter /adresse/titel-des-eintrags. Ist sie vergeben, wird -2, -3 … angehängt.')) ?></small></div>
  </fieldset>
  <div class="adm-row src-nt-actions">
    <button class="adm-btn adm-btn--primary" name="do" value="newtable"><?= icon('check-circle') ?> <?= e(__('Tabelle anlegen und zuordnen')) ?></button>
    <p class="f-help"><?= e($isNew ? __('Die Quelle wird dabei angelegt. Danach sehen Sie den Probeabruf.') : __('Die Quelle wird dabei gespeichert. Danach sehen Sie den Probeabruf.')) ?></p>
  </div>
  <?php endif; ?>
</section>
