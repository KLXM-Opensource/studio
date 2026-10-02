/*
 * Grundeinstellungen → KI (app/Admin/views/system/_ai.php, Core\AI\Profiles, Core\AI\Probe):
 *  - „Verbindung prüfen“ je Verbindung → POST /admin/system/ai/provider {op: check, id} (JSON), Ergebnis + Statuspunkt
 *  - „Modelle anzeigen“ / „Modelle …“ in der Verwendung → {op: models}: Liste mit Größe, Parametern, Quantisierung, Kontext,
 *    Eignung (Text, Bilder, Embeddings, Audio), Filter; „Übernehmen“ setzt Verbindung + Modell in der Zeile des Zwecks
 *  - Dialog „Verbindung hinzufügen/bearbeiten“: Felder je Art, Prüfen und Modelle mit den (noch nicht gespeicherten) Werten;
 *    Schlüssel kommen nie zurück (nur „gesetzt“), leer = unverändert
 *  - Hinweis, wenn sich das Embedding-Modell ändert (Suchindex rechnet neu)
 * Ohne JavaScript: Speichern und Löschen funktionieren als Formulare, Prüfen/Modelle brauchen das Skript.
 */
import { t } from './_i18n.js';

const d = document;
const $ = (s, c = d) => c.querySelector(s);
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const csrf = () => $('#adm-csrf')?.value || $('input[name=_csrf]')?.value || '';
const CAPS = { chat: 'Text', vision: 'Bilder', embed: 'Embeddings', audio: 'Audio' };
const NEED = { text: 'chat', chat: 'chat', vision: 'vision', embed: 'embed', transcribe: 'audio' };
const URLHELP = {
  ollama: 'http://localhost:11434',
  mistral: 'https://api.mistral.ai',
  anthropic: 'https://api.anthropic.com',
  generic: 'https://llm.example.org',
};

let cfg = null;

async function call(body) {
  const r = await fetch(cfg.endpoint, { method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify(body) });
  let j = null;
  try { j = await r.json(); } catch { j = null; }
  return j || { ok: false, status: 'error', message: t('Unerwartete Antwort ({code}).', { code: r.status }) };
}

function dotClass(status) {
  return { ok: 'ok', auth: 'err', unreachable: 'err', blocked: 'err', error: 'err', wrong_type: 'warn' }[status] || 'none';
}
function setDot(dot, status, text) {
  if (!dot) return;
  dot.className = 'aip-dot aip-dot--' + dotClass(status);
  const tx = dot.querySelector('.aip-dot__txt');
  if (tx) tx.textContent = text;
}
const statusText = s => ({ ok: t('erreichbar'), auth: t('Schlüssel falsch'), unreachable: t('nicht erreichbar'), wrong_type: t('falsche Art'), blocked: t('Adresse gesperrt') })[s] || t('Fehler');

function showResult(p, res) {
  p.hidden = false;
  p.className = 'aip-result aip-result--' + dotClass(res.status);
  p.textContent = res.message || statusText(res.status);
}

const gb = n => n == null ? '' : n >= 1e9 ? (n / 1e9).toLocaleString(undefined, { maximumFractionDigits: 1 }) + ' GB' : (n / 1e6).toLocaleString(undefined, { maximumFractionDigits: 0 }) + ' MB';
const ctx = n => !n ? '' : n >= 1000 ? Math.round(n / 1024) + 'k' : String(n);

