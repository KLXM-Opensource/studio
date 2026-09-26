/**
 * Abläufe der Tutorial-Videos (tools/tutorials/record.mjs). Kurznamen = Schlüssel in app/Admin/tutorials.php.
 *
 * Jeder Ablauf: { slug, title, account, mobile?, setup?(t, state), run(t, state), cleanup?(t, state) }
 *   t.step(de, en)  Untertitel (Zeitstempel = jetzt)      t.click / t.type / t.select / t.hover / t.keys / t.ring
 *   t.goto(pfad)    Seite öffnen                          t.blur(selector)  Geheimes unkenntlich machen
 *   t.post(pfad, daten, json?)  Vorbereiten/Aufräumen mit CSRF-Token der Verwaltung
 * setup/cleanup laufen angemeldet in einem eigenen Fenster ohne Aufnahme. Alles, was ein Ablauf anlegt,
 * räumt cleanup wieder ab – auch wenn die Aufnahme fehlschlägt.
 */
export const flows = [];
const add = (f) => flows.push(f);
const meta = process.platform === 'darwin' ? 'Meta' : 'Control';

/* ============================================================ Redaktion */

add({
  slug: 'r-ueberblick', title: 'Anmelden & Überblick', account: 'demo', noLogin: true,
  async run(t) {
    await t.goto('/admin/login');
    await t.step('Die Verwaltung öffnen: Adresse der Website + /admin.', 'Open the admin area: your website address + /admin.');
    await t.step('E-Mail-Adresse und Passwort eingeben, dann „Anmelden“.', 'Enter your email address and password, then “Anmelden” (sign in).');
    await t.type('#email', t.acc.email);
    await t.type('#password', t.acc.password, { delay: 35 });
    await t.click('button[type=submit]', { after: 1200 });
    await t.step('Die Übersicht: Kennzahlen und „Was ist zu tun?“ – was heute ansteht.', 'The dashboard: key figures and “Was ist zu tun?” (what to do) – what needs attention today.');
    await t.ring('#dash-figures', 1600);
    await t.ring('#dash-todo', 1400);
    await t.step('Oben links unter dem Namen: Lupe = Suche, Sprechblase = Assistent, ↗ = Website ansehen.', 'Top left below the name: magnifier = search, speech bubble = assistant, ↗ = view website.');
    await t.ring('.adm-brand-row', 1800);
    await t.step('Links steht das Menü – z. B. „Daten“ für Termine, Team oder Beiträge.', 'The menu is on the left – e.g. “Daten” (data) for events, team or posts.');
    await t.click('#adm-mainnav a[href$="/admin/data"]', { after: 1200 });
    await t.step('Jeder Bereich hat eine eigene Farbe. „Zurück zu …“ oben führt ins Hauptmenü.', 'Every area has its own colour. “Zurück zu …” (back to) at the top leads to the main menu.');
    await t.ring('.adm-drill__back', 1500).catch(() => {});
    await t.click('main a[href$="/admin/data/termine"]', { after: 1200 });
    await t.step('Oft gebraucht? Mit dem Stern ☆ neben der Überschrift als Favorit merken.', 'Need it often? Click the star ☆ next to the heading to add a favourite.');
    await t.click('main button[aria-label="Zu Favoriten hinzufügen"]', { after: 900 });
    t.poster();
    await t.step('Der Favorit steht jetzt oben in der Seitenleiste – nur für Ihr Konto.', 'The favourite now appears at the top of the sidebar – for your account only.');
    await t.ring('nav[aria-labelledby="adm-fav-h"]', 1800);
    await t.step('Alles finden: die Lupe oder ⌘K (Windows: Strg+K) öffnet die Suche – überall in der Verwaltung.', 'Find anything: the magnifier or ⌘K (Windows: Ctrl+K) opens search – everywhere in the admin.');
    await t.hover('.adm-site-open--search', 700).catch(() => {});
    await t.keys(meta + '+k', process.platform === 'darwin' ? '⌘ K' : 'Strg K');
    await t.page.keyboard.type('Benutzer', { delay: 110 });
    await t.wait(1200);
    await t.step('Treffer: Seiten, Einträge, Medien, Einstellungen – oder den Assistenten fragen. Pfeiltasten wählen, ↵ öffnet.', 'Results: pages, entries, media, settings – or ask the assistant. Arrow keys select, ↵ opens.');
    await t.keys('ArrowDown', '↓');
    await t.keys('Enter', '↵');
    await t.wait(1500);
    await t.step('Unten links „Hilfe & Support“: Handbuch, Tutorials, Assistent und „Problem melden“.', 'Bottom left, “Hilfe & Support” (help & support): manual, tutorials, assistant and “Problem melden” (report a problem).');
    await t.click('.adm-helpbox__sum', { after: 1200 });
    await t.ring('.adm-helpbox', 2000);
  },
  async cleanup(t) {
    await t.goto('/admin');
    for (const url of ['/admin/data/termine?view=list', '/admin/data/termine?view=calendar', '/admin/data/termine']) await t.post('/admin/api/favorites/remove', { url }, true);
  },
});

/** Probeseite: Kopie einer vorhandenen Seite (Entwurf) mit neutralem Titel – cleanup löscht sie wieder */
async function probePage(t, state, from = '/ueber-uns', title = 'Über uns – Probeseite', slug = 'probeseite') {
  await t.goto(from);
  const src = await t.page.locator('a[href*="/admin/pages/"]').first().getAttribute('href');
  const id = src.match(/pages\/(\d+)/)[1];
  const r = await t.post(`/admin/pages/${id}/duplicate`, {});
  state.pageId = JSON.parse(r.text).id;
  await t.goto(`/admin/pages/${state.pageId}`);
  await t.page.fill('#title', title);
  await t.page.fill('#slug', slug);
  await t.page.selectOption('#status', 'published');
  await Promise.all([t.page.waitForLoadState(), t.page.click('main form button[type=submit]:has-text("Speichern")')]);
  state.path = '/' + slug;
}
const dropPage = async (t, state) => { if (state.pageId) await t.post(`/admin/pages/${state.pageId}/delete`, {}); };

const BAR = '[aria-label="Redaktion"]';
/** Werkzeugleiste der Website (app/Views/toolbar.php): Modus-Schalter, ⋯-Menü, Veröffentlichen mit Rückfrage */
const barMode = (t, label) => t.click(t.page.locator(`${BAR} [aria-label="Modus"] >> text="${label}"`).first(), { after: 1500 });
const barMore = async (t, item) => {
  await t.click(`${BAR} button[aria-label="Weitere Aktionen"]`, { after: 700 });
  await t.click(t.page.locator('#cms-more-menu [role^=menuitem]', { hasText: item }).first(), { after: 1500 });
};
/** „Bearbeiten“ in der Block-Leiste eines bestimmten Blocks (Leiste = Shadow DOM, CSS-Locators durchdringen offene Schatten-Wurzeln).
 *  Nicht „.cms-block__edit:visible“ – unsichtbare Leisten (opacity 0) zählen für Playwright als sichtbar, das wäre immer der erste Block (Hero). */
const blockOf = (t, label) => t.page.locator('.cms-block').filter({ has: t.page.locator('.cms-block__label', { hasText: label }) }).first();
const blockEdit = (t, label) => blockOf(t, label).locator('.cms-block__edit');
/** Block so scrollen, dass seine Leiste unter der klebenden Kopfzeile der Website frei liegt (sonst trifft der Klick die Navigation) */
const showBlock = async (t, label, top = 170) => {
  const dy = await blockOf(t, label).evaluate((el, top) => Math.round(el.getBoundingClientRect().top - top), top);
  if (Math.abs(dy) > 20) await t.scroll(dy, 1000);
};
// Gestaltete Rückfrage der Leiste (falls vorhanden) bestätigen; ältere Stände nutzen confirm() – das zeigt die Aufnahme selbst an
const confirmOk = async (t) => {
  const ok = t.page.locator('dialog.cms-confirm[open] [data-r="ok"]');
  if (await ok.waitFor({ timeout: 2500 }).then(() => true, () => false)) await t.click(ok, { after: 1500 }); else await t.wait(1200);
};

add({
  slug: 'r-seite-bearbeiten', title: 'Seite bearbeiten & veröffentlichen', account: 'demo',
  setup: (t, s) => probePage(t, s),
  async run(t, s) {
    await t.goto(s.path);
    await t.step('Seite auf der Website öffnen. Die dunkle Leiste oben sehen nur angemeldete Personen.', 'Open the page on the website. Only signed-in editors see the dark bar at the top.');
    await t.step('Oben in der Mitte von „Ansehen“ auf „Bearbeiten“ umschalten.', 'In the middle of the bar, switch from “Ansehen” (view) to “Bearbeiten” (edit).', 1200);
    await barMode(t, 'Bearbeiten');
    await t.step('Im Bearbeitungsmodus hat jeder Block einen Rahmen, seinen Namen und „Bearbeiten“.', 'In edit mode every block shows a frame, its name and “Bearbeiten” (edit).');
    await t.hover('main section >> nth=1', 1200);
    await t.step('Kurze Texte mit gestricheltem Rahmen direkt anklicken und überschreiben.', 'Click short texts with a dashed frame and type over them.');
    const h1 = t.page.locator('main h1[data-edit]').first();
    await t.click(h1, { after: 300 });
    await t.page.keyboard.press(meta + '+a');
    await t.page.keyboard.type('Menschen, die Ideen voranbringen.', { delay: 70 });
    await t.page.keyboard.press('Enter');
    await t.wait(800);
    await t.step('Fließtext: Wort markieren – die Formatierungsleiste erscheint – z. B. „Fett“.', 'Body text: select a word – the formatting bar appears – e.g. “Fett” (bold).');
    const rich = t.page.locator('main [data-edit][contenteditable="true"] p, main [data-edit]:not([contenteditable="plaintext-only"]) p').first();
    await rich.scrollIntoViewIfNeeded();
    await t.moveTo(rich);
    await rich.dblclick();
    await t.wait(900);
    const bold = t.page.locator('button[data-cmd="bold"]:visible').first();   // „B“ (Fett) in der Formatierungsleiste
    await t.click(bold, { after: 1000 });
    await t.step('„Bearbeiten“ am Block öffnet rechts alle Felder – Links, Bilder, Varianten.', '“Bearbeiten” on a block opens all its fields on the right – links, images, variants.');
    await showBlock(t, 'Text + Bild');
    await t.hover(rich, 500);
    await t.click(blockEdit(t, 'Text + Bild'), { after: 1400 });
    const eyebrow = t.page.getByRole('complementary').getByLabel('Dachzeile (optional)');
    await t.click(eyebrow, { after: 200 });
    // Schreibmarke ans Ende – „End“ bewegt unter macOS nicht die Marke, sondern scrollt die Seitenleiste
    await eyebrow.evaluate((el) => el.setSelectionRange(el.value.length, el.value.length));
    await t.page.keyboard.type(' & Werte', { delay: 90 });
    await t.wait(400);
    t.poster();
    await t.step('Die Vorschau links aktualisiert sich sofort. Esc schließt die Seitenleiste.', 'The preview on the left updates right away. Esc closes the sidebar.');
    await t.keys('Escape', 'Esc');
    await t.step('„Speichern“ (⌘S) sichert einen Entwurf – Besucher sehen noch den alten Stand.', '“Speichern” (⌘S) saves a draft – visitors still see the old version.');
    await t.click(`${BAR} button:has-text("Speichern")`, { after: 1500 });
    await t.step('Menü „⋯“ › „Vorschau“ zeigt die Seite ohne Bearbeitungsrahmen.', 'Menu “⋯” › “Vorschau” (preview) shows the page without editing frames.');
    await barMore(t, 'Vorschau');
    await t.scroll(350, 1200);
    await t.step('Zurück auf „Bearbeiten“ – dann „Veröffentlichen“ und bestätigen: Jetzt ist die Änderung online.', 'Back to “Bearbeiten” – then “Veröffentlichen” (publish) and confirm: now the change is live.');
    await t.scroll(-350, 600);
    await barMode(t, 'Bearbeiten');
    await t.click(`${BAR} button[data-editor-publish]:visible`, { after: 1200 });
    await confirmOk(t);
    await t.ring(BAR, 1500);
    await t.step('Der Pfeil neben „Veröffentlichen“ bietet „Entwurf verwerfen …“; frühere Stände unter Seiteneinstellungen › Versionen.', 'The arrow next to “Veröffentlichen” offers “Entwurf verwerfen …” (discard draft); earlier states under page settings › versions.', 2800);
  },
  cleanup: dropPage,
});

/** Medien: IDs vorher merken, danach alle neuen löschen */
const mediaIds = async (t) => ((await t.getJson('/admin/api/media')).items || []).map((m) => m.id);
const mediaSnapshot = async (t, s) => { await t.goto('/admin/media'); s.media = await mediaIds(t); };
const mediaCleanup = async (t, s) => {
  await t.goto('/admin/media');
  for (const id of (await mediaIds(t)).filter((id) => !s.media.includes(id))) await t.post(`/admin/api/media/${id}/delete`, {}, true);
};

