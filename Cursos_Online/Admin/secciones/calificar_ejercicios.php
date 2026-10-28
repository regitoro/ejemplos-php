<?php
$rolCalificar = strtolower($_SESSION["rol"] ?? "");
$uidCalificar = (int) $_SESSION["usuario_id"];
if (empty($_SESSION["csrf_ejercicios"])) {
    $_SESSION["csrf_ejercicios"] = bin2hex(random_bytes(32));
}
$condicionCurso = "";
if ($rolCalificar === "director") {
    $condicionCurso = " AND c.director_id = ?";
} elseif ($rolCalificar === "ponente") {
    $condicionCurso = " AND EXISTS (
        SELECT 1 FROM curso_ponentes cp
        WHERE cp.curso_id = c.id AND cp.usuario_id = ?
    )";
}

$sql = "SELECT ee.id AS entrega_id, ee.respuesta, ee.puntuacion, ee.comentario,
               ee.entregado_en, ee.calificado_en,
               e.titulo AS ejercicio, e.puntuacion_maxima,
               c.id AS curso_id, c.nombre AS curso,
               u.nombre AS alumno, u.email AS correo
        FROM entregas_ejercicios ee
        INNER JOIN ejercicios e ON e.id = ee.ejercicio_id
        INNER JOIN cursos c ON c.id = e.curso_id
        INNER JOIN usuarios u ON u.id = ee.alumno_id
        WHERE 1 = 1" . $condicionCurso . "
        ORDER BY (ee.puntuacion IS NULL) DESC, ee.entregado_en ASC, ee.id ASC";
$stmt = $conexion->prepare($sql);
if ($condicionCurso !== "") {
    $stmt->bind_param("i", $uidCalificar);
}
$stmt->execute();
$entregasEjercicios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$cursosEntregas = [];
foreach ($entregasEjercicios as $entrega) {
    $cursoKey = (int) $entrega["curso_id"];
    if (!isset($cursosEntregas[$cursoKey])) {
        $cursosEntregas[$cursoKey] = ["nombre" => $entrega["curso"], "pendientes" => [], "calificadas" => []];
    }
    $grupo = $entrega["puntuacion"] === null ? "pendientes" : "calificadas";
    $cursosEntregas[$cursoKey][$grupo][] = $entrega;
}
$msgCalificar = $_GET["msg"] ?? "";
?>

<?php if ($msgCalificar === "calificacion_guardada"): ?>
    <div class="aviso-admin ok">La calificación y el comentario se guardaron.</div>
<?php elseif ($msgCalificar === "calificacion_error"): ?>
    <div class="aviso-admin error">No se pudo guardar. Comprueba que la puntuación no exceda el máximo del ejercicio.</div>
<?php endif; ?>

<?php if (!$entregasEjercicios): ?>
    <div class="tarjeta"><p>Todavía no hay entregas de ejercicios para calificar.</p></div>
