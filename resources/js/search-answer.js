/*
 * KI-Antwort über den Suchergebnissen (Core\AI\VisitorChat::searchAnswerBox): fragt POST /api/chat (src=search, JSON)
 * automatisch oder nach Klick, zeigt die Antwort mit Belegen [n] als Links und die Quellen. Keine Cookies, keine Inline-Styles.
 */
(() => {
  const box = document.querySelector('[data-srch-ai]');
  if (!box || !window.fetch) return;
  const d = box.dataset, body = box.querySelector('[data-body]'), L = JSON.parse(d.l || '{}');
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const render = j => {
    const by = {};
    (j.sources || []).forEach(s => { by[s.n] = s; });
    const txt = esc(j.text).replace(/\*\*([^*]+)\*\*/g, '<b>$1</b>')
      .replace(/\[(\d{1,2})\]/g, (m, n) => by[n] ? `<a class="srch-ai__cite" href="${esc(by[n].url)}" title="${esc(by[n].title)}">${n}</a>` : '');
    let h = txt.split(/\n{2,}/).map(p => `<p>${p.replace(/\n/g, '<br>')}</p>`).join('');
    const c = j.contact;
    if (j.unknown && c) {
      const l = [c.url && `<a href="${esc(c.url)}">${esc(c.label)}</a>`, c.phone && `<a href="${esc(c.phone.href)}">${esc(c.phone.label)}</a>`, c.email && `<a href="mailto:${esc(c.email)}">${esc(c.email)}</a>`].filter(Boolean);
      if (l.length) h += `<p class="srch-ai__contact">${l.join(' · ')}</p>`;
    }
    if (j.sources?.length) h += `<p class="srch-ai__srch">${esc(L.src)}</p><ol class="srch-ai__src">` + j.sources.map(s => `<li value="${+s.n}"><a href="${esc(s.url)}">${esc(s.title)}</a></li>`).join('') + '</ol>';
    body.innerHTML = h;
  };
  const run = async () => {
    body.innerHTML = `<p class="srch-ai__wait">${esc(L.wait)}</p>`;
    box.setAttribute('aria-busy', 'true');
    try {
      const f = new FormData();
      f.append('q', d.q); f.append('lang', d.lang); f.append('src', 'search');
      const r = await fetch(d.api, { method: 'POST', body: f, headers: { Accept: 'application/json' }, credentials: 'omit' });
      const j = await r.json();
      if (!j.ok) throw new Error(j.error || L.err);
      render(j);
    } catch (e) {
      body.innerHTML = `<p class="srch-ai__err">${esc(e.message || L.err)}</p>`;
    }
    box.removeAttribute('aria-busy');
  };
  box.hidden = false;
  if ('auto' in d) run();
  else box.querySelector('[data-go]')?.addEventListener('click', run);
})();
