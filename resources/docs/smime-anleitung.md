---
title: "S/MIME einrichten – verschlüsselte Anfragen von der Website empfangen"
subtitle: "Anleitung für Praxen, Einrichtungen und ihre IT"
date: "Stand: September 2026"
lang: de
---


> **Für wen ist diese Anleitung?**
> Für Teams und IT-Betreuung von Einrichtungen, die Anfragen über ein Formular ihrer KLXM-Studio-Website erhalten – etwa Arztpraxen, Therapie- oder Beratungsstellen. KLXM Studio kann diese Anfragen per E-Mail zustellen, **verschlüsselt mit S/MIME**. Diese Anleitung zeigt, was dafür einmalig einzurichten ist.

## Auf einen Blick

1. Sie besorgen ein **E-Mail-Zertifikat (S/MIME)** für das Postfach, in dem die Anfragen ankommen.
2. Sie **installieren** das Zertifikat (Datei `.p12` oder `.pfx`) auf jedem Gerät, auf dem die Anfragen gelesen werden.
3. Sie geben der Website nur den **öffentlichen Teil** des Zertifikats (Datei `.cer` oder `.pem`).
4. Die Website verschlüsselt jede Anfrage damit. **Nur** Ihre Geräte können sie öffnen.
5. Zum Schluss: **Testmail senden** und prüfen, ob sie mit Schloss-Symbol lesbar ankommt.

> **Wichtig:** Die Datei `.p12`/`.pfx` und ihr Passwort bleiben immer bei Ihnen. Weder die Website noch Ihre Website-Betreuung brauchen sie.

## 1. Was ist S/MIME – und warum?

S/MIME ist ein Standard für verschlüsselte E-Mails. Er ist in fast allen Mailprogrammen eingebaut.
Jedes Zertifikat hat zwei Teile: einen **öffentlichen Schlüssel** und einen **privaten Schlüssel**.
Mit dem öffentlichen Schlüssel kann jeder eine Nachricht **verschließen** – auch die Website.
**Öffnen** kann sie nur, wer den privaten Schlüssel hat – also nur Ihre eigenen Geräte.
Unterwegs (beim Mailanbieter, auf Servern, bei Weiterleitungen) bleibt der Inhalt unlesbar.

**Warum das wichtig ist:** Anfragen an eine Arztpraxis, Therapie- oder Beratungsstelle enthalten oft Gesundheitsdaten. Das sind „besondere Kategorien personenbezogener Daten“ nach **Art. 9 DSGVO**. Sie brauchen einen besonders hohen Schutz (Art. 32 DSGVO). Mit S/MIME können nur Sie die Inhalte lesen – nicht der Webhoster, nicht der Mailanbieter und nicht die Website-Betreuung.

> **Gut zu wissen:** S/MIME verschlüsselt den **Inhalt** und die **Anhänge**. Absender, Empfänger, Datum und **Betreff** bleiben sichtbar. Der Betreff der Website-Mails enthält deshalb standardmäßig nur den Formularnamen und eine Vorgangsnummer. Nehmen Sie dort keine Angaben aus der Anfrage auf.

## 2. Zertifikat besorgen

### 2.1 Für welche Adresse?

Das Zertifikat wird für **genau die E-Mail-Adresse** ausgestellt, an die die Website die Anfragen schickt.

- Ein Zertifikat gilt nur für **eine** Adresse. Tippfehler oder ein anderer Alias (z. B. `info@` statt `praxis@`) führen später zu Fehlern.
- Lesen mehrere Personen dasselbe Postfach? Dann reicht **ein** Zertifikat. Es wird auf allen Geräten installiert (siehe Abschnitt 6).

### 2.2 Welche Art von Zertifikat?

Sie brauchen ein einfaches **E-Mail-Zertifikat** (S/MIME). Frühere Bezeichnungen sind „Klasse 1“ oder „Class 1“. Heute heißt die einfachste Stufe meist **„Mailbox-validiert“ (MV)**: Der Anbieter prüft nur, ob Sie Zugriff auf das Postfach haben. Höhere Stufen (mit Prüfung von Organisation oder Person) funktionieren ebenso. Sie sind für die Verschlüsselung aber nicht nötig.

> **Wichtig bei der Bestellung:** Wählen Sie einen **RSA-Schlüssel** (z. B. RSA 2048 oder 3072 Bit), falls der Anbieter fragt. Zertifikate mit **ECC-/ECDSA-Schlüssel** kann KLXM Studio derzeit nicht zum Verschlüsseln verwenden. Das Zertifikat muss außerdem für **E-Mail-Schutz/Verschlüsselung** ausgestellt sein – bei E-Mail-Zertifikaten ist das der Normalfall.

