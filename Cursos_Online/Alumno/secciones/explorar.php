<?php
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
        WHERE c.estado <> 'borrador' AND c.nombre LIKE ?
        ORDER BY c.nombre";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("iis", $id, $id, $like);
$stmt->execute();
$cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
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
                    <li>👨‍🏫 <?php echo $c["ponentes"] ? htmlspecialchars($c["ponentes"]) : "Sin ponente asignado"; ?></li>
                    <li>📅 <?php echo $c["fecha_inicio"] ? date("d/m/Y", strtotime($c["fecha_inicio"])) : "Sin fecha"; ?>
                        <?php echo $c["fecha_fin"] ? " – " . date("d/m/Y", strtotime($c["fecha_fin"])) : ""; ?></li>
                    <li>📖 <?php echo (int) $c["lecciones"]; ?> lecciones</li>
                </ul>

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