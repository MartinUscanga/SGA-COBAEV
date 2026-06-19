// Registramos el Service Worker con actualización automática
if ('serviceWorker' in navigator) {
    // Forzar actualización de SWs existentes ANTES de registrar
    navigator.serviceWorker.getRegistrations().then(registrations => {
        registrations.forEach(reg => reg.update());
    });

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js', { updateViaCache: 'none' })
            .then(reg => {
                console.log('✅ Service Worker registrado');
                
                // Verificar actualizaciones cada 60 segundos
                setInterval(() => {
                    reg.update();
                }, 60000);
                
                // Forzar una actualización inmediata al cargar
                reg.update();
                
                // Actualizar al detectar cambios
                reg.addEventListener('updatefound', () => {
                    const newWorker = reg.installing;
                    console.log('🔄 Nueva versión del Service Worker encontrada');
                    
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            console.log('✅ Service Worker actualizado. Recargando página...');
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
                // PROTECCION CONTRA DUPLICADOS (Frontend):
                // Solo prevenir llamadas repetidas desde EL MISMO dispositivo con la MISMA matrícula
                const tokenGuardado = localStorage.getItem('fcm_token_enviado');
                const matriculasGuardadas = localStorage.getItem('fcm_matriculas');
                
                // Usar MATRICULAS_USUARIO si esta disponible (multi-alumno), sino fallback a MATRICULA_USUARIO
                const matriculasArray = (typeof MATRICULAS_USUARIO !== 'undefined' && Array.isArray(MATRICULAS_USUARIO) && MATRICULAS_USUARIO.length > 0)
                    ? MATRICULAS_USUARIO
                    : [MATRICULA_USUARIO];
                
                const matriculasJSON = JSON.stringify(matriculasArray.sort());

                // Siempre enviar si: token cambió, matrículas cambiaron, o no hay token guardado
                if (tokenGuardado === currentToken && matriculasGuardadas === matriculasJSON) {
                    console.log("✅ Token ya registrado para estas matriculas, no se reenvia.");
                    return;
                }

                // Limpiar localStorage anterior si las matrículas cambiaron
                if (matriculasGuardadas && matriculasGuardadas !== matriculasJSON) {
                    localStorage.removeItem('fcm_token_enviado');
                    localStorage.removeItem('fcm_matriculas');
                    console.log("🔄 Matrículas cambiaron, re-registrando token...");
                }

                // Token nuevo o matriculas diferentes -> enviar al servidor
                console.log("📡 Enviando token al servidor para", matriculasArray.length, "matricula(s)...");
                console.log("Token preview:", currentToken.substring(0, 30) + "...");

                const response = await fetch('guardar_token.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        matriculas: matriculasArray,
                        matricula: MATRICULA_USUARIO,
                        token: currentToken 
                    })
                });

                const data = await response.json();
                console.log("Respuesta del servidor:", data);
                
                // Guardar en localStorage SOLO si se guardo correctamente
                if (data.success) {
                    localStorage.setItem('fcm_token_enviado', currentToken);
                    localStorage.setItem('fcm_matriculas', matriculasJSON);
                    // Mantener compatibilidad con localStorage anterior
                    localStorage.setItem('fcm_matricula', MATRICULA_USUARIO);
                    console.log("✅ Token guardado para", matriculasArray.length, "matricula(s). Status:", data.status);
                } else {
                    console.error("❌ Error al guardar token:", data.message);
                }
            } else {
                console.warn("⚠️ No se pudo obtener token FCM. Verifica permisos y Service Worker.");
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
        // Con data-only, los datos vienen en payload.data
        const title = payload.data?.title || payload.notification?.title || 'Alerta COBAEV';
        const body = payload.data?.body || payload.notification?.body || '';
        const icon = payload.data?.icon || '/logo.png';

        const options = {
            body: body,
            icon: icon,
            tag: 'cobaev-fg' // Tag fijo para evitar duplicados
        };

        navigator.serviceWorker.ready.then(registration => {
            registration.showNotification(title, options);
        });
    }
});

// Ejecutar
solicitarPermisoYRegistrar();

// Heartbeat: re-registrar token cuando la app vuelve a estar visible despues de mucho tiempo idle
document.addEventListener('visibilitychange', function() {
    if (document.visibilityState === 'visible') {
        const ahora = Date.now();
        const ultimoHeartbeat = parseInt(localStorage.getItem('fcm_last_heartbeat') || '0', 10);
        const treintaMinutos = 30 * 60 * 1000; // 1800000 ms

        if (ahora - ultimoHeartbeat > treintaMinutos) {
            console.log('🔄 Heartbeat: Re-registrando token FCM tras inactividad...');
            localStorage.setItem('fcm_last_heartbeat', ahora.toString());
            solicitarPermisoYRegistrar();
        }
    }
});