add({
  // Website „fluid“: nur erzeugte Beispielbilder in der Mediathek; hochgeladen werden erzeugte Grafiken (tools/tutorials/samples/)
  slug: 'r-bild-hochladen', title: 'Bild hochladen mit Alt-Text', account: 'fluid',
  setup: mediaSnapshot,
  async run(t) {
    const photo = t.file('farbverlauf-blau-orange.jpg');
    const photo2 = t.file('fraktal-spirale.jpg');
    await t.goto('/admin/media');
    await t.step('Medien öffnen. Hier liegen alle Bilder, PDFs und Videos der Website.', 'Open “Medien”. All images, PDFs and videos of the website live here.');
    await t.step('Bild vom Computer einfach in die Mediathek ziehen …', 'Simply drag an image from your computer into the media library …', 1200);
    await t.dropFile('[data-media-library] [role=listbox]', photo);
    await t.step('Alt-Text ist Pflicht: Was ist auf dem Bild zu sehen? (für Screenreader und Suchmaschinen)', 'Alt text is required: what does the image show? (for screen readers and search engines)');
    await t.step('„Alt-Text vorschlagen“: Die KI beschreibt das Bild – das dauert einen Moment.', '“Alt-Text vorschlagen”: the AI describes the image – this takes a moment.', 1200);
    await t.click('[data-kia-alt]', { after: 300 });
    await t.page.locator('[data-kia-ok]').first().waitFor({ timeout: 90000 });
    await t.wait(600);
    t.poster();
    await t.step('Vorschlag prüfen! „Hochladen“ bleibt gesperrt, bis Sie „Geprüft ✓“ klicken oder den Text ändern.', 'Check the suggestion! “Hochladen” stays locked until you click “Geprüft ✓” or edit the text.');
    await t.ring('.mu-item [data-alt]', 1600);
    await t.click('[data-kia-ok]', { after: 800 });
    await t.click('[data-mu-start]', { after: 2500 });
    await t.step('Zweiter Weg: Bildschirmfoto oder Bild kopieren und mit ⌘V (Strg+V) einfügen.', 'Second way: copy a screenshot or image and paste it with ⌘V (Ctrl+V).', 1600);
    await t.pasteFile(photo2);
    await t.type('.mu-item [data-alt]', 'Farbiges Fraktal mit runden Formen in Grün, Orange und Rot', { delay: 45 });
    await t.click('[data-mu-start]', { after: 2500 });
    await t.step('Fertig: Die Bilder stehen in der Mediathek und lassen sich in jedem Block auswählen.', 'Done: the images are in the library and can be chosen in any block.', 2200);
  },
  cleanup: mediaCleanup,
});

/** Datensätze: neue Einträge einer Tabelle wieder löschen */
const entryIds = async (t, handle) => {
  // Standardsprache und Englisch (Übersetzungen stehen nur in der Liste ihrer Sprache)
  const ids = [];
  for (const lang of ['', 'en']) {
    await t.goto(`/admin/data/${handle}?view=list${lang ? '&lang=' + lang : ''}`);
    ids.push(...await t.page.evaluate(() => [...document.querySelectorAll('main a[href*="/admin/data/"]')].map((a) => a.getAttribute('href').match(/\/admin\/data\/[\w-]+\/(\d+)/)?.[1]).filter(Boolean)));
  }
  return [...new Set(ids)];
};

add({
  slug: 'r-termin-anlegen', title: 'Termin mit Wiederholung anlegen', account: 'demo',
  async setup(t, s) { s.before = await entryIds(t, 'termine'); },
  async run(t, s) {
    await t.goto('/admin/data/termine?view=list');
    await t.step('Daten › Termine: Hier stehen alle Termine. „+ Termin“ legt einen neuen an.', 'Data › Termine: all events are listed here. “+ Termin” creates a new one.');
    await t.click('main a[href*="/admin/data/termine/new"]', { after: 1200 });
    await t.type(t.page.getByLabel(/^Titel/), 'Offene Sprechstunde', { delay: 70 });
    await t.step('Beginn und Ende wählen – Datum und Uhrzeit.', 'Choose start and end – date and time.');
    const begin = t.page.getByLabel(/^Beginn/);
    await t.click(begin, { after: 200 }); await begin.fill('2026-10-06T16:00'); await t.wait(500);
    const end = t.page.getByLabel(/^Ende/).first();
    await t.click(end, { after: 200 }); await end.fill('2026-10-06T18:00'); await t.wait(700);
    await t.step('Wiederholung: z. B. „wöchentlich“ – dann Wochentag und Ende der Serie festlegen.', 'Repeat: e.g. “wöchentlich” (weekly) – then choose the weekday and when the series ends.');
    await t.select(t.page.getByLabel('Wiederholen', { exact: true }), { label: 'wöchentlich' });
    await t.wait(800);
    await t.ring('fieldset:has(legend:has-text("Wiederholung")), [role=group][aria-label="Wiederholung"]', 1800).catch(() => {});
    await t.click('main input[data-rr="end"][value="count"]', { after: 300 });
    await t.type('main input[data-rr="count"]', '8', { clear: true });
    await t.ring('main .rr-summary, main [data-rr-summary]', 1400).catch(() => {});
    t.poster();
    await t.type(t.page.getByLabel(/^Ort/).first(), 'Beratungsraum 1', { delay: 60 });
    await t.step('„Speichern“ – der Termin erscheint in Liste, Kalender und auf der Website.', '“Speichern” – the event appears in the list, the calendar and on the website.');
    await t.click('main button:has-text("Speichern") >> nth=0', { after: 1800 });
    s.after = true;
    await t.step('Auf der Website öffnen: Angemeldet lässt sich der Termin direkt auf seiner Detailseite ändern.', 'Open it on the website: while signed in you can change the event right on its detail page.');
    const view = t.page.locator('main a.adm-btn:has-text("Ansehen")').first();
    await t.hover(view, 500);
    await t.goto(await view.getAttribute('href'));
    await t.step('Oben auf „Bearbeiten“ umschalten, dann Texte mit gestricheltem Rahmen anklicken und tippen.', 'Switch to “Bearbeiten” (edit) at the top, then click texts with a dashed frame and type.');
    await barMode(t, 'Bearbeiten');
    const title = t.page.locator('main [data-edit], main [contenteditable]').first();
    await t.click(title, { after: 300 });
    await t.page.keyboard.press(meta + '+a');
    await t.page.keyboard.type('Offene Sprechstunde für Eltern', { delay: 80 });
    await t.wait(800);
    await t.step('„Speichern“ – veröffentlichte Einträge sind danach sofort für alle sichtbar.', '“Speichern” (save) – published entries are then visible to everyone right away.');
    await t.click(`${BAR} button:has-text("Speichern"):visible`, { after: 1800 });
    await t.step('Menü „⋯“ › „Alle Felder bearbeiten“ öffnet rechts alle Felder – auch Datum und Wiederholung.', 'Menu “⋯” › “Alle Felder bearbeiten” (edit all fields) opens every field on the right – including date and repetition.');
    await barMore(t, 'Alle Felder bearbeiten');
    await t.wait(1200);
    await t.step('Ändern und unten „Speichern“ – oder schließen, wenn alles passt.', 'Change something and click “Speichern” at the bottom – or close if everything is fine.', 2400);
  },
  async cleanup(t, s) {
    const now = await entryIds(t, 'termine');
    for (const id of now.filter((id) => !s.before.includes(id))) await t.post(`/admin/data/termine/${id}/delete`, {});
  },
});

add({
  slug: 'r-anfragen', title: 'Anfragen im Posteingang lesen', account: 'praxis',
  async run(t, s) {
    await t.goto('/admin');
    await t.step('Neue Online-Anfragen zeigt die Zahl neben „Anfragen“ im Menü.', 'New online requests are counted next to “Anfragen” in the menu.');
    await t.click('#adm-mainnav a[href$="/admin/requests"]', { after: 1200 });
    await t.step('Oben den Eingang wählen, z. B. Rezeptanfragen. Die Inhalte sind verschlüsselt gespeichert.', 'Choose the inbox at the top, e.g. prescription requests. Contents are stored encrypted.');
    await t.ring('nav[aria-label="Eingänge"]', 1400);
    await t.step('Geheimen Schlüssel aus dem Passwortmanager einfügen und „Entschlüsseln“.', 'Paste the secret key from your password manager and click “Entschlüsseln” (decrypt).');
    await t.click('#secret', { after: 200 });
    await t.page.fill('#secret', t.acc.requestKey || '');
    await t.keys(meta + '+v', process.platform === 'darwin' ? '⌘ V' : 'Strg V');
    await t.click('form.adm-unlock button[type=submit]', { after: 1800 });
    t.poster();
    await t.step('Jetzt sind die Angaben lesbar – nur in dieser Ansicht, nach dem Neuladen wieder verschlossen.', 'The details are now readable – only in this view; after reloading they are locked again.');
    await t.hover('main article >> nth=0', 1500);
    await t.step('„Drucken“ und „Kopieren“ helfen beim Übertragen in Ihr Verwaltungssystem.', '“Drucken” (print) and “Kopieren” (copy) help to transfer it into your management system.');
    await t.hover('main article button:has-text("Kopieren")', 1200);
    await t.step('Status setzen: „In Bearbeitung“ oder „Als erledigt markieren“. „Zuweisen“ zeigt, wer sich kümmert.', 'Set the status: “In Bearbeitung” (in progress) or “Als erledigt markieren” (done). “Zuweisen” shows who takes care.');
    const form = t.page.locator('main article form:has(button:has-text("In Bearbeitung"))').first();
    s.statusUrl = await form.getAttribute('action');
    await t.click(form.locator('button'), { after: 1800 });
    await t.step('Erledigte Anfragen löscht das System nach der eingestellten Frist automatisch.', 'Completed requests are deleted automatically after the configured period.', 2400);
  },
  async cleanup(t, s) { if (s.statusUrl) await t.post(s.statusUrl, { status: 'neu', back: 'neu' }); },
});

add({
  slug: 'r-support', title: 'Problem melden & Wissensdatenbank', account: 'demo',
  async run(t, s) {
    await t.goto('/admin');
    await t.step('Etwas klappt nicht? Unten links „Hilfe & Support“ › „Problem melden“ – auf jeder Seite der Verwaltung.', 'Something doesn’t work? Bottom left “Hilfe & Support” › “Problem melden” (report a problem) – on every admin page.');
    await t.click('.adm-helpbox__sum', { after: 900 });
    await t.click('.adm-side a[data-support-report]', { after: 1300 });
    await t.step('Art wählen (Frage, Fehler, Wunsch) und kurz beschreiben, was passiert ist.', 'Pick the type (question, bug, wish) and briefly describe what happened.');
    await t.click('main label:has-text("Fehler")', { after: 400 });
    await t.type(t.page.getByLabel('Kurzer Titel'), 'Änderung nicht sichtbar', { delay: 60 });
    await t.type('#sp-body', 'Ich habe die Seite gespeichert, aber Besucher sehen die Änderung nicht.', { delay: 35 });
    await t.wait(1200);
    t.poster();
    await t.step('Rechts erscheinen schon passende Artikel aus der Wissensdatenbank – vielleicht hilft einer sofort.', 'Matching knowledge-base articles appear on the right – maybe one helps right away.');
    await t.ring('aside[aria-label="Vielleicht hilft das schon"], main [role=complementary]', 1800).catch(() => {});
    await t.step('Technische Angaben (Browser, Seite, Version) gehen automatisch mit. Dann „Meldung senden“.', 'Technical details (browser, page, version) are attached automatically. Then “Meldung senden” (send).');
    await t.cut(() => t.click('main button:has-text("Meldung senden")', { after: 300 }).then(() => t.page.waitForURL(/meldung\/\d+/, { timeout: 90000 })));
    s.issue = t.page.url().match(/meldung\/(\d+)/)?.[1];
    await t.step('Die Antwort des Support-Teams kommt hier und per E-Mail. Neue Antworten zählt „Hilfe & Support“.', 'The support team replies here and by email. “Hilfe & Support” counts new replies.');
    await t.wait(800);
    await t.step('Selbst nachschlagen: Support › Wissensdatenbank – mit Suche.', 'Look it up yourself: Support › Wissensdatenbank (knowledge base) – with search.');
    await t.goto('/admin/support/wissen');
    await t.type(t.page.getByLabel('Wissensdatenbank durchsuchen'), 'Entwurf', { delay: 90 });
    await t.page.keyboard.press('Enter');
    await t.wait(1500);
    await t.click('main h3 a >> nth=0', { after: 1600 });
    await t.scroll(300, 1200);
    await t.step('Unter jedem Artikel: „Hilfreich?“ – so lernt die Wissensdatenbank dazu.', 'Below every article: “Hilfreich?” (helpful?) – this is how the knowledge base improves.', 2400);
  },
  async cleanup(t, s) { if (s.issue) await t.post(`/admin/support/meldung/${s.issue}/loeschen`, {}); },
});

