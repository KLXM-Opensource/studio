/*
 * Wiederholbare Gruppen in öffentlichen Formularen (Core\Data\DataForms::group) – auch vom Theme „praxis“ genutzt.
 * „+ …“ fügt eine leere Zeile an (Fokus dorthin), „Entfernen“ löscht eine; Namen/IDs werden neu nummeriert
 * (feld[0][unterfeld] …), eine Live-Region sagt die Änderung an. Zusätzliche Zeilen für „ohne JavaScript“ stehen in <noscript>.
 */
const F = 'input,select,textarea', A = ['name', 'id', 'for', 'aria-describedby', 'data-ef'];
export function groups(form) {
  form.querySelectorAll('[data-group]').forEach(g => {
    const list = g.querySelector('[data-rows]'), add = g.querySelector('[data-row-add]'), n = g.dataset.group, [sAdd, sRm] = g.dataset.say.split('|');
    const re = new RegExp(`(${g.id}-|${n}\\[|${n}\\.)\\d+`, 'g'), rows = () => list.querySelectorAll('[data-row]');
    const tpl = rows()[0].cloneNode(true);
    tpl.querySelectorAll(F).forEach(e => e.type == 'checkbox' ? e.checked = false : e.value = '');
    const fix = (t, i) => {
      const all = rows();
      all.forEach((r, k) => {
        r.querySelectorAll('*').forEach(e => A.forEach(a => e.hasAttribute(a) && e.setAttribute(a, e.getAttribute(a).replace(re, '$1' + k))));
        r.querySelectorAll('[data-row-n]').forEach(s => s.textContent = k + 1);
        r.querySelector('[data-row-rm]').hidden = all.length <= g.dataset.rowsMin;
      });
      add.hidden = all.length >= g.dataset.rowsMax;
      if (t) {
        g.querySelector('[data-live]').textContent = t.replace('{n}', i + 1);
        (all[i] || all[i - 1] || add).querySelector(F)?.focus();
        form.dispatchEvent(new Event('input'));
      }
    };
    fix();
    add.onclick = () => { list.append(tpl.cloneNode(true)); fix(sAdd, rows().length - 1); };
    list.addEventListener('click', e => {
      const r = e.target.closest('[data-row-rm]')?.closest('[data-row]');
      if (r) { const i = [...rows()].indexOf(r); r.remove(); fix(sRm, i); }
    });
  });
}