// ------------------------------------------------------------------ Modelle
let mstate = null;
function openModels(src) {
  const dlg = $('#aip-models');
  if (!dlg) return;
  mstate = { ...src, list: [], q: '' };
  $('[data-aip-mprof]', dlg).textContent = src.label ? '· ' + src.label : '';
  const sel = $('[data-aip-mfor]', dlg);
  if (sel) {
    const types = cfg.supports[src.type] || [];
    [...sel.options].forEach(o => { o.hidden = o.disabled = !types.includes(o.value); });
    sel.value = src.purpose && types.includes(src.purpose) ? src.purpose : (types[0] || 'text');
    sel.closest('label').hidden = !src.id && !src.purpose;   // ungespeicherte Verbindung: nur ansehen
  }
  $('[data-aip-mq]', dlg).value = '';
  $('[data-aip-mbody]', dlg).innerHTML = '';
  const st = $('[data-aip-mstatus]', dlg);
  st.className = 'aip-result';
  st.textContent = t('Modelle werden geladen …');
  dlg.showModal();
  $('[data-aip-mq]', dlg).focus();
  call({ op: 'models', id: src.id || '', ...(src.p ? { p: src.p } : {}) }).then(res => {
    if (mstate?.seq !== undefined && mstate.seq !== src.seq) return;
    if (!res.ok) { showResult(st, res); return; }
    mstate.list = res.models || [];
    st.className = 'aip-result';
    st.textContent = (res.curated ? t('Der Dienst liefert keine Liste – bekannte Modelle:') + ' ' : '')
      + (mstate.list.length === 1 ? t('1 Modell') : t('{n} Modelle', { n: mstate.list.length })) + (res.ms != null ? ` · ${res.ms} ms` : '');
    renderModels();
  });
}
function renderModels() {
  const dlg = $('#aip-models'), body = $('[data-aip-mbody]', dlg);
  const q = $('[data-aip-mq]', dlg).value.trim().toLowerCase();
  const purpose = $('[data-aip-mfor]', dlg)?.value || mstate.purpose || '';
  const canPick = !!$('[data-aip-mfor]', dlg) && !$('[data-aip-mfor]', dlg).closest('label').hidden;
  const need = NEED[purpose];
  const list = mstate.list.filter(m => !q || m.name.toLowerCase().includes(q) || (m.family || '').toLowerCase().includes(q));
  // Passende Modelle zuerst
  list.sort((a, b) => (b.caps.includes(need) ? 1 : 0) - (a.caps.includes(need) ? 1 : 0));
  const cur = canPick ? $(`[data-aip-use="${purpose}"] [data-aip-umodel]`)?.value : '';
  body.innerHTML = list.map((m, i) => {
    const fits = !need || !m.caps.length || m.caps.includes(need);
    const size = [m.params, m.quant, gb(m.size)].filter(Boolean).join(' · ');
    return `<tr class="${fits ? '' : 'is-off'}${m.name === cur ? ' is-current' : ''}"><th scope="row"><code>${esc(m.name)}</code>${m.family && m.family !== m.name ? `<br><small class="adm-muted">${esc(m.family)}</small>` : ''}</th>`
      + `<td>${esc(size) || '–'}</td><td>${esc(ctx(m.context)) || '–'}</td>`
      + `<td>${m.caps.map(c => `<span class="aip-cap aip-cap--${esc(c)}">${esc(t(CAPS[c] || c))}</span>`).join(' ') || '–'}</td>`
      + `<td>${canPick ? `<button type="button" class="adm-btn adm-btn--small${fits ? '' : ' adm-btn--ghost'}" data-aip-take="${i}" aria-label="${esc(t('„{m}“ übernehmen', { m: m.name }))}">${esc(m.name === cur ? t('Gewählt') : t('Übernehmen'))}</button>` : ''}</td></tr>`;
  }).join('') || `<tr><td colspan="5" class="adm-muted">${esc(t('Nichts gefunden.'))}</td></tr>`;
  mstate.shown = list;
}
function take(i) {
  const m = mstate.shown[i], dlg = $('#aip-models');
  const purpose = $('[data-aip-mfor]', dlg)?.value || mstate.purpose;
  const row = $(`[data-aip-use="${purpose}"]`);
  if (!m || !row) return;
  const sel = $('[data-aip-uprof]', row), inp = $('[data-aip-umodel]', row);
  if (mstate.id && sel && !sel.disabled) sel.value = mstate.id;
  if (inp && !inp.disabled) inp.value = m.name;
  inp?.dispatchEvent(new Event('input', { bubbles: true }));
  dlg.close();
  $('#ki-use')?.setAttribute('open', '');
  row.classList.add('is-changed');
  inp?.focus();
  const note = $('[data-aip-usenote]') || Object.assign(d.createElement('p'), { className: 'aip-result aip-result--ok' });
  note.dataset.aipUsenote = '';
  note.setAttribute('role', 'status');
  note.textContent = t('„{m}“ für „{p}“ übernommen – mit „Verwendung speichern“ sichern.', { m: m.name, p: cfg.purposes[purpose] || purpose });
  $('.aip-save')?.before(note);
}

// ------------------------------------------------------------------ Verbindung (Dialog)
function profileValues(form) {
  const p = {};
  $$('[data-aip-f]', form).forEach(f => { if (!f.closest("[hidden]")) p[f.dataset.aipF] = f.value; });
  return p;
}
function typeChanged(form) {
  const type = $('[data-aip-f=type]', form).value;
  $$('[data-aip-for]', form).forEach(x => { x.hidden = !x.dataset.aipFor.split(' ').includes(type); });
  const url = $('[data-aip-f=base_url]', form);
  url.placeholder = URLHELP[type] || '';
  const key = $('[data-aip-f=api_key]', form);
  const has = form.dataset.hasKey === '1';
  key.placeholder = has ? t('gesetzt – leer lassen = unverändert') : (type === 'ollama' ? t('optional') : '');
}
function openProfile(id) {
  const dlg = $('#aip-profile');
  if (!dlg) return;
  const form = $('[data-aip-pform]', dlg), p = id ? cfg.profiles[id] : null;
  form.reset();
  $('[data-aip-pid]', form).value = id || '';
  $('[data-aip-ptitle]', form).textContent = p ? t('Verbindung bearbeiten') : t('Verbindung hinzufügen');
  form.dataset.hasKey = p?.has_key ? '1' : '0';
  $$('[data-aip-f]', form).forEach(f => {
    const k = f.dataset.aipF;
    f.disabled = !!p?.locked?.includes(k);
    f.title = f.disabled ? t('in einer Konfigurationsdatei festgelegt') : '';
    if (!p || k === 'api_key') { if (k === 'type' && !p) f.value = 'ollama'; return; }
    const v = p[k];
    f.value = v == null ? '' : String(k === 'base_url' && p.type === 'ollama' && v === 'http://127.0.0.1:11434' ? v : v);
  });
  if (!p) $('[data-aip-f=base_url]', form).value = 'http://localhost:11434';
  $('[data-aip-presult]', form).hidden = true;
  typeChanged(form);
  dlg.showModal();
  $('[data-aip-f=label]', form).focus();
}

