/**
 * Ayudita · Panel de Administración
 * SPA liviana: login de admin + secciones de gestión.
 */
const API = (window.AYUDITA_API_URL || 'http://localhost:8080') + '/api/v1';
const KEY = 'ayudita_admin';

// ---------------- Sesión ----------------
const session = {
  get: () => { try { return JSON.parse(localStorage.getItem(KEY)); } catch { return null; } },
  set: (s) => localStorage.setItem(KEY, JSON.stringify(s)),
  clear: () => localStorage.removeItem(KEY),
};

// ---------------- HTTP ----------------
async function api(path, { method = 'GET', body } = {}) {
  const s = session.get();
  const res = await fetch(API + path, {
    method,
    headers: {
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(s?.access_token ? { Authorization: 'Bearer ' + s.access_token } : {}),
    },
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json().catch(() => ({}));
  if (res.status === 401 && s?.refresh_token && !path.startsWith('/auth/')) {
    const r = await fetch(API + '/auth/refresh', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: s.refresh_token }),
    });
    const rj = await r.json().catch(() => ({}));
    if (r.ok) { session.set(rj.data); return api(path, { method, body }); }
    session.clear(); renderLogin();
    throw new Error('Sesión expirada');
  }
  if (!res.ok) throw new Error(json.error?.message || 'Error de servidor');
  return json;
}

// ---------------- Utilidades UI ----------------
const $ = (s, r = document) => r.querySelector(s);
const esc = (v) => { const d = document.createElement('div'); d.textContent = String(v ?? ''); return d.innerHTML; };
const money = (v) => '$' + Number(v || 0).toLocaleString('es-AR', { maximumFractionDigits: 0 });

function toast(msg, type = '') {
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.textContent = msg;
  $('#toasts').appendChild(el);
  setTimeout(() => el.remove(), 3200);
}

function modal(html) {
  const bd = document.createElement('div');
  bd.className = 'modal-backdrop';
  bd.innerHTML = `<div class="modal">${html}</div>`;
  bd.onclick = (e) => { if (e.target === bd) bd.remove(); };
  document.body.appendChild(bd);
  return bd;
}

function confirmAction(text) { return window.confirm(text); }

// ---------------- Login ----------------
function renderLogin() {
  $('#app').innerHTML = `
    <div class="login-wrap">
      <div class="card login-card">
        <div class="logo">💛</div>
        <h1 style="text-align:center;margin-bottom:4px">Ayudita Admin</h1>
        <p class="muted small" style="text-align:center;margin-bottom:20px">Panel de administración</p>
        <form id="loginForm">
          <div class="field"><label>Email</label><input class="input" id="email" type="email" required></div>
          <div class="field"><label>Contraseña</label><input class="input" id="pass" type="password" required></div>
          <button class="btn" style="width:100%" type="submit">Entrar</button>
        </form>
      </div>
    </div>`;
  $('#loginForm').onsubmit = async (e) => {
    e.preventDefault();
    try {
      const json = await api('/auth/login', { method: 'POST', body: { email: $('#email').value.trim(), password: $('#pass').value } });
      if (json.data.user.role !== 'admin') { toast('Esta cuenta no es de administrador', 'error'); return; }
      session.set(json.data);
      renderShell();
      go('dashboard');
    } catch (err) { toast(err.message, 'error'); }
  };
}

// ---------------- Shell ----------------
const SECTIONS = [
  ['dashboard',   '📊', 'Dashboard'],
  ['users',       '👥', 'Usuarios'],
  ['payments',    '💳', 'Pagos'],
  ['payouts',     '💸', 'Liberaciones'],
  ['withdrawals', '🏦', 'Retiros'],
  ['categories',  '🧰', 'Categorías'],
  ['banners',     '🖼️', 'Banners'],
  ['disputes',    '⚖️', 'Disputas'],
  ['reports',     '📈', 'Reportes'],
  ['settings',    '⚙️', 'Configuración'],
  ['logs',        '📜', 'Logs'],
];

