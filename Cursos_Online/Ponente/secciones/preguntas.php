<?php
$ponenteId = (int) $_SESSION["usuario_id"];

$stmt = $conexion->prepare(
    "SELECT pp.id, pp.pregunta, pp.respuesta, pp.estado, pp.creado_en, pp.respondido_en,
            u.nombre AS alumno, c.nombre AS curso, s.titulo AS leccion
     FROM preguntas_ponente pp
     INNER JOIN usuarios u ON u.id = pp.alumno_id
     INNER JOIN cursos c ON c.id = pp.curso_id
     LEFT JOIN subbloques s ON s.id = pp.subbloque_id
     WHERE pp.ponente_id = ?
     ORDER BY (pp.estado = 'pendiente') DESC, pp.creado_en DESC, pp.id DESC"
);
$stmt->bind_param("i", $ponenteId);
$stmt->execute();
$preguntas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$msg = $_GET["msg"] ?? "";
?>

<?php if ($msg === "respuesta_enviada"): ?>
    <div class="aviso-admin ok">La respuesta fue enviada al alumno.</div>
<?php elseif ($msg === "respuesta_error"): ?>
    <div class="aviso-admin error">No se pudo enviar la respuesta. Revisa el texto e inténtalo de nuevo.</div>
<?php endif; ?>

<?php if (!$preguntas): ?>
    <div class="tarjeta"><p>Todavía no tienes preguntas de alumnos.</p></div>
<?php else: ?>
    <?php foreach ($preguntas as $pregunta): ?>
        <article class="tarjeta pregunta-ponente">
            <div class="pregunta-ponente-cabecera">
                <div>
                    <h2><?php echo htmlspecialchars($pregunta["curso"]); ?></h2>
                    <p><strong>Lección:</strong> <?php echo htmlspecialchars($pregunta["leccion"] ?? "Lección eliminada"); ?></p>
                    <p><strong>Alumno:</strong> <?php echo htmlspecialchars($pregunta["alumno"]); ?>
                        · <?php echo date("d/m/Y H:i", strtotime($pregunta["creado_en"])); ?></p>
                </div>
                <span class="etiqueta <?php echo $pregunta["estado"] === "pendiente" ? "no" : "ok"; ?>">
                    <?php echo $pregunta["estado"] === "pendiente" ? "Pendiente" : "Respondida"; ?>
                </span>
            </div>
            <div class="pregunta-ponente-texto">
                <strong>Pregunta</strong>
                <p><?php echo nl2br(htmlspecialchars($pregunta["pregunta"])); ?></p>
            </div>

            <?php if ($pregunta["estado"] === "pendiente"): ?>
                <form method="POST" action="../acciones_preguntas_ponente.php" class="respuesta-ponente-form">
                    <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION["csrf_preguntas"]); ?>">
                    <input type="hidden" name="pregunta_id" value="<?php echo (int) $pregunta["id"]; ?>">
                    <label for="respuesta-<?php echo (int) $pregunta["id"]; ?>">Tu respuesta</label>
                    <textarea id="respuesta-<?php echo (int) $pregunta["id"]; ?>" name="respuesta"
                              maxlength="3000" rows="4" required placeholder="Escribe una respuesta clara para el alumno…"></textarea>
                    <button type="submit" class="btn-principal">Enviar respuesta</button>
                </form>
            <?php else: ?>
                <div class="respuesta-ponente">
                    <strong>Tu respuesta · <?php echo date("d/m/Y H:i", strtotime($pregunta["respondido_en"])); ?></strong>
                    <p><?php echo nl2br(htmlspecialchars($pregunta["respuesta"] ?? "")); ?></p>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
