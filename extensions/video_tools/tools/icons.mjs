#!/usr/bin/env node
// SPDX-License-Identifier: MIT
/**
 * Eigenes kleines Symbol-Sprite der Erweiterung (Phosphor Icons, Stil duotone, MIT) für Symbole, die das Kern-Sprite nicht
 * mitbringt. Nur zur Entwicklung: node extensions/video_tools/tools/icons.mjs → assets/img/icons.svg (wird eingecheckt,
 * `pnpm run build` kopiert es nach public/assets/ext/video_tools/img/). Paket: @phosphor-icons/core aus tools/node_modules
 * (oder PHOSPHOR=/pfad/zu/@phosphor-icons/core).
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const DIR = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const PKG = process.env.PHOSPHOR || path.resolve(DIR, '../../tools/node_modules/@phosphor-icons/core');
const ICONS = ['film-strip', 'scissors', 'play', 'pause', 'speaker-slash', 'speaker-high', 'x-circle', 'stop', 'arrow-line-left', 'arrow-line-right',
  'repeat', 'skip-back', 'skip-forward', 'queue', 'frame-corners', 'gauge', 'monitor', 'archive', 'lightning', 'camera', 'terminal-window'];

let out = '<svg xmlns="http://www.w3.org/2000/svg"><!-- Phosphor Icons (duotone), MIT © Phosphor Icons – https://phosphoricons.com -->';
for (const n of ICONS) {
  const svg = fs.readFileSync(path.join(PKG, 'assets/duotone', `${n}-duotone.svg`), 'utf8');
  const inner = svg.replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '').replace(/opacity="0\.2"/g, 'opacity=".2"');
  out += `<symbol id="i-${n}" viewBox="0 0 256 256">${inner}</symbol>`;
}
out += '</svg>\n';
fs.writeFileSync(path.join(DIR, 'assets/img/icons.svg'), out);
console.log(`assets/img/icons.svg: ${ICONS.length} Symbole, ${(out.length / 1024).toFixed(1)} KB`);
