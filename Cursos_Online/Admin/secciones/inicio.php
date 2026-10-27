<?php
// ---------- Tarjetas de resumen ----------
$totalAlumnos = $conexion->query(
    "SELECT COUNT(*) AS n
     FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE r.nombre = 'alumno' AND u.aceptado = 1"
)->fetch_assoc()["n"];

$totalCursos = $conexion->query("SELECT COUNT(*) AS n FROM cursos")->fetch_assoc()["n"];

$cursosActivos = $conexion->query(
    "SELECT COUNT(*) AS n FROM cursos WHERE estado = 'activo'"
)->fetch_assoc()["n"];

$pendientes = $conexion->query(
    "SELECT COUNT(*) AS n FROM usuarios WHERE aceptado = 0"
)->fetch_assoc()["n"];

$solPendientes = $conexion->query(
    "SELECT COUNT(*) AS n FROM solicitudes_inscripcion WHERE estado = 'pendiente'"
)->fetch_assoc()["n"];

$cursosRevision = $conexion->query(
    "SELECT COUNT(*) AS n FROM cursos WHERE aprobacion = 'en_revision'"
)->fetch_assoc()["n"];

// ---------- Cursos con alumnos y progreso ----------
$sql = "SELECT c.id, c.nombre, c.estado, c.fecha_inicio,
            (SELECT COUNT(*) FROM inscripciones i
              WHERE i.curso_id = c.id) AS alumnos,
            (SELECT COUNT(*) FROM subbloques s
              INNER JOIN bloques b ON s.bloque_id = b.id
              WHERE b.curso_id = c.id) AS lecciones,
            (SELECT COUNT(*) FROM progreso_subbloques p
              INNER JOIN inscripciones i ON p.inscripcion_id = i.id
              WHERE i.curso_id = c.id) AS completadas
        FROM cursos c
        ORDER BY c.id DESC";
$cursos = $conexion->query($sql)->fetch_all(MYSQLI_ASSOC);

// Para escalar las barras de la gráfica
$maxAlumnos = 1;
foreach ($cursos as $c) {
    $maxAlumnos = max($maxAlumnos, (int) $c["alumnos"]);
}
?>

<!-- Tarjetas -->
<div class="resumen">
    <div class="tarjeta dato enlace-tarjeta" href="admin.php?seccion=usuarios">
        <span class="numero"><?php echo (int) $totalAlumnos; ?></span>
        <span class="etiqueta-dato">Alumnos</span>
    </div>
    <div class="tarjeta dato"  href="admin.php?seccion=cursos">
        <span class="numero"><?php echo (int) $totalCursos; ?></span>
        <span class="etiqueta-dato">Cursos</span>
    </div>
    <div class="tarjeta dato"  href="admin.php?seccion=cursos">
        <span class="numero"><?php echo (int) $cursosActivos; ?></span>
        <span class="etiqueta-dato">Cursos activos</span>
    </div>
    <a class="tarjeta dato enlace-tarjeta" href="admin.php?seccion=usuarios">
        <span class="numero"><?php echo (int) $pendientes; ?></span>
        <span class="etiqueta-dato">Solicitudes pendientes</span>
    </a>
    <a class="tarjeta dato enlace-tarjeta" href="admin.php?seccion=inscripciones">
        <span class="numero"><?php echo (int) $solPendientes; ?></span>
        <span class="etiqueta-dato">Inscripciones pendientes</span>
    </a>
    <a class="tarjeta dato enlace-tarjeta" href="admin.php?seccion=revision">
        <span class="numero"><?php echo (int) $cursosRevision; ?></span>
        <span class="etiqueta-dato">Cursos en revisión</span>
    </a>

    
</div>

<?php if (count($cursos) === 0): ?>

    <div class="tarjeta">
        <p>Todavía no hay cursos. Cuando crees el primero aparecerá aquí.</p>
    </div>

<?php else: ?>

    <!-- Gráfica -->
    <div class="tarjeta">
        <h2>Alumnos por curso</h2>
        <?php foreach ($cursos as $c):
            $ancho = round($c["alumnos"] / $maxAlumnos * 100);
        ?>
            <div class="grafica-fila">
                <span class="grafica-nombre"><?php echo htmlspecialchars($c["nombre"]); ?></span>
                <div class="grafica-pista">
                    <div class="grafica-barra" style="width: <?php echo $ancho; ?>%"></div>
                </div>
                <span class="grafica-valor"><?php echo (int) $c["alumnos"]; ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Lista de cursos con progreso -->
    <div class="tarjeta">
        <h2>Progreso de los cursos</h2>
        <div class="tabla-scroll">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Curso</th>
                    <th>Estado</th>
                    <th>Inicio</th>
                    <th>Alumnos</th>
                    <th>Progreso promedio</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cursos as $c):
                $posibles = (int) $c["alumnos"] * (int) $c["lecciones"];
                $progreso = $posibles > 0 ? round($c["completadas"] / $posibles * 100) : 0;
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($c["nombre"]); ?></td>
                    <td>
                        <span class="etiqueta estado-<?php echo htmlspecialchars($c["estado"]); ?>">
                            <?php echo htmlspecialchars($c["estado"]); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo $c["fecha_inicio"]
                            ? date("d/m/Y", strtotime($c["fecha_inicio"]))
                            : "Sin fecha"; ?>
                    </td>
                    <td><?php echo (int) $c["alumnos"]; ?></td>
                    <td>
                        <div class="progreso">
                            <div class="progreso-barra" style="width: <?php echo $progreso; ?>%"></div>
                        </div>
                        <small><?php echo $progreso; ?>%</small>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

<?php endif; ?>