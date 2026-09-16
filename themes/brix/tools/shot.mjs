// Знімки сторінок на справжніх розмірах екрана + пошук горизонтального
// переповнення.
//
//   node tools/shot.mjs <url> [--w=390] [--h=844] [--full] [--out=shot.png]
//                        [--visit=<url>]  — відкрити перед знімком, можна кілька разів
//
// Навіщо свій інструмент: headless Chrome на macOS не робить вікно
// вужчим за 500 px, тож --window-size=390 мовчки віддає знімок,
// обрізаний з 500 — верстка на ньому виглядає зламаною там, де вона
// ціла, і цілою там, де зламана. Тут ширину задає
// Emulation.setDeviceMetricsOverride, і це вже справжні 390.

import { spawn } from 'node:child_process';
import { writeFile, mkdtemp } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = 9333;

const args = process.argv.slice(2);
const url = args.find((a) => !a.startsWith('--'));
const flag = (name, fallback) => {
  const hit = args.find((a) => a.startsWith(`--${name}=`));
  return hit ? hit.split('=')[1] : fallback;
};

if (!url) {
  console.error('Вкажіть URL: node tools/shot.mjs http://brix.local/ --w=390');
  process.exit(1);
}

const width = Number(flag('w', 1440));
const height = Number(flag('h', 900));
const fullPage = args.includes('--full');
// Сторінки кошика й checkout показують щось лише за наявної сесії,
// тож перед знімком можна пройти сценарій: додати товар, потім знімати.
const visits = args.filter((a) => a.startsWith('--visit=')).map((a) => a.slice('--visit='.length));
const out = flag('out', `shot-${width}.png`);

const profile = await mkdtemp(join(tmpdir(), 'brix-shot-'));
const chrome = spawn(CHROME, [
  '--headless=new', '--disable-gpu', '--hide-scrollbars',
  `--remote-debugging-port=${PORT}`, `--user-data-dir=${profile}`,
  '--no-first-run', '--no-default-browser-check', 'about:blank',
], { stdio: 'ignore' });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Чекає, поки Chrome підніме порт налагодження. */
async function waitForDevtools() {
  for (let i = 0; i < 50; i += 1) {
    try {
      const res = await fetch(`http://127.0.0.1:${PORT}/json/version`);
      if (res.ok) return;
    } catch { /* ще не піднявся */ }
    await sleep(100);
  }
  throw new Error('Chrome не підняв порт налагодження');
}

await waitForDevtools();

const target = await (await fetch(`http://127.0.0.1:${PORT}/json/new?about:blank`, { method: 'PUT' })).json();
const ws = new WebSocket(target.webSocketDebuggerUrl);
await new Promise((resolve) => ws.addEventListener('open', resolve, { once: true }));

let seq = 0;
const pending = new Map();

ws.addEventListener('message', (event) => {
  const msg = JSON.parse(event.data);
  if (msg.id && pending.has(msg.id)) {
    const { resolve, reject } = pending.get(msg.id);
    pending.delete(msg.id);
    msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
  }
});

/** Виклик команди DevTools Protocol. */
const send = (method, params = {}) => new Promise((resolve, reject) => {
  const id = ++seq;
  pending.set(id, { resolve, reject });
  ws.send(JSON.stringify({ id, method, params }));
});

await send('Page.enable');
await send('Emulation.setDeviceMetricsOverride', {
  width, height, deviceScaleFactor: 1, mobile: width < 768,
});

for (const step of visits) {
  await send('Page.navigate', { url: step });
  await sleep(1500);
}

await send('Page.navigate', { url });
await sleep(2500);

// ——— пошук переповнення ———
const probe = `(() => {
  const vw = document.documentElement.clientWidth;
  const hits = [];
  for (const el of document.querySelectorAll('*')) {
    const r = el.getBoundingClientRect();
    if (r.width > vw + 1 || r.right > vw + 1) {
      const cls = typeof el.className === 'string' && el.className.trim()
        ? '.' + el.className.trim().split(/\\s+/).join('.') : '';
      hits.push(el.tagName.toLowerCase() + cls + ' w=' + Math.round(r.width) + ' right=' + Math.round(r.right));
    }
  }
  return JSON.stringify({ vw, scrollWidth: document.documentElement.scrollWidth, hits });
})()`;

const { result } = await send('Runtime.evaluate', { expression: probe, returnByValue: true });
const report = JSON.parse(result.value);

console.log(`${url}  вікно ${report.vw}px  документ ${report.scrollWidth}px`);
if (report.scrollWidth > report.vw + 1) {
  console.log('Горизонтальне переповнення:');
  report.hits.slice(0, 15).forEach((h) => console.log('  ' + h));
} else {
  console.log('Переповнення немає.');
}

const shot = await send('Page.captureScreenshot', {
  format: 'png',
  captureBeyondViewport: fullPage,
  ...(fullPage ? { clip: null } : {}),
});
await writeFile(out, Buffer.from(shot.data, 'base64'));
console.log(`Знімок: ${out}`);

ws.close();
chrome.kill();
