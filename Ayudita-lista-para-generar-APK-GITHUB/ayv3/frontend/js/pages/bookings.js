/**
 * Trabajos: listado, detalle con timeline, acciones según rol,
 * extensión, pago, calificación y disputa.
 */
import { render, esc, toast, money, topbar, emptyState, skeletons, sheet, closeSheets,
         STATUS_LABEL, PAY_LABEL, UNIT_LABEL, timeAgo, $ } from '../core/ui.js';
import { go } from '../core/router.js';
import { get, post } from '../core/api.js';
import { store } from '../core/store.js';

export async function bookingsPage() {
  render(`
    ${topbar(store.role === 'provider' ? 'Mis trabajos 🛠️' : 'Mis contrataciones 📋')}
    <div class="chip-row" id="tabs" style="margin-bottom:14px">
      <button class="chip active" data-s="">Todos</button>
      <button class="chip" data-s="pending">Pendientes</button>
      <button class="chip" data-s="in_progress">En curso</button>
      <button class="chip" data-s="completed">Finalizados</button>
    </div>
    <div id="list">${skeletons(3)}</div>
  `);

  async function load(status = '') {
    $('#list').innerHTML = skeletons(3);
    try {
      const items = (await get('/bookings' + (status ? '?status=' + status : ''))).data;
      if (!items.length) {
        $('#list').innerHTML = emptyState('🍃', 'Nada por acá todavía.');
        return;
      }
      $('#list').innerHTML = `<div class="stack">` + items.map((b) => `
        <div class="card tap" data-id="${b.id}">
          <div class="row between">
            <div class="row">
              <span style="font-size:30px">${esc(b.category_icon)}</span>
              <div>
                <b>${esc(b.category_name)}</b>
                <div class="small muted">${store.role === 'provider' ? esc(b.client_name) : esc(b.provider_name)} · ${timeAgo(b.created_at)}</div>
              </div>
            </div>
            <div style="text-align:right">
              <span class="badge ${b.status}">${STATUS_LABEL[b.status]}</span>
              <div class="price small">${money(b.amount_total)}</div>
            </div>
          </div>
        </div>`).join('') + `</div>`;
      $('#list').querySelectorAll('.card').forEach((c) => { c.onclick = () => go('#/trabajos/' + c.dataset.id); });
    } catch (err) {
      $('#list').innerHTML = emptyState('😕', err.message);
    }
  }

  $('#tabs').querySelectorAll('.chip').forEach((chip) => {
    chip.onclick = () => {
      $('#tabs').querySelectorAll('.chip').forEach((c) => c.classList.remove('active'));
      chip.classList.add('active');
      load(chip.dataset.s);
    };
  });
  load();
}

export async function bookingDetailPage({ id }) {
  render(skeletons(4));
  let b;
  try { b = (await get('/bookings/' + id)).data; }
  catch (err) { render(topbar('Ups', '#/trabajos') + emptyState('😕', err.message)); return; }

  const isClient = store.user.id === +b.client_id;
  const otherName = isClient ? b.provider_name : b.client_name;
  const otherUserId = isClient ? +b.provider_user_id : +b.client_id;

  const actions = [];
  if (!isClient) {
    if (b.status === 'pending') actions.push(['accept', 'Aceptar trabajo ✅', 'btn'], ['reject', 'Rechazar', 'btn danger']);
    if (b.status === 'accepted') actions.push(['on_way', 'Voy en camino 🚶', 'btn'], ['start', 'Empezar trabajo 💪', 'btn mint']);
    if (b.status === 'on_way') actions.push(['start', 'Empezar trabajo 💪', 'btn']);
    if (b.status === 'in_progress') actions.push(['complete', 'Trabajo terminado 🎉', 'btn mint']);
  } else {
    if (['accepted', 'in_progress'].includes(b.status)) actions.push(['__extend', 'Extender contratación ⏰', 'btn secondary']);
    if (b.payment_status === 'unpaid' && !['cancelled', 'disputed'].includes(b.status)) actions.push(['__pay', `Pagar ${money(b.amount_total)} 💳`, 'btn']);
  }
  if (b.status === 'completed') actions.push(['__rate', 'Calificar ⭐', 'btn']);
  if (!['completed', 'cancelled', 'disputed'].includes(b.status)) actions.push(['__cancel', 'Cancelar', 'btn ghost']);
  if (['in_progress', 'completed'].includes(b.status)) actions.push(['__dispute', 'Tengo un problema 😟', 'btn ghost']);

  render(`
    ${topbar('Trabajo ' + b.code, '#/trabajos')}
    <div class="card stack">
      <div class="row between">
        <div class="row"><span style="font-size:34px">${esc(b.category_icon)}</span><h2>${esc(b.category_name)}</h2></div>
        <span class="badge ${b.status}">${STATUS_LABEL[b.status]}</span>
      </div>
      <div class="row between"><span class="muted">${isClient ? 'Prestador' : 'Cliente'}</span><b>${esc(otherName)}</b></div>
      <div class="row between"><span class="muted">Duración</span><b>${b.quantity} ${UNIT_LABEL[b.unit]}${b.quantity > 1 ? 's' : ''}</b></div>
      <div class="row between"><span class="muted">Comienza</span><b>${esc(b.start_at)}</b></div>
      <div class="row between"><span class="muted">Termina</span><b>${esc(b.end_at)}</b></div>
      ${b.address ? `<div class="row between"><span class="muted">Dónde</span><b>${esc(b.address)}</b></div>` : ''}
      <div class="row between"><span class="muted">Pago</span><span class="badge ${b.payment_status}">${PAY_LABEL[b.payment_status]}</span></div>
      <div class="row between" style="font-size:1.15rem"><b>Total</b><b class="price">${money(b.amount_total)}</b></div>
      ${b.description ? `<p class="muted">"${esc(b.description)}"</p>` : ''}
    </div>

    <h2 class="section-title">Seguimiento 🧭</h2>
    <div class="card timeline">
      ${b.history.map((h, i) => `
        <div class="tl-item">
          <div><div class="tl-dot"></div>${i < b.history.length - 1 ? '<div class="tl-line"></div>' : ''}</div>
          <div style="padding-bottom:14px"><b>${STATUS_LABEL[h.status] || esc(h.status)}</b>
          <div class="small muted">${esc(h.created_at)}</div></div>
        </div>`).join('')}
    </div>

    <div class="stack" style="margin-top:20px">
      <button class="btn secondary" id="chatBtn">💬 Chatear con ${esc(otherName.split(' ')[0])}</button>
      <div id="actions" class="stack"></div>
    </div>
  `);

  $('#chatBtn').onclick = async () => {
    const json = await post('/conversations', { user_id: otherUserId, booking_id: +b.id });
    go('#/chat/' + json.data.conversation_id);
  };

  const box = $('#actions');
  actions.forEach(([action, label, cls]) => {
    const btn = document.createElement('button');
    btn.className = cls;
    btn.textContent = label;
    btn.onclick = () => handleAction(action, b);
    box.appendChild(btn);
  });
}