**Anbieter (Auswahl, neutral, ohne Wertung):**

| Anbieter | Hinweise |
|---|---|
| **Actalis** (Italien) | Bietet ein **kostenloses** Mailbox-validiertes Zertifikat an (laut Anbieterseite im September 2026: eines pro Konto, erstes Jahr kostenlos, Verlängerung kostenpflichtig). Kostenlose Angebote können sich jederzeit ändern. Der private Schlüssel wird beim Anbieter erzeugt und als `.p12` geliefert. |
| **Certum** (Asseco, Polen) | S/MIME-Zertifikate in mehreren Stufen, Laufzeit bis zu 2 Jahre. |
| **Sectigo** | Vertrieb meist über Händler (Reseller), z. B. als „Personal Authentication“/„S/MIME“. |
| **GlobalSign** | „PersonalSign“-Zertifikate in mehreren Stufen. |
| **D-Trust** (Bundesdruckerei) | Deutscher Anbieter. Vor allem Zertifikate mit Organisationsprüfung, auch für Team-Postfächer. Bestellung über ein Kundenportal oder Händler. |

Preise nennen wir bewusst nicht. Sie ändern sich häufig und hängen vom Händler ab.

**Laufzeit und Verlängerung:**

- Öffentlich vertrauenswürdige S/MIME-Zertifikate gelten nach den Regeln des CA/Browser-Forums **höchstens 825 Tage** (gut 2 Jahre). Üblich sind **1 oder 2 Jahre**.
- Tragen Sie das **Ablaufdatum** sofort in den Teamkalender ein. Erinnerung: **4 Wochen vorher**.
- Bei der Verlängerung erhalten Sie in der Regel ein **neues** Zertifikat mit **neuem** Schlüsselpaar. Dann müssen Sie der Website den **neuen öffentlichen Teil** geben (Abschnitt 3). Und: **Den alten privaten Schlüssel nicht löschen** – sonst sind ältere Mails nicht mehr lesbar (Abschnitt 6).

### 2.3 Für Arztpraxen: Ist die Telematikinfrastruktur (KIM) eine Alternative?

**Nein, nicht für Anfragen von der Website.** KIM („Kommunikation im Medizinwesen“) ist der sichere E-Mail-Dienst der Telematikinfrastruktur. Er verbindet **nur** angeschlossene Einrichtungen: Praxen, Kliniken, Apotheken, Krankenkassen. Patientinnen und Patienten – und eine Website – können keine KIM-Nachrichten senden. KIM bleibt der richtige Weg für eAU, Arztbriefe und Ähnliches. Für das Website-Formular brauchen Sie ein normales S/MIME-Zertifikat für ein normales Postfach.

### 2.4 Was Sie bekommen – und wie Sie es aufbewahren

Je nach Anbieter gibt es zwei Wege:

- **Der Anbieter erzeugt den Schlüssel** (z. B. Actalis): Sie laden eine Datei **`.p12`** oder **`.pfx`** herunter. Das Passwort dazu wird angezeigt oder separat geschickt.
- **Ihr Browser oder Ihr Rechner erzeugt den Schlüssel** (Schlüsselanfrage, „CSR“): Das Zertifikat landet direkt im Browser bzw. im Zertifikatspeicher. Exportieren Sie es dann einmal **mit privatem Schlüssel** als `.p12`/`.pfx` (siehe unten bei Windows bzw. macOS) – als Sicherung und für weitere Geräte.

Die `.p12`/`.pfx`-Datei enthält den **privaten Schlüssel**. Behandeln Sie sie wie einen Generalschlüssel:

- **Sicher speichern:** im Passwortmanager Ihrer Einrichtung (als Anhang) oder auf einem verschlüsselten USB-Stick im Tresor. Passwort **getrennt** von der Datei aufbewahren.
- **Mindestens eine Sicherung** an einem zweiten Ort. Ohne diese Datei sind alle damit verschlüsselten Mails **für immer unlesbar**.
- **Nie** per unverschlüsselter Mail, Messenger oder Cloud-Freigabe verschicken. Nie an die Website-Betreuung oder den Webhoster geben.
- Alte Zertifikatsdateien **nicht löschen** (siehe Abschnitt 6, Archivierung).

## 3. Öffentliches Zertifikat für die Website bereitstellen

Die Website braucht **nur den öffentlichen Teil** – eine Datei mit der Endung **`.cer`**, **`.crt`** oder **`.pem`**. Sie ist nicht geheim.

