// Registramos el Service Worker con actualización automática
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
            .then(reg => {
                console.log('✅ Service Worker registrado');
                
                // Verificar actualizaciones cada 60 segundos
                setInterval(() => {
                    reg.update();
                }, 60000);
                
                // Actualizar al detectar cambios
                reg.addEventListener('updatefound', () => {
                    const newWorker = reg.installing;
                    console.log('🔄 Nueva versión del Service Worker encontrada');
                    
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            console.log('✅ Service Worker actualizado. Recargando página...');
                            // Recargar la página automáticamente para usar la nueva versión
                            window.location.reload();
                        }
                    });
                });
            })
            .catch(err => console.log('❌ Error:', err));
    });
    
    // Controlar cuando el SW toma control
    let refreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (!refreshing) {
            refreshing = true;
            console.log('🔄 Service Worker tomó control, recargando...');
            window.location.reload();
        }
    });
}

// Importa las librerías necesarias de Firebase
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.22.0/firebase-app.js";
import { getMessaging, getToken, onMessage } from "https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging.js";

const firebaseConfig = {
   apiKey: "AIzaSyBirHLalVvjvEiSVxCPFmSEJChNCfTDjuY",
  authDomain: "sga-cobaev.firebaseapp.com",
  projectId: "sga-cobaev",
  storageBucket: "sga-cobaev.firebasestorage.app",
  messagingSenderId: "263139601974",
  appId: "1:263139601974:web:5fba5cdd72c3b084eff297"
};

const app = initializeApp(firebaseConfig);
const messaging = getMessaging(app);

async function solicitarPermisoYRegistrar() {
    try {
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
            const registration = await navigator.serviceWorker.ready;
            const currentToken = await getToken(messaging, { 
                vapidKey: 'BJEY0s0K7FhBubLpEYmvdnxm-3Z0PsHwij0ipGlHyQYw7VvL_3knSrUOFhn5OIJXHSIwTQcvogFO_N-oJj-Q6DU',
                serviceWorkerRegistration: registration
            });

            if (currentToken) {
                console.log("Enviando token:", currentToken);

                const response = await fetch('guardar_token.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        matricula: MATRICULA_USUARIO,
                        token: currentToken 
                    })
                });

                const data = await response.json();
                console.log("Respuesta del servidor:", data);
                if (data.debug_firebase) {
                    console.log("Detalle de Firebase:", data.debug_firebase);
                }
            }
        }
    } catch (err) {
        console.error('Error al registrar token:', err);
    }
}

// Mensajes en primer plano
onMessage(messaging, (payload) => {
    console.log('Mensaje recibido en primer plano:', payload);

    if (Notification.permission === 'granted') {
        const title = payload.notification.title;
        const options = {
            body: payload.notification.body,
            icon: '/logo.png',
            tag: 'cobaev-' + Date.now()
        };

        new Notification(title, options);
    }
});

// Ejecutar
solicitarPermisoYRegistrar();
