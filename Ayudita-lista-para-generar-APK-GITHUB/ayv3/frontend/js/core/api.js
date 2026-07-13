/**
 * Cliente HTTP de la API con:
 *  - Bearer token automático
 *  - Refresh transparente al expirar el access token
 *  - Errores normalizados
 */
import { API_URL } from './config.js';
import { store } from './store.js';

let refreshing = null;

export class ApiError extends Error {
  constructor(message, status, details) {
    super(message);
    this.status = status;
    this.details = details;
  }
}

async function rawRequest(path, { method = 'GET', body, formData, auth = true } = {}) {
  const headers = {};
  if (auth && store.session?.access_token) {
    headers.Authorization = 'Bearer ' + store.session.access_token;
  }
  let payload;
  if (formData) {
    payload = formData;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  const res = await fetch(API_URL + path, { method, headers, body: payload });
  const json = await res.json().catch(() => ({}));

  if (!res.ok) {
    throw new ApiError(json.error?.message || 'Algo salió mal 😕', res.status, json.error?.details);
  }
  return json;
}

async function refreshSession() {
  const session = store.session;
  if (!session?.refresh_token) throw new ApiError('Sesión expirada', 401);
  const json = await rawRequest('/auth/refresh', {
    method: 'POST',
    body: { refresh_token: session.refresh_token },
    auth: false,
  });
  store.setSession(json.data);
}

export async function api(path, options = {}) {
  try {
    return await rawRequest(path, options);
  } catch (err) {
    // Access token vencido: refrescar una sola vez y reintentar.
    if (err.status === 401 && store.session?.refresh_token && !path.startsWith('/auth/')) {
      refreshing = refreshing || refreshSession().finally(() => { refreshing = null; });
      try {
        await refreshing;
      } catch {
        store.clearSession();
        location.hash = '#/login';
        throw err;
      }
      return rawRequest(path, options);
    }
    throw err;
  }
}

export const get  = (path) => api(path);
export const post = (path, body) => api(path, { method: 'POST', body });
export const put  = (path, body) => api(path, { method: 'PUT', body });
export const del  = (path) => api(path, { method: 'DELETE' });
export const upload = (path, formData) => api(path, { method: 'POST', formData });
