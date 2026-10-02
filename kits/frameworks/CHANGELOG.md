# Changelog – Kit „frameworks“

Format: [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), Versionen nach [SemVer](https://semver.org/lang/de/).

## [Unveröffentlicht]

### Neu
- Demo-Kit „frameworks“ (fiktive Firma „Beispielwerk“): dieselben Blöcke wahlweise mit **Tailwind CSS 4** oder **UIkit 3**,
  umschaltbar unter Design → „Framework“ bzw. zum Vergleich mit `?fw=uikit` / `?fw=tailwind`.
- Blöcke: Einstieg, Karten, Text + Bild, Text, Aufklappliste, Reiter, Handlungsaufruf mit Dialog, Kontakt (zentral), Video;
  Datenliste „Karten“/„Liste“ mit Framework-Klassen (Kern für „Kompakt“/„Tabelle“), Formular und Karte aus dem Kern.
- Tailwind: vorkompiliertes CSS (`@tailwindcss/cli`, Typography-Plugin), Preflight in `@layer base`, Variante `dark:` für
  Gerät, Vorschau und dunkle Abschnitte; Vergleichsfassung ohne Preflight.
- UIkit 3.25 (MIT) mitgeliefert; Brücke `css/uikit.css` für Kontrast (WCAG AA), Dunkelmodus und Rich-Text-Klassen.
- Startinhalte mit Seitenbaum, Datentabelle „Projekte“ mit Detailseite, Formular „Anfragen“, Glossar und erzeugten Bildern.
