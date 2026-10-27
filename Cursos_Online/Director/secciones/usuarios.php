<?php
$dirId = (int) $_SESSION["usuario_id"];

// Si se pidió ver un usuario, mostrar su detalle y terminar aquí
if (isset($_GET["ver"])) {
    include __DIR__ . "/usuario_detalle.php";
    return;
}

$mensajes = [
    "inscripcion_quitada" => ["ok",    "Alumno quitado del curso, junto con su progreso."],
    "ponente_quitado"     => ["ok",    "Ponente quitado del curso."],
    "todo_quitado"        => ["ok",    "Usuario quitado de tus cursos."],
    "error"               => ["error", "No se pudo completar la acción."],
];
$msg = $_GET["msg"] ?? "";

// ---------- Filtros ----------
$q = trim($_GET["q"] ?? "");
if (strlen($q) > 100) {
    $q = substr($q, 0, 100);
}
$like = "%" . addcslashes($q, "%_\\") . "%";

// Mis cursos, para el selector
$stmt = $conexion->prepare("SELECT id, nombre FROM cursos WHERE director_id = ? ORDER BY nombre");
$stmt->bind_param("i", $dirId);
$stmt->execute();
$misCursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// El curso del filtro debe ser mío; si no, se ignora
$cursoFiltro = (int) ($_GET["curso"] ?? 0);
if (!in_array($cursoFiltro, array_map(fn($c) => (int) $c["id"], $misCursos), true)) {
    $cursoFiltro = 0;
}

// ---------- Mis cursos (todos, o solo el filtrado) ----------
$stmt = $conexion->prepare(
    "SELECT c.id, c.nombre, c.estado,
            (SELECT COUNT(*) FROM subbloques s
               INNER JOIN bloques b ON s.bloque_id = b.id
              WHERE b.curso_id = c.id) AS lecciones
     FROM cursos c
     WHERE c.director_id = ? AND (? = 0 OR c.id = ?)
     ORDER BY c.nombre"
);
$stmt->bind_param("iii", $dirId, $cursoFiltro, $cursoFiltro);
$stmt->execute();
$cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- Alumnos, agrupados por curso ----------
$stmt = $conexion->prepare(
    "SELECT i.id AS inscripcion_id, i.curso_id, u.id, u.nombre, u.email,
            (SELECT COUNT(*) FROM progreso_subbloques p
              WHERE p.inscripcion_id = i.id) AS hechas
     FROM inscripciones i
     INNER JOIN usuarios u ON i.usuario_id = u.id
     INNER JOIN cursos c ON i.curso_id = c.id
     WHERE c.director_id = ? AND (? = 0 OR c.id = ?)
       AND (u.nombre LIKE ? OR u.email LIKE ?)
     ORDER BY u.nombre"
);
$stmt->bind_param("iiiss", $dirId, $cursoFiltro, $cursoFiltro, $like, $like);
$stmt->execute();
$alumnosPorCurso = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $a) {
    $alumnosPorCurso[(int) $a["curso_id"]][] = $a;
}
$stmt->close();

// ---------- Ponentes, agrupados por curso ----------
$stmt = $conexion->prepare(
    "SELECT cp.curso_id, u.id, u.nombre, u.email
     FROM curso_ponentes cp
     INNER JOIN usuarios u ON cp.usuario_id = u.id
     INNER JOIN cursos c ON cp.curso_id = c.id
     WHERE c.director_id = ? AND (? = 0 OR c.id = ?)
       AND (u.nombre LIKE ? OR u.email LIKE ?)
     ORDER BY u.nombre"
);
$stmt->bind_param("iiiss", $dirId, $cursoFiltro, $cursoFiltro, $like, $like);
$stmt->execute();
$ponentesPorCurso = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
    $ponentesPorCurso[(int) $p["curso_id"]][] = $p;
}
$stmt->close();

// Si se está buscando, solo se muestran los cursos con coincidencias
$visibles = array_filter($cursos, function ($c) use ($q, $alumnosPorCurso, $ponentesPorCurso) {
    return $q === ""
        || !empty($alumnosPorCurso[(int) $c["id"]])
        || !empty($ponentesPorCurso[(int) $c["id"]]);
});

// Campos ocultos para que quitar vuelva a esta misma lista, con el filtro y la búsqueda
$ocultosVolver = '<input type="hidden" name="volver" value="lista">'
               . '<input type="hidden" name="curso" value="' . $cursoFiltro . '">'
               . '<input type="hidden" name="q" value="' . htmlspecialchars($q) . '">';
?>

<?php if (isset($mensajes[$msg])): ?>
    <div class="aviso-admin <?php echo $mensajes[$msg][0]; ?>">
        <?php echo htmlspecialchars($mensajes[$msg][1]); ?>
    </div>
<?php endif; ?>

