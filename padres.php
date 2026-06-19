<?php
/**
 * Portal de Padres - COBAEV
 * Dashboard de seguimiento de asistencias en tiempo real
 * Mobile-first UI/UX
 */

// Aseguramos el bufer de salida para evitar errores de redireccion
ob_start();

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Iniciamos la sesion de forma segura
if (session_status() === PHP_SESSION_NONE) {
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
}

// Protegemos la pagina: verificar autenticacion
if (!isset($_SESSION['tutor_autenticado']) || $_SESSION['tutor_autenticado'] !== true) {
    header("Location: login_padres.php");
    exit;
}

// Traemos la conexion PDO
require_once 'conexion.php';

// Manejar cambio de alumno activo via GET
if (isset($_GET['alumno']) && isset($_SESSION['alumnos'])) {
    $alumno_solicitado = strtoupper(trim($_GET['alumno']));
    // Validar que la matricula solicitada existe en la sesion del tutor
    foreach ($_SESSION['alumnos'] as $alumno_item) {
        if ($alumno_item['matricula'] === $alumno_solicitado) {
            $_SESSION['alumno_matricula'] = $alumno_item['matricula'];
            $_SESSION['alumno_nombre'] = $alumno_item['nombre_completo'];
            break;
        }
    }
}

// Recogemos las variables de sesion (solo las que define login_padres.php)
$matricula_alumno = $_SESSION['alumno_matricula'] ?? '';
$nombre_alumno    = $_SESSION['alumno_nombre'] ?? 'Alumno';
$nombre_tutor     = $_SESSION['tutor_nombre'] ?? 'Tutor';
$alumnos_vinculados = $_SESSION['alumnos'] ?? [];

$asistencias = [];
$ultimo_movimiento = null;
$resumen_hoy = ['entradas' => 0, 'salidas' => 0];

try {
    // Consultamos el historial de asistencias del alumno
    // Campos reales de la tabla: id_asistencia, matricula_alumno, fecha, hora, tipo, sincronizado, registrado_en
    $sql = "SELECT id_asistencia, fecha, hora, tipo, registrado_en 
            FROM asistencias 
            WHERE matricula_alumno = :matricula 
            ORDER BY fecha DESC, hora DESC
            LIMIT 50";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['matricula' => $matricula_alumno]);
    $asistencias = $stmt->fetchAll();

    // Obtenemos el ultimo registro para determinar el estatus
    $ultimo_movimiento = !empty($asistencias) ? $asistencias[0] : null;

    // Resumen del dia de hoy
    $hoy = date('Y-m-d');
    foreach ($asistencias as $reg) {
        if ($reg['fecha'] === $hoy) {
            if ($reg['tipo'] === 'Entrada') $resumen_hoy['entradas']++;
            if ($reg['tipo'] === 'Salida') $resumen_hoy['salidas']++;
        }
    }

} catch (PDOException $e) {
    error_log("Error en padres.php: " . $e->getMessage());
    $asistencias = [];
}

// Logica de Estatus
$es_plantel = false;
$mensaje_estatus = "Fuera del plantel";

if ($ultimo_movimiento && $ultimo_movimiento['tipo'] === 'Entrada') {
    // Verificar que la entrada fue hoy para mostrar como "actualmente en plantel"
    if ($ultimo_movimiento['fecha'] === date('Y-m-d')) {
        $es_plantel = true;
        $mensaje_estatus = "En el plantel";
    }
}

