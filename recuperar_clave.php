<?php
// Recuperacion de clave para tutores
session_start();

$step = 'buscar'; // buscar | confirmar | resultado
$mensaje = '';
$tipo_mensaje = ''; // error | success
$telefono_parcial = '';
$matricula_busqueda = '';
$nueva_clave = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'conexion.php';

    $accion = $_POST['accion'] ?? '';

    if ($accion === 'buscar_matricula') {
        // Paso 1: Buscar tutor por matricula del alumno
        $matricula_busqueda = strtoupper(trim($_POST['matricula'] ?? ''));

        if (empty($matricula_busqueda)) {
            $mensaje = 'Por favor, ingrese la matricula del alumno.';
            $tipo_mensaje = 'error';
            $step = 'buscar';
        } else {
            try {
                $sql = "SELECT id_tutor, telefono FROM tutores WHERE matricula_alumno = :matricula LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['matricula' => $matricula_busqueda]);
                $tutor = $stmt->fetch();

                if ($tutor && !empty($tutor['telefono'])) {
                    // Ocultar parcialmente el telefono (mostrar primeros 3 y ultimos 4)
                    $tel = $tutor['telefono'];
                    $len = strlen($tel);
                    if ($len >= 7) {
                        $telefono_parcial = substr($tel, 0, 3) . str_repeat('*', $len - 7) . substr($tel, -4);
                    } else {
                        $telefono_parcial = str_repeat('*', $len);
                    }
                    $step = 'confirmar';
                } elseif ($tutor && empty($tutor['telefono'])) {
                    $mensaje = 'No hay un telefono registrado para este tutor. Contacte a la administracion del plantel.';
                    $tipo_mensaje = 'error';
                    $step = 'buscar';
                } else {
                    $mensaje = 'No se encontro un tutor vinculado a esa matricula.';
                    $tipo_mensaje = 'error';
                    $step = 'buscar';
                }
            } catch (PDOException $e) {
                $mensaje = 'Error temporal en el servidor. Intente mas tarde.';
                $tipo_mensaje = 'error';
                $step = 'buscar';
            }
        }

    } elseif ($accion === 'confirmar_telefono') {
        // Paso 2: El tutor confirmo que es su telefono, generar nueva clave
        $matricula_busqueda = strtoupper(trim($_POST['matricula'] ?? ''));

        if (empty($matricula_busqueda)) {
            $mensaje = 'Datos invalidos. Intente de nuevo.';
            $tipo_mensaje = 'error';
            $step = 'buscar';
        } else {
            try {
                // Generar clave temporal de 6 caracteres alfanumericos
                $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
                $nueva_clave = '';
                for ($i = 0; $i < 6; $i++) {
                    $nueva_clave .= $caracteres[random_int(0, strlen($caracteres) - 1)];
                }

                // Actualizar la clave en TODOS los registros de ese tutor (misma matricula)
                $sql_update = "UPDATE tutores SET password_tutor = :nueva_clave WHERE matricula_alumno = :matricula";
                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute([
                    'nueva_clave' => $nueva_clave,
                    'matricula' => $matricula_busqueda
                ]);

                if ($stmt_update->rowCount() > 0) {
                    $step = 'resultado';
                } else {
                    $mensaje = 'No se pudo actualizar la clave. Intente de nuevo.';
                    $tipo_mensaje = 'error';
                    $step = 'buscar';
                }
            } catch (PDOException $e) {
                $mensaje = 'Error temporal en el servidor. Intente mas tarde.';
                $tipo_mensaje = 'error';
                $step = 'buscar';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#5c1931">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link rel="manifest" href="manifest.json">
    <title>Recuperar Clave - COBAEV</title>
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
<body class="bg-crema font-sans-clean min-h-screen flex flex-col justify-between p-4 md:p-8 selection:bg-red-200">

    <div class="max-w-md mx-auto w-full text-left flex-shrink-0">
        <a href="login_padres.php" class="inline-flex items-center text-xs font-bold tracking-wider text-zinc-400 hover:text-vino uppercase transition-colors group">
            <span class="mr-2 group-hover:-translate-x-1 transition-transform">&larr;</span> Volver al login
        </a>
    </div>

    <div class="max-w-md mx-auto w-full bg-white border border-zinc-200/80 rounded-2xl p-6 md:p-8 shadow-sm my-auto space-y-6">
        
        <div class="text-center space-y-2">
            <div class="flex items-center justify-center space-x-2">
                <span class="text-vino font-serif-elegant font-bold text-2xl tracking-wider">SGA</span>
                <span class="text-zinc-300 text-xl">|</span>
                <span class="text-dorado font-serif-elegant italic text-xl">Recuperar Clave</span>
            </div>
            <p class="text-[10px] text-zinc-400 font-bold tracking-widest uppercase px-4 leading-normal">
                Restablezca su clave de acceso al portal de padres
            </p>
        </div>

        <?php if (!empty($mensaje)): ?>
            <div class="<?= $tipo_mensaje === 'error' ? 'bg-rose-50 border-rose-200 text-rose-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700' ?> border text-xs rounded-lg p-3 text-center font-medium">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 'buscar'): ?>
        <!-- PASO 1: Buscar por matricula -->
        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="accion" value="buscar_matricula">
            
            <div>
                <label class="block text-xs font-bold text-zinc-500 uppercase mb-1 tracking-wide">Matricula del Alumno</label>
                <div class="relative">
                    <input type="text" name="matricula" required placeholder="Ej. 123310070" inputmode="numeric"
                           value="<?= htmlspecialchars($matricula_busqueda) ?>"
                           class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 pl-9 text-sm font-mono focus:outline-none focus:border-vino transition-colors placeholder:font-sans placeholder:text-zinc-400 uppercase">
                    <svg class="w-4 h-4 absolute left-3 top-3.5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                    </svg>
                </div>
                <p class="text-[10px] text-zinc-400 mt-1">Ingrese la matricula del alumno vinculado a su cuenta</p>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-vino hover:bg-opacity-95 text-white font-sans-clean font-bold text-xs tracking-wider uppercase py-3 rounded-lg shadow-sm active:scale-[0.99] transition-all">
                    Buscar mi cuenta
                </button>
            </div>
        </form>

        <?php elseif ($step === 'confirmar'): ?>
        <!-- PASO 2: Confirmar telefono -->
        <div class="space-y-4">
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-center space-y-2">
                <svg class="w-8 h-8 mx-auto text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                </svg>
                <p class="text-sm font-semibold text-amber-800">Telefono registrado:</p>
                <p class="text-lg font-mono font-bold text-amber-900 tracking-wider"><?= htmlspecialchars($telefono_parcial) ?></p>
                <p class="text-xs text-amber-700">¿Este es su numero de telefono registrado?</p>
            </div>

            <div class="flex gap-3">
                <form action="" method="POST" class="flex-1">
                    <input type="hidden" name="accion" value="confirmar_telefono">
                    <input type="hidden" name="matricula" value="<?= htmlspecialchars($matricula_busqueda) ?>">
                    <button type="submit" class="w-full bg-vino hover:bg-opacity-95 text-white font-sans-clean font-bold text-xs tracking-wider uppercase py-3 rounded-lg shadow-sm active:scale-[0.99] transition-all">
                        Si, es mi numero
                    </button>
                </form>
                <form action="" method="POST" class="flex-1">
                    <input type="hidden" name="accion" value="buscar_matricula">
                    <input type="hidden" name="matricula" value="">
                    <button type="submit" class="w-full bg-zinc-200 hover:bg-zinc-300 text-zinc-700 font-sans-clean font-bold text-xs tracking-wider uppercase py-3 rounded-lg shadow-sm active:scale-[0.99] transition-all">
                        No, cancelar
                    </button>
                </form>
            </div>
        </div>

        <?php elseif ($step === 'resultado'): ?>
        <!-- PASO 3: Mostrar nueva clave -->
        <div class="space-y-4">
            <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 text-center space-y-3">
                <svg class="w-10 h-10 mx-auto text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm font-semibold text-emerald-800">Su nueva clave temporal es:</p>
                <p class="text-2xl font-mono font-bold text-emerald-900 tracking-widest bg-white border border-emerald-300 rounded-lg py-2 px-4 inline-block select-all"><?= htmlspecialchars($nueva_clave) ?></p>
                <p class="text-xs text-emerald-700 leading-relaxed">
                    Cambiela despues de iniciar sesion desde su perfil.
                </p>
            </div>

            <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-center">
                <p class="text-[10px] text-amber-700 font-semibold uppercase tracking-wide">
                    ⚠ Esta clave se muestra una sola vez. Anotela en un lugar seguro.
                </p>
            </div>

            <div class="pt-2">
                <a href="login_padres.php" class="block w-full bg-vino hover:bg-opacity-95 text-white font-sans-clean font-bold text-xs tracking-wider uppercase py-3 rounded-lg shadow-sm text-center transition-all">
                    Ir a iniciar sesion
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <div class="max-w-md mx-auto w-full text-center text-[9px] text-zinc-400 uppercase tracking-widest flex-shrink-0 leading-normal px-2">
        Proteccion de datos conforme a la legislacion escolar vigente
    </div>

</body>
</html>
