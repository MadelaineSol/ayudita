# Referencia de la API — `/api/v1`

- Solo JSON. Envolvente uniforme:
  `{ "success": true, "data": …, "meta": {…} }` /
  `{ "success": false, "error": { "message", "details" } }`
- Autenticación: `Authorization: Bearer <access_token>` (JWT, 15 min).
  Renovar con `POST /auth/refresh` (refresh token rotativo, 30 días).
- Paginación: `?page=1&per_page=20` → `meta: {page, per_page, total, total_pages}`.
- Códigos: 200/201/204 éxito · 401 sin sesión · 403 sin permiso ·
  404 no existe · 409 conflicto de estado · 422 validación · 423 cuenta
  bloqueada temporalmente · 429 rate limit.

## Autenticación

| Método | Ruta | Body | Notas |
|---|---|---|---|
| POST | `/auth/register` | `name, email, password, role(client\|provider), phone?` | throttle 5/5min |
| POST | `/auth/login` | `email, password` | bloqueo tras 5 intentos fallidos (15 min) |
| POST | `/auth/refresh` | `refresh_token` | rota el refresh token |
| POST | `/auth/logout` 🔒 | `refresh_token` | revoca la sesión |
| GET  | `/auth/me` 🔒 | — | perfil propio |
| POST | `/auth/forgot-password` | `email` | siempre responde 200 (no revela existencia) |
| POST | `/auth/reset-password` | `token, password` | revoca todas las sesiones |

## Catálogo público

| GET `/categories` | categorías activas |
| GET `/banners` | banners activos |
| GET `/providers` | búsqueda con filtros: `category_id, q, min_price, max_price, min_rating, min_experience, available, lat, lng, radius(km), sort(rating\|price_asc\|price_desc\|distance), page` |
| GET `/providers/{id}` | perfil completo (categorías, fotos, certificados, disponibilidad) |
| GET `/providers/{id}/ratings` | opiniones |

## Perfil 🔒

| PUT `/profile` | `name?, phone?, address?, city?, lat?, lng?, avatar_url?` |
| POST `/uploads` | multipart `file` (jpg/png/webp/pdf, máx 5 MB) → `{url}` |

## Contrataciones 🔒

| POST | `/bookings` (rol client) | `provider_id, category_id, unit(hour\|day\|week\|month), quantity, start_at, description?, address?, lat?, lng?` |
| GET | `/bookings?status=` | mis trabajos (según rol) |
| GET | `/bookings/{id}` | detalle + historial + extensiones |
| POST | `/bookings/{id}/accept\|reject\|on_way\|start\|complete` | transiciones del prestador |
| POST | `/bookings/{id}/cancel` | `reason?` (cualquiera de las partes) |
| POST | `/bookings/{id}/extend` (client) | `extra_quantity` — recalcula fin y total |
| POST | `/bookings/{id}/rate` | `stars(1-5), comment?` — bidireccional, 1 vez |
| POST | `/bookings/{id}/dispute` | `reason` |

## Pagos 🔒

| POST | `/bookings/{id}/pay` (client) | `method(card\|transfer\|mercadopago\|wallet), external_ref?` — calcula comisión/impuestos y crea payout pendiente |
| GET | `/payments` | mis pagos |
| GET | `/payments/{id}/receipt` | comprobante estructurado |

## Prestador 🔒 (rol provider)

| GET/PUT | `/provider/profile` | `bio, experience_years, rate_hour, rate_day, radius_km, available, category_ids[]` |
| POST | `/provider/photos` | multipart `photo` |
| POST | `/provider/certificates` | `title` (+ multipart `file` opcional) |
| PUT | `/provider/availability` | `slots: [{weekday 0-6, from_time, to_time}]` |
| GET | `/provider/earnings` | saldo, pendiente de liberación, total ganado, retiros |
| POST | `/provider/withdrawals` | `amount, bank_info?` — reserva el saldo |

## Social 🔒

| GET/POST/DELETE | `/favorites` · `/favorites/{provider_id}` |
| GET/POST | `/conversations` (`user_id, booking_id?`) |
| GET | `/conversations/{id}/messages?after_id=` (polling incremental, marca leído) |
| POST | `/conversations/{id}/messages` | `type: text(body) \| location(lat,lng) \| image/file(multipart)` |
| GET | `/notifications` · POST `/notifications/read` |

## Panel admin 🔒 (rol admin)

| GET `/admin/dashboard` | KPIs |
| GET `/admin/reports/{revenue\|top-categories\|top-providers\|top-clients\|active-users\|heatmap}` |
| GET/PUT/DELETE `/admin/users[/{id}]` | filtrar, bloquear, verificar, soft delete |
| GET `/admin/payments` · GET `/admin/payouts` · POST `/admin/payouts/{id}/approve` |
| GET `/admin/withdrawals` · POST `/admin/withdrawals/{id}/process` (`decision: approved\|rejected, notes?`) |
| CRUD `/admin/categories` · `/admin/banners` |
| GET/PUT `/admin/settings` (comisión, impuestos, retiro mínimo…) |
| GET `/admin/disputes` · POST `/admin/disputes/{id}/resolve` |
| GET `/admin/logs` | auditoría |
