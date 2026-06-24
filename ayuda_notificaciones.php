<?php
/**
 * Ayuda para configurar notificaciones push
 * Guia paso a paso para desactivar optimizacion de bateria por marca
 */

// Configuración centralizada de sesiones y autenticación
require_once 'includes/session_padres.php';
verificarSesionTutor();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Notificaciones - COBAEV</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .font-playfair { font-family: 'Playfair Display', serif; }
        .font-jakarta { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-crema { background-color: #f7f3eb; }
        .text-vino { color: #5c1931; }
        .bg-vino { background-color: #5c1931; }
    </style>
</head>
<body class="bg-crema font-jakarta min-h-screen">

    <!-- Header -->
    <header class="bg-vino text-white px-4 py-4 sticky top-0 z-50 shadow-md">
        <div class="max-w-lg mx-auto flex items-center space-x-3">
            <a href="padres.php" class="p-2 rounded-lg hover:bg-white/10 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="font-playfair text-lg font-semibold">Configurar Notificaciones</h1>
        </div>
    </header>

    <!-- Contenido -->
    <main class="max-w-lg mx-auto px-4 py-6 space-y-6">

        <div class="bg-white rounded-xl p-4 shadow-sm border border-amber-100">
            <div class="flex items-start space-x-3">
                <div class="w-8 h-8 bg-amber-50 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                    <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-vino">Importante</p>
                    <p class="text-xs text-zinc-600 mt-1">Para recibir las alertas de entrada y salida de su hijo(a), es necesario desactivar la optimizacion de bateria para el navegador Chrome en su telefono.</p>
                </div>
            </div>
        </div>

        <!-- Samsung -->
        <section class="bg-white rounded-xl p-4 shadow-sm">
            <h2 class="font-playfair text-base font-semibold text-vino mb-3 flex items-center space-x-2">
                <span>Samsung</span>
            </h2>
            <ol class="list-decimal list-inside text-sm text-zinc-700 space-y-2 pl-1">
                <li>Abrir <strong>Configuracion</strong> del telefono</li>
                <li>Ir a <strong>Cuidado del dispositivo</strong></li>
                <li>Tocar <strong>Bateria</strong></li>
                <li>Seleccionar <strong>Limites de uso en segundo plano</strong></li>
                <li>Buscar <strong>Chrome</strong> en la lista</li>
                <li>Seleccionar <strong>Sin restricciones</strong></li>
            </ol>
        </section>

        <!-- Xiaomi/Redmi -->
        <section class="bg-white rounded-xl p-4 shadow-sm">
            <h2 class="font-playfair text-base font-semibold text-vino mb-3">Xiaomi / Redmi</h2>
            <ol class="list-decimal list-inside text-sm text-zinc-700 space-y-2 pl-1">
                <li>Abrir <strong>Configuracion</strong></li>
                <li>Ir a <strong>Apps</strong></li>
                <li>Tocar <strong>Gestionar apps</strong></li>
                <li>Buscar y seleccionar <strong>Chrome</strong></li>
                <li>Activar <strong>Autostart</strong></li>
                <li>En Bateria, seleccionar <strong>Sin restricciones de bateria</strong></li>
            </ol>
        </section>

        <!-- Huawei/Honor -->
        <section class="bg-white rounded-xl p-4 shadow-sm">
            <h2 class="font-playfair text-base font-semibold text-vino mb-3">Huawei / Honor</h2>
            <ol class="list-decimal list-inside text-sm text-zinc-700 space-y-2 pl-1">
                <li>Abrir <strong>Configuracion</strong></li>
                <li>Ir a <strong>Bateria</strong></li>
                <li>Tocar <strong>Inicio de apps</strong></li>
                <li>Buscar <strong>Chrome</strong></li>
                <li>Desactivar gestion automatica</li>
                <li>Seleccionar <strong>Gestionar manualmente</strong> y activar todas las opciones</li>
            </ol>
        </section>

        <!-- OPPO/Realme -->
        <section class="bg-white rounded-xl p-4 shadow-sm">
            <h2 class="font-playfair text-base font-semibold text-vino mb-3">OPPO / Realme</h2>
            <ol class="list-decimal list-inside text-sm text-zinc-700 space-y-2 pl-1">
                <li>Abrir <strong>Configuracion</strong></li>
                <li>Ir a <strong>Bateria</strong></li>
                <li>Tocar <strong>Mas ajustes</strong></li>
                <li>Seleccionar <strong>Optimizacion de bateria</strong></li>
                <li>Buscar <strong>Chrome</strong></li>
                <li>Seleccionar <strong>No optimizar</strong></li>
            </ol>
        </section>

        <!-- General/Android Stock -->
        <section class="bg-white rounded-xl p-4 shadow-sm">
            <h2 class="font-playfair text-base font-semibold text-vino mb-3">General / Android Stock</h2>
            <ol class="list-decimal list-inside text-sm text-zinc-700 space-y-2 pl-1">
                <li>Abrir <strong>Configuracion</strong></li>
                <li>Ir a <strong>Apps</strong></li>
                <li>Buscar y seleccionar <strong>Chrome</strong></li>
                <li>Tocar <strong>Bateria</strong></li>
                <li>Seleccionar <strong>Sin restricciones</strong></li>
            </ol>
        </section>

        <!-- Verificar notificaciones -->
        <section class="bg-white rounded-xl p-4 shadow-sm">
            <h2 class="font-playfair text-base font-semibold text-vino mb-3">Verificar que las notificaciones funcionan</h2>
            <p class="text-sm text-zinc-600 mb-4">Presione el boton para enviar una notificacion de prueba en su dispositivo.</p>
            <button onclick="enviarNotificacionPrueba()" id="btn-test-notif" class="w-full bg-vino text-white font-semibold text-sm py-3 px-4 rounded-lg hover:opacity-90 active:opacity-80 transition-opacity">
                Enviar notificacion de prueba
            </button>
            <p id="resultado-test" class="text-xs text-zinc-500 mt-2 text-center hidden"></p>
        </section>

        <!-- Nota final -->
        <div class="bg-white rounded-xl p-4 shadow-sm border border-zinc-100">
            <p class="text-xs text-zinc-500 text-center">Si despues de seguir estos pasos sigues sin recibir notificaciones, contacta al administrador.</p>
        </div>

    </main>

    <script>
    function enviarNotificacionPrueba() {
        const btn = document.getElementById('btn-test-notif');
        const resultado = document.getElementById('resultado-test');
        
        btn.disabled = true;
        btn.textContent = 'Enviando...';
        resultado.classList.add('hidden');

        if (!('serviceWorker' in navigator)) {
            mostrarResultado('Su navegador no soporta notificaciones push.', false);
            return;
        }

        if (Notification.permission !== 'granted') {
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    realizarPrueba();
                } else {
                    mostrarResultado('Debe permitir las notificaciones para recibir alertas.', false);
                }
            });
        } else {
            realizarPrueba();
        }
    }

    function realizarPrueba() {
        navigator.serviceWorker.ready.then(function(reg) {
            reg.showNotification('Prueba COBAEV', {
                body: 'Las notificaciones funcionan correctamente en su dispositivo.',
                icon: '/logo.png',
                tag: 'test-notificacion'
            });
            mostrarResultado('Notificacion enviada. Si la vio aparecer, las notificaciones funcionan correctamente.', true);
        }).catch(function(err) {
            mostrarResultado('Error al enviar la notificacion de prueba. Intente recargar la pagina.', false);
            console.error('Error en prueba de notificacion:', err);
        });
    }

    function mostrarResultado(mensaje, exito) {
        const btn = document.getElementById('btn-test-notif');
        const resultado = document.getElementById('resultado-test');
        
        btn.disabled = false;
        btn.textContent = 'Enviar notificacion de prueba';
        resultado.textContent = mensaje;
        resultado.classList.remove('hidden');
        resultado.className = 'text-xs mt-2 text-center ' + (exito ? 'text-green-600' : 'text-red-500');
    }
    </script>

</body>
</html>
