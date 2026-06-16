<?php
/**
 * Portal de Padres - COBAEV
 * Dashboard de seguimiento de asistencias en tiempo real
 */

// Aseguramos el búfer de salida para evitar errores de redirección
ob_start();

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Iniciamos la sesión de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Protegemos la página: verificar autenticación
if (!isset($_SESSION['tutor_autenticado']) || $_SESSION['tutor_autenticado'] !== true) {
    header("Location: login_padres_mejorado.php");
    exit;
}

// Verificar timeout de sesión (30 minutos de inactividad)
if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login_padres_mejorado.php?timeout=1");
    exit;
}
$_SESSION['ultima_actividad'] = time();

// Traemos la conexión PDO
require_once 'conexion.php';

// Recogemos las variables de sesión
$matricula_alumno = $_SESSION['alumno_matricula'] ?? '';
$nombre_alumno    = $_SESSION['alumno_nombre'] ?? 'Alumno';
$nombre_tutor     = $_SESSION['tutor_nombre'] ?? 'Tutor';
$grupo_alumno     = $_SESSION['alumno_grupo'] ?? 'N/A';

$asistencias = [];
$ultimo_movimiento = null;

try {
    // Consultamos el historial de asistencias del alumno
    $sql = "SELECT fecha, hora, tipo, metodo_registro 
            FROM asistencias 
            WHERE matricula_alumno = :matricula 
            ORDER BY fecha DESC, hora DESC
            LIMIT 50";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['matricula' => $matricula_alumno]);
    $asistencias = $stmt->fetchAll();

    // Obtenemos el último registro para determinar el estatus
    $ultimo_movimiento = !empty($asistencias) ? $asistencias[0] : null;

} catch (PDOException $e) {
    error_log("Error en padres.php: " . $e->getMessage());
    $asistencias = [];
}

// Lógica de Estatus
$es_plantel = false;
$mensaje_estatus = "Fuera de plantel";

