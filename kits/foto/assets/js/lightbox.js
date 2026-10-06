/* Lightbox des Kits „foto“ (≈ 1,5 KB, ohne Abhängigkeiten) – nur auf Seiten mit Fotostrecke bzw. Bild & Text.
   Ohne JavaScript führen die Bilder als Links zur großen Fassung. Links: a[data-lb] (href, data-srcset, data-w/h, data-caption),
   gruppiert über [data-lb-group]; Videos: a[data-lb-video] mit <template> (foto_video). Dialog: foto_lightbox() (dialog.flb).
   Pfeiltasten (nicht im Video), Pos1/Ende, Escape (nativ, hält das Video an), Wischen,
   Nachbarbilder vorladen, Fokus zurück zum Bild. */
{
const dlg = document.querySelector('dialog.flb');
if (dlg) {
  const img = dlg.querySelector('.flb__img');
  const text = dlg.querySelector('.flb__text');
  const count = dlg.querySelector('.flb__count');
  const tpl = dlg.dataset.count || '{n} / {total}';
  const fig = dlg.querySelector('.flb__fig');
  let list = [], i = 0, opener = null, x0 = null, media = null;
  // Video (a[data-lb-video] → <template> mit <video controls>, Untertiteln, Transkript): beim Blättern und Schließen anhalten
  const clearVideo = () => { if (media) { media.querySelectorAll('video').forEach(v => v.pause()); media.remove(); media = null; } img.hidden = false; };
  const load = (el, a) => {
    el.removeAttribute('srcset');
    if (a.dataset.srcset) { el.sizes = '100vw'; el.srcset = a.dataset.srcset; }
    el.src = a.href;
  };
  const show = k => {
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
    img.alt = a.querySelector('img')?.alt || '';
    if (a.dataset.w) { img.width = +a.dataset.w; img.height = +a.dataset.h; }
    text.textContent = a.dataset.caption || '';
    count.textContent = list.length > 1 ? tpl.replace('{n}', i + 1).replace('{total}', list.length) : '';
    dlg.classList.toggle('is-single', list.length < 2);
    [1, -1].forEach(d => { const n = list[(i + d + list.length) % list.length]; if (n !== a && !n.dataset.lbVideo) load(new Image(), n); });
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
  });
  dlg.addEventListener('click', e => {
    const step = e.target.closest('[data-flb-step]');
    if (step) show(i + Number(step.dataset.flbStep));
    else if (e.target.closest('[data-flb-close]') || e.target === dlg || e.target.classList.contains('flb__fig')) dlg.close();
  });
  dlg.addEventListener('keydown', e => {
    if (e.target.closest && e.target.closest('video,details,summary')) return;   // Pfeiltasten im Video: Spulen/Lautstärke
    const k = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: list.length - 1 }[e.key];
    if (k !== undefined && list.length > 1) { e.preventDefault(); show(k); }
  });
  dlg.addEventListener('pointerdown', e => { x0 = e.pointerType === 'mouse' || e.target.closest('video') ? null : e.clientX; });
  dlg.addEventListener('pointerup', e => {
    if (x0 === null) return;
    const dx = e.clientX - x0;
    x0 = null;
    if (Math.abs(dx) > 48 && list.length > 1) show(i + (dx < 0 ? 1 : -1));
  });
  dlg.addEventListener('close', () => { clearVideo(); img.removeAttribute('srcset'); img.removeAttribute('src'); opener?.focus(); });
}
}
