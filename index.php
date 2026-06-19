<?php
// Verificar si ya hay una sesión activa
// Cookie de sesión con duración larga (30 días) para PWA
ini_set('session.cookie_lifetime', 2592000);
ini_set('session.gc_maxlifetime', 2592000);
session_set_cookie_params([
    'lifetime' => 2592000,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
if (isset($_SESSION['tutor_autenticado']) && $_SESSION['tutor_autenticado'] === true) {
    // Si ya está logueado, redirigir al portal
    header("Location: padres.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de Gestión de Asistencias - Portal de Padres COBAEV">
    <meta name="theme-color" content="#5c1931">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Bienvenido - SGA COBAEV</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        .font-serif-elegant { font-family: 'Playfair Display', serif; }
        .font-sans-clean { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        .bg-crema { background-color: #f7f3eb; }
        .text-vino { color: #5c1931; }
        .bg-vino { background-color: #5c1931; }
        .border-vino { border-color: #5c1931; }
        .text-dorado { color: #a48253; }
        .bg-dorado { background-color: #a48253; }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .fade-in-up { animation: fadeInUp 0.6s ease-out; }
        .fade-in-up-delay { animation: fadeInUp 0.6s ease-out 0.2s both; }
        
        /* Ocultar botón de instalación por defecto */
        #installButton { display: none; }
    </style>
</head>
<body class="bg-crema font-sans-clean min-h-screen flex flex-col">

    <!-- Header Simple -->
    <header class="p-4 text-center border-b border-zinc-200 bg-white">
        <div class="flex items-center justify-center space-x-2">
            <span class="text-vino font-serif-elegant font-bold text-xl tracking-wider">SGA COBAEV</span>
            <span class="text-zinc-300">|</span>
            <span class="text-dorado font-serif-elegant italic text-sm">Portal de Padres</span>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow flex items-center justify-center p-4 md:p-8">
        <div class="max-w-2xl w-full space-y-8 fade-in-up">
            
            <!-- Logo/Imagen Placeholder -->
            <div class="text-center">
                <div class="w-24 h-24 mx-auto mb-6 bg-vino rounded-full flex items-center justify-center shadow-lg">
                    <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
            </div>

            <!-- Título y Descripción -->
            <div class="bg-white border border-zinc-200/80 rounded-2xl p-8 shadow-sm text-center space-y-6">
                <div class="space-y-3">
                    <h1 class="text-3xl md:text-4xl font-serif-elegant font-bold text-vino">
                        ¡Bienvenido, Padre de Familia!
                    </h1>
                    
                    <div class="flex items-center space-x-3 justify-center max-w-xs mx-auto">
                        <div class="h-[2px] w-12 bg-dorado"></div>
                        <span class="text-[9px] text-zinc-300">❖</span>
                        <div class="h-[2px] w-12 bg-dorado"></div>
                    </div>
                    
                    <p class="text-zinc-600 text-base leading-relaxed max-w-md mx-auto">
                        Monitorea en <strong class="text-vino">tiempo real</strong> las entradas y salidas de tu hijo(a) al plantel escolar.
                    </p>
                </div>

                <!-- Características -->
                <div class="grid md:grid-cols-3 gap-4 pt-4">
                    <div class="space-y-2 p-4 bg-zinc-50 rounded-lg">
                        <div class="text-2xl">📱</div>
                        <h3 class="text-xs font-bold text-zinc-700 uppercase tracking-wide">Notificaciones Push</h3>
                        <p class="text-[11px] text-zinc-500">Alertas instantáneas de cada movimiento</p>
                    </div>
                    
                    <div class="space-y-2 p-4 bg-zinc-50 rounded-lg">
                        <div class="text-2xl">⏱️</div>
                        <h3 class="text-xs font-bold text-zinc-700 uppercase tracking-wide">Tiempo Real</h3>
                        <p class="text-[11px] text-zinc-500">Información actualizada al instante</p>
                    </div>
                    
                    <div class="space-y-2 p-4 bg-zinc-50 rounded-lg">
                        <div class="text-2xl">📊</div>
                        <h3 class="text-xs font-bold text-zinc-700 uppercase tracking-wide">Historial</h3>
                        <p class="text-[11px] text-zinc-500">Consulta registros anteriores</p>
                    </div>
                </div>

                <!-- Botones de Acción -->
                <div class="space-y-3 pt-4">
                    <!-- Botón de Instalar PWA -->
                    <button id="installButton" class="w-full bg-dorado hover:bg-opacity-95 text-white font-bold text-sm tracking-wider uppercase py-4 px-8 rounded-xl shadow-lg active:scale-[0.98] transition-all flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span>Instalar Aplicación</span>
                    </button>

                    <!-- Botón de Ingresar -->
                    <a href="login_padres.php" class="block w-full bg-vino hover:bg-opacity-95 text-white font-bold text-sm tracking-wider uppercase py-4 px-8 rounded-xl shadow-lg active:scale-[0.98] transition-all">
                        Ingresar al Portal
                    </a>
                    
                    <!-- Enlace alternativo -->
                    <p class="text-xs text-zinc-500 pt-2">
                        ¿Primera vez aquí? 
                        <a href="#" class="text-dorado hover:underline font-semibold">Solicita tu acceso</a>
                    </p>
                </div>
            </div>

            <!-- Información Adicional -->
            <div class="text-center space-y-3 fade-in-up-delay">
                <div class="bg-white/60 border border-zinc-200/60 rounded-lg p-4">
                    <p class="text-[11px] text-zinc-500 leading-relaxed">
                        <strong class="text-vino">💡 Tip:</strong> Instala la aplicación en tu teléfono para recibir notificaciones 
                        incluso cuando el navegador esté cerrado.
                    </p>
                </div>
            </div>

        </div>
    </main>

    <!-- Footer -->
    <footer class="p-6 text-center text-[9px] text-zinc-400 uppercase tracking-widest bg-white border-t border-zinc-100">
        <p>Colegio de Bachilleres del Estado de Veracruz</p>
        <p class="text-[10px] mt-1 text-zinc-300">Sistema de Alertas de Acceso • 2024</p>
    </footer>

    <!-- Script para PWA Installation -->
    <script>
        let deferredPrompt;
        const installButton = document.getElementById('installButton');

        // Detectar cuando el navegador está listo para instalar
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('💾 Evento de instalación detectado');
            e.preventDefault();
            deferredPrompt = e;
            
            // Mostrar el botón de instalación
            installButton.style.display = 'flex';
        });

        // Manejar el click en el botón de instalación
        installButton.addEventListener('click', async () => {
            if (!deferredPrompt) {
                console.log('⚠️ No hay evento de instalación disponible');
                alert('La aplicación ya está instalada o tu navegador no soporta instalación.');
                return;
            }

            // Mostrar el prompt de instalación
            deferredPrompt.prompt();

            // Esperar la respuesta del usuario
            const { outcome } = await deferredPrompt.userChoice;
            console.log(`👤 Usuario ${outcome === 'accepted' ? 'aceptó' : 'rechazó'} la instalación`);

            if (outcome === 'accepted') {
                // Ocultar el botón después de instalar
                installButton.style.display = 'none';
            }

            // Limpiar el prompt
            deferredPrompt = null;
        });

        // Detectar cuando la app fue instalada exitosamente
        window.addEventListener('appinstalled', () => {
            console.log('✅ PWA instalada exitosamente');
            installButton.style.display = 'none';
            
            // Opcional: mostrar mensaje de éxito
            alert('¡Aplicación instalada! Ahora puedes acceder desde tu pantalla de inicio.');
        });

        // Registrar Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(reg => console.log('✅ Service Worker registrado'))
                    .catch(err => console.log('❌ Error al registrar SW:', err));
            });
        }
    </script>

</body>
</html>