function renderShell() {
  $('#app').innerHTML = `
    <div class="layout">
      <aside class="side">
        <div class="brand"><span class="logo">💛</span><span class="brand-name">Ayudita</span></div>
        <nav>${SECTIONS.map(([id, ico, lbl]) =>
          `<button class="nav-item" data-s="${id}"><span class="ico">${ico}</span><span class="lbl">${lbl}</span></button>`).join('')}
          <button class="nav-item" id="logout"><span class="ico">🚪</span><span class="lbl">Salir</span></button>
        </nav>
      </aside>
      <main class="main" id="main"></main>
    </div>`;
  document.querySelectorAll('.nav-item[data-s]').forEach((b) => { b.onclick = () => go(b.dataset.s); });
  $('#logout').onclick = () => { session.clear(); renderLogin(); };
}

function go(sectionId) {
  document.querySelectorAll('.nav-item').forEach((b) => b.classList.toggle('active', b.dataset.s === sectionId));
  PAGES[sectionId]?.();
}

// ---------------- Páginas ----------------
const PAGES = {
  async dashboard() {
    const main = $('#main');
    main.innerHTML = '<h1>Dashboard 📊</h1><p class="muted">Cargando...</p>';
    try {
      const d = (await api('/admin/dashboard')).data;
      const revenue = (await api('/admin/reports/revenue')).data;
      const max = Math.max(...revenue.map((r) => +r.gross), 1);
      main.innerHTML = `
        <div class="page-head"><h1>Dashboard 📊</h1><span class="muted small">${new Date().toLocaleDateString('es-AR')}</span></div>
        <div class="stats">
          <div class="card stat"><div class="num">${d.clients}</div><div class="lbl">Clientes</div></div>
          <div class="card stat"><div class="num">${d.providers}</div><div class="lbl">Prestadores</div></div>
          <div class="card stat"><div class="num">${d.bookings}</div><div class="lbl">Trabajos</div></div>
          <div class="card stat"><div class="num">${money(d.gross_volume)}</div><div class="lbl">Volumen total</div></div>
          <div class="card stat"><div class="num">${money(d.total_commission)}</div><div class="lbl">Comisiones ganadas</div></div>
        </div>
        <div class="stats">
          <div class="card stat"><div class="num">${d.payouts_pending}</div><div class="lbl">⏳ Liberaciones pendientes</div></div>
          <div class="card stat"><div class="num">${d.withdrawals_requested}</div><div class="lbl">🏦 Retiros solicitados</div></div>
          <div class="card stat"><div class="num">${d.disputes_open}</div><div class="lbl">⚖️ Disputas abiertas</div></div>
          <div class="card stat"><div class="num">${d.bookings_pending}</div><div class="lbl">📋 Trabajos pendientes</div></div>
        </div>
        <div class="card">
          <h3 style="margin-bottom:8px">Ingresos por mes 💰</h3>
          <div class="bars" style="margin-bottom:26px">
            ${revenue.map((r) => `<div class="bar" style="height:${(+r.gross / max) * 100}%" title="${r.month}: ${money(r.gross)}"><span>${r.month.slice(5)}</span></div>`).join('') || '<p class="muted">Sin datos todavía</p>'}
          </div>
        </div>`;
    } catch (err) { main.innerHTML = `<h1>Dashboard</h1><p class="muted">${esc(err.message)}</p>`; }
  },

  async users() {
    const main = $('#main');
    main.innerHTML = `
      <div class="page-head"><h1>Usuarios 👥</h1></div>
      <div class="filters">
        <select class="input" id="fRole"><option value="">Todos los roles</option>
          <option value="client">Clientes</option><option value="provider">Prestadores</option></select>
        <select class="input" id="fStatus"><option value="">Todos los estados</option>
          <option value="active">Activos</option><option value="blocked">Bloqueados</option></select>
        <input class="input" id="fQ" placeholder="Buscar nombre o email...">
        <button class="btn" id="fGo">Filtrar</button>
      </div>
      <div class="table-wrap" id="tbl"></div>`;

    async function load() {
      const p = new URLSearchParams();
      if ($('#fRole').value) p.set('role', $('#fRole').value);
      if ($('#fStatus').value) p.set('status', $('#fStatus').value);
      if ($('#fQ').value.trim()) p.set('q', $('#fQ').value.trim());
      const rows = (await api('/admin/users?' + p)).data;
      $('#tbl').innerHTML = `<table><thead><tr>
        <th>Nombre</th><th>Rol</th><th>Email</th><th>Estado</th><th>Verificado</th><th>Saldo</th><th></th>
      </tr></thead><tbody>${rows.map((u) => `
        <tr>
          <td><b>${esc(u.name)}</b><div class="small muted">${esc(u.city || '')}</div></td>
          <td>${u.role === 'provider' ? '🛠️ Prestador' : '🙋 Cliente'}</td>
          <td>${esc(u.email)}</td>
          <td><span class="badge ${u.status === 'active' ? 'ok' : 'bad'}">${u.status}</span></td>
          <td>${u.role === 'provider' ? (u.provider_verified == 1 ? '✅' : `<button class="btn sm outline" data-verify="${u.id}">Verificar</button>`) : '—'}</td>
          <td>${u.balance != null ? money(u.balance) : '—'}</td>
          <td>
            ${u.status === 'active'
              ? `<button class="btn sm danger" data-block="${u.id}">Bloquear</button>`
              : `<button class="btn sm success" data-unblock="${u.id}">Activar</button>`}
          </td>
        </tr>`).join('')}</tbody></table>`;

      $('#tbl').querySelectorAll('[data-block]').forEach((b) => b.onclick = async () => {
        if (!confirmAction('¿Bloquear a este usuario?')) return;
        await api('/admin/users/' + b.dataset.block, { method: 'PUT', body: { status: 'blocked' } });
        toast('Usuario bloqueado', 'success'); load();
      });
      $('#tbl').querySelectorAll('[data-unblock]').forEach((b) => b.onclick = async () => {
        await api('/admin/users/' + b.dataset.unblock, { method: 'PUT', body: { status: 'active' } });
        toast('Usuario activado', 'success'); load();
      });
      $('#tbl').querySelectorAll('[data-verify]').forEach((b) => b.onclick = async () => {
        await api('/admin/users/' + b.dataset.verify, { method: 'PUT', body: { verified: true } });
        toast('Prestador verificado ✅', 'success'); load();
      });
    }
    $('#fGo').onclick = load;
    load();
  },

  async payments() {
    const main = $('#main');
    main.innerHTML = `<div class="page-head"><h1>Pagos 💳</h1></div><div class="table-wrap" id="tbl"><p class="muted" style="padding:16px">Cargando...</p></div>`;
    const rows = (await api('/admin/payments')).data;
    $('#tbl').innerHTML = `<table><thead><tr>
      <th>Trabajo</th><th>Cliente</th><th>Monto</th><th>Comisión</th><th>Neto prestador</th><th>Medio</th><th>Estado</th><th>Fecha</th>
    </tr></thead><tbody>${rows.map((p) => `
      <tr>
        <td><b>${esc(p.booking_code)}</b></td>
        <td>${esc(p.payer_name)}</td>
        <td><b>${money(p.amount)}</b></td>
        <td>${money(p.commission_amount)} <span class="muted small">(${p.commission_percent}%)</span></td>
        <td>${money(p.net_amount)}</td>
        <td>${esc(p.method)}</td>
        <td><span class="badge ${p.status === 'completed' ? 'ok' : p.status === 'pending' ? 'warn' : 'bad'}">${p.status}</span></td>
        <td class="small muted">${esc(p.paid_at || p.created_at)}</td>
      </tr>`).join('') || '<tr><td colspan="8" class="muted">Sin pagos todavía</td></tr>'}</tbody></table>`;
  },

  async payouts() {
    const main = $('#main');
    main.innerHTML = `
      <div class="page-head"><h1>Liberaciones de pago 💸</h1>
      <span class="muted small">Aprobá para acreditar el neto al saldo del prestador</span></div>
      <div class="table-wrap" id="tbl"></div>`;
    async function load() {
      const rows = (await api('/admin/payouts')).data;
      $('#tbl').innerHTML = `<table><thead><tr>
        <th>Prestador</th><th>Monto neto</th><th>Estado</th><th>Fecha</th><th></th>
      </tr></thead><tbody>${rows.map((p) => `
        <tr>
          <td><b>${esc(p.provider_name)}</b></td>
          <td><b>${money(p.amount)}</b></td>
          <td><span class="badge ${p.status === 'approved' ? 'ok' : p.status === 'pending' ? 'warn' : 'bad'}">${p.status}</span></td>
          <td class="small muted">${esc(p.created_at)}</td>
          <td>${p.status === 'pending' ? `<button class="btn sm success" data-ok="${p.id}">Aprobar liberación</button>` : ''}</td>
        </tr>`).join('') || '<tr><td colspan="5" class="muted">Nada pendiente 🎉</td></tr>'}</tbody></table>`;
      $('#tbl').querySelectorAll('[data-ok]').forEach((b) => b.onclick = async () => {
        if (!confirmAction('¿Liberar este pago al prestador?')) return;
        await api(`/admin/payouts/${b.dataset.ok}/approve`, { method: 'POST', body: {} });
        toast('Pago liberado 💸', 'success'); load();
      });
    }
    load();
  },

  async withdrawals() {
    const main = $('#main');
    main.innerHTML = `<div class="page-head"><h1>Retiros 🏦</h1></div><div class="table-wrap" id="tbl"></div>`;
    async function load() {
      const rows = (await api('/admin/withdrawals')).data;
      $('#tbl').innerHTML = `<table><thead><tr>
        <th>Prestador</th><th>Monto</th><th>Datos bancarios</th><th>Estado</th><th>Fecha</th><th></th>
      </tr></thead><tbody>${rows.map((w) => {
        let bank = '';
        try { bank = Object.values(JSON.parse(w.bank_info || '{}')).join(' · '); } catch { bank = ''; }
        return `<tr>
          <td><b>${esc(w.provider_name)}</b></td>
          <td><b>${money(w.amount)}</b></td>
          <td class="small">${esc(bank)}</td>
          <td><span class="badge ${w.status === 'paid' ? 'ok' : w.status === 'requested' ? 'warn' : 'bad'}">${w.status}</span></td>
          <td class="small muted">${esc(w.created_at)}</td>
          <td>${w.status === 'requested' ? `
            <button class="btn sm success" data-a="${w.id}">Aprobar y pagar</button>
            <button class="btn sm danger" data-r="${w.id}">Rechazar</button>` : esc(w.notes || '')}</td>
        </tr>`;
      }).join('') || '<tr><td colspan="6" class="muted">Sin retiros pendientes</td></tr>'}</tbody></table>`;

      $('#tbl').querySelectorAll('[data-a]').forEach((b) => b.onclick = async () => {
        if (!confirmAction('¿Confirmás que ya transferiste el dinero al prestador?')) return;
        await api(`/admin/withdrawals/${b.dataset.a}/process`, { method: 'POST', body: { decision: 'approved' } });
        toast('Retiro aprobado 🏦', 'success'); load();
      });
      $('#tbl').querySelectorAll('[data-r]').forEach((b) => b.onclick = async () => {
        const notes = prompt('Motivo del rechazo:') || undefined;
        await api(`/admin/withdrawals/${b.dataset.r}/process`, { method: 'POST', body: { decision: 'rejected', notes } });
        toast('Retiro rechazado (saldo devuelto)', 'success'); load();
      });
    }
    load();
  },

  async categories() {
    const main = $('#main');
    main.innerHTML = `
      <div class="page-head"><h1>Categorías 🧰</h1><button class="btn" id="add">➕ Nueva categoría</button></div>
      <div class="table-wrap" id="tbl"></div>`;
    async function load() {
      const rows = (await api('/admin/categories')).data;
      $('#tbl').innerHTML = `<table><thead><tr><th>Icono</th><th>Nombre</th><th>Descripción</th><th>Activa</th><th></th></tr></thead>
      <tbody>${rows.map((c) => `
        <tr>
          <td style="font-size:22px">${esc(c.icon)}</td>
          <td><b>${esc(c.name)}</b></td>
          <td class="muted">${esc(c.description || '')}</td>
          <td><span class="badge ${+c.active ? 'ok' : 'bad'}">${+c.active ? 'Sí' : 'No'}</span></td>
          <td>
            <button class="btn sm outline" data-e="${c.id}">Editar</button>
            <button class="btn sm danger" data-d="${c.id}">Eliminar</button>
          </td>
        </tr>`).join('')}</tbody></table>`;

      const openForm = (cat = null) => {
        const m = modal(`
          <h2 style="margin-bottom:14px">${cat ? 'Editar' : 'Nueva'} categoría</h2>
          <div class="grid-2">
            <div class="field"><label>Nombre</label><input class="input" id="cName" value="${esc(cat?.name || '')}"></div>
            <div class="field"><label>Icono (emoji)</label><input class="input" id="cIcon" value="${esc(cat?.icon || '💼')}"></div>
          </div>
          <div class="field"><label>Descripción</label><input class="input" id="cDesc" value="${esc(cat?.description || '')}"></div>
          ${cat ? `<div class="field"><label>Activa</label><select class="input" id="cActive">
            <option value="1" ${+cat.active ? 'selected' : ''}>Sí</option><option value="0" ${+cat.active ? '' : 'selected'}>No</option></select></div>` : ''}
          <button class="btn" id="cSave" style="width:100%">Guardar</button>`);
        m.querySelector('#cSave').onclick = async () => {
          const body = {
            name: m.querySelector('#cName').value.trim(),
            icon: m.querySelector('#cIcon').value.trim(),
            description: m.querySelector('#cDesc').value.trim() || undefined,
          };
          if (cat) body.active = m.querySelector('#cActive').value === '1';
          try {
            if (cat) await api('/admin/categories/' + cat.id, { method: 'PUT', body });
            else await api('/admin/categories', { method: 'POST', body });
            m.remove(); toast('Categoría guardada ✅', 'success'); load();
          } catch (err) { toast(err.message, 'error'); }
        };
      };

      $('#add').onclick = () => openForm();
      $('#tbl').querySelectorAll('[data-e]').forEach((b) => b.onclick = () => openForm(rows.find((r) => r.id == b.dataset.e)));
      $('#tbl').querySelectorAll('[data-d]').forEach((b) => b.onclick = async () => {
        if (!confirmAction('¿Eliminar esta categoría? (soft delete)')) return;
        await api('/admin/categories/' + b.dataset.d, { method: 'DELETE' });
        toast('Categoría eliminada', 'success'); load();
      });
    }
    load();
  },

  async banners() {
    const main = $('#main');
    main.innerHTML = `
      <div class="page-head"><h1>Banners 🖼️</h1><button class="btn" id="add">➕ Nuevo banner</button></div>
      <div class="table-wrap" id="tbl"></div>`;
    async function load() {
      const rows = (await api('/admin/banners')).data;
      $('#tbl').innerHTML = `<table><thead><tr><th>Emoji</th><th>Título</th><th>Activo</th><th></th></tr></thead>
      <tbody>${rows.map((b) => `
        <tr>
          <td style="font-size:22px">${esc(b.emoji || '💛')}</td>
          <td><b>${esc(b.title)}</b></td>
          <td><span class="badge ${+b.active ? 'ok' : 'bad'}">${+b.active ? 'Sí' : 'No'}</span></td>
          <td>
            <button class="btn sm outline" data-t="${b.id}" data-active="${b.active}">${+b.active ? 'Desactivar' : 'Activar'}</button>
            <button class="btn sm danger" data-d="${b.id}">Eliminar</button>
          </td>
        </tr>`).join('') || '<tr><td colspan="4" class="muted">Sin banners</td></tr>'}</tbody></table>`;

      $('#add').onclick = () => {
        const m = modal(`
          <h2 style="margin-bottom:14px">Nuevo banner</h2>
          <div class="field"><label>Título</label><input class="input" id="bTitle"></div>
          <div class="field"><label>Emoji</label><input class="input" id="bEmoji" value="💛"></div>
          <button class="btn" id="bSave" style="width:100%">Crear</button>`);
        m.querySelector('#bSave').onclick = async () => {
          try {
            await api('/admin/banners', { method: 'POST', body: {
              title: m.querySelector('#bTitle').value.trim(),
              emoji: m.querySelector('#bEmoji').value.trim(),
            }});
            m.remove(); toast('Banner creado ✅', 'success'); load();
          } catch (err) { toast(err.message, 'error'); }
        };
      };
      $('#tbl').querySelectorAll('[data-t]').forEach((b) => b.onclick = async () => {
        await api('/admin/banners/' + b.dataset.t, { method: 'PUT', body: { active: !(+b.dataset.active) } });
        load();
      });
      $('#tbl').querySelectorAll('[data-d]').forEach((b) => b.onclick = async () => {
        if (!confirmAction('¿Eliminar banner?')) return;
        await api('/admin/banners/' + b.dataset.d, { method: 'DELETE' });
        load();
      });
    }
    load();
  },

  async disputes() {
    const main = $('#main');
    main.innerHTML = `<div class="page-head"><h1>Disputas ⚖️</h1></div><div class="table-wrap" id="tbl"></div>`;
    async function load() {
      const rows = (await api('/admin/disputes')).data;
      $('#tbl').innerHTML = `<table><thead><tr>
        <th>Trabajo</th><th>Abierta por</th><th>Motivo</th><th>Estado</th><th></th>
      </tr></thead><tbody>${rows.map((d) => `
        <tr>
          <td><b>${esc(d.booking_code)}</b></td>
          <td>${esc(d.opened_by_name)}</td>
          <td class="small">${esc(d.reason)}</td>
          <td><span class="badge ${d.status === 'open' ? 'warn' : d.status === 'resolved' ? 'ok' : 'bad'}">${d.status}</span></td>
          <td>${d.status === 'open' ? `<button class="btn sm" data-r="${d.id}">Resolver</button>` : `<span class="small muted">${esc(d.resolution || '')}</span>`}</td>
        </tr>`).join('') || '<tr><td colspan="5" class="muted">Sin disputas 🎉</td></tr>'}</tbody></table>`;

      $('#tbl').querySelectorAll('[data-r]').forEach((b) => b.onclick = () => {
        const m = modal(`
          <h2 style="margin-bottom:14px">Resolver disputa</h2>
          <div class="field"><label>Decisión</label>
            <select class="input" id="dStatus"><option value="resolved">Resuelta a favor</option><option value="rejected">Rechazada</option></select></div>
          <div class="field"><label>Resolución (visible en auditoría)</label><textarea class="input" id="dRes" rows="3"></textarea></div>
          <button class="btn" id="dSave" style="width:100%">Guardar decisión</button>`);
        m.querySelector('#dSave').onclick = async () => {
          try {
            await api(`/admin/disputes/${b.dataset.r}/resolve`, { method: 'POST', body: {
              status: m.querySelector('#dStatus').value,
              resolution: m.querySelector('#dRes').value.trim(),
            }});
            m.remove(); toast('Disputa procesada ⚖️', 'success'); load();
          } catch (err) { toast(err.message, 'error'); }
        };
      });
    }
    load();
  },

  async reports() {
    const main = $('#main');
    main.innerHTML = `<div class="page-head"><h1>Reportes 📈</h1></div><div id="rep" class="stats" style="grid-template-columns:1fr 1fr"></div>`;
    const [cats, provs, clients, active] = await Promise.all([
      api('/admin/reports/top-categories'), api('/admin/reports/top-providers'),
      api('/admin/reports/top-clients'), api('/admin/reports/active-users'),
    ]);
    const list = (title, rows, fmt) => `
      <div class="card"><h3 style="margin-bottom:10px">${title}</h3>
        ${rows.length ? rows.map(fmt).join('') : '<p class="muted small">Sin datos todavía</p>'}</div>`;
    $('#rep').innerHTML =
      list('🧰 Servicios más pedidos', cats.data, (c) =>
        `<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line)">
          <span>${esc(c.icon)} ${esc(c.name)}</span><b>${c.total_bookings} trabajos</b></div>`) +
      list('⭐ Prestadores mejor calificados', provs.data, (p) =>
        `<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line)">
          <span>${esc(p.name)}</span><b>★ ${(+p.rating_avg).toFixed(1)} (${p.rating_count})</b></div>`) +
      list('🙋 Clientes frecuentes', clients.data, (c) =>
        `<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line)">
          <span>${esc(c.name)}</span><b>${c.total_bookings} · ${money(c.total_spent)}</b></div>`) +
      list('📅 Clientes activos por mes', active.data, (a) =>
        `<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line)">
          <span>${esc(a.month)}</span><b>${a.active_clients}</b></div>`);
  },

  async settings() {
    const main = $('#main');
    main.innerHTML = `<div class="page-head"><h1>Configuración ⚙️</h1></div><div class="card" id="box" style="max-width:560px">Cargando...</div>`;
    const rows = (await api('/admin/settings')).data;
    const val = (k) => rows.find((r) => r.setting_key === k)?.setting_value || '';
    $('#box').innerHTML = `
      <div class="field"><label>Nombre de la app</label><input class="input" id="sName" value="${esc(val('app_name'))}"></div>
      <div class="grid-2">
        <div class="field"><label>Comisión de la plataforma (%)</label>
          <input class="input" id="sComm" type="number" min="0" max="100" step="0.5" value="${esc(val('commission_percent'))}">
        </div>
        <div class="field"><label>Impuestos (%)</label>
          <input class="input" id="sTax" type="number" min="0" max="100" step="0.5" value="${esc(val('tax_percent'))}">
        </div>
      </div>
      <div class="grid-2">
        <div class="field"><label>Moneda</label><input class="input" id="sCur" value="${esc(val('currency'))}"></div>
        <div class="field"><label>Retiro mínimo</label><input class="input" id="sMin" type="number" value="${esc(val('min_withdrawal'))}"></div>
      </div>
      <div class="field"><label>Email de soporte</label><input class="input" id="sMail" type="email" value="${esc(val('support_email'))}"></div>
      <button class="btn" id="sSave" style="width:100%">Guardar configuración</button>`;
    $('#sSave').onclick = async () => {
      try {
        await api('/admin/settings', { method: 'PUT', body: {
          app_name: $('#sName').value.trim(),
          commission_percent: $('#sComm').value,
          tax_percent: $('#sTax').value,
          currency: $('#sCur').value.trim(),
          min_withdrawal: $('#sMin').value,
          support_email: $('#sMail').value.trim(),
        }});
        toast('Configuración guardada ✅ (la nueva comisión aplica a los próximos pagos)', 'success');
      } catch (err) { toast(err.message, 'error'); }
    };
  },

  async logs() {
    const main = $('#main');
    main.innerHTML = `<div class="page-head"><h1>Auditoría 📜</h1></div><div class="table-wrap" id="tbl"></div>`;
    const rows = (await api('/admin/logs')).data;
    $('#tbl').innerHTML = `<table><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>IP</th></tr></thead>
    <tbody>${rows.map((l) => `
      <tr>
        <td class="small muted">${esc(l.created_at)}</td>
        <td>${esc(l.user_name || 'Sistema')}</td>
        <td><b>${esc(l.action)}</b></td>
        <td class="small">${esc(l.entity || '')} ${l.entity_id ? '#' + l.entity_id : ''}</td>
        <td class="small muted">${esc(l.ip || '')}</td>
      </tr>`).join('')}</tbody></table>`;
  },
};

// ---------------- Arranque ----------------
if (session.get()?.access_token) { renderShell(); go('dashboard'); }
else renderLogin();
