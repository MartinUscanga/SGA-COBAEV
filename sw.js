// [sw.js] - Versión 1.3
const SW_VERSION = 'v1.3';
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
    console.log('[SW] Instalando versión:', SW_VERSION);
    // Limpiar cachés antiguas para evitar conflictos
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    console.log('[SW] Eliminando cache antigua:', cacheName);
                    return caches.delete(cacheName);
                })
            );
        }).then(() => {
            console.log('[SW] Cachés limpiadas. Activando inmediatamente...');
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', (event) => {
    console.log('[SW] Activado versión:', SW_VERSION);
    event.waitUntil(
        clients.claim() // Toma control de todas las páginas inmediatamente
    );
});

// Manejador de notificaciones en segundo plano
messaging.onBackgroundMessage((payload) => {
    console.log('[sw.js] Mensaje recibido:', payload);
    
    // Con data-only, los datos vienen en payload.data
    const title = payload.data?.title || payload.notification?.title || "Alerta COBAEV";
    const body = payload.data?.body || payload.notification?.body || "Registro de acceso";
    const icon = payload.data?.icon || '/logo.png';
    const clickAction = payload.data?.click_action || '';

    self.registration.showNotification(title, {
        body: body,
        icon: icon,
        tag: 'cobaev-' + (payload.data?.tipo || 'bg'),
        renotify: true,
        requireInteraction: true,
        vibrate: [200, 100, 200],
        data: {
            click_action: clickAction,
            id_aviso: payload.data?.id_aviso || '',
            tipo: payload.data?.tipo || ''
        }
    });
});

// Manejador de click en notificaciones
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification click:', event.notification.data);
    event.notification.close();

    const urlToOpen = event.notification.data?.click_action || '/padres.php';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            // Buscar si ya hay una ventana abierta con esa URL
            for (const client of clientList) {
                if (client.url.includes(urlToOpen) && 'focus' in client) {
                    return client.focus();
                }
            }
            // Si no hay ventana abierta, abrir una nueva
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});
