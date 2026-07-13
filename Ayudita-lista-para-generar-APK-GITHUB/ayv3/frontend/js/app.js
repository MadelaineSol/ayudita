/**
 * Ayudita — punto de entrada del frontend.
 * Registra rutas, dibuja la barra de navegación y arranca el router.
 */
import { route, startRouter, dispatch, go } from './core/router.js';
import { store } from './core/store.js';
import { $, esc } from './core/ui.js';
import { get } from './core/api.js';
import { registerPush } from './native.js';

import { welcomePage, choosePage } from './pages/onboarding.js';
import { registerPage, loginPage, forgotPage } from './pages/auth.js';
import { homePage, searchPage, providerPage } from './pages/home.js';
import { bookingsPage, bookingDetailPage } from './pages/bookings.js';
import { chatListPage, chatRoomPage, stopChatPolling } from './pages/chat.js';
import { profilePage, favoritesPage, notificationsPage, paymentsPage } from './pages/profile.js';
import { providerProfilePage, earningsPage } from './pages/provider.js';

// ---------- Rutas ----------
route('/', welcomePage);
route('/elegir', choosePage);
route('/registro', registerPage);
route('/login', loginPage);
route('/recuperar', forgotPage);

route('/home', homePage, { requiresAuth: true });
route('/buscar', searchPage, { requiresAuth: true });
// Las rutas fijas del prestador van ANTES que la dinámica /prestador/:id
route('/prestador/perfil', providerProfilePage, { requiresAuth: true, roles: ['provider'] });
route('/prestador/ingresos', earningsPage, { requiresAuth: true, roles: ['provider'] });
route('/prestador/:id', providerPage, { requiresAuth: true });

route('/trabajos', bookingsPage, { requiresAuth: true });
route('/trabajos/:id', bookingDetailPage, { requiresAuth: true });

route('/chats', chatListPage, { requiresAuth: true });
route('/chat/:id', chatRoomPage, { requiresAuth: true });

route('/perfil', profilePage, { requiresAuth: true });
route('/favoritos', favoritesPage, { requiresAuth: true });
route('/avisos', notificationsPage, { requiresAuth: true });
route('/pagos', paymentsPage, { requiresAuth: true });

// ---------- Barra de navegación ----------
const TABS_CLIENT = [
  ['#/home', '🏠', 'Inicio'],
  ['#/trabajos', '📋', 'Trabajos'],
  ['#/chats', '💬', 'Chats'],
  ['#/avisos', '🔔', 'Avisos'],
  ['#/perfil', '🙂', 'Perfil'],
];
const TABS_PROVIDER = [
  ['#/trabajos', '📋', 'Trabajos'],
  ['#/prestador/ingresos', '💰', 'Ingresos'],
  ['#/chats', '💬', 'Chats'],
  ['#/prestador/perfil', '🛠️', 'Mi perfil'],
  ['#/perfil', '🙂', 'Cuenta'],
];

function drawTabbar() {
  const bar = $('#tabbar');
  if (!store.isLoggedIn) { bar.classList.add('hidden'); return; }

  const tabs = store.role === 'provider' ? TABS_PROVIDER : TABS_CLIENT;
  const current = location.hash || '#/home';
  bar.classList.remove('hidden');
  bar.innerHTML = tabs.map(([hash, ico, label]) => `
    <button class="tab ${current === hash ? 'active' : ''}" data-hash="${hash}" aria-label="${esc(label)}">
      <span class="ico" aria-hidden="true">${ico}</span>${esc(label)}
      ${hash === '#/avisos' ? '<span class="dot hidden" id="notifDot"></span>' : ''}
    </button>`).join('');
  bar.querySelectorAll('.tab').forEach((t) => { t.onclick = () => go(t.dataset.hash); });
}

async function refreshNotifDot() {
  if (!store.isLoggedIn) return;
  try {
    const json = (await get('/notifications')).data;
    $('#notifDot')?.classList.toggle('hidden', !json.unread);
  } catch { /* sin conexión */ }
}

// ---------- Arranque ----------
window.addEventListener('hashchange', () => { stopChatPolling(); drawTabbar(); });

startRouter();
drawTabbar();
setTimeout(() => $('#splash').classList.add('hidden'), 1100);
setInterval(refreshNotifDot, 30000);
refreshNotifDot();

// Push nativo: registra el token cuando la app corre en Android/iOS.
if (store.isLoggedIn) registerPush((token) => console.info('Push token:', token));
