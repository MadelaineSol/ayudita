# Ayudita 💛

**Plataforma de contratación temporal de servicios** — conecta prestadores
(paseadores de perros, electricistas, niñeras, plomeros…) con personas que
necesitan una mano. Contratás por horas, días, semanas o meses, y podés
extender la contratación cuando quieras.

> Nombre temporal del producto: **Ayudita** (repositorio `com360`).

## Arquitectura

```
Frontend móvil (HTML5 + CSS3 + JS ES2023)
        │
        ▼
   Capacitor  ──►  Android / iOS / PWA
        │
        ▼
     API REST  (/api/v1, solo JSON)
        │
        ▼
     PHP 8.3  (MVC puro, sin frameworks)
        │
        ▼
     MySQL 8

Frontend Web Responsive (mismo código, mismo backend)
Panel Administrador (SPA independiente, mismo backend)
```

## Estructura del repositorio

| Carpeta      | Contenido |
|--------------|-----------|
| `backend/`   | API REST en PHP 8 puro: MVC, Repository Pattern, Service Layer, DTOs, Middlewares, Validación |
| `database/`  | Migraciones SQL y seeds (MySQL 8) |
| `frontend/`  | SPA compartida: web responsive **y** app móvil (mismo código) |
| `admin/`     | Panel de administración (SPA de escritorio) |
| `mobile/`    | Proyecto Capacitor (empaqueta `frontend/` para Android/iOS) |
| `docs/`      | Documentación técnica, manuales y diagramas |

## Inicio rápido (desarrollo)

```bash
# 1. Base de datos
mysql -u root -p < database/migrations/001_core.sql
mysql -u root -p < database/migrations/002_marketplace.sql
mysql -u root -p < database/migrations/003_payments_chat.sql
mysql -u root -p ayudita < database/seeds/001_seed.sql

# 2. Backend
cd backend
cp .env.example .env        # completar credenciales y JWT_SECRET
php -S localhost:8080 -t public

# 3. Frontend web
cd ../frontend
python3 -m http.server 3000   # o cualquier servidor estático

# 4. Panel admin
cd ../admin
python3 -m http.server 3001
```

Usuarios demo (seeds): `admin@ayudita.app / Admin1234!`,
`clara@demo.app / Demo1234!` (cliente), `pablo@demo.app / Demo1234!` (prestador).
**Cambiarlos en producción** (`php backend/bin/create-admin.php`).


## Android listo para compilar

El proyecto nativo Android ya está incluido en `mobile/android`. En Windows, abrí `GENERAR_APK_WINDOWS.md` y ejecutá los archivos `.bat` incluidos en `mobile/`.

> Antes de generar la APK, configurá la URL del backend en `mobile/app-config.json`. Una app instalada no puede usar `localhost` de la PC.

## Flujo de dinero 💸

1. El **cliente paga a la plataforma** (nunca directo al prestador).
2. La plataforma retiene una **comisión configurable** desde el panel admin.
3. El **admin aprueba la liberación** (payout) → el neto se acredita al saldo del prestador.
4. El prestador **solicita un retiro** → el admin lo aprueba y transfiere.

## Documentación

- [Arquitectura](docs/ARQUITECTURA.md)
- [Manual de instalación](docs/INSTALACION.md)
- [Manual de despliegue](docs/DESPLIEGUE.md)
- [Manual del administrador](docs/MANUAL_ADMIN.md)
- [Referencia de la API](docs/API.md)
- [Base de datos y diagrama ER](docs/BASE_DE_DATOS.md)
- [Seguridad](docs/SEGURIDAD.md)
- [Flujo de pantallas](docs/PANTALLAS.md)

## Corrección de descarga Gradle
La versión V2 usa `gradle-8.7-bin.zip`, checksum SHA-256 oficial y timeout ampliado.
Si hubo una descarga dañada, cierre Android Studio y ejecute `mobile/05_REPARAR_GRADLE.bat`.