<form method="GET" action="director.php" class="buscador">
    <input type="hidden" name="seccion" value="usuarios">

    <select name="curso" class="select-linea" style="flex: 0 1 260px;"
            title="Filtrar por curso" onchange="this.form.submit()">
        <option value="0">Todos mis cursos</option>
        <?php foreach ($misCursos as $c): ?>
            <option value="<?php echo (int) $c["id"]; ?>"
                <?php echo $cursoFiltro === (int) $c["id"] ? "selected" : ""; ?>>
                <?php echo htmlspecialchars($c["nombre"]); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <input type="search" name="q" maxlength="100" placeholder="Buscar por nombre o correo…"
           value="<?php echo htmlspecialchars($q); ?>">

    <button type="submit" class="btn-principal">Buscar</button>
    <?php if ($q !== "" || $cursoFiltro > 0): ?>
        <a href="director.php?seccion=usuarios" class="btn-link">Limpiar</a>
    <?php endif; ?>
</form>

<?php if (count($misCursos) === 0): ?>

    <div class="tarjeta">
        <p>Todavía no tienes cursos, así que no hay usuarios que mostrar.</p>
    </div>

<?php elseif (count($visibles) === 0): ?>

    <div class="tarjeta">
        <p>No se encontraron usuarios con esos criterios en tus cursos.</p>
    </div>

<?php endif; ?>

<?php foreach ($visibles as $c):
    $alumnos   = $alumnosPorCurso[(int) $c["id"]] ?? [];
    $ponentes  = $ponentesPorCurso[(int) $c["id"]] ?? [];
?>
    <div class="tarjeta">
        <h2>
            <?php echo htmlspecialchars($c["nombre"]); ?>
            <span class="etiqueta estado-<?php echo htmlspecialchars($c["estado"]); ?>">
                <?php echo htmlspecialchars($c["estado"]); ?>
            </span>
            <span class="contador"><?php echo count($alumnos); ?></span>
        </h2>

        <?php if (count($alumnos) === 0 && count($ponentes) === 0): ?>
            <p>Este curso todavía no tiene alumnos ni ponentes.</p>
        <?php endif; ?>

        <?php if (count($alumnos) > 0): ?>
            <h3 class="subtitulo-seccion">Alumnos</h3>
            <ul class="lista-personas">
                <?php foreach ($alumnos as $a):
                    $pct = $c["lecciones"] > 0 ? round($a["hechas"] / $c["lecciones"] * 100) : 0;
                ?>
                    <li>
                        <div>
                            <a class="enlace-nombre"
                               href="director.php?seccion=usuarios&ver=<?php echo (int) $a["id"]; ?>">
                                <?php echo htmlspecialchars($a["nombre"]); ?>
                            </a>
                            <small><?php echo htmlspecialchars($a["email"]); ?></small>
                        </div>
                        <div class="progreso-alumno">
                            <div class="progreso">
                                <div class="progreso-barra" style="width: <?php echo $pct; ?>%"></div>
                            </div>
                            <small><?php echo $pct; ?>% (<?php echo (int) $a["hechas"]; ?> de <?php echo (int) $c["lecciones"]; ?>)</small>
                        </div>
                        <form method="POST" action="../acciones_usuarios_director.php"
                              onsubmit="return confirm('¿Quitar a este alumno del curso? Se borrará también su progreso.');">
                            <input type="hidden" name="accion" value="quitar_inscripcion">
                            <input type="hidden" name="inscripcion_id" value="<?php echo (int) $a["inscripcion_id"]; ?>">
                            <input type="hidden" name="usuario_id" value="<?php echo (int) $a["id"]; ?>">
                            <?php echo $ocultosVolver; ?>
                            <button type="submit" class="btn-peligro mini">Quitar</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (count($ponentes) > 0): ?>
            <h3 class="subtitulo-seccion">Ponentes</h3>
            <ul class="lista-personas">
                <?php foreach ($ponentes as $p): ?>
                    <li>
                        <div>
                            <a class="enlace-nombre"
                               href="director.php?seccion=usuarios&ver=<?php echo (int) $p["id"]; ?>">
                                <?php echo htmlspecialchars($p["nombre"]); ?>
                            </a>
                            <small><?php echo htmlspecialchars($p["email"]); ?></small>
                        </div>
                        <form method="POST" action="../acciones_usuarios_director.php"
                              onsubmit="return confirm('¿Quitar a este ponente del curso?');">
                            <input type="hidden" name="accion" value="quitar_ponente">
                            <input type="hidden" name="curso_id" value="<?php echo (int) $c["id"]; ?>">
                            <input type="hidden" name="usuario_id" value="<?php echo (int) $p["id"]; ?>">
                            <?php echo $ocultosVolver; ?>
                            <button type="submit" class="btn-peligro mini">Quitar</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endforeach; ?>