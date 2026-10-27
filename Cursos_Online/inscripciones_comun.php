<?php
// Lo incluye cada panel (Admin y Director).
// Necesita $conexion y $panel (por ejemplo "admin.php").

$mensajes = [
    "insc_creadas"         => ["ok",    "Alumnos inscritos correctamente."],
    "insc_quitada"         => ["ok",    "Inscripción eliminada, junto con el progreso del alumno en ese curso."],
    "solicitud_aprobada"   => ["ok",    "Solicitud aprobada: el alumno ya está inscrito."],
    "solicitud_rechazada"  => ["ok",    "Solicitud rechazada."],
    "solicitud_invalida"   => ["error", "Esa solicitud ya no está pendiente."],
    "insc_nada"            => ["error", "No se inscribió a nadie: elige al menos un alumno disponible."],
    "error"                => ["error", "No se pudo completar la acción."],
];
$msg = $_GET["msg"] ?? "";

// Alcance: el director solo ve sus cursos; el admin (0) ve todos
$dirId = strtolower($_SESSION["rol"] ?? "") === "director" ? (int) $_SESSION["usuario_id"] : 0;

// ---------- Orden ----------
// Lista blanca: al SQL solo llega el texto de este arreglo, nunca lo que escribe el usuario
$ordenes = [
    "fecha_desc"    => ["Ordenar: más recientes primero",   "i.inscrito_en DESC, i.id DESC"],
    "fecha_asc"     => ["Ordenar: más antiguas primero",    "i.inscrito_en ASC, i.id ASC"],
    "curso"         => ["Ordenar: curso (A–Z) y luego fecha", "c.nombre ASC, i.inscrito_en DESC, i.id DESC"],
    "alumno"        => ["Ordenar: alumno (A–Z)",            "u.nombre ASC, c.nombre ASC"],
    "progreso_desc" => ["Ordenar: progreso, mayor a menor", "COALESCE(hechas / NULLIF(lecciones, 0), 0) DESC, u.nombre ASC"],
    "progreso_asc"  => ["Ordenar: progreso, menor a mayor", "COALESCE(hechas / NULLIF(lecciones, 0), 0) ASC, u.nombre ASC"],
];
$orden = $_GET["orden"] ?? "fecha_desc";
if (!isset($ordenes[$orden])) {
    $orden = "fecha_desc";
}

// ---------- Filtros ----------
$q = trim($_GET["q"] ?? "");
if (strlen($q) > 100) {
    $q = substr($q, 0, 100);
}
$like = "%" . addcslashes($q, "%_\\") . "%";

// Campos ocultos para que los formularios de acciones conserven el orden y la búsqueda
$ocultosVolver = '<input type="hidden" name="orden" value="' . htmlspecialchars($orden) . '">'
               . '<input type="hidden" name="q" value="' . htmlspecialchars($q) . '">';

// Cursos que esta persona puede gestionar, para el selector
$stmt = $conexion->prepare(
    "SELECT id, nombre, estado FROM cursos
     WHERE (? = 0 OR director_id = ?)
     ORDER BY nombre"
);
$stmt->bind_param("ii", $dirId, $dirId);
$stmt->execute();
$cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// El curso del filtro debe estar entre los permitidos; si no, se ignora
$cursoFiltro = (int) ($_GET["curso"] ?? 0);
$cursoSel = null;
foreach ($cursos as $c) {
    if ((int) $c["id"] === $cursoFiltro) {
        $cursoSel = $c;
    }
}
if ($cursoSel === null) {
    $cursoFiltro = 0;
}

// ---------- Solicitudes pendientes (la más antigua primero) ----------
$stmt = $conexion->prepare(
    "SELECT s.id, s.creada_en, u.nombre, u.email,
            c.nombre AS curso, c.estado
     FROM solicitudes_inscripcion s
     INNER JOIN usuarios u ON s.usuario_id = u.id
     INNER JOIN cursos c ON s.curso_id = c.id
     WHERE s.estado = 'pendiente'
       AND (? = 0 OR c.director_id = ?)
       AND (? = 0 OR s.curso_id = ?)
       AND (u.nombre LIKE ? OR u.email LIKE ?)
     ORDER BY s.creada_en, s.id"
);
$stmt->bind_param("iiiiss", $dirId, $dirId, $cursoFiltro, $cursoFiltro, $like, $like);
$stmt->execute();
$solicitudes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- Inscripciones ----------
$limite = 200;
$sql = "SELECT i.id, i.inscrito_en,
               u.nombre, u.email,
               c.id AS curso_id, c.nombre AS curso, c.estado,
               (SELECT COUNT(*) FROM subbloques s
                 INNER JOIN bloques b ON s.bloque_id = b.id
                 WHERE b.curso_id = c.id) AS lecciones,
               (SELECT COUNT(*) FROM progreso_subbloques p
                 WHERE p.inscripcion_id = i.id) AS hechas
        FROM inscripciones i
        INNER JOIN usuarios u ON i.usuario_id = u.id
        INNER JOIN cursos c ON i.curso_id = c.id
        WHERE (? = 0 OR c.director_id = ?)
          AND (? = 0 OR i.curso_id = ?)
          AND (u.nombre LIKE ? OR u.email LIKE ?)
        ORDER BY " . $ordenes[$orden][1] . "
        LIMIT " . $limite;