export function initAiSettings() {
  const data = $('#aip-data');
  if (!data) return;
  try { cfg = JSON.parse(data.textContent); } catch { return; }
  const root = $('[data-aip]');

  // Prüfen und Modelle je Verbindung
  root?.addEventListener('click', async e => {
    const chk = e.target.closest('[data-aip-check]');
    if (chk) {
      const row = chk.closest('[data-aip-prow]'), out = $('[data-aip-result]', row);
      chk.disabled = true;
      chk.setAttribute('aria-busy', 'true');
      out.hidden = false; out.className = 'aip-result'; out.textContent = t('Prüfe …');
      const res = await call({ op: 'check', id: chk.dataset.aipCheck });
      chk.disabled = false; chk.removeAttribute('aria-busy');
      showResult(out, res);
      setDot($('[data-aip-dot]', row), res.status, statusText(res.status));
      return;
    }
    const mod = e.target.closest('[data-aip-models]');
    if (mod) {
      const p = cfg.profiles[mod.dataset.aipModels];
      openModels({ id: mod.dataset.aipModels, label: p?.label, type: p?.type, purpose: '' });
      return;
    }
    const ed = e.target.closest('[data-aip-edit]');
    if (ed) { openProfile(ed.dataset.aipEdit); return; }
    if (e.target.closest('[data-aip-add]')) { openProfile(''); return; }
    const pick = e.target.closest('[data-aip-pick]');
    if (pick) {
      const row = pick.closest('[data-aip-use]'), id = $('[data-aip-uprof]', row)?.value;
      const purpose = pick.dataset.aipPick;
      // Besucher-Chat ohne eigene Verbindung: Modelle der Verbindung für Texte
      const pid = id || (purpose === 'chat' ? $('[data-aip-use="text"] [data-aip-uprof]')?.value : '');
      if (!pid) {
        const sel = $('[data-aip-uprof]', row);
        sel?.focus();
        const note = row.querySelector('.aip-warn--pick') || Object.assign(d.createElement('p'), { className: 'aip-warn aip-warn--pick' });
        note.setAttribute('role', 'status');
        note.textContent = t('Bitte zuerst eine Verbindung wählen.');
        pick.closest('td').append(note);
        return;
      }
      row.querySelector('.aip-warn--pick')?.remove();
      const p = cfg.profiles[pid];
      openModels({ id: pid, label: p?.label, type: p?.type, purpose });
    }
  });

  // Hinweis bei neuem Embedding-Modell
  const er = $('[data-aip-use="embed"]');
  if (er) {
    const sel = $('[data-aip-uprof]', er), inp = $('[data-aip-umodel]', er), warn = $('[data-aip-embedwarn]', er);
    const orig = (sel?.value || '') + '|' + (inp?.value || '');
    const upd = () => { warn.hidden = !sel?.value || ((sel?.value || '') + '|' + (inp?.value || '')) === orig || orig === '|'; };
    sel?.addEventListener('change', upd); inp?.addEventListener('input', upd);
  }

  // Modell-Dialog
  const md = $('#aip-models');
  if (md) {
    $('[data-aip-mq]', md).addEventListener('input', renderModels);
    $('[data-aip-mfor]', md)?.addEventListener('change', renderModels);
    md.addEventListener('click', e => {
      if (e.target.closest('[data-aip-close]')) { md.close(); return; }
      const b = e.target.closest('[data-aip-take]');
      if (b) take(+b.dataset.aipTake);
    });
  }

  // Verbindungs-Dialog
  const pd = $('#aip-profile');
  if (pd) {
    const form = $('[data-aip-pform]', pd), out = $('[data-aip-presult]', pd);
    $('[data-aip-f=type]', form).addEventListener('change', () => typeChanged(form));
    pd.addEventListener('click', async e => {
      if (e.target.closest('[data-aip-close]')) { pd.close(); return; }
      const id = $('[data-aip-pid]', form).value;
      if (e.target.closest('[data-aip-pcheck]')) {
        out.hidden = false; out.className = 'aip-result'; out.textContent = t('Prüfe …');
        showResult(out, await call({ op: 'check', id, p: profileValues(form) }));
      } else if (e.target.closest('[data-aip-pmodels]')) {
        const p = profileValues(form);
        pd.close();
        openModels({ id, label: p.label, type: p.type || cfg.profiles[id]?.type, purpose: '', p });
      }
    });
  }
}