KLXM Studio erwartet das Zertifikat im **Textformat „PEM“** (auch „Base-64“ genannt).

**So erkennen Sie die richtige Datei:** Öffnen Sie sie mit einem Texteditor (Editor, TextEdit). Sie beginnt mit `-----BEGIN CERTIFICATE-----` und endet mit `-----END CERTIFICATE-----`. Dazwischen stehen Buchstaben und Zahlen.

- Steht irgendwo `PRIVATE KEY`, ist es die **falsche** Datei – bitte nicht weitergeben. (KLXM Studio lehnt sie auch ab und speichert nichts davon.)
- Sehen Sie nur unlesbare Zeichen, ist die Datei im Binärformat („DER“). Exportieren Sie dann erneut im Format **Base-64** bzw. **PEM** wie unten beschrieben – oder bitten Sie Ihre Website-Betreuung, sie umzuwandeln.

### Windows (Windows 10/11)

1. Die `.p12`/`.pfx`-Datei zuerst installieren (Abschnitt 4, Windows).
2. **Start** öffnen, `Benutzerzertifikate verwalten` eintippen und öffnen (entspricht `certmgr.msc`).
3. **Eigene Zertifikate → Zertifikate** öffnen. Das Zertifikat mit Ihrer Empfangsadresse suchen.
4. Rechtsklick → **Alle Aufgaben → Exportieren…**
5. **Nein, privaten Schlüssel nicht exportieren** wählen.
6. Format **Base-64-codiert X.509 (.CER)** wählen, Dateinamen vergeben (z. B. `praxis-zertifikat.cer`), **Fertig stellen**.

### macOS (Apple Mail, Outlook für Mac)

1. Die `.p12`-Datei zuerst installieren (Abschnitt 4, macOS).
2. Die App **Schlüsselbundverwaltung** über die Spotlight-Suche öffnen (**⌘ + Leertaste**, „Schlüsselbund“ tippen). Seit macOS 15 Sequoia liegt sie nicht mehr im Ordner „Dienstprogramme“.
3. Links **Anmeldung** wählen, oben **Meine Zertifikate**.
4. Das Zertifikat mit Ihrer Empfangsadresse **anklicken** (die Zeile mit dem Zertifikat, **nicht** den aufgeklappten Schlüssel darunter).
5. **Ablage → Objekte exportieren…**
6. Dateiformat **Privacy Enhanced Mail (.pem)** wählen, **Sichern**. (Das Format „Zertifikat (.cer)“ speichert der Mac binär – dafür nicht verwenden.) Fragt der Mac nach einem Passwort, haben Sie den Schlüssel erwischt – abbrechen und Schritt 4 wiederholen.

### Thunderbird (alle Systeme)

1. **Einstellungen → Datenschutz & Sicherheit →** ganz unten bei **Zertifikate** auf **Zertifikate verwalten…**
2. Reiter **Ihre Zertifikate**, das Zertifikat markieren, **Ansehen…**
3. In der Zertifikatsansicht unter **Verschiedenes** auf **PEM (Zertifikat)** klicken und speichern.

*(Nicht „Sichern…“ verwenden – das exportiert den privaten Schlüssel.)*

### Für die IT: mit OpenSSL

```
openssl pkcs12 -in praxis.p12 -clcerts -nokeys -out praxis-zertifikat.pem
```

Bei älteren `.p12`-Dateien meldet OpenSSL 3 ggf. einen Fehler; dann zusätzlich `-legacy` angeben. Prüfen: `openssl x509 -in praxis-zertifikat.pem -noout -subject -enddate` zeigt Adresse und Ablaufdatum.

### Zertifikat an die Website übergeben

**Weg A – selbst eintragen** (mit einem Konto, das Anfragen verwalten und die Tabelle bearbeiten darf, meist die Administration):

1. In KLXM Studio anmelden.
2. **Daten →** die Tabelle bzw. den Eingang wählen (z. B. Rezeptanfragen oder Kontaktanfragen) **→ Einstellungen → Zustellung**.
3. Bei **S/MIME-Zertifikat** den Inhalt der `.pem`/`.cer`-Datei einfügen (bzw. die Datei hochladen) und speichern.
4. KLXM Studio prüft das Zertifikat sofort: Abgelaufene Zertifikate, Zertifikate ohne Verschlüsselungs-Freigabe und Dateien mit privatem Schlüssel werden abgelehnt – mit einer verständlichen Meldung.
5. Für jede weitere Tabelle, die per E-Mail zustellt, wiederholen.

