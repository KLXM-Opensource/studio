/*
 * Sortierbare Tabellen für Besucher (Datenliste „Tabelle“ → „Spalten sortierbar“, Merkmal '@tablesort', Core\Theme::conditionalJs).
 * Muster WAI-ARIA APG „Sortable Table“: der Inhalt jedes Spaltenkopfs kommt in einen <button>, aria-sort steht nur am gerade
 * sortierten <th>, eine Ansage (role=status) nennt Spalte und Richtung. Erster Klick aufsteigend, jeder weitere wechselt.
 * Sortiert wird je <tbody>, stabil; leere Zellen ans Ende. Wert: data-sort-value > <time datetime> > sichtbarer Text.
 * Typ je Spalte automatisch: Zahl („1.234,50 €“, „12 %“, Dezimaltrenner nach <html lang>), Datum (2026-11-20, 20.11.2026) oder
 * Text (Intl.Collator, „Raum 2“ vor „Raum 10“). Texte aus data-label-* der Tabelle. Ohne JavaScript bleibt die Tabelle, wie sie ist.
 * Aussehen: css/table-sort.css (Kern, ein Kit ersetzt es mit eigener Datei) – Klassen .tsort-btn, .tsort-ico, .tsort-status.
 */
(() => {
  const lang = document.documentElement.lang || 'de';
  const comma = !/^en/i.test(lang);   // Dezimaltrenner: de „,“ – en „.“
  const coll = new Intl.Collator(lang, { numeric: true, sensitivity: 'base' });
  const valueOf = td => {
    if (!td) return '';
    if (td.dataset.sortValue != null) return td.dataset.sortValue.trim();
    const t = td.querySelector('time[datetime]');
    return (t ? t.getAttribute('datetime') : td.textContent).trim();
  };
  const num = s => {
    let x = s.replace(/[\s €$£%]|CHF|EUR/gi, '');
    // Tausender nur in Dreiergruppen („1.234,5“ bzw. „1,234.5“) – sonst wäre „12.01.2026“ eine Zahl
    const re = comma ? /^[-+−]?(\d{1,3}(\.\d{3})+|\d+)(,\d+)?$/ : /^[-+−]?(\d{1,3}(,\d{3})+|\d+)(\.\d+)?$/;
    if (!re.test(x)) return null;
    return parseFloat((comma ? x.replace(/\./g, '').replace(',', '.') : x.replace(/,/g, '')).replace('−', '-'));
  };
  const date = s => {
    let m = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (m) { const t = Date.parse(s); return isNaN(t) ? Date.UTC(+m[1], m[2] - 1, +m[3]) : t; }
    m = s.match(/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})/);
    return m ? Date.UTC(+m[3] < 100 ? 2000 + +m[3] : +m[3], m[2] - 1, +m[1]) : null;
  };
  document.querySelectorAll('table[data-table-sort]').forEach(table => {
    const head = table.tHead?.rows[0];
    if (!head) return;
    const label = (k, d) => table.getAttribute('data-label-' + k) || d;
    const status = document.createElement('p');
    status.className = 'tsort-status';
    status.setAttribute('role', 'status');
    table.after(status);
    const ths = [...head.cells];
    const sort = (i, dir) => {
      const vals = [...table.tBodies].flatMap(tb => [...tb.rows].map(r => valueOf(r.cells[i])));
      const filled = vals.filter(v => v !== '');
      const type = filled.length && filled.every(v => date(v) !== null) ? 'date' : filled.length && filled.every(v => num(v) !== null) ? 'number' : 'text';
      const key = v => type === 'number' ? num(v) : type === 'date' ? date(v) : v;
      [...table.tBodies].forEach(tb => {
        const rows = [...tb.rows].map((r, n) => ({ r, n, v: valueOf(r.cells[i]) }));
        rows.sort((a, b) => {
          if (a.v === '' || b.v === '') return (a.v === '') - (b.v === '') || a.n - b.n;   // leere ans Ende
          const c = type === 'text' ? coll.compare(a.v, b.v) : key(a.v) - key(b.v);
          return (dir === 'descending' ? -c : c) || a.n - b.n;   // stabil
        });
        tb.append(...rows.map(x => x.r));
      });
      ths.forEach((th, k) => k === i ? th.setAttribute('aria-sort', dir) : th.removeAttribute('aria-sort'));
      status.textContent = label('sorted', 'Sortiert nach {column}, {direction}')
        .replace('{column}', ths[i].textContent.trim()).replace('{direction}', label(dir, dir === 'ascending' ? 'aufsteigend' : 'absteigend'));
    };
    ths.forEach((th, i) => {
      if (th.dataset.sort === 'false' || !th.textContent.trim()) return;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'tsort-btn';
      btn.append(...th.childNodes);
      const ico = document.createElement('span');
      ico.className = 'tsort-ico';
      ico.setAttribute('aria-hidden', 'true');
      btn.append(ico);
      th.append(btn);
      btn.addEventListener('click', () => sort(i, th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending'));
    });
    table.classList.add('is-sortable');
  });
})();
