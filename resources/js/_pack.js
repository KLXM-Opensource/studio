/*
 * Große Formulare gebündelt senden (Core\Http\Request::unpack)
 * PHP verwirft bei mehr als max_input_vars Feldern (Standard 1000) still den Rest – z. B. bei „Felder & Einstellungen“ einer
 * großen Datentabelle, Kit-Einstellungen mit Wiederholungen oder dem Rollen-Editor. Beim Absenden packt dieses Skript alle Felder
 * eines solchen Formulars als JSON-Liste [[name, wert], …] in ein einziges Feld „_packed“; der Server baut daraus wieder die
 * gewohnte Struktur. Dateien bleiben echte Felder. Ohne JavaScript erkennt der Server den Überlauf und speichert nichts.
 * Gilt für alle POST-Formulare mit mehr Feldern als die halbe Grenze (<body data-max-vars>); kleine Formulare bleiben unverändert.
 */
const PACKED = '_packed';

export function initPack(d = document) {
  const limit = parseInt(d.body?.dataset.maxVars || '1000', 10) || 1000;
  const threshold = Math.max(10, Math.floor(limit / 2));
  // window (Bubbling, ganz zuletzt): Skripte, die das Absenden selbst übernehmen (preventDefault), haben dann schon entschieden
  window.addEventListener('submit', (e) => {
    const form = e.target;
    if (e.defaultPrevented || !(form instanceof HTMLFormElement) || form.hasAttribute('data-no-pack')) return;
    if ((e.submitter?.getAttribute('formmethod') || form.method || 'get').toLowerCase() !== 'post') return;
    let fd;
    try { fd = new FormData(form, e.submitter || null); } catch { fd = new FormData(form); }
    const pairs = [];
    let count = 0;
    for (const [k, v] of fd) { count++; if (typeof v === 'string') pairs.push([k, v]); }
    if (count <= threshold) return;
    // Benannte Felder (außer Dateien) für diesen Absendevorgang ausschalten – die Liste der Formulardaten entsteht erst nach dem
    // submit-Ereignis; danach (und bei Rückkehr per Zurück-Taste) wieder einschalten
    const off = [...form.elements].filter((el) => el.name && !el.disabled && el.type !== 'file');
    if (e.submitter?.name && !off.includes(e.submitter)) off.push(e.submitter);
    off.forEach((el) => { el.disabled = true; });
    let box = form.querySelector(`input[name="${PACKED}"]`);
    if (!box) { box = d.createElement('input'); box.type = 'hidden'; box.name = PACKED; form.append(box); }
    box.disabled = false;
    box.value = JSON.stringify(pairs);
    const restore = () => { off.forEach((el) => { el.disabled = false; }); box.remove(); };
    setTimeout(restore, 0);
    window.addEventListener('pageshow', restore, { once: true });
  });
}
