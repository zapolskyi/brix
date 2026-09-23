// Перезбирає локальні шрифти: тягне з Google Fonts підмножини cyrillic + latin,
// кладе .woff2 в assets/fonts/, генерує assets/scss/base/_fonts.scss
// і маніфест assets/fonts/fonts.json.
//
//   node tools/fonts.mjs
//
// Навіщо локально: шрифти з CDN — це третій домен у критичному шляху
// і мінус до LCP. Підмножини беремо лише дві, бо сайт двомовний UA/EN.

import { writeFile, mkdir } from 'node:fs/promises';
import { statSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const FONTS_DIR = join(ROOT, 'assets/fonts');
const SCSS_OUT = join(ROOT, 'assets/scss/base/_fonts.scss');

// Google віддає woff2 лише сучасним браузерам — інакше прилетить ttf.
const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
         + '(KHTML, like Gecko) Chrome/120.0 Safari/537.36';

// Накреслення рівно ті, що реально вживаються в макетах.
const API = 'https://fonts.googleapis.com/css2'
  + '?family=Geologica:wght@600;700;800'
  + '&family=Manrope:wght@400;500;600;700;800'
  + '&family=IBM+Plex+Mono:wght@400;500'
  + '&display=swap';

const SUBSETS = ['cyrillic', 'latin'];
const SLUG = { 'Geologica': 'geologica', 'Manrope': 'manrope', 'IBM Plex Mono': 'plex-mono' };

const css = await (await fetch(API, { headers: { 'User-Agent': UA } })).text();

// Змінні шрифти (Manrope, Geologica) Google віддає одним файлом на
// всі накреслення: той самий URL повторюється для кожної ваги. Такі
// накреслення зливаємо в одне @font-face з діапазоном ваг — інакше
// браузер тягнув би однаковий файл кілька разів під різними іменами.
const byUrl = new Map();
for (const [, subset, body] of css.matchAll(/\/\*\s*([a-z-]+)\s*\*\/\s*@font-face\s*\{([^}]*)\}/g)) {
  if (!SUBSETS.includes(subset)) continue;
  const family = body.match(/font-family:\s*'([^']+)'/)[1];
  const weight = Number(body.match(/font-weight:\s*(\d+)/)[1]);
  const url = body.match(/url\((https:\/\/[^)]+\.woff2)\)/)[1];
  const unicodeRange = body.match(/unicode-range:\s*([^;]+);/)[1].trim();
  const seen = byUrl.get(url);
  if (seen) { seen.weights.push(weight); continue; }
  byUrl.set(url, { family, weights: [weight], subset, url, unicodeRange });
}

const faces = [...byUrl.values()].map((f) => {
  const min = Math.min(...f.weights);
  const max = Math.max(...f.weights);
  const weight = min === max ? String(min) : `${min} ${max}`;
  const tag = min === max ? String(min) : 'var';
  return { ...f, weight, file: `${SLUG[f.family]}-${tag}-${f.subset}.woff2` };
});

faces.sort((a, b) => a.family.localeCompare(b.family) || String(a.weight).localeCompare(String(b.weight)) || a.subset.localeCompare(b.subset));

await mkdir(FONTS_DIR, { recursive: true });
for (const f of faces) {
  const buf = Buffer.from(await (await fetch(f.url, { headers: { 'User-Agent': UA } })).arrayBuffer());
  await writeFile(join(FONTS_DIR, f.file), buf);
  console.log(`${f.file.padEnd(32)} ${String(Math.round(buf.length / 1024)).padStart(4)} KB`);
}

const scss = [
  '// Локальні шрифти. Згенеровано з Google Fonts, підмножини cyrillic + latin.',
  '// Файли лежать в assets/fonts/, маніфест — assets/fonts/fonts.json.',
  '// Не правити вручну: перегенерувати через tools/fonts.mjs.',
  '',
  ...faces.flatMap((f) => [
    '@font-face {',
    `  font-family: '${f.family}';`,
    '  font-style: normal;',
    `  font-weight: ${f.weight};`,
    '  font-display: swap;',
    `  src: url('../fonts/${f.file}') format('woff2');`,
    `  unicode-range: ${f.unicodeRange};`,
    '}',
    '',
  ]),
].join('\n');
await writeFile(SCSS_OUT, scss);

const manifest = faces.map(({ family, weight, subset, file, unicodeRange }) => ({
  family, weight, subset, file,
  unicode_range: unicodeRange,
  bytes: statSync(join(FONTS_DIR, file)).size,
}));
await writeFile(join(FONTS_DIR, 'fonts.json'), JSON.stringify(manifest, null, 2) + '\n');

const total = manifest.reduce((sum, m) => sum + m.bytes, 0);
console.log(`\nусього: ${Math.round(total / 1024)} KB у ${manifest.length} файлах`);
