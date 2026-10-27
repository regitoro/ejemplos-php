<?php
$ponId = (int) $_SESSION["usuario_id"];

// ---------- Búsqueda ----------
$q = trim($_GET["q"] ?? "");
if (strlen($q) > 100) {
    $q = substr($q, 0, 100);
}
$like = "%" . addcslashes($q, "%_\\") . "%";

// ---------- Mis cursos, con su equipo ----------
$stmt = $conexion->prepare(
    "SELECT c.id, c.nombre, c.estado,
            (SELECT u.nombre FROM usuarios u WHERE u.id = c.director_id) AS director,
            (SELECT GROUP_CONCAT(u.nombre ORDER BY u.nombre SEPARATOR ', ')
               FROM curso_ponentes cp2
               INNER JOIN usuarios u ON cp2.usuario_id = u.id
              WHERE cp2.curso_id = c.id AND cp2.usuario_id <> ?) AS otros,
            (SELECT COUNT(*) FROM subbloques s
               INNER JOIN bloques b ON s.bloque_id = b.id
              WHERE b.curso_id = c.id) AS lecciones
     FROM cursos c
     INNER JOIN curso_ponentes cp ON cp.curso_id = c.id
     WHERE cp.usuario_id = ?
     ORDER BY c.nombre"
);
$stmt->bind_param("ii", $ponId, $ponId);
$stmt->execute();
$cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- Alumnos de esos cursos, agrupados por curso ----------
$stmt = $conexion->prepare(
    "SELECT i.curso_id, u.id, u.nombre, u.email,
            (SELECT COUNT(*) FROM progreso_subbloques p
              WHERE p.inscripcion_id = i.id) AS hechas
     FROM inscripciones i
     INNER JOIN usuarios u ON i.usuario_id = u.id
     INNER JOIN curso_ponentes cp ON cp.curso_id = i.curso_id
     WHERE cp.usuario_id = ?
       AND (u.nombre LIKE ? OR u.email LIKE ?)
     ORDER BY u.nombre"
);
$stmt->bind_param("iss", $ponId, $like, $like);
$stmt->execute();
$alumnosPorCurso = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $a) {
    $alumnosPorCurso[(int) $a["curso_id"]][] = $a;
}
$stmt->close();

// Si se está buscando, solo se muestran los cursos con coincidencias
$visibles = array_filter(
    $cursos,
    fn($c) => $q === "" || !empty($alumnosPorCurso[(int) $c["id"]])
);
?>

<form method="GET" action="ponente.php" class="buscador">
    <input type="hidden" name="seccion" value="usuarios">
    <input type="search" name="q" maxlength="100" placeholder="Buscar alumno por nombre o correo…"
           value="<?php echo htmlspecialchars($q); ?>">
    <button type="submit" class="btn-principal">Buscar</button>
    <?php if ($q !== ""): ?>
        <a href="ponente.php?seccion=usuarios" class="btn-link">Limpiar</a>
    <?php endif; ?>
</form>

<?php if (count($cursos) === 0): ?>

    <div class="tarjeta">
        <p>Todavía no tienes cursos asignados, así que no hay usuarios que mostrar.</p>
    </div>

<?php elseif (count($visibles) === 0): ?>

    <div class="tarjeta">
        <p>No se encontraron alumnos con ese nombre o correo en tus cursos.</p>
    </div>

<?php endif; ?>

<?php foreach ($visibles as $c):
    $alumnos = $alumnosPorCurso[(int) $c["id"]] ?? [];
?>
    <div class="tarjeta">
        <h2>
            <?php echo htmlspecialchars($c["nombre"]); ?>
            <span class="etiqueta estado-<?php echo htmlspecialchars($c["estado"]); ?>">
                <?php echo htmlspecialchars($c["estado"]); ?>
            </span>
            <span class="contador"><?php echo count($alumnos); ?></span>
        </h2>

        <p class="nota">
            <strong>Director:</strong>
            <?php echo $c["director"] ? htmlspecialchars($c["director"]) : "sin director"; ?>
            ·
            <strong>Otros ponentes:</strong>
            <?php echo $c["otros"] ? htmlspecialchars($c["otros"]) : "ninguno"; ?>
        </p>

        <?php if (count($alumnos) === 0): ?>
            <p>Este curso todavía no tiene alumnos inscritos.</p>
        <?php else: ?>
            <ul class="lista-personas">
                <?php foreach ($alumnos as $a):
                    $pct = $c["lecciones"] > 0 ? round($a["hechas"] / $c["lecciones"] * 100) : 0;
                ?>
                    <li>
                        <div>
                            <strong><?php echo htmlspecialchars($a["nombre"]); ?></strong>
                            <small><?php echo htmlspecialchars($a["email"]); ?></small>
                        </div>
                        <div class="progreso-alumno">
                            <div class="progreso">
                                <div class="progreso-barra" style="width: <?php echo $pct; ?>%"></div>
                            </div>
                            <small><?php echo $pct; ?>% (<?php echo (int) $a["hechas"]; ?> de <?php echo (int) $c["lecciones"]; ?>)</small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endforeach; ?>