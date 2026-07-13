/**
 * Copia ../frontend a www/ para empaquetarlo con Capacitor.
 * La URL de la API se toma, en este orden, de:
 * 1) AYUDITA_API_URL (variable de entorno)
 * 2) app-config.json
 */
const fs = require('fs');
const path = require('path');

const src = path.join(__dirname, '..', 'frontend');
const dest = path.join(__dirname, 'www');
const appConfigPath = path.join(__dirname, 'app-config.json');

if (!fs.existsSync(src)) {
  console.error('❌ No se encontró la carpeta frontend:', src);
  process.exit(1);
}

let appConfig = {};
if (fs.existsSync(appConfigPath)) {
  try {
    appConfig = JSON.parse(fs.readFileSync(appConfigPath, 'utf8'));
  } catch (error) {
    console.error('❌ app-config.json no contiene JSON válido:', error.message);
    process.exit(1);
  }
}

const rawApiUrl = process.env.AYUDITA_API_URL || appConfig.apiUrl;
if (!rawApiUrl || !/^https?:\/\//i.test(rawApiUrl)) {
  console.error('❌ Configurá una URL válida en mobile/app-config.json. Ejemplo: http://10.0.2.2:8080');
  process.exit(1);
}

const apiUrl = rawApiUrl.replace(/\/+$/, '');

fs.rmSync(dest, { recursive: true, force: true });
fs.cpSync(src, dest, { recursive: true });

const configJs = path.join(dest, 'js', 'core', 'config.js');
let content = fs.readFileSync(configJs, 'utf8');
content = content.replace(
  /window\.AYUDITA_API_URL\s*\|\|\s*['"][^'"]+['"]/, 
  `window.AYUDITA_API_URL || '${apiUrl}'`
);
fs.writeFileSync(configJs, content);

const buildInfo = {
  apiUrl,
  environment: appConfig.environment || 'custom',
  generatedAt: new Date().toISOString(),
};
fs.writeFileSync(path.join(dest, 'build-info.json'), JSON.stringify(buildInfo, null, 2));

// Permitir contenido mixto solamente cuando se usa una API HTTP de desarrollo.
// En producción, la API debe usar HTTPS y esta opción queda desactivada.
const capacitorConfigPath = path.join(__dirname, 'capacitor.config.json');
const capacitorConfig = JSON.parse(fs.readFileSync(capacitorConfigPath, 'utf8'));
capacitorConfig.android = {
  ...(capacitorConfig.android || {}),
  allowMixedContent: apiUrl.startsWith('http://'),
};
fs.writeFileSync(capacitorConfigPath, JSON.stringify(capacitorConfig, null, 2) + '\n');

console.log('✅ Frontend copiado a mobile/www');
console.log('✅ API configurada:', apiUrl);
