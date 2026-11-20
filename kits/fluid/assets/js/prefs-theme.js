/* Kit „fluid“ – Darstellungs-Knopf für Besucher: Wahl im Bedienfeld (<ncss-preferences>, Attribute am <html> aus
   js/preferences-early.js bzw. js/preferences.js) auf die Klassen von fluid übertragen. Synchron im <head> direkt nach
   preferences-early.js, vor den Stylesheets – nichts blitzt auf.
     data-theme="dark"    → scheme-dark  (dunkle Design-Werte, wie der Hell/Dunkel-Schalter; nur mit „Dunkles Farbschema“)
     data-theme="light"   → scheme-light (helle Werte, auch wenn das Gerät dunkel eingestellt ist)
     data-motion="reduce" → has-motion weg (keine Einblend-Animationen, kein Laufband von fluid)
   Ohne Wahl bleiben die Klassen, wie sie das Design setzt. Farbschema nur mit data-scheme am <script> (das Bedienfeld bietet es
   an – nicht, wenn der Hell/Dunkel-Schalter des Kits im Kopf steht, js/scheme.js). */
{
const html = document.documentElement;
const dark = html.classList.contains('has-dark') && document.currentScript?.hasAttribute('data-scheme');
const motion = html.classList.contains('has-motion');
const sync = () => {
  const t = html.getAttribute('data-theme');
  if (dark) {
    html.classList.toggle('scheme-dark', t === 'dark');
    html.classList.toggle('scheme-light', t === 'light');
  }
  if (motion) html.classList.toggle('has-motion', html.getAttribute('data-motion') !== 'reduce');
};
sync();
new MutationObserver(sync).observe(html, { attributes: true, attributeFilter: ['data-theme', 'data-motion'] });
}
