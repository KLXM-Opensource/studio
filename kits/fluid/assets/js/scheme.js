/* Kit „fluid“ – Hell/Dunkel-Schalter (design: dark_toggle). Im <head> geladen (nicht defer): setzt die gewählte Darstellung
   (scheme-dark / scheme-light am <html>) vor dem ersten Zeichnen – kein Aufblitzen. Ohne Wahl gilt die Einstellung des Geräts.
   Der Knopf im Kopf (.scheme-btn) wechselt; gemerkt wird nur im Browser (localStorage „f-scheme“). */
{
const html = document.documentElement, key = 'f-scheme', mq = matchMedia('(prefers-color-scheme: dark)');
let pick = null;
try { pick = localStorage.getItem(key); } catch {}
const apply = () => {
  html.classList.toggle('scheme-dark', pick === 'dark');
  html.classList.toggle('scheme-light', pick === 'light');
  const dark = pick ? pick === 'dark' : mq.matches;
  document.querySelectorAll('[data-scheme-toggle]').forEach(b => {
    b.setAttribute('aria-pressed', String(dark));
    b.setAttribute('aria-label', dark ? b.dataset.labelLight : b.dataset.labelDark);
    b.title = b.getAttribute('aria-label');
  });
};
apply();
mq.addEventListener('change', apply);
document.addEventListener('DOMContentLoaded', apply);
document.addEventListener('click', e => {
  const b = e.target.closest?.('[data-scheme-toggle]');
  if (!b) return;
  const dark = !(pick ? pick === 'dark' : mq.matches);
  pick = dark === mq.matches ? null : (dark ? 'dark' : 'light');   // wie das Gerät → keine eigene Wahl mehr speichern
  try { pick ? localStorage.setItem(key, pick) : localStorage.removeItem(key); } catch {}
  html.classList.add('scheme-switching');
  apply();
  setTimeout(() => html.classList.remove('scheme-switching'), 400);
});
}