Bis zu **5 Zertifikate** lassen sich hinterlegen. Die Website verschlüsselt dann für alle gleichzeitig. Das hilft beim Wechsel (altes und neues Zertifikat für kurze Zeit parallel) und wenn Anfragen an mehrere Adressen gehen, die jeweils ein eigenes Zertifikat haben.

**Weg B – an die Website-Betreuung schicken:** Senden Sie die Datei `.pem`/`.cer` an Ihre Ansprechperson (bei KLXM-Kundinnen und -Kunden: KLXM). Der öffentliche Teil darf per normaler E-Mail verschickt werden. Nennen Sie dazu die Empfängeradresse. Sie trägt das Zertifikat ein und sendet eine Testmail.

## 4. Zertifikat installieren und verschlüsselte Mails lesen

Der private Schlüssel muss auf **jedem Gerät und in jedem Programm** vorhanden sein, mit dem die Anfragen gelesen werden. Ist er installiert, **entschlüsseln die Programme automatisch** – ganz ohne Zusatzschritte beim Lesen.

Beschrieben sind die Versionen, die im September 2026 aktuell sind. Menünamen können sich mit Updates leicht ändern.

### 4.1 macOS: Apple Mail (macOS 15 Sequoia, macOS 26 Tahoe)

1. Die `.p12`-Datei im Finder **doppelklicken**.
2. Als Schlüsselbund **Anmeldung** wählen, **Hinzufügen**.
3. Das **Passwort der `.p12`-Datei** eingeben. (Eventuell fragt der Mac vorher nach Ihrem Mac-Anmeldepasswort.)
4. **Mail** beenden und neu öffnen. Fertig: Apple Mail findet das Zertifikat selbst und entschlüsselt automatisch.
5. Optional prüfen: In der **Schlüsselbundverwaltung → Anmeldung → Meine Zertifikate** steht das Zertifikat mit einem aufklappbaren **privaten Schlüssel** darunter.

### 4.2 Windows: Zertifikat in den Windows-Zertifikatspeicher (Windows 10/11)

Outlook (klassisch) und die meisten Windows-Programme nutzen diesen Speicher.

1. Die `.pfx`/`.p12`-Datei **doppelklicken**. Der **Zertifikatimport-Assistent** startet.
2. Speicherort **Aktueller Benutzer**, **Weiter**, Datei bestätigen, **Weiter**.
3. **Kennwort** der Datei eingeben. Optionen:
   - **Schlüssel als exportierbar markieren**: nur anhaken, wenn Sie später von diesem PC aus eine Sicherung erstellen wollen. Sonst leer lassen (sicherer).
   - **Hohe Sicherheit für den privaten Schlüssel aktivieren**: optional; Windows fragt dann bei jeder Nutzung nach.
4. **Zertifikatspeicher automatisch auswählen**, **Weiter**, **Fertig stellen**.
5. Jede Person, die sich mit einem **eigenen Windows-Konto** anmeldet, muss den Import selbst durchführen.

### 4.3 Windows: Outlook klassisch (Microsoft 365 Apps, Outlook 2021, Outlook 2024)

Voraussetzung: Schritt 4.2 ist erledigt. **Lesen** funktioniert danach sofort. Für verschlüsselte **Antworten** hinterlegen Sie das Zertifikat zusätzlich:

1. **Datei → Optionen → Trust Center → Einstellungen für das Trust Center…**
2. Links **E-Mail-Sicherheit**, unter „Verschlüsselte E-Mail-Nachrichten“ auf **Einstellungen…**
3. Bei **Signaturzertifikat** und **Verschlüsselungszertifikat** jeweils **Auswählen…** und das Zertifikat des Postfachs wählen. **OK**.
4. Haken **Inhalt und Anlagen für ausgehende Nachrichten verschlüsseln** **nicht** setzen – sonst versucht Outlook, jede Mail zu verschlüsseln, auch an Patienten ohne Zertifikat.

Hinweis: Outlook 2016 und 2019 werden seit Oktober 2025 nicht mehr mit Sicherheitsupdates versorgt. Die Schritte sind dort gleich; wir empfehlen trotzdem eine aktuelle Version.

### 4.4 Windows: das neue Outlook für Windows

