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
               e.titulo AS ejercicio, e.instrucciones, e.puntuacion_maxima, e.tipo,
           b.titulo AS bloque,
               c.id AS curso_id, c.nombre AS curso,
           u.id AS alumno_id, u.nombre AS alumno, u.email AS correo, r.nombre AS rol_alumno
        FROM entregas_ejercicios ee
        INNER JOIN ejercicios e ON e.id = ee.ejercicio_id
        INNER JOIN cursos c ON c.id = e.curso_id
        INNER JOIN usuarios u ON u.id = ee.alumno_id
    INNER JOIN roles r ON r.id = u.rol_id
    LEFT JOIN bloques b ON b.id = e.bloque_id
        WHERE 1 = 1" . $condicionCurso . "
        ORDER BY (ee.puntuacion IS NULL) DESC, ee.entregado_en ASC, ee.id ASC";
$stmt = $conexion->prepare($sql);
if ($condicionCurso !== "") {
    $stmt->bind_param("i", $uidCalificar);
}
$stmt->execute();
$entregasEjercicios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$pendientes = array_values(array_filter(
    $entregasEjercicios,
    fn($entrega) => $entrega["puntuacion"] === null && $entrega["tipo"] !== "multiple"
));
$cursosEntregas = [];
foreach ($entregasEjercicios as $entrega) {
    $cursoKey = (int) $entrega["curso_id"];
    if (!isset($cursosEntregas[$cursoKey])) {
        $cursosEntregas[$cursoKey] = ["nombre" => $entrega["curso"], "pendientes" => [], "calificadas" => []];
    }
    $grupo = $entrega["puntuacion"] === null ? "pendientes" : "calificadas";
    $cursosEntregas[$cursoKey][$grupo][] = $entrega;
}
$revisarId = (int) ($_GET["revisar"] ?? 0);
$indiceRevision = null;
foreach ($pendientes as $indice => $pendiente) {
    if ((int) $pendiente["entrega_id"] === $revisarId) {
        $indiceRevision = $indice;
        break;
    }
}
$entregaActiva = $indiceRevision === null ? null : $pendientes[$indiceRevision];
$urlPanel = ["admin" => "admin.php", "director" => "director.php", "ponente" => "ponente.php"][$rolCalificar] ?? "admin.php";
$msgCalificar = $_GET["msg"] ?? "";
?>

<?php if ($msgCalificar === "calificacion_guardada"): ?>
    <div class="aviso-admin ok">La calificación y el comentario se guardaron.</div>
<?php elseif ($msgCalificar === "calificacion_error"): ?>
    <div class="aviso-admin error">No se pudo guardar. Comprueba que la puntuación no exceda el máximo del ejercicio.</div>
<?php endif; ?>

<?php if ($revisarId > 0 && !$entregaActiva): ?>
    <div class="aviso-admin error">Esta entrega ya no está pendiente o no tienes permiso para calificarla.</div>
<?php endif; ?>

<?php if ($entregaActiva): ?>
    <?php
    $anteriorRevision = $indiceRevision > 0 ? $pendientes[$indiceRevision - 1] : null;
    $siguienteRevision = $indiceRevision + 1 < count($pendientes) ? $pendientes[$indiceRevision + 1] : null;
    ?>
    <section class="revision-ejercicio">
        <div class="revision-progreso">
            <a class="btn-link revision-flecha <?php echo $anteriorRevision ? '' : 'deshabilitado'; ?>"
               href="<?php echo $anteriorRevision ? $urlPanel . '?seccion=calificar_ejercicios&revisar=' . (int) $anteriorRevision['entrega_id'] : '#'; ?>"
               <?php echo $anteriorRevision ? '' : 'aria-disabled="true" tabindex="-1"'; ?> aria-label="Ejercicio pendiente anterior">←</a>
            <div>
                <strong>Revisión de entregas pendientes</strong>
                <span>Ejercicio <?php echo $indiceRevision + 1; ?> de <?php echo count($pendientes); ?></span>
            </div>
            <a class="btn-link revision-flecha <?php echo $siguienteRevision ? '' : 'deshabilitado'; ?>"
               href="<?php echo $siguienteRevision ? $urlPanel . '?seccion=calificar_ejercicios&revisar=' . (int) $siguienteRevision['entrega_id'] : '#'; ?>"
               <?php echo $siguienteRevision ? '' : 'aria-disabled="true" tabindex="-1"'; ?> aria-label="Siguiente ejercicio pendiente">→</a>
        </div>

        <article class="tarjeta entrega-ejercicio revision-entrega">
            <div class="entrega-ejercicio-cabecera">
                <div>
                    <span class="revision-contexto"><?php echo htmlspecialchars($entregaActiva["curso"]); ?> · <?php echo htmlspecialchars($entregaActiva["bloque"] ?? "Sin bloque"); ?></span>
                    <h2><?php echo htmlspecialchars($entregaActiva["ejercicio"]); ?></h2>
                    <p>Entregado el <?php echo date("d/m/Y H:i", strtotime($entregaActiva["entregado_en"])); ?></p>
                </div>
                <span class="etiqueta no">Pendiente</span>
            </div>

            <section class="datos-alumno-calificacion">
                <div class="datos-alumno-avatar" aria-hidden="true"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($entregaActiva["alumno"], 0, 1, "UTF-8"), "UTF-8")); ?></div>
                <div>
                    <span class="revision-contexto">Información del alumno</span>
                    <strong><?php echo htmlspecialchars($entregaActiva["alumno"]); ?></strong>
                    <span><?php echo htmlspecialchars($entregaActiva["correo"]); ?></span>
                </div>
                <dl>
                    <div><dt>ID de usuario</dt><dd><?php echo (int) $entregaActiva["alumno_id"]; ?></dd></div>
                    <div><dt>Rol</dt><dd><?php echo htmlspecialchars($entregaActiva["rol_alumno"]); ?></dd></div>
                </dl>
            </section>

            <div class="entrega-respuesta revision-instrucciones">
                <strong>Pregunta o instrucciones</strong>
                <p><?php echo nl2br(htmlspecialchars($entregaActiva["instrucciones"])); ?></p>
            </div>

            <div class="entrega-respuesta revision-respuesta">
                <strong>Respuesta del alumno</strong>
                <p><?php echo nl2br(htmlspecialchars($entregaActiva["respuesta"])); ?></p>
            </div>

            <form method="POST" action="../acciones_calificar_ejercicio.php" class="form-calificar-ejercicio revision-formulario">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION["csrf_ejercicios"] ?? ""); ?>">
                <input type="hidden" name="entrega_id" value="<?php echo (int) $entregaActiva["entrega_id"]; ?>">
                <input type="hidden" name="revisar" value="<?php echo (int) $entregaActiva["entrega_id"]; ?>">
                <label for="puntos-activos">Calificación <span>Máximo <?php echo number_format((float) $entregaActiva["puntuacion_maxima"], 2); ?> puntos</span></label>
                <input id="puntos-activos" type="number" name="puntuacion" min="0" max="<?php echo htmlspecialchars((string) $entregaActiva["puntuacion_maxima"]); ?>" step="0.01" required autofocus>
                <label for="comentario-activo">Retroalimentación <span>Visible para el alumno</span></label>
                <textarea id="comentario-activo" name="comentario" maxlength="5000" rows="5" placeholder="Escribe observaciones concretas y recomendaciones para mejorar…"></textarea>
                <div class="revision-acciones">
                    <a class="btn-link" href="<?php echo $urlPanel; ?>?seccion=calificar_ejercicios">← Volver a la lista</a>
                    <button type="submit" class="btn-principal">Guardar calificación y continuar →</button>
                </div>
            </form>
        </article>
    </section>
