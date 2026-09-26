<?php /** Handbuch · Kapitel „Kalkulation & Angebote“ (Erweiterung kalkulation, eingebunden über Extension::docs('manual') – nur für Berechtigte) */ ?>
  <p class="lead">Unter <a href="<?= e(url('/admin/kalkulation')) ?>"><b>Kalkulation</b></a> rechnen Sie Preise intern durch und geben sie als <b>Angebot</b> aus – mit Stundensätzen, Aufschlägen, Leistungskatalog, Marge und effektivem Stundensatz. Nichts davon erscheint auf der Website, in der API, im MCP oder in der Suche. Sehen darf es nur, wer das Recht <b>„Kalkulationen, Leistungskatalog und Einstellungen verwalten“</b> hat (Administration immer).</p>

  <h3>Einrichten (einmalig)</h3>
  <ol class="doc-steps">
    <li><b>Einstellungen</b>: Stundensätze eintragen (z. B. Beratung/Konzept/Design, Entwicklung, Support/Betrieb – umbenennen und ergänzen möglich), Aufschlag auf Fremdkosten, Projektpuffer, Umsatzsteuer (19 % vorbelegt), Rundung der Einzelpreise, Nummernkreis und Standardtexte für Angebote.</li>
    <li><b>Leistungskatalog</b>: Die Leistungen und Pakete sind aus der bisherigen Arbeitsmappe übernommen – <b>ohne Beträge</b> und als „Beispiel“ markiert. Je Paket Einrichtungsstunden bzw. Festpreis, Fremdkosten, monatliche Infrastruktur-/Lizenzkosten und Betreuungsstunden eintragen, dann die Markierung „Beispiel“ entfernen.</li>
  </ol>

  <h3>Kalkulation erstellen</h3>
  <ol class="doc-steps">
    <li><b>Neue Kalkulation</b> – Nummer, Datum und Gültigkeit werden vorgeschlagen, Sätze und Aufschläge aus den Einstellungen übernommen (die <b>Grundlage</b> bleibt je Kalkulation fest; spätere Änderungen der Einstellungen ändern alte Kalkulationen nicht).</li>
    <li>Titel, Kunde, Anschrift und Projekt eintragen.</li>
    <li><b>Aus Katalog …</b> fügt Pakete hinzu: Pakete mit Einrichtung und Betrieb ergeben zwei Positionen (einmalig und monatlich). <b>+ Position</b> legt eine freie Position an.</li>
    <li>Je Position: <b>Menge</b>, <b>Einheit</b>, <b>Preisbasis</b> (ein Stundensatz → Stunden je Einheit, oder <b>Festpreis</b> → Preis je Einheit), <b>Fremdkosten</b> je Einheit (Einkauf, erhält den Aufschlag), <b>Rabatt %</b>. Unter <b>⋯</b>: Gruppe, Beschreibung fürs Angebot, Projektpuffer, <b>optional</b> bzw. <b>Alternative</b> (erscheinen im Angebot, zählen nicht zur Summe), interne Notiz und die Rechnung der Position.</li>
    <li>Rechts unten und in <b>Summen</b> stehen live: einmalig netto, monatlich netto, jährlich, USt und brutto; Nachlass je Art ist möglich. <b>Intern: Kosten &amp; Marge</b> zeigt Fremdkosten, Stunden, Marge in € und % und den effektiven Stundensatz.</li>
  </ol>
  <p>Gespeichert wird <b>automatisch</b> kurz nach jeder Änderung; die Leiste unten zeigt „Ungespeichert“, „Speichert …“ bzw. „Gespeichert“. Ungültige Zahlen werden rot markiert und nicht gespeichert. Zahlen dürfen deutsch eingegeben werden (<code>1.234,50</code>).</p>
  <p>Tastatur: <kbd>Tab</kbd> springt durch die Felder, <kbd>Enter</kbd> in der letzten Zeile legt eine neue Position an (sonst nächste Zeile, <kbd>⇧</kbd>+<kbd>Enter</kbd> vorige), <kbd>Strg</kbd>/<kbd>⌘</kbd>+<kbd>Enter</kbd> neue Position, <kbd>Alt</kbd>+<kbd>↑</kbd>/<kbd>↓</kbd> verschiebt, <kbd>Strg</kbd>/<kbd>⌘</kbd>+<kbd>S</kbd> speichert sofort.</p>

  <h3>Rechenregeln</h3>
  <ul>
    <li>Einzelpreis = Stunden × Satz (bzw. Festpreis) + Projektpuffer (nur bei „Puffer“) + Fremdkosten × (1 + Aufschlag), danach gerundet nach der Einstellung.</li>
    <li>Gesamt = Menge × Einzelpreis − Rabatt. Summen je Art abzüglich Nachlass = netto; USt auf netto; jährlich = monatlich × 12.</li>
    <li>Marge = Umsatz − Fremdkosten (mit „Selbstkosten je Stunde“ zusätzlich − Stunden × Selbstkosten). Effektiver Stundensatz = (Umsatz − Fremdkosten) ÷ Stunden. Bei Festpreisen zählen die „Std. intern“.</li>
  </ul>

  <h3>Kundenansicht, Angebot, CSV</h3>
  <p><b>Kundenansicht</b> blendet alle internen Spalten aus und zeigt die Positionen so, wie der Kunde sie sieht. <b>Angebot / PDF</b> öffnet die Druckansicht (A4) mit Kopf aus den zentralen Angaben der Website (oder dem Absender aus den Einstellungen) – im Druckdialog „Als PDF speichern“ wählen. <b>CSV</b> gibt die Positionen für Tabellenprogramme aus, wahlweise mit internen Spalten.</p>

  <h3>Status, Kopien, Stände</h3>
  <p>Status: Entwurf, Angeboten, Angenommen, Abgelehnt, Archiv (die Liste zeigt standardmäßig alles außer Archiv). <b>Duplizieren</b> legt eine Kopie als Entwurf an. Unter <b>Stände &amp; Protokoll</b> sichert KLXM Studio höchstens alle 15 Minuten und bei jedem Statuswechsel automatisch einen Stand; eigene Stände mit Notiz sind jederzeit möglich, jeder Stand lässt sich wiederherstellen. Arbeiten zwei Personen gleichzeitig an einer Kalkulation, meldet der Editor das und überschreibt nichts ungefragt.</p>

  <p class="doc-note">Die Kalkulation ist eine Erweiterung und muss unter <b>Administration → Funktionen &amp; Erweiterungen</b> eingeschaltet sein. Daten gehören zur jeweiligen Website. Optional lassen sich Inhalte verschlüsselt speichern (Einstellungen → Verschlüsselung).</p>
