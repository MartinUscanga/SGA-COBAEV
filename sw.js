// [sw.js] - Versión 1.1
const SW_VERSION = 'v1.1';
console.log('[SW] Versión:', SW_VERSION);

importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: "AIzaSyBirHLalVvjvEiSVxCPFmSEJChNCfTDjuY",
    projectId: "sga-cobaev",
    messagingSenderId: "263139601974",
    appId: "1:263139601974:web:5fba5cdd72c3b084eff297"
});

const messaging = firebase.messaging();

// Activar inmediatamente la nueva versión del SW
self.addEventListener('install', (event) => {
    console.log('[SW] Instalando nueva versión...');
    self.skipWaiting(); // Salta la espera y activa inmediatamente
});

self.addEventListener('activate', (event) => {
    console.log('[SW] Activado. Tomando control de las páginas...');
    event.waitUntil(
        clients.claim() // Toma control de todas las páginas inmediatamente
    );
});

// Manejador de notificaciones en segundo plano
messaging.onBackgroundMessage((payload) => {
    console.log('[sw.js] Mensaje recibido en segundo plano:', payload);
    
    // Extraer información del payload
    const title = payload.notification?.title || "Alerta COBAEV";
    const body = payload.notification?.body || "Registro de acceso";

    // Mostrar notificación con tag único
    self.registration.showNotification(title, {
        body: body,
        icon: '/logo.png',
        tag: 'cobaev-' + Date.now(), // TAG ÚNICO para no sobreescribir
        renotify: true,
        requireInteraction: true,
        vibrate: [200, 100, 200]
    });
});
