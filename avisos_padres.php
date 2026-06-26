<?php
/**
 * Lista completa de avisos - Portal de Padres
 * SGA COBAEV
 */
require_once 'includes/session_padres.php';
verificarSesionTutor();

$nombre_tutor = $_SESSION['tutor_nombre'] ?? 'Tutor';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Avisos - SGA COBAEV</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-zinc-50 min-h-screen">
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-gradient-to-r from-violet-600 to-purple-700 text-white shadow-lg">
        <div class="max-w-lg mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="padres.php" class="w-9 h-9 bg-white/10 hover:bg-white/20 rounded-lg flex items-center justify-center transition-colors">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-base font-bold">Avisos</h1>
                    <p class="text-[10px] text-white/70">Notificaciones del plantel</p>
                </div>
            </div>
            <div id="badge-total" class="text-xs bg-white/20 px-2 py-1 rounded-full"></div>
        </div>
    </header>

    <!-- Filtros -->
    <div class="max-w-lg mx-auto px-4 pt-4 pb-2">
        <!-- Categorias -->
        <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
            <button onclick="filtrarCategoria('')" id="chip-todas" class="chip-cat flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-violet-600 text-white transition-colors">
                Todas
            </button>
            <button onclick="filtrarCategoria('institucional')" id="chip-institucional" class="chip-cat flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-zinc-200 text-zinc-600 hover:bg-violet-100 transition-colors">
                Institucional
            </button>
            <button onclick="filtrarCategoria('academico')" id="chip-academico" class="chip-cat flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-zinc-200 text-zinc-600 hover:bg-violet-100 transition-colors">
                Academico
            </button>
            <button onclick="filtrarCategoria('emergencia')" id="chip-emergencia" class="chip-cat flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-zinc-200 text-zinc-600 hover:bg-violet-100 transition-colors">
                Emergencia
            </button>
            <button onclick="filtrarCategoria('pagos')" id="chip-pagos" class="chip-cat flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-zinc-200 text-zinc-600 hover:bg-violet-100 transition-colors">
                Pagos
            </button>
            <button onclick="filtrarCategoria('cultural')" id="chip-cultural" class="chip-cat flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold bg-zinc-200 text-zinc-600 hover:bg-violet-100 transition-colors">
                Cultural
            </button>
        </div>
        <!-- Toggle no leidos -->
        <div class="flex items-center justify-between mt-2">
            <span class="text-xs text-zinc-500" id="resultado-texto">Cargando...</span>
            <label class="flex items-center space-x-2 cursor-pointer">
                <span class="text-xs text-zinc-500">Solo no leidos</span>
                <input type="checkbox" id="toggle-no-leidos" onchange="cargarAvisos()" class="w-4 h-4 text-violet-600 rounded border-zinc-300 focus:ring-violet-500">
            </label>
        </div>
    </div>

    <!-- Lista de avisos -->
    <div class="max-w-lg mx-auto px-4 pb-20" id="contenedor-avisos">
        <div class="text-center py-10">
            <div class="w-8 h-8 border-2 border-violet-300 border-t-violet-600 rounded-full animate-spin mx-auto"></div>
            <p class="text-xs text-zinc-400 mt-3">Cargando avisos...</p>
        </div>
    </div>

    <!-- Paginacion -->
    <div class="max-w-lg mx-auto px-4 pb-6 hidden" id="paginacion-container">
        <div class="flex items-center justify-center space-x-2">
            <button onclick="paginaAnterior()" id="btn-prev" class="px-3 py-2 rounded-lg bg-zinc-200 text-zinc-600 text-xs font-semibold hover:bg-zinc-300 disabled:opacity-50 disabled:cursor-not-allowed">
                Anterior
            </button>
            <span id="pagina-info" class="text-xs text-zinc-500"></span>
            <button onclick="paginaSiguiente()" id="btn-next" class="px-3 py-2 rounded-lg bg-violet-600 text-white text-xs font-semibold hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Siguiente
            </button>
        </div>
    </div>

    <script>
        let categoriaActual = '';
        let paginaActual = 1;
        let totalPaginas = 1;

        function filtrarCategoria(cat) {
            categoriaActual = cat;
            paginaActual = 1;
            // Actualizar chips
            document.querySelectorAll('.chip-cat').forEach(el => {
                el.classList.remove('bg-violet-600', 'text-white');
                el.classList.add('bg-zinc-200', 'text-zinc-600');
            });
            const activeChip = document.getElementById('chip-' + (cat || 'todas'));
            if (activeChip) {
                activeChip.classList.remove('bg-zinc-200', 'text-zinc-600');
                activeChip.classList.add('bg-violet-600', 'text-white');
            }
            cargarAvisos();
        }

        function paginaAnterior() {
            if (paginaActual > 1) {
                paginaActual--;
                cargarAvisos();
            }
        }

        function paginaSiguiente() {
            if (paginaActual < totalPaginas) {
                paginaActual++;
                cargarAvisos();
            }
        }

        async function cargarAvisos() {
            const noLeidos = document.getElementById('toggle-no-leidos').checked ? '1' : '0';
            let url = 'api/obtener_avisos.php?pagina=' + paginaActual + '&limite=10';
            if (categoriaActual) url += '&categoria=' + categoriaActual;
            if (noLeidos === '1') url += '&no_leidos=1';

            try {
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    totalPaginas = data.paginacion.total_paginas || 1;
                    renderAvisos(data.avisos);
                    actualizarPaginacion(data.paginacion);
                    document.getElementById('resultado-texto').textContent = 
                        data.estadisticas.total + ' aviso(s), ' + data.estadisticas.no_leidos + ' sin leer';
                    document.getElementById('badge-total').textContent = data.estadisticas.no_leidos + ' sin leer';
                } else {
                    renderVacio();
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('contenedor-avisos').innerHTML = 
                    '<div class="text-center py-10"><p class="text-xs text-zinc-400">Error al cargar avisos</p></div>';
            }
        }

        function renderAvisos(avisos) {
            const container = document.getElementById('contenedor-avisos');
            if (!avisos || avisos.length === 0) {
                renderVacio();
                return;
            }

            const coloresCat = {
                'institucional': 'bg-blue-100 text-blue-600',
                'academico': 'bg-emerald-100 text-emerald-600',
                'emergencia': 'bg-red-100 text-red-600',
                'pagos': 'bg-amber-100 text-amber-600',
                'cultural': 'bg-purple-100 text-purple-600'
            };

            const coloresPrioridad = {
                'urgente': 'bg-red-500 text-white',
                'importante': 'bg-amber-500 text-white',
                'normal': ''
            };

            let html = '<div class="space-y-3">';
            avisos.forEach(aviso => {
                const colorCat = coloresCat[aviso.categoria] || 'bg-zinc-100 text-zinc-600';
                const colorPrioridad = coloresPrioridad[aviso.prioridad] || '';
                const noLeido = !aviso.leido;

                html += '<a href="avisos_detalle.php?id=' + aviso.id + '" class="block bg-white rounded-xl shadow-sm border border-zinc-100 hover:shadow-md hover:border-violet-200 transition-all p-4 ' + (noLeido ? 'border-l-4 border-l-violet-500' : '') + '">';
                html += '  <div class="flex items-start space-x-3">';
                // Icono de categoria
                html += '    <div class="flex-shrink-0 w-10 h-10 rounded-lg ' + colorCat + ' flex items-center justify-center text-lg">';
                html += '      ' + escapeHtml(aviso.icono);
                html += '    </div>';
                html += '    <div class="flex-1 min-w-0">';
                // Titulo con indicador no leido
                html += '      <div class="flex items-center space-x-2">';
                if (noLeido) {
                    html += '        <span class="w-2 h-2 bg-violet-500 rounded-full flex-shrink-0"></span>';
                }
                html += '        <h3 class="text-sm font-bold text-zinc-800 truncate">' + escapeHtml(aviso.titulo) + '</h3>';
                html += '      </div>';
                // Preview
                html += '      <p class="text-xs text-zinc-500 mt-1 line-clamp-2">' + escapeHtml(aviso.preview) + '</p>';
                // Meta: fecha y prioridad
                html += '      <div class="flex items-center space-x-2 mt-2">';
                html += '        <span class="text-[10px] text-zinc-400">' + escapeHtml(aviso.fecha_relativa) + '</span>';
                if (colorPrioridad) {
                    html += '        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold ' + colorPrioridad + '">' + escapeHtml(aviso.prioridad) + '</span>';
                }
                html += '      </div>';
                html += '    </div>';
                // Chevron
                html += '    <svg class="w-4 h-4 text-zinc-300 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>';
                html += '  </div>';
                html += '</a>';
            });
            html += '</div>';
            container.innerHTML = html;
        }

        function renderVacio() {
            document.getElementById('contenedor-avisos').innerHTML = 
                '<div class="text-center py-16">' +
                '<div class="w-16 h-16 mx-auto bg-violet-50 rounded-2xl flex items-center justify-center mb-4">' +
                '<svg class="w-8 h-8 text-violet-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>' +
                '</div>' +
                '<p class="text-sm font-semibold text-zinc-500">Sin avisos</p>' +
                '<p class="text-xs text-zinc-400 mt-1">No hay avisos que mostrar con los filtros seleccionados</p>' +
                '</div>';
        }

        function actualizarPaginacion(pag) {
            const container = document.getElementById('paginacion-container');
            if (pag.total_paginas <= 1) {
                container.classList.add('hidden');
                return;
            }
            container.classList.remove('hidden');
            document.getElementById('pagina-info').textContent = 'Pagina ' + pag.pagina_actual + ' de ' + pag.total_paginas;
            document.getElementById('btn-prev').disabled = pag.pagina_actual <= 1;
            document.getElementById('btn-next').disabled = pag.pagina_actual >= pag.total_paginas;
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Cargar al iniciar
        document.addEventListener('DOMContentLoaded', function() {
            cargarAvisos();
        });
    </script>
</body>
</html>
