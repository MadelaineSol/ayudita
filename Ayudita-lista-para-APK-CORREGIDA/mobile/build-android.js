const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const mode = (process.argv[2] || 'apk').toLowerCase();
if (!['apk', 'aab'].includes(mode)) {
  console.error('Uso: node build-android.js apk | aab');
  process.exit(1);
}

const root = __dirname;
const androidDir = path.join(root, 'android');
const isWindows = process.platform === 'win32';

function run(command, args, cwd = root) {
  const result = spawnSync(command, args, { cwd, stdio: 'inherit', shell: isWindows });
  if (result.status !== 0) process.exit(result.status || 1);
}

function findSdk() {
  const candidates = [
    process.env.ANDROID_HOME,
    process.env.ANDROID_SDK_ROOT,
    isWindows && process.env.LOCALAPPDATA ? path.join(process.env.LOCALAPPDATA, 'Android', 'Sdk') : null,
    process.platform === 'darwin' ? path.join(os.homedir(), 'Library', 'Android', 'sdk') : null,
    process.platform === 'linux' ? path.join(os.homedir(), 'Android', 'Sdk') : null,
  ].filter(Boolean);
  return candidates.find((candidate) => fs.existsSync(candidate));
}

if (!fs.existsSync(androidDir)) {
  console.error('❌ Falta mobile/android. Ejecutá npm run android:add');
  process.exit(1);
}

const sdk = findSdk();
if (!sdk) {
  console.error('❌ No se encontró el Android SDK. Instalá Android Studio y abrilo al menos una vez.');
  console.error('Ruta habitual en Windows: %LOCALAPPDATA%\\Android\\Sdk');
  process.exit(1);
}

const escapedSdk = sdk.replace(/\\/g, '\\\\').replace(/:/g, '\\:');
fs.writeFileSync(path.join(androidDir, 'local.properties'), `sdk.dir=${escapedSdk}\n`);

if (mode === 'aab') {
  const keyProps = path.join(androidDir, 'keystore.properties');
  if (!fs.existsSync(keyProps)) {
    console.error('❌ Para generar un AAB firmado, copiá android/keystore.properties.example como android/keystore.properties y completalo.');
    process.exit(1);
  }
}

run(isWindows ? 'npm.cmd' : 'npm', ['run', 'prepare:android']);
const gradle = isWindows ? 'gradlew.bat' : './gradlew';
const task = mode === 'apk' ? 'assembleDebug' : 'bundleRelease';
run(gradle, [task], androidDir);

const source = mode === 'apk'
  ? path.join(androidDir, 'app', 'build', 'outputs', 'apk', 'debug', 'app-debug.apk')
  : path.join(androidDir, 'app', 'build', 'outputs', 'bundle', 'release', 'app-release.aab');

if (!fs.existsSync(source)) {
  console.error('❌ La compilación terminó, pero no se encontró el archivo esperado:', source);
  process.exit(1);
}

const dist = path.join(root, 'dist');
fs.mkdirSync(dist, { recursive: true });
const target = path.join(dist, mode === 'apk' ? 'Ayudita-debug.apk' : 'Ayudita-release.aab');
fs.copyFileSync(source, target);
console.log('\n✅ Archivo generado:', target);
