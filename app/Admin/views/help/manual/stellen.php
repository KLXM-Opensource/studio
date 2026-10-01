<?php /** Handbuch · Kapitel „Stellenangebote & Google for Jobs“ (Core\Data\Jobs; Variablen: siehe help/manual.php) */ ?>
  <p class="lead">Offene Stellen pflegen Sie als Einträge der Tabelle <b>„Stellenangebote“</b>. Jede Stelle bekommt eine eigene Seite mit Eckdaten, Beschreibung und Bewerbungsformular – und die Angaben, die Google für die Stellensuche (<b>Google for Jobs</b>) braucht, entstehen automatisch.</p>
  <h3>Einrichten (einmal, Administration)</h3>
  <ol class="doc-steps">
    <li><b>Daten → „Weitere Tabelle aus Vorlage“ → Stellenangebote.</b> Adresse prüfen (z. B. <code>stellen</code> → Stellen unter <code>/stellen/…</code>), „Detailseiten-Vorlage gleich mit anlegen“ angehakt lassen, bei „Bewerbungsformular“ den Eingang <b>„Bewerbungen“</b> anlegen lassen bzw. einen vorhandenen wählen. Speichern.</li>
    <li><b>Bewerbungen → Felder &amp; Einstellungen → Zustellung:</b> Empfänger-Adresse eintragen (Standard: „Nur per E-Mail – nicht im System speichern“, Lebenslauf und Unterlagen als Anhang; PDF, Word, OpenDocument). „Testmail senden“.</li>
    <li>Optional: eine Seite „Stellenangebote“ mit dem Block <b>„Datenliste“</b> (Tabelle Stellenangebote) – zeigt nur laufende Stellen; mit „Abschnitt ausblenden, wenn nichts da ist“ verschwindet der Abschnitt, solange nichts ausgeschrieben ist.</li>
  </ol>
  <h3>Eine Stelle ausschreiben</h3>
  <ul>
    <li><b>Titel:</b> nur die Berufsbezeichnung, z. B. „Medizinische Fachangestellte (m/w/d)“ – ohne „Wir suchen“, Ort oder Gehalt.</li>
    <li><b>Beschreibung:</b> die vollständige Anzeige (Aufgaben, Profil, Angebot, Kontakt) mit Zwischenüberschriften und Listen. Google zeigt genau diesen Text.</li>
    <li><b>Veröffentlicht am</b> (heute vorbelegt) und <b>Gültig bis</b>: Ab dem Tag nach „Gültig bis“ verschwindet die Stelle aus Listen, Sitemap und Google; ihre Seite bleibt erreichbar und sagt „Diese Stelle ist nicht mehr ausgeschrieben“ (ohne Formular, nicht mehr in Suchmaschinen). Ohne Datum läuft die Stelle bis auf Weiteres – dann bei Besetzung auf <b>Entwurf</b> stellen.</li>
    <li><b>Beschäftigungsart</b> (mehrere möglich), <b>Beginn</b>, <b>Arbeitsweise</b> (Vor Ort, mit Homeoffice, vollständig remote), <b>Arbeitsort</b> (leer = Adresse der Website), <b>Gehalt</b> (optional – Google zeigt Stellen mit Gehalt bevorzugt an), Ansprechperson.</li>
    <li>Rechts im Eintrag zeigt <b>„Google for Jobs“</b>, ob alle Pflichtangaben vorhanden sind.</li>
  </ul>
  <h3>Bewerbungen</h3>
  <p>Auf jeder Stellenseite steht das Bewerbungsformular. Das Feld <b>„Stelle“</b> ist dort schon ausgefüllt und gesperrt, der Betreff der E-Mail nennt die Stelle. Dasselbe Formular lässt sich mit dem Block „Formular (Datentabelle)“ auch für Initiativbewerbungen einsetzen – dann tragen Bewerbende die Stelle selbst ein.</p>
  <div class="doc-note doc-note--tip"><strong>Checkliste Google for Jobs</strong>
    <ul>
      <li><b>Google Search Console</b> für die Domain einrichten und die Sitemap (<code>/sitemap.xml</code>) einreichen; unter „Verbesserungen → Stellenanzeigen“ erscheinen gefundene Stellen und Fehler.</li>
      <li>Neue Stelle mit dem <b>Test für Rich-Suchergebnisse</b> (search.google.com/test/rich-results) prüfen: Adresse der Stelle eingeben → „Stellenanzeige“ gültig.</li>
      <li><b>„Gültig bis“ aktuell halten</b> bzw. besetzte Stellen sofort auf Entwurf stellen – Google straft abgelaufene Anzeigen ab.</li>
      <li>Gehalt angeben, wo möglich (Transparenz; Google und Bewerbende filtern danach).</li>
      <li>Die Website muss für Suchmaschinen freigegeben sein (Umgebung „production“) – Vorschau-/Staging-Umgebungen sind gesperrt.</li>
    </ul></div>
