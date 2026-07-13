/**
 * Perfil del usuario, favoritos, notificaciones e historial de pagos.
 */
import { render, esc, toast, money, topbar, emptyState, skeletons, starsOf, timeAgo, sheet, closeSheets, $ } from '../core/ui.js';
import { go } from '../core/router.js';
import { get, post, put, upload } from '../core/api.js';
import { store } from '../core/store.js';
import { pickPhoto, shareApp } from '../native.js';

export function profilePage() {
  const u = store.user;
  render(`
    ${topbar('Mi perfil 🙂')}
    <div class="card center stack" style="align-items:center">
      <div class="avatar lg" id="avatarBox">${u.avatar_url ? `<img src="${esc(u.avatar_url)}" alt="">` : '🙂'}</div>
      <h2>${esc(u.name)}</h2>
      <p class="muted small">${esc(u.email)}</p>
      <button class="btn secondary sm" id="changePhoto">📷 Cambiar foto</button>
    </div>
    <div class="stack" style="margin-top:16px">
      <button class="btn secondary" id="editData">✏️ Mis datos</button>
      <button class="btn secondary" onclick="location.hash='#/favoritos'">💛 Mis favoritos</button>
      <button class="btn secondary" onclick="location.hash='#/pagos'">🧾 Mis pagos y comprobantes</button>
      ${u.role === 'provider' ? `<button class="btn mint" onclick="location.hash='#/prestador/perfil'">🛠️ Mi perfil de prestador</button>` : ''}
      <button class="btn secondary" id="share">💌 Recomendar Ayudita</button>
      <button class="btn ghost" id="logout">Cerrar sesión</button>
    </div>
  `);

  $('#share').onclick = () => shareApp();
  $('#logout').onclick = async () => {
    try { await post('/auth/logout', { refresh_token: store.session.refresh_token }); } catch { /* ok */ }
    store.clearSession();
    go('#/');
  };

  $('#changePhoto').onclick = async () => {
    try {
      const file = await pickPhoto();
      if (!file) return;
      const fd = new FormData();
      fd.append('file', file);
      const json = await upload('/uploads', fd);
      const updated = await put('/profile', { avatar_url: json.data.url });
      store.updateUser(updated.data);
      toast('¡Foto actualizada! 📸', 'success');
      profilePage();
    } catch (err) { toast(err.message, 'error'); }
  };

  $('#editData').onclick = () => {
    const node = sheet(`
      <h2>Mis datos ✏️</h2>
      <form id="f" class="stack" style="margin-top:14px">
        <div class="field"><label>Nombre</label><input class="input" id="name" value="${esc(u.name)}"></div>
        <div class="field"><label>Teléfono</label><input class="input" id="phone" type="tel" value="${esc(u.phone || '')}"></div>
        <div class="field"><label>Ciudad</label><input class="input" id="city" value="${esc(u.city || '')}"></div>
        <div class="field"><label>Dirección</label><input class="input" id="address" value="${esc(u.address || '')}"></div>
        <button class="btn" type="submit">Guardar</button>
      </form>
    `);
    node.querySelector('#f').onsubmit = async (e) => {
      e.preventDefault();
      try {
        const json = await put('/profile', {
          name: node.querySelector('#name').value.trim(),
          phone: node.querySelector('#phone').value.trim() || undefined,
          city: node.querySelector('#city').value.trim() || undefined,
          address: node.querySelector('#address').value.trim() || undefined,
        });
        store.updateUser(json.data);
        closeSheets();
        toast('Datos guardados ✅', 'success');
        profilePage();
      } catch (err) { toast(err.message, 'error'); }
    };
  };
}

