/*
 * Grafik-Editor (Core\Graphics) – eigenes Bündel, lädt nur auf /admin/grafik/editor oder beim ersten Öffnen als Overlay
 * (admin.js → _grafik.js). Schnittstelle für Mediathek, Blockauswahl und Erweiterungen (z. B. Motion):
 *
 *   CMSGraphicEditor.open({ format: 'info'|'icon'|'preview', mediaId, id, block, w, h, svg, target: 'none', onSave })
 *     → Promise<{ saved, svg }>       Overlay; target 'none' = nichts ablegen, onSave bekommt das bereinigte SVG
 *   CMSGraphicEditor.fill({ id?, template?, returnFocus }) → Promise<{ saved }>   Vorlage ausfüllen (Formular, fertige Bilder in der Mediathek)
 *   CMSGraphicEditor.mount(el, boot, { onSave, shell })  → Editor-Instanz (getSvg, setSvg, on, select, registerPersona)
 *
 * Entwicklerhandbuch → Medien → Grafik-Editor. MIT
 */
import { GraphicEditor } from './grafik/editor.js';
import { open, mountPage } from './grafik/shell.js';
import { fill } from './grafik/fill.js';

window.CMSGraphicEditor = Object.assign(window.CMSGraphicEditor || {}, {
  version: 1,
  open,
  // Persona Bild: Vorlage ausfüllen (Mediathek → Neu → „Aus Vorlage erstellen“), ohne Editor
  fill,
  mount: (el, boot, opts = {}) => new GraphicEditor(el, boot, opts),
});
mountPage();
