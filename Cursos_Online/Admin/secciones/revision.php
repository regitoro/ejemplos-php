<?php
$mensajes = [
    "curso_aprobado"       => ["ok",    "Curso aprobado. El director ya puede publicarlo."],
    "cambios_pedidos"      => ["ok",    "Curso devuelto al director con tus comentarios."],
    "comentario_requerido" => ["error", "Escribe un comentario para que el director sepa qué cambiar."],
    "error"                => ["error", "No se pudo completar la acción. Puede que el curso ya no esté en revisión."],
];
$msg = $_GET["msg"] ?? "";

// Cursos esperando revisión, los más antiguos primero
$cursos = $conexion->query(
    "SELECT c.id, c.nombre, c.descripcion, c.fecha_inicio, c.fecha_fin, c.enviado_en,
            (SELECT u.nombre FROM usuarios u WHERE u.id = c.director_id) AS director
     FROM cursos c
     WHERE c.aprobacion = 'en_revision'
     ORDER BY c.enviado_en IS NULL, c.enviado_en, c.id"
)->fetch_all(MYSQLI_ASSOC);

// Contenido de todos esos cursos, en una sola consulta: [curso][bloque] => título + lecciones
$estructura = [];
if (count($cursos) > 0) {
    $ids = implode(",", array_map(fn($c) => (int) $c["id"], $cursos));   // ids enteros de la BD

    $res = $conexion->query(
        "SELECT b.curso_id, b.id AS bloque_id, b.titulo AS bloque,
                s.id AS sub_id, s.titulo AS sub, s.contenido
         FROM bloques b
         LEFT JOIN subbloques s ON s.bloque_id = b.id
         WHERE b.curso_id IN ($ids)
         ORDER BY b.orden, b.id, s.orden, s.id"
    );
    while ($f = $res->fetch_assoc()) {
        $c = (int) $f["curso_id"];
        $b = (int) $f["bloque_id"];
        if (!isset($estructura[$c][$b])) {
            $estructura[$c][$b] = ["titulo" => $f["bloque"], "subs" => []];
        }
        if ($f["sub_id"] !== null) {
            $estructura[$c][$b]["subs"][] = ["titulo" => $f["sub"], "contenido" => $f["contenido"]];
        }
    }
}
?>

<?php if (isset($mensajes[$msg])): ?>
    <div class="aviso-admin <?php echo $mensajes[$msg][0]; ?>">
        <?php echo htmlspecialchars($mensajes[$msg][1]); ?>
    </div>
<?php endif; ?>

<?php if (count($cursos) === 0): ?>

    <div class="tarjeta">
        <p>No hay cursos esperando revisión.</p>
    </div>

<?php endif; ?>

<?php foreach ($cursos as $c):
    $bloques = $estructura[(int) $c["id"]] ?? [];
    $totalLecciones = 0;
    foreach ($bloques as $b) {
        $totalLecciones += count($b["subs"]);
    }
?>
    <div class="tarjeta">
        <h2>
            <?php echo htmlspecialchars($c["nombre"]); ?>
            <span class="etiqueta rev-en_revision">En revisión</span>
        </h2>

        <ul class="datos-usuario">
            <li><strong>Director</strong>
                <span><?php echo $c["director"] ? htmlspecialchars($c["director"]) : "Sin director"; ?></span></li>
            <li><strong>Enviado</strong>
                <span><?php echo $c["enviado_en"] ? date("d/m/Y H:i", strtotime($c["enviado_en"])) : "—"; ?></span></li>
            <li><strong>Fechas</strong>
                <span>
                    <?php echo $c["fecha_inicio"] ? date("d/m/Y", strtotime($c["fecha_inicio"])) : "Sin inicio"; ?>
                    –
                    <?php echo $c["fecha_fin"] ? date("d/m/Y", strtotime($c["fecha_fin"])) : "Sin fin"; ?>
                </span></li>
            <li><strong>Contenido</strong>
                <span><?php echo count($bloques); ?> bloques, <?php echo $totalLecciones; ?> lecciones</span></li>
        </ul>

        <?php if (trim((string) $c["descripcion"]) !== ""): ?>
            <p class="revision-desc"><?php echo nl2br(htmlspecialchars($c["descripcion"])); ?></p>
        <?php endif; ?>

        <details class="editar">
            <summary>Ver contenido del curso</summary>

            <?php if (count($bloques) === 0): ?>
                <p class="nota">El curso no tiene bloques.</p>
            <?php endif; ?>

            <?php foreach ($bloques as $b): ?>
                <div class="estructura-bloque">
                    <strong><?php echo htmlspecialchars($b["titulo"]); ?></strong>

                    <?php if (count($b["subs"]) === 0): ?>
                        <p class="nota">Sin lecciones.</p>
                    <?php endif; ?>

                    <?php foreach ($b["subs"] as $s): ?>
                        <details class="estructura-sub">
                            <summary>• <?php echo htmlspecialchars($s["titulo"]); ?></summary>
                            <div class="al-contenido">
                                <?php echo trim((string) $s["contenido"]) !== ""
                                    ? nl2br(htmlspecialchars($s["contenido"]))
                                    : "<em>Sin contenido.</em>"; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </details>

        <div class="acciones-revision">
            <form method="POST" action="acciones_revision.php"
                  onsubmit="return confirm('¿Aprobar este curso? El director podrá publicarlo.');">
                <input type="hidden" name="accion" value="aprobar">
                <input type="hidden" name="id" value="<?php echo (int) $c["id"]; ?>">
                <button type="submit" class="btn-ok btn-grande">Aprobar</button>
            </form>

            <a href="admin.php?seccion=cursos&gestionar=<?php echo (int) $c["id"]; ?>" class="btn-link">
                Abrir curso completo
            </a>
        </div>

        <details class="editar">
            <summary>Pedir cambios al director</summary>
            <form method="POST" action="acciones_revision.php" class="formulario">
                <input type="hidden" name="accion" value="rechazar">
                <input type="hidden" name="id" value="<?php echo (int) $c["id"]; ?>">
                <div class="campo-admin">
                    <label for="com<?php echo (int) $c["id"]; ?>">¿Qué debe cambiar?</label>
                    <textarea id="com<?php echo (int) $c["id"]; ?>" name="comentario" rows="3"
                              maxlength="1000" required
                              placeholder="Ej.: Falta contenido en el bloque 2."></textarea>
                </div>
                <div class="acciones-form">
                    <button type="submit" class="btn-peligro btn-grande">Devolver con comentarios</button>
                </div>
            </form>
        </details>
    </div>
<?php endforeach; ?>