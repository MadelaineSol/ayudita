/**
 * Panel del prestador: perfil, tarifas, categorías, fotos,
 * certificados, disponibilidad, ingresos y retiros.
 */
import { render, esc, toast, money, topbar, emptyState, skeletons, sheet, closeSheets, $ } from '../core/ui.js';
import { get, put, post, upload } from '../core/api.js';
import { store } from '../core/store.js';
import { takePhoto } from '../native.js';

export async function providerProfilePage() {
  render(skeletons(4));
  let p, cats;
  try {
    [p, cats] = await Promise.all([
      (await get('/provider/profile')).data,
      store.cache('categories') || (await get('/categories')).data,
    ]);
  } catch (err) { render(topbar('Ups') + emptyState('😕', err.message)); return; }

  const myCatIds = p.categories.map((c) => c.id);

  render(`
    ${topbar('Mi perfil de prestador 🛠️')}
    <div class="card stack">
      <div class="row between"><h2>Estado</h2>
        <label class="chip ${p.available ? 'active' : ''}" id="avail" role="switch" aria-checked="${!!p.available}">
          ${p.available ? '🟢 Disponible' : '⚪ No disponible'}
        </label>
      </div>
      ${p.verified ? '<p class="small">✅ Perfil verificado por Ayudita</p>' : '<p class="small muted">⏳ Verificación pendiente: un admin revisará tu perfil</p>'}
    </div>

    <h2 class="section-title">Sobre mí ✏️</h2>
    <form id="f" class="card stack">
      <div class="field"><label>Contá quién sos y qué hacés</label>
        <textarea class="input" id="bio" placeholder="Ej: Soy electricista matriculado hace 10 años...">${esc(p.bio || '')}</textarea>
      </div>
      <div class="grid-2">
        <div class="field"><label>Años de experiencia</label>
          <input class="input" id="exp" type="number" min="0" max="80" value="${p.experience_years || 0}" inputmode="numeric"></div>
        <div class="field"><label>Radio de trabajo (km)</label>
          <input class="input" id="radius" type="number" min="1" max="500" value="${p.radius_km || 10}" inputmode="numeric"></div>
      </div>
      <div class="grid-2">
        <div class="field"><label>Tarifa por hora ($)</label>
          <input class="input" id="rh" type="number" min="0" value="${+p.rate_hour || 0}" inputmode="numeric"></div>
        <div class="field"><label>Tarifa por día ($)</label>
          <input class="input" id="rd" type="number" min="0" value="${+p.rate_day || 0}" inputmode="numeric"></div>
      </div>
      <div class="field"><label>Mis servicios</label>
        <div class="chip-row" id="catChips">
          ${cats.map((c) => `<button type="button" class="chip ${myCatIds.includes(c.id) ? 'active' : ''}" data-id="${c.id}">${esc(c.icon)} ${esc(c.name)}</button>`).join('')}
        </div>
      </div>
      <button class="btn" type="submit">Guardar cambios 💾</button>
    </form>

    <h2 class="section-title">Mis fotos 📸</h2>
    <div class="photo-strip" id="photos">
      ${p.photos.map((f) => `<img src="${esc(f.url)}" alt="Foto de trabajo">`).join('')}
    </div>
    <button class="btn secondary" id="addPhoto">📷 Agregar foto de mis trabajos</button>

    <h2 class="section-title">Certificados 🎓</h2>
    <div class="stack" id="certs">
      ${p.certificates.map((c) => `<div class="card row" style="box-shadow:var(--shadow-tiny)">📜 <b>${esc(c.title)}</b> ${c.verified ? '✅' : '<span class="small muted">(en revisión)</span>'}</div>`).join('') || '<p class="muted small">Sumá certificados para generar más confianza ✨</p>'}
    </div>
    <button class="btn secondary" id="addCert" style="margin-top:10px">➕ Agregar certificado</button>

    <h2 class="section-title">Disponibilidad 📅</h2>
    <button class="btn secondary" id="editAvail">🗓️ Editar mis horarios</button>
  `);

  $('#avail').onclick = async () => {
    try {
      await put('/provider/profile', { available: !p.available });
      toast(!p.available ? '¡Ahora estás disponible! 🟢' : 'Pausaste tu disponibilidad', 'success');
      providerProfilePage();
    } catch (err) { toast(err.message, 'error'); }
  };

  $('#catChips').querySelectorAll('.chip').forEach((chip) => {
    chip.onclick = () => chip.classList.toggle('active');
  });

  $('#f').onsubmit = async (e) => {
    e.preventDefault();
    try {
      const ids = [...$('#catChips').querySelectorAll('.chip.active')].map((c) => +c.dataset.id);
      await put('/provider/profile', {
        bio: $('#bio').value.trim(),
        experience_years: +$('#exp').value || 0,
        radius_km: +$('#radius').value || 10,
        rate_hour: +$('#rh').value || 0,
        rate_day: +$('#rd').value || 0,
        category_ids: ids,
      });
      toast('Perfil guardado ✅', 'success');
    } catch (err) { toast(err.message, 'error'); }
  };

  $('#addPhoto').onclick = async () => {
    try {
      const file = await takePhoto();
      if (!file) return;
      const fd = new FormData();
      fd.append('photo', file);
      await upload('/provider/photos', fd);
      toast('¡Foto agregada! 📸', 'success');
      providerProfilePage();
    } catch (err) { toast(err.message, 'error'); }
  };

  $('#addCert').onclick = () => {
    const node = sheet(`
      <h2>Agregar certificado 🎓</h2>
      <form id="cf" class="stack" style="margin-top:14px">
        <div class="field"><label>Título</label>
          <input class="input" id="title" required minlength="3" placeholder="Ej: Matrícula de electricista N° 1234"></div>
        <button class="btn" type="submit">Agregar</button>
      </form>
    `);
    node.querySelector('#cf').onsubmit = async (e) => {
      e.preventDefault();
      try {
        await post('/provider/certificates', { title: node.querySelector('#title').value.trim() });
        closeSheets();
        toast('Certificado agregado 🎓', 'success');
        providerProfilePage();
      } catch (err) { toast(err.message, 'error'); }
    };
  };

  $('#editAvail').onclick = () => openAvailabilitySheet(p);
}