add({
  // Echte lokale KI: Text verbessern auf „fluid“ (Probeseite aus „Über uns“, nur erzeugte Bilder und Katzenfotos),
  // Übersetzen in der Verwaltung von „praxis“ (einzige Website mit zweiter Sprache und echter KI; nur die Kategorie
  // „Vorsorge“ – Name + Beschreibung, kein Bildfeld, keine Website-Ansicht). „demo“ nutzt in der Testkopie einen Fake-Anbieter.
  slug: 'r-ki-texte', title: 'KLXM Ai: Text verbessern & übersetzen', account: 'fluid',
  async setup(t, s, ACC) {
    await probePage(t, s, '/ueber-uns', 'Über uns – Probeseite KI', 'probeseite-ki');
    await t.signIn(ACC.praxis);
    const pr = t.as(ACC.praxis);
    // Darstellung der Verwaltung auf „praxis“ für die Aufnahme hell (Einstellung des Kontos stellt cleanup wieder her)
    await pr.goto('/admin/account');
    const pref = await t.page.evaluate(() => ({ appearance: document.querySelector('select[name=appearance]')?.value ?? '', locale: document.querySelector('select[name=locale]')?.value ?? '' }));
    if (pref.appearance !== 'light') { s.prPref = pref; await pr.post('/admin/account/locale', { ...pref, appearance: 'light' }); }
    s.before = await entryIds(pr, 'kategorien');
  },
  async run(t, s, ACC) {
    await t.goto(s.path + '?edit=1');
    await t.step('In jedem Textfeld steht „✦ KI“: Text anklicken, in der Formatierungsleiste „✦ KI“ wählen.', 'Every text field has “✦ KI”: click into the text, then choose “✦ KI” in the formatting bar.');
    const rich = t.page.locator('main [data-edit]:not([contenteditable="plaintext-only"]) p').first();
    await t.click(rich, { after: 700 });
    await t.click(t.page.locator('button:visible:has-text("KI")').first(), { after: 1000 });
    await t.step('„Verbessern“, „Kürzen“, „Einfacher“ … oder ein freier Auftrag. Markierter Text = nur dieser Teil.', '“Verbessern” (improve), “Kürzen” (shorten), “Einfacher” (simpler) … or a free prompt. Selected text = only that part.');
    await t.cut(async () => {
      await t.click(t.page.locator('button:visible:has-text("Verbessern")').first(), { after: 300 });
      await t.page.locator('[data-kia-apply]').first().waitFor({ timeout: 120000 });
    });
    t.poster();
    await t.step('Ein Vorschlag, kein Automatismus: Vorschau und „Änderungen“ prüfen, dann „Übernehmen“ oder „Verwerfen“.', 'A suggestion, not autopilot: check the preview and “Änderungen” (changes), then “Übernehmen” (apply) or “Verwerfen” (discard).');
    await t.click('button[data-view="diff"]', { after: 2200 });
    await t.click('[data-kia-apply]', { after: 1400 });
    await t.step('Übernommen wird in den Entwurf – online geht es erst mit „Veröffentlichen“.', 'It goes into the draft – it only goes live with “Veröffentlichen” (publish).');
    await t.click('[aria-label="Redaktion"] button:has-text("Speichern")', { after: 1400 });
    await t.step('Übersetzen: KLXM Ai › Übersetzen zeigt, was in anderen Sprachen noch fehlt (im Video eine Website mit Englisch).', 'Translate: KLXM Ai › Übersetzen shows what is still missing in other languages (here a website with English).');
    await t.goto(ACC.praxis.url.replace(/\/$/, '') + '/admin/ai/uebersetzen');
    const entry = t.page.locator('main li').filter({ hasText: 'Vorsorge' }).filter({ hasText: 'Kategorien' }).locator('button:has-text("Übersetzung anlegen")').first();
    await entry.evaluate((el) => el.scrollIntoView({ block: 'center', behavior: 'smooth' }));
    await t.wait(1200);
    await t.step('„Übersetzung anlegen“ erstellt einen Entwurf in der anderen Sprache.', '“Übersetzung anlegen” (create translation) creates a draft in the other language.');
    await t.click(entry, { after: 2200 });
    await t.step('„✦ Aus Deutsch übersetzen“: Texte auswählen, „Vorschläge erzeugen“.', '“✦ Aus Deutsch übersetzen” (translate from German): pick texts, then “Vorschläge erzeugen” (generate suggestions).');
    await t.click(t.page.locator('[data-kia-translate-form]').first(), { after: 1200 });
    await t.cut(async () => {
      await t.click('[data-kia-go]', { after: 300 });
      await t.page.waitForFunction(() => { const b = document.querySelector('[data-kia-take]'); return b && !b.disabled && !document.querySelector('.kia-busy, [aria-busy="true"]'); }, null, { timeout: 240000 });
    });
    await t.step('Jede Übersetzung prüfen und bei Bedarf ändern – übernommen wird nur, was angehakt ist.', 'Check every translation and edit if needed – only ticked items are applied.');
    await t.scroll(250, 1400);
    await t.click('[data-kia-take]', { after: 1400 });
    await t.step('Als Entwurf speichern und prüfen lassen – veröffentlicht wird von Menschen, nicht von der KI.', 'Save as a draft and have it checked – people publish, not the AI.');
    await t.click('main input[type=radio][value="draft"]', { after: 500 }).catch(() => {});
    await t.click('main button:has-text("Speichern") >> nth=0', { after: 2000 });
  },
  async cleanup(t, s, ACC) {
    await dropPage(t, s);
    const pr = t.as(ACC.praxis);
    const now = await entryIds(pr, 'kategorien');
    for (const id of now.filter((id) => !s.before.includes(id))) await pr.post(`/admin/data/kategorien/${id}/delete`, {});
    if (s.prPref) await pr.post('/admin/account/locale', s.prPref);
  },
});

/* ============================================================ Administration einer Website */

add({
  slug: 'a-benutzer-rollen', title: 'Benutzer & Rollen, 2FA verlangen', account: 'demo',
  async setup(t, s) {
    // Richtlinie „Anmeldung & Sicherheit“ (Core\Mfa) merken – cleanup stellt sie exakt wieder her
    await t.goto('/admin/users');
    s.pol = await t.page.evaluate(() => {
      const f = document.querySelector('#zwei-faktor'), d = { methods: '1' };
      for (const n of ['totp', 'passkey', 'passwordless']) if (f.querySelector(`input[name=${n}]`)?.checked) d[n] = '1';
      f.querySelectorAll('select[name^="req["]').forEach((sel) => { d[sel.name] = sel.value; });
      d.grace_days = f.querySelector('#us-grace')?.value ?? '0';
      return d;
    });
  },
  async run(t) {
    await t.goto('/admin/users');
    await t.step('Administration › Benutzer & Rollen: Jede Person bekommt ein eigenes Konto.', 'Administration › Benutzer & Rollen (users & roles): everyone gets their own account.');
    await t.step('Erst eine passende Rolle: „+ Neue Rolle“, Namen eintragen und Rechte anhaken.', 'First a suitable role: “+ Neue Rolle” (new role), enter a name and tick the permissions.');
    await t.click('main a[href$="/admin/roles/new"]', { after: 1200 });
    await t.type('#r-name', 'Praktikum', { delay: 80 });
    await t.type('#r-desc', 'Entwürfe schreiben, Bilder hochladen', { delay: 45 });
    for (const label of ['Seiten bearbeiten (als Entwurf)', 'Dateien hochladen und bearbeiten', 'Einträge anlegen und bearbeiten']) {
      await t.click(t.page.getByLabel(label, { exact: true }), { after: 350 });
    }
    t.poster();
    await t.step('Was nicht angehakt ist, sieht die Rolle nicht – z. B. kein „Veröffentlichen“.', 'Whatever is not ticked stays hidden for the role – e.g. no “Veröffentlichen” (publish).');
    await t.click('main button:has-text("Rolle speichern")', { after: 1500 });
    await t.step('„Neuen Benutzer anlegen“: Name, E-Mail, Startpasswort (persönlich übergeben) und Rolle.', '“Neuen Benutzer anlegen” (new user): name, email, initial password (hand over in person) and role.');
    await t.page.locator('#u-name').scrollIntoViewIfNeeded();
    await t.type('#u-name', 'Erika Beispiel', { delay: 70 });
    await t.type('#u-email', 'erika@example.test', { delay: 55 });
    await t.blur('#u-pw');
    await t.type('#u-pw', 'Nur-fuer-die-Aufnahme-' + Math.random().toString(36).slice(2, 10), { delay: 25 });
    await t.select('#u-role', { label: 'Praktikum' });
    await t.click('main form[action$="/admin/users"] button[type=submit]', { after: 1500 });
    await t.page.locator('#zwei-faktor').scrollIntoViewIfNeeded();
    await t.step('„Anmeldung & Sicherheit“: erlaubte Verfahren – Authenticator-App, Passkeys, Anmeldung ohne Passwort.', '“Anmeldung & Sicherheit” (sign-in & security): allowed methods – authenticator app, passkeys, passwordless sign-in.');
    await t.ring('#zwei-faktor fieldset >> nth=0', 1400);
    await t.step('Je Rolle den zweiten Faktor wählen – z. B. Redaktion: „verlangt (App oder Passkey)“.', 'Choose the second factor per role – e.g. Redaktion (editors): “verlangt (App oder Passkey)” (required).');
    await t.select('#us-req-editor', 'any');
    await t.step('„Übergangsfrist“: 0 Tage = bei der nächsten Anmeldung einrichten. Dann „Speichern“.', '“Übergangsfrist” (grace period): 0 days = set up at the next sign-in. Then “Speichern” (save).');
    await t.click('#zwei-faktor button[type=submit]', { after: 1500 });
    await t.step('Betroffene richten App oder Passkey bei der nächsten Anmeldung ein. Netzwerk-Konten haben 2FA immer.', 'Those affected set up an app or passkey at their next sign-in. Network accounts always use 2FA.', 2600);
  },
  async cleanup(t, s) {
    await t.goto('/admin/users');
    const del = await t.page.locator('tr:has-text("erika@example.test") form[action*="/delete"]').first().getAttribute('action').catch(() => null);
    if (del) await t.post(del, {});
    await t.post('/admin/roles/praktikum/delete', {});
    if (s.pol) await t.post('/admin/users/2fa', s.pol);
  },
});

add({
  slug: 'a-website-daten', title: 'Website & Mehrsprachigkeit', account: 'demo',
  async setup(t, s) { await t.goto('/admin/settings'); s.claim = await t.page.getByLabel('Kurzbeschreibung (Claim)').inputValue(); },
  async run(t) {
    await t.goto('/admin/settings');
    await t.step('Website: Name, Adresse, Telefon, Öffnungszeiten – einmal gepflegt, überall auf der Website.', 'Website data: name, address, phone, opening hours – maintained once, shown everywhere.');
    await t.step('„Vorschau“ zeigt rechts, wo die Angabe auf der Website erscheint.', '“Vorschau” (preview) shows on the right where the value appears on the website.', 1200);
    await t.click('main [data-st-pvtoggle]', { after: 1800 });
    await t.type(t.page.getByLabel('Kurzbeschreibung (Claim)'), 'Beratung, Planung und Umsetzung – aus einer Hand.', { clear: true, delay: 45 });
    await t.wait(1200);
    t.poster();
    await t.step('„Speichern“ – diese Angaben gelten sofort, ohne Veröffentlichen.', '“Speichern” (save) – these values apply immediately, without publishing.');
    await t.click('main .adm-savebar button[type=submit]', { after: 1600 });
    await t.step('Zweite Sprache: oben „EN English“ – hier nur die Texte übersetzen, Telefon & Adresse gelten für alle.', 'Second language: “EN English” at the top – translate only the texts; phone & address apply to all.');
    await t.click('main nav[aria-label="Sprache"] a:has-text("English")', { after: 1600 });
    await t.ring('main .st-trans', 1600).catch(() => {});
    await t.step('Welche Sprachen es gibt, legen Sie unter Grundeinstellungen › Sprachen fest.', 'Which languages exist is set under Grundeinstellungen › Sprachen (languages).');
    await t.goto('/admin/system#sprachen');
    await t.click('main [role=tab]:has-text("Sprachen")', { after: 1200 });
    await t.hover('main button:has-text("+ Sprache hinzufügen")', 1200);
    await t.step('Die erste aktive Sprache ist Standard; jede weitere bekommt ein Präfix wie /en/.', 'The first active language is the default; every further one gets a prefix like /en/.');
    await t.step('Seiten und Einträge übersetzen: in der Seitenliste bzw. mit „✦ Aus Deutsch übersetzen“ (KI).', 'Translate pages and entries: in the page list or with “✦ Aus Deutsch übersetzen” (AI).', 2600);
  },
  async cleanup(t, s) {
    await t.goto('/admin/settings');
    await t.page.getByLabel('Kurzbeschreibung (Claim)').fill(s.claim || '');
    await Promise.all([t.page.waitForLoadState(), t.page.click('main .adm-savebar button[type=submit]')]);
  },
});

