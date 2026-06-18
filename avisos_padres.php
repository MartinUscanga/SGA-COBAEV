<?php
/**
 * Avisos de Padres - COBAEV
 * Pagina dedicada para ver todos los avisos (leidos y no leidos)
 * Mobile-first UI/UX
 */

// Aseguramos el bufer de salida para evitar errores de redireccion
ob_start();

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Iniciamos la sesion de forma segura
if (session_status() === PHP_SESSION_NONE) {
    // Cookie de sesion con duracion larga (30 dias) para PWA
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

// Variables de sesion
$matricula = $_SESSION['alumno_matricula'] ?? '';
$nombre_tutor = $_SESSION['tutor_nombre'] ?? 'Tutor';

$avisos = [];

try {
    $sql = "SELECT a.id_aviso, a.titulo, a.mensaje, a.fecha_envio, a.destinatario,
                   CASE WHEN ar.id IS NOT NULL THEN 1 ELSE 0 END AS leido
            FROM avisos a
            INNER JOIN alumnos al ON al.matricula = :matricula
            LEFT JOIN avisos_leidos ar ON ar.id_aviso = a.id_aviso AND ar.matricula_alumno = :matricula2
            WHERE (a.destinatario = 'todos' OR a.destinatario = :matricula3 OR a.destinatario = CONCAT('grupo:', al.grupo))
            ORDER BY a.fecha_envio DESC
            LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'matricula' => $matricula,
        'matricula2' => $matricula,
        'matricula3' => $matricula
    ]);
    $avisos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error en avisos_padres.php: " . $e->getMessage());
    $avisos = [];
}

$total_no_leidos = 0;
foreach ($avisos as $aviso) {
    if (!$aviso['leido']) {
        $total_no_leidos++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="Avisos del plantel - Portal de Padres COBAEV">
    <meta name="theme-color" content="#5c1931">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Mis Avisos - COBAEV</title>

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
<body class="bg-crema font-sans-clean min-h-screen flex flex-col">

    <!-- Header sticky -->
    <header class="bg-vino px-4 py-3 flex items-center sticky top-0 z-50 shadow-lg">
        <a href="padres.php" class="w-9 h-9 bg-white/10 hover:bg-white/20 rounded-lg flex items-center justify-center transition-colors active:scale-95 mr-3">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <div class="flex-1">
            <h1 class="text-white font-serif-elegant font-bold text-sm tracking-wide leading-none">Mis Avisos</h1>
            <p class="text-white/50 text-[9px] font-medium uppercase tracking-widest mt-0.5">Notificaciones del plantel</p>
        </div>
        <?php if ($total_no_leidos > 0): ?>
        <span class="w-6 h-6 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center shadow-sm">
            <?php echo $total_no_leidos > 9 ? '9+' : $total_no_leidos; ?>
        </span>
        <?php endif; ?>
    </header>

    <!-- Main content -->
    <main class="flex-grow px-4 py-4">
        <?php if (empty($avisos)): ?>
            <!-- Empty state -->
            <div class="bg-white rounded-2xl border border-zinc-100 shadow-sm p-8 text-center mt-4">
                <div class="w-16 h-16 mx-auto bg-zinc-50 rounded-2xl flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-zinc-500">Sin avisos</p>
                <p class="text-xs text-zinc-400 mt-1.5 max-w-[220px] mx-auto leading-relaxed">
                    No hay avisos del plantel por el momento. Vuelve mas tarde.
                </p>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($avisos as $aviso): ?>
                    <?php
                        $es_leido = (int)$aviso['leido'] === 1;
                        $fecha_formato = date('d M Y, h:i A', strtotime($aviso['fecha_envio']));
                    ?>
                    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden <?php echo $es_leido ? 'border-zinc-100 opacity-60' : 'border-zinc-200'; ?>" id="aviso-card-<?php echo $aviso['id_aviso']; ?>">
                        <div class="px-4 py-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-sm text-zinc-800 leading-tight"><?php echo htmlspecialchars($aviso['titulo']); ?></p>
                                    <p class="text-xs text-zinc-600 mt-2 leading-relaxed"><?php echo nl2br(htmlspecialchars($aviso['mensaje'])); ?></p>
                                    <p class="text-[10px] text-zinc-400 mt-2 uppercase tracking-wider"><?php echo $fecha_formato; ?></p>
                                </div>
                                <?php if ($es_leido): ?>
                                    <span class="flex-shrink-0 inline-flex items-center px-2 py-1 rounded-lg bg-zinc-100 text-[10px] font-semibold text-zinc-400 uppercase tracking-wide">
                                        Leido
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!$es_leido): ?>
                                <div class="mt-3 pt-3 border-t border-zinc-100">
                                    <button onclick="marcarLeido(<?php echo $aviso['id_aviso']; ?>)" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-semibold transition-colors active:scale-95">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                        <span>Marcar como leido</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <footer class="px-4 py-4 text-center flex-shrink-0 border-t border-zinc-100 bg-white/50">
        <p class="text-[9px] text-zinc-400 uppercase tracking-widest">
            COBAEV &bull; Sistema de Alertas de Acceso
        </p>
    </footer>

    <script>
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
                    const card = document.getElementById('aviso-card-' + idAviso);
                    if (card) {
                        // Fade the card and replace button with "Leido" badge
                        card.classList.add('opacity-60');
                        card.classList.remove('border-zinc-200');
                        card.classList.add('border-zinc-100');
                        // Remove button area
                        const btnArea = card.querySelector('.border-t.border-zinc-100');
                        if (btnArea) {
                            btnArea.remove();
                        }
                        // Add "Leido" badge
                        const titleRow = card.querySelector('.flex.items-start');
                        if (titleRow) {
                            const badge = document.createElement('span');
                            badge.className = 'flex-shrink-0 inline-flex items-center px-2 py-1 rounded-lg bg-zinc-100 text-[10px] font-semibold text-zinc-400 uppercase tracking-wide';
                            badge.textContent = 'Leido';
                            titleRow.appendChild(badge);
                        }
                    }
                }
            } catch (error) {
                console.error('Error al marcar leido:', error);
            }
        }
    </script>

</body>
</html>
<?php ob_end_flush(); ?>
