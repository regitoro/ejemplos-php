<?php
session_start();
require_once __DIR__ . "/../conexion.php";   

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../login.php"); 
    exit;
}

$conexion   = conectar();
$usuario_id = (int) $_SESSION["usuario_id"];
$curso_id   = (int) ($_GET["id"] ?? 0);
if (empty($_SESSION["csrf_ejercicios"])) {
    $_SESSION["csrf_ejercicios"] = bin2hex(random_bytes(32));
}

// 1. El alumno debe estar inscrito y el curso no puede ser borrador
$stmt = $conexion->prepare(
    "SELECT i.id AS inscripcion_id, c.nombre, c.descripcion, c.fecha_inicio, c.fecha_fin, c.estado
     FROM inscripciones i
     INNER JOIN cursos c ON i.curso_id = c.id
     WHERE i.curso_id = ? AND i.usuario_id = ? AND c.estado <> 'borrador'"
);
$stmt->bind_param("ii", $curso_id, $usuario_id);
$stmt->execute();
$curso = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$curso) {
    header("Location: alumno.php");
    exit;
}

// Ejercicios y entrega del alumno. La nota individual solo se muestra después de calificar.
$stmt = $conexion->prepare(
    "SELECT e.id, e.titulo, e.instrucciones, e.puntuacion_maxima,
            ee.id AS entrega_id, ee.respuesta, ee.puntuacion, ee.comentario,
            ee.entregado_en, ee.calificado_en
     FROM ejercicios e
     LEFT JOIN entregas_ejercicios ee
            ON ee.ejercicio_id = e.id AND ee.alumno_id = ?
     WHERE e.curso_id = ?
     ORDER BY e.creado_en, e.id"
);
$stmt->bind_param("ii", $usuario_id, $curso_id);
$stmt->execute();
$ejercicios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$promedioFinal = null;
if ($curso["estado"] === "finalizado") {
    $stmt = $conexion->prepare(
        "SELECT AVG(ee.puntuacion / e.puntuacion_maxima * 100) AS promedio,
                COUNT(*) AS calificados
         FROM entregas_ejercicios ee
         INNER JOIN ejercicios e ON e.id = ee.ejercicio_id
         WHERE e.curso_id = ? AND ee.alumno_id = ? AND ee.puntuacion IS NOT NULL"
    );
    $stmt->bind_param("ii", $curso_id, $usuario_id);
    $stmt->execute();
    $promedio = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ((int) $promedio["calificados"] > 0) {
        $promedioFinal = (float) $promedio["promedio"];
    }
}

$inscripcion_id = (int) $curso["inscripcion_id"];

// 2. Lecciones con su estado, ordenadas por bloque
$sql = "SELECT s.id, s.titulo, s.contenido, b.id AS bloque_id, b.titulo AS bloque,
               (p.subbloque_id IS NOT NULL) AS completada
        FROM bloques b
        INNER JOIN subbloques s ON s.bloque_id = b.id
        LEFT JOIN progreso_subbloques p
               ON p.subbloque_id = s.id AND p.inscripcion_id = ?
        WHERE b.curso_id = ?
        ORDER BY b.orden, b.id, s.orden, s.id";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $inscripcion_id, $curso_id);
$stmt->execute();
$lecciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3. Progreso, calculado con las mismas lecciones
$total      = count($lecciones);
$hechas     = count(array_filter($lecciones, fn($l) => $l["completada"]));
$porcentaje = $total > 0 ? round($hechas / $total * 100) : 0;

