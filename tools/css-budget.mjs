// Budget einer Seite: jede geladene CSS- und JS-Datei mit Größe (minifiziert wie ausgeliefert) und gzip, Summen und Prüfung.
//
//   node tools/css-budget.mjs http://example.localhost:8099/            Tabelle aller Stylesheets und Skripte der Seite
//   node tools/css-budget.mjs <url> --mobile                             Telefon-Ansicht (390 px)
//   node tools/css-budget.mjs <url> --check [--css=30] [--js=8]          Exit 2, wenn CSS bzw. Kit-JS über dem Budget (KB, minifiziert)
//   node tools/css-budget.mjs <url> --json                               maschinenlesbar
//
// Gezählt wird, was der Browser beim Aufruf tatsächlich lädt (auch nachgeladene Skripte und Stylesheets am Ende von <body>),
// ohne Angemeldet-Sein (Besucheransicht). „blockiert“ = Stylesheet im <head> bzw. Skript ohne defer/async/module im <head>.
// Budget (Entwicklerhandbuch → Build): CSS der Startseite < 30 KB, Frontend-JS des Kits < 8 KB. Braucht Playwright (tools/).
import zlib from 'node:zlib';
import { chromium } from 'playwright';

const args = process.argv.slice(2);
const opt = Object.fromEntries(args.filter(a => a.startsWith('--')).map(a => { const [k, v] = a.slice(2).split('='); return [k, v ?? true]; }));
const url = args.find(a => !a.startsWith('--'));
if (!url) { console.error('Aufruf: node tools/css-budget.mjs <adresse> [--mobile] [--check] [--css=30] [--js=8] [--json]'); process.exit(1); }

const b = await chromium.launch();
const ctx = await b.newContext(opt.mobile ? { viewport: { width: 390, height: 844 }, isMobile: true } : { viewport: { width: 1440, height: 900 } });
const page = await ctx.newPage();
const files = new Map();
page.on('response', async res => {
  const type = res.request().resourceType();
  if (type !== 'stylesheet' && type !== 'script') return;
  try {
    const body = await res.body();
    files.set(res.url(), { url: res.url(), kind: type === 'stylesheet' ? 'css' : 'js', bytes: body.length, gzip: zlib.gzipSync(body, { level: 9 }).length });
  } catch { /* Umleitung o. Ä. */ }
});
await page.goto(url, { waitUntil: 'networkidle' });
await page.waitForTimeout(500);
// Was blockiert das erste Zeichnen? Stylesheets im <head>, klassische Skripte im <head> ohne defer/async
const blocking = new Set(await page.evaluate(() => [
  ...[...document.head.querySelectorAll('link[rel~="stylesheet"][href]')].filter(l => !l.media || l.media === 'all').map(l => l.href),
  ...[...document.head.querySelectorAll('script[src]')].filter(s => !s.defer && !s.async && s.type !== 'module').map(s => s.src),
]));
await b.close();

const kb = n => (n / 1024).toFixed(1).padStart(6);
const short = u => { const x = new URL(u); return x.origin === new URL(url).origin ? x.pathname : u; };
const group = u => { const p = new URL(u).pathname; return /^\/assets\/kits\//.test(p) ? 'Kit' : /^\/assets\/ext\//.test(p) ? 'Erweiterung' : /^\/assets\//.test(p) ? 'Kern' : 'Website'; };
const list = [...files.values()].map(f => ({ ...f, path: short(f.url), group: group(f.url), blocking: blocking.has(f.url) }));
const sum = (kind, pred = () => true) => list.filter(f => f.kind === kind && pred(f)).reduce((a, f) => [a[0] + f.bytes, a[1] + f.gzip], [0, 0]);
const totals = {
  css: sum('css'), cssBlocking: sum('css', f => f.blocking), js: sum('js'), kitJs: sum('js', f => f.group === 'Kit'),
};
const budget = { css: +(opt.css || 30) * 1024, js: +(opt.js || 8) * 1024 };

if (opt.json) {
  console.log(JSON.stringify({ url, files: list, totals, budget }, null, 2));
} else {
  for (const kind of ['css', 'js']) {
    console.log(`\n${kind.toUpperCase()}                                                        min KB  gzip KB`);
    for (const f of list.filter(f => f.kind === kind).sort((a, c) => c.bytes - a.bytes)) {
      console.log(`  ${(f.path.length > 52 ? '…' + f.path.slice(-51) : f.path).padEnd(52)} ${kb(f.bytes)}  ${kb(f.gzip)}  ${f.group}${f.blocking ? ', blockiert' : ''}`);
    }
  }
  const line = (label, [m, g]) => console.log(`  ${label.padEnd(52)} ${kb(m)}  ${kb(g)}`);
  console.log('\nSummen');
  line('CSS gesamt', totals.css);
  line('CSS im <head> (blockiert das erste Zeichnen)', totals.cssBlocking);
  line('JS gesamt', totals.js);
  line('JS des Kits', totals.kitJs);
  console.log(`\nBudget: CSS ${totals.css[0] <= budget.css ? 'ok' : 'ÜBER'} (< ${budget.css / 1024} KB), JS des Kits ${totals.kitJs[0] <= budget.js ? 'ok' : 'ÜBER'} (< ${budget.js / 1024} KB)`);
}
if (opt.check && (totals.css[0] > budget.css || totals.kitJs[0] > budget.js)) process.exit(2);
