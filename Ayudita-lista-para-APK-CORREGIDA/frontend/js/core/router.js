/**
 * Router por hash (#/ruta/param) con guardas de sesión.
 * Funciona idéntico en web, PWA y Capacitor.
 */
import { store } from './store.js';

const routes = [];

export function route(pattern, handler, { requiresAuth = false, roles = null } = {}) {
  routes.push({ pattern, handler, requiresAuth, roles });
}

export function go(hash) {
  location.hash = hash;
}

export async function dispatch() {
  const path = (location.hash.slice(1) || '/').split('?')[0];
  const query = new URLSearchParams((location.hash.split('?')[1] || ''));

  for (const r of routes) {
    const names = [];
    const regex = new RegExp('^' + r.pattern.replace(/:(\w+)/g, (_, n) => { names.push(n); return '([^/]+)'; }) + '$');
    const match = path.match(regex);
    if (!match) continue;

    if (r.requiresAuth && !store.isLoggedIn) { go('#/login'); return; }
    if (r.roles && !r.roles.includes(store.role)) { go('#/home'); return; }

    const params = Object.fromEntries(names.map((n, i) => [n, decodeURIComponent(match[i + 1])]));
    await r.handler(params, query);
    return;
  }
  go(store.isLoggedIn ? '#/home' : '#/');
}

export function startRouter() {
  window.addEventListener('hashchange', dispatch);
  dispatch();
}