// 4. Ponentes del curso
$stmt = $conexion->prepare(
    "SELECT u.nombre
     FROM curso_ponentes cp
     INNER JOIN usuarios u ON cp.usuario_id = u.id
     WHERE cp.curso_id = ?
     ORDER BY u.nombre"
);
$stmt->bind_param("i", $curso_id);
$stmt->execute();
$ponentes = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), "nombre");
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($curso["nombre"]); ?></title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="alumno.css">
</head>
<body class="app">
<main class="card ancha">

   <p><a href="alumno.php?seccion=mis_cursos" class="al-volver">← Mis cursos</a></p>

    <div class="encabezado">
        <h1><?php echo htmlspecialchars($curso["nombre"]); ?></h1>
    </div>

    <?php if (!empty($curso["descripcion"])): ?>
        <p><?php echo nl2br(htmlspecialchars($curso["descripcion"])); ?></p>
    <?php endif; ?>

    <ul class="datos">
        <?php if ($ponentes): ?>
            <li><strong>Ponentes</strong> <span><?php echo htmlspecialchars(implode(", ", $ponentes)); ?></span></li>
        <?php endif; ?>
        <li><strong>Inicio</strong>
            <span><?php echo $curso["fecha_inicio"] ? date("d/m/Y", strtotime($curso["fecha_inicio"])) : "Sin fecha"; ?></span></li>
        <li><strong>Fin</strong>
            <span><?php echo $curso["fecha_fin"] ? date("d/m/Y", strtotime($curso["fecha_fin"])) : "Sin fecha"; ?></span></li>
    </ul>

    <h2>Tu progreso</h2>
    <div class="al-progreso grande"><div style="width: <?php echo $porcentaje; ?>%"></div></div>
    <p><?php echo $porcentaje; ?>% (<?php echo $hechas; ?> de <?php echo $total; ?> lecciones)
        <?php echo ($total > 0 && $hechas === $total) ? " 🎉 ¡Curso completado!" : ""; ?></p>

    <h2>Contenido</h2>

    <?php if ($total === 0): ?>
        <p>Este curso todavía no tiene lecciones.</p>
    <?php endif; ?>

    <?php
    $bloqueActual = null;
    foreach ($lecciones as $l):
        if ($bloqueActual !== $l["bloque_id"]):
            $bloqueActual = $l["bloque_id"];
    ?>
        <h3 class="al-bloque"><?php echo htmlspecialchars($l["bloque"]); ?></h3>
    <?php endif; ?>

    <div class="leccion <?php echo $l["completada"] ? 'hecha' : ''; ?>" id="l<?php echo (int) $l["id"]; ?>">
    <form method="POST" action="completar_leccion.php">
        <input type="hidden" name="subbloque_id" value="<?php echo (int) $l["id"]; ?>">
        <button type="submit" class="check"
                title="<?php echo $l["completada"] ? 'Desmarcar lección' : 'Marcar como completada'; ?>">
            <?php echo $l["completada"] ? "✔" : ""; ?>
        </button>
    </form>

    <a class="al-leccion-enlace" href="leccion.php?id=<?php echo (int) $l["id"]; ?>">
        <?php echo htmlspecialchars($l["titulo"]); ?>
    </a>
