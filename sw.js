// ========================================
// SERVICE WORKER - Sistema COBAEV
// Manejo de notificaciones push con Firebase Cloud Messaging
// ========================================

// Importar Firebase en modo compatibilidad para Service Workers
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js');

// Inicializar Firebase
firebase.initializeApp({
    apiKey: "AIzaSyBirHLalVvjvEiSVxCPFmSEJChNCfTDjuY",
    projectId: "sga-cobaev",
    messagingSenderId: "263139601974",
    appId: "1:263139601974:web:5fba5cdd72c3b084eff297"
});

const messaging = firebase.messaging();

// ========================================
// NOTIFICACIONES EN SEGUNDO PLANO
// ========================================
messaging.onBackgroundMessage((payload) => {
    console.log('[Service Worker] 📬 Mensaje recibido en segundo plano:', payload);
    console.log('[Service Worker] 🔍 Payload completo:', JSON.stringify(payload, null, 2));
    
    // Extraer información del payload
    const notificationTitle = payload.notification?.title || 'Alerta COBAEV';
    const notificationBody = payload.notification?.body || 'Nuevo registro de acceso';
    const notificationIcon = payload.notification?.icon || '/logo.png';
    const notificationImage = payload.notification?.image || null;
    
    // Datos adicionales
    const notificationData = {
        ...payload.data,
        timestamp: Date.now(),
        url: payload.data?.url || '/padres.php'
    };

    // Opciones de la notificación
    const notificationOptions = {
        body: notificationBody,
        icon: notificationIcon,
        badge: '/logo.png',
        image: notificationImage,
        tag: 'cobaev-' + Date.now(), // 🔥 IMPORTANTE: Tag único para cada notificación
        renotify: true,
        requireInteraction: true,
        vibrate: [300, 100, 300, 100, 300], // Vibración más larga
        silent: false,
        data: notificationData
    };

    console.log('[Service Worker] 🔔 Mostrando notificación:', notificationTitle);

    // 🔥 SOLUCIÓN: Mostrar notificación de forma síncrona
    return self.registration.showNotification(notificationTitle, notificationOptions)
        .then(() => {
            console.log('[Service Worker] ✅ Notificación mostrada exitosamente');
        })
        .catch((error) => {
            console.error('[Service Worker] ❌ Error al mostrar notificación:', error);
        });
});

// ========================================
// FALLBACK: Evento PUSH (por si FCM falla)
// ========================================
self.addEventListener('push', (event) => {
    console.log('[Service Worker] 📨 Evento PUSH recibido');
    
    if (event.data) {
        try {
            const data = event.data.json();
            console.log('[Service Worker] Datos del push:', data);
            
            const title = data.notification?.title || 'Notificación COBAEV';
            const options = {
                body: data.notification?.body || 'Nueva actualización',
                icon: '/logo.png',
                badge: '/logo.png',
                tag: 'cobaev-push-' + Date.now(),
                renotify: true,
                requireInteraction: true,
                vibrate: [300, 100, 300, 100, 300],
                data: data.data || {}
            };
            
            event.waitUntil(
                self.registration.showNotification(title, options)
            );
        } catch (error) {
            console.error('[Service Worker] Error al parsear push:', error);
            
            // Notificación genérica si falla el parsing
            event.waitUntil(
                self.registration.showNotification('Nueva Notificación', {
                    body: 'Tienes una nueva actualización del sistema COBAEV',
                    icon: '/logo.png',
                    tag: 'cobaev-fallback-' + Date.now()
                })
            );
        }
    }
});

// ========================================
// CLICK EN NOTIFICACIÓN
// ========================================
self.addEventListener('notificationclick', (event) => {
    console.log('[Service Worker] 👆 Click en notificación:', event.action);
    
    event.notification.close(); // Cerrar la notificación

    // Manejar acciones
    if (event.action === 'cerrar') {
        // Solo cerrar
        return;
    }

    // Acción "ver" o click general en la notificación
    const urlToOpen = event.notification.data?.url || '/padres.php';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Si ya hay una ventana abierta, enfocarse en ella
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

// ========================================
// INSTALACIÓN DEL SERVICE WORKER
// ========================================
self.addEventListener('install', (event) => {
    console.log('[Service Worker] ⚙️ Instalando...');
    
    // Cachear recursos básicos (opcional)
    event.waitUntil(
        caches.open('cobaev-cache-v1').then((cache) => {
            return cache.addAll([
                '/padres.php',
                '/login_padres.php',
                '/app.js',
                '/icono-cobaev.png',
                '/badge-cobaev.png'
            ]).catch((err) => {
                console.warn('[Service Worker] ⚠️ Error al cachear recursos:', err);
            });
        })
    );
    
    // Activar inmediatamente
    self.skipWaiting();
});

// ========================================
// ACTIVACIÓN DEL SERVICE WORKER
// ========================================
self.addEventListener('activate', (event) => {
    console.log('[Service Worker] ✅ Activado');
    
    // Limpiar caches antiguas
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== 'cobaev-cache-v1') {
                        console.log('[Service Worker] 🗑️ Eliminando cache antigua:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    
    // Tomar control de todas las páginas inmediatamente
    return self.clients.claim();
});

// ========================================
// FETCH - Estrategia de red primero
// ========================================
self.addEventListener('fetch', (event) => {
    // Solo interceptar solicitudes GET
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then((response) => {
                // Clonar la respuesta porque solo se puede usar una vez
                const responseClone = response.clone();
                
                // Guardar en cache si es exitosa
                if (response.status === 200) {
                    caches.open('cobaev-cache-v1').then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                
                return response;
            })
            .catch(() => {
                // Si falla la red, intentar devolver desde cache
                return caches.match(event.request);
            })
    );
});

// ========================================
// CERRAR NOTIFICACIÓN AUTOMÁTICAMENTE
// ========================================
self.addEventListener('notificationclose', (event) => {
    console.log('[Service Worker] 🔕 Notificación cerrada:', event.notification.tag);
    
    // Opcional: enviar estadística al servidor
    // fetch('/api/notificacion-cerrada', { method: 'POST', body: JSON.stringify({ tag: event.notification.tag }) });
});

console.log('[Service Worker] 🚀 Service Worker cargado correctamente');
