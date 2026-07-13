# Generar la APK en GitHub sin Android Studio

Esta alternativa compila Ayudita en los servidores de GitHub y evita los errores locales `bad_record_mac / Tag mismatch`.

## 1. Subir el proyecto a GitHub

1. Descomprimí este ZIP.
2. Creá un repositorio nuevo en GitHub.
3. Subí **todo el contenido de la carpeta**, incluyendo la carpeta oculta `.github`.
4. Confirmá que en GitHub exista este archivo:

   `.github/workflows/generar-apk.yml`

## 2. Ejecutar la compilación

1. Entrá al repositorio.
2. Abrí la pestaña **Actions**.
3. Elegí **Generar APK Ayudita**.
4. Tocá **Run workflow**.
5. Volvé a tocar el botón verde **Run workflow**.
6. Esperá a que el proceso termine con un tilde verde.

## 3. Descargar la APK

1. Abrí la ejecución terminada.
2. Bajá hasta la sección **Artifacts**.
3. Tocá **Ayudita-debug-apk**.
4. GitHub descargará un ZIP.
5. Dentro estará `app-debug.apk`.

## Importante

- Esta APK es de prueba y se puede instalar manualmente en Android.
- Para Play Store se debe generar y firmar un archivo `.aab` de producción.
- La app está configurada con la URL de API que figure en `mobile/app-config.json`.
- Una URL local como `192.168.x.x` solo funcionará mientras el teléfono y la PC estén en la misma red y el backend esté encendido.
- Para entregar al cliente, reemplazá esa URL por el dominio HTTPS real del backend antes de compilar.
