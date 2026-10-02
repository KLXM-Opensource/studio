/*
 * Support & Wissensdatenbank (Core\Support)
 *  - „Problem melden“-Links nehmen die aktuelle Seite als Kontext mit (?from=…)
 *  - Beim Schreiben: ähnliche Wissensartikel und beantwortete Fragen vorschlagen (Duplikate vermeiden)
 *  - Bildschirmfotos: Vorschau, Entfernen, Einfügen aus der Zwischenablage (Strg/⌘+V), Ziehen & Ablegen
 *  - Fenstergröße als sichtbarer Kontext; Stimmen ohne Neuladen
 * Ohne JavaScript funktioniert alles über normale Formulare.
 */
import { t } from './_i18n.js';

const d = document;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const MAX_FILES = 5, MAX_BYTES = 8 * 1024 * 1024, TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];

export function initSupport(csrf) {
  // ---------------------------------------------------------------- Kontext: aktuelle Seite
  const here = location.pathname + location.search;
  if (!location.pathname.includes('/support/neu')) {
    $$('[data-support-report]').forEach(a => {
      try {
        const u = new URL(a.href, location.href);
        if (!u.searchParams.has('from')) u.searchParams.set('from', here);
        a.href = u.pathname + u.search;
      } catch { /* Adresse bleibt */ }
    });
  }

  // ---------------------------------------------------------------- Fenstergröße (wird vor dem Senden angezeigt)
  const vp = $('[data-support-viewport]');
  if (vp) {
    const val = `${innerWidth}x${innerHeight}@${Math.round((devicePixelRatio || 1) * 100) / 100}`;
    vp.value = val;
    $$('[data-support-viewport-out]').forEach(dd => { dd.textContent = val; dd.hidden = false; });
    $$('[data-support-viewport-row]').forEach(dt => { dt.hidden = false; });
  }

  // ---------------------------------------------------------------- Vorschläge
  const box = $('[data-support-suggest]');
  if (box) {
    const list = $('[data-support-suggest-list]', box), hint = $('[data-support-suggest-hint]', box);
    const fields = $$('[data-support-similar]');
    let timer, ctrl, last = '';
    const run = async () => {
      const q = fields.map(f => f.value).join(' ').trim().slice(0, 400);
      if (q === last) return;
      last = q;
      if (q.length < 4) { list.innerHTML = ''; return; }
      ctrl?.abort(); ctrl = new AbortController();
      try {
        const r = await fetch(box.dataset.endpoint + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: ctrl.signal });
        const data = await r.json();
        list.innerHTML = (data.items || []).map(it => `<li class="sp-sugg sp-sugg--${esc(it.type)}"><span class="sp-sugg__label">${esc(it.label)}</span>
          <a href="${esc(it.url)}" target="_blank" rel="noopener">${esc(it.title)}<span class="sr-only"> (${esc(t('öffnet in neuem Tab'))})</span></a><small>${esc(it.excerpt)}</small></li>`).join('');
        if (hint) hint.textContent = data.items?.length ? t('Passt einer dieser Einträge? Dann ist die Lösung vielleicht schon da.') : t('Keine passenden Einträge gefunden – senden Sie Ihre Meldung gern ab.');
        box.classList.toggle('has-items', !!data.items?.length);
      } catch (e) { if (e.name !== 'AbortError') list.innerHTML = ''; }
    };
    fields.forEach(f => f.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 400); }));
    if (fields.some(f => f.value.trim())) run();
  }

  // ---------------------------------------------------------------- Bildschirmfotos
  $$('[data-support-shots]').forEach(zone => {
    const input = $('input[type=file]', zone), thumbs = $('[data-support-thumbs]', zone), form = zone.closest('form');
    if (!input || !thumbs || typeof DataTransfer === 'undefined') return;
    let files = [];
    const sync = () => {
      const dt = new DataTransfer();
      files.forEach(f => dt.items.add(f));
      input.files = dt.files;
      thumbs.innerHTML = '';
      files.forEach((f, i) => {
        const li = d.createElement('li');
        const img = d.createElement('img');
        img.alt = '';
        img.src = URL.createObjectURL(f);
        img.addEventListener('load', () => URL.revokeObjectURL(img.src), { once: true });
        const name = d.createElement('span');
        name.textContent = f.name;
        const rm = d.createElement('button');
        rm.type = 'button';
        rm.className = 'adm-link';
        rm.textContent = t('Entfernen');
        rm.setAttribute('aria-label', t('„{name}“ entfernen', { name: f.name }));
        rm.addEventListener('click', () => { files.splice(i, 1); sync(); input.focus(); });
        li.append(img, name, rm);
        thumbs.append(li);
      });
    };
    const add = list => {
      const errors = [];
      for (const f of list) {
        if (!TYPES.includes(f.type)) { errors.push(t('„{name}“ ist kein Bild (erlaubt: JPEG, PNG, WebP, GIF).', { name: f.name })); continue; }
        if (f.size > MAX_BYTES) { errors.push(t('„{name}“ ist größer als {mb} MB.', { name: f.name, mb: 8 })); continue; }
        if (files.length >= MAX_FILES) { errors.push(t('Höchstens {n} Bilder je Nachricht – „{name}“ wurde nicht übernommen.', { n: MAX_FILES, name: f.name })); continue; }
        files.push(f.name && f.name !== 'image.png' ? f : new File([f], `bildschirmfoto-${new Date().toISOString().slice(0, 19).replace(/[T:]/g, '-')}.${(f.type.split('/')[1] || 'png').replace('jpeg', 'jpg')}`, { type: f.type }));
      }
      sync();
      const live = $('#adm-live');
      if (live) live.textContent = errors.length ? errors.join(' ') : t('{n} Bilder angehängt', { n: files.length });
      if (errors.length) alert(errors.join('\n'));
    };
    input.addEventListener('change', () => { const picked = [...input.files]; files = []; add(picked); });
    // Einfügen aus der Zwischenablage ins Textfeld des Formulars
    $$('[data-support-paste]', form).forEach(ta => ta.addEventListener('paste', e => {
      const imgs = [...(e.clipboardData?.files || [])].filter(f => f.type.startsWith('image/'));
      if (imgs.length) { e.preventDefault(); add(imgs); }
    }));
    // Ziehen & Ablegen auf den Bereich
    zone.addEventListener('dragover', e => { if ([...e.dataTransfer.types].includes('Files')) { e.preventDefault(); zone.classList.add('is-over'); } });
    zone.addEventListener('dragleave', () => zone.classList.remove('is-over'));
    zone.addEventListener('drop', e => { e.preventDefault(); zone.classList.remove('is-over'); add([...e.dataTransfer.files]); });
  });

  // ---------------------------------------------------------------- Doppeltes Absenden verhindern
  $$('[data-support-form]').forEach(f => f.addEventListener('submit', () => {
    const b = $('button[type=submit], button:not([type])', f);
    if (b) setTimeout(() => { b.disabled = true; b.setAttribute('aria-busy', 'true'); }, 0);
  }));

  // ---------------------------------------------------------------- Stimmen
  $$('[data-support-vote]').forEach(f => f.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = $('button', f), n = $('[data-support-score]', f.closest('.sp-vote'));
    try {
      const r = await fetch(f.action, { method: 'POST', body: new FormData(f), headers: { Accept: 'application/json', 'X-CSRF-Token': csrf() }, credentials: 'same-origin' });
      const data = await r.json();
      if (!data.ok) throw new Error();
      btn.classList.toggle('is-on', data.voted);
      btn.setAttribute('aria-pressed', String(data.voted));
      if (n) n.lastChild.textContent = String(data.score);
      const live = $('#adm-live');
      if (live) live.textContent = data.voted ? t('Danke – Ihre Stimme zählt.') : t('Stimme zurückgenommen.');
    } catch { f.submit(); }
  }));
}
