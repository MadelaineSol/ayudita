/**
 * Registro, login y recuperación de contraseña.
 * Google/Apple/Teléfono: botones presentes; requieren configurar los
 * proveedores OAuth en producción (ver docs/INSTALL.md).
 */
import { render, esc, toast, topbar } from '../core/ui.js';
import { go } from '../core/router.js';
import { post } from '../core/api.js';
import { store } from '../core/store.js';

const socialButtons = `
  <div class="stack" style="margin-top:14px">
    <button class="btn secondary" onclick="window.__soon()">🔵 Continuar con Google</button>
    <button class="btn secondary" onclick="window.__soon()">🍎 Continuar con Apple</button>
    <button class="btn secondary" onclick="window.__soon()">📱 Continuar con teléfono</button>
  </div>`;

window.__soon = () => toast('Disponible al configurar el proveedor 😉');

export function registerPage(_, query) {
  const role = query.get('role') === 'provider' ? 'provider' : 'client';
  render(`
    ${topbar('Crear cuenta', '#/elegir')}
    <p class="muted">${role === 'provider' ? '🛠️ Vas a ofrecer tus servicios' : '🙋 Vas a contratar servicios'}</p>
    <form id="f" class="stack" style="margin-top:18px" novalidate>
      <div class="field">
        <label for="name">Tu nombre</label>
        <input class="input" id="name" autocomplete="name" required minlength="2" placeholder="Ej: María López">
      </div>
      <div class="field">
        <label for="email">Tu email</label>
        <input class="input" id="email" type="email" autocomplete="email" required placeholder="maria@email.com">
      </div>
      <div class="field">
        <label for="phone">Tu teléfono <span class="muted small">(opcional)</span></label>
        <input class="input" id="phone" type="tel" autocomplete="tel" placeholder="+54 11 5555-5555">
      </div>
      <div class="field">
        <label for="pass">Elegí una contraseña</label>
        <input class="input" id="pass" type="password" autocomplete="new-password" required minlength="8">
        <span class="hint">Mínimo 8 caracteres. ¡Que sea difícil de adivinar! 🔒</span>
      </div>
      <button class="btn" type="submit">Crear mi cuenta 💛</button>
    </form>
    ${socialButtons}
    <p class="center" style="margin-top:18px">¿Ya tenés cuenta? <a href="#/login"><b>Iniciá sesión</b></a></p>
  `);

  document.getElementById('f').onsubmit = async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
      const json = await post('/auth/register', {
        name: document.getElementById('name').value.trim(),
        email: document.getElementById('email').value.trim(),
        phone: document.getElementById('phone').value.trim() || undefined,
        password: document.getElementById('pass').value,
        role,
      });
      store.setSession(json.data);
      store.setOnboarded();
      toast('¡Bienvenido a Ayudita! 🎉', 'success');
      go(role === 'provider' ? '#/prestador/perfil' : '#/home');
    } catch (err) {
      toast(err.message, 'error');
    } finally {
      btn.disabled = false;
    }
  };
}

export function loginPage() {
  render(`
    ${topbar('¡Hola de nuevo! 👋', '#/')}
    <form id="f" class="stack" style="margin-top:18px" novalidate>
      <div class="field">
        <label for="email">Tu email</label>
        <input class="input" id="email" type="email" autocomplete="email" required>
      </div>
      <div class="field">
        <label for="pass">Tu contraseña</label>
        <input class="input" id="pass" type="password" autocomplete="current-password" required>
      </div>
      <button class="btn" type="submit">Entrar 💛</button>
      <a class="center" href="#/recuperar">¿Te olvidaste la contraseña?</a>
    </form>
    ${socialButtons}
    <p class="center" style="margin-top:18px">¿Sos nuevo? <a href="#/elegir"><b>Creá tu cuenta</b></a></p>
  `);

  document.getElementById('f').onsubmit = async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
      const json = await post('/auth/login', {
        email: document.getElementById('email').value.trim(),
        password: document.getElementById('pass').value,
      });
      store.setSession(json.data);
      store.setOnboarded();
      go('#/home');
    } catch (err) {
      toast(err.message, 'error');
    } finally {
      btn.disabled = false;
    }
  };
}

export function forgotPage() {
  render(`
    ${topbar('Recuperar contraseña', '#/login')}
    <p class="muted">Te enviamos un enlace a tu email para crear una contraseña nueva 💌</p>
    <form id="f" class="stack" style="margin-top:18px">
      <div class="field">
        <label for="email">Tu email</label>
        <input class="input" id="email" type="email" required>
      </div>
      <button class="btn" type="submit">Enviarme el enlace</button>
    </form>
  `);
  document.getElementById('f').onsubmit = async (e) => {
    e.preventDefault();
    try {
      await post('/auth/forgot-password', { email: document.getElementById('email').value.trim() });
      toast('Listo. Revisá tu email 💌', 'success');
      go('#/login');
    } catch (err) {
      toast(err.message, 'error');
    }
  };
}