export async function favoritesPage() {
  render(`${topbar('Mis favoritos 💛', '#/perfil')}<div id="list">${skeletons(3)}</div>`);
  try {
    const items = (await get('/favorites')).data;
    if (!items.length) { $('#list').innerHTML = emptyState('💛', 'Guardá acá a tus prestadores preferidos.'); return; }
    $('#list').innerHTML = `<div class="stack">` + items.map((p) => `
      <div class="card tap provider-card" data-id="${p.id}">
        <div class="avatar">${p.avatar_url ? `<img src="${esc(p.avatar_url)}" alt="">` : '🙂'}</div>
        <div class="info">
          <div class="name">${esc(p.name)} ${p.verified ? '✅' : ''}</div>
          <div class="small"><span class="stars">${starsOf(p.rating_avg)}</span> <span class="muted">(${p.rating_count})</span></div>
        </div>
        <div class="price">${money(p.rate_hour)}<span class="small muted">/h</span></div>
      </div>`).join('') + `</div>`;
    $('#list').querySelectorAll('.card').forEach((c) => { c.onclick = () => go('#/prestador/' + c.dataset.id); });
  } catch (err) { $('#list').innerHTML = emptyState('😕', err.message); }
}

export async function notificationsPage() {
  render(`${topbar('Avisos 🔔')}<div id="list">${skeletons(3)}</div>`);
  try {
    const json = (await get('/notifications')).data;
    post('/notifications/read', {}).catch(() => {});
    if (!json.items.length) { $('#list').innerHTML = emptyState('🔔', 'Sin novedades por ahora.'); return; }
    $('#list').innerHTML = `<div class="stack">` + json.items.map((n) => `
      <div class="card ${n.data ? 'tap' : ''}" data-data='${esc(n.data || '')}'>
        <div class="row between"><b>${esc(n.title)}</b><span class="small muted">${timeAgo(n.created_at)}</span></div>
        <p class="muted small">${esc(n.body)}</p>
      </div>`).join('') + `</div>`;
    $('#list').querySelectorAll('.card.tap').forEach((c) => {
      c.onclick = () => {
        try {
          const data = JSON.parse(c.dataset.data);
          if (data.booking_id) go('#/trabajos/' + data.booking_id);
        } catch { /* sin acción */ }
      };
    });
  } catch (err) { $('#list').innerHTML = emptyState('😕', err.message); }
}

export async function paymentsPage() {
  render(`${topbar('Mis pagos 🧾', '#/perfil')}<div id="list">${skeletons(3)}</div>`);
  try {
    const items = (await get('/payments')).data;
    if (!items.length) { $('#list').innerHTML = emptyState('🧾', 'Tus pagos y comprobantes van a aparecer acá.'); return; }
    $('#list').innerHTML = `<div class="stack">` + items.map((p) => `
      <div class="card tap" data-id="${p.id}">
        <div class="row between">
          <div><b>Trabajo ${esc(p.booking_code)}</b><div class="small muted">${esc(p.paid_at || p.created_at)}</div></div>
          <div style="text-align:right"><b class="price">${money(p.amount)}</b>
          <div class="small muted">Ver comprobante →</div></div>
        </div>
      </div>`).join('') + `</div>`;
    $('#list').querySelectorAll('.card').forEach((c) => { c.onclick = () => showReceipt(c.dataset.id); });
  } catch (err) { $('#list').innerHTML = emptyState('😕', err.message); }
}

async function showReceipt(id) {
  try {
    const r = (await get(`/payments/${id}/receipt`)).data;
    sheet(`
      <h2 class="center">Comprobante 🧾</h2>
      <div class="card stack" style="margin-top:14px;box-shadow:none;background:var(--surface-soft)">
        <div class="row between"><span class="muted">N°</span><b>${esc(r.receipt_number)}</b></div>
        <div class="row between"><span class="muted">Trabajo</span><b>${esc(r.booking_code)}</b></div>
        <div class="row between"><span class="muted">Fecha</span><b>${esc(r.paid_at)}</b></div>
        <div class="row between"><span class="muted">Medio</span><b>${esc(r.method)}</b></div>
        <div class="row between"><span class="muted">Monto</span><b class="price">${money(r.amount)}</b></div>
      </div>
      <button class="btn secondary" style="margin-top:14px" onclick="window.print()">🖨️ Imprimir / guardar PDF</button>
    `);
  } catch (err) { toast(err.message, 'error'); }
}
