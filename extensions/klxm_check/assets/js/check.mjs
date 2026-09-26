/**
 * KLXM Check – Verhalten des Werkzeugs (Erweiterung klxm_check). ES-Modul ohne Abhängigkeiten, lädt nur auf Seiten mit
 * dem Werkzeug. Kein innerHTML mit Serverdaten: alle Texte gehen als textContent in den DOM (Ergebnisse sind reine Daten).
 *
 * Reiter nach WAI-ARIA APG „Tabs“ (Pfeiltasten, Pos1/Ende, automatische Aktivierung), Ergebnisse in Live-Regionen,
 * ?domain= und ?tab=checker|ssl-checker|spf-generator|dmarc-generator (Schema des alten Werkzeugs), ⌘/Strg+K.
 */
const SECTIONS = ['spf', 'dmarc', 'dkim', 'mail', 'hosting', 'web'];
const RANK = { ok: 0, info: 1, warn: 2, error: 3 };
const ICONS = {
  ok: '<path d="m5 12.5 4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>',
  info: '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 11v6M12 7.5v.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>',
  warn: '<path d="M12 3.5 2.5 20h19z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 10v4.5M12 17.2v.3" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>',
  error: '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="m8.5 8.5 7 7m0-7-7 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>',
};

/** SVG-Symbol (feste Pfade, keine Daten) */
function icon(level) {
  const ns = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(ns, 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('class', 'kc-i');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  // Pfade stammen aus der Konstante ICONS oben (kein Inhalt von außen)
  svg.innerHTML = ICONS[level] || ICONS.info;
  return svg;
}

/** Element bauen: el('p', {class: 'x'}, 'Text', kind) */
function el(tag, attrs = {}, ...kids) {
  const n = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (v === null || v === undefined || v === false) continue;
    if (k === 'class') n.className = v;
    else if (k === 'text') n.textContent = v;
    else n.setAttribute(k, v === true ? '' : String(v));
  }
  for (const k of kids.flat()) if (k !== null && k !== undefined && k !== false) n.append(k instanceof Node ? k : document.createTextNode(String(k)));
  return n;
}

function fmt(s, params = {}) {
  return String(s ?? '').replace(/\{(\w+)\}/g, (m, k) => (k in params ? params[k] : m));
}

async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    // Rückfall ohne Clipboard-API (http, ältere Browser)
    const ta = el('textarea', { class: 'kc-sr', 'aria-hidden': 'true' });
    ta.value = text;
    document.body.append(ta);
    ta.select();
    let ok = false;
    try { ok = document.execCommand('copy'); } catch { ok = false; }
    ta.remove();
    return ok;
  }
}

