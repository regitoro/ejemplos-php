<?php
/** Devuelve iconos SVG de línea para la interfaz. */
function iconoInterfaz(string $nombre): string
{
    $rutas = [
        "inicio" => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        "cursos" => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21z"/><path d="M4 5.5v13A2.5 2.5 0 0 1 6.5 16H20M8 7h8"/>',
        "tareas" => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4.5h6M8 11h8M8 15h5"/>',
        "revision" => '<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/>',
        "fecha" => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/>',
        "usuarios" => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM20 8v6M23 11h-6"/>',
        "perfil" => '<circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/>',
        "buscar" => '<circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/>',
        "preguntas" => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 4.4 1.6c-1.1 1.2-2 1.5-2 3M12 17.5v.1"/>',
        "ponente" => '<circle cx="9" cy="8" r="3.5"/><path d="M3 20v-1a6 6 0 0 1 6-6h1a6 6 0 0 1 6 6v1M17 5a3.5 3.5 0 0 1 0 6.8M18 14a5 5 0 0 1 3 4.6V20"/>',
        "leccion" => '<path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5"/>',
    ];
    $ruta = $rutas[$nombre] ?? $rutas["tareas"];

    return '<svg class="icono-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" '
        . 'aria-hidden="true" focusable="false">' . $ruta . '</svg>';
}