// Agrupar asistencias por fecha para mejor visualizacion
$asistencias_por_fecha = [];
foreach ($asistencias as $reg) {
    $fecha_key = $reg['fecha'];
    if (!isset($asistencias_por_fecha[$fecha_key])) {
        $asistencias_por_fecha[$fecha_key] = [];
    }
    $asistencias_por_fecha[$fecha_key][] = $reg;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="Portal de seguimiento de asistencias para padres de familia COBAEV">
    <meta name="theme-color" content="#5c1931">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Portal de Padres - COBAEV</title>
    
    <!-- PWA Manifest -->
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="apple-touch-icon" href="logo.png">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Fuentes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Variable JavaScript para FCM -->
    <script>
        const MATRICULA_USUARIO = "<?php echo htmlspecialchars($matricula_alumno); ?>";
        const MATRICULAS_USUARIO = <?php echo json_encode(array_map(function($a) { return $a['matricula']; }, $alumnos_vinculados)); ?>;
        
        if (MATRICULA_USUARIO === "") {
            console.error("Error: La sesion no tiene matricula definida");
        } else {
            console.log("Matricula activa:", MATRICULA_USUARIO);
            console.log("Todas las matriculas:", MATRICULAS_USUARIO);
        }
    </script>
    
    <!-- Firebase y Service Worker -->
    <script type="module" src="app.js"></script>
    
    <style>
        .font-serif-elegant { font-family: 'Playfair Display', serif; }
        .font-sans-clean { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        .bg-crema { background-color: #f7f3eb; }
        .text-vino { color: #5c1931; }
        .bg-vino { background-color: #5c1931; }
        .border-vino { border-color: #5c1931; }
        .text-dorado { color: #a48253; }
        .bg-dorado { background-color: #a48253; }

        /* Smooth scrolling for mobile */
        html { scroll-behavior: smooth; }
        body { -webkit-tap-highlight-color: transparent; }
        
        /* Status pulse animation */
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(2.2); opacity: 0; }
        }
        .pulse-ring::before {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            animation: pulse-ring 2s ease-out infinite;
        }
        .pulse-ring-green::before { background: rgba(16, 185, 129, 0.3); }
        .pulse-ring-gray::before { background: rgba(161, 161, 170, 0.2); }

        /* Card hover for touch feedback */
        .touch-card {
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .touch-card:active {
            transform: scale(0.98);
        }

        /* Timeline connector */
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 19px;
            top: 44px;
            bottom: -12px;
            width: 2px;
            background: linear-gradient(to bottom, #e4e4e7, transparent);
        }
        .timeline-item:last-child::before {
            display: none;
        }

        /* Swipe hint animation */
        @keyframes swipe-hint {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(-4px); }
        }
    </style>
</head>
<body class="bg-crema font-sans-clean min-h-screen flex flex-col selection:bg-red-200">

    <!-- Header compacto estilo app movil -->
    <header class="bg-vino px-4 py-3 flex justify-between items-center sticky top-0 z-50 shadow-lg safe-area-top">
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-white/10 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
            </div>
            <div>
                <p class="text-white font-serif-elegant font-bold text-sm tracking-wide leading-none">SGA COBAEV</p>
                <p class="text-white/50 text-[9px] font-medium uppercase tracking-widest mt-0.5">Portal Padres</p>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            <!-- Boton de avisos/notificaciones -->
            <button onclick="toggleAvisos()" class="w-9 h-9 bg-white/10 hover:bg-white/20 rounded-lg flex items-center justify-center transition-colors active:scale-95 relative" title="Avisos" id="btn-avisos">
                <svg class="w-4 h-4 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <!-- Badge de avisos no leidos -->
                <span id="badge-avisos" class="hidden absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center shadow-sm">0</span>
            </button>
            <!-- Boton de opciones -->
            <button onclick="toggleMenu()" class="w-9 h-9 bg-white/10 hover:bg-white/20 rounded-lg flex items-center justify-center transition-colors active:scale-95 relative" title="Opciones">
                <svg class="w-4 h-4 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                </svg>
            </button>
        </div>

        <!-- Dropdown de avisos -->
        <div id="panel-avisos" class="hidden absolute top-16 right-4 bg-white rounded-xl shadow-xl border border-zinc-200 overflow-hidden z-[100] w-80 max-h-[70vh]">
            <div class="px-4 py-3 border-b border-zinc-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-zinc-700">Avisos</p>
                    <p class="text-[10px] text-zinc-400">Notificaciones del plantel</p>
                </div>
                <span id="avisos-count-header" class="text-[10px] font-bold text-zinc-400 uppercase"></span>
            </div>
            <div id="lista-avisos" class="overflow-y-auto max-h-[50vh]">
                <div class="p-6 text-center">
                    <div class="w-10 h-10 mx-auto bg-zinc-50 rounded-xl flex items-center justify-center mb-2">
                        <svg class="w-5 h-5 text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <p class="text-xs text-zinc-400">Cargando avisos...</p>
                </div>
            </div>
            <div class="px-4 py-3 border-t border-zinc-100 text-center">
                <a href="avisos_padres.php" class="text-xs font-semibold text-vino hover:underline">Ver todos los avisos</a>
            </div>
        </div>

        <!-- Menu desplegable -->
        <div id="menu-opciones" class="hidden absolute top-16 right-4 bg-white rounded-xl shadow-xl border border-zinc-200 overflow-hidden z-[100] w-56">
            <div class="py-1">
                <a href="perfil_tutor.php" class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-zinc-50 active:bg-zinc-100 transition-colors text-left">
                    <div class="w-8 h-8 bg-violet-50 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-zinc-700">Mi perfil</p>
                        <p class="text-[10px] text-zinc-400">Editar datos y contraseña</p>
                    </div>
                </a>
                <button onclick="resetServiceWorker()" class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-zinc-50 active:bg-zinc-100 transition-colors text-left">
                    <div class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-zinc-700">Actualizar sistema</p>
                        <p class="text-[10px] text-zinc-400">Resetear notificaciones</p>
                    </div>
                </button>
                <button onclick="reinstalarPWA()" class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-zinc-50 active:bg-zinc-100 transition-colors text-left">
                    <div class="w-8 h-8 bg-emerald-50 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-zinc-700">Instalar app</p>
                        <p class="text-[10px] text-zinc-400">Agregar a pantalla inicio</p>
                    </div>
                </button>
                <a href="ayuda_notificaciones.php" class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-zinc-50 active:bg-zinc-100 transition-colors text-left">
                    <div class="w-8 h-8 bg-amber-50 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-zinc-700">Configurar notificaciones</p>
                        <p class="text-[10px] text-zinc-400">Asegurar que lleguen alertas</p>
                    </div>
                </a>
                <div class="border-t border-zinc-100 my-1"></div>
                <a href="logout.php" class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-rose-50 active:bg-rose-100 transition-colors text-left">
                    <div class="w-8 h-8 bg-rose-50 rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-rose-600">Cerrar sesión</p>
                        <p class="text-[10px] text-zinc-400">Salir del portal</p>
                    </div>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow pb-6">

        <!-- Selector de alumno (solo si hay mas de 1 vinculado) -->
        <?php if (count($alumnos_vinculados) > 1): ?>
        <div class="px-4 pt-3 pb-1">
            <p class="text-[10px] text-zinc-400 font-bold uppercase tracking-wider mb-2">Alumno activo</p>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($alumnos_vinculados as $alumno_pill): ?>
                    <?php 
                        $es_activo = ($alumno_pill['matricula'] === $matricula_alumno);
                        // Obtener iniciales del nombre
                        $partes_nombre = explode(' ', $alumno_pill['nombre_completo']);
                        $inicial = mb_strtoupper(mb_substr($partes_nombre[0], 0, 1));
                    ?>
                    <a href="padres.php?alumno=<?php echo urlencode($alumno_pill['matricula']); ?>"
                       class="inline-flex items-center space-x-2 px-3 py-2 rounded-xl text-xs font-semibold transition-all active:scale-95
                              <?php echo $es_activo 
                                  ? 'bg-dorado text-white shadow-sm' 
                                  : 'bg-white border border-zinc-200 text-zinc-600 hover:border-zinc-300'; ?>">
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0
                                     <?php echo $es_activo ? 'bg-white/20 text-white' : 'bg-zinc-100 text-zinc-500'; ?>">
                            <?php echo $inicial; ?>
                        </span>
                        <span class="truncate max-w-[120px]"><?php echo htmlspecialchars($alumno_pill['nombre_completo']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Saludo y contexto -->
        <div class="px-4 pt-5 pb-3">
            <p class="text-xs text-zinc-400 font-medium">Bienvenido/a,</p>
            <h1 class="text-lg font-bold text-zinc-800 leading-tight"><?php echo htmlspecialchars($nombre_tutor); ?></h1>
        </div>

        <!-- Tarjeta de Estado Principal -->
        <div class="px-4 mb-4">
            <div class="touch-card bg-white rounded-2xl shadow-sm border border-zinc-100 overflow-hidden">
                <!-- Cabecera con datos del alumno -->
                <div class="px-5 pt-5 pb-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-11 h-11 rounded-full bg-gradient-to-br from-[#5c1931] to-[#a48253] flex items-center justify-center shadow-sm">
                                <span class="text-white font-bold text-sm">
                                    <?php echo mb_strtoupper(mb_substr($nombre_alumno, 0, 1)); ?>
                                </span>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-zinc-800 leading-tight"><?php echo htmlspecialchars($nombre_alumno); ?></h2>
                                <p class="text-[11px] text-zinc-400 font-mono mt-0.5"><?php echo htmlspecialchars($matricula_alumno); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Estado actual - zona destacada -->
                <div class="mx-4 mb-4 rounded-xl p-4 <?php echo $es_plantel ? 'bg-emerald-50 border border-emerald-100' : 'bg-zinc-50 border border-zinc-100'; ?>" data-status-container>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <!-- Indicador con pulso -->
                            <div class="relative flex items-center justify-center w-10 h-10">
                                <div class="pulse-ring <?php echo $es_plantel ? 'pulse-ring-green' : 'pulse-ring-gray'; ?> absolute inset-0 rounded-full"></div>
                                <div data-status-dot class="w-4 h-4 rounded-full <?php echo $es_plantel ? 'bg-emerald-500' : 'bg-zinc-300'; ?> relative z-10 shadow-sm"></div>
                            </div>
                            <div>
                                <p data-status-text class="text-xs font-bold <?php echo $es_plantel ? 'text-emerald-800' : 'text-zinc-600'; ?> uppercase tracking-wide">
                                    <?php echo $mensaje_estatus; ?>
                                </p>
                                <?php if ($ultimo_movimiento): ?>
                                    <p data-status-detail class="text-[11px] <?php echo $es_plantel ? 'text-emerald-600' : 'text-zinc-400'; ?> mt-0.5">
                                        <?php 
                                            $hora_formato = date('h:i A', strtotime($ultimo_movimiento['hora']));
                                            echo ($ultimo_movimiento['tipo'] === 'Entrada' ? 'Ingreso' : 'Salida') . " a las {$hora_formato}";
                                        ?>
                                    </p>
                                <?php else: ?>
                                    <p data-status-detail class="text-[11px] text-zinc-400 mt-0.5">Sin registros</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Icono de tipo -->
                        <?php if ($ultimo_movimiento): ?>
                        <div class="w-9 h-9 rounded-lg <?php echo $es_plantel ? 'bg-emerald-100' : 'bg-zinc-100'; ?> flex items-center justify-center">
                            <?php if ($es_plantel): ?>
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            <?php else: ?>
                                <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"></path>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Resumen del dia -->
                <div class="px-5 pb-5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="text-center">
                                <p data-count-entradas class="text-lg font-bold text-vino leading-none"><?php echo $resumen_hoy['entradas']; ?></p>
                                <p class="text-[9px] text-zinc-400 font-medium uppercase tracking-wider mt-1">Entradas hoy</p>
                            </div>
                            <div class="w-px h-8 bg-zinc-100"></div>
                            <div class="text-center">
                                <p data-count-salidas class="text-lg font-bold text-vino leading-none"><?php echo $resumen_hoy['salidas']; ?></p>
                                <p class="text-[9px] text-zinc-400 font-medium uppercase tracking-wider mt-1">Salidas hoy</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-[10px] text-zinc-400 font-medium"><?php echo date('d \d\e M, Y'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historial de Accesos -->
        <div class="px-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center space-x-2">
                    <div class="w-1 h-4 bg-dorado rounded-full"></div>
                    <h3 class="text-xs font-bold tracking-wide text-zinc-700 uppercase">Historial Reciente</h3>
                </div>
                <span class="text-[10px] text-zinc-400 font-medium"><?php echo count($asistencias); ?> registros</span>
            </div>

            <?php if (empty($asistencias)): ?>
                <!-- Estado vacio -->
                <div class="bg-white rounded-2xl border border-zinc-100 shadow-sm p-8 text-center">
                    <div class="w-16 h-16 mx-auto bg-zinc-50 rounded-2xl flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-zinc-500">Sin registros de acceso</p>
                    <p class="text-xs text-zinc-400 mt-1.5 max-w-[200px] mx-auto leading-relaxed">
                        Los movimientos de entrada y salida apareceran aqui automaticamente
                    </p>
                </div>
            <?php else: ?>
                <!-- Lista agrupada por fecha -->
                <div class="space-y-4">
                    <?php foreach ($asistencias_por_fecha as $fecha_grupo => $registros_dia): ?>
                        <!-- Separador de fecha -->
                        <div class="flex items-center space-x-3">
                            <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider whitespace-nowrap">
                                <?php 
                                    $hoy = date('Y-m-d');
                                    $ayer = date('Y-m-d', strtotime('-1 day'));
                                    if ($fecha_grupo === $hoy) {
                                        echo 'Hoy';
                                    } elseif ($fecha_grupo === $ayer) {
                                        echo 'Ayer';
                                    } else {
                                        // Formato: "Lun 16 Jun"
                                        $dias = ['Dom','Lun','Mar','Mie','Jue','Vie','Sab'];
                                        $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
                                        $ts = strtotime($fecha_grupo);
                                        echo $dias[date('w', $ts)] . ' ' . date('d', $ts) . ' ' . $meses[date('n', $ts) - 1];
                                    }
                                ?>
                            </span>
                            <div class="h-px bg-zinc-100 flex-grow"></div>
                        </div>

                        <!-- Registros del dia -->
                        <div class="bg-white rounded-2xl border border-zinc-100 shadow-sm overflow-hidden">
                            <?php foreach ($registros_dia as $idx => $registro): ?>
                                <div class="flex items-center px-4 py-3 <?php echo $idx > 0 ? 'border-t border-zinc-50' : ''; ?>">
                                    <!-- Icono -->
                                    <?php if ($registro['tipo'] === 'Entrada'): ?>
                                        <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                            </svg>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-9 h-9 rounded-xl bg-rose-50 flex items-center justify-center flex-shrink-0">
                                            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
                                            </svg>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Info -->
                                    <div class="ml-3 flex-grow min-w-0">
                                        <p class="text-xs font-semibold text-zinc-700">
                                            <?php echo $registro['tipo'] === 'Entrada' ? 'Entrada al plantel' : 'Salida del plantel'; ?>
                                        </p>
                                        <p class="text-[10px] text-zinc-400 mt-0.5">
                                            Registrado el <?php echo date('d/m/Y', strtotime($registro['fecha'])); ?>
                                        </p>
                                    </div>

                                    <!-- Hora -->
                                    <div class="text-right flex-shrink-0 ml-2">
                                        <p class="text-xs font-bold text-vino font-mono">
                                            <?php echo date('h:i', strtotime($registro['hora'])); ?>
                                        </p>
                                        <p class="text-[9px] text-zinc-400 font-medium uppercase">
                                            <?php echo date('A', strtotime($registro['hora'])); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Footer minimalista -->
    <footer class="px-4 py-4 text-center flex-shrink-0 border-t border-zinc-100 bg-white/50">
        <p class="text-[9px] text-zinc-400 uppercase tracking-widest">
            COBAEV &bull; Sistema de Alertas de Acceso
        </p>
        <p class="text-[9px] text-zinc-300 mt-0.5">
            Sesion: <?php echo htmlspecialchars($nombre_tutor); ?>
        </p>
    </footer>

    <!-- Scripts de funcionalidad -->
    <script>
        // Cerrar menu al hacer click fuera
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('menu-opciones');
            const boton = e.target.closest('[onclick="toggleMenu()"]');
            if (!boton && !menu.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });

        // Cerrar avisos al abrir menu y viceversa
        function toggleMenu() {
            const menu = document.getElementById('menu-opciones');
            const panel = document.getElementById('panel-avisos');
            panel.classList.add('hidden');
            menu.classList.toggle('hidden');
        }

        // Reset del Service Worker + verificar/registrar token
        async function resetServiceWorker() {
            const menu = document.getElementById('menu-opciones');
            menu.classList.add('hidden');

            if (!confirm('¿Actualizar el sistema de notificaciones?\n\nEsto verificará que su dispositivo esté correctamente registrado para recibir alertas.')) return;

            try {
                // 1. Desregistrar Service Workers
                const registrations = await navigator.serviceWorker.getRegistrations();
                for (const reg of registrations) {
                    await reg.unregister();
                }

                // 2. Limpiar cachés
                const cacheNames = await caches.keys();
                for (const name of cacheNames) {
                    await caches.delete(name);
                }

                // 3. Limpiar localStorage del token para forzar re-registro
                localStorage.removeItem('fcm_token_enviado');
                localStorage.removeItem('fcm_matriculas');
                localStorage.removeItem('fcm_matricula');

                // 4. Re-registrar Service Worker
                const reg = await navigator.serviceWorker.register('sw.js', { updateViaCache: 'none' });
                console.log('✅ SW re-registrado');

                // 5. Esperar a que esté listo y obtener token FCM
                const registration = await navigator.serviceWorker.ready;
                
                const { initializeApp } = await import("https://www.gstatic.com/firebasejs/9.22.0/firebase-app.js");
                const { getMessaging, getToken } = await import("https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging.js");
                
                const app = initializeApp({
                    apiKey: "AIzaSyBirHLalVvjvEiSVxCPFmSEJChNCfTDjuY",
                    authDomain: "sga-cobaev.firebaseapp.com",
                    projectId: "sga-cobaev",
                    storageBucket: "sga-cobaev.firebasestorage.app",
                    messagingSenderId: "263139601974",
                    appId: "1:263139601974:web:5fba5cdd72c3b084eff297"
                }, 'reset-app');
                
                const messaging = getMessaging(app);
                const currentToken = await getToken(messaging, {
                    vapidKey: 'BJEY0s0K7FhBubLpEYmvdnxm-3Z0PsHwij0ipGlHyQYw7VvL_3knSrUOFhn5OIJXHSIwTQcvogFO_N-oJj-Q6DU',
                    serviceWorkerRegistration: registration
                });

                if (currentToken) {
                    // 6. Enviar token al servidor
                    const matriculasArray = (typeof MATRICULAS_USUARIO !== 'undefined' && Array.isArray(MATRICULAS_USUARIO))
                        ? MATRICULAS_USUARIO : [MATRICULA_USUARIO];

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
                    
                    if (data.success) {
                        localStorage.setItem('fcm_token_enviado', currentToken);
                        localStorage.setItem('fcm_matriculas', JSON.stringify(matriculasArray.sort()));
                        alert('✅ Sistema actualizado correctamente.\n\nSu dispositivo está registrado para recibir notificaciones.');
                    } else {
                        alert('⚠️ Sistema actualizado pero hubo un problema al registrar el token.\n\nIntente de nuevo.');
                    }
                } else {
                    alert('⚠️ No se pudo obtener token de notificaciones.\n\nVerifique que los permisos estén activados.');
                }

                window.location.reload();
            } catch (error) {
                console.error('Error en reset:', error);
                alert('Error al actualizar: ' + error.message + '\n\nLa página se recargará.');
                window.location.reload();
            }
        }

        // Instalar PWA
        let deferredPrompt = null;

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
        });

        function reinstalarPWA() {
            const menu = document.getElementById('menu-opciones');
            menu.classList.add('hidden');

            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(choice => {
                    if (choice.outcome === 'accepted') {
                        alert('¡Aplicación instalada correctamente!');
                    }
                    deferredPrompt = null;
                });
            } else {
                alert('Para instalar la app:\n\n• Android: Menú ⋮ → "Instalar aplicación"\n• iPhone: Compartir → "Agregar a pantalla de inicio"\n• PC: Icono ➕ en la barra de direcciones');
            }
        }

        // ========== SISTEMA DE AVISOS ==========

        // Toggle panel de avisos
        function toggleAvisos() {
            const panel = document.getElementById('panel-avisos');
            const menu = document.getElementById('menu-opciones');
            // Cerrar menu si esta abierto
            menu.classList.add('hidden');
            panel.classList.toggle('hidden');
            if (!panel.classList.contains('hidden')) {
                cargarAvisos();
            }
        }

        // Cargar avisos desde API
        async function cargarAvisos() {
            try {
                const response = await fetch('api/obtener_avisos.php');
                const data = await response.json();

                if (data.success && data.avisos) {
                    actualizarBadge(data.total);
                    renderAvisos(data.avisos);
                } else {
                    renderAvisosVacio();
                }
            } catch (error) {
                console.error('Error al cargar avisos:', error);
                renderAvisosError();
            }
        }

        // Actualizar badge de notificaciones
        function actualizarBadge(total) {
            const badge = document.getElementById('badge-avisos');
            if (total > 0) {
                badge.textContent = total > 9 ? '9+' : total;
                badge.classList.remove('hidden');
                badge.classList.add('flex');
            } else {
                badge.classList.add('hidden');
                badge.classList.remove('flex');
            }
            const countHeader = document.getElementById('avisos-count-header');
            countHeader.textContent = total > 0 ? total + ' nuevo(s)' : 'Sin nuevos';
        }

        // Render avisos en panel
        function renderAvisos(avisos) {
            const lista = document.getElementById('lista-avisos');
            if (avisos.length === 0) {
                renderAvisosVacio();
                return;
            }

            let html = '';
            avisos.forEach(aviso => {
                const fecha = new Date(aviso.fecha_envio);
                const fechaStr = fecha.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

                html += '<div class="px-4 py-3 border-b border-zinc-50 hover:bg-zinc-50 transition-colors" id="aviso-' + aviso.id_aviso + '">';
                html += '  <div class="flex items-start justify-between">';
                html += '    <div class="flex-1 min-w-0 mr-2">';
                html += '      <p class="text-xs font-bold text-zinc-700 truncate">' + escapeHtml(aviso.titulo) + '</p>';
                html += '      <p class="text-[9px] text-zinc-400 mt-1 uppercase tracking-wider">' + fechaStr + '</p>';
                html += '    </div>';
                html += '    <div class="flex-shrink-0 mt-0.5 w-2 h-2 bg-red-400 rounded-full"></div>';
                html += '  </div>';
                html += '</div>';
            });
            lista.innerHTML = html;
        }

        // Avisos vacio
        function renderAvisosVacio() {
            const lista = document.getElementById('lista-avisos');
            lista.innerHTML = '<div class="p-6 text-center">' +
                '<div class="w-10 h-10 mx-auto bg-emerald-50 rounded-xl flex items-center justify-center mb-2">' +
                '<svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' +
                '</div>' +
                '<p class="text-xs font-semibold text-zinc-500">Sin avisos pendientes</p>' +
                '<p class="text-[10px] text-zinc-400 mt-0.5">Estas al dia</p>' +
                '</div>';
        }

        // Error al cargar
        function renderAvisosError() {
            const lista = document.getElementById('lista-avisos');
            lista.innerHTML = '<div class="p-6 text-center">' +
                '<p class="text-xs text-zinc-400">Error al cargar avisos</p>' +
                '</div>';
        }

        // Marcar aviso como leido
        async function marcarLeido(idAviso) {
            try {
                const response = await fetch('api/marcar_leido.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_aviso: idAviso })
                });
                const data = await response.json();
                if (data.success) {
                    // Remover el aviso del panel con animacion
                    const elem = document.getElementById('aviso-' + idAviso);
                    if (elem) {
                        elem.style.opacity = '0';
                        elem.style.transform = 'translateX(20px)';
                        elem.style.transition = 'all 0.3s ease';
                        setTimeout(() => {
                            elem.remove();
                            // Recargar para actualizar badge
                            cargarAvisos();
                        }, 300);
                    }
                }
            } catch (error) {
                console.error('Error al marcar leido:', error);
            }
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Cerrar panel de avisos al hacer click fuera
        document.addEventListener('click', function(e) {
            const panel = document.getElementById('panel-avisos');
            const btnAvisos = document.getElementById('btn-avisos');
            if (!panel.contains(e.target) && !btnAvisos.contains(e.target)) {
                panel.classList.add('hidden');
            }
        });

        // Cargar avisos al iniciar la pagina
        document.addEventListener('DOMContentLoaded', function() {
            cargarAvisos();
            // Actualizar avisos cada 60 segundos
            setInterval(cargarAvisos, 60000);
            
            // Auto-refresh de asistencias cada 30 segundos
            setInterval(refrescarAsistencias, 30000);
        });

        // ========== AUTO-REFRESH DE ASISTENCIAS ==========
        async function refrescarAsistencias() {
            try {
                const response = await fetch('api/obtener_asistencias.php');
                if (!response.ok) return;
                const data = await response.json();
                if (!data.success) return;

                // Actualizar estado (en plantel / fuera)
                const statusContainer = document.querySelector('[data-status-container]');
                if (statusContainer) {
                    const indicador = statusContainer.querySelector('[data-status-dot]');
                    const texto = statusContainer.querySelector('[data-status-text]');
                    const detalle = statusContainer.querySelector('[data-status-detail]');
                    
                    if (data.es_plantel) {
                        if (indicador) indicador.className = 'w-4 h-4 rounded-full bg-emerald-500 relative z-10 shadow-sm';
                        if (texto) { texto.textContent = 'En el plantel'; texto.className = 'text-xs font-bold text-emerald-800 uppercase tracking-wide'; }
                        if (detalle && data.ultimo_movimiento) {
                            const hora = new Date('2000-01-01T' + data.ultimo_movimiento.hora).toLocaleTimeString('es-MX', {hour: '2-digit', minute: '2-digit', hour12: true});
                            detalle.textContent = 'Ingreso a las ' + hora;
                            detalle.className = 'text-[11px] text-emerald-600 mt-0.5';
                        }
                    } else {
                        if (indicador) indicador.className = 'w-4 h-4 rounded-full bg-zinc-300 relative z-10 shadow-sm';
                        if (texto) { texto.textContent = 'Fuera del plantel'; texto.className = 'text-xs font-bold text-zinc-600 uppercase tracking-wide'; }
                        if (detalle && data.ultimo_movimiento) {
                            const hora = new Date('2000-01-01T' + data.ultimo_movimiento.hora).toLocaleTimeString('es-MX', {hour: '2-digit', minute: '2-digit', hour12: true});
                            detalle.textContent = 'Salida a las ' + hora;
                            detalle.className = 'text-[11px] text-zinc-400 mt-0.5';
                        }
                    }
                }

                // Actualizar contadores del día
                const contEntradas = document.querySelector('[data-count-entradas]');
                const contSalidas = document.querySelector('[data-count-salidas]');
                if (contEntradas) contEntradas.textContent = data.resumen_hoy.entradas;
                if (contSalidas) contSalidas.textContent = data.resumen_hoy.salidas;

            } catch (error) {
                // Silenciar errores de red para no molestar al usuario
                console.log('Auto-refresh: sin conexión');
            }
        }
    </script>

</body>
</html>
<?php ob_end_flush(); ?>
