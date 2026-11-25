#!/usr/bin/env node
/**
 * Capturas de la app en varios anchos y modos, para revisar el diseño con ojos de verdad.
 *
 *   node capturas.mjs --token=<token de la API> --out=<carpeta> \
 *     [--paths=/listas/1,/perfil] [--widths=420,1280] [--themes=oscuro,claro] \
 *     [--hover=<selector CSS>] [--click=<selector CSS>] [--scroll=<px>]
 *     [--base=http://localhost:4202] [--scale=1]
 *
 * Por cada ruta × ancho × modo deja un PNG en --out y escribe su ruta. Con --hover, además pasa
 * el mouse sobre el primer elemento visible que calce y saca otra captura con el sufijo «-hover»
 * (la barrita encendida, el borde ámbar de una tarjeta...). Con --click, lo toca con un clic de
 * mouse de verdad (así se ubican los popovers) y saca otra con el sufijo «-clic». Con --scroll,
 * baja la página esos píxeles antes de capturar.
 *
 * Necesita google-chrome y Node 22 o más nuevo (usa el WebSocket global). El token se guarda en
 * el localStorage del perfil temporal de Chrome, igual que lo hace la app al entrar.
 */
import { spawn } from 'node:child_process';
import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const args = Object.fromEntries(
  process.argv.slice(2).map((arg) => {
    const [key, ...rest] = arg.replace(/^--/, '').split('=');
    return [key, rest.join('=')];
  }),
);
if (!args.token || !args.out) {
  console.error('Faltan --token y --out. Ver el comentario al inicio de este archivo.');
  process.exit(1);
}

const base = args.base || 'http://localhost:4202';
const paths = (args.paths || '/listas').split(',');
const widths = (args.widths || '420,1280').split(',').map(Number);
const themes = (args.themes || 'oscuro,claro').split(',');
const scale = Number(args.scale || 1);
const port = 9400 + Math.floor(Math.random() * 500);
mkdirSync(args.out, { recursive: true });
// El perfil de Chrome, dentro de la carpeta de salida y aparte: se borra al terminar.
const profile = join(args.out, `.chrome-${port}`);

// Chrome sin ventana dice que no tiene mouse ((hover: hover) da falso), y los estilos de hover de
// la app, los de Tailwind incluidos, van dentro de esa media query: sin esto, --hover no muestra
// nada. Las DevTools no dejan emular `hover`; estas opciones de Blink sí (2 = hover, 4 = puntero fino).
const mouse = 'primaryHoverType=2,availableHoverTypes=2,primaryPointerType=4,availablePointerTypes=4';
const chrome = spawn(
  'google-chrome',
  [
    '--headless=new',
    `--remote-debugging-port=${port}`,
    '--no-first-run',
    `--blink-settings=${mouse}`,
    `--user-data-dir=${profile}`,
    'about:blank',
  ],
  { stdio: 'ignore' },
);
const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

try {
  let tabs;
  for (let attempt = 0; attempt < 40 && !tabs; attempt++) {
    await sleep(250);
    tabs = await fetch(`http://127.0.0.1:${port}/json`).then((r) => r.json()).catch(() => undefined);
  }
  const ws = new WebSocket(tabs.find((tab) => tab.type === 'page').webSocketDebuggerUrl);
  await new Promise((resolve) => (ws.onopen = resolve));
  let id = 0;
  const pending = new Map();
  ws.onmessage = (message) => {
    const data = JSON.parse(message.data);
    pending.get(data.id)?.(data.result);
    pending.delete(data.id);
  };
  const send = (method, params = {}) =>
    new Promise((resolve) => {
      pending.set(++id, resolve);
      ws.send(JSON.stringify({ id, method, params }));
    });
  const value = async (expression) =>
    (await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true })).result?.value;
  const shot = async (name) => {
    const { data } = await send('Page.captureScreenshot');
    const file = join(args.out, `${name}.png`);
    writeFileSync(file, Buffer.from(data, 'base64'));
    console.log(file);
  };

  for (const width of widths) {
    await send('Emulation.setDeviceMetricsOverride', {
      width,
      height: width < 600 ? 900 : 800,
      deviceScaleFactor: scale,
      mobile: width < 600,
    });
    for (const theme of themes) {
      for (const path of paths) {
        // Primero una página de la app, para poder escribir en su localStorage.
        await send('Page.navigate', { url: `${base}/login` });
        await sleep(1200);
        await value(
          `localStorage.setItem('whishlist.token', ${JSON.stringify(args.token)}); localStorage.setItem('whishlist.tema', ${JSON.stringify(theme)})`,
        );
        await send('Page.navigate', { url: base + path });
        await sleep(3500);
        if (args.scroll) {
          await value(
            `[...document.querySelectorAll('ion-content')].filter(c => c.offsetParent).pop()?.scrollToPoint(0, ${Number(args.scroll)}, 0)`,
          );
          await sleep(500);
        }
        const name = `${path.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'inicio'}-${width}-${theme}`;
        await shot(name);

        const centerOf = (selector) =>
          value(`(() => {
            const element = [...document.querySelectorAll(${JSON.stringify(selector)})].find((e) => e.offsetParent);
            if (!element) return null;
            const box = element.getBoundingClientRect();
            return { x: box.x + box.width / 2, y: box.y + box.height / 2 };
          })()`);

        if (args.hover) {
          const point = await centerOf(args.hover);
          if (point) {
            await send('Input.dispatchMouseEvent', { type: 'mouseMoved', ...point });
            await sleep(500);
            await shot(`${name}-hover`);
            await send('Input.dispatchMouseEvent', { type: 'mouseMoved', x: 1, y: 1 });
          } else {
            console.error(`No se encontró ${args.hover} en ${path}`);
          }
        }

        if (args.click) {
          const point = await centerOf(args.click);
          if (!point) {
            console.error(`No se encontró ${args.click} en ${path}`);
            continue;
          }
          for (const type of ['mousePressed', 'mouseReleased']) {
            await send('Input.dispatchMouseEvent', { type, ...point, button: 'left', clickCount: 1 });
          }
          await sleep(1000);
          await shot(`${name}-clic`);
        }
      }
    }
  }
  ws.close();
} finally {
  // Esperar a que Chrome cierre: si no, sigue escribiendo en su perfil mientras se borra.
  const closed = new Promise((resolve) => chrome.once('exit', resolve));
  chrome.kill();
  await closed;
  rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
}
