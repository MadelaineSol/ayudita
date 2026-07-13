# Manual de despliegue (producción)

## Topología recomendada

```
Usuarios ──► CDN/Proxy (HTTPS, HTTP/2) ──► Nginx
                                            ├── api.dominio.com  → backend/public (PHP-FPM 8.3)
                                            ├── app.dominio.com  → frontend/ (estático)
                                            └── admin.dominio.com → admin/ (estático, IP allowlist opcional)
MySQL 8 en red privada. Backups diarios.
```

## Nginx (API)

```nginx
server {
    listen 443 ssl http2;
    server_name api.dominio.com;

    root /var/www/ayudita/backend/public;
    index index.php;

    client_max_body_size 6M;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Archivos subidos (servidos desde storage con alias controlado)
    location /uploads/ {
        alias /var/www/ayudita/backend/storage/uploads/;
        add_header X-Content-Type-Options nosniff;
    }

    location ~ /\. { deny all; }
}
```

## Checklist de producción

- [ ] `APP_DEBUG=false` en `.env`
- [ ] `JWT_SECRET` de 96 caracteres aleatorios, distinto por entorno
- [ ] `CORS_ORIGINS` sin `*`: solo dominios reales + `capacitor://localhost`
- [ ] HTTPS obligatorio (Let's Encrypt / certificado propio), HSTS ya lo envía la API
- [ ] Usuario MySQL con permisos mínimos (sin DDL en producción)
- [ ] Usuarios demo eliminados; admin creado con `bin/create-admin.php`
- [ ] Backups automáticos de MySQL (`mysqldump` diario + retención 30 días)
- [ ] Logs: rotar `backend/storage/logs/` (logrotate)
- [ ] `php.ini`: `expose_php=Off`, `upload_max_filesize=6M`, `post_max_size=8M`
- [ ] OPcache habilitado
- [ ] Monitoreo de disponibilidad sobre `GET /api/v1/categories`

## Actualizaciones

```bash
cd /var/www/ayudita
git pull origin main
# Aplicar nuevas migraciones si las hay:
mysql -u root -p ayudita < database/migrations/NNN_*.sql
sudo systemctl reload php8.3-fpm
```

La app móvil solo necesita recompilarse cuando cambia `frontend/` y se desea
actualizar los stores; los cambios de backend son transparentes.
