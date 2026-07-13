/**
 * Utilidades de interfaz: render, toasts, hojas modales, helpers.
 */

export const $ = (sel, root = document) => root.querySelector(sel);

/** Escapa texto para inyectar en HTML (anti-XSS en el cliente). */
export function esc(value) {
  const div = document.createElement('div');
  div.textContent = String(value ?? '');
  return div.innerHTML;
}

export function render(html) {
  const app = $('#app');
  app.innerHTML = html;
  app.scrollTop = 0;
  window.scrollTo({ top: 0 });
  return app;
}

export function toast(message, type = '') {
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.textContent = message;
  $('#toasts').appendChild(el);
  setTimeout(() => {
    el.style.opacity = '0';
    el.style.transition = 'opacity .4s';
    setTimeout(() => el.remove(), 400);
  }, 2600);
}

/** Hoja inferior (bottom sheet). Devuelve el nodo para enganchar eventos. */
export function sheet(html) {
  const backdrop = document.createElement('div');
  backdrop.className = 'sheet-backdrop';
  backdrop.innerHTML = `<div class="sheet"><div class="sheet-handle"></div>${html}</div>`;
  backdrop.addEventListener('click', (e) => { if (e.target === backdrop) backdrop.remove(); });
  document.body.appendChild(backdrop);
  return backdrop;
}

export function closeSheets() {
  document.querySelectorAll('.sheet-backdrop').forEach((n) => n.remove());
}

export function money(value) {
  return '$' + Number(value || 0).toLocaleString('es-AR', { maximumFractionDigits: 0 });
}

export function timeAgo(dateStr) {
  if (!dateStr) return '';
  const diff = (Date.now() - new Date(dateStr.replace(' ', 'T'))) / 1000;
  if (diff < 60) return 'recién';
  if (diff < 3600) return `hace ${Math.floor(diff / 60)} min`;
  if (diff < 86400) return `hace ${Math.floor(diff / 3600)} h`;
  return new Date(dateStr.replace(' ', 'T')).toLocaleDateString('es-AR');
}

export function starsOf(avg) {
  return '★ ' + Number(avg || 0).toFixed(1);
}

export const STATUS_LABEL = {
  pending: 'Pendiente', accepted: 'Aceptado', on_way: 'En camino',
  in_progress: 'En curso', completed: 'Finalizado', cancelled: 'Cancelado',
  disputed: 'En disputa',
};
export const PAY_LABEL = { unpaid: 'A pagar', paid: 'Pagado', released: 'Liberado', refunded: 'Reembolsado' };
export const UNIT_LABEL = { hour: 'hora', day: 'día', week: 'semana', month: 'mes' };

export function topbar(title, backHash = null) {
  return `
    <div class="topbar">
      ${backHash !== null ? `<button class="back-btn" onclick="history.back()" aria-label="Volver">←</button>` : ''}
      <h1>${esc(title)}</h1>
    </div>`;
}

export function emptyState(emoji, text) {
  return `<div class="empty"><span class="emoji">${emoji}</span><p>${esc(text)}</p></div>`;
}

export function skeletons(n = 3) {
  return `<div class="stack">${'<div class="skeleton"></div>'.repeat(n)}</div>`;
}