</div>

    <?php endforeach; ?>

    <section class="ejercicios-alumno">
        <h2>Ejercicios</h2>
        <?php if (($_GET["msg"] ?? "") === "ejercicio_entregado"): ?>
            <p class="aviso-ejercicio ok">Tu respuesta se guardó correctamente.</p>
        <?php elseif (($_GET["msg"] ?? "") === "ejercicio_error"): ?>
            <p class="aviso-ejercicio error">No se pudo guardar la respuesta. Inténtalo otra vez.</p>
        <?php elseif (($_GET["msg"] ?? "") === "ejercicio_calificado"): ?>
            <p class="aviso-ejercicio error">Este ejercicio ya fue calificado y no admite cambios.</p>
        <?php endif; ?>

        <?php if ($curso["estado"] === "finalizado"): ?>
            <div class="calificacion-final">
                <strong>Calificación final del curso</strong>
                <?php if ($promedioFinal === null): ?>
                    <p>Aún no hay ejercicios calificados para calcular el promedio.</p>
                <?php else: ?>
                    <p><?php echo number_format($promedioFinal, 2); ?> / 100</p>
                    <small>Promedio de los ejercicios entregados y calificados, normalizado sobre 100.</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!$ejercicios): ?>
            <p>Este curso todavía no tiene ejercicios.</p>
        <?php else: ?>
            <div class="ejercicios-alumno-lista">
                <?php foreach ($ejercicios as $ejercicio): ?>
                    <article class="ejercicio-alumno">
                        <div class="ejercicio-alumno-cabecera">
                            <h3><?php echo htmlspecialchars($ejercicio["titulo"]); ?></h3>
                            <span>Máximo: <?php echo number_format((float) $ejercicio["puntuacion_maxima"], 2); ?> puntos</span>
                        </div>
                        <p class="ejercicio-instrucciones"><?php echo nl2br(htmlspecialchars($ejercicio["instrucciones"])); ?></p>

                        <?php if ($ejercicio["entrega_id"] === null): ?>
                            <?php if ($curso["estado"] === "finalizado"): ?>
                                <p class="ejercicio-pendiente">El curso finalizó y ya no se aceptan nuevas entregas.</p>
                            <?php else: ?>
                            <form method="POST" action="../acciones_entrega_ejercicio.php" class="form-entrega-ejercicio">
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION["csrf_ejercicios"]); ?>">
                                <input type="hidden" name="curso_id" value="<?php echo $curso_id; ?>">
                                <input type="hidden" name="ejercicio_id" value="<?php echo (int) $ejercicio["id"]; ?>">
                                <label for="respuesta-ejercicio-<?php echo (int) $ejercicio["id"]; ?>">Tu respuesta</label>
                                <textarea id="respuesta-ejercicio-<?php echo (int) $ejercicio["id"]; ?>" name="respuesta" rows="5" maxlength="60000" required placeholder="Escribe tu respuesta aquí…"></textarea>
                                <button type="submit" class="btn">Enviar respuesta</button>
                            </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="respuesta-alumno">
                                <strong>Tu respuesta · <?php echo date("d/m/Y H:i", strtotime($ejercicio["entregado_en"])); ?></strong>
                                <p><?php echo nl2br(htmlspecialchars($ejercicio["respuesta"])); ?></p>
                            </div>
                            <?php if ($ejercicio["puntuacion"] === null): ?>
                                <p class="ejercicio-pendiente">Entregado; pendiente de calificación.</p>
                                <details class="editar respuesta-editar">
                                    <summary>Editar mi respuesta</summary>
                                    <form method="POST" action="../acciones_entrega_ejercicio.php" class="form-entrega-ejercicio">
                                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION["csrf_ejercicios"]); ?>">
                                        <input type="hidden" name="curso_id" value="<?php echo $curso_id; ?>">
                                        <input type="hidden" name="ejercicio_id" value="<?php echo (int) $ejercicio["id"]; ?>">
                                        <label for="respuesta-editar-<?php echo (int) $ejercicio["id"]; ?>">Actualizar respuesta</label>
                                        <textarea id="respuesta-editar-<?php echo (int) $ejercicio["id"]; ?>" name="respuesta" rows="5" maxlength="60000" required><?php echo htmlspecialchars($ejercicio["respuesta"]); ?></textarea>
                                        <button type="submit" class="btn secundario">Actualizar entrega</button>
                                    </form>
                                </details>
                            <?php else: ?>
                                <div class="resultado-ejercicio">
                                    <strong>Calificación: <?php echo number_format((float) $ejercicio["puntuacion"], 2); ?> / <?php echo number_format((float) $ejercicio["puntuacion_maxima"], 2); ?></strong>
                                    <span>(<?php echo number_format((float) $ejercicio["puntuacion"] / (float) $ejercicio["puntuacion_maxima"] * 100, 2); ?>%)</span>
                                    <?php if (trim((string) $ejercicio["comentario"]) !== ""): ?>
                                        <p><strong>Comentario del evaluador:</strong><br><?php echo nl2br(htmlspecialchars($ejercicio["comentario"])); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</main>
</body>
</html>