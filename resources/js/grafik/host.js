/*
 * Grafik-Editor – Anbindung an das CMS („Host“). Alles, was die Persona „Bild“ und das Ausfüllen von Vorlagen vom CMS brauchen,
 * läuft über dieses eine Objekt (im Editor: ed.cms, Einbettung: mountEditor(el, boot, { cms })) – keine verstreuten fetch()-Aufrufe. Eine andere Umgebung (z. B. REDAXO) liefert ein Objekt mit
 * denselben Funktionen:
 *   can(what)                 'templates' (Vorlagen gestalten), 'parts' (Bausteine)
 *   tokens()                  Design-Farben [{name, color, token, dark}]
 *   media.pick()              Foto aus der Mediathek wählen → {id} | null (Medienwähler des CMS)
 *   media.info(ids)           Angaben zu Fotos → {id: {id, url, full, w, h, focus: {x, y}, alt, name}}
 *   upload(blob, name)        Datei in Stücken hochladen (Mechanik der Mediathek) → Upload-ID
 *   saveImage(body)           Bild speichern (Arbeitsfassung + Ausgaben je Format) → {id, outputs, versions}
 *   templates.list()          Vorlagen → {templates, swatches, photos, fontFiles}
 *   templates.save(body)      Vorlage speichern → {item}
 *   templates.remove(ref)     Vorlage löschen
 * Übersetzungen: t() aus ../_i18n.js (im CMS: Wörterbuch der Verwaltung). MIT
 */
import { csrf } from '../_html.js';
import { t } from '../_i18n.js';

const CHUNK = 1024 * 1024;

export function studioHost(boot) {
  const u = boot.compose || {};
  const req = async (method, url, body) => {
    const r = await fetch(url, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': csrf(), ...(body && !(body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}) },
      body: body instanceof FormData ? body : body ? JSON.stringify(body) : undefined });
    const j = await r.json().catch(() => ({ ok: false, error: t('Unerwartete Antwort vom Server.') }));
    if (!r.ok || j.ok === false) throw new Error(j.error || t('Speichern fehlgeschlagen.'));
    return j;
  };
  return {
    can: what => (what === 'templates' ? !!boot.canTemplates : what === 'parts' ? !!boot.canParts : false),
    tokens: () => boot.swatches || [],
    media: {
      async pick() {
        const m = await window.CMSMedia?.pick?.('image');
        return m && m.id ? m : null;
      },
      async info(ids) {
        const list = [...new Set(ids.map(Number).filter(Boolean))];
        if (!list.length || !u.photos) return {};
        return (await req('GET', u.photos + '?ids=' + list.join(','))).photos || {};
      },
    },
    async upload(blob, name = 'bild') {
      const id = (crypto.randomUUID?.() || String(Date.now()) + Math.random().toString(16).slice(2)).replace(/[^a-zA-Z0-9-]/g, '');
      const total = Math.max(1, Math.ceil(blob.size / CHUNK));
      for (let i = 0; i < total; i++) {
        const fd = new FormData();
        fd.append('chunk', blob.slice(i * CHUNK, (i + 1) * CHUNK), 'chunk');
        fd.append('upload_id', id); fd.append('index', String(i)); fd.append('total', String(total)); fd.append('size', String(blob.size));
        fd.append('_csrf', csrf()); fd.append('name', name);
        await req('POST', u.chunk, fd);
      }
      return id;
    },
    saveImage: body => req('POST', u.save, body),
    templates: {
      list: () => req('GET', u.templates),
      save: body => req('POST', u.template, body),
      remove: ref => req('POST', u.templateDelete, { ref }),
    },
  };
}
