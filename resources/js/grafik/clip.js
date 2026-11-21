/*
 * Grafik-Editor – Beschneiden und Masken (wie „Schnittmaske erstellen“ in Illustrator bzw. Beschneiden/Maskieren in Affinity):
 * das oberste Objekt der Auswahl beschneidet bzw. maskiert die übrigen. Ergebnis: eine Gruppe mit clip-path bzw. mask; die
 * Form liegt als <clipPath>/<mask> als erstes Kind in dieser Gruppe (sichtbar in den Ebenen, wird mitkopiert, Desktop-
 * Programme lesen es als Schnittmaske bzw. Deckkraftmaske).
 *
 * Maske = Helligkeit (SVG-Standard, überall lesbar): Weiß zeigt, Schwarz verbirgt, Verläufe blenden weich aus. Eine Form mit
 * einfarbiger Füllung wird in der Maske weiß (die alte Farbe merkt data-ge-fill, „Freigeben“ stellt sie wieder her).
 * Ausstanzen per Maske (kind 'knockout', „umgekehrte Maske“): weiße Fläche über den Inhalt, die Form schwarz darin – die Form
 * schneidet ein Loch, bleibt aber als Form bearbeitbar (zerstörungsfrei). MIT
 */
import { NS, docMatrix, parentMatrix, fmtMatrix } from './geom.js';
import { uid, refOf, useCount, getProp, setProp } from './doc.js';
import { t } from '../_i18n.js';

const SHAPE_SEL = 'path,rect,circle,ellipse,line,polyline,polygon,text,use';

/** Verweist das Element auf eine Beschneidung/Maske? → { kind, def } */
export function clipOf(el, root) {
  for (const [attr, kind] of [['clip-path', 'clip'], ['mask', 'mask']]) {
    const v = getProp(el, attr, false);
    if (v && v !== 'none') { const def = refOf(root, v); if (def) return { kind, def, attr }; }
  }
  return null;
}

/**
 * Beschneiden (kind 'clip') bzw. Maske (kind 'mask') aus der Auswahl (in Dokumentreihenfolge, oberstes = Form).
 * Rückgabe: { el: Gruppe } oder { error }
 */
export function makeClip(root, list, kind) {
  if (list.length < 2) return { error: t('Bitte mindestens zwei Objekte auswählen – das oberste beschneidet die übrigen.') };
  const parent = list[0].parentNode;
  if (list.some(el => el.parentNode !== parent)) return { error: t('Nur Objekte derselben Ebene lassen sich gemeinsam beschneiden.') };
  const top = list[list.length - 1], rest = list.slice(0, -1);
  const g = document.createElementNS(NS, 'g');
  top.after(g);
  g.append(...rest);
  const def = document.createElementNS(NS, kind === 'clip' ? 'clipPath' : 'mask');
  def.id = uid(root, kind === 'clip' ? 'beschnitt-' : 'maske-');
  if (kind === 'knockout') {
    // weiße Fläche über den ganzen Inhalt (Raum der Gruppe), etwas größer wegen Konturen
    let b = null;
    try { b = g.getBBox(); } catch { /* leer */ }
    const pad = b ? Math.max(b.width, b.height) * 0.1 + 10 : 0;
    const r = document.createElementNS(NS, 'rect');
    if (b) { r.setAttribute('x', String(Math.floor(b.x - pad))); r.setAttribute('y', String(Math.floor(b.y - pad))); r.setAttribute('width', String(Math.ceil(b.width + pad * 2))); r.setAttribute('height', String(Math.ceil(b.height + pad * 2))); }
    r.setAttribute('fill', '#FFFFFF');
    r.setAttribute('data-ge-knock', '1');
    def.setAttribute('maskUnits', 'userSpaceOnUse');
    if (b) { def.setAttribute('x', r.getAttribute('x')); def.setAttribute('y', r.getAttribute('y')); def.setAttribute('width', r.getAttribute('width')); def.setAttribute('height', r.getAttribute('height')); }
    def.append(r);
  }
  if (kind === 'clip' && ['g', 'switch', 'svg'].includes(top.localName)) {
    // clipPath darf keine Gruppen enthalten: Formen einzeln mit ihrer Lage übernehmen
    const Pi = parentMatrix(top, root).inverse();
    for (const s of top.querySelectorAll(SHAPE_SEL)) {
      if (s.closest('defs,clipPath,mask,marker,pattern,symbol')) continue;
      const c = s.cloneNode(true);
      const m = fmtMatrix(Pi.multiply(docMatrix(s, root)));
      if (m) c.setAttribute('transform', m); else c.removeAttribute('transform');
      def.append(c);
    }
    top.remove();
  } else {
    def.append(top);
  }
  if (kind === 'mask' || kind === 'knockout') {
    // Maske: einfarbige Füllung → weiß; Ausstanzen: → schwarz (Loch). Alte Farbe merken
    const want = kind === 'mask' ? '#FFFFFF' : '#000000';
    const shapes = [...def.querySelectorAll(SHAPE_SEL)].filter(s => !s.hasAttribute('data-ge-knock'));
    for (const s of shapes) {
      const f = getProp(s, 'fill');
      if (!f || /^url/.test(f) || f === 'none') continue;
      if (f.toUpperCase() !== want) { s.setAttribute('data-ge-fill', getProp(s, 'fill', false) || f); setProp(s, 'fill', want); }
      const st = getProp(s, 'stroke');
      if (kind === 'knockout' && st && st !== 'none' && !/^url/.test(st)) { s.setAttribute('data-ge-stroke', st); setProp(s, 'stroke', want); }
    }
  }
  g.prepend(def);
  g.setAttribute(kind === 'clip' ? 'clip-path' : 'mask', `url(#${def.id})`);
  g.setAttribute('data-name', kind === 'clip' ? t('Beschnitten') : kind === 'knockout' ? t('Ausgestanzt') : t('Maskiert'));
  if (kind === 'knockout') g.setAttribute('data-ge-knockout', '1');
  return { el: g };
}

