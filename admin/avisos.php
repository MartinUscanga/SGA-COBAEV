<?php
/**
 * Panel de Administracion - Avisos a Padres
 * SGA COBAEV
 * 
 * SQL para crear la tabla:
 * CREATE TABLE avisos (
 *   id_aviso INT AUTO_INCREMENT PRIMARY KEY,
 *   titulo VARCHAR(255) NOT NULL,
 *   mensaje TEXT NOT NULL,
 *   destinatario VARCHAR(50) NOT NULL DEFAULT 'todos',
 *   creado_por VARCHAR(100) NOT NULL,
 *   fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   leido TINYINT NOT NULL DEFAULT 0
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 */
session_start();
if (!isset($_SESSION['usuario_autenticado']) || $_SESSION['usuario_autenticado'] !== true) {
    header("Location: ../login_admin.php");
    exit;
}

require_once '../conexion.php';

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje_exito = '';
$mensaje_error = '';

// Procesar envio de aviso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    // Validar CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $mensaje_error = 'Token de seguridad invalido. Recarga la pagina.';
    } else {
        $accion = $_POST['accion'];

        if ($accion === 'crear_aviso') {
            $titulo = trim($_POST['titulo'] ?? '');
            $mensaje = trim($_POST['mensaje'] ?? '');
            $destinatario = trim($_POST['destinatario'] ?? 'todos');

            if (empty($titulo) || empty($mensaje)) {
                $mensaje_error = 'El titulo y mensaje son obligatorios.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO avisos (titulo, mensaje, destinatario, creado_por, fecha_envio) VALUES (:titulo, :mensaje, :destinatario, :creado_por, NOW())");
                    $stmt->execute([
                        'titulo' => $titulo,
                        'mensaje' => $mensaje,
                        'destinatario' => $destinatario,
                        'creado_por' => $_SESSION['username'] ?? $_SESSION['usuario_nombre'] ?? 'admin'
                    ]);
                    $mensaje_exito = 'Aviso enviado correctamente.';
                } catch (PDOException $e) {
                    $mensaje_error = 'Error al enviar aviso: ' . $e->getMessage();
                }
            }
        } elseif ($accion === 'eliminar_aviso') {
            $id_aviso = intval($_POST['id_aviso'] ?? 0);
            if ($id_aviso > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM avisos WHERE id_aviso = :id");
                    $stmt->execute(['id' => $id_aviso]);
                    $mensaje_exito = 'Aviso eliminado correctamente.';
                } catch (PDOException $e) {
                    $mensaje_error = 'Error al eliminar: ' . $e->getMessage();
                }
            }
        }
    }
}

// Obtener avisos enviados (paginados)
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 15;
$offset = ($pagina - 1) * $por_pagina;

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM avisos");
    $total_avisos = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT * FROM avisos ORDER BY fecha_envio DESC LIMIT " . intval($por_pagina) . " OFFSET " . intval($offset));
    $stmt->execute();
    $avisos = $stmt->fetchAll();
} catch (PDOException $e) {
    $avisos = [];
    $total_avisos = 0;
    $mensaje_error = 'Error al cargar avisos: ' . $e->getMessage();
}

