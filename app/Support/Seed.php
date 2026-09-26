<?php
declare(strict_types=1);

namespace Core\Support;

/**
 * Startartikel der Wissensdatenbank – ausschließlich aus dem Handbuch (app/Admin/views/help/manual.php)
 * abgeleitet. Werden einmalig beim Anlegen der zentralen Datenbank eingespielt und sind danach frei änderbar.
 */
final class Seed
{
    public static function run(): void
    {
        $db = Support::db();
        if ((int) $db->fetchValue('SELECT COUNT(*) FROM articles') > 0) return;
        $now = now();
        foreach (self::articles() as [$title, $tags, $body]) {
            $tagStr = Support::tagString(Support::tags($tags));
            $id = $db->insert('articles', ['title' => $title, 'body' => $body, 'tags' => $tagStr, 'visibility' => 'all', 'sites' => '', 'status' => 'published',
                'author_key' => 'system:0', 'author_name' => 'Handbuch', 'editor_key' => 'system:0', 'editor_name' => 'Handbuch',
                'created_at' => $now, 'updated_at' => $now]);
            $db->insert('article_revisions', ['article_id' => $id, 'title' => $title, 'body' => $body, 'tags' => $tagStr, 'visibility' => 'all', 'sites' => '',
                'editor_key' => 'system:0', 'editor_name' => 'Handbuch', 'note' => 'Aus dem Handbuch übernommen', 'created_at' => $now]);
            Search::index('article', $id, $title, $body, $tagStr);
        }
    }

    /** @return list<array{string,string,string}> Titel, Tags, Text (Markdown-lite) */
    private static function articles(): array
    {
        return [
            ['Alt-Texte für Bilder richtig setzen', 'medien, barrierefreiheit, bilder', <<<'MD'
Jedes Bild auf der Website braucht einen **Alt-Text** – eine kurze Beschreibung für Menschen, die das Bild nicht sehen (z. B. mit Screenreader). Ohne Alt-Text startet der Upload in der Mediathek nicht.

### So geht’s
1. Beim **Hochladen** unter *Medien* für jedes Bild den Alt-Text eintragen.
2. In einem Satz beschreiben, was zu sehen ist – z. B. „Zwei Personen im Gespräch am Empfang“.
3. Rein schmückende Bilder als **dekorativ** markieren – dann ist kein Alt-Text nötig.
4. Später ändern: Datei in der Mediathek anklicken und rechts in der Seitenleiste den Alt-Text anpassen – gespeichert wird automatisch.

### Fehlende Alt-Texte finden
Links in der Mediathek zeigt **„Ohne Alt-Text“**, wo Beschreibungen fehlen (der Eintrag erscheint nur, wenn es welche gibt).

### Merksätze
- Jedes Bild mit Alt-Text, klare Überschriften.
- Links sagen, wohin sie führen („Preisliste herunterladen“ statt „hier“).
MD],
            ['Ich habe gespeichert, aber Besucher sehen die Änderung nicht', 'veröffentlichen, entwurf, seiten', <<<'MD'
**Speichern** sichert einen **Entwurf**. Besucher sehen weiterhin den alten Stand; Sie selbst sehen den Entwurf, solange Sie angemeldet sind.

### Lösung
1. Seite im Editor öffnen und auf **Veröffentlichen** klicken – erst dann ist die Änderung online.
2. In der Seitenübersicht zeigt „Änderungen offen“, wo noch etwas unveröffentlicht ist.
3. Außerhalb des Bearbeitungsmodus schaltet **Entwurf | Live** oben in der Leiste um: *Entwurf* zeigt Ihren Arbeitsstand, *Live* genau das, was Besucher gerade sehen.

### Gut zu wissen
- Die zentralen Angaben (z. B. Kontaktdaten) gelten dagegen **sofort** nach dem Speichern.
- Hat Ihre Rolle kein Recht zum Veröffentlichen, fehlt der Knopf – das ist so gewollt. Dann veröffentlicht jemand mit diesem Recht.
MD],
            ['Bild austauschen, ohne es überall neu einzusetzen', 'medien, bilder, ersetzen', <<<'MD'
Ein neues Mitarbeiterfoto oder ein aktualisiertes PDF? Mit **Datei ersetzen** tauschen Sie die Datei an einer Stelle aus – **überall, wo sie verwendet wird, erscheint automatisch die neue**.

### So geht’s
1. **Medien** öffnen und die Datei doppelklicken (oder Rechtsklick).
2. **Datei ersetzen …** wählen und die neue Datei auswählen.

### Was bleibt, was nicht
- Alt-Text, Tags und Sammlungen bleiben erhalten.
- Fokuspunkt und eigene Zuschnitte werden zurückgesetzt – bitte kurz prüfen und bei Bedarf neu setzen.

### Tipp gegen unscharfe Bilder
Ein größeres Original im passenden Seitenverhältnis hochladen, z. B. Porträt 1:1 ab 1200 × 1200 px, Bild breit (16:9 oder 21:9) ab 2400 px Breite.
MD],
            ['Versehentlich etwas gelöscht oder geändert: Version wiederherstellen', 'seiten, versionen, entwurf', <<<'MD'
Jede Seite merkt sich ihre letzten **20 gespeicherten Stände**.

### Früheren Stand zurückholen
1. **Seiten** öffnen, die Seite wählen und **Seiteneinstellungen → Versionen** aufrufen.
2. Beim gewünschten Stand auf **Wiederherstellen** klicken – das legt einen Entwurf an.
3. Entwurf prüfen und **Veröffentlichen**.

### Alle unveröffentlichten Änderungen verwerfen
Im Editor oben **Entwurf verwerfen** – oder in der Seitenliste beim Eintrag mit „Änderungen offen“. Der verworfene Stand bleibt unter *Versionen* gesichert.
MD],
            ['PDF oder andere Datei zum Download anbieten', 'medien, pdf, downloads', <<<'MD'
### So geht’s
1. Die Datei unter **Medien** hochladen – optional in eine **Sammlung** legen (z. B. „Formulare“).
2. Im Editor einen Block **Downloads** einfügen und die Datei **oder** die ganze Sammlung wählen.
3. **Veröffentlichen**.

### Gut zu wissen
- Zeigt der Block eine Sammlung, erscheinen neue PDFs in dieser Sammlung automatisch.
- PDFs öffnen sich auf der Website in einem eigenen, schnellen Betrachter (Mozilla PDF.js) – ohne Programme von Dritten.
- Große Dateien werden beim Hochladen in Stücken übertragen; der Balken zeigt den Fortschritt.
MD],
        ];
    }
}
