<?php
/**
 * Ayuda para Configurar Notificaciones - Portal de Padres
 * SGA COBAEV
 * Guia paso a paso para desactivar optimizacion de bateria por marca
 */

ob_start();
date_default_timezone_set('America/Mexico_City');

if (session_status() === PHP_SESSION_NONE) {
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
}

// Verificar autenticacion
if (!isset($_SESSION['tutor_autenticado']) || $_SESSION['tutor_autenticado'] !== true) {
    header("Location: login_padres.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#5c1931">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Configurar Notificaciones - COBAEV</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        .font-serif-elegant { font-family: 'Playfair Display', serif; }
        .font-sans-clean { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        .bg-crema { background-color: #f7f3eb; }
        .text-vino { color: #5c1931; }
        .bg-vino { background-color: #5c1931; }
        .border-vino { border-color: #5c1931; }
        .text-dorado { color: #a48253; }
        .bg-dorado { background-color: #a48253; }

        html { scroll-behavior: smooth; }
        body { -webkit-tap-highlight-color: transparent; }
    </style>
</head>
<body class="bg-crema font-sans-clean min-h-screen">

    <!-- Header -->
    <header class="bg-vino sticky top-0 z-50 shadow-lg">
        <div class="flex items-center justify-between px-4 py-3">
            <a href="padres.php" class="w-9 h-9 bg-white/10 hover:bg-white/20 rounded-lg flex items-center justify-center transition-colors active:scale-95">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-white font-serif-elegant text-base font-semibold">Configurar Notificaciones</h1>
            <div class="w-9"></div>
        </div>
    </header>

    <!-- Contenido principal -->
    <main class="px-4 py-6 max-w-lg mx-auto space-y-6">

        <!-- Introduccion -->
        <div class="bg-white rounded-xl p-4 shadow-sm border border-zinc-100">
            <p class="text-sm text-zinc-600 leading-relaxed">
                Para que las notificaciones de asistencia lleguen correctamente, es necesario desactivar la 
                <strong class="text-vino">optimizacion de bateria</strong> para el navegador Chrome en tu dispositivo.
            </p>
            <p class="text-xs text-zinc-400 mt-2">Selecciona la marca de tu telefono y sigue los pasos:</p>
        </div>

        <!-- Samsung -->
        <div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden">
            <div class="px-4 py-3 bg-blue-50 border-b border-blue-100">
                <h2 class="text-sm font-bold text-blue-800 font-sans-clean">Samsung</h2>
            </div>
            <div class="px-4 py-3">
                <ol class="text-xs text-zinc-600 space-y-2 list-decimal list-inside">
                    <li>Abre <strong>Configuracion</strong> de tu telefono</li>
                    <li>Ve a <strong>Cuidado del dispositivo</strong></li>
                    <li>Toca en <strong>Bateria</strong></li>
                    <li>Selecciona <strong>Limites de uso en segundo plano</strong></li>
                    <li>Busca <strong>Chrome</strong> en la lista</li>
                    <li>Selecciona <strong>Sin restricciones</strong></li>
                </ol>
            </div>
        </div>

        <!-- Xiaomi / Redmi -->
        <div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden">
            <div class="px-4 py-3 bg-orange-50 border-b border-orange-100">
                <h2 class="text-sm font-bold text-orange-800 font-sans-clean">Xiaomi / Redmi</h2>
            </div>
            <div class="px-4 py-3">
                <ol class="text-xs text-zinc-600 space-y-2 list-decimal list-inside">
                    <li>Abre <strong>Configuracion</strong> de tu telefono</li>
                    <li>Ve a <strong>Apps</strong></li>
                    <li>Toca en <strong>Gestionar apps</strong></li>
                    <li>Busca y selecciona <strong>Chrome</strong></li>
                    <li>Activa <strong>Autostart</strong> (inicio automatico)</li>
                    <li>En la seccion de bateria, selecciona <strong>Sin restricciones de bateria</strong></li>
                </ol>
            </div>
        </div>

        <!-- Huawei / Honor -->
        <div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden">
            <div class="px-4 py-3 bg-red-50 border-b border-red-100">
                <h2 class="text-sm font-bold text-red-800 font-sans-clean">Huawei / Honor</h2>
            </div>
            <div class="px-4 py-3">
                <ol class="text-xs text-zinc-600 space-y-2 list-decimal list-inside">
                    <li>Abre <strong>Configuracion</strong> de tu telefono</li>
                    <li>Ve a <strong>Bateria</strong></li>
                    <li>Toca en <strong>Inicio de apps</strong></li>
                    <li>Busca <strong>Chrome</strong> en la lista</li>
                    <li>Desactiva la gestion automatica</li>
                    <li>Selecciona <strong>Gestionar manualmente</strong> y activa todas las opciones</li>
                </ol>
            </div>
        </div>

        <!-- OPPO / Realme -->
        <div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden">
            <div class="px-4 py-3 bg-green-50 border-b border-green-100">
                <h2 class="text-sm font-bold text-green-800 font-sans-clean">OPPO / Realme</h2>
            </div>
            <div class="px-4 py-3">
                <ol class="text-xs text-zinc-600 space-y-2 list-decimal list-inside">
                    <li>Abre <strong>Configuracion</strong> de tu telefono</li>
                    <li>Ve a <strong>Bateria</strong></li>
                    <li>Toca en <strong>Mas ajustes</strong></li>
                    <li>Selecciona <strong>Optimizacion de bateria</strong></li>
                    <li>Busca <strong>Chrome</strong></li>
                    <li>Selecciona <strong>No optimizar</strong></li>
                </ol>
            </div>
        </div>

        <!-- General / Android Stock -->
        <div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden">
            <div class="px-4 py-3 bg-zinc-50 border-b border-zinc-200">
                <h2 class="text-sm font-bold text-zinc-800 font-sans-clean">General / Android Stock</h2>
            </div>
            <div class="px-4 py-3">
                <ol class="text-xs text-zinc-600 space-y-2 list-decimal list-inside">
                    <li>Abre <strong>Configuracion</strong> de tu telefono</li>
                    <li>Ve a <strong>Apps</strong></li>
                    <li>Busca y selecciona <strong>Chrome</strong></li>
                    <li>Toca en <strong>Bateria</strong></li>
                    <li>Selecciona <strong>Sin restricciones</strong></li>
                </ol>
            </div>
        </div>

        <!-- Verificar notificaciones -->
        <div class="bg-white rounded-xl shadow-sm border border-zinc-100 overflow-hidden">
            <div class="px-4 py-3 bg-amber-50 border-b border-amber-100">
                <h2 class="text-sm font-bold text-amber-800 font-sans-clean">Verificar que las notificaciones funcionan</h2>
            </div>
            <div class="px-4 py-4 text-center">
                <p class="text-xs text-zinc-500 mb-4">Presiona el boton para enviar una notificacion de prueba a este dispositivo:</p>
                <button onclick="enviarNotificacionPrueba()" id="btn-prueba" class="bg-vino text-white text-sm font-semibold px-6 py-3 rounded-xl shadow-md hover:opacity-90 active:scale-95 transition-all">
                    Enviar notificacion de prueba
                </button>
                <p id="resultado-prueba" class="text-xs mt-3 hidden"></p>
            </div>
        </div>

        <!-- Nota final -->
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
            <p class="text-xs text-amber-800 leading-relaxed">
                Si despues de seguir estos pasos sigues sin recibir notificaciones, contacta al administrador.
            </p>
        </div>

    </main>

    <script>
        function enviarNotificacionPrueba() {
            const btn = document.getElementById('btn-prueba');
            const resultado = document.getElementById('resultado-prueba');
            
            btn.disabled = true;
            btn.textContent = 'Enviando...';
            resultado.classList.add('hidden');

            if (!('serviceWorker' in navigator)) {
                mostrarResultado('Tu navegador no soporta notificaciones push.', false);
                btn.disabled = false;
                btn.textContent = 'Enviar notificacion de prueba';
                return;
            }

            navigator.serviceWorker.ready.then(function(registration) {
                return registration.showNotification('Notificacion de prueba', {
                    body: 'Si ves esto, las notificaciones funcionan correctamente',
                    icon: '/logo.png'
                });
            }).then(function() {
                mostrarResultado('Notificacion enviada. Deberias verla en tu pantalla.', true);
            }).catch(function(error) {
                console.error('Error al enviar notificacion de prueba:', error);
                mostrarResultado('Error: No se pudo enviar la notificacion. Verifica los permisos.', false);
            }).finally(function() {
                btn.disabled = false;
                btn.textContent = 'Enviar notificacion de prueba';
            });
        }

        function mostrarResultado(mensaje, exito) {
            const resultado = document.getElementById('resultado-prueba');
            resultado.textContent = mensaje;
            resultado.className = 'text-xs mt-3 ' + (exito ? 'text-green-600' : 'text-red-600');
            resultado.classList.remove('hidden');
        }
    </script>

</body>
</html>