if ($ultimo_movimiento && $ultimo_movimiento['tipo'] === 'Entrada') {
    $es_plantel = true;
    $mensaje_estatus = "En plantel";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Portal de seguimiento de asistencias para padres de familia COBAEV">
    <meta name="theme-color" content="#5c1931">
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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Variable JavaScript para FCM -->
    <script>
        const MATRICULA_USUARIO = "<?php echo htmlspecialchars($matricula_alumno); ?>";
        
        if (MATRICULA_USUARIO === "") {
            console.error("⚠️ Error: La sesión no tiene matrícula definida");
        } else {
            console.log("✅ Matrícula cargada:", MATRICULA_USUARIO);
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
    </style>
</head>
<body class="bg-crema font-sans-clean min-h-screen flex flex-col justify-between selection:bg-red-200">

    <!-- Header -->
    <header class="bg-white border-b border-zinc-200 px-4 py-3 flex justify-between items-center sticky top-0 z-50 shadow-sm flex-shrink-0">
        <div class="flex items-center space-x-2">
            <a href="index.html" class="text-zinc-400 hover:text-vino transition-colors" title="Volver al inicio">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div>
                <span class="text-vino font-serif-elegant font-bold tracking-wider text-base">PORTAL DE PADRES</span>
                <span class="text-zinc-300">|</span>
                <span class="text-dorado font-serif-elegant italic text-sm">SGA COBAEV</span>
            </div>
        </div>

        <a href="logout.php" class="text-zinc-400 hover:text-rose-600 transition-colors" title="Cerrar sesión">
            <div class="flex flex-col items-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                <p class="text-[9px] font-bold uppercase">Salir</p>
            </div>
        </a>
    </header>

    <!-- Main Content -->
    <main class="flex-grow p-4 space-y-5 max-w-md w-full mx-auto overflow-y-auto">
        
        <!-- Tarjeta de Información del Alumno -->
        <div class="bg-white border border-zinc-200/80 rounded-2xl p-5 shadow-sm text-center space-y-4">
            <div class="space-y-1">
                <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-widest">Alumno Monitoreado</p>
                <h2 class="text-lg font-bold text-zinc-800 leading-tight"><?php echo htmlspecialchars($nombre_alumno); ?></h2>
                <p class="text-xs text-zinc-500 font-mono">
                    <?php echo htmlspecialchars($matricula_alumno); ?> • Grupo: <?php echo htmlspecialchars($grupo_alumno); ?>
                </p>
            </div>

            <div class="flex items-center space-x-3 justify-center max-w-xs mx-auto">
                <div class="h-[1px] bg-zinc-200 flex-grow"></div>
                <span class="text-[9px] text-zinc-300">❖</span>
                <div class="h-[1px] bg-zinc-200 flex-grow"></div>
            </div>

            <!-- Estado en Tiempo Real -->
            <div class="py-1">
                <p class="text-[9px] font-bold text-zinc-400 uppercase tracking-widest mb-1">Estado en Tiempo Real</p>
                
                <div class="text-3xl font-serif-elegant font-bold tracking-wide <?php echo $es_plantel ? 'text-emerald-700' : 'text-zinc-600'; ?>">
                    <?php echo $mensaje_estatus; ?>
                </div>
                
                <?php if ($ultimo_movimiento): ?>
                    <p class="text-[10px] <?php echo $es_plantel ? 'text-emerald-600' : 'text-zinc-400'; ?> font-medium mt-1 flex items-center justify-center gap-1">
                        <?php if ($es_plantel): ?>
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <?php endif; ?>
                        <?php 
                            $fecha_formato = date('d/m/Y', strtotime($ultimo_movimiento['fecha']));
                            $hora_formato = date('h:i A', strtotime($ultimo_movimiento['hora']));
                            echo $es_plantel ? "Ingreso registrado" : "Última salida";
                            echo " el {$fecha_formato} a las {$hora_formato}";
                        ?>
                    </p>
                <?php else: ?>
                    <p class="text-[10px] text-zinc-400 font-medium mt-1">
                        Sin registros de asistencia
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historial de Accesos -->
        <div class="space-y-3">
            <div class="flex items-center space-x-2 px-1">
                <span class="h-[1px] w-3 bg-dorado"></span>
                <p class="text-[10px] font-bold tracking-widest text-dorado uppercase">Historial de Accesos</p>
            </div>

            <div class="bg-white border border-zinc-200/80 rounded-2xl p-5 shadow-sm space-y-6 relative overflow-hidden">
                
                <?php if (!empty($asistencias)): ?>
                    <div class="absolute top-8 bottom-8 left-9 w-[1px] bg-zinc-200"></div>
                <?php endif; ?>

                <?php if (empty($asistencias)): ?>
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 mx-auto text-zinc-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <p class="text-sm text-zinc-400 font-medium">Aún no hay registros de acceso</p>
                        <p class="text-xs text-zinc-400 mt-1">Los movimientos aparecerán aquí automáticamente</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($asistencias as $registro): ?>  
                            <div class="flex items-start space-x-4 relative z-10">
                                <?php if ($registro['tipo'] == 'Entrada'): ?>
                                    <div class="w-9 h-9 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center flex-shrink-0 shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                        </svg>
                                    </div>
                                <?php else: ?>
                                    <div class="w-9 h-9 rounded-full bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center flex-shrink-0 shadow-sm">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex-grow pt-0.5">
                                    <div class="flex justify-between items-baseline">
                                        <h4 class="text-xs font-bold text-zinc-800 uppercase tracking-wide">
                                            <?php echo htmlspecialchars($registro['tipo']); ?>
                                        </h4>
                                        <p class="text-[11px] text-zinc-500">
                                            <?php echo date('d/m/Y', strtotime($registro['fecha'])); ?>
                                        </p>
                                    </div>
                                    <p class="text-xs font-mono font-bold text-vino mt-1">
                                        <?php echo date('h:i A', strtotime($registro['hora'])); ?>
                                    </p>
                                    <?php if (isset($registro['metodo_registro'])): ?>
                                        <p class="text-[10px] text-zinc-400 mt-0.5">
                                            Método: <?php echo htmlspecialchars($registro['metodo_registro']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="p-4 text-center text-[9px] text-zinc-400 uppercase tracking-widest bg-white border-t border-zinc-100 flex-shrink-0">
        COBAEV • Sistema de Alertas de Acceso
        <br>
        <span class="text-zinc-300">Sesión iniciada como: <?php echo htmlspecialchars($nombre_tutor); ?></span>
    </footer>

</body>
</html>
<?php ob_end_flush(); ?>
