<?php
/**
 * Perfil del Tutor - Editar datos personales
 * SGA COBAEV - Portal de Padres
 */

ob_start();
date_default_timezone_set('America/Mexico_City');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['tutor_autenticado']) || $_SESSION['tutor_autenticado'] !== true) {
    header("Location: login_padres.php");
    exit;
}

require_once 'conexion.php';

$matricula_alumno = $_SESSION['alumno_matricula'] ?? '';
$mensaje_exito = "";
$mensaje_error = "";

// Obtener datos actuales del tutor
$tutor = null;
try {
    $sql = "SELECT id_tutor, matricula_alumno, nombre_tutor, telefono, password_tutor, creado_el 
            FROM tutores 
            WHERE matricula_alumno = :matricula";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['matricula' => $matricula_alumno]);
    $tutor = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error perfil_tutor.php (SELECT): " . $e->getMessage());
    $mensaje_error = "Error al cargar los datos. Intente de nuevo.";
}

// Procesar formulario
if ($_SERVER["REQUEST_METHOD"] == "POST" && $tutor) {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'actualizar_datos') {
            $nombre = trim($_POST['nombre_tutor'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');

            // Validaciones
            if (empty($nombre) || strlen($nombre) < 3) {
                $mensaje_error = "El nombre debe tener al menos 3 caracteres.";
            } elseif (!empty($telefono) && !preg_match('/^[0-9]{10}$/', $telefono)) {
                $mensaje_error = "El teléfono debe tener 10 dígitos numéricos.";
            } else {
                // Actualizar TODOS los registros del mismo tutor (por nombre_tutor original)
                $update_sql = "UPDATE tutores SET nombre_tutor = :nombre, telefono = :telefono 
                               WHERE nombre_tutor = :nombre_original AND password_tutor = :password";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([
                    'nombre' => $nombre,
                    'telefono' => $telefono ?: null,
                    'nombre_original' => $tutor['nombre_tutor'],
                    'password' => $tutor['password_tutor']
                ]);

                // Actualizar sesion
                $_SESSION['tutor_nombre'] = $nombre;
                $mensaje_exito = "Datos actualizados correctamente.";

                // Recargar datos del tutor
                $stmt->execute(['matricula' => $matricula_alumno]);
                $tutor = $stmt->fetch();
            }

        } elseif ($accion === 'cambiar_password') {
            $password_actual = trim($_POST['password_actual'] ?? '');
            $password_nueva = trim($_POST['password_nueva'] ?? '');
            $password_confirmar = trim($_POST['password_confirmar'] ?? '');

            // Validaciones
            if ($password_actual !== $tutor['password_tutor']) {
                $mensaje_error = "La contraseña actual no es correcta.";
            } elseif (strlen($password_nueva) < 6) {
                $mensaje_error = "La nueva contraseña debe tener al menos 6 caracteres.";
            } elseif ($password_nueva !== $password_confirmar) {
                $mensaje_error = "Las contraseñas nuevas no coinciden.";
            } elseif ($password_nueva === $password_actual) {
                $mensaje_error = "La nueva contraseña debe ser diferente a la actual.";
            } else {
                // Actualizar password en TODOS los registros del mismo tutor
                $update_sql = "UPDATE tutores SET password_tutor = :password_nueva 
                               WHERE nombre_tutor = :nombre_tutor AND password_tutor = :password_actual";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([
                    'password_nueva' => $password_nueva,
                    'nombre_tutor' => $tutor['nombre_tutor'],
                    'password_actual' => $password_actual
                ]);

                $mensaje_exito = "Contraseña actualizada correctamente.";

                // Recargar datos del tutor
                $stmt->execute(['matricula' => $matricula_alumno]);
                $tutor = $stmt->fetch();
            }

        } elseif ($accion === 'vincular_alumno') {
            $nueva_matricula = strtoupper(trim($_POST['nueva_matricula'] ?? ''));

            if (empty($nueva_matricula) || strlen($nueva_matricula) < 5) {
                $mensaje_error = "Ingrese una matrícula válida.";
            } else {
                // Verificar que la matricula exista en la tabla alumnos
                $check_alumno = $pdo->prepare("SELECT matricula, nombre, apellido_paterno, apellido_materno FROM alumnos WHERE matricula = :matricula");
                $check_alumno->execute(['matricula' => $nueva_matricula]);
                $alumno_encontrado = $check_alumno->fetch();

                if (!$alumno_encontrado) {
                    $mensaje_error = "La matrícula ingresada no existe en el sistema.";
                } else {
                    // Verificar que no este ya vinculada a este tutor
                    $check_vinculo = $pdo->prepare("SELECT id_tutor FROM tutores WHERE nombre_tutor = :nombre AND matricula_alumno = :matricula");
                    $check_vinculo->execute([
                        'nombre' => $tutor['nombre_tutor'],
                        'matricula' => $nueva_matricula
                    ]);

                    if ($check_vinculo->fetch()) {
                        $mensaje_error = "Esta matrícula ya está vinculada a su cuenta.";
                    } else {
                        // Insertar nuevo registro en tutores
                        $insert_sql = "INSERT INTO tutores (matricula_alumno, nombre_tutor, telefono, password_tutor, creado_el) 
                                       VALUES (:matricula, :nombre, :telefono, :password, NOW())";
                        $insert_stmt = $pdo->prepare($insert_sql);
                        $insert_stmt->execute([
                            'matricula' => $nueva_matricula,
                            'nombre' => $tutor['nombre_tutor'],
                            'telefono' => $tutor['telefono'],
                            'password' => $tutor['password_tutor']
                        ]);

                        // Actualizar la sesion con el nuevo alumno
                        $nombre_completo_nuevo = $alumno_encontrado['nombre'] . ' ' . $alumno_encontrado['apellido_paterno'] . ' ' . $alumno_encontrado['apellido_materno'];
                        $_SESSION['alumnos'][] = [
                            'matricula' => $nueva_matricula,
                            'nombre_completo' => $nombre_completo_nuevo
                        ];

                        $mensaje_exito = "Alumno vinculado exitosamente: " . $nombre_completo_nuevo;
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Error perfil_tutor.php (UPDATE): " . $e->getMessage());
        $mensaje_error = "Error al guardar los cambios. Intente de nuevo.";
    }
}

// Obtener lista de alumnos vinculados para mostrar en la seccion
$alumnos_vinculados = $_SESSION['alumnos'] ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#5c1931">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Mi Perfil - SGA COBAEV</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
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
<body class="bg-crema font-sans-clean min-h-screen flex flex-col selection:bg-red-200">

    <!-- Header -->
    <header class="bg-vino px-4 py-3 flex justify-between items-center sticky top-0 z-50 shadow-lg">
        <div class="flex items-center space-x-3">
            <a href="padres.php" class="w-8 h-8 bg-white/10 hover:bg-white/20 rounded-lg flex items-center justify-center transition-colors active:scale-95">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div>
                <p class="text-white font-serif-elegant font-bold text-sm tracking-wide leading-none">Mi Perfil</p>
                <p class="text-white/50 text-[9px] font-medium uppercase tracking-widest mt-0.5">Configuración</p>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow pb-8">

        <!-- Mensajes -->
        <?php if (!empty($mensaje_exito)): ?>
            <div class="mx-4 mt-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-xl p-3 text-center font-medium flex items-center justify-center space-x-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span><?= htmlspecialchars($mensaje_exito) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensaje_error)): ?>
            <div class="mx-4 mt-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl p-3 text-center font-medium flex items-center justify-center space-x-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><?= htmlspecialchars($mensaje_error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($tutor): ?>

        <!-- Avatar y nombre -->
        <div class="text-center pt-6 pb-4">
            <div class="w-16 h-16 mx-auto rounded-full bg-gradient-to-br from-[#5c1931] to-[#a48253] flex items-center justify-center shadow-md">
                <span class="text-white font-bold text-xl">
                    <?php echo mb_strtoupper(mb_substr($tutor['nombre_tutor'], 0, 1)); ?>
                </span>
            </div>
            <h2 class="text-base font-bold text-zinc-800 mt-3"><?php echo htmlspecialchars($tutor['nombre_tutor']); ?></h2>
            <p class="text-[11px] text-zinc-400 font-mono mt-0.5">Matrícula vinculada: <?php echo htmlspecialchars($tutor['matricula_alumno']); ?></p>
            <p class="text-[10px] text-zinc-400 mt-1">Registrado el <?php echo date('d/m/Y', strtotime($tutor['creado_el'])); ?></p>
        </div>

        <!-- Formulario: Datos Personales -->
        <div class="px-4 mb-4">
            <form method="POST" class="bg-white rounded-2xl border border-zinc-100 shadow-sm overflow-hidden">
                <input type="hidden" name="accion" value="actualizar_datos">
                
                <div class="px-5 py-4 border-b border-zinc-50">
                    <div class="flex items-center space-x-2">
                        <div class="w-1 h-4 bg-dorado rounded-full"></div>
                        <h3 class="text-xs font-bold tracking-wide text-zinc-700 uppercase">Datos Personales</h3>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <!-- Nombre -->
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Nombre completo</label>
                        <input 
                            type="text" 
                            name="nombre_tutor" 
                            value="<?php echo htmlspecialchars($tutor['nombre_tutor']); ?>"
                            required
                            minlength="3"
                            class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-sm focus:outline-none focus:border-vino focus:ring-1 focus:ring-[#5c1931]/20 transition-all placeholder:text-zinc-400">
                    </div>

                    <!-- Teléfono -->
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Teléfono (10 dígitos)</label>
                        <input 
                            type="tel" 
                            name="telefono" 
                            value="<?php echo htmlspecialchars($tutor['telefono'] ?? ''); ?>"
                            maxlength="10"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            placeholder="Ej. 2281234567"
                            class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-sm font-mono focus:outline-none focus:border-vino focus:ring-1 focus:ring-[#5c1931]/20 transition-all placeholder:text-zinc-400 placeholder:font-sans">
                    </div>

                    <button type="submit" class="w-full bg-vino hover:bg-opacity-95 text-white font-bold text-xs tracking-wider uppercase py-3 rounded-lg shadow-sm active:scale-[0.98] transition-all">
                        Guardar cambios
                    </button>
                </div>
            </form>
        </div>

        <!-- Formulario: Cambiar Contraseña -->
        <div class="px-4 mb-4">
            <form method="POST" class="bg-white rounded-2xl border border-zinc-100 shadow-sm overflow-hidden">
                <input type="hidden" name="accion" value="cambiar_password">
                
                <div class="px-5 py-4 border-b border-zinc-50">
                    <div class="flex items-center space-x-2">
                        <div class="w-1 h-4 bg-rose-400 rounded-full"></div>
                        <h3 class="text-xs font-bold tracking-wide text-zinc-700 uppercase">Cambiar Contraseña</h3>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <!-- Contraseña actual -->
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Contraseña actual</label>
                        <input 
                            type="password" 
                            name="password_actual" 
                            required
                            placeholder="Ingresa tu contraseña actual"
                            class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-sm focus:outline-none focus:border-vino focus:ring-1 focus:ring-[#5c1931]/20 transition-all placeholder:text-zinc-400">
                    </div>

                    <!-- Nueva contraseña -->
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Nueva contraseña</label>
                        <input 
                            type="password" 
                            name="password_nueva" 
                            required
                            minlength="6"
                            placeholder="Mínimo 6 caracteres"
                            class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-sm focus:outline-none focus:border-vino focus:ring-1 focus:ring-[#5c1931]/20 transition-all placeholder:text-zinc-400">
                    </div>

                    <!-- Confirmar contraseña -->
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Confirmar nueva contraseña</label>
                        <input 
                            type="password" 
                            name="password_confirmar" 
                            required
                            minlength="6"
                            placeholder="Repite la nueva contraseña"
                            class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-sm focus:outline-none focus:border-vino focus:ring-1 focus:ring-[#5c1931]/20 transition-all placeholder:text-zinc-400">
                    </div>

                    <button type="submit" class="w-full bg-rose-600 hover:bg-opacity-95 text-white font-bold text-xs tracking-wider uppercase py-3 rounded-lg shadow-sm active:scale-[0.98] transition-all">
                        Cambiar contraseña
                    </button>
                </div>
            </form>
        </div>

        <!-- Alumnos vinculados -->
        <div class="px-4 mb-4">
            <div class="bg-white rounded-2xl border border-zinc-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-zinc-50">
                    <div class="flex items-center space-x-2">
                        <div class="w-1 h-4 bg-emerald-400 rounded-full"></div>
                        <h3 class="text-xs font-bold tracking-wide text-zinc-700 uppercase">Alumnos Vinculados</h3>
                    </div>
                </div>
                <div class="p-5">
                    <?php if (!empty($alumnos_vinculados)): ?>
                        <div class="space-y-2">
                            <?php foreach ($alumnos_vinculados as $av): ?>
                                <div class="flex items-center space-x-3 p-2.5 rounded-xl bg-zinc-50 border border-zinc-100">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#5c1931] to-[#a48253] flex items-center justify-center flex-shrink-0">
                                        <span class="text-white font-bold text-[10px]">
                                            <?php echo mb_strtoupper(mb_substr($av['nombre_completo'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-grow">
                                        <p class="text-xs font-semibold text-zinc-700 truncate"><?php echo htmlspecialchars($av['nombre_completo']); ?></p>
                                        <p class="text-[10px] text-zinc-400 font-mono"><?php echo htmlspecialchars($av['matricula']); ?></p>
                                    </div>
                                    <?php if ($av['matricula'] === $matricula_alumno): ?>
                                        <span class="text-[9px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full uppercase">Activo</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-xs text-zinc-400 text-center">No hay alumnos vinculados.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Formulario: Vincular otro alumno -->
        <div class="px-4 mb-4">
            <form method="POST" class="bg-white rounded-2xl border border-zinc-100 shadow-sm overflow-hidden">
                <input type="hidden" name="accion" value="vincular_alumno">
                
                <div class="px-5 py-4 border-b border-zinc-50">
                    <div class="flex items-center space-x-2">
                        <div class="w-1 h-4 bg-blue-400 rounded-full"></div>
                        <h3 class="text-xs font-bold tracking-wide text-zinc-700 uppercase">Vincular Otro Alumno</h3>
                    </div>
                </div>

                <div class="p-5 space-y-4">
                    <p class="text-[11px] text-zinc-500 leading-relaxed">
                        Si tiene otro hijo/a en el plantel, ingrese su matrícula para vincularlo a su cuenta y recibir notificaciones de ambos.
                    </p>

                    <!-- Matricula del nuevo alumno -->
                    <div>
                        <label class="block text-[11px] font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Matrícula del alumno</label>
                        <input 
                            type="text" 
                            name="nueva_matricula" 
                            required
                            placeholder="Ej. 123310070"
                            class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-3 text-sm font-mono uppercase focus:outline-none focus:border-vino focus:ring-1 focus:ring-[#5c1931]/20 transition-all placeholder:text-zinc-400 placeholder:font-sans">
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-opacity-95 text-white font-bold text-xs tracking-wider uppercase py-3 rounded-lg shadow-sm active:scale-[0.98] transition-all">
                        Vincular alumno
                    </button>
                </div>
            </form>
        </div>

        <?php else: ?>
            <div class="px-4 pt-8 text-center">
                <p class="text-sm text-zinc-500">No se pudieron cargar los datos del perfil.</p>
                <a href="padres.php" class="text-xs text-dorado hover:underline mt-2 inline-block">Volver al portal</a>
            </div>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="px-4 py-4 text-center flex-shrink-0 border-t border-zinc-100 bg-white/50">
        <p class="text-[9px] text-zinc-400 uppercase tracking-widest">
            COBAEV &bull; Portal de Padres
        </p>
    </footer>

    <!-- Script para solo números en teléfono -->
    <script>
        document.querySelector('input[name="telefono"]')?.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    </script>

</body>
</html>
<?php ob_end_flush(); ?>
