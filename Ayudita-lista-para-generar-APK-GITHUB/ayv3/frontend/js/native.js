/**
 * Puente de funciones nativas.
 * En la app móvil usa los plugins de Capacitor (cámara, GPS, compartir,
 * notificaciones push, biometría). En la web usa APIs estándar como fallback.
 */

const Cap = window.Capacitor;
const isNative = !!(Cap && Cap.isNativePlatform && Cap.isNativePlatform());

/** Foto desde cámara o galería. Devuelve un File o null. */
export async function takePhoto() {
  if (isNative && Cap.Plugins?.Camera) {
    const photo = await Cap.Plugins.Camera.getPhoto({
      quality: 80, resultType: 'uri', source: 'PROMPT',
      promptLabelHeader: 'Foto', promptLabelPhoto: 'Elegir de la galería',
      promptLabelPicture: 'Sacar foto',
    });
    const blob = await (await fetch(photo.webPath)).blob();
    return new File([blob], 'foto.jpg', { type: blob.type || 'image/jpeg' });
  }
  return pickFile('image/*', true);
}

export async function pickPhoto() {
  return takePhoto();
}

function pickFile(accept, capture = false) {
  return new Promise((resolve) => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = accept;
    if (capture) input.capture = 'environment';
    input.onchange = () => resolve(input.files[0] || null);
    input.oncancel = () => resolve(null);
    input.click();
  });
}

/** Posición actual { lat, lng }. */
export async function getPosition() {
  if (isNative && Cap.Plugins?.Geolocation) {
    const pos = await Cap.Plugins.Geolocation.getCurrentPosition({ enableHighAccuracy: true, timeout: 10000 });
    return { lat: pos.coords.latitude, lng: pos.coords.longitude };
  }
  return new Promise((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
      reject,
      { enableHighAccuracy: true, timeout: 10000 }
    );
  });
}

/** Compartir la app. */
export async function shareApp() {
  const data = {
    title: 'Ayudita 💛',
    text: 'Encontré gente buena para que te dé una mano: paseadores, electricistas, niñeras y más.',
    url: 'https://ayudita.app',
  };
  try {
    if (isNative && Cap.Plugins?.Share) await Cap.Plugins.Share.share(data);
    else if (navigator.share) await navigator.share(data);
    else await navigator.clipboard.writeText(data.url);
  } catch { /* cancelado por el usuario */ }
}

/** Registro de notificaciones push (solo nativo; requiere FCM/APNs configurado). */
export async function registerPush(onToken) {
  if (!isNative || !Cap.Plugins?.PushNotifications) return;
  const { PushNotifications } = Cap.Plugins;
  const perm = await PushNotifications.requestPermissions();
  if (perm.receive !== 'granted') return;
  PushNotifications.addListener('registration', (token) => onToken?.(token.value));
  await PushNotifications.register();
}

/** Desbloqueo biométrico opcional (requiere plugin nativo de biometría). */
export async function biometricAvailable() {
  return isNative && !!Cap.Plugins?.BiometricAuth;
}