$stmt = $conexion->prepare($sql);
$stmt->bind_param("iiiiss", $dirId, $dirId, $cursoFiltro, $cursoFiltro, $like, $like);
$stmt->execute();
$inscripciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- Alumnos disponibles para el curso elegido ----------
$disponibles = [];
if ($cursoSel !== null) {
    $stmt = $conexion->prepare(
        "SELECT u.id, u.nombre, u.email
         FROM usuarios u
         INNER JOIN roles r ON u.rol_id = r.id
         WHERE r.nombre = 'alumno' AND u.aceptado = 1
           AND u.id NOT IN (SELECT usuario_id FROM inscripciones WHERE curso_id = ?)
         ORDER BY u.nombre"
    );
    $stmt->bind_param("i", $cursoFiltro);
    $stmt->execute();
    $disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>



<?php if (isset($mensajes[$msg])): ?>
    <div class="aviso-admin <?php echo $mensajes[$msg][0]; ?>">
        <?php echo htmlspecialchars($mensajes[$msg][1]); ?>
    </div>
<?php endif; ?>

<!-- Filtros y orden -->
<div class="tarjeta">
    <form method="GET" action="<?php echo htmlspecialchars($panel); ?>" class="buscador">
        <input type="hidden" name="seccion" value="inscripciones">

        <select name="curso" class="select-linea" style="flex: 0 1 260px;"
                title="Filtrar por curso" onchange="this.form.submit()">
            <option value="0">Todos los cursos</option>
            <?php foreach ($cursos as $c): ?>
                <option value="<?php echo (int) $c["id"]; ?>"
                    <?php echo $cursoFiltro === (int) $c["id"] ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($c["nombre"] . " (" . $c["estado"] . ")"); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="orden" class="select-linea" style="flex: 0 1 280px;"
                title="Ordenar la lista de inscripciones" onchange="this.form.submit()">
            <?php foreach ($ordenes as $clave => $datos): ?>
                <option value="<?php echo $clave; ?>" <?php echo $orden === $clave ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($datos[0]); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="search" name="q" maxlength="100" placeholder="Buscar alumno por nombre o correo…"
               value="<?php echo htmlspecialchars($q); ?>">

        <button type="submit" class="btn-principal">Buscar</button>
        <?php if ($q !== "" || $cursoFiltro > 0 || $orden !== "fecha_desc"): ?>
            <a href="<?php echo htmlspecialchars($panel); ?>?seccion=inscripciones" class="btn-link">Limpiar</a>
        <?php endif; ?>
    </form>
</div>


<!-- Solicitudes pendientes -->
<div class="tarjeta">
    <h2>Solicitudes pendientes <span class="contador"><?php echo count($solicitudes); ?></span></h2>

    <?php if (count($solicitudes) === 0): ?>
        <p>No hay solicitudes de inscripción pendientes.</p>
    <?php else: ?>
        <div class="tabla-scroll">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Curso</th>
                    <th>Solicitada</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($solicitudes as $s): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($s["nombre"]); ?></strong><br>
                        <small><?php echo htmlspecialchars($s["email"]); ?></small>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($s["curso"]); ?>
                        <span class="etiqueta estado-<?php echo htmlspecialchars($s["estado"]); ?>">
                            <?php echo htmlspecialchars($s["estado"]); ?>
                        </span>
                    </td>
                    <td><?php echo date("d/m/Y H:i", strtotime($s["creada_en"])); ?></td>
                    <td>
                        <div class="acciones">
                            <?php foreach (["aprobar" => ["Aprobar", "btn-ok"], "rechazar" => ["Rechazar", "btn-peligro"]] as $acc => [$texto, $clase]): ?>
                                <form method="POST" action="../acciones_inscripciones.php">
                                    <!-- aprobar o rechazar -->
                                    <input type="hidden" name="accion" value="<?php echo $acc; ?>"> 
                                    <?php echo $ocultosVolver; ?>
                                    <input type="hidden" name="solicitud_id" value="<?php echo (int) $s["id"]; ?>">
                                    <input type="hidden" name="curso_filtro" value="<?php echo $cursoFiltro; ?>">
                                    <button type="submit" class="<?php echo $clase; ?> mini"
                                            style="border:none;border-radius:8px;padding:6px 10px;cursor:pointer;font-weight:600;color:#fff;font-size:0.85rem;">
                                        <?php echo $texto; ?>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<!-- Inscribir alumnos manualmente (solo cuando hay un curso elegido) -->
<?php if ($cursoSel !== null): ?>
    <div class="tarjeta">
        <h2>Inscribir alumnos en “<?php echo htmlspecialchars($cursoSel["nombre"]); ?>”</h2>

        <?php if (count($disponibles) === 0): ?>
            <p class="nota">
                No hay más alumnos disponibles para este curso. Los alumnos aparecen aquí
                cuando se registran y se aprueban en la sección Usuarios.
            </p>
        <?php else: ?>
            <form method="POST" action="../acciones_inscripciones.php" class="formulario">
                <input type="hidden" name="accion" value="inscribir">
                <?php echo $ocultosVolver; ?>
                <input type="hidden" name="curso_id" value="<?php echo $cursoFiltro; ?>">

                <div class="lista-checks">
                    <?php foreach ($disponibles as $d): ?>
                        <label>
                            <input type="checkbox" name="usuario_ids[]" value="<?php echo (int) $d["id"]; ?>">
                            <?php echo htmlspecialchars($d["nombre"] . " (" . $d["email"] . ")"); ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="acciones-form">
                    <button type="submit" class="btn-principal">Inscribir seleccionados</button>
                    <button type="button" class="btn-link"
                            onclick="document.querySelectorAll('.lista-checks input').forEach(c => c.checked = true);">
                        Seleccionar todos
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
<?php else: ?>
    <p class="nota">Elige un curso en el filtro para poder inscribir alumnos en él manualmente.</p>
<?php endif; ?>

<!-- Lista de inscripciones -->
<div class="tarjeta">
    <h2>Inscripciones <span class="contador"><?php echo count($inscripciones); ?></span></h2>

    <?php if (count($inscripciones) === 0): ?>
        <p>No hay inscripciones con esos criterios.</p>
    <?php else: ?>
        <div class="tabla-scroll">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Curso</th>
                    <th>Inscrito</th>
                    <th>Progreso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($inscripciones as $i):
                $pct = $i["lecciones"] > 0 ? round($i["hechas"] / $i["lecciones"] * 100) : 0;
            ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($i["nombre"]); ?></strong><br>
                        <small><?php echo htmlspecialchars($i["email"]); ?></small>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($i["curso"]); ?>
                        <span class="etiqueta estado-<?php echo htmlspecialchars($i["estado"]); ?>">
                            <?php echo htmlspecialchars($i["estado"]); ?>
                        </span>
                    </td>
                    <td><?php echo date("d/m/Y", strtotime($i["inscrito_en"])); ?></td>
                    <td>
                        <div class="progreso">
                            <div class="progreso-barra" style="width: <?php echo $pct; ?>%"></div>
                        </div>
                        <small><?php echo $pct; ?>% (<?php echo (int) $i["hechas"]; ?> de <?php echo (int) $i["lecciones"]; ?>)</small>
                    </td>
                    <td>
                        <form method="POST" action="../acciones_inscripciones.php"
                              onsubmit="return confirm('¿Quitar esta inscripción? Se borrará también el progreso del alumno en el curso.');">
                            <input type="hidden" name="accion" value="quitar">
                            <?php echo $ocultosVolver; ?>
                            <input type="hidden" name="inscripcion_id" value="<?php echo (int) $i["id"]; ?>">
                            <input type="hidden" name="curso_filtro" value="<?php echo $cursoFiltro; ?>">
                            <button type="submit" class="btn-peligro mini">Quitar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if (count($inscripciones) === $limite): ?>
            <p class="nota">Se muestran las <?php echo $limite; ?> inscripciones más recientes. Usa los filtros para acotar la búsqueda.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>