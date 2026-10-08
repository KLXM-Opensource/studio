// Optischer Vorher/Nachher-Vergleich einer Website (Desktop + Telefon, alle Seiten aus Kopf und Navigation).
//
//   node tools/visual-diff.mjs vorher  https://www.example.org   Screenshots vor einer Änderung/einem Deploy
//   node tools/visual-diff.mjs nachher https://www.example.org   dieselben Seiten danach
//   node tools/visual-diff.mjs diff                               abweichende Pixel je Seite (0 = unverändert)
//
// Optionen: --out=<ordner> (Standard storage/visual-diff/<host>), --max=<n> Seiten (Standard 14), --wait=<ms> (Standard 1000).
// Zeitabhängige Inhalte (Öffnungszeiten „jetzt geöffnet“, Countdown, Zufallsbilder) erzeugen erwartbare Abweichungen.
// Braucht Playwright (tools/node_modules, `pnpm install` in tools/).
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const args = process.argv.slice(2);
const opt = Object.fromEntries(args.filter(a => a.startsWith('--')).map(a => a.slice(2).split('=')));
const [cmd, base] = args.filter(a => !a.startsWith('--'));
const outFor = url => path.resolve(opt.out || path.join(root, 'storage/visual-diff', new URL(url).host));

if (cmd === 'vorher' || cmd === 'nachher') {
  if (!base) { console.error('Adresse fehlt: node tools/visual-diff.mjs vorher https://…'); process.exit(1); }
  const D = outFor(base);
  fs.mkdirSync(D, { recursive: true });
  fs.writeFileSync(path.join(D, 'base.txt'), base);
  const b = await chromium.launch();
  const page = await (await b.newContext({ viewport: { width: 1440, height: 900 }, locale: 'de-DE', reducedMotion: 'reduce' })).newPage();
  await page.goto(base, { waitUntil: 'networkidle' });
  let paths = await page.$$eval('header a[href], nav a[href]', as => [...new Set(as.map(a => new URL(a.href, location.href))
    .filter(u => u.origin === location.origin).map(u => u.pathname))]);
  paths = ['/', ...paths.filter(p => p !== '/')].slice(0, +(opt.max || 14));
  for (const [w, h, n] of [[1440, 900, 'd'], [390, 844, 'm']]) {
    await page.setViewportSize({ width: w, height: h });
    for (const p of paths) {
      await page.goto(new URL(p, base).href, { waitUntil: 'networkidle' });
      await page.waitForTimeout(+(opt.wait || 1000));
      await page.screenshot({ path: path.join(D, `${cmd}-${n}-${p.replace(/\W+/g, '_') || 'home'}.png`), fullPage: true });
    }
  }
  console.log(`${paths.length} Seiten × 2 Größen → ${D}`);
  await b.close();
} else if (cmd === 'diff') {
  const dirs = opt.out ? [path.resolve(opt.out)] : (fs.existsSync(path.join(root, 'storage/visual-diff'))
    ? fs.readdirSync(path.join(root, 'storage/visual-diff')).map(d => path.join(root, 'storage/visual-diff', d)) : []);
  const b = await chromium.launch();
  const page = await b.newPage();
  let total = 0;
  for (const D of dirs) {
    for (const f of fs.readdirSync(D).filter(f => f.startsWith('vorher-')).sort()) {
      const after = path.join(D, f.replace('vorher-', 'nachher-'));
      if (!fs.existsSync(after)) { console.log(`${path.basename(D)}  ${f.slice(7)}  nachher fehlt`); continue; }
      const r = await page.evaluate(async ([a, c]) => {
        const load = s => new Promise(res => { const i = new Image(); i.onload = () => res(i); i.src = 'data:image/png;base64,' + s; });
        const [x, y] = await Promise.all([load(a), load(c)]);
        if (x.width !== y.width || x.height !== y.height) return `Größe ${x.width}×${x.height} → ${y.width}×${y.height}`;
        const px = img => { const cv = new OffscreenCanvas(img.width, img.height); const g = cv.getContext('2d'); g.drawImage(img, 0, 0); return g.getImageData(0, 0, img.width, img.height).data; };
        const p = px(x), q = px(y);
        let n = 0;
        for (let i = 0; i < p.length; i += 4) if (Math.abs(p[i] - q[i]) + Math.abs(p[i + 1] - q[i + 1]) + Math.abs(p[i + 2] - q[i + 2]) > 30) n++;
        return n;
      }, [fs.readFileSync(path.join(D, f)).toString('base64'), fs.readFileSync(after).toString('base64')]);
      if (typeof r === 'number') total += r; else total += 1;
      console.log(`${path.basename(D)}  ${f.slice(7)}  ${r}`);
    }
  }
  console.log(total === 0 ? 'Unverändert (0 abweichende Pixel).' : `Abweichungen: ${total}`);
  await b.close();
  process.exit(total === 0 ? 0 : 2);
} else {
  console.error('Aufruf: node tools/visual-diff.mjs vorher|nachher <adresse> | diff  [--out=…] [--max=14] [--wait=1000]');
  process.exit(1);
}
