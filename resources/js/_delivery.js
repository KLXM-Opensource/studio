/*
 * Eingangs-Tabelle → „Zustellung der Anfragen“ und Bereich „Verschlüsselung“ (Core\Data\Delivery): Abschnitte je Modus ein-/ausblenden,
 * Weiterleitung je Auswahlfeld, S/MIME-Zertifikat aus einer Datei (PEM oder DER) ins Textfeld übernehmen.
 * Ohne JavaScript bleibt alles sichtbar; die Prüfung macht der Server.
 */
export function initDelivery(root = document) {
  root.querySelectorAll('[data-delivery]').forEach(box => {
    // Modus: Wahl im Bereich „Verschlüsselung“ (settings[protection]: none/system/both/mail) – gilt für alle Boxen im Formular
    const scope = box.closest('form') || box;
    const modes = [...scope.querySelectorAll('input[type=radio][name="settings[protection]"], input[type=radio][name$="[delivery][mode]"]')];
    const sync = () => {
      const m = modes.find(r => r.checked)?.value || box.dataset.deliveryMode || 'system';
      box.querySelectorAll('[data-delivery-when]').forEach(el => { el.hidden = !el.dataset.deliveryWhen.split(' ').includes(m); });
    };
    modes.forEach(r => r.addEventListener('change', sync));
    sync();

    const rf = box.querySelector('[data-route-field]');
    const routes = () => box.querySelectorAll('[data-route-for]').forEach(el => {
      const on = rf && el.dataset.routeFor === rf.value;
      el.hidden = !on;
      el.querySelectorAll('input').forEach(i => { i.disabled = !on; });
    });
    rf?.addEventListener('change', routes);
    if (rf) routes();

    const file = box.querySelector('[data-pem-file]');
    const target = box.querySelector('[data-pem-target]');
    file?.addEventListener('change', async () => {
      const f = file.files?.[0];
      if (!f || !target) return;
      const buf = new Uint8Array(await f.arrayBuffer());
      const text = new TextDecoder().decode(buf);
      if (text.includes('-----BEGIN')) {
        target.value = text.trim();
      } else {
        let bin = '';
        buf.forEach(b => { bin += String.fromCharCode(b); });
        target.value = '-----BEGIN CERTIFICATE-----\n' + btoa(bin).replace(/(.{64})/g, '$1\n').trim() + '\n-----END CERTIFICATE-----';
      }
      box.querySelector('[data-delivery-nosmime]')?.setAttribute('hidden', '');
    });
  });
}
