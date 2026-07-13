/**
 * Home del cliente: saludo, búsqueda, categorías, banners.
 * Búsqueda con filtros y detalle del prestador con contratación.
 */
import { render, esc, toast, money, starsOf, topbar, emptyState, skeletons, sheet, closeSheets, $ } from '../core/ui.js';
import { go } from '../core/router.js';
import { get, post } from '../core/api.js';
import { store } from '../core/store.js';
import { getPosition } from '../native.js';

export async function homePage() {
  const user = store.user;
  render(`
    <div class="hello">
      <div>
        <h1>Hola, ${esc((user?.name || '').split(' ')[0])} 👋</h1>
        <p class="muted">¿Con qué te damos una mano hoy?</p>
      </div>
      <div class="avatar" role="img" aria-label="Tu perfil">${user?.avatar_url ? `<img src="${esc(user.avatar_url)}" alt="">` : '🙂'}</div>
    </div>
    <div class="search-bar" style="margin:16px 0">
      <span aria-hidden="true">🔍</span>
      <input id="q" placeholder="Buscar: electricista, niñera..." aria-label="Buscar servicios">
    </div>
    <div id="banners"></div>
    <h2 class="section-title">Servicios 🧰</h2>
    <div id="cats" class="cat-grid">${'<div class="skeleton" style="height:96px"></div>'.repeat(8)}</div>
  `);

  $('#q').addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.value.trim()) go('#/buscar?q=' + encodeURIComponent(e.target.value.trim()));
  });

  try {
    const cached = store.cache('categories');
    const cats = cached || (await get('/categories')).data;
    store.cache('categories', cats);
    $('#cats').innerHTML = cats.map((c) => `
      <button class="cat" data-id="${c.id}" data-name="${esc(c.name)}">
        <span class="ico" aria-hidden="true">${esc(c.icon)}</span>${esc(c.name)}
      </button>`).join('');
    $('#cats').querySelectorAll('.cat').forEach((b) => {
      b.onclick = () => go(`#/buscar?category_id=${b.dataset.id}&title=${encodeURIComponent(b.dataset.name)}`);
    });

    const banners = (await get('/banners')).data;
    $('#banners').innerHTML = banners.slice(0, 2).map((b) => `
      <div class="banner" style="margin-bottom:12px">
        <span class="emoji">${esc(b.emoji || '💛')}</span><span>${esc(b.title)}</span>
      </div>`).join('');
  } catch {
    $('#cats').innerHTML = emptyState('📡', 'Sin conexión. Mostrando lo último guardado.');
  }
}

export async function searchPage(_, query) {
  const title = query.get('title') || 'Buscar';
  render(`
    ${topbar(title, '#/home')}
    <div class="chip-row" id="filters" style="margin-bottom:14px">
      <button class="chip" data-sort="rating">⭐ Mejor puntuados</button>
      <button class="chip" data-sort="price_asc">💰 Más baratos</button>
      <button class="chip" data-sort="distance">📍 Cerca mío</button>
    </div>
    <div id="list">${skeletons(4)}</div>
  `);

  let sort = 'rating';
  let coords = null;

  async function load() {
    $('#list').innerHTML = skeletons(4);
    const params = new URLSearchParams();
    for (const key of ['category_id', 'q']) if (query.get(key)) params.set(key, query.get(key));
    params.set('sort', sort);
    if (coords) { params.set('lat', coords.lat); params.set('lng', coords.lng); }

    try {
      const providers = (await get('/providers?' + params)).data;
      if (!providers.length) {
        $('#list').innerHTML = emptyState('🌱', 'Todavía no hay prestadores acá. ¡Pronto habrá!');
        return;
      }
      $('#list').innerHTML = `<div class="stack">` + providers.map((p) => `
        <div class="card tap provider-card" data-id="${p.id}">
          <div class="avatar">${p.avatar_url ? `<img src="${esc(p.avatar_url)}" alt="">` : '🙂'}</div>
          <div class="info">
            <div class="name">${esc(p.name)} ${p.verified ? '<span title="Verificado">✅</span>' : ''}</div>
            <div class="small muted">${esc(p.city || '')} ${p.distance_km != null ? `· a ${p.distance_km} km` : ''}</div>
            <div class="small"><span class="stars">${starsOf(p.rating_avg)}</span> <span class="muted">(${p.rating_count})</span></div>
          </div>
          <div class="price">${money(p.rate_hour)}<span class="small muted">/h</span></div>
        </div>`).join('') + `</div>`;
      $('#list').querySelectorAll('.provider-card').forEach((c) => {
        c.onclick = () => go('#/prestador/' + c.dataset.id);
      });
    } catch (err) {
      $('#list').innerHTML = emptyState('😕', err.message);
    }
  }

  $('#filters').querySelectorAll('.chip').forEach((chip) => {
    chip.onclick = async () => {
      $('#filters').querySelectorAll('.chip').forEach((c) => c.classList.remove('active'));
      chip.classList.add('active');
      sort = chip.dataset.sort;
      if (sort === 'distance' && !coords) {
        coords = await getPosition().catch(() => null);
        if (!coords) toast('No pudimos obtener tu ubicación 📍', 'error');
      }
      load();
    };
  });

  load();
}