add({
  slug: 'a-design', title: 'Design: Style-Editor, Vorlagen, Navigation', account: 'demo',
  async run(t) {
    await t.goto('/admin/design');
    await t.step('Administration › Design: Farben, Schrift und Formen des Kits für diese Website.', 'Administration › Design: colours, fonts and shapes of the kit for this website.');
    await t.step('Vorlagen füllen das Formular mit einem stimmigen Satz – die Vorschau rechts zeigt es sofort.', 'Presets fill the form with a matching set – the preview on the right shows it right away.', 1400);
    await t.click('main button:has-text("Mint Tech")', { after: 2200 });
    await t.click('main button:has-text("Warm Handwerk")', { after: 2200 });
    t.poster();
    await t.step('Jede Farbe mit Kontrastprüfung (AA/AAA) – für helle und dunkle Darstellung.', 'Every colour with a contrast check (AA/AAA) – for light and dark mode.');
    await t.ring('main [role=tabpanel] >> nth=0', 1600).catch(() => {});
    await t.step('Reiter „Typografie“, „Navigation“, „Kopfbereich: Suche & Aktionen“ – Schrift, Menü, Suchfeld und Button oben.', 'Tabs “Typografie”, “Navigation”, “Kopfbereich: Suche & Aktionen” (header: search & actions) – fonts, menu, search field and button at the top.');
    await t.click('main [role=tab]:has-text("Typografie")', { after: 1500 });
    await t.click('main [role=tab]:has-text("Navigation")', { after: 1600 });
    await t.click('main [role=tab]:has-text("Kopfbereich")', { after: 1800 });
    await t.step('Vorschau prüfen: Seite wählen, „Mobil“ und „Dunkel“ umschalten.', 'Check the preview: pick a page, switch to “Mobil” (mobile) and “Dunkel” (dark).');
    await t.click('main button:has-text("Mobil")', { after: 1600 });
    await t.click('main button:has-text("Dunkel")', { after: 1800 });
    await t.step('„Speichern“ stellt das Design online, „Verlauf“ holt frühere Stände zurück. Hier: „Verwerfen“.', '“Speichern” puts the design online, “Verlauf” (history) restores earlier versions. Here: “Verwerfen” (discard).');
    await t.hover('main button:has-text("Speichern")', 900);
    await t.click('main button:has-text("Verwerfen")', { after: 1800 });
  },
});

add({
  slug: 'a-datentabelle', title: 'Datentabelle anlegen', account: 'demo',
  async run(t) {
    await t.goto('/admin/data');
    await t.step('Daten › „+ Neue Tabelle“: eigene Inhalte wie Kurse, Produkte oder Stellen.', 'Data › “+ Neue Tabelle” (new table): your own content such as courses, products or jobs.');
    await t.click('main a[href$="/admin/data/new"]', { after: 1400 });
    await t.type(t.page.getByLabel(/^Name \(Mehrzahl\)/), 'Kurse', { delay: 80 });
    await t.type(t.page.getByLabel('Einzahl', { exact: true }), 'Kurs', { delay: 80 });
    await t.step('Symbol wählen: suchen, z. B. „Schule“ – erscheint in Menü und Listen.', 'Choose an icon: search, e.g. “Schule” (school) – shown in the menu and lists.');
    await t.click('main .icp__btn', { after: 700 });
    await t.page.keyboard.type('Schule', { delay: 110 });
    await t.wait(900);
    await t.click('.icp__pop [role=option] >> nth=0', { after: 900 });
    await t.step('Felder hinzufügen: unten den Typ wählen und benennen – Datum, Ja/Nein, Link …', 'Add fields: choose the type below and name it – date, yes/no, link …');
    const addField = async (type, label) => {
      await t.click(t.page.locator(`main button[data-add-field="${type}"]`), { after: 700 });
      const last = t.page.locator('main li.dt-field').last();
      await t.type(last.locator('input[data-label]'), label, { delay: 70, clear: true });
      await last.locator('input[data-label]').dispatchEvent('input');   // Kurzname steht – Auswahl der Bedingungen aktualisieren
      return last;
    };
    await addField('datetime', 'Beginn');
    await addField('bool', 'Online-Kurs');
    const link = await addField('url', 'Link zum Raum');
    t.poster();
    await t.step('Bedingungen: „Link zum Raum“ nur zeigen, wenn „Online-Kurs“ = Ja.', 'Conditions: show “Link zum Raum” only when “Online-Kurs” = yes.');
    await t.click(link.locator('summary:has-text("Bedingungen")'), { after: 700 });
    await t.click(link.locator('button[data-rule-add="visible_if"]'), { after: 700 });
    const rule = link.locator('[data-cond-grp="visible_if"] [data-rule]').first();
    await t.select(rule.locator('select').first(), { label: 'Online-Kurs' });
    const val = rule.locator('[data-rule-val] select, [data-rule-val] input').first();
    if (await val.evaluate((e) => e.tagName === 'SELECT')) await t.select(val, { label: 'Ja' }); else await t.type(val, 'Ja');
    await t.step('Rechts: Adresse der Detailseiten, öffentliches Formular und Website-Suche.', 'On the right: detail-page address, public form and website search.');
    await t.type(t.page.getByLabel('Adresse der Detailseiten'), 'kurse', { delay: 90 });
    await t.click(t.page.getByLabel('Besucher können Einträge anlegen'), { after: 700 });
    await t.hover(t.page.getByLabel('In der Website-Suche'), 1000);
    await t.step('„Tabelle anlegen“ – danach steht sie links im Menü und ist sofort nutzbar.', '“Tabelle anlegen” (create table) – it then appears in the menu on the left, ready to use.');
    await t.click('main button:has-text("Tabelle anlegen")', { after: 2200 });
    await t.step('Kalender: unter „Felder & Einstellungen“ „Als Kalender nutzen“ und das Feld „Beginn“ wählen.', 'Calendar: in “Felder & Einstellungen” (fields & settings) tick “Als Kalender nutzen” and choose the “Beginn” field.');
    await t.click('a[href$="/admin/data/kurse/schema"]', { after: 1500 });
    await t.click(t.page.getByLabel('Als Kalender nutzen'), { after: 800 });
    await t.select('select[name="settings[calendar][start]"]', { label: 'Beginn' });
    await t.click('main .dt-save button[type=submit]', { after: 2000 });
    await t.step('Jetzt gibt es Kalenderansicht, Wiederholungen und ein iCal-Abo für diese Tabelle.', 'Now the table has a calendar view, repetitions and an iCal subscription.', 2400);
  },
  async cleanup(t) {
    await t.goto('/admin/data/kurse/schema');
    if (t.page.url().includes('/kurse/')) await t.post('/admin/data/kurse/destroy', { confirm: 'kurse' });
  },
});

add({
  // Website „fluid“: erzeugtes Beispielvideo (tools/tutorials/samples/farbverlauf-animation.mp4) wird vorher hochgeladen und danach gelöscht
  slug: 'a-untertitel', title: 'Untertitel & Transkripte für Videos', account: 'fluid',
  async setup(t, s) {
    const up = await t.upload(t.file('farbverlauf-animation.mp4'), 'Farbverläufe in Blau, Pink und Grün (Beispielvideo)');
    s.video = up?.file?.id;
    if (!s.video) throw new Error('Beispielvideo nicht hochgeladen: ' + JSON.stringify(up).slice(0, 200));
    s.uploaded = true;
    const st = await t.getJson(`/admin/api/media/${s.video}/tracks`);
    s.tracks = (st.tracks || []).map((x) => x.id);
    s.transcripts = Object.keys(st.transcripts || {});
  },
  async run(t, s) {
    await t.goto('/admin/media');
    await t.step('Barrierefreiheit: Videos brauchen Untertitel, Audio ein Transkript. Medien › Videos.', 'Accessibility: videos need captions, audio needs a transcript. Media › Videos.');
    await t.click('button:has-text("Videos ohne Untertitel")', { after: 1200 });
    await t.click('[role=listbox] [role=option] >> nth=0', { after: 1400 });
    await t.step('Rechts unter „Untertitel & Transkript“: hochladen (.vtt/.srt), neu schreiben oder mit KI transkribieren.', 'On the right under “Untertitel & Transkript”: upload (.vtt/.srt), write new ones or transcribe with AI.');
    await t.ring('section:has(h3:has-text("Untertitel & Transkript")), [aria-label="Untertitel & Transkript"]', 1800).catch(() => {});
    await t.click('button:has-text("Neu schreiben")', { after: 1600 });
    await t.step('Video abspielen und an passender Stelle „+ Untertitel an aktueller Zeit“ – Text eintippen.', 'Play the video and at the right moment choose “+ Untertitel an aktueller Zeit” – type the text.');
    const play = 'button:has-text("Abspielen/Pause")';
    await t.click(play, { after: 700 });
    await t.click('button[data-add]', { after: 200 });
    await t.click(play, { after: 300 });
    await t.click('.cap-cue:last-child textarea[data-f="text"]', { after: 200 });
    await t.page.keyboard.type('Farbflächen wandern langsam über das Bild.', { delay: 45 });
    await t.click(play, { after: 1200 });
    await t.click('button[data-add]', { after: 200 });
    await t.click(play, { after: 300 });
    await t.click('.cap-cue:last-child textarea[data-f="text"]', { after: 200 });
    await t.page.keyboard.type('Blau, Pink und Grün gehen ineinander über.', { delay: 45 });
    t.poster();
    await t.step('Gut lesbar: höchstens 2 Zeilen à 42 Zeichen. Zeiten lassen sich mit ⇤ ⇥ anpassen.', 'Easy to read: at most 2 lines of 42 characters. Adjust times with ⇤ ⇥.');
    await t.ring('.cap-cue >> nth=0', 1600).catch(() => {});
    await t.step('Erst „Geprüft – veröffentlichen“ zeigt die Untertitel auf der Website. KI-Entwürfe immer prüfen!', 'Only “Geprüft – veröffentlichen” (checked – publish) shows the captions on the website. Always check AI drafts!');
    await t.click('button:has-text("Geprüft – veröffentlichen")', { after: 1800 });
    // Muss jetzt veröffentlicht sein (MediaTrackController::store nimmt status=published an) – sonst Aufnahme abbrechen
    const st = await t.getJson(`/admin/api/media/${s.video}/tracks`);
    const tr = (st.tracks || []).find((x) => !s.tracks.includes(x.id));
    if (tr?.status !== 'published') throw new Error('Neue Untertitel-Spur ist nicht veröffentlicht: ' + (tr?.status || 'fehlt'));
    s.vtt = tr.url;
    await t.step('Veröffentlicht: Das Video verschwindet aus „Videos ohne Untertitel“. Unter „Videos“ steht die Spur als „veröffentlicht“.', 'Published: the video leaves “Videos ohne Untertitel” (videos without captions). Under “Videos” the track shows as “veröffentlicht” (published).');
    await t.click('button.fx-src[data-src="kind"][data-value="video"]', { after: 1200 });
    await t.click('[role=listbox] [role=option] >> nth=0', { after: 1400 });
    await t.ring('.cap-list:not(.cap-list--tr) .cap-row >> nth=0', 2000).catch(() => {});
    await t.step('Transkript: „+ Transkript“ – oder den Text aus den Untertiteln übernehmen.', 'Transcript: “+ Transkript” – or take the text over from the captions.');
    await t.click('button:has-text("+ Transkript")', { after: 1400 });
    const from = t.page.locator('button[data-from]');
    if (await from.count()) await t.click(from, { after: 1200 });
    await t.click('button[data-pub]:visible', { after: 1800 });
    await t.step('Auf der Website erscheinen Untertitel im Player und „Transkript anzeigen“ unter dem Video.', 'On the website the captions show in the player, plus “Transkript anzeigen” (show transcript) below the video.', 2200);
    await t.hover('button:has-text("Mit KI transkribieren")', 1400).catch(() => {});
    await t.step('„Mit KI transkribieren“ (z. B. whisper.cpp auf dem Server) erzeugt einen Entwurf zur Prüfung.', '“Mit KI transkribieren” (e.g. whisper.cpp on the server) creates a draft for review.', 2400);
  },
  async cleanup(t, s) {
    if (!s.video) return;
    if (s.uploaded) { await t.post(`/admin/api/media/${s.video}/delete`, {}, true); return; }   // Spuren und Transkripte gehen mit
    const st = await t.getJson(`/admin/api/media/${s.video}/tracks`);
    for (const tr of (st.tracks || []).filter((x) => !s.tracks.includes(x.id))) await t.post(`/admin/api/media/${s.video}/tracks/${tr.id}/delete`, {}, true);
    for (const lang of Object.keys(st.transcripts || {}).filter((l) => !s.transcripts.includes(l))) await t.post(`/admin/api/media/${s.video}/transcript`, { lang, text: '' }, true);
  },
});

/** Seiteneinstellungen: ein Feld lesen / zurückschreiben (Vorbereiten/Aufräumen) */
const pageField = async (t, id, sel) => { await t.goto(`/admin/pages/${id}`); return t.page.locator(sel).inputValue(); };
const setPageField = async (t, id, sel, value) => {
  await t.goto(`/admin/pages/${id}`);
  await t.page.locator(sel).fill(value);
  await Promise.all([t.page.waitForLoadState(), t.page.click('main form button[type=submit]:has-text("Speichern")')]);
};

