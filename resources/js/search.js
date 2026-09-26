/*
 * Website-Suche: Vorschläge beim Tippen (Progressive Enhancement, ≤ 2 KB minifiziert).
 * Lädt das Theme-site.js erst, wenn ein Suchfeld [data-suggest-js] den Fokus bekommt.
 * Combobox nach ARIA APG: role=combobox, aria-expanded, aria-controls, aria-activedescendant; ↑/↓, Enter, Escape.
 * Keine Cookies, keine Speicherung; Anfragen nur an die eigene Domain (JSON, 180 ms entprellt).
 */
(() => {
  const d = document, me = d.currentScript, set = (el, o) => { for (const k in o) el.setAttribute(k, o[k]); };
  if (window.cmsSearch) return;
  window.cmsSearch = 1;
  // Stil der Vorschlagsliste (auf der Ergebnisseite schon geladen)
  if (me && !d.querySelector('link[href*="/search.css"]')) d.head.append(Object.assign(d.createElement('link'), { rel: 'stylesheet', href: me.src.replace(/js\/search\.js.*/, 'css/search.css') }));
  let n = 0;
  const esc = s => String(s).replace(/[&<>"]/g, c => '&#' + c.charCodeAt(0) + ';');

  const init = input => {
    if (input.dataset.ready) return;
    input.dataset.ready = 1;
    const L = JSON.parse(input.dataset.l10n || '{}'), id = 'ssug' + ++n, list = d.createElement('ul'), live = d.createElement('span');
    let timer, ctrl, active = -1, items = [];
    set(list, { id, class: 'ssug', role: 'listbox', hidden: '' });
    set(live, { class: 'sf-sr', 'aria-live': 'polite' });
    input.parentNode.append(list, live);
    set(input, { role: 'combobox', 'aria-autocomplete': 'list', 'aria-expanded': 'false', 'aria-controls': id });

    const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); select(-1); };
    const select = i => {
      active = i;
      items.forEach((li, k) => li.setAttribute('aria-selected', k === i));
      i < 0 ? input.removeAttribute('aria-activedescendant') : input.setAttribute('aria-activedescendant', items[i].id);
    };
    const opt = (href, html, k) => {
      const li = d.createElement('li');
      set(li, { id: id + k, role: 'option', 'aria-selected': 'false' });
      li.innerHTML = '<a tabindex="-1" href="' + esc(href) + '">' + html + '</a>';
      return li;
    };
    const load = () => {
      const q = input.value.trim();
      if (q.length < 2) return close();
      ctrl?.abort();
      ctrl = new AbortController();
      fetch(input.dataset.suggest + '?q=' + encodeURIComponent(q), { signal: ctrl.signal, headers: { Accept: 'application/json' } })
        .then(r => r.json()).then(j => {
          if (input.value.trim() !== q) return;
          items = j.items.map((it, k) => opt(it.url, esc(it.title) + '<small>' + esc([it.badge, it.date].filter(Boolean).join(' · ')) + '</small>', k));
          items.push(opt(j.all, esc(L.all || '') + ' „' + esc(q) + '“', 'a'));
          items.at(-1).className = 'ssug__all';
          list.replaceChildren(...items);
          list.hidden = false;
          input.setAttribute('aria-expanded', 'true');
          live.textContent = (L.count || '{n}').replace('{n}', j.items.length);
          select(-1);
        }).catch(() => {});
    };
    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 180); });
    input.addEventListener('keydown', e => {
      const k = e.key, open = !list.hidden;
      if (k === 'ArrowDown' || k === 'ArrowUp') {
        e.preventDefault();
        if (!open) return load();
        const i = active + (k === 'ArrowDown' ? 1 : -1);
        select(i >= items.length ? -1 : i < -1 ? items.length - 1 : i);
      } else if (k === 'Enter' && open && active >= 0) {
        e.preventDefault();
        location.href = items[active].firstChild.href;
      } else if (k === 'Escape' && open) {
        e.preventDefault(); e.stopPropagation();
        close();
      }
    });
    input.addEventListener('blur', () => setTimeout(close, 150));
    list.addEventListener('mousedown', e => e.preventDefault());
  };

  // Kopf-Suche in Browsern ohne CSS Anchor Positioning: Popover unter der Lupe ausrichten (12 px Abstand zum Fensterrand).
  // Dieses Skript lädt beim ersten Fokus – das Popover ist dann schon offen (autofocus) und wird sofort ausgerichtet.
  if (!CSS.supports('anchor-name:--a') && d.body.showPopover) d.querySelectorAll('.hsearch__panel').forEach(p => {
    const place = () => {
      const b = d.querySelector('[popovertarget="' + p.id + '"]').getBoundingClientRect(), w = p.offsetWidth;
      Object.assign(p.style, { inset: 'auto', top: b.bottom + 14 + 'px', left: Math.max(12, Math.min(b.right + 18 - w, innerWidth - w - 12)) + 'px' });
    };
    p.addEventListener('toggle', e => e.newState == 'open' && place());
    p.matches(':popover-open') && place();
  });

  d.querySelectorAll('[data-suggest]').forEach(init);
  d.addEventListener('focusin', e => e.target.matches?.('[data-suggest]') && init(e.target));
})();
