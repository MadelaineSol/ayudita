/**
 * Onboarding: bienvenida animada + elección de rol.
 */
import { render, esc } from '../core/ui.js';
import { go } from '../core/router.js';
import { store } from '../core/store.js';

export function welcomePage() {
  if (store.isLoggedIn) { go('#/home'); return; }
  render(`
    <div class="onboard">
      <div class="hero">🐕🧹💡</div>
      <h1>¡Hola! Somos <span style="color:var(--primary-dark)">Ayudita</span> 💛</h1>
      <p class="muted" style="font-size:1.1rem">
        Gente buena y de confianza para darte una mano:
        paseadores, electricistas, niñeras y mucho más.
      </p>
      <p class="muted">Simple, seguro y al toque.</p>
      <button class="btn" id="start">Comenzar ✨</button>
      <button class="btn ghost" id="haveAccount">Ya tengo cuenta</button>
    </div>
  `);
  document.getElementById('start').onclick = () => go('#/elegir');
  document.getElementById('haveAccount').onclick = () => go('#/login');
}

export function choosePage() {
  render(`
    <div class="onboard" style="justify-content:center">
      <h1 class="center">¿Qué querés hacer? 🤔</h1>
      <p class="muted center">Podés cambiarlo cuando quieras</p>
      <div class="card tap role-card" id="roleClient">
        <span class="emoji">🙋</span>
        <div>
          <h2>Quiero contratar</h2>
          <p class="muted small">Busco a alguien que me ayude con algo</p>
        </div>
      </div>
      <div class="card tap role-card" id="roleProvider">
        <span class="emoji">🛠️</span>
        <div>
          <h2>Quiero ofrecer mis servicios</h2>
          <p class="muted small">Tengo un oficio y busco trabajos</p>
        </div>
      </div>
    </div>
  `);
  document.getElementById('roleClient').onclick = () => go('#/registro?role=client');
  document.getElementById('roleProvider').onclick = () => go('#/registro?role=provider');
}