function openAvailabilitySheet(p) {
  const days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
  const current = Object.fromEntries(p.availability.map((s) => [s.weekday, s]));
  const node = sheet(`
    <h2>Mis horarios 🗓️</h2>
    <p class="muted small">Marcá los días y horarios en los que trabajás.</p>
    <div class="stack" style="margin-top:14px">
      ${days.map((d, i) => `
        <div class="row" data-day="${i}">
          <label class="chip ${current[i] ? 'active' : ''}" style="min-width:110px">${d}</label>
          <input class="input" type="time" value="${current[i]?.from_time?.slice(0, 5) || '09:00'}" style="min-height:46px">
          <input class="input" type="time" value="${current[i]?.to_time?.slice(0, 5) || '18:00'}" style="min-height:46px">
        </div>`).join('')}
      <button class="btn" id="saveAvail">Guardar horarios</button>
    </div>
  `);
  node.querySelectorAll('.chip').forEach((c) => { c.onclick = () => c.classList.toggle('active'); });
  node.querySelector('#saveAvail').onclick = async () => {
    const slots = [...node.querySelectorAll('[data-day]')]
      .filter((row) => row.querySelector('.chip').classList.contains('active'))
      .map((row) => {
        const [from, to] = row.querySelectorAll('input');
        return { weekday: +row.dataset.day, from_time: from.value + ':00', to_time: to.value + ':00' };
      });
    try {
      await put('/provider/availability', { slots });
      closeSheets();
      toast('Horarios guardados 🗓️', 'success');
    } catch (err) { toast(err.message, 'error'); }
  };
}

export async function earningsPage() {
  render(`${topbar('Mis ingresos 💰')}<div id="c">${skeletons(3)}</div>`);
  let e;
  try { e = (await get('/provider/earnings')).data; }
  catch (err) { $('#c').innerHTML = emptyState('😕', err.message); return; }

  $('#c').innerHTML = `
    <div class="grid-2">
      <div class="card stat"><div class="num">${money(e.balance)}</div><div class="lbl">Disponible para retirar</div></div>
      <div class="card stat"><div class="num">${money(e.pending_release)}</div><div class="lbl">En camino (por liberar)</div></div>
    </div>
    <div class="card stat" style="margin-top:14px"><div class="num">${money(e.total_released)}</div><div class="lbl">Total ganado en Ayudita 🎉</div></div>
    <button class="btn mint" id="withdraw" style="margin-top:18px" ${e.balance <= 0 ? 'disabled' : ''}>🏦 Retirar mi dinero</button>
    <h2 class="section-title">Mis retiros</h2>
    <div class="stack">
      ${e.withdrawals.map((w) => `
        <div class="card row between">
          <div><b>${money(w.amount)}</b><div class="small muted">${esc(w.created_at)}</div></div>
          <span class="badge ${w.status === 'paid' ? 'completed' : w.status === 'rejected' ? 'cancelled' : 'pending'}">
            ${{ requested: 'Solicitado', approved: 'Aprobado', paid: 'Pagado', rejected: 'Rechazado' }[w.status]}
          </span>
        </div>`).join('') || '<p class="muted center">Todavía no pediste retiros.</p>'}
    </div>
  `;

  $('#withdraw')?.addEventListener('click', () => {
    const node = sheet(`
      <h2>Retirar dinero 🏦</h2>
      <p class="muted small">El equipo de Ayudita procesa tu retiro y te transfiere a tu cuenta.</p>
      <form id="wf" class="stack" style="margin-top:14px">
        <div class="field"><label>Monto a retirar</label>
          <input class="input" id="amount" type="number" min="1" max="${e.balance}" value="${e.balance}" inputmode="numeric"></div>
        <div class="field"><label>CBU / CVU / Alias</label>
          <input class="input" id="cbu" required placeholder="tu.alias.mp"></div>
        <button class="btn" type="submit">Solicitar retiro</button>
      </form>
    `);
    node.querySelector('#wf').onsubmit = async (ev) => {
      ev.preventDefault();
      try {
        await post('/provider/withdrawals', {
          amount: +node.querySelector('#amount').value,
          bank_info: { cbu: node.querySelector('#cbu').value.trim() },
        });
        closeSheets();
        toast('¡Retiro solicitado! Te avisamos cuando esté 💸', 'success');
        earningsPage();
      } catch (err) { toast(err.message, 'error'); }
    };
  });
}
