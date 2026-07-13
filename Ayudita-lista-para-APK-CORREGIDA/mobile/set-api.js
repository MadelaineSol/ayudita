const fs = require('fs');
const path = require('path');

const url = process.argv[2];
if (!url || !/^https?:\/\//i.test(url)) {
  console.error('Uso: node set-api.js http://10.0.2.2:8080');
  console.error('Producción: node set-api.js https://api.tudominio.com');
  process.exit(1);
}

const file = path.join(__dirname, 'app-config.json');
let config = {};
if (fs.existsSync(file)) {
  try { config = JSON.parse(fs.readFileSync(file, 'utf8')); } catch { config = {}; }
}
config.apiUrl = url.replace(/\/+$/, '');
config.environment = /^https:\/\//i.test(config.apiUrl) ? 'production' : 'development';
fs.writeFileSync(file, JSON.stringify(config, null, 2) + '\n');
console.log('✅ URL de API guardada:', config.apiUrl);
console.log('Ahora ejecutá: npm run prepare:android');
