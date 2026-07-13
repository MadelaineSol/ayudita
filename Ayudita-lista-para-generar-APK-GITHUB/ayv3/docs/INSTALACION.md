# Manual de instalación

## Requisitos

- PHP **8.3+** con extensiones: `pdo_mysql`, `json`, `fileinfo`, `mbstring`, `openssl`
- MySQL **8.0+**
- Apache 2.4 (mod_rewrite) o Nginx
- Node.js 18+ (solo para compilar la app móvil)
- Android Studio (APK/AAB) y/o Xcode en macOS (iOS)

## 1. Base de datos

```bash
mysql -u root -p < database/migrations/001_core.sql
mysql -u root -p < database/migrations/002_marketplace.sql
mysql -u root -p < database/migrations/003_payments_chat.sql
# Datos iniciales (categorías, configuración, usuarios demo):
mysql -u root -p ayudita < database/seeds/001_seed.sql
```

Crear un usuario MySQL dedicado con permisos mínimos:

```sql
CREATE USER 'ayudita_user'@'localhost' IDENTIFIED BY 'CAMBIAR-PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON ayudita.* TO 'ayudita_user'@'localhost';
FLUSH PRIVILEGES;
```

## 2. Backend

```bash
cd backend
cp .env.example .env
php -r "echo bin2hex(random_bytes(48));"   # pegar el resultado en JWT_SECRET
```

Completar `.env` (DB, CORS_ORIGINS con los dominios reales).

- **Desarrollo**: `php -S localhost:8080 -t public`
- **Apache**: DocumentRoot → `backend/public` (el `.htaccess` ya está incluido)
- **Nginx**: ver `docs/DESPLIEGUE.md`

Verificar: `curl http://localhost:8080/api/v1/categories` debe devolver JSON.

Crear el administrador real y borrar los usuarios demo:

```bash
php bin/create-admin.php "Tu Nombre" tu@email.com
mysql -u root -p ayudita -e "DELETE FROM users WHERE email LIKE '%demo.app' OR email='admin@ayudita.app';"
```

## 3. Frontend web y panel admin

Son estáticos: servirlos con cualquier servidor (Apache/Nginx/CDN).
Definir la URL del API antes de cargar los scripts, agregando en el
`<head>` de `frontend/index.html` y `admin/index.html`:

```html
<script>window.AYUDITA_API_URL = 'https://api.tudominio.com';</script>
```

## 4. App móvil (Capacitor)

```bash
cd mobile
npm install
AYUDITA_API_URL=https://api.tudominio.com npm run sync:www
npx cap add android      # y/o: npx cap add ios
npm run android:open     # abre Android Studio para compilar/firmar
npm run ios:open         # abre Xcode (requiere macOS)
```

- **Android**: Android Studio → Build → Generate Signed Bundle (AAB) → Google Play Console.
- **iOS**: Xcode → firma con tu Apple Developer Team → Product → Archive → App Store Connect.
- **Push**: configurar Firebase (google-services.json) y APNs; el frontend ya registra el token (`registerPush`).

## 5. Login social (opcional)

Los botones de Google/Apple/Teléfono están en la UI. Para activarlos:
crear credenciales OAuth (Google Cloud / Apple Developer), validar el
`id_token` en un nuevo endpoint `POST /auth/social` y crear el usuario con
`auth_provider` correspondiente. La tabla `users` ya lo soporta.
