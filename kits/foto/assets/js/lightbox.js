/* Lightbox des Kits „foto“ (≈ 1,5 KB, ohne Abhängigkeiten) – nur auf Seiten mit Fotostrecke bzw. Bild & Text.
   Ohne JavaScript führen die Bilder als Links zur großen Fassung. Links: a[data-lb] (href, data-srcset, data-w/h, data-caption),
   gruppiert über [data-lb-group]; Videos: a[data-lb-video] mit <template> (foto_video). Dialog: foto_lightbox() (dialog.flb).
   Pfeiltasten (nicht im Video), Pos1/Ende, Escape (nativ, hält das Video an), Wischen (folgt dem Finger),
   Nachbarbilder vorladen, Fokus zurück zum Bild.
   Bewegung (nur mit „Animationen“, nie bei „Bewegung reduzieren“): Öffnen zoomt aus dem Vorschaubild, Schließen zurück;
   Blättern gleitet mit Überblendung in Richtung des Wechsels (Web Animations, keine Inline-Stile im HTML). */
{
const dlg = document.querySelector('dialog.flb');
if (dlg) {
  const img = dlg.querySelector('.flb__img');
  const text = dlg.querySelector('.flb__text');
  const count = dlg.querySelector('.flb__count');
  const tpl = dlg.dataset.count || '{n} / {total}';
  const fig = dlg.querySelector('.flb__fig');
  let list = [], i = 0, opener = null, x0 = null, dx = 0, media = null;
  const motion = () => document.documentElement.classList.contains('has-motion') && !matchMedia('(prefers-reduced-motion: reduce)').matches;
  const EASE = 'cubic-bezier(.2,.75,.15,1)';
  const thumb = a => a?.querySelector('img');
  // Vom Vorschaubild zur Großansicht (bzw. zurück): Verschiebung + Skalierung zwischen den beiden Rechtecken
  const zoom = (a, back) => {
    const t = thumb(a);
    if (!motion() || !t || img.hidden) return null;
    const from = t.getBoundingClientRect(), to = img.getBoundingClientRect();
    if (!from.width || !to.width) return null;
    const k = from.width / to.width;
    const tf = `translate(${from.left + from.width / 2 - (to.left + to.width / 2)}px, ${from.top + from.height / 2 - (to.top + to.height / 2)}px) scale(${k})`;
    const frames = [{ transform: tf, opacity: .6 }, { transform: 'none', opacity: 1 }];
    dlg.animate([{ backgroundColor: 'transparent' }, {}], { duration: back ? 280 : 420, easing: 'ease', direction: back ? 'reverse' : 'normal', fill: 'both' });
    return img.animate(back ? frames.reverse() : frames, { duration: back ? 300 : 460, easing: EASE, fill: back ? 'forwards' : 'none' });
  };
  // Blättern: altes Bild gleitet hinaus (Kopie), neues herein
  const slide = dir => {
    if (!motion() || img.hidden || !img.currentSrc) return;
    const r = img.getBoundingClientRect();
    const ghost = img.cloneNode();
    ghost.removeAttribute('class');
    ghost.className = 'flb__ghost';
    Object.assign(ghost.style, { left: r.left + 'px', top: r.top + 'px', width: r.width + 'px', height: r.height + 'px' });
    dlg.append(ghost);
    ghost.animate([{ transform: `translateX(${dx}px)`, opacity: 1 }, { transform: `translateX(${-dir * 18}%) scale(.96)`, opacity: 0 }], { duration: 420, easing: EASE, fill: 'forwards' })
      .finished.finally(() => ghost.remove());
  };
  const enter = dir => {
    if (!motion() || img.hidden) return;
    img.animate([{ transform: `translateX(${dir * 14}%) scale(1.02)`, opacity: 0, filter: 'blur(6px)' }, { transform: 'none', opacity: 1, filter: 'none' }], { duration: 520, easing: EASE });
  };
  // Video (a[data-lb-video] → <template> mit <video controls>, Untertiteln, Transkript): beim Blättern und Schließen anhalten
  const clearVideo = () => { if (media) { media.querySelectorAll('video').forEach(v => v.pause()); media.remove(); media = null; } img.hidden = false; };
  const load = (el, a) => {
    el.removeAttribute('srcset');
    if (a.dataset.srcset) { el.sizes = '100vw'; el.srcset = a.dataset.srcset; }
    el.src = a.href;
  };
  const show = (k, dir = 0) => {
    if (dir) slide(dir);
    i = (k + list.length) % list.length;
    const a = list[i];
    clearVideo();
    const vt = a.dataset.lbVideo && document.getElementById(a.dataset.lbVideo);
    if (vt) {
      img.hidden = true; img.removeAttribute('src'); img.removeAttribute('srcset');
      media = document.createElement('div');
      media.className = 'flb__media';
      media.append(vt.content.cloneNode(true));
      fig.insertBefore(media, img);
      const v = media.querySelector('video');
      if (v) { v.focus({ preventScroll: true }); v.play().catch(() => {}); }   // Klick auf „Abspielen“ = Nutzeraktion; Ton wie in der Datei
    } else load(img, a);
    img.alt = a.dataset.alt || a.querySelector('img')?.alt || '';
    if (a.dataset.w) { img.width = +a.dataset.w; img.height = +a.dataset.h; }
    text.textContent = a.dataset.caption || '';
    count.textContent = list.length > 1 ? tpl.replace('{n}', i + 1).replace('{total}', list.length) : '';
    dlg.classList.toggle('is-single', list.length < 2);
    [1, -1].forEach(d => { const n = list[(i + d + list.length) % list.length]; if (n !== a && !n.dataset.lbVideo) load(new Image(), n); });
    if (dir) enter(dir);
    dx = 0;
  };
  document.addEventListener('click', e => {
    const a = e.target.closest?.('a[data-lb]');
    if (!a || e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    const group = a.closest('[data-lb-group]');
    list = group ? [...group.querySelectorAll('a[data-lb]')] : [a];
    opener = a;
    show(list.indexOf(a));
    dlg.showModal();
    if (motion() && !img.hidden) (img.complete ? Promise.resolve() : img.decode().catch(() => {})).then(() => zoom(a));
  });
  dlg.addEventListener('click', e => {
    const step = e.target.closest('[data-flb-step]');
    if (step) show(i + Number(step.dataset.flbStep), Number(step.dataset.flbStep));
    else if (e.target.closest('[data-flb-close]') || e.target === dlg || e.target.classList.contains('flb__fig')) close();
  });
  dlg.addEventListener('keydown', e => {
    if (e.target.closest && e.target.closest('video,details,summary')) return;   // Pfeiltasten im Video: Spulen/Lautstärke
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    const k = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: list.length - 1 }[e.key];
    if (k !== undefined && list.length > 1) { e.preventDefault(); show(k, k > i ? 1 : -1); }
  });
  // Schließen: zurück ins Vorschaubild zoomen, wenn es zu sehen ist
  const close = () => {
    const a = list[i], r = thumb(a)?.getBoundingClientRect();
    const anim = r && r.bottom > 0 && r.top < innerHeight ? zoom(a, true) : null;
    if (anim) anim.finished.then(() => { dlg.close(); anim.cancel(); }, () => dlg.close()); else dlg.close();
    if (a && a !== opener) opener = a;   // Fokus auf das zuletzt gezeigte Bild
  };
  // Wischen: das Bild folgt dem Finger, ab 48 px wird geblättert, sonst federt es zurück
  dlg.addEventListener('pointerdown', e => { x0 = e.pointerType === 'mouse' || e.target.closest('video') ? null : e.clientX; dx = 0; });
  dlg.addEventListener('pointermove', e => {
    if (x0 === null || list.length < 2) return;
    dx = e.clientX - x0;
    if (motion()) img.style.transform = `translateX(${dx}px)`;
  });
  dlg.addEventListener('pointerup', e => {
    if (x0 === null) return;
    x0 = null;
    img.style.transform = '';
    if (Math.abs(dx) > 48 && list.length > 1) show(i + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1);
    else if (dx && motion()) img.animate([{ transform: `translateX(${dx}px)` }, { transform: 'none' }], { duration: 300, easing: EASE });
    dx = 0;
  });
  dlg.addEventListener('close', () => { clearVideo(); img.removeAttribute('srcset'); img.removeAttribute('src'); opener?.focus(); });
}
}
