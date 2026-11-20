/*
 * Kit „NativeCSS“ – Seitenrahmen: Menü als <dialog> (Kopfleiste). Ohne Skript funktioniert es in aktuellen Browsern über
 * command/commandfor; die drei Dateien von NativeCSS ergänzen
 *   command-fallback.js      command="show-modal|close" für Browser ohne Invoker Commands
 *   dialog-light-dismiss.js  „Klick daneben schließt“ (closedby="any" bzw. Nachbildung)
 *   nav.js                   Zeile oder Menü-Schaltfläche je nach Platz (data-ncss-nav-auto, kein fester Breakpoint), aria-expanded,
 *                            schließt Untermenü und Menü nach Klick auf einen Link (z. B. Anker auf derselben Seite)
 * Unverändert aus vendor/ncss/js (MIT).
 */
import '../../vendor/ncss/js/command-fallback.js';
import '../../vendor/ncss/js/dialog-light-dismiss.js';
import '../../vendor/ncss/js/nav.js';
