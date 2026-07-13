# Generar la APK de Ayudita en Windows

El proyecto Android ya está creado dentro de `mobile/android`. No tenés que ejecutar `npx cap add android`.

## Requisitos

1. Node.js LTS.
2. Android Studio con Android SDK Platform 35 instalado.
3. JDK 17, preferentemente el que viene incluido con Android Studio.
4. Backend de Ayudita funcionando en una URL accesible por la app.

## La URL de la API

Antes de compilar, abrí:

```text
mobile/app-config.json
```

Usá una de estas opciones:

```text
Emulador Android: http://10.0.2.2:8080
Celular físico:   http://IP-DE-TU-PC:8080
Producción:       https://api.tudominio.com
```

No agregues `/api/v1`: la app lo agrega automáticamente.

Para usar un celular físico y un backend local:

- La PC y el teléfono deben estar en la misma red Wi-Fi.
- El servidor PHP debe escuchar en todas las interfaces, por ejemplo:

```bat
C:\xampp\php\php.exe -S 0.0.0.0:8080 -t public
```

- Permití el puerto 8080 en el Firewall de Windows.

Para una app publicada, el backend debe estar en un servidor con HTTPS.

## Método fácil

Dentro de `mobile`, ejecutá en orden:

1. `01_CONFIGURAR_API.bat`
2. `02_PREPARAR_Y_ABRIR_ANDROID.bat`
3. En Android Studio, esperá a que termine Gradle y presioná **Run**.

## Generar APK automáticamente

Ejecutá:

```text
mobile/03_GENERAR_APK_DEBUG.bat
```

La APK aparecerá en:

```text
mobile/dist/Ayudita-debug.apk
```

Es una APK de prueba. Se puede instalar manualmente en un teléfono Android habilitando la instalación desde fuentes desconocidas.

## Generar archivo para Google Play

Google Play utiliza un Android App Bundle firmado (`.aab`). En Android Studio:

1. Abrí `mobile/android`.
2. Menú **Build**.
3. **Generate Signed Bundle / APK**.
4. Seleccioná **Android App Bundle**.
5. Creá o seleccioná un archivo `.jks`.
6. Elegí `release`.
7. Guardá el `.jks`, el alias y las contraseñas en un lugar seguro.

También podés copiar `mobile/android/keystore.properties.example` como `keystore.properties`, completarlo y ejecutar:

```bat
npm run aab:release
```

El resultado queda en:

```text
mobile/dist/Ayudita-release.aab
```

## Importante

- El pago real todavía no está integrado; el flujo actual es simulado.
- Las notificaciones push requieren configurar Firebase y agregar `google-services.json`.
- Para producción, cambiá la URL de la API a HTTPS y restringí CORS incluyendo `https://localhost` para Android Capacitor.