- Das neue Outlook kann S/MIME seit Anfang 2025 – laut Microsoft aber nur für das **primäre Geschäftskonto** (Microsoft 365 / Exchange Online). Eine separate „S/MIME-Steuerung“ ist für das neue Outlook nicht nötig; sie betrifft nur **Outlook im Web** im Browser.
- Das Zertifikat muss vorher im Windows-Zertifikatspeicher liegen (Schritt 4.2). Das neue Outlook importiert es nicht selbst.
- Einstellungen: **Einstellungen → E-Mail → S/MIME**.
- **Läuft das Empfangspostfach über IMAP bei einem Webhoster** (kein Microsoft 365), unterstützt das neue Outlook S/MIME dafür nach unserem Kenntnisstand **nicht**. Nutzen Sie dann **Outlook klassisch** oder **Thunderbird**. Tipp: Oben rechts im neuen Outlook lässt sich meist zurück zur klassischen Version wechseln, solange Microsoft diese anbietet.

### 4.5 macOS: Outlook für Mac (Version 16, „neues Outlook“ und Legacy-Ansicht)

1. Die `.p12`-Datei wie in 4.1 in den Schlüsselbund **Anmeldung** importieren.
2. **Neues Outlook:** **Outlook → Einstellungen… → Konten** (bzw. **Outlook → Konten**), das Konto des Empfangspostfachs wählen, **Sicherheit**, unter **Zertifikat** das Zertifikat des Postfachs wählen, **OK**.
   **Legacy-Outlook:** **Extras → Konten →** Konto → **Erweitert → Sicherheit**.
3. Outlook neu starten. Beim ersten Öffnen einer verschlüsselten Mail fragt macOS eventuell, ob Outlook den Schlüssel verwenden darf: **Immer erlauben**.

### 4.6 Thunderbird (Windows, macOS, Linux; Version 128 ESR, 140 ESR und neuer)

1. **≡ (Menü) → Einstellungen → Datenschutz & Sicherheit**, nach unten zu **Zertifikate**, **Zertifikate verwalten…**
2. Reiter **Ihre Zertifikate → Importieren…**, die `.p12`-Datei wählen, das **Passwort der Datei** eingeben.
   Hinweis: Hat Thunderbird ein **Hauptpasswort**, fragt es zuerst danach.
3. **≡ → Konten-Einstellungen**, beim Konto des Empfangspostfachs **Ende-zu-Ende-Verschlüsselung** wählen.
4. Im Bereich **S/MIME** bei **Persönliches Zertifikat für digitale Unterschrift** und **Persönliches Zertifikat für Verschlüsselung** jeweils **Auswählen…** und das Zertifikat des Postfachs wählen.
5. Nicht einstellen, dass jede Mail verschlüsselt wird (**Standardmäßig verschlüsseln** aus lassen).

Thunderbird nutzt einen **eigenen** Zertifikatspeicher – auch unter Windows und macOS. Der Import ist deshalb zusätzlich nötig, selbst wenn das Zertifikat schon in Windows oder im Schlüsselbund liegt.

### 4.7 Linux

- **Thunderbird:** wie 4.6.
- **Evolution (GNOME, Version 3.5x):**
  1. **Bearbeiten → Einstellungen → Zertifikate →** Reiter **Ihre Zertifikate → Importieren**, Datei wählen, Passwort eingeben.
  2. **Bearbeiten → Einstellungen → E-Mail-Konten →** Konto **Bearbeiten → Sicherheit**, unter **S/MIME** das **Verschlüsselungszertifikat** auswählen.
  3. Meldet Evolution ein unbekanntes Zertifikat: unter **Zertifikate → Zertifizierungsstellen** der ausstellenden Stelle vertrauen („Vertrauen, um E-Mail-Benutzer zu identifizieren“).
  4. Bei der Flatpak-Version von Evolution gab es Berichte über verlorene Zertifikate – im Zweifel die Paketversion der Distribution nutzen.

### 4.8 iPhone und iPad (Apple Mail, iOS/iPadOS 18 und 26)

