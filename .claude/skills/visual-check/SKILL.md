---
name: visual-check
description: Optischer Vorher/Nachher-Vergleich einer Website (alle Menüseiten, Desktop + Telefon, Pixelvergleich) mit tools/visual-diff.mjs – vor/nach Deploys, CSS- oder Kit-Änderungen, wenn sich eine Website nicht ändern darf.
---

# Optischer Vergleich

```bash
node tools/visual-diff.mjs vorher  <URL>     # vor der Änderung
# … Änderung / Deploy …
node tools/visual-diff.mjs nachher <URL>
node tools/visual-diff.mjs diff               # je Seite abweichende Pixel; Exit 0 = unverändert, 2 = Abweichungen
```

- Bilder: `storage/visual-diff/<host>/` (nicht versioniert); Optionen `--out=`, `--max=` (Seiten, Standard 14), `--wait=` (ms).
- Lokal genauso: `node tools/visual-diff.mjs vorher http://<site>.localhost:8099`.
- Abweichungen zuerst auf zeitabhängige Inhalte prüfen (Öffnungszeiten-Status, Countdown, Zufallsbilder) und auf Inhalte,
  die die Redaktion inzwischen geändert hat – erst dann Code verdächtigen.
- Unerwartete Abweichung: nicht weiter ausrollen, betroffene Bilder ansehen und dem Nutzer zeigen.