<?php else: ?>
    <?php foreach ($cursosEntregas as $cursoIdGrupo => $grupoCurso): ?>
        <section class="curso-entregas">
            <h2 class="curso-entregas-titulo">
                <?php echo htmlspecialchars($grupoCurso["nombre"]); ?>
                <span class="contador"><?php echo count($grupoCurso["pendientes"]) + count($grupoCurso["calificadas"]); ?> entregas</span>
            </h2>

            <details class="grupo-entregas" open>
                <summary>
                    Pendientes de calificar
                    <span class="etiqueta no"><?php echo count($grupoCurso["pendientes"]); ?></span>
                </summary>
                <?php if (!$grupoCurso["pendientes"]): ?>
                    <p class="grupo-sin-entregas">No hay entregas pendientes para este curso.</p>
                <?php else: ?>
                    <?php foreach ($grupoCurso["pendientes"] as $entrega): ?>
                        <article class="tarjeta entrega-ejercicio">
            <div class="entrega-ejercicio-cabecera">
                <div>
                    <h2><?php echo htmlspecialchars($entrega["ejercicio"]); ?></h2>
                    <p><strong>Alumno:</strong> <?php echo htmlspecialchars($entrega["alumno"]); ?>
                        · <?php echo htmlspecialchars($entrega["correo"]); ?></p>
                    <p><strong>Entregado:</strong> <?php echo date("d/m/Y H:i", strtotime($entrega["entregado_en"])); ?></p>
                </div>
                <span class="etiqueta no">Pendiente</span>
            </div>
            <div class="entrega-respuesta">
                <strong>Respuesta del alumno</strong>
                <p><?php echo nl2br(htmlspecialchars($entrega["respuesta"])); ?></p>
            </div>
            <form method="POST" action="../acciones_calificar_ejercicio.php" class="form-calificar-ejercicio">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION["csrf_ejercicios"] ?? ""); ?>">
                <input type="hidden" name="entrega_id" value="<?php echo (int) $entrega["entrega_id"]; ?>">
                <label for="puntos-<?php echo (int) $entrega["entrega_id"]; ?>">
                    Puntuación (máximo <?php echo number_format((float) $entrega["puntuacion_maxima"], 2); ?>)
                </label>
                <input id="puntos-<?php echo (int) $entrega["entrega_id"]; ?>" type="number" name="puntuacion"
                       min="0" max="<?php echo htmlspecialchars((string) $entrega["puntuacion_maxima"]); ?>"
                       step="0.01" required value="<?php echo $entrega["puntuacion"] === null ? "" : htmlspecialchars((string) $entrega["puntuacion"]); ?>">
                <label for="comentario-<?php echo (int) $entrega["entrega_id"]; ?>">Comentario para el alumno</label>
                <textarea id="comentario-<?php echo (int) $entrega["entrega_id"]; ?>" name="comentario" maxlength="5000" rows="3"
                          placeholder="Escribe observaciones o recomendaciones…"><?php echo htmlspecialchars($entrega["comentario"] ?? ""); ?></textarea>
                <button type="submit" class="btn-principal">
                    <?php echo $entrega["puntuacion"] === null ? "Calificar entrega" : "Actualizar calificación"; ?>
                </button>
            </form>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </details>

            <details class="grupo-entregas">
                <summary>
                    Calificadas
                    <span class="etiqueta ok"><?php echo count($grupoCurso["calificadas"]); ?></span>
                </summary>
                <?php if (!$grupoCurso["calificadas"]): ?>
                    <p class="grupo-sin-entregas">Aún no hay entregas calificadas para este curso.</p>
                <?php else: ?>
                    <?php foreach ($grupoCurso["calificadas"] as $entrega): ?>
                        <article class="tarjeta entrega-ejercicio">
                            <div class="entrega-ejercicio-cabecera">
                                <div>
                                    <h2><?php echo htmlspecialchars($entrega["ejercicio"]); ?></h2>
                                    <p><strong>Alumno:</strong> <?php echo htmlspecialchars($entrega["alumno"]); ?>
                                        · <?php echo htmlspecialchars($entrega["correo"]); ?></p>
                                    <p><strong>Calificado:</strong> <?php echo $entrega["calificado_en"] ? date("d/m/Y H:i", strtotime($entrega["calificado_en"])) : "—"; ?></p>
                                </div>
                                <span class="etiqueta ok"><?php echo number_format((float) $entrega["puntuacion"], 2); ?> / <?php echo number_format((float) $entrega["puntuacion_maxima"], 2); ?></span>
                            </div>
                            <details class="ver-entrega-calificada">
                                <summary>Ver respuesta y comentario</summary>
                                <div class="entrega-respuesta">
                                    <strong>Respuesta del alumno</strong>
                                    <p><?php echo nl2br(htmlspecialchars($entrega["respuesta"])); ?></p>
                                </div>
                                <?php if (trim((string) $entrega["comentario"]) !== ""): ?>
                                    <div class="entrega-respuesta comentario-calificacion">
                                        <strong>Comentario enviado</strong>
                                        <p><?php echo nl2br(htmlspecialchars($entrega["comentario"])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <form method="POST" action="../acciones_calificar_ejercicio.php" class="form-calificar-ejercicio">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION["csrf_ejercicios"] ?? ""); ?>">
                                    <input type="hidden" name="entrega_id" value="<?php echo (int) $entrega["entrega_id"]; ?>">
                                    <label for="puntos-<?php echo (int) $entrega["entrega_id"]; ?>">Actualizar puntuación (máximo <?php echo number_format((float) $entrega["puntuacion_maxima"], 2); ?>)</label>
                                    <input id="puntos-<?php echo (int) $entrega["entrega_id"]; ?>" type="number" name="puntuacion" min="0" max="<?php echo htmlspecialchars((string) $entrega["puntuacion_maxima"]); ?>" step="0.01" required value="<?php echo htmlspecialchars((string) $entrega["puntuacion"]); ?>">
                                    <label for="comentario-<?php echo (int) $entrega["entrega_id"]; ?>">Comentario para el alumno</label>
                                    <textarea id="comentario-<?php echo (int) $entrega["entrega_id"]; ?>" name="comentario" maxlength="5000" rows="3"><?php echo htmlspecialchars($entrega["comentario"] ?? ""); ?></textarea>
                                    <button type="submit" class="btn-principal">Actualizar calificación</button>
                                </form>
                            </details>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </details>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
