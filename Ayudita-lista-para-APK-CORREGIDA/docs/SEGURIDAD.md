# Seguridad implementada 🔐

## Autenticación y sesiones
- **Argon2id** (memoria 64 MB, 4 iteraciones) para contraseñas.
- **JWT HS256** de corta vida (15 min) firmado con secreto de entorno.
- **Refresh tokens rotativos** (30 días): aleatorios (80 hex), guardados
  **hasheados** (SHA-256), revocables por sesión o globalmente; cada uso
  emite uno nuevo y revoca el anterior.
- **Bloqueo por intentos fallidos**: 5 errores → cuenta bloqueada 15 min (HTTP 423).
- Reset de contraseña: token de un solo uso (30 min, hasheado) que
  **revoca todas las sesiones** al usarse; la respuesta no revela si el email existe.
- Estructura preparada para **2FA** (`users.two_factor_enabled`) y
  verificación de email (`email_verifications`).

## API
- **Prepared statements en el 100 % de las consultas** (PDO sin emulación) → SQL injection.
- **Validación backend declarativa** con whitelist de campos: lo no declarado se descarta ("no confiar nunca en el frontend").
- Saneo de texto (`Text::clean`) contra XSS almacenado + `esc()` en cada render del frontend.
- **Rate limiting** por IP+ruta en endpoints sensibles (login 10/5min, registro 5/5min, chat 60/min…).
- **CORS** restringido a orígenes configurados.
- **Headers**: CSP, X-Content-Type-Options, X-Frame-Options DENY, HSTS, Referrer-Policy, Permissions-Policy; `X-Powered-By` eliminado.
- **CSRF**: la API es stateless con Bearer token (sin cookies de sesión), lo que elimina el vector clásico; CORS restringido cubre el resto.
- Errores de producción sin stack trace (`APP_DEBUG=false`); detalle solo en logs.

## Autorización
- Middleware `role:` por ruta (admin / provider / client).
- Comprobación de **propiedad del recurso** en cada controlador (un usuario
  solo ve sus bookings, chats, pagos; el admin todo).
- Máquina de estados de bookings: transiciones inválidas → 409.

## Archivos
- Validación por **MIME real** (finfo), no por extensión; límite 5 MB;
  nombre regenerado aleatorio; guardado fuera del webroot del API.

## Dinero
- Transacciones SQL en pago, liberación y retiro (sin estados a medias).
- El retiro **reserva el saldo** al solicitarse (previene doble retiro).
- Comisión validada 0–100 y congelada por pago.

## Auditoría y logs
- `audit_logs`: login/logout/fallos, cambios de configuración, bloqueos,
  aprobaciones de dinero, con IP y detalles.
- Logs de aplicación diarios en `backend/storage/logs/`.

## Pendiente de configurar en producción
- HTTPS + certificados (HSTS ya se envía).
- Proveedores OAuth (Google/Apple) y SMS.
- Pasarela de pago real (Mercado Pago/Stripe) con webhooks firmados
  (el modelo de datos ya lo soporta: `payments.status='pending'` + `external_ref`).
