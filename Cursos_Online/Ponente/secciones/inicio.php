<?php
$ponId = (int) $_SESSION["usuario_id"];

// Cuenta filas con una consulta que recibe solo el id del ponente
function contarDe(mysqli $c, string $sql, int $id): int
{
    $stmt = $c->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $n = (int) $stmt->get_result()->fetch_assoc()["n"];
    $stmt->close();
    return $n;
}

$totalCursos = contarDe($conexion,
    "SELECT COUNT(*) AS n FROM curso_ponentes WHERE usuario_id = ?", $ponId);

$totalAlumnos = contarDe($conexion,
    "SELECT COUNT(DISTINCT i.usuario_id) AS n
     FROM inscripciones i
     INNER JOIN curso_ponentes cp ON cp.curso_id = i.curso_id
     WHERE cp.usuario_id = ?", $ponId);

$cursosActivos = contarDe($conexion,
    "SELECT COUNT(*) AS n
     FROM curso_ponentes cp
     INNER JOIN cursos c ON cp.curso_id = c.id
     WHERE cp.usuario_id = ? AND c.estado = 'activo'", $ponId);

// Mis cursos con alumnos y progreso promedio
$stmt = $conexion->prepare(
    "SELECT c.id, c.nombre, c.estado,
            (SELECT COUNT(*) FROM inscripciones i
              WHERE i.curso_id = c.id) AS alumnos,
            (SELECT COUNT(*) FROM subbloques s
              INNER JOIN bloques b ON s.bloque_id = b.id
              WHERE b.curso_id = c.id) AS lecciones,
            (SELECT COUNT(*) FROM progreso_subbloques p
              INNER JOIN inscripciones i ON p.inscripcion_id = i.id
              WHERE i.curso_id = c.id) AS completadas
     FROM cursos c
     INNER JOIN curso_ponentes cp ON cp.curso_id = c.id
     WHERE cp.usuario_id = ?
     ORDER BY c.nombre"
);
$stmt->bind_param("i", $ponId);
$stmt->execute();
$cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="resumen">
    <div class="tarjeta dato">
        <span class="numero"><?php echo $totalCursos; ?></span>
        <span class="etiqueta-dato">Cursos asignados</span>
    </div>
    <div class="tarjeta dato">
        <span class="numero"><?php echo $totalAlumnos; ?></span>
        <span class="etiqueta-dato">Alumnos</span>
    </div>
    <div class="tarjeta dato">
        <span class="numero"><?php echo $cursosActivos; ?></span>
        <span class="etiqueta-dato">Cursos activos</span>
    </div>
</div>

<?php if (count($cursos) === 0): ?>

    <div class="tarjeta">
        <p>Todavía no tienes cursos asignados. Cuando un director o el administrador te asigne uno, aparecerá aquí.</p>
    </div>

<?php else: ?>

    <div class="tarjeta">
        <h2>Progreso de mis cursos</h2>
        <div class="tabla-scroll">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Curso</th>
                    <th>Estado</th>
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
                    <td>
                        <a class="enlace-nombre"
                           href="ponente.php?seccion=cursos&gestionar=<?php echo (int) $c["id"]; ?>">
                            <?php echo htmlspecialchars($c["nombre"]); ?>
                        </a>
                    </td>
                    <td>
                        <span class="etiqueta estado-<?php echo htmlspecialchars($c["estado"]); ?>">
                            <?php echo htmlspecialchars($c["estado"]); ?>
                        </span>
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