add({
  slug: 'a-api-freigabe', title: 'API-Token & „Eingereicht“ (Freigabe)', account: 'demo',
  async setup(t, s) {
    await t.goto('/ueber-uns');
    s.pageId = (await t.page.locator('a[href*="/admin/pages/"]').first().getAttribute('href')).match(/pages\/(\d+)/)[1];
    s.meta = await pageField(t, s.pageId, '#meta_description');
  },
  async run(t, s) {
    await t.goto('/admin/api-tokens');
    await t.step('API & MCP: Zugänge für Automatisierungen und KI-Assistenten wie Claude.', 'API & MCP: access for automations and AI assistants such as Claude.');
    await t.page.locator('main h2:has-text("Neuen Token erzeugen")').scrollIntoViewIfNeeded();
    await t.type(t.page.getByLabel(/^Name/).last(), 'Claude – Redaktion', { delay: 70 });
    await t.select(t.page.getByLabel('Berechtigung', { exact: true }), { label: 'Lesen und ändern' });
    await t.step('„Änderungen: Zur Freigabe“ – ein Mensch prüft jede Änderung, bevor sie online geht.', '“Änderungen: Zur Freigabe” (for approval) – a person checks every change before it goes live.');
    await t.select(t.page.getByLabel('Änderungen', { exact: true }), { label: 'Zur Freigabe' });
    await t.click('main button:has-text("Token erzeugen")', { after: 1600 });
    s.token = (await t.page.locator('#new-token').textContent()).trim();
    await t.step('Der Token erscheint nur einmal – sofort in den Passwortmanager kopieren (hier unkenntlich).', 'The token is shown only once – copy it to your password manager right away (blurred here).');
    await t.ring('#new-token', 1800);
    // Der „Assistent“ reicht über die REST-API eine Änderung ein (Antwort 202 = zur Freigabe)
    const r = await fetch(`${t.base}/api/v1/pages/${s.pageId}`, { method: 'PATCH', headers: { Authorization: `Bearer ${s.token}`, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ meta_description: 'Wer wir sind, wie wir arbeiten und was uns wichtig ist – das Team der Musterfirma stellt sich vor.' }) });
    s.status = r.status;
    await t.step('Ändert der Assistent jetzt etwas, landet es unter KLXM Ai › Eingereicht.', 'When the assistant now changes something, it lands in KLXM Ai › Eingereicht (submitted).');
    await t.goto('/admin/ai/eingereicht');
    await t.wait(800);
    await t.click('main a[href*="/admin/ai/eingereicht/"] >> nth=0', { after: 1600 });
    t.poster();
    await t.step('Herkunft (Token, Kanal) und Vorher/Nachher prüfen – dann „Übernehmen“ oder „Ablehnen“.', 'Check the origin (token, channel) and before/after – then “Übernehmen” (apply) or “Ablehnen” (reject).');
    await t.scroll(260, 1600);
    await t.click('main button:has-text("Übernehmen"):visible >> nth=0', { after: 2000 });
    await t.step('Jede Einreichung bleibt protokolliert. Tokens lassen sich jederzeit umstellen oder widerrufen.', 'Every submission stays logged. Tokens can be switched or revoked at any time.');
    await t.goto('/admin/api-tokens');
    await t.click('main tr:has-text("Claude – Redaktion") button:has-text("Widerrufen")', { after: 2400 });
  },
  async cleanup(t, s) {
    await t.goto('/admin/api-tokens');
    const f = t.page.locator('main tr:has-text("Claude – Redaktion") form:has(button:has-text("Widerrufen"))');
    for (const a of await f.evaluateAll((els) => els.map((e) => e.getAttribute('action')))) await t.post(a, {});
    if (s.pageId) await setPageField(t, s.pageId, '#meta_description', s.meta || '');
  },
});

add({
  slug: 'a-seo', title: 'SEO-Übersicht', account: 'fluid',
  async setup(t, s) {
    await t.goto('/datenschutz');
    s.pageId = (await t.page.locator('a[href*="/admin/pages/"]').first().getAttribute('href')).match(/pages\/(\d+)/)[1];
    s.meta = await pageField(t, s.pageId, '#meta_description');
  },
  async run(t, s) {
    await t.goto('/admin/ai/seo');
    await t.step('KLXM Ai › SEO-Übersicht: Wo fehlen Angaben für Suchmaschinen – oder sind doppelt?', 'KLXM Ai › SEO overview: where are search-engine descriptions missing – or duplicated?');
    await t.ring('main table >> nth=0', 1800);
    await t.step('Zeilen auswählen – hier nur „Datenschutz“ – und „Vorschläge für Auswahl erzeugen“.', 'Select rows – here only “Datenschutz” (privacy) – and click “Vorschläge für Auswahl erzeugen” (generate suggestions).');
    const all = t.page.getByLabel('Alle mit Problemen auswählen').first();
    if (await all.isChecked()) await t.click(all, { after: 500 }); else { await t.click(all, { after: 300 }); await t.click(all, { after: 500 }); }
    await t.click(t.page.getByLabel('„Datenschutz“ auswählen'), { after: 600 });
    await t.cut(async () => {
      await t.click('main [data-kia-seo-generate] >> nth=0', { after: 300 });
      await t.page.waitForFunction(() => { const b = document.querySelector('[data-kia-seo-apply]'); return b && !b.disabled; }, null, { timeout: 180000 });
    });
    t.poster();
    await t.step('Vorschlag lesen und bei Bedarf ändern (Zähler: ideal bis 160 Zeichen). Nur Angehaktes wird übernommen.', 'Read the suggestion and edit if needed (counter: ideally up to 160 characters). Only ticked rows are applied.');
    await t.hover('main tr:has-text("Datenschutz") textarea', 2200).catch(() => {});
    await t.click('main [data-kia-seo-apply] >> nth=0', { after: 600 });
    await confirmOk(t);   // Rückfrage „… Beschreibungen speichern?“
    await t.page.waitForLoadState('load').catch(() => {});
    await t.wait(1200);
    await t.step('Die Beschreibung steht jetzt in den Seiteneinstellungen – dort prüft auch der „SEO-Check“ Überschriften und Bilder.', 'The description is now in the page settings – where the “SEO-Check” also checks headings and images.');
    await t.goto(`/admin/pages/${s.pageId}`);
    await t.ring('#meta_description', 2200);
  },
  async cleanup(t, s) { if (s.pageId) await setPageField(t, s.pageId, '#meta_description', s.meta || ''); },
});

/* ============================================================ Agenturen / Netzwerk-Administration (Konto „network“) */

add({
  slug: 'n-netzwerk', title: 'Netzwerk-Dashboard & Website öffnen', account: 'network',
  async run(t) {
    await t.goto('/admin/network');
    await t.step('Netzwerk › Alle Websites: Zustand, Anfragen, Freigaben, Speicher und Sicherung auf einen Blick.', 'Network › all websites: status, requests, approvals, storage and backups at a glance.');
    await t.ring('main h1 ~ * >> nth=1', 1600).catch(() => {});
    await t.step('Suchen und filtern: „Mit Hinweisen“, „Wartung“, „Nicht live“.', 'Search and filter: “Mit Hinweisen” (with warnings), “Wartung” (maintenance), “Nicht live” (not live).');
    await t.type(t.page.getByLabel('Websites durchsuchen'), 'verein', { delay: 110 });
    await t.wait(1200);
    await t.type(t.page.getByLabel('Websites durchsuchen'), '', { clear: true });
    await t.click('main [role=group][aria-label="Filter"] button:has-text("Mit Hinweisen")', { after: 1600 });
    await t.click('main [role=group][aria-label="Filter"] button:has-text("Alle")', { after: 1000 });
    t.poster();
    await t.step('„Öffnen“ meldet Sie per Einmal-Token (SSO) in der Verwaltung dieser Website an – ohne zweites Passwort.', '“Öffnen” (open) signs you in to that website’s admin with a one-time token (SSO) – no second password.');
    const demo = t.page.locator('main article', { has: t.page.locator('h3', { hasText: 'demo.localhost' }) }).first();
    await t.hover(demo.locator('button:has-text("Öffnen")'), 1800);
    await t.step('Dort arbeiten Sie mit den Rechten der Agentur; „Website wechseln“ in der Seitenleiste führt zur nächsten.', 'There you work with agency rights; “Website wechseln” (switch website) in the sidebar leads to the next one.');
    await t.step('Unten: Netzwerk-Konten (immer mit Zwei-Faktor-Anmeldung), neue Website, geteilte Ressourcen, Protokoll.', 'Below: network accounts (always with two-factor sign-in), new website, shared resources, log.');
    await t.page.locator('#konten').scrollIntoViewIfNeeded();
    await t.ring('#konten', 2000);
    await t.step('Sperren Sie ein Netzwerk-Konto, enden seine Sitzungen auf allen Websites sofort.', 'Disabling a network account ends its sessions on all websites immediately.', 2400);
    await t.scroll(500, 1600);
  },
});

add({
  slug: 'n-website-anlegen', title: 'Neue Website anlegen', account: 'network',
  async run(t) {
    await t.goto('/admin/network#neu');
    await t.step('Netzwerk › „Neue Website“: Kurzname, Domains und Kit – wie „site:create“ auf der Kommandozeile.', 'Network › “Neue Website” (new website): short name, domains and kit – like “site:create” on the command line.');
    await t.page.locator('#neu').scrollIntoViewIfNeeded();
    await t.type('#ns-key', 'probe', { delay: 90 });
    await t.type('#ns-hosts', 'www.probe.example, probe.localhost', { delay: 55 });
    await t.select('#ns-theme', { label: 'Basis (neutral)' }).catch(() => t.select('#ns-theme', { index: 1 }));
    await t.click('#neu button[type=submit]', { after: 1800 });
    t.poster();
    await t.step('Angelegt: eigene Konfiguration mit eigenen Schlüsseln. Die nächsten Schritte stehen im Kasten.', 'Created: its own configuration with its own keys. The next steps are shown in the box.');
    await t.page.locator('#neu').scrollIntoViewIfNeeded();
    await t.ring('#neu .net-created', 2400).catch(() => {});
    await t.step('Domain im Hosting auf denselben Ordner (httpdocs/public) zeigen lassen – der erste Aufruf legt alles an.', 'Point the domain to the same folder (httpdocs/public) in your hosting – the first request creates everything.');
    await t.step('Dann „Öffnen“ – oder erstes lokales Konto über /admin/setup mit dem Setup-Token (hier unkenntlich).', 'Then “Öffnen” (open) – or create the first local account via /admin/setup with the setup token (blurred here).', 2800);
  },
  async cleanup(t, s, ACC) {
    // Die neue Konfiguration liegt auf dem Server: nur bei lokaler Testkopie („root“ im Zugang) automatisch entfernen
    const root = t.acc.root;
    const file = root && `${root.replace(/\/$/, '')}/config/sites/probe.php`;
    const fs = await import('node:fs');
    if (file && fs.existsSync(file)) fs.rmSync(file);
    else console.warn('  ! Bitte auf dem Server entfernen: config/sites/probe.php');
  },
});

add({
  slug: 'n-funktionen', title: 'Pakete & Funktionen je Projekt', account: 'network',
  async run(t) {
    await t.goto('/admin/network');
    await t.step('Jede Website hat einen Funktionsumfang: Preset „full“, „content“ oder „minimal“ plus einzelne Schalter.', 'Every website has a feature set: preset “full”, “content” or “minimal” plus individual switches.');
    const card = t.page.locator('main article', { has: t.page.locator('h3', { hasText: 'kunde' }) }).first();
    await t.ring(card.locator('dl'), 2200);
    await t.step('Die Agentur legt ihn in config/sites/{website}.php fest – die Kundschaft kann ihn nicht ändern.', 'The agency sets it in config/sites/{website}.php – customers cannot change it.');
    await t.hover(card.locator('dd[title]').first(), 1800).catch(() => {});
    await t.goto('/admin/system#info');
    await t.click('main [role=tab]:has-text("Systeminfo")', { after: 1200 });
    t.poster();
    await t.step('In jeder Website zeigt Grundeinstellungen › Systeminfo, was dort an- oder abgeschaltet ist.', 'In every website, Grundeinstellungen › Systeminfo shows what is switched on or off there.');
    const fx = t.page.locator('main :text("Funktionsumfang dieser Website")').first();
    await fx.scrollIntoViewIfNeeded();
    await t.ring(fx.locator('xpath=..'), 2600).catch(() => {});
    await t.step('Abgeschaltete Funktionen sperren Menüs, Rechte, API und MCP – auch für die Administration der Website.', 'Disabled features lock menus, permissions, API and MCP – even for the website’s own admins.');
    await t.step('Alle Schalter und Beispiele: Technische Dokumentation › Funktionsumfang & Erweiterungen.', 'All switches and examples: technical documentation › features & extensions.');
    await t.goto('/admin/hilfe/technik#funktionen');
    await t.wait(800);
    await t.scroll(260, 2400);
  },
});

/** Geteilte Medien: Demo-Pool „Beispiel-Pool (Demo)“ für die Website „fluid“ (nur erzeugte Bilder); cleanup löscht Datei und Pool */
const POOL = 'beispiel-pool';
const dropPool = async (t, s, ACC) => {
  if (ACC.fluid) {
    const f = t.as(ACC.fluid);
    await f.goto('/admin/media');
    // Pool-Dateien löschen (die Verweis-Einträge auf fluid zuerst), dann den leeren Pool
    // Verweis auf fluid (bzw. die noch nicht geteilte Grafik) löschen – bei Pool-Verweisen entfernt das nur den Verweis
    if (s.localId) await f.post(`/admin/api/media/${s.localId}/delete`, {}, true);
    const pooled = ((await f.getJson(`/admin/api/media?pool=${POOL}`).catch(() => ({}))).items || []);
    for (const m of pooled) await f.post(`/admin/api/media/${m.id}/delete`, { pool: POOL }, true);
  }
  await t.goto('/admin/system#pools');
  await t.post(`/admin/system/pools/${POOL}/delete`, {});
};