/** Beschneidung/Maske freigeben: Form wieder als Objekt über dem Inhalt, Gruppe aufgelöst (wenn sie nur dafür da war) */
export function releaseClip(root, g) {
  const c = clipOf(g, root);
  if (!c) return null;
  const shared = useCount(root, c.def.id) > 1;
  const shapes = [...c.def.children].filter(s => !s.hasAttribute('data-ge-knock'));
  const out = [];
  let after = g;
  for (const s of shapes) {
    const n = shared ? s.cloneNode(true) : s;
    if (n.hasAttribute('data-ge-fill')) { setProp(n, 'fill', n.getAttribute('data-ge-fill')); n.removeAttribute('data-ge-fill'); }
    if (n.hasAttribute('data-ge-stroke')) { setProp(n, 'stroke', n.getAttribute('data-ge-stroke')); n.removeAttribute('data-ge-stroke'); }
    // die Form galt im Raum der Gruppe – außerhalb davon: Transformation der Gruppe mitnehmen
    if (g.hasAttribute('transform')) {
      const m = fmtMatrix(parentMatrix(g, root).inverse().multiply(docMatrix(g, root)).multiply(nodeMatrix(n)));
      if (m) n.setAttribute('transform', m); else n.removeAttribute('transform');
    }
    after.after(n); after = n; out.push(n);
  }
  g.removeAttribute(c.attr);
  if (g.style?.getPropertyValue(c.attr)) { g.style.removeProperty(c.attr); if (!g.getAttribute('style')) g.removeAttribute('style'); }
  if (!shared) c.def.remove();
  g.removeAttribute('data-ge-knockout');
  const own = [...g.attributes].every(a => ['data-name'].includes(a.name));
  const kids = [...g.children];
  if (own && [t('Beschnitten'), t('Maskiert'), t('Ausgestanzt')].includes(g.getAttribute('data-name') || '')) {
    for (const k of kids) g.before(k);
    g.remove();
    return [...kids, ...out];
  }
  return [g, ...out];
}

function nodeMatrix(el) {
  const list = el.transform?.baseVal;
  const m = list && list.numberOfItems ? list.consolidate()?.matrix : null;
  return m ? new DOMMatrix([m.a, m.b, m.c, m.d, m.e, m.f]) : new DOMMatrix();
}