<?php endif; ?>

<?php if (!$entregaActiva && !$entregasEjercicios): ?>
    <div class="tarjeta"><p>Todavía no hay entregas de ejercicios para calificar.</p></div>
<?php elseif (!$entregaActiva): ?>
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
                        <a class="pendiente-ejercicio-fila <?php echo (int) $entrega["entrega_id"] === $revisarId ? 'activo' : ''; ?>"
                           href="<?php echo $urlPanel; ?>?seccion=calificar_ejercicios&revisar=<?php echo (int) $entrega["entrega_id"]; ?>">
                            <span class="pendiente-ejercicio-icono" aria-hidden="true">↗</span>
                            <span class="pendiente-ejercicio-detalle">
                                <strong><?php echo htmlspecialchars($entrega["ejercicio"]); ?></strong>
                                <small><?php echo htmlspecialchars($entrega["bloque"] ?? "Sin bloque"); ?> · <?php echo htmlspecialchars($entrega["alumno"]); ?> · <?php echo date("d/m/Y", strtotime($entrega["entregado_en"])); ?></small>
                            </span>
                            <span class="etiqueta no">Calificar →</span>
                        </a>
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
                                <span class="etiqueta ok"><?php echo $entrega["tipo"] === "multiple" ? "Auto · " : ""; ?><?php echo number_format((float) $entrega["puntuacion"], 2); ?> / <?php echo number_format((float) $entrega["puntuacion_maxima"], 2); ?></span>
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
                                <?php if ($entrega["tipo"] !== "multiple"): ?>
                                <form method="POST" action="../acciones_calificar_ejercicio.php" class="form-calificar-ejercicio">
                                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION["csrf_ejercicios"] ?? ""); ?>">
                                    <input type="hidden" name="entrega_id" value="<?php echo (int) $entrega["entrega_id"]; ?>">
                                    <label for="puntos-<?php echo (int) $entrega["entrega_id"]; ?>">Actualizar puntuación (máximo <?php echo number_format((float) $entrega["puntuacion_maxima"], 2); ?>)</label>
                                    <input id="puntos-<?php echo (int) $entrega["entrega_id"]; ?>" type="number" name="puntuacion" min="0" max="<?php echo htmlspecialchars((string) $entrega["puntuacion_maxima"]); ?>" step="0.01" required value="<?php echo htmlspecialchars((string) $entrega["puntuacion"]); ?>">
                                    <label for="comentario-<?php echo (int) $entrega["entrega_id"]; ?>">Comentario para el alumno</label>
                                    <textarea id="comentario-<?php echo (int) $entrega["entrega_id"]; ?>" name="comentario" maxlength="5000" rows="3"><?php echo htmlspecialchars($entrega["comentario"] ?? ""); ?></textarea>
                                    <button type="submit" class="btn-principal">Actualizar calificación</button>
                                </form>
                                <?php else: ?>
                                    <p class="nota">Esta respuesta fue calificada automáticamente.</p>
                                <?php endif; ?>
                            </details>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </details>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