add({
  slug: 'n-geteilt', title: 'Geteilte Medien & geteilte Daten', account: 'network',
  async setup(t, s, ACC) {
    if (!ACC.fluid) throw new Error('Zugang „fluid“ fehlt (Website mit erzeugten Beispielbildern)');
    // Auf „fluid“ anmelden (die Aufnahme übernimmt die Sitzung), Reste eines abgebrochenen Laufs entfernen, erzeugte Grafik bereitlegen
    await t.signIn(ACC.fluid);
    await dropPool(t, {}, ACC);
    const up = await t.as(ACC.fluid).upload(t.file('farbverlauf-blau-orange.jpg'), 'Farbverlauf von Blau über Orange zu Gelb (Beispielgrafik)');
    s.localId = up?.file?.id;
    if (!s.localId) throw new Error('Beispielgrafik nicht hochgeladen');
  },
  async run(t, s, ACC) {
    await t.goto('/admin/system#pools');
    await t.click('main [role=tab]:has-text("Geteilte Medien")', { after: 1200 });
    await t.step('Grundeinstellungen › Geteilte Medien: zentrale Mediatheken (z. B. Logos, Markenbilder) für mehrere Websites.', 'Grundeinstellungen › Geteilte Medien (shared media): central libraries (e.g. logos, brand images) for several websites.');
    const box = t.page.locator('#panel-pools .pl-new');
    await box.scrollIntoViewIfNeeded();
    await t.step('„Neuen Pool anlegen“: Name, Kurzname und die Websites, die ihn nutzen dürfen.', '“Neuen Pool anlegen” (new pool): name, short name and the websites allowed to use it.');
    await t.type(box.locator('input[name=label]'), 'Beispiel-Pool (Demo)', { delay: 60 });
    await t.type(box.locator('input[name=key]'), POOL, { delay: 70 });
    await t.click(box.locator('label:has(input[value="fluid"])'), { after: 400 });
    await t.click(box.locator('button[type=submit]'), { after: 1600 });
    s.created = true;
    await t.click('main [role=tab]:has-text("Geteilte Medien")', { after: 800 }).catch(() => {});
    const card = t.page.locator('#panel-pools .pl-card', { has: t.page.locator(`code:text-is("${POOL}")`) }).first();
    await card.scrollIntoViewIfNeeded();
    await t.step('Je Pool: „Genutzt von“ (welche Websites) und „Pflegen dürfen“ – alle anderen verwenden die Dateien nur.', 'Per pool: “Genutzt von” (used by) and “Pflegen dürfen” (may maintain) – everyone else only uses the files.');
    await t.click(card.locator('fieldset:nth-of-type(2) label:has(input[value="fluid"])'), { after: 400 });
    await t.click(card.locator('button[type=submit]:has-text("Speichern")'), { after: 1600 });
    // Auf der Website „fluid“ (Sitzung aus setup): Datei in den Pool verschieben
    await t.goto(ACC.fluid.url.replace(/\/$/, '') + '/admin/media');
    await t.step('In der Mediathek: Datei mit rechter Maustaste › „In … verschieben (geteilt)“ – Verwendungen bleiben erhalten.', 'In the media library: right-click a file › “In … verschieben (geteilt)” (move to shared) – existing uses are kept.');
    const item = t.page.locator(`[role=listbox] [role=option][data-id="${s.localId}"]`).first();
    await t.moveTo(item);
    await item.click({ button: 'right' });
    await t.wait(900);
    await t.click(t.page.locator('[role=menu] button, .fx-menu button').filter({ hasText: 'Beispiel-Pool (Demo)' }).first(), { after: 900 });
    await confirmOk(t);
    s.shared = true;
    await t.step('Oben zwischen „Diese Website“ und dem Pool umschalten – dort liegt die Datei jetzt für alle Websites des Pools.', 'Switch between “Diese Website” (this website) and the pool at the top – the file now lives there for every website of the pool.');
    // Nach dem Verschieben lädt die Mediathek neu (Seitenleiste wird neu gezeichnet) – erst dann umschalten, notfalls zweiter Klick
    await t.page.waitForResponse((r) => r.url().includes('/admin/api/media'), { timeout: 3000 }).catch(() => {});
    await t.wait(1200);
    const scope = t.page.locator(`button[data-scope="${POOL}"]:visible`).first();
    await t.click(scope, { after: 1000 });
    if ((await scope.getAttribute('aria-pressed').catch(() => null)) !== 'true') await t.click(t.page.locator(`button[data-scope="${POOL}"]:visible`).first(), { after: 1000 });
    await t.page.waitForFunction((k) => document.querySelector(`button[data-scope="${k}"][aria-pressed="true"]`), POOL, { timeout: 8000 });
    await t.wait(900);
    t.poster();
    await t.ring(`[role=listbox] [role=option] >> nth=0`, 1400).catch(() => {});
    await t.goto('/admin/system#shared');
    await t.click('main [role=tab]:has-text("Geteilte Daten")', { after: 1500 });
    await t.step('Geteilte Daten: Eine Website (z. B. der Verband) besitzt die Tabelle, Mitglieder (Vereine) sehen und ergänzen Einträge.', 'Shared data: one website (e.g. the association) owns the table, members (clubs) see and add entries.');
    await t.scroll(200, 1600);
    await t.step('Beim Verein erscheinen die Einträge des Verbands in derselben Tabelle – nur lesbar, eigene bleiben bearbeitbar.', 'At the club the association’s entries appear in the same table – read-only, its own stay editable.');
    await t.goto('/admin/network#geteilt');
    await t.page.locator('#geteilt').scrollIntoViewIfNeeded();
    await t.ring('#geteilt', 2400);
    await t.step('Befehle für Sicherungen: pool:backup und shared:backup (siehe Technische Dokumentation › Geteilte Datentabellen).', 'Backup commands: pool:backup and shared:backup (see technical documentation › shared data tables).', 2600);
  },
  cleanup: dropPool,
});

add({
  slug: 'n-ki', title: 'KI einrichten (Ollama, whisper.cpp, Datenschutz)', account: 'network',
  async run(t) {
    await t.goto('/admin/system#ki');
    await t.click('main [role=tab]:has-text("KI")', { after: 1200 });
    await t.step('Grundeinstellungen › KI: je Website einschalten und Fähigkeiten wählen – Texte, Bilder, Suche, Untertitel.', 'Grundeinstellungen › KI: switch on per website and choose capabilities – text, images, search, captions.');
    await t.ring('main [role=tabpanel]:visible h3 >> nth=0', 1600).catch(() => {});
    await t.step('Tageslimit, Hinweise zu Zielgruppe und Stil sowie ein Glossar steuern den Assistenten.', 'A daily limit, notes on audience and style, and a glossary steer the assistant.');
    await t.hover(t.page.getByLabel('Hinweise für die KI (Zielgruppe, Anrede, Stil)'), 1600);
    await t.step('„Fähigkeiten“: Anbieter und Modell legt die Agentur in der Konfiguration fest – z. B. Ollama und whisper.cpp.', '“Fähigkeiten” (capabilities): provider and model are set by the agency in the configuration – e.g. Ollama and whisper.cpp.');
    const table = t.page.locator('main [role=tabpanel]:visible table').first();
    await table.scrollIntoViewIfNeeded();
    await t.ring(table, 2000);
    t.poster();
    await t.step('„Bleibt auf dem Server“: Mit lokalen Modellen verlassen Inhalte das Haus nicht. Externe Dienste sind gekennzeichnet.', '“Bleibt auf dem Server” (stays on the server): with local models content never leaves your premises. External services are marked.');
    await t.cut(async () => {
      await t.click(table.locator('button:has-text("Verbindung testen")').first(), { after: 300 });
      await t.wait(4000);
    });
    await t.step('Konfiguration und Befehle (ai:test, search:index) stehen in der Technischen Dokumentation › KLXM Ai.', 'Configuration and commands (ai:test, search:index) are in the technical documentation › KLXM Ai.');
    await t.goto('/admin/hilfe/technik#ki');
    await t.wait(900);
    await t.scroll(300, 2400);
  },
});

add({
  slug: 'n-staging-deploy', title: 'Staging & Deploy', account: 'network',
  async run(t) {
    await t.goto('/admin/network');
    await t.step('Staging ist eine eigene Installation mit Kopie der Live-Inhalte. Das Netzwerk zeigt, was nicht live ist.', 'Staging is a separate installation with a copy of the live content. The network shows what is not live.');
    await t.click('main [role=group][aria-label="Filter"] button:has-text("Nicht live")', { after: 1600 });
    await t.click('main [role=group][aria-label="Filter"] button:has-text("Alle")', { after: 900 });
    await t.step('Vor Eingriffen: „Wartung ▾“ – Sicherung jetzt, Wartungsmodus, Seiten-Cache leeren.', 'Before interventions: “Wartung ▾” – backup now, maintenance mode, clear page cache.');
    await t.click(t.page.locator('main article summary:has-text("Wartung")').first(), { after: 1800 });
    t.poster();
    await t.page.keyboard.press('Escape');
    await t.click(t.page.locator('main article summary:has-text("Wartung")').first(), { after: 600 });
    await t.step('Gesundheitsprüfung für Deploys: /health antwortet ohne Geheimnisse (ok, Version, Umgebung).', 'Health check for deploys: /health answers without secrets (ok, version, environment).');
    await t.goto('/health');
    await t.wait(2000);
    await t.step('Deploy per Kommandozeile: deploy/deploy.sh staging bzw. production – mit Sicherung, migrate, health und Rollback.', 'Deploy on the command line: deploy/deploy.sh staging or production – with backup, migrate, health and rollback.');
    await t.goto('/admin/hilfe/technik#deploy');
    await t.wait(800);
    await t.scroll(220, 2600);
  },
});

add({
  slug: 'n-theme', title: 'Eigenes Kit: Design-Tokens & Export', account: 'demo',
  async run(t) {
    await t.goto('/admin/design');
    await t.step('Jeder Wert im Style-Editor ist ein Design-Token (CSS-Variable) aus dem Kit – theme.php → „design“.', 'Every value in the style editor is a design token (CSS variable) from the kit – theme.php → “design”.');
    await t.ring('main [role=tabpanel] >> nth=0', 1800).catch(() => {});
    await t.step('„Export“ sichert die Werte als Datei – z. B. um sie in ein eigenes Kit oder eine andere Website zu übernehmen.', '“Export” saves the values as a file – e.g. to take them into your own kit or another website.');
    await t.click('main button:has-text("Export")', { after: 1800 });
    t.poster();
    await t.keys('Escape', 'Esc');
    await t.step('Eigenes Projekt = eigenes Kit: „kit:create“ kopiert das Start-Kit – der Core bleibt updatefähig.', 'Own project = own kit: “kit:create” copies the start kit – the core stays updatable.');
    await t.goto('/admin/hilfe/technik#start-kit');
    await t.wait(900);
    await t.scroll(320, 1800);
    await t.step('Aufbau, Tokens, Blöcke und Vorlagen beschreibt die Technische Dokumentation › Kits & Design.', 'Structure, tokens, blocks and presets are described in the technical documentation › kits & design.');
    await t.goto('/admin/hilfe/technik#design');
    await t.wait(800);
    await t.scroll(260, 2400);
  },
});

/* ============================================================ Neue Funktionen (1.0) */

/** Schreibende Anfragen im Aufnahme-Fenster abfangen und mit „ok“ beantworten (nichts wird gespeichert) */
const dryRun = async (t, match, allow = () => false) => {
  await t.page.route((u) => match.test(u.pathname), (route) => (['GET', 'HEAD'].includes(route.request().method()) || allow(route.request())
    ? route.continue()
    : route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, success: true }) })));
};