/** Einfache Prüfungen im Browser (Server prüft ohnehin streng) */
const RE_DOMAIN = /^(?=.{1,253}$)([a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/i;
const RE_MAIL = /^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/;
function validIp4(v) {
  const m = v.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})(?:\/(\d{1,2}))?$/);
  return !!m && m.slice(1, 5).every((x) => +x <= 255) && (m[5] === undefined || +m[5] <= 32);
}
function validIp6(v) {
  const [a, c] = v.split('/');
  if (c !== undefined && !(/^\d{1,3}$/.test(c) && +c <= 128)) return false;
  if (!/^[0-9a-f:.]+$/i.test(a) || (a.match(/::/g) || []).length > 1 || !a.includes(':')) return false;
  const parts = a.split(':');
  return parts.length <= 8 && parts.every((p) => p === '' || /^[0-9a-f]{1,4}$/i.test(p) || validIp4(p));
}
function cleanDomain(v) {
  let s = String(v || '').trim().toLowerCase();
  s = s.replace(/^[a-z][a-z0-9+.-]*:\/\//, '').replace(/[/?#].*$/, '');
  if (s.includes('@')) s = s.slice(s.lastIndexOf('@') + 1);
  return s.replace(/\.$/, '');
}
function lines(v) {
  return String(v || '').split(/[\n,;\s]+/).map((x) => x.trim()).filter(Boolean);
}

class Tool {
  constructor(root) {
    this.root = root;
    this.api = root.dataset.api;
    this.lang = root.dataset.lang || '';
    this.hl = Math.min(6, parseInt(root.dataset.hl || '3', 10));
    this.ownsUrl = root.hasAttribute('data-url');
    try { this.t = JSON.parse(root.querySelector('.kc-i18n')?.textContent || '{}'); } catch { this.t = {}; }
    this.tabs = [...root.querySelectorAll('[role="tab"]')];
    this.panels = [...root.querySelectorAll('[data-panel]')];
    this.seq = 0;
    this.initTabs();
    this.initAnalyse();
    this.initTls();
    this.initSpfGen();
    this.initDmarcGen();
    this.initAnalysis('spf-analyse');
    this.initAnalysis('dmarc-analyse');
    this.fromUrl();
  }

  panel(name) { return this.panels.find((p) => p.dataset.panel === name) || null; }

  // ------------------------------------------------------------------ Reiter

  initTabs() {
    if (!this.tabs.length) return;
    this.tabs.forEach((tab, i) => {
      tab.addEventListener('click', () => this.select(tab.dataset.tab, true));
      tab.addEventListener('keydown', (e) => {
        let j = null;
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') j = (i + 1) % this.tabs.length;
        else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') j = (i - 1 + this.tabs.length) % this.tabs.length;
        else if (e.key === 'Home') j = 0;
        else if (e.key === 'End') j = this.tabs.length - 1;
        if (j === null) return;
        e.preventDefault();
        this.select(this.tabs[j].dataset.tab, true);
        this.tabs[j].focus();
      });
    });
  }

  select(name, updateUrl = false) {
    if (!this.panel(name)) return false;
    for (const tab of this.tabs) {
      const on = tab.dataset.tab === name;
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
      tab.tabIndex = on ? 0 : -1;
    }
    for (const p of this.panels) p.hidden = p.dataset.panel !== name;
    if (updateUrl && this.ownsUrl) {
      const u = new URL(location.href);
      u.searchParams.set('tab', name);
      if (name !== 'checker' && name !== 'ssl-checker') u.searchParams.delete('domain');
      if (name !== 'ssl-checker') u.searchParams.delete('service');
      if (name !== 'checker') u.searchParams.delete('selector');
      history.replaceState(null, '', u);
    }
    return true;
  }

  fromUrl() {
    if (!this.ownsUrl) return;
    const q = new URLSearchParams(location.search);
    const tab = q.get('tab');
    const domain = (q.get('domain') || '').slice(0, 300);
    if (tab && this.panel(tab)) this.select(tab);
    if (!domain) return;
    if ((!tab || tab === 'checker') && this.panel('checker')) {
      this.select('checker');
      const f = this.panel('checker').querySelector('form');
      f.elements.domain.value = domain;
      if (q.get('selector')) f.elements.selector.value = q.get('selector').slice(0, 100);
      this.runAnalyse(f);
    } else if (tab === 'ssl-checker' && this.panel('ssl-checker')) {
      const f = this.panel('ssl-checker').querySelector('form');
      f.elements.host.value = domain;
      if (q.get('service') && [...f.elements.service.options].some((o) => o.value === q.get('service'))) f.elements.service.value = q.get('service');
      this.runTls(f);
    }
  }

  // ------------------------------------------------------------------ Schnittstelle

  async call(section, params, body = null) {
    const u = new URL(this.api + '/' + section, location.href);
    if (this.lang) u.searchParams.set('lang', this.lang);
    let init = { headers: { Accept: 'application/json' }, credentials: 'omit', cache: 'no-store' };
    if (body) {
      init = { ...init, method: 'POST', headers: { ...init.headers, 'Content-Type': 'application/json' }, body: JSON.stringify({ ...body, lang: this.lang }) };
    } else {
      for (const [k, v] of Object.entries(params || {})) if (v !== '' && v !== null && v !== undefined) u.searchParams.set(k, v);
    }
    try {
      const res = await fetch(u, init);
      const data = await res.json().catch(() => null);
      if (!data) return { ok: false, error: this.t.error };
      return data;
    } catch {
      return { ok: false, error: this.t.network };
    }
  }

  fieldError(input, errEl, msg) {
    if (msg) {
      input?.setAttribute('aria-invalid', 'true');
      errEl.textContent = msg;
      errEl.hidden = false;
      input?.focus();
    } else {
      input?.removeAttribute('aria-invalid');
      errEl.textContent = '';
      errEl.hidden = true;
    }
  }

  // ------------------------------------------------------------------ Domain-Analyse

  initAnalyse() {
    const p = this.panel('checker');
    if (!p) return;
    const f = p.querySelector('form');
    f.addEventListener('submit', (e) => {
      e.preventDefault();
      this.runAnalyse(f);
    });
    p.querySelector('[data-copylink]')?.addEventListener('click', async () => {
      const ok = await copyText(location.href);
      this.say(p.querySelector('[data-status]'), ok ? this.t.linkCopied : this.t.copyFailed);
    });
    // ⌘/Strg + K: Eingabefeld der Domain-Analyse (nur das erste Werkzeug der Seite)
    if (this.ownsUrl) {
      document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && !e.altKey && !e.shiftKey && e.key.toLowerCase() === 'k') {
          e.preventDefault();
          this.select('checker', true);
          f.elements.domain.focus();
          f.elements.domain.select();
        }
      });
    }
  }

  say(node, text) {
    if (!node) return;
    // Gleicher Text erneut: kurz leeren, damit Screenreader ihn wieder ansagen
    node.textContent = '';
    window.setTimeout(() => { node.textContent = text || ''; }, 30);
  }

  async runAnalyse(f) {
    const p = this.panel('checker');
    const input = f.elements.domain;
    const err = p.querySelector('.kc-err');
    const domain = cleanDomain(input.value);
    if (!domain) return this.fieldError(input, err, this.t.enterDomain);
    this.fieldError(input, err, '');
    input.value = domain;
    const selector = f.elements.selector.value.trim();
    const smtp = f.elements.smtp.checked ? '1' : '0';
    if (this.ownsUrl) {
      const u = new URL(location.href);
      u.searchParams.set('domain', domain);
      u.searchParams.delete('tab');
      if (selector) u.searchParams.set('selector', selector); else u.searchParams.delete('selector');
      history.replaceState(null, '', u);
    }
    const seq = ++this.seq;
    const btn = f.querySelector('button[type="submit"]');
    btn.setAttribute('aria-busy', 'true');
    const status = p.querySelector('[data-status]');
    const results = p.querySelector('[data-results]');
    const box = p.querySelector('[data-sections]');
    const jump = p.querySelector('[data-jump]');
    results.hidden = false;
    p.querySelector('[data-sdomain]').textContent = '– ' + domain;
    p.querySelector('[data-score]').replaceChildren(el('p', { class: 'kc-wait' }, el('span', { class: 'kc-spin', 'aria-hidden': 'true' }), fmt(this.t.runningAll, { domain })));
    p.querySelector('[data-meta]').textContent = '';
    this.say(status, fmt(this.t.runningAll, { domain }));
    box.replaceChildren();
    jump.replaceChildren();
    const cards = {};
    for (const s of SECTIONS) {
      const id = this.root.id + '-s-' + s;
      cards[s] = el('section', { class: 'kc-card', id, tabindex: '-1', 'aria-labelledby': id + '-h', 'aria-busy': 'true' },
        el('div', { class: 'kc-card__head' }, el('h' + this.hl, { class: 'kc-stitle', id: id + '-h' }, this.t.sections?.[s] || s),
          el('span', { class: 'kc-badge kc-badge--wait' }, el('span', { class: 'kc-spin', 'aria-hidden': 'true' }), this.t.running)));
      box.append(cards[s]);
      jump.append(el('li', {}, el('a', { href: '#' + id, 'data-jump': s }, this.t.sections?.[s] || s)));
    }
    const done = {};
    const queue = [...SECTIONS];
    // Höchstens drei Abfragen gleichzeitig (schont Server und Ratenbegrenzung)
    const worker = async () => {
      while (queue.length && seq === this.seq) {
        const s = queue.shift();
        const params = { domain };
        if (s === 'dkim' && selector) params.selector = selector;
        if (s === 'mail') params.smtp = smtp;
        const data = await this.call(s, params);
        if (seq !== this.seq) return;
        done[s] = data;
        this.renderCard(cards[s], data, s);
        this.updateJump(jump, s, data);
      }
    };
    await Promise.all([worker(), worker(), worker()]);
    btn.removeAttribute('aria-busy');
    if (seq !== this.seq) return;
    const sum = this.summary(done);
    p.querySelector('[data-score]').replaceChildren(sum.node);
    const checked = Object.values(done).find((d) => d && d.checked)?.checked;
    if (checked) p.querySelector('[data-meta]').textContent = new Date(checked).toLocaleString(document.documentElement.lang || undefined);
    this.say(status, fmt(this.t.doneAll, { domain, grade: sum.grade, score: sum.score }));
  }

  updateJump(list, s, data) {
    const a = list.querySelector(`[data-jump="${s}"]`);
    if (!a) return;
    const st = data.ok ? data.status : 'error';
    a.replaceChildren(el('span', { class: 'kc-v kc-v--' + st }, icon(st)), this.t.sections?.[s] || s, el('span', { class: 'kc-sr' }, ': ' + (this.t['status_' + st] || st)));
  }

  summary(done) {
    const vals = Object.values(done).filter((d) => d && d.ok);
    const score = vals.length ? Math.round(vals.reduce((a, d) => a + (d.score ?? 0), 0) / vals.length) : 0;
    const errors = Object.values(done).filter((d) => !d?.ok || d.status === 'error').length;
    let key = score >= 90 ? 'a' : score >= 75 ? 'b' : score >= 50 ? 'c' : 'd';
    // Probleme lassen sich nicht wegmitteln: ein Fehler höchstens „Ausbaufähig“, zwei und mehr „Kritisch“
    if (errors >= 2) key = 'd';
    else if (errors === 1 && (key === 'a' || key === 'b')) key = 'c';
    const st = { a: 'ok', b: 'info', c: 'warn', d: 'error' }[key];
    const grade = this.t['grade_' + key];
    const node = el('div', { class: 'kc-score' },
      el('div', { class: 'kc-grade kc-grade--' + st, 'aria-hidden': 'true' }, el('span', {}, String(score), el('small', {}, '/100'))),
      el('p', { class: 'kc-grade-text' }, el('b', {}, this.t.overall + ': ' + grade), fmt(this.t.points, { score })));
    return { node, grade, score };
  }

  // ------------------------------------------------------------------ Darstellung eines Ergebnisses

  renderCard(card, data, section, hl = this.hl) {
    card.removeAttribute('aria-busy');
    const id = card.id;
    const title = (data && data.title) || this.t.sections?.[section] || section;
    const head = el('div', { class: 'kc-card__head' }, el('h' + hl, { class: 'kc-stitle', id: id + '-h' }, title));
    if (!data || !data.ok) {
      head.append(this.badge('error'));
      card.replaceChildren(head, el('ul', { class: 'kc-findings' }, this.finding('error', (data && data.error) || this.t.error)));
      return;
    }
    head.append(this.badge(data.status));
    const kids = [head];
    if (data.summary) kids.push(el('p', { class: 'kc-card__sum' }, data.summary));
    if (data.findings?.length) kids.push(el('ul', { class: 'kc-findings' }, data.findings.map((f) => this.finding(f.level, f.text))));
    for (const b of data.blocks || []) kids.push(this.block(b, hl + 1));
    if (data.cached && data.checked) kids.push(el('p', { class: 'kc-meta' }, fmt(this.t.cached, { time: new Date(data.checked).toLocaleTimeString(document.documentElement.lang || undefined, { hour: '2-digit', minute: '2-digit' }) })));
    card.replaceChildren(...kids);
  }

  badge(st) {
    return el('span', { class: 'kc-badge kc-badge--' + st }, icon(st), this.t['status_' + st] || st);
  }

  finding(level, text) {
    return el('li', { class: 'kc-f kc-f--' + level }, icon(level), el('span', {}, el('span', { class: 'kc-sr' }, (this.t['level_' + level] || '') + ' '), text));
  }

  value(v) {
    if (Array.isArray(v)) {
      const [text, level] = v;
      if (!level) return document.createTextNode(String(text ?? ''));
      return el('span', { class: 'kc-v kc-v--' + level }, icon(level), el('span', {}, String(text ?? ''), el('span', { class: 'kc-sr' }, ' (' + (this.t['status_' + level] || level) + ')')));
    }
    return document.createTextNode(String(v ?? ''));
  }

  block(b, hl) {
    const wrap = el('div', { class: 'kc-block' });
    const h = Math.min(6, hl);
    if (b.title) wrap.append(el('h' + h, { class: 'kc-btitle' }, b.title));
    if (b.type === 'kv') {
      const dl = el('dl', { class: 'kc-kv' });
      for (const [k, v, lvl] of b.rows) dl.append(el('dt', {}, k), el('dd', {}, this.value(lvl ? [v, lvl] : v)));
      wrap.append(dl);
    } else if (b.type === 'table') {
      const table = el('table', {});
      if (b.caption) table.append(el('caption', {}, b.caption));
      table.append(el('thead', {}, el('tr', {}, b.head.map((c) => el('th', { scope: 'col' }, c)))));
      table.append(el('tbody', {}, b.rows.map((r) => el('tr', {}, r.map((c, i) => el(i === 0 ? 'th' : 'td', i === 0 ? { scope: 'row', class: 'kc-c--mono' } : {}, this.value(c)))))));
      wrap.append(el('div', { class: 'kc-tablewrap', tabindex: '0', role: 'region', 'aria-label': b.title || '' }, table));
    } else if (b.type === 'code') {
      wrap.append(this.codeBox(b.text));
    } else if (b.type === 'list') {
      wrap.append(el(b.ordered ? 'ol' : 'ul', { class: 'kc-list' }, b.items.map(([t, lvl]) => el('li', {}, this.value(lvl ? [t, lvl] : t)))));
    }
    return wrap;
  }

  codeBox(text) {
    const btn = el('button', { type: 'button', class: 'btn btn--secondary kc-btn kc-sm' }, this.t.copy);
    btn.addEventListener('click', () => this.copyBtn(btn, text));
    return el('div', { class: 'kc-code' }, el('code', {}, text), btn);
  }

  async copyBtn(btn, text) {
    const ok = await copyText(text);
    const old = this.t.copy;
    btn.textContent = ok ? this.t.copied : this.t.copyFailed;
    window.setTimeout(() => { btn.textContent = old; }, 2000);
  }

  // ------------------------------------------------------------------ SSL/TLS

  initTls() {
    const p = this.panel('ssl-checker');
    if (!p) return;
    const f = p.querySelector('form');
    f.addEventListener('submit', (e) => {
      e.preventDefault();
      this.runTls(f);
    });
  }

  async runTls(f) {
    const p = this.panel('ssl-checker');
    const host = cleanDomain(f.elements.host.value);
    const err = p.querySelector('.kc-err');
    if (!host) return this.fieldError(f.elements.host, err, this.t.enterDomain);
    this.fieldError(f.elements.host, err, '');
    f.elements.host.value = host;
    const service = f.elements.service.value;
    if (this.ownsUrl) {
      const u = new URL(location.href);
      u.searchParams.set('tab', 'ssl-checker');
      u.searchParams.set('domain', host);
      u.searchParams.set('service', service);
      history.replaceState(null, '', u);
    }
    await this.single(p, 'tls', { host, service }, null, this.hl - 1);
  }

  /** Eine Prüfung mit einer Ergebnis-Karte (SSL/TLS, Analysen) */
  async single(p, section, params, body, hl, statusSel = '[data-status]') {
    const status = p.querySelector(statusSel);
    const out = p.querySelector('[data-out]');
    const btn = (body ? p.querySelector(`[data-form="${section}"]`) : p.querySelector('form'))?.querySelector('button[type="submit"]');
    btn?.setAttribute('aria-busy', 'true');
    this.say(status, this.t.running);
    const card = el('section', { class: 'kc-card', id: this.root.id + '-' + section, tabindex: '-1', 'aria-busy': 'true', 'aria-labelledby': this.root.id + '-' + section + '-h' },
      el('p', { class: 'kc-wait' }, el('span', { class: 'kc-spin', 'aria-hidden': 'true' }), this.t.running));
    out.replaceChildren(card);
    const data = await this.call(section, params, body);
    btn?.removeAttribute('aria-busy');
    this.renderCard(card, data, section, Math.max(2, hl + 1));
    this.say(status, data.ok ? (data.title ? data.title + ': ' : '') + (data.summary || this.t['status_' + data.status]) : (data.error || this.t.error));
    return data;
  }

  // ------------------------------------------------------------------ SPF-Generator

  initSpfGen() {
    const p = this.panel('spf-generator');
    if (!p) return;
    const f = p.querySelector('[data-form="spf-gen"]');
    const out = p.querySelector('[data-genout]');
    f.addEventListener('submit', (e) => {
      e.preventDefault();
      const err = f.querySelector('[data-generr]');
      const bad = [];
      const mark = (name, ok) => (ok ? f.elements[name].removeAttribute('aria-invalid') : f.elements[name].setAttribute('aria-invalid', 'true'));
      const domain = cleanDomain(f.elements.domain.value);
      mark('domain', RE_DOMAIN.test(domain));
      if (!RE_DOMAIN.test(domain)) bad.push(fmt(this.t.badDomain, { v: domain || '–' }));
      const pick = (name, test, msg) => {
        const vals = lines(f.elements[name].value);
        const wrong = vals.filter((v) => !test(v));
        mark(name, !wrong.length);
        wrong.forEach((v) => bad.push(fmt(msg, { v })));
        return vals.filter(test);
      };
      const ip4 = pick('ip4', validIp4, this.t.badIp4);
      const ip6 = pick('ip6', validIp6, this.t.badIp6);
      const inc = pick('include', (v) => RE_DOMAIN.test(v), this.t.badDomain);
      const ah = pick('ahost', (v) => RE_DOMAIN.test(v), this.t.badDomain);
      const mh = pick('mxhost', (v) => RE_DOMAIN.test(v), this.t.badDomain);
      const providers = [...f.querySelectorAll('input[name="provider"]:checked')].map((x) => x.value);
      if (bad.length) {
        err.textContent = this.t.genInvalid + ' ' + bad.slice(0, 4).join(' ');
        err.hidden = false;
        f.querySelector('[aria-invalid="true"]')?.focus();
        return;
      }
      err.hidden = true;
      const terms = [];
      const add = (t) => { if (!terms.includes(t)) terms.push(t); };
      ip4.forEach((v) => add('ip4:' + v));
      ip6.forEach((v) => add('ip6:' + v));
      if (f.elements.a.checked) add('a');
      ah.map((h) => (h === domain ? 'a' : 'a:' + h)).forEach(add);
      if (f.elements.mx.checked) add('mx');
      mh.map((h) => (h === domain ? 'mx' : 'mx:' + h)).forEach(add);
      [...providers, ...inc].forEach((v) => add('include:' + v));
      const record = ['v=spf1', ...terms, f.elements.all.value].join(' ');
      const lookups = terms.filter((t) => /^(a|mx|include|exists|ptr)(:|$)/.test(t)).length;
      const notes = [el('li', { class: 'kc-f kc-f--' + (lookups > 10 ? 'error' : lookups >= 8 ? 'warn' : 'info') }, icon(lookups > 10 ? 'error' : lookups >= 8 ? 'warn' : 'info'), el('span', {}, fmt(this.t.lookups, { n: lookups })))];
      if (record.length > 255) notes.push(this.finding('info', this.t.tooLong));
      out.querySelector('[data-record]').textContent = record;
      out.querySelector('[data-notes]').replaceChildren(...notes);
      out.hidden = false;
      out.dataset.domain = domain;
      this.say(p.querySelector('[data-status]'), this.t.genSpfDone);
      out.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    });
    this.wireOut(p, 'spf-analyse');
  }

  /** Ausgabe der Generatoren: Kopieren, „Diesen Eintrag analysieren“ */
  wireOut(p, analyse) {
    const out = p.querySelector('[data-genout]');
    const btn = out.querySelector('[data-copy]');
    btn.addEventListener('click', () => this.copyBtn(btn, out.querySelector('[data-record]').textContent));
    out.querySelector('[data-toanalyse]').addEventListener('click', () => {
      const f = p.querySelector(`[data-form="${analyse}"]`);
      f.elements.record.value = out.querySelector('[data-record]').textContent;
      if (out.dataset.domain) f.elements.domain.value = out.dataset.domain;
      f.requestSubmit();
      f.elements.record.focus();
    });
  }

  // ------------------------------------------------------------------ DMARC-Generator

  initDmarcGen() {
    const p = this.panel('dmarc-generator');
    if (!p) return;
    const f = p.querySelector('[data-form="dmarc-gen"]');
    const out = p.querySelector('[data-genout]');
    const pctOut = f.querySelector('[data-pctout]');
    const pct = f.elements.pct;
    const showPct = () => { pctOut.textContent = fmt(this.t.percent, { n: pct.value }); };
    pct.addEventListener('input', showPct);
    showPct();
    f.addEventListener('submit', (e) => {
      e.preventDefault();
      const err = f.querySelector('[data-generr]');
      const bad = [];
      const rua = f.elements.rua.value.trim();
      const ruf = f.elements.ruf.value.trim();
      const domain = cleanDomain(f.elements.domain.value);
      for (const [name, v] of [['rua', rua], ['ruf', ruf]]) {
        const ok = !v || RE_MAIL.test(v);
        ok ? f.elements[name].removeAttribute('aria-invalid') : f.elements[name].setAttribute('aria-invalid', 'true');
        if (!ok) bad.push(fmt(this.t.badMail, { v }));
      }
      const dOk = !domain || RE_DOMAIN.test(domain);
      dOk ? f.elements.domain.removeAttribute('aria-invalid') : f.elements.domain.setAttribute('aria-invalid', 'true');
      if (!dOk) bad.push(fmt(this.t.badDomain, { v: domain }));
      if (bad.length) {
        err.textContent = this.t.genInvalid + ' ' + bad.join(' ');
        err.hidden = false;
        f.querySelector('[aria-invalid="true"]')?.focus();
        return;
      }
      err.hidden = true;
      const pol = f.elements.p.value;
      const parts = ['v=DMARC1', 'p=' + pol];
      if (f.elements.sp.value && f.elements.sp.value !== pol) parts.push('sp=' + f.elements.sp.value);
      if (pct.value !== '100') parts.push('pct=' + pct.value);
      if (rua) parts.push('rua=mailto:' + rua);
      if (ruf) parts.push('ruf=mailto:' + ruf, 'fo=1');
      if (f.elements.aspf.value === 's') parts.push('aspf=s');
      if (f.elements.adkim.value === 's') parts.push('adkim=s');
      const record = parts.join('; ');
      const notes = [];
      if (pol === 'none') notes.push(this.finding('warn', this.t.noneWarn));
      if (!rua) notes.push(this.finding('warn', this.t.noRua));
      for (const m of [rua, ruf].filter(Boolean)) {
        const host = m.slice(m.lastIndexOf('@') + 1).toLowerCase();
        if (domain && host !== domain && !host.endsWith('.' + domain) && !domain.endsWith('.' + host)) {
          notes.push(this.finding('info', fmt(this.t.externalRua, { host, name: domain + '._report._dmarc.' + host })));
        }
      }
      out.querySelector('[data-record]').textContent = record;
      out.querySelector('[data-notes]').replaceChildren(...notes);
      out.hidden = false;
      out.dataset.domain = domain;
      this.say(p.querySelector('[data-status]'), this.t.genDmarcDone);
      out.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    });
    this.wireOut(p, 'dmarc-analyse');
  }

  // ------------------------------------------------------------------ Analyse eingefügter Einträge

  initAnalysis(section) {
    const panelName = section === 'spf-analyse' ? 'spf-generator' : 'dmarc-generator';
    const p = this.panel(panelName);
    if (!p) return;
    const f = p.querySelector(`[data-form="${section}"]`);
    const err = f.querySelector('[data-generr]');
    for (const b of f.querySelectorAll('[data-example]')) {
      b.addEventListener('click', () => {
        f.elements.record.value = b.dataset.example;
        f.elements.domain.value = b.dataset.exdomain || '';
        f.elements.record.focus();
      });
    }
    f.addEventListener('submit', (e) => {
      e.preventDefault();
      const record = f.elements.record.value.trim();
      if (!record) return this.fieldError(f.elements.record, err, section === 'spf-analyse' ? this.t.pasteSpf : this.t.pasteDmarc);
      const domain = cleanDomain(f.elements.domain.value);
      if (domain && !RE_DOMAIN.test(domain)) return this.fieldError(f.elements.domain, err, fmt(this.t.badDomain, { v: domain }));
      this.fieldError(f.elements.record, err, '');
      f.elements.domain.removeAttribute('aria-invalid');
      this.single(p, section, null, { record, domain }, this.hl, '[data-status2]');
    });
  }
}

function init() {
  for (const root of document.querySelectorAll('[data-kc]')) {
    if (root.dataset.kcReady) continue;
    root.dataset.kcReady = '1';
    new Tool(root);
  }
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();
