/*
 * Tabellen-Baukasten → Feld → „Bedingungen“ (anzeigen wenn · Pflicht wenn · Vergleich).
 * Hält die Feldauswahl aktuell (neue, umbenannte, entfernte Felder), passt das Wert-Feld an den Typ an
 * (Auswahl → Liste der Möglichkeiten, Ja/Nein → Ja/Nein, sonst Freitext) und blendet es bei „ist ausgefüllt/leer“ aus.
 * Gespeichert und geprüft wird serverseitig (Core\Data\Rules::normalize).
 */
import { t } from './_i18n.js';

const SRC = ['text', 'textarea', 'number', 'bool', 'date', 'datetime', 'time', 'select', 'multiselect', 'email', 'tel', 'url', 'link', 'color', 'iban', 'relation', 'media', 'file', 'group'];
const CMP = ['date', 'datetime', 'time', 'number', 'text'];

export function initRuleBuilder(schema, list, slug) {
  let uid = 0;
  const rows = () => [...list.querySelectorAll('[data-field]')];
  const info = li => {
    const label = li.querySelector('[data-label]').value.trim();
    const name = li.querySelector('[data-name]').value.trim() || slug(label);
    const type = li.querySelector('[data-type]').value;
    const opts = [];
    if (type === 'select' || type === 'multiselect') {
      for (const l of (li.querySelector('textarea[name$="[options]"]')?.value || '').split('\n').map(s => s.trim()).filter(Boolean)) {
        const i = l.indexOf('=');
        opts.push(i > 0 ? [l.slice(0, i).trim(), l.slice(i + 1).trim()] : [slug(l) || l, l]);
      }
    }
    if (type === 'bool') opts.push(['1', t('Ja')], ['0', t('Nein')]);
    return { li, name, label: label || name, type, opts };
  };
  const prefix = li => li.querySelector('[name$="[label]"]').name.replace(/\[label\]$/, '');

  // Wert-Feld passend zum gewählten Feld: Liste (Auswahl, Ja/Nein) oder Freitext
  const valueControl = (rule, src) => {
    const box = rule.querySelector('[data-rule-val]'), cur = box.querySelector('[name]'), opSel = rule.querySelector('[data-rule-op]');
    // Wiederholbare Gruppe: nur „ist ausgefüllt“ / „ist leer“
    const grp = src?.type === 'group';
    for (const o of opSel.options) o.disabled = grp && o.value !== 'filled' && o.value !== 'empty';
    if (opSel.selectedOptions[0]?.disabled) opSel.value = 'filled';
    const op = opSel.value;
    box.hidden = op === 'filled' || op === 'empty';
    const opts = src?.opts || [], sig = JSON.stringify(opts);
    if (opts.length ? cur.tagName === 'SELECT' && cur.dataset.sig === sig : cur.tagName === 'INPUT') return;
    let el;
    if (opts.length) {
      el = document.createElement('select');
      const v = cur.value === '' && src.type === 'bool' ? '0' : cur.value;
      opts.forEach(([k, l]) => el.add(new Option(l, k, false, k === v)));
      el.dataset.sig = sig;
    } else {
      el = document.createElement('input');
      el.value = cur.tagName === 'INPUT' ? cur.value : '';
      el.placeholder = src && ['date', 'datetime'].includes(src.type) ? t('JJJJ-MM-TT') : t('Wert');
    }
    el.name = cur.name;
    el.setAttribute('aria-label', t('Wert'));
    cur.replaceWith(el);
  };

  const refresh = () => {
    const all = rows().map(info);
    for (const { li, name } of all) {
      li.querySelectorAll('[data-rule-field]').forEach(sel => {
        const cmp = !!sel.closest('.dt-rule--cmp'), cur = sel.value;
        const opts = all.filter(f => f.name && f.name !== name && (cmp ? CMP : SRC).includes(f.type));
        sel.replaceChildren(new Option(t('– Feld wählen –'), ''), ...opts.map(f => new Option(f.label, f.name, false, f.name === cur)));
        if (cur && !opts.some(f => f.name === cur)) sel.add(new Option(t('{name} (fehlt)', { name: cur }), cur, false, true));
        if (!cmp) valueControl(sel.closest('[data-rule]'), all.find(f => f.name === sel.value));
      });
      const n = li.querySelectorAll('[data-rule]').length;
      const count = li.querySelector('[data-cond-count]');
      if (count) count.textContent = n ? `(${n})` : '';
      li.querySelectorAll('[data-cond-grp]').forEach(g => {
        const m = g.querySelector('[data-cond-mode]');
        if (m) m.hidden = g.querySelectorAll('[data-rule]').length < 2;
      });
    }
  };

  // Umbenannte Felder: Verweise in allen Bedingungen mitziehen
  const renamed = () => rows().forEach(li => {
    const nm = info(li).name, old = li.dataset.nm;
    if (old && nm && old !== nm) {
      list.querySelectorAll('[data-rule-field]').forEach(s => {
        if (s.value !== old) return;
        s.add(new Option(nm, nm)); s.value = nm;
      });
    }
    li.dataset.nm = nm;
  });

  schema.addEventListener('input', e => {
    if (e.target.matches('[data-label],[data-name],textarea[name$="[options]"]')) { renamed(); refresh(); }
  });
  schema.addEventListener('change', e => {
    if (e.target.matches('[data-type],[data-rule-field],[data-rule-op]')) refresh();
    if (e.target.matches('[data-form-toggle]')) {
      const o = e.target.closest('[data-form-settings]')?.querySelector('[data-form-opts]');
      if (o) o.hidden = !e.target.checked;
    }
  });
  schema.addEventListener('click', e => {
    const add = e.target.closest('[data-rule-add]');
    if (add) {
      const li = add.closest('[data-field]'), g = add.dataset.ruleAdd, cmp = g === 'compare';
      const p = `${prefix(li)}[${g}]${cmp ? '' : '[rules]'}[n${uid++}]`;
      const tpl = schema.querySelector(`[data-rule-template="${cmp ? 'cmp' : 'cond'}"]`).innerHTML.replaceAll('__P__', p);
      add.closest('[data-cond-grp]').querySelector('[data-rules]').insertAdjacentHTML('beforeend', tpl);
      refresh();
      add.closest('[data-cond-grp]').querySelector('[data-rule]:last-child [data-rule-field]')?.focus();
      return;
    }
    const rm = e.target.closest('[data-rule-remove]');
    if (rm) {
      const grp = rm.closest('[data-cond-grp]');
      rm.closest('[data-rule]').remove();
      refresh();
      grp.querySelector('[data-rule-add]').focus();
      return;
    }
    // Felder hinzugefügt, entfernt oder verschoben → Auswahllisten neu aufbauen (nach dem Baukasten-Code)
    if (e.target.closest('[data-add-field],[data-remove-field],[data-move]')) setTimeout(() => { renamed(); refresh(); });
  });
  renamed();
  refresh();
}