export async function providerPage({ id }) {
  render(skeletons(4));
  let p;
  try {
    p = (await get('/providers/' + id)).data;
  } catch (err) {
    render(topbar('Ups', '#/home') + emptyState('😕', err.message));
    return;
  }

  const ratings = (await get(`/providers/${id}/ratings`).catch(() => ({ data: [] }))).data;

  render(`
    ${topbar('', '#/home')}
    <div class="card center stack" style="align-items:center">
      <div class="avatar lg">${p.avatar_url ? `<img src="${esc(p.avatar_url)}" alt="">` : '🙂'}</div>
      <h1>${esc(p.name)} ${p.verified ? '✅' : ''}</h1>
      <div><span class="stars">${starsOf(p.rating_avg)}</span> <span class="muted">(${p.rating_count} opiniones) · ${p.jobs_done} trabajos</span></div>
      <div class="chip-row" style="justify-content:center">
        ${p.categories.map((c) => `<span class="chip">${esc(c.icon)} ${esc(c.name)}</span>`).join('')}
      </div>
      <p class="muted">${esc(p.bio || '')}</p>
      <div class="grid-2" style="width:100%">
        <div class="stat card" style="box-shadow:none;background:var(--surface-soft)">
          <div class="num">${money(p.rate_hour)}</div><div class="lbl">por hora</div>
        </div>
        <div class="stat card" style="box-shadow:none;background:var(--surface-soft)">
          <div class="num">${money(p.rate_day)}</div><div class="lbl">por día</div>
        </div>
      </div>
    </div>

    ${p.photos.length ? `<h2 class="section-title">Sus trabajos 📸</h2>
      <div class="photo-strip">${p.photos.map((f) => `<img src="${esc(f.url)}" alt="Foto de trabajo">`).join('')}</div>` : ''}

    ${p.certificates.length ? `<h2 class="section-title">Certificados 🎓</h2>
      <div class="stack">${p.certificates.map((c) => `<div class="card row">📜 <b>${esc(c.title)}</b> ${c.verified ? '✅' : ''}</div>`).join('')}</div>` : ''}

    ${ratings.length ? `<h2 class="section-title">Opiniones 💬</h2>
      <div class="stack">${ratings.slice(0, 5).map((r) => `
        <div class="card">
          <div class="row between"><b>${esc(r.rater_name)}</b><span class="stars">${'★'.repeat(r.stars)}</span></div>
          <p class="muted small">${esc(r.comment || '')}</p>
        </div>`).join('')}</div>` : ''}

    <div class="stack" style="margin-top:22px">
      <button class="btn" id="hire">Contratar a ${esc(p.name.split(' ')[0])} 🤝</button>
      <div class="grid-2">
        <button class="btn secondary" id="chat">💬 Chatear</button>
        <button class="btn secondary" id="fav">💛 Favorito</button>
      </div>
    </div>
  `);

  $('#fav').onclick = async () => {
    try { await post('/favorites', { provider_id: p.id }); toast('¡Guardado en favoritos! 💛', 'success'); }
    catch (err) { toast(err.message, 'error'); }
  };
  $('#chat').onclick = async () => {
    try {
      const json = await post('/conversations', { user_id: p.user_id });
      go('#/chat/' + json.data.conversation_id);
    } catch (err) { toast(err.message, 'error'); }
  };
  $('#hire').onclick = () => openHireSheet(p);
}

