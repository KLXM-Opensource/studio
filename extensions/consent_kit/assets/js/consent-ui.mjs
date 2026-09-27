/*!
 * KLXM Studio – Consent-Kit (Oberfläche): <consent-kit> und <consent-embed> · MIT
 * Portiert aus FriendsOfREDAXO/consent_kit assets/consent-kit.js (MIT, © KLXM Crossmedia GmbH)
 *
 * Natives <dialog> (Fokusfalle, Escape, inert vom Browser), Shadow DOM, Gestaltung über --ck-* (Stylesheet von der
 * eigenen Domain, keine Inline-Styles – CSP style-src 'self'). Alle Schaltflächen teilen Klasse und ::part(button):
 * „Alle ablehnen“ ist so auffällig wie „Alle akzeptieren“. Nachgeladen vom Kern (consent.js) nur bei Bedarf.
 */
const d = document;
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const safeUrl = u => (/^https?:\/\//i.test(u) || /^[/?#]/.test(u) ? u : '#');
const fill = (t, v) => String(t ?? '').replace(/\{(\w+)\}/g, (m, k) => (k in v ? v[k] : m));
/** Wie fill(), aber als HTML: Text maskiert, Platzhalter aus links ({label, url, external}) werden zu <a> – ohne url nur die Beschriftung */
const fillHtml = (t, v, links) => String(t ?? '').split(/(\{\w+\})/).map(part => {
  const l = /^\{\w+\}$/.test(part) ? links[part.slice(1, -1)] : null;
  if (!l) return esc(fill(part, v));
  if (!l.url) return esc(l.label);
  return `<a href="${esc(safeUrl(l.url))}"${l.external ? ' target="_blank" rel="noopener noreferrer"' : ''}>${esc(l.label)}</a>`;
}).join('');
const cookieIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 12.3A9 9 0 1 1 11.7 3a4 4 0 0 0 4.6 4.7A4 4 0 0 0 21 12.3Z"/><path d="M8.5 9.5h.01M8 14.5h.01M12.5 12.5h.01M13 17h.01M16.5 14h.01"/></svg>';
const closeIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>';

export function mount(api) {
  const { cfg, services, optional, needsDecision, dismissed, gpc, dismiss, apply, emit, script, codeUrl, store, loaded, atLoad, sleep } = api._;
  const t = cfg.texts;

  // ---------------------------------------------------------------- Entscheidung (nur hier – der Kern bleibt klein)
  const uuid = () => {
    if (crypto.randomUUID) return crypto.randomUUID();
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 15) | 64; b[8] = (b[8] & 63) | 128;
    const h = [...b].map(x => x.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
  };
  const post = body => fetch(cfg.endpoint, {
    method: 'POST', credentials: 'same-origin', keepalive: true,
    headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
  }).catch(() => null);
  /** Cookie „cms_consent“ – entsteht erst hier, durch eine Entscheidung (der Server bestätigt ihn per Set-Cookie) */
  const setCookie = v => {
    d.cookie = cfg.cookie + '=' + v + '; Max-Age=' + (v ? cfg.days * 86400 : 0) + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  };
  /** Beim Widerruf: dokumentierte Cookies/Storage-Einträge löschen (eigene Domain und Überdomains) */
  const clearItems = s => {
    const host = location.hostname, parts = host.split('.');
    const domains = ['', host, '.' + host];
    for (let i = 1; i < parts.length - 1; i++) domains.push('.' + parts.slice(i).join('.'));
    const names = d.cookie.split('; ').map(c => c.split('=')[0]).filter(Boolean);
    for (const it of s.items || []) {
      const re = new RegExp('^' + it.name.split('*').map(p => p.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('.*') + '$');
      if (it.type === 'cookie') {
        for (const n of names.filter(n => re.test(decodeURIComponent(n)))) for (const dm of domains) d.cookie = n + '=; Max-Age=0; Path=/' + (dm ? '; Domain=' + dm : '');
      } else if (it.type === 'local_storage' || it.type === 'session_storage') {
        store(() => { const st = it.type === 'local_storage' ? localStorage : sessionStorage; Object.keys(st).filter(k => re.test(k)).forEach(k => st.removeItem(k)); });
      }
    }
  };
  const revoke = s => { if (s.rev && loaded.has(s.key)) script(codeUrl(s.key, 'r')); clearItems(s); };

  function decide(keys, action) {
    const before = new Set(api.accepted());
    const a = {}, r = {};
    for (const s of optional) (keys.includes(s.key) ? a : r)[s.key] = s.h;
    const old = api._.state();
    api._.setState({ id: old?.id || uuid(), e: cfg.epoch, rev: cfg.rev, ts: Math.floor(Date.now() / 1000), a, r });
    if (cfg.preview) { emit('change', action); return; }
    setCookie(encodeURIComponent(JSON.stringify(api._.state())));
    store(() => sessionStorage.removeItem(cfg.dismissKey));
    const req = post({ id: api._.state().id, action, accepted: Object.keys(a), gpc, lang: cfg.lang });
    let reload = false;
    for (const k of before) if (!a[k]) { const s = services.get(k); revoke(s); reload = reload || (cfg.reload && loaded.has(k)); }
    // Neu erlaubte Dienste mit fremden Hosts: Die CSP dieser Antwort kennt sie noch nicht → einmal neu laden
    if (Object.keys(a).some(k => !atLoad.has(k) && services.get(k).x)) reload = true;
    if (!reload) apply();
    emit('change', action);
    if (reload) Promise.race([req, sleep(1500)]).then(() => location.reload());
  }

  function withdraw() {
    const id = api._.state()?.id;
    for (const k of api.accepted()) revoke(services.get(k));
    api._.setState(null);
    setCookie('');
    store(() => sessionStorage.removeItem(cfg.dismissKey));
    emit('change', 'withdraw');
    if (cfg.preview) return;
    const req = id ? post({ id, action: 'withdraw', accepted: [], gpc, lang: cfg.lang }) : Promise.resolve();
    Promise.race([req, sleep(1500)]).then(() => location.reload());
  }

  function reset() {
    setCookie('');
    store(() => sessionStorage.removeItem(cfg.dismissKey));
    location.reload();
  }
  const theme = el => el.getAttribute('theme') || (cfg.theme === 'site' ? (cfg.darkScope && d.documentElement.matches(cfg.darkScope) ? 'auto' : 'light') : cfg.theme);

  /** Shadow DOM mit dem Stylesheet der Website; Inhalt erst sichtbar, wenn es geladen ist (kein Aufblitzen) */
  const shadow = (host, html) => {
    const root = host.attachShadow({ mode: 'open' });
    root.innerHTML = `<link rel="stylesheet" href="${esc(cfg.css)}">${html}`;
    const link = root.querySelector('link');
    host.ready = new Promise(r => { link.onload = link.onerror = () => r(); });
    host.setAttribute('theme', theme(host));
    if (cfg.lang) host.setAttribute('lang', cfg.lang);
    return root;
  };

  class Kit extends HTMLElement {
    connectedCallback() {
      if (this.shadowRoot) return;
      this.view = null;
      this.expanded = new Set();
      this.selection = new Set();
      const root = shadow(this, `<dialog part="dialog" aria-labelledby="ck-title"></dialog><button type="button" class="trigger ${esc(cfg.position.includes('right') ? 'bottom-right' : 'bottom-left')}" part="trigger button" hidden aria-label="${esc(t.trigger)}" title="${esc(t.trigger)}">${cookieIcon}</button>`);
      this.dialog = root.querySelector('dialog');
      this.trigger = root.querySelector('.trigger');
      this.trigger.addEventListener('click', () => this.open('settings'));
      this.dialog.addEventListener('click', e => this.onClick(e));
      this.dialog.addEventListener('change', e => this.onChange(e));
      this.dialog.addEventListener('cancel', e => { e.preventDefault(); if (this.view === 'settings' || cfg.dismiss) this.dismiss(); });
      // Nicht-modale Hinweise bekommen kein cancel-Ereignis
      this.dialog.addEventListener('keydown', e => { if (e.key === 'Escape' && !this.dialog.matches(':modal') && cfg.dismiss) this.dismiss(); });
      this.ready.then(() => this.updateTrigger());
    }

    get layout() {
      const l = this.getAttribute('layout') || cfg.layout;
      // Datenschutz und Impressum müssen ohne Entscheidung lesbar sein: erzwungener Dialog weicht dort auf die Box aus
      return cfg.quiet && l === 'modal' && !cfg.dismiss ? 'box' : l;
    }
    get position() { return this.getAttribute('position') || cfg.position; }
    get withGroups() { return !!cfg.bannerGroups && optional.length > 0 && ['modal', 'offcanvas'].includes(this.layout); }

    async open(view = 'settings') {
      await this.ready;
      if (view === 'settings' && this.view !== 'settings' && d.activeElement !== d.body) this.returnFocus = d.activeElement;
      if (this.view !== view) this.selection = new Set(api.accepted());
      this.view = view;
      const modal = !this.hasAttribute('preview') && (view === 'settings' || this.layout === 'modal');
      this.hide();
      const off = this.layout === 'offcanvas';
      this.dialog.className = (view === 'settings' ? 'settings' + (off ? ' offcanvas' : '') : this.layout + ' banner') + ' ' + this.position;
      this.dialog.innerHTML = view === 'settings' ? this.settingsHtml() : this.bannerHtml();
      this.trigger.hidden = true;
      if (modal) {
        this.dialog.showModal();
        d.documentElement.style.setProperty('overflow', 'hidden');
        this.dialog.querySelector('h2').focus();
      } else {
        // Nicht-modal ohne Fokus-Sprung; als Popover in der obersten Ebene (über schwebenden Elementen der Seite wie dem Chat)
        if (this.dialog.showPopover && !this.hasAttribute('preview')) {
          // Popover leer öffnen, dann füllen: Chromium fokussiert sonst das erste Element (Hinweis soll keinen Fokus ziehen)
          const html = this.dialog.innerHTML;
          this.dialog.innerHTML = '';
          this.dialog.setAttribute('popover', 'manual');
          this.dialog.showPopover();
          if (this.shadowRoot.activeElement === this.dialog) this.dialog.blur();   // Startpunkt für Tab bleibt der Hinweis
          this.dialog.innerHTML = html;
        } else this.dialog.setAttribute('open', '');
        d.documentElement.style.removeProperty('overflow');
        if (view === 'settings') this.dialog.querySelector('h2').focus();
      }
      if (view === 'settings' || this.withGroups) this.sync();
    }

    hide() {
      if (this.dialog.open) this.dialog.close();
      this.dialog.removeAttribute('open');
      try { if (this.dialog.matches(':popover-open')) this.dialog.hidePopover(); } catch { /* ohne Popover-API */ }
      this.dialog.removeAttribute('popover');
    }

    close() {
      this.hide();
      this.dialog.innerHTML = '';
      this.view = null;
      d.documentElement.style.removeProperty('overflow');
      this.updateTrigger();
      const back = this.returnFocus;
      this.returnFocus = null;
      if (back && back.isConnected && back !== d.body) back.focus();
      else if (!this.trigger.hidden) this.trigger.focus({ preventScroll: true });
    }

    /** Schließen ohne Entscheidung: nichts wird geladen, der Hinweis bleibt für diese Browser-Sitzung aus */
    dismiss() {
      if (this.view === 'settings' && needsDecision() && !dismissed() && !api._.autoRejected()) { this.open('banner'); return; }
      dismiss();
      this.close();
    }

    updateTrigger() { this.trigger.hidden = !(cfg.trigger && this.view === null); }

    buttons(middle) {
      return `<div class="buttons">
        <button type="button" class="btn" part="button" data-action="reject">${esc(t.reject_all)}</button>
        <button type="button" class="btn" part="button" data-action="${middle}">${esc(middle === 'save' ? t.save : t.settings)}</button>
        <button type="button" class="btn" part="button" data-action="accept">${esc(t.accept_all)}</button>
      </div>`;
    }
    closeHtml() { return `<button type="button" class="close" part="close" data-action="close" aria-label="${esc(t.close)}" title="${esc(t.close)}">${closeIcon}</button>`; }
    linksHtml() {
      return cfg.links.length ? '<ul class="links">' + cfg.links.map(l => `<li><a href="${esc(safeUrl(l.url))}">${esc(l.label)}</a></li>`).join('') + '</ul>' : '';
    }
    gpcHtml() { return gpc && cfg.gpc !== 'ignore' ? `<p class="notice">${esc(t.gpc_notice)}</p>` : ''; }

    bannerHtml() {
      return `<div class="inner">
        <div class="text"><div class="head"><h2 id="ck-title" tabindex="-1">${esc(t.title)}</h2>${cfg.dismiss ? this.closeHtml() : ''}</div>
        <div class="body"><p>${esc(t.intro)}</p>${this.gpcHtml()}${this.withGroups ? this.groupsHtml(false) : ''}${this.linksHtml()}</div></div>
        <div class="foot">${this.buttons(this.withGroups ? 'save' : 'settings')}</div>
      </div>`;
    }

    groupsHtml(details) {
      return cfg.groups.map((g, gi) => {
        const id = 'ck-g' + gi;
        const open = details && this.expanded.has(id);
        const n = g.services.length;
        const count = n === 1 ? t.services_count_one : fill(t.services_count, { n });
        const toggle = g.required ? `<span class="always">${esc(t.always_active)}</span>`
          : `<label class="switch"><input type="checkbox" data-group="${esc(g.key)}" aria-label="${esc(fill(t.group_toggle, { name: g.name }))}"><span class="track"></span></label>`;
        const name = details
          ? `<h3 class="gname"><button type="button" class="expand" aria-expanded="${open}" aria-controls="${id}" data-expand="${id}"><span class="chev"></span><span>${esc(g.name)}</span><span class="count">${esc(count)}</span></button></h3>`
          : `<h3 class="gname"><span>${esc(g.name)}</span> <span class="count">${esc(count)}</span></h3>`;
        const list = details ? `<div class="services" id="${id}"${open ? '' : ' hidden'}>${g.services.map((s, i) => this.serviceHtml(g, s, id + 's' + i)).join('')}</div>` : '';
        return `<section class="group${details ? '' : ' plain'}" part="group"><div class="group-head">${name}${toggle}</div><p class="muted">${esc(g.description)}</p>${list}</section>`;
      }).join('');
    }

    serviceHtml(g, s, id) {
      const open = this.expanded.has(id);
      const toggle = g.required ? `<span class="always">${esc(t.always_active)}</span>`
        : `<label class="switch"><input type="checkbox" data-service="${esc(s.key)}" data-in-group="${esc(g.key)}" aria-labelledby="${id}-name"><span class="track"></span></label>`;
      const rows = (s.items || []).map(i => `<tr><td>${esc(i.name)}</td><td data-label="${esc(t.col_type)}">${esc(i.typeLabel)}</td><td data-label="${esc(t.col_host)}">${esc(i.host || location.hostname)}</td><td data-label="${esc(t.col_duration)}">${esc(i.duration)}</td><td data-label="${esc(t.col_purpose)}">${esc(i.purpose)}</td></tr>`).join('');
      const table = rows
        ? `<div class="table" tabindex="0" role="region" aria-label="${esc(t.storage)}: ${esc(s.name)}"><table><caption>${esc(t.storage)}</caption><thead><tr><th scope="col">${esc(t.col_name)}</th><th scope="col">${esc(t.col_type)}</th><th scope="col">${esc(t.col_host)}</th><th scope="col">${esc(t.col_duration)}</th><th scope="col">${esc(t.col_purpose)}</th></tr></thead><tbody>${rows}</tbody></table></div>`
        : `<p class="muted">${esc(t.no_items)}</p>`;
      const prov = s.provider ? `<dt>${esc(t.provider)}</dt><dd>${esc(s.provider)}</dd>` : '';
      const priv = s.privacyUrl ? `<dt>${esc(t.privacy_policy)}</dt><dd><a href="${esc(safeUrl(s.privacyUrl))}" target="_blank" rel="noopener noreferrer">${esc(fill(t.privacy_policy_of, { name: s.name }))}<span class="sr"> ${esc(t.new_tab)}</span></a></dd>` : '';
      return `<div class="service" part="service">
        <div class="service-head"><span class="name" id="${id}-name">${esc(s.name)}</span>${toggle}</div>
        ${s.description ? `<p>${esc(s.description)}</p>` : ''}
        <button type="button" class="more" aria-expanded="${open}" aria-controls="${id}" data-expand="${id}" data-open="${esc(t.hide_details)}" data-closed="${esc(t.show_details)}" aria-describedby="${id}-name">${esc(open ? t.hide_details : t.show_details)}</button>
        <div class="details" id="${id}"${open ? '' : ' hidden'}>${prov || priv ? `<dl>${prov}${priv}</dl>` : ''}${table}</div>
      </div>`;
    }

    settingsHtml() {
      const st = api._.state();
      const meta = st ? `<p class="meta">${esc(fill(t.consent_info, { id: st.id, date: new Date(st.ts * 1000).toLocaleString(cfg.lang) }))}</p>
        <p class="meta"><button type="button" class="more" part="withdraw" data-action="withdraw">${esc(t.withdraw)}</button></p>` : '';
      return `<div class="inner">
        <div class="head"><h2 id="ck-title" tabindex="-1">${esc(t.settings_title)}</h2>${this.closeHtml()}</div>
        <div class="body"><p>${esc(t.settings_intro)}</p>${this.gpcHtml()}${this.groupsHtml(true)}${this.linksHtml()}${meta}</div>
        <div class="foot">${this.buttons('save')}</div>
      </div>`;
    }

    sync() {
      for (const i of this.dialog.querySelectorAll('input[data-service]')) i.checked = this.selection.has(i.dataset.service);
      for (const i of this.dialog.querySelectorAll('input[data-group]')) {
        const list = cfg.groups.find(g => g.key === i.dataset.group)?.services || [];
        const on = list.filter(s => this.selection.has(s.key)).length;
        i.checked = on > 0 && on === list.length;
        i.indeterminate = on > 0 && on < list.length;
      }
    }

    onChange(e) {
      const i = e.target;
      if (i.dataset.group) for (const s of cfg.groups.find(g => g.key === i.dataset.group).services) i.checked ? this.selection.add(s.key) : this.selection.delete(s.key);
      else if (i.dataset.service) i.checked ? this.selection.add(i.dataset.service) : this.selection.delete(i.dataset.service);
      this.sync();
    }

    onClick(e) {
      const b = e.target.closest('button');
      if (!b) return;
      if (b.dataset.expand) {
        const open = b.getAttribute('aria-expanded') !== 'true';
        b.setAttribute('aria-expanded', String(open));
        this.dialog.querySelector('#' + b.dataset.expand).hidden = !open;
        open ? this.expanded.add(b.dataset.expand) : this.expanded.delete(b.dataset.expand);
        if (b.dataset.open) b.textContent = open ? b.dataset.open : b.dataset.closed;
        return;
      }
      switch (b.dataset.action) {
        case 'settings': this.open('settings'); break;
        case 'close': this.dismiss(); break;
        case 'withdraw': this.close(); withdraw(); break;
        case 'accept': this.finish(optional.map(s => s.key), 'accept_all'); break;
        case 'reject': this.finish([], 'reject_all'); break;
        case 'save': this.finish([...this.selection], 'custom'); break;
      }
    }

    finish(keys, action) {
      decide(keys, action);
      this.close();
      if (this.hasAttribute('preview')) this.open('banner');
    }
  }

  class Embed extends HTMLElement {
    connectedCallback() {
      if (this.shadowRoot) return;
      const key = this.getAttribute('service') || '';
      const s = services.get(key);
      const name = s?.name || this.getAttribute('name') || key;
      const label = this.getAttribute('label');
      const ratio = (this.getAttribute('ratio') || '').match(/^(\d{1,2})\s*\/\s*(\d{1,2})$/);
      if (ratio) this.style.setProperty('--ck-embed-ratio', ratio[1] + ' / ' + ratio[2]);   // CSSOM – kein style-Attribut
      const heading = `<h3 id="ck-e">${esc(fill(t.embed_title, { name }))}${label ? ': ' + esc(label) : ''}</h3>`;
      let body;
      if (s) {
        const links = { privacy: { label: t.privacy_policy }, imprint: { label: t.imprint } };
        for (const l of cfg.links) if (l.key) links[l.key] = l;
        links.service_privacy = { label: fill(t.privacy_policy_of, { name }), url: s.privacyUrl, external: true };
        const once = !s.x;   // Dienste mit fremden Skript-Hosts brauchen die Einwilligung (CSP) – kein „einmal laden“
        body = `<p>${fillHtml(t.embed_text, { name }, links)}</p>
          <div class="buttons${once ? '' : ' two'}">
            ${once ? `<button type="button" class="btn" part="button" data-action="once">${esc(t.embed_once)}</button>` : ''}
            ${!s.required ? `<button type="button" class="btn" part="button" data-action="always">${esc(fill(t.embed_always, { name }))}</button>` : ''}
            <button type="button" class="btn" part="button" data-action="settings">${esc(t.embed_settings)}</button>
          </div>`;
      } else {
        // Nicht angelegt oder inaktiv: ohne Dienst fehlen die Angaben im Hinweis und in der Datenschutzerklärung – nichts ladbar
        console.warn(`[consent-kit] <consent-embed service="${key}">: Dienst fehlt oder ist auf dieser Domain inaktiv`);
        body = `<p>${esc(t.embed_unavailable)}</p>${cfg.editorHint ? `<p class="notice">${esc(cfg.editorHint.replace('{0}', key))}</p>` : ''}`;
      }
      const root = shadow(this, `<div class="placeholder" part="placeholder" role="group" aria-labelledby="ck-e">${heading}${body}</div><slot></slot>`);
      root.addEventListener('click', e => {
        const a = e.target.closest('button')?.dataset.action;
        if (a === 'once') this.load(true);
        if (a === 'always') decide([...new Set([...api.accepted(), key])], 'embed');
        if (a === 'settings') api.open('settings');
      });
      this.listener = () => { if (api.has(key)) this.load(false); };
      d.addEventListener('consentkit:change', this.listener);
      this.listener();
    }
    disconnectedCallback() { d.removeEventListener('consentkit:change', this.listener); }
    load(focus) {
      if (this.hasAttribute('loaded') || !services.get(this.getAttribute('service') || '')) return;
      const tpl = this.querySelector(':scope > template');
      if (!tpl) return;
      this.setAttribute('loaded', '');
      const c = tpl.content.cloneNode(true);
      // Skripte im Inhalt nur mit src (Inline-Code verbietet die CSP)
      for (const x of c.querySelectorAll('script')) {
        const n = d.createElement('script');
        for (const a of x.attributes) n.setAttribute(a.name, a.value);
        x.replaceWith(n);
      }
      const first = c.firstElementChild;
      this.append(c);
      if (focus && first) {
        if (first.tabIndex < 0 && !first.hasAttribute('tabindex')) first.setAttribute('tabindex', '-1');
        first.focus({ preventScroll: true });
      }
    }
  }

  if (!customElements.get('consent-kit')) customElements.define('consent-kit', Kit);
  if (!customElements.get('consent-embed')) customElements.define('consent-embed', Embed);
  let kit = d.querySelector('consent-kit');
  if (!kit) { kit = d.createElement('consent-kit'); d.body.prepend(kit); }
  return { open: v => kit.open(v), close: () => kit.close(), kit, decide, withdraw, reset };
}