async function handleAction(action, b) {
  const reload = () => bookingDetailPage({ id: b.id });
  try {
    if (action === '__pay') return openPaySheet(b, reload);
    if (action === '__rate') return openRateSheet(b, reload);
    if (action === '__extend') return openExtendSheet(b, reload);
    if (action === '__cancel') {
      if (!confirm('¿Seguro que querés cancelar este trabajo?')) return;
      await post(`/bookings/${b.id}/cancel`, {});
      toast('Trabajo cancelado', 'success');
      return reload();
    }
    if (action === '__dispute') {
      const reason = prompt('Contanos qué pasó (mínimo 10 letras):');
      if (!reason) return;
      await post(`/bookings/${b.id}/dispute`, { reason });
      toast('Disputa abierta. Te vamos a ayudar 🤝', 'success');
      return reload();
    }
    await post(`/bookings/${b.id}/${action}`, {});
    toast('¡Listo! ✅', 'success');
    reload();
  } catch (err) {
    toast(err.message, 'error');
  }
}

function openPaySheet(b, done) {
  const node = sheet(`
    <h2>Pagar ${money(b.amount_total)} 💳</h2>
    <p class="muted">Tu pago queda protegido en Ayudita hasta que el trabajo esté bien terminado 🛡️</p>
    <div class="stack" style="margin-top:16px">
      <button class="btn" data-m="card">💳 Tarjeta de crédito / débito</button>
      <button class="btn secondary" data-m="mercadopago">🩵 Mercado Pago</button>
      <button class="btn secondary" data-m="transfer">🏦 Transferencia</button>
    </div>
  `);
  node.querySelectorAll('[data-m]').forEach((btn) => {
    btn.onclick = async () => {
      try {
        await post(`/bookings/${b.id}/pay`, { method: btn.dataset.m });
        closeSheets();
        toast('¡Pago realizado! 🎉', 'success');
        done();
      } catch (err) { toast(err.message, 'error'); }
    };
  });
}

function openRateSheet(b, done) {
  let stars = 0;
  const node = sheet(`
    <h2 class="center">¿Cómo salió todo? ⭐</h2>
    <div class="rate-stars" id="stars" style="margin:18px 0">
      ${[1, 2, 3, 4, 5].map((n) => `<button data-n="${n}" aria-label="${n} estrellas">⭐</button>`).join('')}
    </div>
    <div class="field">
      <textarea class="input" id="comment" placeholder="Contanos tu experiencia (opcional) 💬"></textarea>
    </div>
    <button class="btn" id="send" disabled>Enviar calificación</button>
  `);
  node.querySelectorAll('#stars button').forEach((btn) => {
    btn.onclick = () => {
      stars = +btn.dataset.n;
      node.querySelectorAll('#stars button').forEach((s) => s.classList.toggle('on', +s.dataset.n <= stars));
      node.querySelector('#send').disabled = false;
    };
  });
  node.querySelector('#send').onclick = async () => {
    try {
      await post(`/bookings/${b.id}/rate`, { stars, comment: node.querySelector('#comment').value.trim() || undefined });
      closeSheets();
      toast('¡Gracias por calificar! 💛', 'success');
      done();
    } catch (err) { toast(err.message, 'error'); }
  };
}

function openExtendSheet(b, done) {
  const node = sheet(`
    <h2>Extender contratación ⏰</h2>
    <p class="muted">Agregá más ${UNIT_LABEL[b.unit]}s al trabajo actual.</p>
    <div class="field" style="margin-top:14px">
      <label>¿Cuántos ${UNIT_LABEL[b.unit]}s más?</label>
      <input class="input" id="extra" type="number" min="1" max="365" value="1" inputmode="numeric">
      <span class="hint">Costo extra: <b id="cost">${money(b.rate)}</b></span>
    </div>
    <button class="btn" id="ok">Confirmar extensión</button>
  `);
  const input = node.querySelector('#extra');
  input.oninput = () => { node.querySelector('#cost').textContent = money(b.rate * Math.max(1, +input.value || 1)); };
  node.querySelector('#ok').onclick = async () => {
    try {
      await post(`/bookings/${b.id}/extend`, { extra_quantity: Math.max(1, +input.value || 1) });
      closeSheets();
      toast('¡Contratación extendida! ⏰', 'success');
      done();
    } catch (err) { toast(err.message, 'error'); }
  };
}
