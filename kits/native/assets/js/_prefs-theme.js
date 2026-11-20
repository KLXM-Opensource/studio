/*
 * Teil von ncss-prefs-early.js: Darstellungs-Einstellungen der Besucher (<ncss-preferences>) auf die Klassen von fluid übertragen.
 *   data-theme="dark"     → is-dark (wie die Vorschau im Style-Editor), nur wenn das Design ein dunkles Schema erlaubt
 *   data-theme="light"    → has-dark vorübergehend weg: die dunklen Regeln für die Geräte-Einstellung greifen nicht
 *   data-motion="reduce"  → has-motion weg: keine Einblend-Animationen und kein Laufband von fluid
 */
const html = document.documentElement;
const dark = html.classList.contains('has-dark');
const motion = html.classList.contains('has-motion');
let ours = false;   // is-dark nur zurücknehmen, wenn es von hier kommt
const sync = () => {
  const theme = html.getAttribute('data-theme');
  if (dark) {
    if (theme === 'dark') {
      ours = ours || !html.classList.contains('is-dark');
      html.classList.add('is-dark');
    } else if (ours) {
      html.classList.remove('is-dark');
      ours = false;
    }
    html.classList.toggle('has-dark', theme !== 'light');
  }
  if (motion) html.classList.toggle('has-motion', html.getAttribute('data-motion') !== 'reduce');
};
sync();
new MutationObserver(sync).observe(html, { attributes: true, attributeFilter: ['data-theme', 'data-motion'] });
