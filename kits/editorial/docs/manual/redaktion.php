<?php /** Handbuch „editorial“ · Kapitel „Redaktion“ · @var string $settingsTitle */ ?>
  <p class="lead">Nachrichten, Termine und Personen pflegen Sie unter <b>Daten</b>. Jede Tabelle hat eine Detailseiten-Vorlage mit dem Block <b>Artikel</b> – so erscheint jeder Eintrag automatisch als gesetzter Artikel.</p>
  <h3>Eine Nachricht schreiben</h3>
  <ol>
    <li><b>Titel:</b> konkret, ohne Punkt am Ende. <b>Rubrik:</b> erscheint als Dachzeile und in der Rubrik-Leiste.</li>
    <li><b>Vorspann:</b> ein bis zwei Sätze – steht in Listen und groß über dem Artikel.</li>
    <li><b>Text:</b> Zwischenüberschriften (Überschrift 3) bilden das Inhaltsverzeichnis; der erste Buchstabe wird zur Initiale.</li>
    <li><b>Bild</b> mit Alt-Text und Nachweis in der Mediathek, <b>Bildunterschrift</b> optional, <b>Autor</b> aus der Tabelle „Menschen“.</li>
  </ol>
  <h3>Die Startseite als Titelseite</h3>
  <p>Die Startseite besteht aus Datenlisten: „Aufmacher + Raster“ zeigt die neuesten vier Nachrichten (hervorgehobene zuerst), „Kurz notiert“ überspringt diese vier und zeigt die nächsten. Die Reihenfolge der Abschnitte ändern Sie im Editor per Ziehen.</p>
  <h3>Rubrikseiten</h3>
  <p>Eine Rubrik beginnt mit dem <b>Aufmacher</b> in der Variante „Ressortkopf“: Unterseiten erscheinen automatisch als Reiter. Mit „Besucher filtern nach …“ in der Datenliste entsteht eine Rubrik-Leiste, die ohne JavaScript funktioniert.</p>
  <h3>Geteilte Tabellen (Verband und Vereine)</h3>
  <p>Nutzt Ihre Website geteilte Tabellen einer anderen Website (<?= e(term('shared_owner')) ?>), zeigen Datenlisten fremde Einträge mit „von …“. Welche Einträge erscheinen, bestimmt die Quelle der Tabelle bzw. das Feld „Quelle“ im Block.</p>
