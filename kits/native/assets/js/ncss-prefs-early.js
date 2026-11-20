/*
 * Darstellungs-Knopf für Besucher (Design → „Darstellungs-Knopf für Besucher“): synchron im <head> vor den Stylesheets –
 * gespeicherte Wahl als Attribute am <html> (preferences-early.js von NativeCSS, unverändert), danach auf die Klassen von
 * fluid übertragen (_prefs-theme.js). Kein Aufblitzen in falscher Schriftgröße oder Farbe.
 */
import '../../vendor/ncss/js/preferences-early.js';
import './_prefs-theme.js';
