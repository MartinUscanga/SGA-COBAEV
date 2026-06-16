// ========================================
// SERVICE WORKER - PWA
// ========================================
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
            .then(reg => console.log('✅ Service Worker registrado correctamente'))
            .catch(err => console.error('❌ Error al registrar Service Worker:', err));
    });
}

// ========================================
// FIREBASE CLOUD MESSAGING (FCM)
// ========================================
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.22.0/firebase-app.js";
import { getMessaging, getToken, onMessage } from "https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging.js";

// Configuración de Firebase
const firebaseConfig = {
    apiKey: "AIzaSyBirHLalVvjvEiSVxCPFmSEJChNCfTDjuY",
    authDomain: "sga-cobaev.firebaseapp.com",
    projectId: "sga-cobaev",
    storageBucket: "sga-cobaev.firebasestorage.app",
    messagingSenderId: "263139601974",
    appId: "1:263139601974:web:5fba5cdd72c3b084eff297"
};

// Inicializar Firebase
const app = initializeApp(firebaseConfig);
const messaging = getMessaging(app);

// VAPID Key para notificaciones push
const VAPID_KEY = 'BJEY0s0K7FhBubLpEYmvdnxm-3Z0PsHwij0ipGlHyQYw7VvL_3knSrUOFhn5OIJXHSIwTQcvogFO_N-oJj-Q6DU';

// ========================================
// FUNCIÓN: Solicitar permisos y registrar token
// ========================================
async function solicitarPermisoYRegistrar() {
    try {
        // 1. Verificar que MATRICULA_USUARIO exista (definida en portal_padres.php)
        if (typeof MATRICULA_USUARIO === 'undefined' || MATRICULA_USUARIO === '') {
            console.error('❌ Error: La matrícula del usuario no está definida');
            return;
        }

        // 2. Solicitar permiso para notificaciones
        const permission = await Notification.requestPermission();
        
        if (permission === 'granted') {
            console.log('✅ Permiso de notificaciones concedido');

            // 3. Esperar a que el Service Worker esté listo
            const registration = await navigator.serviceWorker.ready;

            // 4. Obtener el token de FCM
            const currentToken = await getToken(messaging, { 
                vapidKey: VAPID_KEY,
                serviceWorkerRegistration: registration
            });

            if (currentToken) {
                console.log('📱 Token FCM obtenido:', currentToken.substring(0, 20) + '...');

                // 5. Enviar token al servidor PHP
                const response = await fetch('guardar_token.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        matricula: MATRICULA_USUARIO,
                        token: currentToken 
                    })
                });

                const data = await response.json();
                console.log('📡 Respuesta del servidor:', data);

                if (data.debug_firebase) {
                    console.log('🔍 Debug Firebase:', data.debug_firebase);
                }

                if (data.success) {
                    console.log('✅ Token guardado correctamente en la base de datos');
                } else {
                    console.error('❌ Error al guardar token:', data.message || 'Error desconocido');
                }

            } else {
                console.warn('⚠️ No se pudo obtener el token FCM');
            }

        } else if (permission === 'denied') {
            console.warn('⚠️ Permiso de notificaciones denegado por el usuario');
        } else {
            console.log('ℹ️ Permiso de notificaciones no concedido (default)');
        }

    } catch (err) {
        console.error('❌ Error al registrar token FCM:', err);
        console.error('Detalles del error:', err.message);
    }
}

// ========================================
// ESCUCHAR MENSAJES EN PRIMER PLANO
// ========================================
onMessage(messaging, (payload) => {
    console.log('📬 Mensaje recibido en primer plano:', payload);

    // Verificar si hay permiso para mostrar notificaciones
    if (Notification.permission === 'granted' && payload.notification) {
        const title = payload.notification.title || 'Notificación COBAEV';
        const options = {
            body: payload.notification.body || 'Nueva notificación del sistema',
            icon: '/logo.svg',
            badge: '/logo.svg',
            tag: 'cobaev-notification',
            requireInteraction: true,
            data: payload.data || {}
        };

        // Mostrar notificación
        new Notification(title, options);

        // Opcional: Reproducir sonido
        const audio = new Audio('/notification-sound.mp3');
        audio.play().catch(err => console.log('No se pudo reproducir el sonido'));
    }
});

// ========================================
// INICIALIZACIÓN AUTOMÁTICA
// ========================================
// Ejecutar cuando la página cargue completamente
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', solicitarPermisoYRegistrar);
} else {
    solicitarPermisoYRegistrar();
}

// ========================================
// DEBUGGING - Solo en desarrollo
// ========================================
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    console.log('🔧 Modo desarrollo activado');
    window.debugFCM = {
        matricula: typeof MATRICULA_USUARIO !== 'undefined' ? MATRICULA_USUARIO : 'NO DEFINIDA',
        permission: Notification.permission,
        swSupport: 'serviceWorker' in navigator,
        messaging: messaging
    };
    console.table(window.debugFCM);
}