function openHireSheet(p) {
  if (store.role !== 'client') { toast('Ingresá como cliente para contratar 🙋'); return; }
  const cats = p.categories;
  const node = sheet(`
    <h2>Contratar a ${esc(p.name.split(' ')[0])} 🤝</h2>
    <form id="hireForm" class="stack" style="margin-top:16px">
      <div class="field">
        <label>¿Para qué servicio?</label>
        <select class="input" id="cat">${cats.map((c) => `<option value="${c.id}">${esc(c.icon)} ${esc(c.name)}</option>`).join('')}</select>
      </div>
      <div class="grid-2">
        <div class="field">
          <label>¿Por cuánto tiempo?</label>
          <select class="input" id="unit">
            <option value="hour">Por hora</option>
            <option value="day">Por día</option>
            <option value="week">Por semana</option>
            <option value="month">Por mes</option>
          </select>
        </div>
        <div class="field">
          <label>Cantidad</label>
          <input class="input" id="qty" type="number" min="1" max="365" value="1" inputmode="numeric">
        </div>
      </div>
      <div class="field">
        <label>¿Cuándo empieza?</label>
        <input class="input" id="start" type="datetime-local" required>
      </div>
      <div class="field">
        <label>¿Dónde? <span class="muted small">(opcional)</span></label>
        <input class="input" id="addr" placeholder="Tu dirección">
      </div>
      <div class="field">
        <label>Contale qué necesitás <span class="muted small">(opcional)</span></label>
        <textarea class="input" id="desc" placeholder="Ej: pasear a mi perra Luna 2 veces por día 🐕"></textarea>
      </div>
      <div class="card" style="background:var(--surface-soft);box-shadow:none">
        <div class="row between"><b>Total estimado</b><b class="price" id="total">${money(p.rate_hour)}</b></div>
        <p class="small muted">Pagás seguro dentro de la app. El prestador cobra cuando el trabajo termina bien 🤗</p>
      </div>
      <button class="btn" type="submit">Enviar solicitud ✨</button>
    </form>
  `);

  const rateOf = (unit) => unit === 'hour' ? p.rate_hour : unit === 'day' ? p.rate_day : unit === 'week' ? p.rate_day * 5 : p.rate_day * 22;
  const recalc = () => {
    const unit = node.querySelector('#unit').value;
    const qty = Math.max(1, +node.querySelector('#qty').value || 1);
    node.querySelector('#total').textContent = money(rateOf(unit) * qty);
  };
  node.querySelector('#unit').onchange = recalc;
  node.querySelector('#qty').oninput = recalc;

  const startInput = node.querySelector('#start');
  startInput.value = new Date(Date.now() + 3600e3).toISOString().slice(0, 16);

  node.querySelector('#hireForm').onsubmit = async (e) => {
    e.preventDefault();
    try {
      const json = await post('/bookings', {
        provider_id: p.id,
        category_id: +node.querySelector('#cat').value,
        unit: node.querySelector('#unit').value,
        quantity: +node.querySelector('#qty').value,
        start_at: startInput.value.replace('T', ' ') + ':00',
        address: node.querySelector('#addr').value.trim() || undefined,
        description: node.querySelector('#desc').value.trim() || undefined,
      });
      closeSheets();
      toast('¡Solicitud enviada! 🎉', 'success');
      go('#/trabajos/' + json.data.id);
    } catch (err) {
      toast(err.message, 'error');
    }
  };
}
