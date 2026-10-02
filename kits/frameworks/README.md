# Kit „frameworks“ – Tailwind CSS oder UIkit

Demo-Kit für KLXM Studio: **ein Kit, zwei Frameworks**. Dieselben Blöcke und Inhalte erscheinen wahlweise mit
[Tailwind CSS 4](https://tailwindcss.com/) oder [UIkit 3](https://getuikit.com/) – und Bearbeiten auf der Website,
Werkzeugleiste, Glossar, Datenlisten, Formulare, Karte und Video funktionieren mit beiden. Die Firma „Beispielwerk“
und alle Inhalte sind frei erfunden.

Ausführlich: **Hilfe → Technik → „Frameworks (Tailwind, UIkit, Bootstrap)“**.

## Umschalten

- Dauerhaft: Verwaltung → Design → „Framework“ (`tailwind` | `uikit`) – Klasse `fw-{framework}` am `<html>`.
- Zum Vergleichen: `?fw=uikit` bzw. `?fw=tailwind` an die Adresse hängen. Gilt nur für diese Anfrage (Besucher bekommen
  kein Cookie); angemeldet merkt sich die Sitzung den Wert, `?fw=reset` hebt das auf.
- Tailwind ohne Preflight (nur Vergleich): Design → „Tailwind: Preflight laden“ aus.

## Aufbau

```
kits/frameworks/
├── theme.php              Definition (§ 1–11); Design-Token „framework“
├── functions.php          frameworks_fw(), frameworks_view(), frameworks_assets() …
├── blocks/*.php           wählen nur die Vorlage: include frameworks_view('hero')
│   └── data_list.php      Abfrage wie im Kern, Ausgabe „Karten“/„Liste“ über views/{fw}/data_list.php
├── views/tailwind/*.php   Markup mit Tailwind-Utilities
├── views/uikit/*.php      Markup mit uk-Klassen und UIkit-Attributen
├── templates/             layout.php (lädt Framework-CSS/-JS), partials → views/{fw}/header|footer|section
├── tailwind/              app.css (Preflight in @layer base), app-nopf.css, _kit.css (@source, @theme, dark:, Komponenten)
├── assets/css/            site.css (beide: Tokens, Kern-Variablen) · uikit.css (Brücke) · video.css
├── assets/js/             site.js (beide) · tailwind.js (Reiter, Dialog-Rückfall)
├── build.mjs              Tailwind-CLI + UIkit-Vendoren → public/assets/kits/frameworks/
├── seed.php, tools/       Startinhalte (tools/demo-content.php), tools/demo.php zum Neu-Einspielen
└── package.json           tailwindcss, @tailwindcss/cli, @tailwindcss/typography, uikit
```

## Bauen

```
cd tools && pnpm run build          # alles (installiert kits/frameworks/node_modules bei Bedarf)
node kits/frameworks/build.mjs      # nur Tailwind neu (nach Änderungen an views/tailwind)
```

Das gebaute CSS (`public/assets/kits/frameworks/css/tailwind*.css`) und UIkit (`vendor/uikit/`) werden eingecheckt –
auf dem Server ist kein Node nötig. Kein Play-CDN (CSP, Produktion).

## Eigenes Framework-Kit

```
php bin/console kit:create meinkit --from=frameworks
```

Dann einen Ordner unter `views/` behalten, `frameworks_fw()` fest auf dieses Framework stellen (oder das Token entfernen),
`tailwind/` bzw. `assets/css/uikit.css` behalten und bauen.

## Testwebsite

```
php bin/console site:create frameworks frameworks.localhost:8088,frameworks.localhost frameworks
php bin/console user:create admin@frameworks.localhost admin --site=frameworks
CMS_SITE=frameworks php kits/frameworks/tools/demo.php --force     # Demo neu einspielen
```

## Lizenzen

Kit: MIT. UIkit © YOOtheme GmbH, MIT (`public/assets/kits/frameworks/vendor/uikit/LICENSE.md`).
Tailwind CSS © Tailwind Labs, MIT (`public/assets/kits/frameworks/css/LICENSE-tailwindcss.txt`). Bilder: mit GD erzeugt.
