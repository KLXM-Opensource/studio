/*
 * Wiederholbare Gruppen (z. B. mehrere Medikamente) – lädt site.js erst beim ersten Fokus in ein Formular mit Gruppe,
 * damit die Startseite im JS-Budget bleibt.
 */
import { groups } from '../../../../resources/js/_group.js';

document.querySelectorAll('form[data-form]').forEach(f => { if (!f._groups) { f._groups = 1; groups(f); } });