add({
  slug: 'r-uebersicht', title: 'Die neue Übersicht', account: 'demo',
  async run(t) {
    // „Übersicht anpassen“ speichert je Konto – in der Aufnahme abgefangen, cleanup setzt zusätzlich auf Standard zurück
    await dryRun(t, /\/admin\/api\/dashboard\/prefs$/);
    await t.goto('/admin');
    await t.step('Nach der Anmeldung: die Übersicht. Oben Begrüßung, Datum und Schnellaktionen für Ihre Rolle.', 'After signing in: the dashboard. At the top a greeting, the date and quick actions for your role.');
    await t.ring('.dash-hero', 1800);
    await t.step('Kennzahlen als Rundinstrumente: Seiten online, Einträge, Medien, Eingereicht – mit Trend über 30 Tage.', 'Key figures as dials: pages online, entries, media, submitted – with a 30-day trend.');
    await t.ring('#dash-figures .dash-tiles', 2000);
    await t.hover('#dash-figures .dash-tile >> nth=2', 900);
    t.poster();
    await t.step('„Was ist zu tun?“ – Aufgaben nach Dringlichkeit, jede mit Erklärung und direktem Link.', '“Was ist zu tun?” (what to do) – tasks by urgency, each with an explanation and a direct link.');
    await t.page.locator('#dash-todo').evaluate((el) => el.scrollIntoView({ block: 'start', behavior: 'smooth' }));
    await t.wait(900);
    await t.ring('#dash-todo .dash-todo', 1800);
    await t.hover('#dash-todo .dash-todo__item >> nth=0', 900);
    await t.step('Statistik ohne Besucher-Tracking: Aktivität der letzten 30 Tage – auch als Tabelle.', 'Statistics without visitor tracking: activity of the last 30 days – also as a table.');
    await t.page.locator('#dash-activity').evaluate((el) => el.scrollIntoView({ block: 'start', behavior: 'smooth' }));
    await t.wait(900);
    await t.click('#dash-activity summary', { after: 1400 }).catch(() => {});
    await t.step('„Zuletzt bearbeitet“: weiterarbeiten, wo Sie oder das Team aufgehört haben.', '“Zuletzt bearbeitet” (recently edited): continue where you or the team left off.');
    await t.ring('#dash-recent', 1800);
    await t.step('„Hilfe & Einstieg“: KLXM Ai fragen, Tutorials für Ihre Rolle und was neu ist.', '“Hilfe & Einstieg” (help & getting started): ask KLXM Ai, tutorials for your role and what’s new.');
    await t.ring('#dash-help', 1800);
    await t.page.evaluate(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
    await t.wait(900);
    await t.step('„Übersicht anpassen“: Karten verschieben, ausblenden oder zuklappen – je Konto.', '“Übersicht anpassen” (customise): move, hide or collapse cards – per account.');
    await t.click('[data-dash-customize]', { after: 1400 });
    await t.click('#dash-help [data-dash-move="up"]', { after: 1200 }).catch(() => {});
    await t.hover('#dash-activity [data-dash-hide]', 900).catch(() => {});
    await t.step('„Standard wiederherstellen“ bringt die ursprüngliche Anordnung zurück. „Fertig“ schließt.', '“Standard wiederherstellen” (restore default) brings back the original layout. “Fertig” (done) closes.');
    await t.hover('.dash-bar__reset button', 1000).catch(() => {});
    await t.click('[data-dash-customize]', { after: 1400 });
  },
  async cleanup(t) { await t.goto('/admin'); await t.post('/admin/api/dashboard/prefs', { do: 'reset' }); },
});

add({
  slug: 'a-quellen', title: 'Externe Quellen anbinden', account: 'demo',
  async run(t) {
    await dryRun(t, /^\/admin\/quellen\/\d+(\/.*)?$/, (req) => /(^|&|\b)do=preview\b|name="do"\r?\n\r?\npreview/.test(req.postData() || ''));   // Speichern/Abrufen nur zeigen – „Vorschau & Test“ speichert nichts
    await t.goto('/admin/quellen');
    await t.step('Daten › Externe Quellen: RSS-Feeds, JSON-APIs, XML und OpenImmo als Einträge einer Datentabelle.', 'Data › Externe Quellen (external sources): RSS feeds, JSON APIs, XML and OpenImmo as entries of a data table.');
    await t.ring('main table, main .src-list', 1800).catch(() => {});
    await t.step('Besucher laden nie etwas von der Quelle: Liste, Detailseite, Suche und API kommen aus der eigenen Tabelle.', 'Visitors never load anything from the source: list, detail page, search and API come from your own table.');
    await t.step('Vorlagen für den Start: RSS/Atom, OpenImmo, JSON-API oder XML mit XPath.', 'Templates to start with: RSS/Atom, OpenImmo, JSON API or XML with XPath.');
    await t.hover('main a[href*="preset=rss"] >> nth=1', 700);
    await t.hover('main a[href*="preset=openimmo"] >> nth=0', 900);
    await t.step('Das Beispiel „Immobilien“ liest eine OpenImmo-Datei aus einer Makler-Software.', 'The example “Immobilien” (real estate) reads an OpenImmo file from estate-agent software.');
    await t.click('main a[href$="/admin/quellen/3"]:has-text("Bearbeiten")', { after: 1400 });
    await t.step('Quelle: Name, Format und Adresse – oder die Datei rechts hochladen. Anmeldedaten werden verschlüsselt gespeichert.', 'Source: name, format and address – or upload the file on the right. Credentials are stored encrypted.');
    await t.ring('main form section >> nth=0', 1800).catch(() => {});
    await t.step('Abruf: Zeitplan, Zwischenspeicher und was mit Einträgen geschieht, die in der Quelle fehlen.', 'Fetching: schedule, cache and what happens to entries missing from the source.');
    await t.page.locator('main :text("Abruf")').first().evaluate((el) => el.scrollIntoView({ block: 'start', behavior: 'smooth' }));
    await t.wait(1600);
    await t.step('Zuordnung: Jedes Feld der Tabelle bekommt einen Pfad in der Quelle – mit Umwandlung, z. B. Zahl oder Datum.', 'Mapping: every table field gets a path in the source – with a conversion, e.g. number or date.');
    await t.page.locator('main :text("Zieltabelle & Zuordnung")').first().evaluate((el) => el.scrollIntoView({ block: 'start', behavior: 'smooth' }));
    await t.wait(1200);
    await t.scroll(420, 1600);
    t.poster();
    await t.step('„Vorschau & Test“ zeigt die ersten Einträge so, wie sie in der Tabelle ankämen – ohne zu speichern.', '“Vorschau & Test” (preview & test) shows the first entries as they would arrive in the table – without saving.');
    await t.click('main button:has-text("Vorschau & Test")', { after: 2000 });
    await t.ring('main [class*=preview], main table >> nth=0', 1800).catch(() => {});
    await t.step('Rechts: Stand, „Jetzt abrufen“ und das Protokoll der letzten Abrufe.', 'On the right: status, “Jetzt abrufen” (fetch now) and the log of recent fetches.');
    await t.page.evaluate(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
    await t.wait(900);
    await t.hover('main button:has-text("Jetzt abrufen")', 1200);
    await t.step('Übernommene Einträge stehen in „Immobilien“ – nur lesbar, weil die Quelle sie pflegt.', 'Imported entries are in “Immobilien” – read-only, because the source maintains them.');
    await t.goto('/admin/data/immobilien');
    await t.wait(1600);
  },
});

add({
  slug: 'a-consent', title: 'Cookie-Einwilligung mit dem Consent Kit', account: 'demo', consent: true,
  async run(t) {
    await dryRun(t, /./);   // alle schreibenden Anfragen abfangen: Einstellungen und Einwilligungen nur zeigen
    await t.goto('/admin/consent');
    await t.step('Cookie-Einwilligung (Erweiterung Consent Kit): Ohne einwilligungspflichtige Dienste gibt es keinen Hinweis – cookiefrei ab Werk.', 'Cookie consent (Consent Kit extension): without services that need consent there is no notice – cookie-free out of the box.');
    await t.step('Dienste: z. B. Statistik (Matomo) oder externe Medien (YouTube) – je Domain an- und abschaltbar.', 'Services: e.g. statistics (Matomo) or external media (YouTube) – switchable per domain.');
    await t.ring('main table >> nth=0', 1800).catch(() => {});
    await t.step('„Dienst hinzufügen“: Vorlagen mit Anbieter, Zweck, Cookies und Datenschutz-Link.', '“Dienst hinzufügen” (add service): templates with provider, purpose, cookies and privacy link.');
    await t.click('main a[href$="/admin/consent/templates"]', { after: 1600 });
    await t.scroll(300, 1400);
    await t.step('Design: Farben kommen aus dem Design der Website. Vorschau als Box, Leiste oder Dialog – hell und dunkel.', 'Design: colours come from the website design. Preview as box, bar or dialog – light and dark.');
    await t.goto('/admin/consent/design');
    const pv = (label) => t.page.locator('main button', { hasText: new RegExp('^' + label + '$') }).first();
    await t.click(pv('Leiste'), { after: 1200 });
    await t.click(pv('Dialog'), { after: 1200 });
    await t.click(pv('Dunkel'), { after: 1200 });
    await t.click(pv('Einstellungen'), { after: 1400 });
    t.poster();
    await t.click(pv('Hell'), { after: 600 });
    await t.click(pv('Box'), { after: 600 });
    await t.step('Auf der Website: Der Hinweis erscheint, bis Besucher entscheiden. „Alle ablehnen“ ist so leicht wie „Alle akzeptieren“.', 'On the website: the notice appears until visitors decide. “Alle ablehnen” (reject all) is as easy as “Alle akzeptieren” (accept all).');
    await t.goto('/baukasten/medien');
    await t.wait(900);
    await t.click(t.page.locator('consent-kit button:has-text("Einstellungen")').first(), { after: 1600 });
    await t.step('„Einstellungen“ zeigt jeden Dienst mit Zweck und Speicherdauer – einzeln wählbar.', '“Einstellungen” (settings) shows every service with purpose and storage period – selectable one by one.');
    await t.scroll(0, 1400);
    await t.click(t.page.locator('consent-kit button:has-text("Alle ablehnen")').first(), { after: 1400 });
    await t.step('Abgelehnte Inhalte zeigen einen Platzhalter: erst nach Klick lädt das Video vom Anbieter.', 'Rejected content shows a placeholder: the video only loads from the provider after a click.');
    await t.page.locator('.vembed').first().evaluate((el) => el.scrollIntoView({ block: 'center', behavior: 'smooth' }));
    await t.wait(1000);
    await t.ring('.vembed', 2000);
    await t.step('Jede Entscheidung steht anonym im Protokoll; Besucher ändern sie jederzeit über „Cookie-Einstellungen“.', 'Every decision is logged anonymously; visitors can change it at any time via “Cookie-Einstellungen” (cookie settings).');
    await t.goto('/admin/consent/log');
    await t.wait(1800);
  },
});

add({
  // Website „fluid“ (Erweiterung video_tools): erzeugtes Testbild-Video. Aufträge werden nur gezeigt, nicht gestartet.
  slug: 'r-videos', title: 'Videos optimieren und schneiden', account: 'fluid',
  async run(t) {
    await dryRun(t, /^\/admin\/api\/video-tools\/(media\/\d+\/(optimize|trim|poster|poster\/clear)|bulk)$/);
    await t.goto('/admin/media');
    await t.step('Medien › Videos: Die Video-Werkzeuge prüfen jedes Video und machen es fit fürs Web.', 'Media › Videos: the video tools check every video and make it fit for the web.');
    await t.click('button.fx-src[data-src="kind"][data-value="video"]', { after: 1200 });
    await t.click('[role=listbox] [role=option]:has-text("Beispiel: Testbild") >> nth=-1', { after: 1600 });
    await t.step('Rechts unter „Video-Werkzeuge“: Punktzahl, Codec, Auflösung, Bitrate und Empfehlungen.', 'On the right under “Video-Werkzeuge” (video tools): score, codec, resolution, bitrate and recommendations.');
    await t.page.locator('.vt-sec').evaluate((el) => el.scrollIntoView({ block: 'start', behavior: 'smooth' }));
    await t.wait(1000);
    await t.ring('.vt-score', 1600);
    await t.step('„Für Web optimieren …“: Preset wählen – z. B. Web 720p – als neue Version oder anstelle des Originals.', '“Für Web optimieren …” (optimise for web): choose a preset – e.g. Web 720p – as a new version or replacing the original.');
    await t.click('[data-vt-opt]', { after: 1400 });
    await t.click('dialog[open] label:has(input[value="web720"])', { after: 900 });
    t.poster();
    await t.hover('dialog[open] label:has(input[value="new"])', 900);
    await t.step('Der Auftrag läuft im Hintergrund auf dem Server – der Browser darf geschlossen werden.', 'The job runs in the background on the server – you may close the browser.');
    await t.hover('dialog[open] button:has-text("Im Hintergrund starten")', 1200);
    await t.click('dialog[open] button:has-text("Abbrechen")', { after: 900 });
    await t.step('„Schneiden …“: Anfang und Ende setzen (Tasten I und O), Bereich testen, Ausschnitt hinzufügen.', '“Schneiden …” (trim): set start and end (keys I and O), test the range, add the clip.');
    await t.page.locator('.vt-sec').evaluate((el) => el.scrollIntoView({ block: 'start', behavior: 'smooth' }));
    await t.wait(700);
    await t.click('[data-vt-trim]', { after: 1600 });
    await t.click('dialog[open] [data-vt-play]', { after: 2000 });
    await t.keys('i', 'I');
    await t.wait(2200);
    await t.keys('o', 'O');
    await t.click('dialog[open] [data-vt-play]', { after: 600 });
    await t.click('dialog[open] [data-vt-add]', { after: 1200 });
    await t.step('Verlustfrei (schnell) oder präzise – Untertitel werden passend mitgeschnitten.', 'Lossless (fast) or precise – captions are trimmed to match.');
    await t.ring('dialog[open] fieldset, dialog[open] label:has(input[name="vt-precise"])', 1800).catch(() => {});
    await t.click('dialog[open] button:has-text("Schließen")', { after: 900 });
    await t.step('„Poster …“: ein Standbild wählen – Video-Blöcke zeigen es vor dem Abspielen.', '“Poster …”: choose a still frame – video blocks show it before playback.');
    await t.page.locator('.vt-sec').evaluate((el) => el.scrollIntoView({ block: 'start', behavior: 'smooth' }));
    await t.wait(700);
    await t.click('[data-vt-poster]', { after: 1600 });
    await t.hover('dialog[open] [data-vt-take]', 1200);
    await t.click('dialog[open] button:has-text("Schließen")', { after: 900 });
    await t.step('„Alle Video-Aufträge“: Fortschritt, Ergebnis und Protokoll – auch Abbrechen und Wiederholen.', '“Alle Video-Aufträge” (all video jobs): progress, result and log – also cancel and retry.');
    await t.click('.vt-alljobs', { after: 2200 });
    await t.page.keyboard.press('Escape');
    await t.wait(800);
  },
});

add({
  // Konto „starter“ (Website mit dem Start-Kit). Die Ausgabe von kit:create ist echt (Testkopie, danach wieder entfernt),
  // hier als Terminal-Karte gezeigt – die Aufnahme legt kein Kit an.
  slug: 'n-kit-starter', title: 'Eigenes Kit mit dem Start-Kit', account: 'starter',
  async run(t) {
    const lines = KIT_CREATE.split('\n');
    await t.page.route(t.base + '/__tutorial/terminal', (r) => r.fulfill({ contentType: 'text/html; charset=utf-8', body: terminalHtml('php bin/console kit:create kanzlei', lines) }));
    await t.goto('/__tutorial/terminal');
    await t.step('Eigenes Kit für ein Projekt: „kit:create“ kopiert das Start-Kit und benennt alles um.', 'Your own kit for a project: “kit:create” copies the start kit and renames everything.');
    await t.page.evaluate(() => window.__typeCmd?.());
    await t.wait(2600);
    await t.step('Die Ausgabe nennt die neuen Ordner und die nächsten Schritte: anpassen, bauen, testen, prüfen.', 'The output lists the new folders and the next steps: adjust, build, test, check.');
    await t.page.evaluate(() => window.__showOut?.());
    await t.wait(2400);
    t.poster();
    await t.step('Das Start-Kit ist das kleinste vollständige Kit: sechs Beispielblöcke, hell und dunkel, eine Akzentfarbe.', 'The start kit is the smallest complete kit: six example blocks, light and dark, one accent colour.');
    await t.goto('/');
    await t.wait(900);
    await t.scroll(700, 2200);
    await t.scroll(700, 2000);
    await t.step('Farben, Schrift und Navigation stellen Sie wie gewohnt unter Design ein – die Werte sind Design-Tokens des Kits.', 'You set colours, fonts and navigation under Design as usual – the values are design tokens of the kit.');
    await t.goto('/admin/design');
    await t.wait(900);
    await t.click('main [role=tab] >> nth=1', { after: 1400 }).catch(() => {});
    await t.click('main [role=tab] >> nth=0', { after: 1000 }).catch(() => {});
    await t.step('theme.php ist in Abschnitte § 1–11 gegliedert. Die Anleitung: Technische Dokumentation › Kits & Design.', 'theme.php is divided into sections § 1–11. The guide: technical documentation › kits & design.');
    await t.goto('/admin/hilfe/technik#start-kit');
    await t.wait(900);
    await t.scroll(300, 2200);
  },
});

/** Ausgabe von „php bin/console kit:create kanzlei“ (Testkopie, 25.09.2026) */
const KIT_CREATE = `Kit „kanzlei“ angelegt – Kopie von „starter“, Präfix starter_ → kanzlei_
  themes/kanzlei/          theme.php, Blöcke, Templates, Startinhalte, lang/, tools/
  themes/kanzlei/assets/   CSS/JS-Quellen
  public/themes/kanzlei/   gebaute Assets (Kopie – nach Änderungen neu bauen)

Nächste Schritte:
  1. themes/kanzlei/theme.php öffnen: label, description, version; Farben in § 6 und assets/css/_tokens.css
  2. Bauen:      cd tools && pnpm run build
  3. Testen:     php bin/console site:create <key> <domain> kanzlei
  4. Prüfen:     php bin/console health --site=<key> · php themes/kanzlei/tools/contrast.php
  Anleitung:    Verwaltung → Hilfe → Technik → Kits & Design → „Eigenes Kit entwickeln“`;

/** Terminal-Karte (nur in der Aufnahme): Befehl wird getippt, dann erscheint die Ausgabe */
const terminalHtml = (cmd, lines) => {
  const esc = (x) => x.replace(/&/g, '&amp;').replace(/</g, '&lt;');
  return `<!doctype html><html lang="de"><meta charset="utf-8"><title>Terminal</title><style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:radial-gradient(60% 60% at 30% 30%,#1d3350,#0f1626);font:16px/1.55 ui-monospace,SFMono-Regular,Menlo,monospace;color:#dfe6f3}
.win{width:1080px;border-radius:14px;overflow:hidden;background:#0b0f18;box-shadow:0 40px 90px -30px rgba(0,0,0,.7),0 0 0 1px rgba(255,255,255,.1)}
.bar{height:36px;display:flex;align-items:center;gap:8px;padding:0 14px;background:#1a2130;color:#9aa6bb;font:600 13px system-ui}
.bar i{width:12px;height:12px;border-radius:50%;background:#555}.bar i:nth-child(1){background:#f26d5f}.bar i:nth-child(2){background:#f4be4f}.bar i:nth-child(3){background:#5ec269}
.bar span{margin:0 auto}
pre{margin:0;padding:22px 26px 28px;white-space:pre-wrap;min-height:420px}
.p{color:#5fe0cf}.c{color:#fff}.o{opacity:0;transition:opacity .4s}.o.on{opacity:1}.h{color:#ffb36b;font-weight:700}
.cur{display:inline-block;width:9px;height:1.1em;vertical-align:-3px;background:#dfe6f3;animation:b 1s steps(1) infinite}@keyframes b{50%{opacity:0}}
</style><body><div class="win"><div class="bar"><i></i><i></i><i></i><span>~/projekt/httpdocs</span></div><pre><span class="p">$ </span><span class="c" id="cmd"></span><span class="cur" id="cur"></span>
${lines.map((l, i) => `<span class="o" data-i="${i}">${/^(Kit |Nächste)/.test(l) ? `<span class="h">${esc(l)}</span>` : esc(l)}</span>`).join('\n')}</pre></div>
<script>
window.__typeCmd = () => { const t = ${JSON.stringify(cmd)}, el = document.getElementById('cmd'); let i = 0; const k = setInterval(() => { el.textContent = t.slice(0, ++i); if (i >= t.length) clearInterval(k); }, 55); };
window.__showOut = () => { document.getElementById('cur').remove(); document.querySelectorAll('.o').forEach((e, i) => setTimeout(() => e.classList.add('on'), i * 110)); };
</script></body></html>`;
};

/* ============================================================ Smartphone-Varianten (390 × 844) */

add({
  slug: 'r-ueberblick-mobil', title: 'Anmelden & Überblick auf dem Smartphone', account: 'demo', mobile: true, noLogin: true,
  async run(t) {
    await t.goto('/admin/login');
    await t.step('Die Verwaltung funktioniert auch auf dem Smartphone: Adresse + /admin, dann anmelden.', 'The admin also works on a smartphone: address + /admin, then sign in.');
    await t.type('#email', t.acc.email, { delay: 45 });
    await t.type('#password', t.acc.password, { delay: 30 });
    await t.click('button[type=submit]', { after: 1500 });
    await t.step('Die Übersicht passt sich an: Kennzahlen, „Was ist zu tun?“ und Statistik untereinander.', 'The dashboard adapts: key figures, “Was ist zu tun?” (what to do) and statistics one below the other.');
    await t.scroll(500, 1600);
    await t.scroll(-500, 800);
    await t.step('Oben links „Menü öffnen“ – das Menü kommt als Schublade von links.', 'Top left “Menü öffnen” (open menu) – the menu slides in from the left.');
    await t.click('.adm-top__menu', { after: 1400 });
    t.poster();
    await t.step('„Daten“, „Medien“, „Anfragen“ … wie am Computer. Favoriten stehen ganz oben.', '“Daten”, “Medien”, “Anfragen” … just like on a computer. Favourites are at the top.');
    await t.click('#adm-mainnav a[href$="/admin/data"]', { after: 1600 });
    await t.step('Die Lupe oben rechts öffnet die Suche – Seiten, Einträge, Medien und Einstellungen.', 'The magnifier at the top right opens search – pages, entries, media and settings.');
    await t.click('.adm-top__search', { after: 900 });
    await t.page.keyboard.type('Kontakt', { delay: 120 });
    await t.wait(2000);
    await t.page.keyboard.press('Escape');
    await t.step('Tipp: Längere Texte schreiben Sie bequemer am Computer – kurze Änderungen gehen überall.', 'Tip: longer texts are easier on a computer – quick changes work anywhere.', 2400);
  },
});

add({
  slug: 'r-seite-bearbeiten-mobil', title: 'Seite auf dem Smartphone ändern', account: 'demo', mobile: true,
  // Unter 768 px: Aktionsleiste unten (Abbrechen · Speichern · Veröffentlichen), Seitenleiste „Block“ darüber; Website-Menü (basis) im Bearbeitungsmodus ausgeblendet
  setup: (t, s) => probePage(t, s),
  async run(t, s) {
    await t.goto(s.path);
    await t.step('Auch unterwegs: Seite öffnen, oben „Bearbeiten“ antippen.', 'On the go as well: open the page, tap “Bearbeiten” (edit) at the top.');
    await t.click(t.page.locator(`${BAR} a:visible:has-text("Bearbeiten")`).first(), { after: 1800 });
    await t.step('Die Aktionen stehen jetzt unten: Abbrechen, Speichern, Veröffentlichen.', 'The actions are now at the bottom: Abbrechen (cancel), Speichern (save), Veröffentlichen (publish).', 1000);
    await t.ring(t.page.locator(`${BAR} .cms-bar__edit`).first(), 2200);
    await t.step('Überschrift antippen und neu schreiben.', 'Tap the heading and type a new one.', 1200);
    const h1 = t.page.locator('main h1[data-edit]').first();
    await t.click(h1, { after: 300 });
    await t.page.keyboard.press(meta + '+a');
    await t.page.keyboard.type('Menschen, die Ideen voranbringen.', { delay: 70 });
    await t.wait(900);
    await t.step('Text im Block antippen – oben rechts erscheint seine Leiste mit „Bearbeiten“.', 'Tap text in a block – its bar with “Bearbeiten” (edit) appears at the top right.', 1000);
    await showBlock(t, 'Text + Bild', 110);
    await t.click(blockOf(t, 'Text + Bild').locator('[data-edit="text"] p').first(), { after: 1400 });
    await t.step('„Bearbeiten“ öffnet alle Felder des Blocks – die Seitenleiste liegt über der Aktionsleiste.', '“Bearbeiten” opens all fields of the block – the panel sits above the action bar.', 1000);
    await t.click(blockEdit(t, 'Text + Bild'), { after: 1600 });
    const eyebrow = t.page.getByRole('complementary').getByLabel('Dachzeile (optional)');
    await t.click(eyebrow, { after: 200 });
    await eyebrow.evaluate((el) => el.setSelectionRange(el.value.length, el.value.length));
    await t.page.keyboard.type(' & Werte', { delay: 90 });
    await t.wait(700);
    t.poster();
    await t.step('„Fertig“ schließt die Seitenleiste – die Änderung ist sofort in der Seite zu sehen.', '“Fertig” (done) closes the panel – the change shows in the page right away.', 1200);
    await t.click(t.page.locator('.cms-drawer button:visible:has-text("Fertig")').first(), { after: 1600 });
    await t.step('„Speichern“ sichert den Entwurf, „Veröffentlichen“ stellt ihn online.', '“Speichern” saves the draft, “Veröffentlichen” puts it online.', 1200);
    await t.click(t.page.locator(`${BAR} button[data-editor-save]:visible`).first(), { after: 1600 });
    await t.click(t.page.locator(`${BAR} button[data-editor-publish]:visible`).first(), { after: 1200 });
    await confirmOk(t);
    await t.step('Fertig – die Änderung ist online.', 'Done – the change is live.', 1000);
    await t.page.evaluate(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
    await t.wait(2000);
  },
  cleanup: dropPage,
});

add({
  slug: 'r-anfragen-mobil', title: 'Anfragen auf dem Smartphone lesen', account: 'praxis', mobile: true,
  async run(t, s) {
    await t.goto('/admin');
    await t.step('Neue Anfragen zeigt die Übersicht ganz oben – antippen öffnet den Posteingang.', 'The dashboard shows new requests at the top – tap to open the inbox.');
    await t.click('main a[href$="/admin/requests"]', { after: 1600 });
    await t.step('Anfragen lassen sich auch am Smartphone lesen – Schlüssel aus dem Passwortmanager einfügen.', 'Requests can be read on a smartphone too – paste the key from your password manager.');
    await t.click('#secret', { after: 200 });
    await t.page.fill('#secret', t.acc.requestKey || '');
    await t.click('form.adm-unlock button[type=submit]', { after: 1800 });
    t.poster();
    await t.step('Die Angaben stehen untereinander; darunter Drucken, Kopieren und der Status.', 'The details are listed one below the other; below them print, copy and the status.');
    await t.page.locator('main article').first().scrollIntoViewIfNeeded();
    await t.wait(1500);
    await t.scroll(260, 2000);
    await t.step('Status setzen: „In Bearbeitung“ – so sehen alle, dass sich jemand kümmert.', 'Set the status: “In Bearbeitung” (in progress) – so everyone sees someone is on it.');
    const form = t.page.locator('main article form:has(button:has-text("In Bearbeitung"))').first();
    s.statusUrl = await form.getAttribute('action');
    await t.click(form.locator('button'), { after: 1800 });
    await t.step('Nach dem Neuladen ist alles wieder verschlossen – der Schlüssel wird nie gespeichert.', 'After reloading everything is locked again – the key is never stored.', 2400);
  },
  async cleanup(t, s) { if (s.statusUrl) await t.post(s.statusUrl, { status: 'neu', back: 'neu' }); },
});
