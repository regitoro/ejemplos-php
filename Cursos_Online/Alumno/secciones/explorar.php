<?php
require_once __DIR__ . "/../../ui_icons.php";
$mensajesSol = [
    "solicitud_enviada"   => ["ok",    "Solicitud enviada. Te avisaremos cuando sea revisada: aparecerá el curso en Mis cursos si se aprueba."],
    "solicitud_existe"    => ["ok",    "Ya tenías una solicitud pendiente para ese curso."],
    "solicitud_cancelada" => ["ok",    "Solicitud cancelada."],
    "error"               => ["error", "No se pudo completar la acción."],
];
$msgSol = $_GET["msg"] ?? "";

// Texto buscado (máximo 100 caracteres)
$q = trim($_GET["q"] ?? "");
if (strlen($q) > 100) {
    $q = substr($q, 0, 100);
}

// Escapar % y _ para que se busquen como texto normal y no como comodines
$like = "%" . addcslashes($q, "%_\\") . "%";

$sql = "SELECT c.id, c.nombre, c.descripcion, c.estado, c.fecha_inicio, c.fecha_fin,
               (SELECT COUNT(*) FROM subbloques s
                 INNER JOIN bloques b ON s.bloque_id = b.id
                 WHERE b.curso_id = c.id) AS lecciones,
               (SELECT GROUP_CONCAT(u.nombre ORDER BY u.nombre SEPARATOR ', ')
                 FROM curso_ponentes cp
                 INNER JOIN usuarios u ON cp.usuario_id = u.id
                 WHERE cp.curso_id = c.id) AS ponentes,
               (SELECT COUNT(*) FROM inscripciones i
                 WHERE i.curso_id = c.id AND i.usuario_id = ?) AS inscrito,
               (SELECT sl.estado FROM solicitudes_inscripcion sl
                 WHERE sl.curso_id = c.id AND sl.usuario_id = ?) AS solicitud
        FROM cursos c
        WHERE c.estado <> 'borrador'
          AND c.nombre LIKE ?
          AND NOT EXISTS (
              SELECT 1 FROM inscripciones i
              WHERE i.curso_id = c.id AND i.usuario_id = ?
          )
        ORDER BY c.nombre";
$stmt = $conexion->prepare($sql);
    $stmt->bind_param("iisi", $id, $id, $like, $id);
$stmt->execute();
$cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Temario de los cursos visibles: títulos de bloques y lecciones, nunca su contenido.
$temarios = [];
if ($cursos) {
    $idsCursos = implode(",", array_map(fn($curso) => (int) $curso["id"], $cursos));
    $resultadoTemario = $conexion->query(
        "SELECT b.curso_id, b.id AS bloque_id, b.titulo AS bloque,
                s.id AS leccion_id, s.titulo AS leccion
         FROM bloques b
         LEFT JOIN subbloques s ON s.bloque_id = b.id
         WHERE b.curso_id IN ($idsCursos)
         ORDER BY b.orden, b.id, s.orden, s.id"
    );

    while ($fila = $resultadoTemario->fetch_assoc()) {
        $cursoId = (int) $fila["curso_id"];
        $bloqueId = (int) $fila["bloque_id"];
        if (!isset($temarios[$cursoId][$bloqueId])) {
            $temarios[$cursoId][$bloqueId] = ["titulo" => $fila["bloque"], "lecciones" => []];
        }
        if ($fila["leccion_id"] !== null) {
            $temarios[$cursoId][$bloqueId]["lecciones"][] = $fila["leccion"];
        }
    }
}
?>

<?php if (isset($mensajesSol[$msgSol])): ?>
    <div class="aviso-admin <?php echo $mensajesSol[$msgSol][0]; ?>">
        <?php echo htmlspecialchars($mensajesSol[$msgSol][1]); ?>
    </div>
<?php endif; ?>

<form method="GET" action="alumno.php" class="buscador">
    <input type="hidden" name="seccion" value="explorar">
    <input type="search" name="q" maxlength="100" placeholder="Buscar curso por nombre…"
           value="<?php echo htmlspecialchars($q); ?>">
    <button type="submit" class="btn-principal">Buscar</button>
    <?php if ($q !== ""): ?>
        <a href="alumno.php?seccion=explorar" class="btn-link">Limpiar</a>
    <?php endif; ?>