1. Die `.p12`-Datei **sicher** aufs Gerät bringen: per **AirDrop** vom Büro-Mac oder über die **Dateien**-App. Nicht per unverschlüsselter Mail.
2. Datei antippen. Es erscheint „Profil geladen“.
3. **Einstellungen →** oben **Profil geladen** (oder **Allgemein → VPN und Geräteverwaltung**) → **Installieren**. Gerätecode eingeben, dann das **Passwort der `.p12`-Datei**.
4. **Einstellungen → Apps → Mail → Mail-Accounts →** Konto des Empfangspostfachs → **Account → Erweitert**. Dort **S/MIME** einschalten und bei **Verschlüsseln standardmäßig**/**Signieren** das Zertifikat wählen, falls gewünscht. *(Bis iOS 17: **Einstellungen → Mail → Accounts → …**.)*
5. Verschlüsselte Mails öffnet Apple Mail danach automatisch.

Einschränkung: Andere Mail-Apps auf dem iPhone (auch Outlook) haben **keinen** Zugriff auf dieses Zertifikat.

### 4.9 Android

Android hat **keine** eingebaute S/MIME-Unterstützung in einer Standard-Mail-App. Möglich sind:

- **FairEmail** (kostenlos, quelloffen): unterstützt S/MIME. Das Zertifikat wird in den Android-Schlüsselspeicher importiert und in der App ausgewählt.
- **R2Mail2**: unterstützt S/MIME und PGP; die App wirkt älter, funktioniert aber laut Berichten weiterhin.
- **Outlook für Android**: S/MIME nur für **Microsoft 365 / Exchange Online** und in der Regel über eine Geräteverwaltung (Intune). Für ein IMAP-Postfach beim Webhoster **nicht geeignet**.
- **Thunderbird für Android** (früher K-9 Mail): **kein** S/MIME, nur OpenPGP. Laut Thunderbird-Team ist S/MIME für 2026 nicht geplant.

**Unsere Empfehlung:** Lesen Sie Anfragen am **Arbeitsplatz-PC**. Den privaten Schlüssel auf private Smartphones zu kopieren, erhöht das Risiko. Wenn mobil nötig: nur auf dienstlichen, gesperrten Geräten.

### 4.10 Webmail im Browser

- Die meisten Webmail-Oberflächen **können S/MIME nicht entschlüsseln**: z. B. **Gmail (privat)** und viele Webmailer von Hostern. Die Mail erscheint dort als Anhang `smime.p7m`.
- **Ausnahmen mit Einschränkungen:**
  - **Outlook im Web** (Microsoft 365 / Exchange): mit der **S/MIME-Steuerung** (Browser-Erweiterung für Edge oder Chrome, unter **Einstellungen → E-Mail → S/MIME**) – nur für Microsoft-365-/Exchange-Konten und nur auf Windows-PCs mit installiertem Zertifikat.
  - **Gmail in Google Workspace** („gehostetes S/MIME“): nur in bestimmten Editionen (z. B. Enterprise Plus, Education). Dabei liegt der **private Schlüssel bei Google** – für Gesundheitsdaten datenschutzrechtlich sorgfältig abwägen.
  - Einzelne Mailanbieter bieten S/MIME im Webmail an. Fragen Sie Ihren Anbieter.
- Webmail ist **kein** Ersatz für ein Mailprogramm mit installiertem Zertifikat.

## 5. Testen

1. Zertifikat hochladen (Abschnitt 3) und in mindestens einem Mailprogramm installieren (Abschnitt 4).
2. In KLXM Studio: **Daten →** Tabelle **→ Einstellungen → Zustellung → Testmail senden**.
3. Im Empfangspostfach auf **jedem** Gerät öffnen, auf dem Anfragen gelesen werden sollen.

Die Testmail enthält **erfundene Angaben**. Ihr Betreff beginnt mit **[TEST]**, unten steht „TESTNACHRICHT … bitte nicht archivieren“. Im Kopf der Nachricht steht bei „Zustellung“ der Zusatz **S/MIME-verschlüsselt**.

**So sieht ein korrektes Ergebnis aus:**

| Programm | Kennzeichen | Inhalt |
|---|---|---|
| Apple Mail (Mac) | Im Kopf der Mail Zeile **Sicherheit** mit **Schloss** („Verschlüsselt“) | normal lesbar |
| Outlook klassisch | **Schloss-Symbol** in der Mailliste und im Kopf der Mail | normal lesbar |
| Neues Outlook / Outlook für Mac | Hinweis bzw. Symbol „Verschlüsselt“ (S/MIME) im Kopf | normal lesbar |
| Thunderbird | Schloss bzw. **S/MIME**-Symbol rechts im Kopf; ein Klick zeigt „Nachricht ist verschlüsselt“ | normal lesbar |
| iPhone/iPad | **Schloss** neben dem Absender | normal lesbar |
| Webmail ohne S/MIME | nur Anhang `smime.p7m` | **nicht lesbar** – erwartet, kein Fehler der Website |

**Fehlerbild:** Leere Mail, nur Anhang `smime.p7m`, oder Meldung „Kann nicht entschlüsselt werden“ → Abschnitt 6.

## 6. Wenn es nicht klappt

**„Kann nicht entschlüsselt werden“ / nur `smime.p7m` sichtbar**

- Auf **diesem** Gerät bzw. in **diesem** Programm ist der private Schlüssel nicht installiert. → Abschnitt 4 für dieses Programm durchgehen. Thunderbird braucht einen eigenen Import.
- Das Programm kann kein S/MIME (z. B. Webmail, Thunderbird für Android). → anderes Programm nutzen.
- Die Website nutzt ein **anderes** Zertifikat als das installierte (z. B. nach einer Verlängerung). → Vergleichen: Name/Adresse und Ablaufdatum in KLXM Studio (**Zustellung**) und im Mailprogramm müssen übereinstimmen.

**Zertifikat abgelaufen**

- **30 Tage vor Ablauf** zeigt KLXM Studio bei der Zustellung eine Warnung.
- Ist das Zertifikat abgelaufen, verschickt die Website **keine Inhalte mehr per E-Mail**. Bei „Nur per E-Mail“ wird die Anfrage stattdessen verschlüsselt im System gesichert und die Administration gewarnt – es geht also nichts verloren, kommt aber nicht mehr im Postfach an.
- Lösung: neues Zertifikat besorgen und den neuen öffentlichen Teil eintragen (Abschnitt 3).
- **Alte Mails** lassen sich mit dem alten privaten Schlüssel weiterhin öffnen. Deshalb das alte Zertifikat **nicht löschen** – auch wenn das Mailprogramm es als „abgelaufen“ markiert.

**Falsche Postfach-Adresse**

- Zertifikat und Empfängeradresse der Website müssen **exakt** übereinstimmen. Eine Weiterleitung an eine andere Adresse ist technisch möglich: Die Mail bleibt verschlüsselt und ist dort nur mit **demselben** Schlüssel lesbar.
- Neue Adresse? → neues Zertifikat für diese Adresse.

**Mehrere Personen oder Geräte lesen dasselbe Postfach**

- Der **gleiche** private Schlüssel (dieselbe `.p12`-Datei) muss auf **jedem** Gerät und in **jedem** Programm installiert sein. Das ist bei S/MIME normal.
- Alternative: Jede Person hat ein **eigenes** Postfach mit **eigenem** Zertifikat. Die Website kann an bis zu 10 Adressen senden und für bis zu 5 Zertifikate gleichzeitig verschlüsseln. Die Empfänger tragen Sie unter **Zustellung** ein.
- Jede Installation ist ein weiterer Ort, an dem der Schlüssel liegt. Installieren Sie ihn nur auf dienstlichen Geräten mit Bildschirmsperre und Benutzerkonto.
- Scheidet jemand aus, der die `.p12`-Datei und das Passwort kannte: Zertifikat vorsorglich erneuern.

**Verlängerung / neues Zertifikat**

1. Neues Zertifikat besorgen (Abschnitt 2).
2. Auf **allen** Geräten installieren (Abschnitt 4). **Das alte nicht entfernen.**
3. Neuen öffentlichen Teil an die Website geben (Abschnitt 3). Tipp: Altes und neues Zertifikat können kurz parallel hinterlegt sein, bis alle Geräte umgestellt sind.
4. Testmail senden (Abschnitt 5).
5. Alte **und** neue `.p12`-Datei samt Passwort sicher aufbewahren.

**Archivierung und Aufbewahrung**

- Verschlüsselte Mails bleiben **auch im Archiv verschlüsselt** – im Postfach, in Backups und in Mail-Archivsystemen.
- Lesbar sind sie nur, solange der passende private Schlüssel vorhanden ist. Ist der Schlüssel weg, ist der Inhalt **dauerhaft verloren**. Auch die Website-Betreuung kann ihn nicht wiederherstellen.
- Für Arztpraxen: Für die ärztliche Dokumentation gelten lange Aufbewahrungsfristen (in der Regel **mindestens 10 Jahre** nach Abschluss der Behandlung, § 630f Abs. 3 BGB; teils länger). Ob und welche Anfragen dazugehören, entscheiden Sie.
- **Unsere Empfehlung:**
  - Übernehmen Sie dokumentationspflichtige Inhalte in Ihre **Fachsoftware** (in Arztpraxen: das Praxisverwaltungssystem, PVS). Dort sind sie unabhängig vom Mail-Schlüssel gesichert.
  - Bewahren Sie **alle** privaten Schlüssel (auch abgelaufene) mindestens so lange auf wie die zugehörigen Mails.
  - Nutzen Sie ein **Mail-Archivsystem** (z. B. für die GoBD): Klären Sie mit dem Anbieter, ob es verschlüsselte Mails speichert, ob es sie mit Ihrem Schlüssel entschlüsselt ablegt und wie das datenschutzrechtlich geregelt ist.
  - Stimmen Sie das Vorgehen mit Ihrer/Ihrem **Datenschutzbeauftragten** ab.

## 7. Checkliste für den Start

**Bei Ihnen**

- [ ] Empfänger-Postfach festgelegt (genaue Adresse): ______________________
- [ ] E-Mail-Zertifikat (S/MIME) für genau diese Adresse bestellt und erhalten
- [ ] `.p12`/`.pfx` und Passwort **getrennt** und sicher gespeichert, **Sicherung** an zweitem Ort
- [ ] Ablaufdatum notiert: ____________ · Erinnerung 4 Wochen vorher im Kalender
- [ ] Zertifikat auf **allen** Lesegeräten und in **allen** Mailprogrammen installiert:
  - [ ] Arbeitsplatz 1: ______________________
  - [ ] Arbeitsplatz 2: ______________________
  - [ ] weitere: ______________________
- [ ] Zertifikat mit **RSA-Schlüssel** (nicht ECC)
- [ ] Öffentlicher Teil als **PEM/Base-64** exportiert und geprüft (beginnt mit `-----BEGIN CERTIFICATE-----`, kein „PRIVATE KEY“)
- [ ] Öffentlicher Teil hochgeladen **oder** an die Website-Betreuung geschickt
- [ ] Testmail auf jedem Gerät geöffnet: Schloss sichtbar, Inhalt lesbar
- [ ] Wer liest die Anfragen? Vertretung geregelt (Urlaub, Krankheit)
- [ ] Umgang mit Aufbewahrung/Archiv geklärt (PVS, Archivanbieter, Datenschutzbeauftragte)

**Was Ihre Website-Betreuung (z. B. KLXM) von Ihnen braucht**

1. Die **Empfängeradresse** für die Anfragen.
2. Den **öffentlichen Teil** des Zertifikats als `.pem` oder Base-64-`.cer` (nicht die `.p12`/`.pfx`!).
3. Eine **Ansprechperson** bei Ihnen oder Ihrer IT für den Test.
4. Die **Rückmeldung nach der Testmail**: auf welchen Geräten lesbar, wo nicht.
5. Später: rechtzeitig vor Ablauf den **neuen öffentlichen Teil**.

Die Website-Betreuung braucht **nie**: die `.p12`/`.pfx`-Datei, deren Passwort oder Zugang zum Empfangspostfach.


## Anhang: Stand, Versionen und offene Punkte

**Beschriebene Versionen (Stand September 2026):** Windows 10/11; Outlook klassisch (Microsoft 365 Apps, Outlook 2021/2024); neues Outlook für Windows; macOS 15 Sequoia und 26 Tahoe mit Apple Mail; Outlook für Mac (Version 16); Thunderbird 128/140 ESR und aktuelle Versionen; Evolution 3.5x; iOS/iPadOS 18 und 26; Android mit FairEmail/R2Mail2.

**Punkte, die wir nicht abschließend prüfen konnten:**

- **Neues Outlook für Windows mit IMAP-Konten:** Microsoft nennt S/MIME nur für das primäre (Microsoft-365-)Konto. Ob IMAP-Konten inzwischen unterstützt werden, bitte am Gerät prüfen.
- **Outlook für Mac mit IMAP-Konten:** Die Einrichtung ist dokumentiert; ob verschlüsselte Mails in IMAP-Konten zuverlässig geöffnet werden, bitte mit der Testmail prüfen.
- **Genaue Menünamen** (v. a. Thunderbird-Zertifikatsansicht, iOS-Einstellungen, Outlook für Mac) ändern sich mit Updates leicht.
- **Anbieter-Angebote** (insbesondere kostenlose Zertifikate, Laufzeiten) können sich jederzeit ändern.
- **Android-Apps:** Funktionsumfang und Pflege von FairEmail und R2Mail2 bitte vor dem Einsatz prüfen.

**Quellen (Auswahl):** Microsoft Support „Outlook für die Verwendung von S/MIME-Verschlüsselung einrichten“ und „Digital signierte oder verschlüsselte Nachricht senden (Mac)“; Microsoft Learn „S/MIME for Outlook for iOS and Android“; Apple Support 102245 (S/MIME in iOS Mail); Mozilla Support (Thunderbird Ende-zu-Ende-Verschlüsselung); GNOME Evolution Hilfe „Managing S/MIME certificates“; Google Workspace Hilfe „Hosted S/MIME“; CA/Browser Forum S/MIME Baseline Requirements; Actalis, Certum, GlobalSign, D-Trust (Produktseiten); KBV „E-Mail-Dienst KIM“; Thunderbird Blog „Mobile Progress Report“ (2026).
