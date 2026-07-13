/**
 * Chat interno: lista de conversaciones y sala con polling liviano.
 * Soporta texto, foto (cámara/galería) y ubicación.
 */
import { render, esc, toast, topbar, emptyState, skeletons, timeAgo, $ } from '../core/ui.js';
import { go } from '../core/router.js';
import { get, post, upload } from '../core/api.js';
import { store } from '../core/store.js';
import { takePhoto, getPosition } from '../native.js';

let pollTimer = null;
export function stopChatPolling() { clearInterval(pollTimer); pollTimer = null; }

export async function chatListPage() {
  render(`${topbar('Mensajes 💬')}<div id="list">${skeletons(3)}</div>`);
  try {
    const items = (await get('/conversations')).data;
    if (!items.length) {
      $('#list').innerHTML = emptyState('💬', 'Cuando contrates o te contraten, vas a chatear por acá.');
      return;
    }
    $('#list').innerHTML = `<div class="stack">` + items.map((c) => `
      <div class="card tap row" data-id="${c.id}">
        <div class="avatar">${c.other_avatar ? `<img src="${esc(c.other_avatar)}" alt="">` : '🙂'}</div>
        <div style="flex:1;min-width:0">
          <div class="row between"><b>${esc(c.other_name)}</b><span class="small muted">${timeAgo(c.last_message_at)}</span></div>
          <div class="small muted" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${esc(c.last_message || 'Sin mensajes aún')}</div>
        </div>
        ${+c.unread ? `<span class="badge pending">${c.unread}</span>` : ''}
      </div>`).join('') + `</div>`;
    $('#list').querySelectorAll('.card').forEach((c) => { c.onclick = () => go('#/chat/' + c.dataset.id); });
  } catch (err) {
    $('#list').innerHTML = emptyState('😕', err.message);
  }
}

export async function chatRoomPage({ id }) {
  stopChatPolling();
  render(`
    ${topbar('Chat', '#/chats')}
    <div class="chat-box" id="box">${skeletons(2)}</div>
    <div class="chat-input">
      <button class="btn secondary sm" id="cam" aria-label="Enviar foto">📷</button>
      <button class="btn secondary sm" id="loc" aria-label="Enviar ubicación">📍</button>
      <input class="input" id="msg" placeholder="Escribí un mensaje..." autocomplete="off">
      <button class="btn sm" id="send" aria-label="Enviar">➤</button>
    </div>
  `);

  let lastId = 0;
  const myId = store.user.id;

  function bubble(m) {
    const mine = +m.sender_id === myId;
    let content = '';
    if (m.type === 'text') content = esc(m.body);
    if (m.type === 'image') content = `${esc(m.body || '')}<img src="${esc(m.file_url)}" alt="Foto" loading="lazy">`;
    if (m.type === 'file') content = `📎 <a href="${esc(m.file_url)}" target="_blank" rel="noopener">Ver archivo</a>`;
    if (m.type === 'location') content = `📍 <a href="https://maps.google.com/?q=${m.lat},${m.lng}" target="_blank" rel="noopener">Mi ubicación</a>`;
    return `<div class="msg ${mine ? 'mine' : 'theirs'}">${content}<span class="time">${(m.created_at || '').slice(11, 16)}</span></div>`;
  }

  async function poll(initial = false) {
    try {
      const msgs = (await get(`/conversations/${id}/messages?after_id=${lastId}`)).data;
      if (initial) $('#box').innerHTML = '';
      if (msgs.length) {
        lastId = msgs[msgs.length - 1].id;
        $('#box').insertAdjacentHTML('beforeend', msgs.map(bubble).join(''));
        window.scrollTo({ top: document.body.scrollHeight });
      }
    } catch { /* silencio: reintento en el próximo tick */ }
  }

  await poll(true);
  pollTimer = setInterval(poll, 3500);

  $('#send').onclick = sendText;
  $('#msg').addEventListener('keydown', (e) => { if (e.key === 'Enter') sendText(); });

  async function sendText() {
    const input = $('#msg');
    const body = input.value.trim();
    if (!body) return;
    input.value = '';
    try {
      await post(`/conversations/${id}/messages`, { type: 'text', body });
      poll();
    } catch (err) { toast(err.message, 'error'); }
  }

  $('#cam').onclick = async () => {
    try {
      const file = await takePhoto();
      if (!file) return;
      const fd = new FormData();
      fd.append('type', 'image');
      fd.append('file', file);
      await upload(`/conversations/${id}/messages`, fd);
      poll();
    } catch (err) { toast(err.message, 'error'); }
  };

  $('#loc').onclick = async () => {
    try {
      const pos = await getPosition();
      await post(`/conversations/${id}/messages`, { type: 'location', lat: pos.lat, lng: pos.lng });
      poll();
    } catch { toast('No pudimos obtener tu ubicación 📍', 'error'); }
  };
}
