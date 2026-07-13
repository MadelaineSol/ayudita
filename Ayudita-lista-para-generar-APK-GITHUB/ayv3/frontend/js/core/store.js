/**
 * Estado global mínimo persistido en localStorage.
 * Guarda sesión (tokens + usuario) y caché liviana de catálogo.
 */
const KEY = 'ayudita';

function read() {
  try { return JSON.parse(localStorage.getItem(KEY)) || {}; }
  catch { return {}; }
}

function write(state) {
  localStorage.setItem(KEY, JSON.stringify(state));
}

export const store = {
  get session() { return read().session || null; },

  setSession(session) {
    const s = read();
    s.session = session;
    write(s);
  },

  updateUser(user) {
    const s = read();
    if (s.session) { s.session.user = user; write(s); }
  },

  clearSession() {
    const s = read();
    delete s.session;
    write(s);
  },

  get user() { return this.session?.user || null; },
  get role() { return this.user?.role || null; },
  get isLoggedIn() { return !!this.session?.access_token; },

  /** Caché simple con expiración (para modo offline parcial). */
  cache(key, value = undefined, ttlMs = 10 * 60 * 1000) {
    const s = read();
    s.cache = s.cache || {};
    if (value !== undefined) {
      s.cache[key] = { value, exp: Date.now() + ttlMs };
      write(s);
      return value;
    }
    const hit = s.cache[key];
    return hit && hit.exp > Date.now() ? hit.value : null;
  },

  get onboarded() { return !!read().onboarded; },
  setOnboarded() { const s = read(); s.onboarded = true; write(s); },
};