$total_paginas = ceil($total_avisos / $por_pagina);
$pagina_actual = 'avisos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Avisos - Panel Admin SGA COBAEV</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .font-serif-elegant { font-family: 'Playfair Display', serif; }
        .font-sans-clean { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-crema { background-color: #f7f3eb; }
        .text-vino { color: #5c1931; }
        .bg-vino { background-color: #5c1931; }
        .border-vino { border-color: #5c1931; }
        .text-dorado { color: #a48253; }
        .bg-dorado { background-color: #a48253; }
    </style>
</head>
<body class="bg-crema font-sans-clean min-h-screen flex">

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 bg-vino text-white transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out flex flex-col">
        <div class="p-6 border-b border-white/10">
            <h1 class="font-serif-elegant text-xl font-bold tracking-wide">SGA COBAEV</h1>
            <p class="text-xs text-white/60 mt-1">Panel Administrativo</p>
        </div>
        <nav class="flex-1 p-4 space-y-1">
            <a href="index.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </a>
            <a href="alumnos.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Alumnos
            </a>
            <a href="tutores.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Tutores
            </a>
            <a href="asistencias.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Asistencias
            </a>
            <a href="usuarios.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Usuarios
            </a>
            <a href="dispositivos.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Dispositivos
            </a>
            <a href="avisos.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium bg-white/10 text-white">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                Avisos
            </a>
        </nav>
        <div class="p-4 border-t border-white/10">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-xs font-bold">
                    <?= strtoupper(substr($_SESSION['usuario_nombre'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate"><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Admin') ?></p>
                    <p class="text-xs text-white/50"><?= htmlspecialchars($_SESSION['usuario_rol'] ?? 'Admin') ?></p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Overlay mobile -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-20 hidden md:hidden" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <main class="flex-1 md:ml-64 min-h-screen">
        <!-- Header -->
        <header class="bg-white border-b border-zinc-200 px-4 md:px-8 py-4 flex items-center justify-between sticky top-0 z-10">
            <div class="flex items-center space-x-4">
                <button onclick="toggleSidebar()" class="md:hidden text-zinc-600 hover:text-vino">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h2 class="font-serif-elegant text-xl font-bold text-vino">Avisos a Padres</h2>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-zinc-500 hidden sm:inline"><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? '') ?></span>
                <a href="../logout.php" class="text-xs font-bold text-white bg-vino hover:bg-opacity-90 px-4 py-2 rounded-lg transition-colors">Cerrar Sesion</a>
            </div>
        </header>

        <div class="p-4 md:p-8 space-y-6">

            <!-- Mensajes de estado -->
            <?php if ($mensaje_exito): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg p-4 text-sm font-medium">
                    <?= htmlspecialchars($mensaje_exito) ?>
                </div>
            <?php endif; ?>
            <?php if ($mensaje_error): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-lg p-4 text-sm font-medium">
                    <?= htmlspecialchars($mensaje_error) ?>
                </div>
            <?php endif; ?>

            <!-- Formulario para crear aviso -->
            <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-zinc-100">
                    <h3 class="font-serif-elegant text-lg font-bold text-vino">Enviar Nuevo Aviso</h3>
                    <p class="text-xs text-zinc-400 mt-0.5">Los avisos se mostraran en el portal de padres</p>
                </div>
                <form method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="accion" value="crear_aviso">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1">Titulo del aviso</label>
                            <input type="text" name="titulo" required maxlength="255" placeholder="Ej: Junta de padres" class="w-full border border-zinc-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1">Destinatario</label>
                            <div class="flex items-center space-x-3">
                                <select name="destinatario" id="select-destinatario" onchange="toggleMatricula()" class="flex-1 border border-zinc-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                                    <option value="todos">Todos los padres</option>
                                    <option value="matricula">Matricula especifica</option>
                                </select>
                                <input type="text" name="matricula_especifica" id="input-matricula" placeholder="Ej: B2024001" maxlength="50" class="hidden flex-1 border border-zinc-200 rounded-lg px-4 py-2.5 text-sm uppercase focus:outline-none focus:border-vino transition-colors">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1">Mensaje</label>
                        <textarea name="mensaje" required rows="4" maxlength="2000" placeholder="Escribe el contenido del aviso..." class="w-full border border-zinc-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-vino transition-colors resize-none"></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-vino hover:bg-opacity-90 text-white font-bold text-xs uppercase tracking-wider px-6 py-3 rounded-lg transition-colors flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            <span>Enviar Aviso</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de avisos enviados -->
            <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-zinc-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-serif-elegant text-lg font-bold text-vino">Avisos Enviados</h3>
                        <p class="text-xs text-zinc-400 mt-0.5"><?= $total_avisos ?> aviso(s) en total</p>
                    </div>
                </div>

                <?php if (empty($avisos)): ?>
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 mx-auto bg-zinc-50 rounded-2xl flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </div>
                        <p class="text-sm font-semibold text-zinc-500">No hay avisos enviados</p>
                        <p class="text-xs text-zinc-400 mt-1">Usa el formulario de arriba para enviar el primer aviso</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 tracking-wide">
                                <tr>
                                    <th class="px-6 py-3 text-left">Titulo</th>
                                    <th class="px-6 py-3 text-left">Destinatario</th>
                                    <th class="px-6 py-3 text-center">Estado</th>
                                    <th class="px-6 py-3 text-left">Enviado por</th>
                                    <th class="px-6 py-3 text-left">Fecha</th>
                                    <th class="px-6 py-3 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-50">
                                <?php foreach ($avisos as $aviso): ?>
                                    <tr class="hover:bg-zinc-50 transition-colors">
                                        <td class="px-6 py-3">
                                            <p class="font-semibold text-zinc-700"><?= htmlspecialchars($aviso['titulo']) ?></p>
                                            <p class="text-xs text-zinc-400 mt-0.5 truncate max-w-[200px]"><?= htmlspecialchars(mb_substr($aviso['mensaje'], 0, 60)) ?><?= mb_strlen($aviso['mensaje']) > 60 ? '...' : '' ?></p>
                                        </td>
                                        <td class="px-6 py-3">
                                            <?php if ($aviso['destinatario'] === 'todos'): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">Todos</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 font-mono"><?= htmlspecialchars($aviso['destinatario']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <?php if ($aviso['leido']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">Leido</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-zinc-100 text-zinc-600">No leido</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-xs text-zinc-500"><?= htmlspecialchars($aviso['creado_por']) ?></td>
                                        <td class="px-6 py-3 text-xs text-zinc-500"><?= date('d/m/Y H:i', strtotime($aviso['fecha_envio'])) ?></td>
                                        <td class="px-6 py-3 text-center">
                                            <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar este aviso?')">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="accion" value="eliminar_aviso">
                                                <input type="hidden" name="id_aviso" value="<?= $aviso['id_aviso'] ?>">
                                                <button type="submit" class="text-rose-500 hover:text-rose-700 transition-colors" title="Eliminar">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginacion -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="px-6 py-4 border-t border-zinc-100 flex items-center justify-between">
                            <p class="text-xs text-zinc-400">Pagina <?= $pagina ?> de <?= $total_paginas ?></p>
                            <div class="flex items-center space-x-2">
                                <?php if ($pagina > 1): ?>
                                    <a href="?pagina=<?= $pagina - 1 ?>" class="px-3 py-1.5 text-xs font-medium border border-zinc-200 rounded-lg hover:bg-zinc-50 transition-colors">Anterior</a>
                                <?php endif; ?>
                                <?php if ($pagina < $total_paginas): ?>
                                    <a href="?pagina=<?= $pagina + 1 ?>" class="px-3 py-1.5 text-xs font-medium border border-zinc-200 rounded-lg hover:bg-zinc-50 transition-colors">Siguiente</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Toggle sidebar mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        // Toggle campo de matricula
        function toggleMatricula() {
            const select = document.getElementById('select-destinatario');
            const input = document.getElementById('input-matricula');
            if (select.value === 'matricula') {
                input.classList.remove('hidden');
                input.required = true;
            } else {
                input.classList.add('hidden');
                input.required = false;
                input.value = '';
            }
        }

        // Antes de enviar, ajustar el valor del destinatario
        document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
            const select = document.getElementById('select-destinatario');
            const input = document.getElementById('input-matricula');
            if (select.value === 'matricula' && input.value.trim()) {
                select.value = input.value.trim().toUpperCase();
                select.name = 'destinatario';
            }
        });
    </script>
</body>
</html>
