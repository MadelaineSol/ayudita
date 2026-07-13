# Arquitectura de Ayudita

## Visión general

Monorepo con 4 aplicaciones y una base de datos:

```
┌────────────────┐  ┌────────────────┐  ┌────────────────┐
│ Frontend móvil │  │  Frontend web  │  │  Panel Admin   │
│  (Capacitor)   │  │  (responsive)  │  │     (SPA)      │
└───────┬────────┘  └───────┬────────┘  └───────┬────────┘
        │      mismo código │                   │
        └──────────┬────────┴───────────────────┘
                   ▼  HTTPS · JSON · JWT
         ┌───────────────────┐
         │  API REST /api/v1 │  PHP 8.3 puro (MVC)
         └─────────┬─────────┘
                   ▼  PDO · prepared statements
         ┌───────────────────┐
         │      MySQL 8      │
         └───────────────────┘
```

`frontend/` se sirve como web responsive **y** se copia a `mobile/www` para
empaquetarse con Capacitor: un solo código, tres plataformas (web, Android, iOS).

## Backend (backend/)

Capas (Clean Architecture, sin framework):

```
public/index.php      Front controller único
src/Core/             App, Router, Request, Response, Database (PDO), Env, Logger
src/Config/routes.php Definición declarativa de rutas + middlewares
src/Middlewares/      Cors, SecurityHeaders, Auth (JWT), Role, Throttle
src/Controllers/      Capa HTTP: valida entrada y delega (+ Admin/)
src/Services/         Lógica de negocio (Auth, Booking, Payment, Notification…)
src/Repositories/     Acceso a datos (Repository Pattern, SQL centralizado)
src/DTO/              Objetos de transferencia tipados
src/Validation/       Validador declarativo (whitelist de campos)
src/Security/         Jwt (HS256), Password (Argon2id), RateLimiter
src/Helpers/          Text (saneo), Paginator
src/Exceptions/       HttpException, ValidationException
```

Principios aplicados: **SOLID** (cada clase una responsabilidad, dependencias
inyectables por constructor), **DRY** (BaseRepository, Validator, helpers),
**KISS/YAGNI** (sin contenedor DI ni ORM innecesarios: PHP puro legible).

### Flujo de una petición

1. `index.php` → autoload PSR-4 + `.env` → `App::run()`
2. Middlewares globales: SecurityHeaders → CORS (responde preflight)
3. Router matchea método + patrón (`/bookings/{id}`) → middlewares de ruta
   (`throttle` → `auth` → `role`)
4. Controller valida entrada (`Validator`) → Service (negocio) → Repository (SQL)
5. `Response::json()` — envolvente uniforme `{success, data, error, meta}`

### Máquina de estados de un trabajo

```
pending ──accept──► accepted ──on_way──► on_way ──start──► in_progress ──complete──► completed
   │                    │                                       │
 reject/cancel        cancel                                 dispute ──► disputed
                                     (cliente puede cancelar hasta completed)
Pago:  unpaid ──pay──► paid ──payout aprobado──► released
```

## Frontend (frontend/)

SPA vanilla ES2023, sin frameworks:

- `js/core/`: router por hash con guardas, cliente API con refresh
  automático de tokens, store en localStorage con caché (offline parcial),
  utilidades UI (toasts, sheets, escape XSS).
- `js/pages/`: una función de render por pantalla.
- `js/native.js`: puente Capacitor (cámara, GPS, compartir, push, biometría)
  con fallbacks web estándar.
- `css/styles.css`: design system con tokens (colores suaves, radios grandes,
  sombras sutiles, microanimaciones, `prefers-reduced-motion`).

## Decisiones clave

| Decisión | Motivo |
|---|---|
| JWT corto (15 min) + refresh token rotativo (30 días, hasheado en DB) | Robo de access token tiene ventana mínima; el refresh es revocable |
| El dinero entra siempre a la plataforma | Modelo escrow: comisión garantizada y protección a ambas partes |
| Payout manual aprobado por admin | Requisito de negocio: control humano del flujo de dinero |
| Chat por polling (3,5 s) | Sin dependencias (WebSockets requeriría otro runtime); suficiente para MVP comercial y reemplazable por SSE/WS detrás del mismo endpoint |
| Haversine en SQL para distancia | Sin servicios externos; índice en (lat,lng) |