</form>

<?php if ($q !== ""): ?>
    <p class="resultado-busqueda">
        <?php echo count($cursos); ?> resultado(s) para
        “<strong><?php echo htmlspecialchars($q); ?></strong>”
    </p>
<?php endif; ?>

<?php if (count($cursos) === 0): ?>

    <div class="tarjeta">
        <p>
            <?php echo $q !== ""
                ? "No se encontró ningún curso con ese nombre."
                : "Todavía no hay cursos disponibles."; ?>
        </p>
    </div>

<?php else: ?>

    <div class="explorar-grid">
        <?php foreach ($cursos as $c): ?>
            <?php
            $bloquesCurso = $temarios[(int) $c["id"]] ?? [];
            ?>
            <div class="tarjeta curso-card">
                <div class="curso-card-cabecera">
                    <strong><?php echo htmlspecialchars($c["nombre"]); ?></strong>
                    <span class="etiqueta estado-<?php echo htmlspecialchars($c["estado"]); ?>">
                        <?php echo htmlspecialchars($c["estado"]); ?>
                    </span>
                </div>

                <p class="curso-desc">
                    <?php echo trim((string) $c["descripcion"]) !== ""
                        ? htmlspecialchars($c["descripcion"])
                        : "Sin descripción."; ?>
                </p>

                <ul class="curso-meta">
                    <li><span class="meta-icono"><?php echo iconoInterfaz("ponente"); ?></span><?php echo $c["ponentes"] ? htmlspecialchars($c["ponentes"]) : "Sin ponente asignado"; ?></li>
                    <li><span class="meta-icono"><?php echo iconoInterfaz("fecha"); ?></span><?php echo $c["fecha_inicio"] ? date("d/m/Y", strtotime($c["fecha_inicio"])) : "Sin fecha"; ?>
                        <?php echo $c["fecha_fin"] ? " – " . date("d/m/Y", strtotime($c["fecha_fin"])) : ""; ?></li>
                    <li><span class="meta-icono"><?php echo iconoInterfaz("leccion"); ?></span><?php echo (int) $c["lecciones"]; ?> lecciones</li>
                </ul>

                <details class="curso-temario">
                    <summary>Ver bloques y lecciones</summary>
                    <?php if (!$bloquesCurso): ?>
                        <p class="temario-vacio">El temario se publicará próximamente.</p>
                    <?php else: ?>
                        <ol class="temario-bloques">
                            <?php foreach ($bloquesCurso as $bloque): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($bloque["titulo"]); ?></strong>
                                    <?php if ($bloque["lecciones"]): ?>
                                        <ul>
                                            <?php foreach ($bloque["lecciones"] as $tituloLeccion): ?>
                                                <li><?php echo htmlspecialchars($tituloLeccion); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <small>Lecciones por publicar</small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </details>

                <?php if ($c["inscrito"]): ?>

                    <a href="curso.php?id=<?php echo (int) $c["id"]; ?>" class="btn-principal">Abrir curso</a>

                <?php elseif ($c["solicitud"] === "pendiente"): ?>

                    <span class="etiqueta estado-borrador">Solicitud pendiente</span>
                    <form method="POST" action="acciones_solicitud.php">
                        <input type="hidden" name="accion" value="cancelar">
                        <input type="hidden" name="curso_id" value="<?php echo (int) $c["id"]; ?>">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                        <button type="submit" class="btn-link">Cancelar solicitud</button>
                    </form>

                <?php elseif ($c["estado"] === "activo"): ?>

                    <?php if ($c["solicitud"] === "rechazada"): ?>
                        <span class="no-inscrito">Tu solicitud anterior fue rechazada.</span>
                    <?php endif; ?>
                    <form method="POST" action="acciones_solicitud.php">
                        <input type="hidden" name="accion" value="solicitar">
                        <input type="hidden" name="curso_id" value="<?php echo (int) $c["id"]; ?>">
                        <input type="hidden" name="q" value="<?php echo htmlspecialchars($q); ?>">
                        <button type="submit" class="btn-principal">
                            <?php echo $c["solicitud"] === "rechazada" ? "Solicitar de nuevo" : "Solicitar inscripción"; ?>
                        </button>
                    </form>

                <?php else: ?>

                    <span class="no-inscrito">Este curso ya no recibe inscripciones.</span>

                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>