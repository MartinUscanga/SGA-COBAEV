<?php
/**
 * Detalle de aviso individual - Portal de Padres
 * SGA COBAEV
 */
require_once 'includes/session_padres.php';
verificarSesionTutor();
require_once 'conexion.php';

$id_aviso = intval($_GET['id'] ?? 0);
$matricula = $_SESSION['alumno_matricula'] ?? '';
$matricula_tutor = $_SESSION['tutor_matricula'] ?? $_SESSION['alumno_matricula'] ?? '';

if ($id_aviso <= 0) {
    header('Location: avisos_padres.php');
    exit;
}

// Cargar el aviso
try {
    $stmt = $pdo->prepare("
        SELECT id_aviso, titulo, contenido, mensaje, categoria, prioridad, 
               archivo_adjunto, fecha_publicacion, destinatario
        FROM avisos 
        WHERE id_aviso = :id_aviso 
        AND activo = 1
        AND (destinatario = 'todos' OR destinatario = :matricula)
    ");
    $stmt->execute(['id_aviso' => $id_aviso, 'matricula' => $matricula]);
    $aviso = $stmt->fetch();

    if (!$aviso) {
        header('Location: avisos_padres.php');
        exit;
    }

    // Marcar como leido automaticamente
    $stmt_leido = $pdo->prepare("
        INSERT IGNORE INTO avisos_leidos (id_aviso, matricula_tutor) 
        VALUES (:id_aviso, :matricula_tutor)
    ");
    $stmt_leido->execute([
        'id_aviso' => $id_aviso,
        'matricula_tutor' => $matricula_tutor
    ]);

} catch (PDOException $e) {
    error_log('SGA Error avisos_detalle: ' . $e->getMessage());
    header('Location: avisos_padres.php');
    exit;
}

// Preparar datos para la vista
$titulo = htmlspecialchars($aviso['titulo'] ?? '', ENT_QUOTES, 'UTF-8');
$contenido = nl2br(htmlspecialchars($aviso['contenido'] ?? $aviso['mensaje'] ?? '', ENT_QUOTES, 'UTF-8'));
$categoria = $aviso['categoria'] ?? 'institucional';
$prioridad = $aviso['prioridad'] ?? 'normal';
$archivo = $aviso['archivo_adjunto'] ?? '';
$fecha_pub = $aviso['fecha_publicacion'] ?? '';

// Colores por categoria
$coloresCat = [
    'institucional' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'label' => 'Institucional'],
    'academico' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'label' => 'Academico'],
    'emergencia' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'label' => 'Emergencia'],
    'pagos' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'label' => 'Pagos'],
    'cultural' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'label' => 'Cultural']
];
$catInfo = $coloresCat[$categoria] ?? $coloresCat['institucional'];

// Colores por prioridad
$coloresPri = [
    'urgente' => 'bg-red-500 text-white',
    'importante' => 'bg-amber-500 text-white',
    'normal' => 'bg-zinc-200 text-zinc-600'
];
$priColor = $coloresPri[$prioridad] ?? $coloresPri['normal'];

// Iconos por categoria
$iconosCat = [
    'institucional' => "\xF0\x9F\x8F\xAB",
    'academico' => "\xF0\x9F\x93\x9A",
    'emergencia' => "\xF0\x9F\x9A\xA8",
    'pagos' => "\xF0\x9F\x92\xB0",
    'cultural' => "\xF0\x9F\x8E\xAD"
];
$icono = $iconosCat[$categoria] ?? "\xF0\x9F\x93\xA2";

// Fecha formateada
$fechaFormateada = '';
if ($fecha_pub) {
    $dt = new DateTime($fecha_pub);
    $fechaFormateada = $dt->format('d/m/Y H:i');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?> - SGA COBAEV</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-zinc-50 min-h-screen">
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-gradient-to-r from-violet-600 to-purple-700 text-white shadow-lg">
        <div class="max-w-lg mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="avisos_padres.php" class="w-9 h-9 bg-white/10 hover:bg-white/20 rounded-lg flex items-center justify-center transition-colors">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-base font-bold">Detalle de Aviso</h1>
                </div>
            </div>
            <button onclick="compartir()" class="w-9 h-9 bg-white/10 hover:bg-white/20 rounded-lg flex items-center justify-center transition-colors" title="Compartir">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
            </button>
        </div>
    </header>

    <!-- Contenido -->
    <div class="max-w-lg mx-auto px-4 py-6">
        <!-- Card principal -->
        <div class="bg-white rounded-2xl shadow-sm border border-zinc-100 overflow-hidden">
            <!-- Encabezado con icono y categoria -->
            <div class="px-6 pt-6 pb-4">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-12 h-12 rounded-xl <?php echo $catInfo['bg']; ?> flex items-center justify-center text-2xl">
                        <?php echo $icono; ?>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center space-x-2 flex-wrap">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?php echo $catInfo['bg'] . ' ' . $catInfo['text']; ?>">
                                <?php echo $catInfo['label']; ?>
                            </span>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full <?php echo $priColor; ?>">
                                <?php echo ucfirst($prioridad); ?>
                            </span>
                        </div>
                        <p class="text-[10px] text-zinc-400 mt-1"><?php echo $fechaFormateada; ?></p>
                    </div>
                </div>

                <!-- Titulo -->
                <h1 class="text-lg font-bold text-zinc-900 leading-tight">
                    <?php echo $titulo; ?>
                </h1>
            </div>

            <!-- Separador -->
            <div class="border-t border-zinc-100 mx-6"></div>

            <!-- Contenido completo -->
            <div class="px-6 py-5">
                <div class="text-sm text-zinc-700 leading-relaxed space-y-3">
                    <?php echo $contenido; ?>
                </div>
            </div>

            <?php if (!empty($archivo)): ?>
            <!-- Archivo adjunto -->
            <div class="border-t border-zinc-100 mx-6"></div>
            <div class="px-6 py-4">
                <a href="<?php echo htmlspecialchars($archivo, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="flex items-center space-x-3 p-3 bg-zinc-50 rounded-xl hover:bg-zinc-100 transition-colors">
                    <div class="w-10 h-10 bg-violet-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-zinc-700 truncate">Archivo adjunto</p>
                        <p class="text-[10px] text-zinc-400 truncate"><?php echo htmlspecialchars(basename($archivo), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <svg class="w-4 h-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Botones de accion -->
        <div class="mt-6 flex space-x-3">
            <a href="avisos_padres.php" class="flex-1 flex items-center justify-center space-x-2 px-4 py-3 bg-white border border-zinc-200 rounded-xl text-sm font-semibold text-zinc-700 hover:bg-zinc-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                <span>Volver a avisos</span>
            </a>
            <button onclick="compartir()" class="flex-1 flex items-center justify-center space-x-2 px-4 py-3 bg-violet-600 rounded-xl text-sm font-semibold text-white hover:bg-violet-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                </svg>
                <span>Compartir</span>
            </button>
        </div>
    </div>

    <script>
        function compartir() {
            if (navigator.share) {
                navigator.share({
                    title: <?php echo json_encode($aviso['titulo']); ?>,
                    text: <?php echo json_encode(mb_substr($aviso['contenido'] ?? $aviso['mensaje'] ?? '', 0, 200)); ?>,
                    url: window.location.href
                }).catch(err => console.log('Compartir cancelado'));
            } else {
                // Fallback: copiar al portapapeles
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Enlace copiado al portapapeles');
                }).catch(() => {
                    alert('No se pudo compartir. Copia la URL manualmente.');
                });
            }
        }
    </script>
</body>
</html>
