/**
 * Aufgaben (Core\Tasks): „Aufgabe anlegen“ öffnet den Dialog auf der Seite ([data-task-open="{id}"] → <dialog id>), statt die
 * eigene Seite /admin/aufgaben/neu zu laden. Ohne JavaScript bleibt der Link; Abbrechen schließt per formmethod="dialog".
 * Nach dem Öffnen steht der Fokus im Titel.
 */
export function initTasks() {
  document.addEventListener('click', (e) => {
    const a = e.target.closest?.('[data-task-open]');
    if (!a) return;
    const dlg = document.getElementById(a.dataset.taskOpen);
    if (!(dlg instanceof HTMLDialogElement) || typeof dlg.showModal !== 'function') return;
    e.preventDefault();
    dlg.showModal();
    dlg.querySelector('input[name="title"]')?.focus();
  });
}
