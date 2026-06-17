<?php
/**
 * Sistema de Checador con Lector HID
 * SGA COBAEV - Control de Asistencias
 * 
 * Requiere autenticación de usuario con rol de vigilante/admin
 */

// Configuración de seguridad
session_start();

// Verificar autenticación (ajusta según tu sistema de roles)
if (!isset($_SESSION['usuario_autenticado'])) {
    header("Location: login_admin.php");
    exit;
}

// Opcional: Verificar rol específico
// if ($_SESSION['rol'] !== 'vigilante' && $_SESSION['rol'] !== 'admin') {
//     header("Location: acceso_denegado.php");
//     exit;
// }

// Configuración de zona horaria
date_default_timezone_set('America/Mexico_City');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Checador - SGA COBAEV</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">
    
    <style>
        .font-serif-elegant { font-family: 'Playfair Display', serif; }
        .font-sans-clean { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono-clock { font-family: 'Share Tech Mono', monospace; }
        
        .bg-crema { background-color: #f7f3eb; }
        .bg-crema-oscura { background-color: #efeae0; }
        .text-vino { color: #5c1931; }
        .bg-vino { background-color: #5c1931; }
        .border-vino { border-color: #5c1931; }
        .text-dorado { color: #a48253; }
        .border-dorado { border-color: #a48253; }

        /* Animación suave para nuevos registros */
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .animate-slide-in { animation: slideInRight 0.3s ease-out; }

        /* Pulso suave para zona de escaneo */
        @keyframes softPulse {
            0%, 100% { border-color: #d4d4d8; }
            50% { border-color: #a48253; }
        }
        .pulse-border { animation: softPulse 3s ease-in-out infinite; }

        /* Transición suave para el último registro */
        #contenedor-ultimo-registro {
            transition: all 0.3s ease;
        }

        /* Flash fullscreen al escanear (éxito) */
        @keyframes flashGreen {
            0% { opacity: 0; }
            15% { opacity: 0.25; }
            100% { opacity: 0; }
        }
        @keyframes flashRed {
            0% { opacity: 0; }
            15% { opacity: 0.25; }
            100% { opacity: 0; }
        }
        .flash-success {
            animation: flashGreen 0.8s ease-out;
            background: #10b981;
        }
        .flash-error {
            animation: flashRed 0.8s ease-out;
            background: #ef4444;
        }
        .flash-exit {
            animation: flashRed 0.8s ease-out;
            background: #f59e0b;
        }
        #flash-overlay {
            pointer-events: none;
            position: fixed;
            inset: 0;
            z-index: 9999;
            opacity: 0;
        }

        /* Bitácora con scroll suave */
        #lista-bitacora {
            scroll-behavior: smooth;
        }

        /* Último registro más prominente */
        #contenedor-ultimo-registro h3 {
            font-size: 1rem;
            line-height: 1.3;
        }
    </style>
</head>
<body class="bg-crema font-sans-clean min-h-screen flex flex-col selection:bg-red-200 overflow-hidden">

    <!-- Flash overlay para feedback visual -->
    <div id="flash-overlay"></div>

    <!-- NAVBAR SUPERIOR -->
    <header class="bg-white border-b border-zinc-200 px-6 py-3 flex justify-between items-center h-16 shadow-sm">
        <div class="flex items-center space-x-3">
            <!-- Botón Regresar -->
            <a href="index.php" class="text-zinc-500 hover:text-vino transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <!-- Logo / Título -->
            <div>
                <div class="flex items-center space-x-2">
                    <span class="text-vino font-serif-elegant font-bold text-lg tracking-wider">CHECADOR</span>
                    <span class="text-zinc-300">|</span>
                    <span class="text-dorado font-serif-elegant italic text-base">SGA COBAEV</span>
                </div>
                <p class="text-[9px] text-zinc-400 font-bold tracking-widest uppercase">Registro de Asistencia con Lector HID</p>
            </div>
        </div>
        <!-- Fecha y Usuario -->
        <div class="text-right">
            <div class="text-zinc-500 text-xs font-medium tracking-wide uppercase" id="fecha-cabecera">
                <script>
                    // Fecha en español
                    const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
                    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                    const fecha = new Date();
                    document.write(`${diasSemana[fecha.getDay()]}, ${fecha.getDate()} de ${meses[fecha.getMonth()]} de ${fecha.getFullYear()}`);
                </script>
            </div>
            <div class="text-[10px] text-zinc-400">
                Usuario: <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Admin'); ?></strong>
            </div>
        </div>
    </header>

    <!-- CONTENEDOR PRINCIPAL DIVIDIDO EN DOS COLUMNAS -->
    <main class="flex-grow grid grid-cols-1 lg:grid-cols-3 p-6 gap-6 h-[calc(100vh-4rem)]">
        
        <!-- COLUMNA IZQUIERDA: Reloj, Zona de Escaneo e Input Manual -->
        <div class="lg:col-span-2 flex flex-col justify-between space-y-4 h-full">
            
            <!-- Bloque del Reloj -->
            <div class="space-y-1">
                <div class="flex items-center space-x-2">
                    <span class="h-[1px] w-4 bg-dorado"></span>
                    <p class="text-[10px] font-bold tracking-widest text-dorado uppercase">Hora Institucional</p>
                </div>
                <div id="reloj" class="text-7xl md:text-8xl font-serif-elegant font-bold text-vino tracking-normal select-none leading-none"></div>
            </div>

            <!-- Zona de Estado del Lector HID -->
            <div class="relative flex-grow bg-gradient-to-br from-zinc-50 to-zinc-100 rounded-lg border-2 border-dashed border-zinc-300 pulse-border flex flex-col items-center justify-center overflow-hidden shadow-inner my-2">
                
                <!-- Esquinas decorativas -->
                <div class="absolute top-3 left-3 w-5 h-5 border-t-2 border-l-2 border-vino/30 rounded-tl"></div>
                <div class="absolute top-3 right-3 w-5 h-5 border-t-2 border-r-2 border-vino/30 rounded-tr"></div>
                <div class="absolute bottom-3 left-3 w-5 h-5 border-b-2 border-l-2 border-vino/30 rounded-bl"></div>
                <div class="absolute bottom-3 right-3 w-5 h-5 border-b-2 border-r-2 border-vino/30 rounded-br"></div>
                
                <!-- Estado: Esperando escaneo -->
                <div id="zona-escaner" class="text-center space-y-4 p-6">
                    <div class="flex justify-center text-vino/40">
                        <svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-serif-elegant italic text-2xl text-vino">Lector Activo</h3>
                        <p class="text-sm text-zinc-600 max-w-xs mx-auto mt-2">
                            Escanea la credencial con el lector HID
                        </p>
                        <div class="mt-4 inline-block bg-emerald-100 text-emerald-700 px-4 py-2 rounded-full text-xs font-bold">
                            <span class="inline-block w-2 h-2 bg-emerald-500 rounded-full mr-2 animate-pulse"></span>
                            Esperando escaneo...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Input de Modo Manual inferior -->
            <div class="flex space-x-2">
                <input 
                    type="text" 
                    id="input-manual" 
                    maxlength="9" 
                    inputmode="numeric"
                    pattern="[0-9]*"
                    placeholder="Modo manual: 9 dígitos numéricos (ej. 202400123)" 
                    class="flex-grow bg-white border border-zinc-300 rounded px-4 py-2 text-sm font-mono focus:outline-none focus:border-vino transition-colors shadow-sm"
                    autocomplete="off">
                <button 
                    type="button" 
                    id="btn-registrar-manual"
                    onclick="procesarMatriculaManual()" 
                    class="bg-vino hover:bg-opacity-90 text-white font-sans-clean font-bold text-xs tracking-wider uppercase px-6 rounded shadow transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                    Registrar
                </button>
            </div>
        </div>

        <!-- COLUMNA DERECHA: Último registro y Bitácora Reciente -->
        <div class="bg-white border border-zinc-200 rounded-lg p-4 flex flex-col justify-between h-full shadow-sm overflow-hidden">
            
            <!-- Sección Superior: Último Registro -->
            <div class="space-y-2 flex-shrink-0">
                <div class="flex items-center space-x-2">
                    <span class="h-[1px] w-3 bg-dorado"></span>
                    <p class="text-[10px] font-bold tracking-widest text-dorado uppercase">Último Registro</p>
                </div>
                <div id="contenedor-ultimo-registro" class="bg-crema border-2 border-dashed border-zinc-200 rounded-xl p-4 text-center text-sm text-zinc-500 h-32 flex items-center justify-center font-medium transition-all">
                    <div class="space-y-1">
                        <svg class="w-8 h-8 mx-auto text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-xs text-zinc-400">Esperando primer escaneo...</p>
                    </div>
                </div>
            </div>

            <!-- Sección Inferior: Bitácora Reciente -->
            <div class="flex-grow flex flex-col min-h-0 mt-4">
                <div class="flex items-center space-x-2 mb-3 flex-shrink-0">
                    <span class="h-[1px] w-3 bg-dorado"></span>
                    <p class="text-[10px] font-bold tracking-widest text-dorado uppercase">Bitácora Reciente</p>
                </div>

                <div class="flex-grow overflow-y-auto space-y-4 pr-1" id="lista-bitacora">
                    <!-- Se llena dinámicamente -->
                </div>
            </div>

        </div>
    </main>

    <!-- SCRIPTS DE CONTROL -->
    <script>
    // ============================================
    // CONFIGURACIÓN DEL SISTEMA
    // ============================================
    const CONFIG = {
        LONGITUD_MATRICULA: 9,           // Caracteres de la matrícula
        SOLO_NUMEROS: true,              // ¿Solo acepta números?
        TIMEOUT_LECTURA: 300,            // ms entre caracteres del lector HID
        PREVENIR_DUPLICADOS: 2000,       // ms para considerar escaneo duplicado
        DELAY_REACTIVACION: 3000,        // ms antes de aceptar nuevo escaneo
        TIMEOUT_PROCESAMIENTO: 10000     // ms timeout de seguridad
    };

    // ============================================
    // VARIABLES GLOBALES
    // ============================================
    let procesando = false;
    let buffer = "";
    let timer = null;
    let timeoutProcesamiento = null;
    let ultimaMatricula = "";
    let tiempoUltimoEscaneo = 0;

    // Audio precar gado
    const audioBeep = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBSuBzvLZjz0');
    audioBeep.preload = 'auto';

    // ============================================
    // RELOJ DE ALTA PRECISIÓN
    // ============================================
    function actualizarReloj() {
        const ahora = new Date();
        let h = ahora.getHours().toString().padStart(2, '0');
        let m = ahora.getMinutes().toString().padStart(2, '0');
        let s = ahora.getSeconds().toString().padStart(2, '0');
        document.getElementById('reloj').innerText = `${h}:${m}:${s}`;
    }
    setInterval(actualizarReloj, 1000);
    actualizarReloj();

    // ============================================
    // PROCESAMIENTO DE LECTURA HID
    // ============================================
    function procesarLecturaHID(cadenaCompleta) {
        // Extraer solo los primeros 9 caracteres
        const matricula = cadenaCompleta.substring(0, CONFIG.LONGITUD_MATRICULA);
        const ahora = Date.now();
        
        // Validar que sean 9 dígitos numéricos
        if (!/^\d{9}$/.test(matricula)) {
            mostrarError('Código inválido. La matrícula debe tener 9 dígitos numéricos.');
            procesando = false;
            buffer = "";
            return;
        }
        
        // Prevenir escaneos duplicados
        if (matricula === ultimaMatricula && (ahora - tiempoUltimoEscaneo) < CONFIG.PREVENIR_DUPLICADOS) {
            console.log('⚠️ Escaneo duplicado ignorado:', matricula);
            procesando = false;
            buffer = "";
            return;
        }
        
        ultimaMatricula = matricula;
        tiempoUltimoEscaneo = ahora;
        
        // Cambiar estado visual
        mostrarEscaneando();
        
        // Reproducir sonido
        audioBeep.play().catch(e => console.log("Audio bloqueado por navegador"));
        
        // Enviar al backend
        enviarMatriculaBackend(matricula);
    }

    // ============================================
    // PROCESAMIENTO MANUAL
    // ============================================
    function procesarMatriculaManual() {
        const input = document.getElementById('input-manual');
        const matricula = input.value.trim();
        
        if (!matricula || procesando) return;
        
        // Validar formato
        if (!/^\d{9}$/.test(matricula)) {
            mostrarError('Formato inválido. Ingresa 9 dígitos numéricos.');
            return;
        }
        
        procesando = true;
        mostrarEscaneando();
        enviarMatriculaBackend(matricula);
        input.value = "";
    }

    // ============================================
    // COMUNICACIÓN CON BACKEND
    // ============================================
    function enviarMatriculaBackend(matricula) {
        const boton = document.getElementById('btn-registrar-manual');
        boton.disabled = true;
        
        // Timeout de seguridad
        timeoutProcesamiento = setTimeout(() => {
            procesando = false;
            boton.disabled = false;
            mostrarError('Timeout: El servidor no respondió a tiempo.');
            console.warn('⚠️ Timeout: Liberando procesamiento');
        }, CONFIG.TIMEOUT_PROCESAMIENTO);
        
        const formData = new FormData();
        formData.append('matricula', matricula);
        
        fetch('procesar_qr.php', { method: 'POST', body: formData })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            clearTimeout(timeoutProcesamiento);
            actualizarUIRespuesta(data, matricula);
            
            // Actualizar bitácora
            // Soporta ambos formatos del backend:
            // 1. data.nuevo_registro (registro individual nuevo)
            // 2. data.bitacora (array completo de registros)
            if (data.bitacora && data.bitacora.length > 0) {
                // Backend devuelve la bitácora completa: recargar toda la lista
                const contenedor = document.getElementById('lista-bitacora');
                contenedor.innerHTML = data.bitacora.map(r => crearTarjetaRegistro(r)).join('');
            } else if (data.nuevo_registro) {
                // Backend devuelve solo el nuevo registro: insertar al inicio
                agregarRegistroBitacora(data.nuevo_registro);
            } else {
                // Fallback: recargar bitácora desde endpoint dedicado
                cargarBitacoraInicial();
            }
        })
        .catch(error => {
            clearTimeout(timeoutProcesamiento);
            console.error('Error:', error);
            mostrarError('Error de conexión. Verifica tu red e intenta de nuevo.');
        })
        .finally(() => {
            setTimeout(() => { 
                procesando = false; 
                boton.disabled = false;
                restaurarZonaEscaner();
            }, CONFIG.DELAY_REACTIVACION);
        });
    }

    // ============================================
    // ACTUALIZACIÓN DE UI
    // ============================================
    function actualizarUIRespuesta(data, matricula) {
        const contenedor = document.getElementById('contenedor-ultimo-registro');
        const horaActual = data.hora || document.getElementById('reloj').innerText;
        
        let esEntrada = data.title && data.title.includes('Entrada');
        let esExito = data.status === 'success';
        
        // Flash fullscreen
        const flash = document.getElementById('flash-overlay');
        flash.className = '';
        void flash.offsetWidth; // Forzar reflow
        if (esExito && esEntrada) {
            flash.classList.add('flash-success');
        } else if (esExito && !esEntrada) {
            flash.classList.add('flash-exit');
        } else {
            flash.classList.add('flash-error');
        }
        
        let borderClass = esExito ? (esEntrada ? 'border-emerald-200' : 'border-rose-200') : 'border-amber-200';
        let bgClass = esExito ? (esEntrada ? 'bg-emerald-50' : 'bg-rose-50') : 'bg-amber-50';
        let textClass = esExito ? (esEntrada ? 'text-emerald-600' : 'text-rose-600') : 'text-amber-600';
        let titleText = esExito ? (esEntrada ? 'ENTRADA REGISTRADA' : 'SALIDA REGISTRADA') : 'ATENCIÓN';
        let grupoText = data.grupo ? `• Grupo ${data.grupo}` : '';
        
        contenedor.style.opacity = '0';
        contenedor.style.transform = 'scale(0.95)';
        setTimeout(() => {
            contenedor.className = `bg-white border-2 ${borderClass} ${bgClass} rounded-xl p-4 flex items-center space-x-4 h-32 transition-all duration-300`;
            contenedor.innerHTML = `
                <div class="w-16 h-16 rounded-2xl border-2 ${borderClass} ${bgClass} flex items-center justify-center flex-shrink-0">
                    ${esExito && esEntrada 
                        ? '<svg class="w-8 h-8 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
                        : esExito 
                            ? '<svg class="w-8 h-8 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"></path></svg>'
                            : '<svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
                    }
                </div>
                <div class="text-left flex-grow">
                    <span class="text-[10px] font-bold ${textClass} tracking-wider uppercase block">${titleText}</span>
                    <h3 class="text-base font-bold text-zinc-900 leading-tight mt-1">${data.message || 'Procesado'}</h3>
                    <p class="text-xs text-zinc-500 font-mono mt-0.5">${matricula} ${grupoText}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-lg font-mono font-bold text-vino">${horaActual}</p>
                </div>
            `;
            contenedor.style.opacity = '1';
            contenedor.style.transform = 'scale(1)';
        }, 150);
    }

    function mostrarEscaneando() {
        const zona = document.getElementById('zona-escaner');
        zona.innerHTML = `
            <div class="text-center space-y-3">
                <svg class="animate-bounce w-20 h-20 text-emerald-500 mx-auto" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <p class="text-emerald-600 font-bold text-lg">¡Escaneado!</p>
                <p class="text-sm text-zinc-500">Procesando...</p>
            </div>
        `;
    }

    function restaurarZonaEscaner() {
        const zona = document.getElementById('zona-escaner');
        zona.innerHTML = `
            <div class="flex justify-center text-vino/40">
                <svg class="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                </svg>
            </div>
            <div>
                <h3 class="font-serif-elegant italic text-2xl text-vino">Lector Activo</h3>
                <p class="text-sm text-zinc-600 max-w-xs mx-auto mt-2">Escanea la credencial con el lector HID</p>
                <div class="mt-4 inline-block bg-emerald-100 text-emerald-700 px-4 py-2 rounded-full text-xs font-bold">
                    <span class="inline-block w-2 h-2 bg-emerald-500 rounded-full mr-2 animate-pulse"></span>
                    Esperando escaneo...
                </div>
            </div>
        `;
    }

    function mostrarError(mensaje) {
        const contenedor = document.getElementById('contenedor-ultimo-registro');
        contenedor.innerHTML = `
            <div class="text-center space-y-2">
                <svg class="w-8 h-8 text-rose-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm text-rose-600 font-medium">${mensaje}</p>
            </div>
        `;
    }

    // ============================================
    // BITÁCORA
    // ============================================
    function agregarRegistroBitacora(registro) {
        const contenedor = document.getElementById('lista-bitacora');
        const nuevoHTML = crearTarjetaRegistro(registro);
        contenedor.insertAdjacentHTML('afterbegin', nuevoHTML);
        
        // Auto-scroll suave al tope
        contenedor.scrollTo({ top: 0, behavior: 'smooth' });
        
        // Animación de entrada
        if (contenedor.firstElementChild) {
            contenedor.firstElementChild.style.animation = 'slideInRight 0.3s ease-out';
        }
        
        // Limitar a 20 registros visibles
        if (contenedor.children.length > 20) {
            contenedor.lastChild.remove();
        }
    }

    function crearTarjetaRegistro(r) {
        return `
            <div class="flex items-center justify-between border border-zinc-200 bg-white p-4 rounded-xl shadow-sm">
                <div class="flex items-center space-x-4">
                    <div class="w-1.5 ${r.tipo === 'Entrada' ? 'bg-emerald-500' : 'bg-rose-500'} h-12 rounded-full"></div>
                    <div class="w-12 h-12 rounded-lg bg-orange-100 text-orange-700 flex items-center justify-center text-lg font-bold font-serif-elegant">
                        ${r.nombre.charAt(0)}
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-zinc-900 leading-tight">${r.nombre} ${r.apellido_paterno} ${r.apellido_materno || ''}</h4>
                        <p class="text-xs font-mono text-zinc-500 mt-0.5">${r.matricula_alumno} ${r.grupo ? '• Grupo ' + r.grupo : ''}</p>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-[10px] font-bold px-3 py-1 rounded-full ${r.tipo === 'Entrada' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'} uppercase block tracking-wider">
                        ${r.tipo}
                    </span>
                    <span class="text-sm font-mono font-bold text-zinc-700 mt-1 block">${r.hora}</span>
                </div>
            </div>
        `;
    }

    function cargarBitacoraInicial() {
        fetch('obtener_bitacora.php')
        .then(response => response.json())
        .then(data => {
            const contenedor = document.getElementById('lista-bitacora');
            contenedor.innerHTML = data.map(r => crearTarjetaRegistro(r)).join('');
        })
        .catch(error => console.error('Error al cargar bitácora:', error));
    }

    // ============================================
    // CAPTURA DEL LECTOR HID
    // ============================================
    const inputManual = document.getElementById('input-manual');
    
    // Enter en el input manual
    inputManual.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            procesarMatriculaManual();
        }
    });
    
    // Solo números en input manual
    inputManual.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Captura global del lector HID
    document.addEventListener('keypress', function(e) {
        // Ignorar si el input manual tiene el foco
        if (document.activeElement.id === 'input-manual') return;
        if (procesando) return;
        
        clearTimeout(timer);
        
        const char = String.fromCharCode(e.charCode);
        
        // Aceptar números, letras y algunos símbolos
        if (/^[a-zA-Z0-9\-_]$/.test(char)) {
            buffer += char;
        }
        
        // Detectar Enter del lector HID
        if (e.key === 'Enter') {
            if (buffer.length >= CONFIG.LONGITUD_MATRICULA) {
                procesando = true;
                procesarLecturaHID(buffer);
                buffer = "";
            }
            return;
        }
        
        // Timeout de seguridad
        timer = setTimeout(() => {
            if (buffer.length >= CONFIG.LONGITUD_MATRICULA) {
                procesando = true;
                procesarLecturaHID(buffer);
            }
            buffer = "";
        }, CONFIG.TIMEOUT_LECTURA);
    });

    // ============================================
    // INICIALIZACIÓN
    // ============================================
    window.onload = function() {
        actualizarReloj();
        cargarBitacoraInicial();
        console.log('✅ Checador HID inicializado');
        console.log('📋 Configuración:', CONFIG);
    };
    </script>
</body>
</html>
