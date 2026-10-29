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
        "SELECT e.id, e.bloque_id, e.tipo, b.titulo AS bloque,
             e.titulo, e.instrucciones, e.puntuacion_maxima,
            ee.id AS entrega_id, ee.respuesta, ee.puntuacion, ee.comentario,
            ee.entregado_en, ee.calificado_en
     FROM ejercicios e
         LEFT JOIN bloques b ON b.id = e.bloque_id
     LEFT JOIN entregas_ejercicios ee
            ON ee.ejercicio_id = e.id AND ee.alumno_id = ?
     WHERE e.curso_id = ?
         ORDER BY b.orden, b.id, e.creado_en, e.id"
);
$stmt->bind_param("ii", $usuario_id, $curso_id);
$stmt->execute();
$ejercicios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$ejerciciosPorBloque = [];
$opcionesPorEjercicio = [];
$opcionCorrectaPorEjercicio = [];
if ($ejercicios) {
    $idsEjercicios = implode(",", array_map(fn($ejercicio) => (int) $ejercicio["id"], $ejercicios));
    $resultadoOpciones = $conexion->query(
        "SELECT id, ejercicio_id, texto, orden
         FROM opciones_ejercicio
         WHERE ejercicio_id IN ($idsEjercicios)
         ORDER BY ejercicio_id, orden, id"
    );
    while ($opcion = $resultadoOpciones->fetch_assoc()) {
        $opcionesPorEjercicio[(int) $opcion["ejercicio_id"]][] = $opcion;
    }
    foreach ($ejercicios as $ejercicio) {
        $claveBloque = $ejercicio["bloque_id"] === null ? 0 : (int) $ejercicio["bloque_id"];
        if (!isset($ejerciciosPorBloque[$claveBloque])) {
            $ejerciciosPorBloque[$claveBloque] = [
                "titulo" => $ejercicio["bloque"] ?? "Ejercicios sin bloque",
                "ejercicios" => [],
            ];
        }
        $ejerciciosPorBloque[$claveBloque]["ejercicios"][] = $ejercicio;
    }

    $quizzesIncorrectos = array_map(
        fn($ejercicio) => (int) $ejercicio["id"],
        array_filter($ejercicios, fn($ejercicio) =>
            $ejercicio["tipo"] === "multiple"
            && $ejercicio["entrega_id"] !== null
            && $ejercicio["puntuacion"] !== null
            && (float) $ejercicio["puntuacion"] === 0.0
        )
    );
    if ($quizzesIncorrectos) {
        $idsIncorrectos = implode(",", $quizzesIncorrectos);
        $resultadoCorrectas = $conexion->query(
            "SELECT ejercicio_id, texto FROM opciones_ejercicio
             WHERE es_correcta = 1 AND ejercicio_id IN ($idsIncorrectos)"
        );
        while ($correcta = $resultadoCorrectas->fetch_assoc()) {
            $opcionCorrectaPorEjercicio[(int) $correcta["ejercicio_id"]] = $correcta["texto"];
        }
    }
}

$totalEjercicios = count($ejercicios);
$ejerciciosEntregados = count(array_filter($ejercicios, fn($e) => $e["entrega_id"] !== null));
$ejerciciosCalificados = count(array_filter($ejercicios, fn($e) => $e["puntuacion"] !== null));
$promedioFinal = null;
if ($totalEjercicios > 0 && $ejerciciosCalificados === $totalEjercicios) {
    $promedioFinal = array_sum(array_map(
        fn($e) => (float) $e["puntuacion"] / (float) $e["puntuacion_maxima"] * 100,
        $ejercicios
    )) / $totalEjercicios;
}
$ejerciciosListos = $totalEjercicios > 0
    && $ejerciciosEntregados === $totalEjercicios
    && $ejerciciosCalificados === $totalEjercicios;

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
$leccionesCompletas = $total > 0 && $hechas === $total;
$calificacionAprobatoria = 80.0;
$cursoAprobado = $leccionesCompletas && $ejerciciosListos
    && $promedioFinal !== null && $promedioFinal >= $calificacionAprobatoria;

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

$siguienteLeccion = null;
foreach ($lecciones as $leccion) {
    if (!$leccion["completada"]) {
        $siguienteLeccion = $leccion;
        break;
    }
}
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
<body class="app curso-app">
<main class="curso-pagina" id="curso-aprendizaje"
      data-total="<?php echo $total; ?>" data-hechas="<?php echo $hechas; ?>">
    <a href="alumno.php?seccion=mis_cursos" class="al-volver">← Volver a mis cursos</a>

    <section class="curso-portada">
        <div class="curso-portada-texto">
            <span class="curso-kicker">AULA VIRTUAL</span>
            <div class="curso-titulo-fila">
                <h1><?php echo htmlspecialchars($curso["nombre"]); ?></h1>
                <span class="etiqueta estado-<?php echo htmlspecialchars($curso["estado"]); ?>">
                    <?php echo htmlspecialchars(ucfirst($curso["estado"])); ?>
                </span>
            </div>
            <?php if (!empty($curso["descripcion"])): ?>
                <p class="curso-portada-descripcion"><?php echo nl2br(htmlspecialchars($curso["descripcion"])); ?></p>
            <?php else: ?>
                <p class="curso-portada-descripcion">Sigue tu avance y continúa aprendiendo a tu propio ritmo.</p>
            <?php endif; ?>

            <div class="curso-datos-rapidos">
                <?php if ($ponentes): ?>
                    <div class="curso-dato-rapido">
                        <span class="curso-dato-etiqueta">Impartido por</span>
                        <strong><?php echo htmlspecialchars(implode(", ", $ponentes)); ?></strong>
                    </div>
                <?php endif; ?>
                <div class="curso-dato-rapido">
                    <span class="curso-dato-etiqueta">Duración</span>
                    <strong><?php echo $curso["fecha_inicio"] ? date("d/m/Y", strtotime($curso["fecha_inicio"])) : "Por definir"; ?>
                        <span aria-hidden="true">—</span>
                        <?php echo $curso["fecha_fin"] ? date("d/m/Y", strtotime($curso["fecha_fin"])) : "Sin fecha límite"; ?></strong>
                </div>
                <div class="curso-dato-rapido">
                    <span class="curso-dato-etiqueta">Contenido</span>
                    <strong><?php echo $total; ?> <?php echo $total === 1 ? "lección" : "lecciones"; ?></strong>
                </div>
            </div>
        </div>
    </section>

    <section class="curso-progreso-panel" aria-labelledby="titulo-progreso">
        <div class="curso-progreso-cabecera">
            <div>
                <span class="curso-kicker">TU RECORRIDO</span>
                <h2 id="titulo-progreso">Progreso del curso</h2>
            </div>
            <strong class="curso-progreso-porcentaje" id="progreso-porcentaje"><?php echo $porcentaje; ?>%</strong>
        </div>
        <div class="al-progreso curso-barra-progreso" role="progressbar"
             aria-label="Progreso del curso" aria-valuemin="0" aria-valuemax="100"
             aria-valuenow="<?php echo $porcentaje; ?>">
            <div id="progreso-barra" style="width: <?php echo $porcentaje; ?>%"></div>
        </div>
        <div class="curso-progreso-pie">
            <span id="progreso-resumen"><?php echo $hechas; ?> de <?php echo $total; ?> lecciones completadas</span>
            <div class="curso-progreso-accion" id="progreso-accion">
                <?php if ($total > 0 && $hechas === $total): ?>
                    <span class="curso-completado">Curso completado</span>
                <?php elseif ($siguienteLeccion): ?>
                    <a class="curso-continuar" href="leccion.php?id=<?php echo (int) $siguienteLeccion["id"]; ?>">
                        Continuar aprendizaje <span aria-hidden="true">→</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <p class="curso-estado-actualizacion" id="estado-actualizacion" role="status" aria-live="polite"></p>
    </section>

    <section class="resultado-curso <?php echo $cursoAprobado ? 'resultado-aprobado' : ''; ?>"
             id="resultado-curso" data-ejercicios-listos="<?php echo $ejerciciosListos ? 'true' : 'false'; ?>"
             data-aprobado="<?php echo $promedioFinal !== null && $promedioFinal >= $calificacionAprobatoria ? 'true' : 'false'; ?>"
             data-promedio="<?php echo $promedioFinal !== null ? number_format($promedioFinal, 2, '.', '') : ''; ?>"
             data-ejercicios-entregados="<?php echo $ejerciciosEntregados; ?>"
             data-ejercicios-calificados="<?php echo $ejerciciosCalificados; ?>"
             data-ejercicios-total="<?php echo $totalEjercicios; ?>"
             <?php echo $leccionesCompletas ? '' : 'hidden'; ?>>
        <div class="resultado-curso-icono" id="resultado-curso-icono" aria-hidden="true"><?php echo $cursoAprobado ? '✓' : 'i'; ?></div>
        <div class="resultado-curso-contenido">
            <span class="curso-kicker">RESULTADO DEL CURSO</span>
            <?php if (!$ejerciciosListos): ?>
                <h2>Contenido completado</h2>
                <?php if ($totalEjercicios === 0): ?>
                    <p>Este curso no tiene ejercicios evaluables, por lo que no es posible calcular un promedio ni emitir el certificado de aprobación.</p>
                <?php else: ?>
                    <p>Tu calificación final estará disponible cuando se entreguen y califiquen todos los ejercicios.
                        <?php echo $ejerciciosCalificados; ?> de <?php echo $totalEjercicios; ?> calificados.</p>
                <?php endif; ?>
            <?php else: ?>
                <h2 id="resultado-curso-titulo"><?php echo $cursoAprobado ? '¡Aprobaste el curso!' : ($promedioFinal >= $calificacionAprobatoria ? 'Promedio aprobatorio' : 'Resultado final'); ?></h2>
                <p id="resultado-curso-mensaje">
                    <?php if ($cursoAprobado): ?>
                        Completaste todas las lecciones y alcanzaste el mínimo aprobatorio de <?php echo number_format($calificacionAprobatoria, 0); ?> puntos.
                    <?php elseif ($promedioFinal >= $calificacionAprobatoria): ?>
                        Tu promedio es aprobatorio. Completa todas las lecciones para habilitar tu certificado.
                    <?php else: ?>
                        Promedio de todos los ejercicios. El mínimo aprobatorio es <?php echo number_format($calificacionAprobatoria, 0); ?> / 100.
                    <?php endif; ?>
                </p>
                <strong class="resultado-curso-calificacion"><?php echo number_format($promedioFinal, 2); ?><span> / 100</span></strong>
                <p class="resultado-curso-no-aprobado" id="resultado-curso-no-aprobado" <?php echo $promedioFinal >= $calificacionAprobatoria ? 'hidden' : ''; ?>>No se emitió certificado de aprobación.</p>
                <a class="btn-certificado" id="enlace-certificado" href="certificado.php?id=<?php echo $curso_id; ?>"
                   <?php echo $cursoAprobado ? '' : 'hidden'; ?>>Ver y guardar certificado</a>
            <?php endif; ?>
        </div>
    </section>

    <section class="curso-contenido" aria-labelledby="titulo-contenido">
        <div class="curso-seccion-cabecera">
            <div>
                <span class="curso-kicker">PLAN DE ESTUDIO</span>
                <h2 id="titulo-contenido">Contenido del curso</h2>
            </div>
            <span class="curso-conteo"><?php echo $total; ?> <?php echo $total === 1 ? "lección" : "lecciones"; ?></span>
        </div>

        <?php if ($total === 0): ?>
            <div class="curso-vacio">Este curso todavía no tiene lecciones disponibles.</div>
        <?php else: ?>
            <?php
            $bloqueActual = null;
            $numeroBloque = 0;
            foreach ($lecciones as $l):
                if ($bloqueActual !== $l["bloque_id"]):
                    if ($bloqueActual !== null): ?>
                        </div></section>
                    <?php endif;
                    $bloqueActual = $l["bloque_id"];
                    $numeroBloque++;
                    $leccionesBloque = array_values(array_filter(
                        $lecciones,
                        fn($leccion) => (int) $leccion["bloque_id"] === (int) $bloqueActual
                    ));
                    $hechasBloque = count(array_filter($leccionesBloque, fn($leccion) => $leccion["completada"]));
                    ?>
                    <section class="curso-bloque" aria-labelledby="bloque-titulo-<?php echo (int) $bloqueActual; ?>">
                        <header class="curso-bloque-cabecera">
                            <span class="curso-bloque-numero"><?php echo str_pad((string) $numeroBloque, 2, "0", STR_PAD_LEFT); ?></span>
                            <div class="curso-bloque-info">
                                <h3 id="bloque-titulo-<?php echo (int) $bloqueActual; ?>"><?php echo htmlspecialchars($l["bloque"]); ?></h3>
                                <span><?php echo $hechasBloque; ?> de <?php echo count($leccionesBloque); ?> lecciones completadas</span>
                            </div>
                            <span class="curso-bloque-estado"><?php echo $hechasBloque === count($leccionesBloque) ? "Completo" : "En curso"; ?></span>
                        </header>
                        <div class="curso-lista-lecciones">
                <?php endif; ?>

                <article class="leccion-curso <?php echo $l["completada"] ? 'hecha' : ''; ?>"
                         id="l<?php echo (int) $l["id"]; ?>" data-leccion-id="<?php echo (int) $l["id"]; ?>"
                         data-bloque-id="<?php echo (int) $l["bloque_id"]; ?>">
                    <form method="POST" action="completar_leccion.php" class="form-check-leccion">
                        <input type="hidden" name="subbloque_id" value="<?php echo (int) $l["id"]; ?>">
                        <button type="submit" class="check check-curso" aria-label="<?php echo $l["completada"] ? 'Marcar como pendiente' : 'Marcar como completada'; ?>"
                                aria-pressed="<?php echo $l["completada"] ? 'true' : 'false'; ?>" title="<?php echo $l["completada"] ? 'Marcar como pendiente' : 'Marcar como completada'; ?>"></button>
                    </form>
                    <a class="leccion-curso-enlace" href="leccion.php?id=<?php echo (int) $l["id"]; ?>">
                        <span class="leccion-curso-titulo"><?php echo htmlspecialchars($l["titulo"]); ?></span>
                        <span class="leccion-curso-accion"><?php echo $l["completada"] ? "Revisar lección" : "Ver lección"; ?> <span aria-hidden="true">→</span></span>
                    </a>
                    <span class="leccion-curso-badge"><?php echo $l["completada"] ? "Completada" : "Pendiente"; ?></span>
                </article>
            <?php endforeach; ?>
            </div></section>
        <?php endif; ?>
    </section>

    <section class="curso-ejercicios-seccion">

    <section class="ejercicios-alumno">
        <h2>Ejercicios</h2>
        <?php if (($_GET["msg"] ?? "") === "ejercicio_entregado"): ?>
            <p class="aviso-ejercicio ok">Tu respuesta se guardó correctamente.</p>
        <?php elseif (($_GET["msg"] ?? "") === "ejercicio_auto_calificado"): ?>
            <p class="aviso-ejercicio ok">Tu respuesta se comprobó y calificó automáticamente.</p>
        <?php elseif (($_GET["msg"] ?? "") === "ejercicio_error"): ?>
            <p class="aviso-ejercicio error">No se pudo guardar la respuesta. Inténtalo otra vez.</p>
        <?php elseif (($_GET["msg"] ?? "") === "ejercicio_calificado"): ?>
            <p class="aviso-ejercicio error">Este ejercicio ya fue calificado y no admite cambios.</p>
        <?php endif; ?>

        <?php if (!$ejercicios): ?>
            <p>Este curso todavía no tiene ejercicios.</p>
        <?php else: ?>
            <div class="ejercicios-por-bloque">
                <?php foreach ($ejerciciosPorBloque as $grupoEjercicios): ?>
                    <details class="ejercicios-bloque">
                        <summary class="ejercicios-bloque-cabecera">
                            <h3><?php echo htmlspecialchars($grupoEjercicios["titulo"]); ?></h3>
                            <span class="ejercicios-bloque-resumen">
                                <?php echo count($grupoEjercicios["ejercicios"]); ?> <?php echo count($grupoEjercicios["ejercicios"]) === 1 ? "ejercicio" : "ejercicios"; ?>
                                <span class="ejercicios-flecha" aria-hidden="true">⌄</span>
                            </span>
                        </summary>
                        <div class="ejercicios-alumno-lista">
                        <?php foreach ($grupoEjercicios["ejercicios"] as $ejercicio): ?>
                            <?php
                            $opcionesEjercicio = $opcionesPorEjercicio[(int) $ejercicio["id"]] ?? [];
                            if ($ejercicio["puntuacion"] !== null) {
                                $estadoResumen = "Calificado · " . number_format((float) $ejercicio["puntuacion"], 2) . " / " . number_format((float) $ejercicio["puntuacion_maxima"], 2);
                                $claseResumen = "calificado";
                            } elseif ($ejercicio["entrega_id"] !== null) {
                                $estadoResumen = "Enviado · pendiente de calificación";
                                $claseResumen = "enviado";
                            } elseif ($curso["estado"] === "finalizado") {
                                $estadoResumen = "Plazo cerrado";
                                $claseResumen = "cerrado";
                            } else {
                                $estadoResumen = "Por entregar";
                                $claseResumen = "pendiente";
                            }
                            ?>
                            <details class="ejercicio-acordeon">
                                <summary class="ejercicio-acordeon-cabecera">
                                    <strong><?php echo htmlspecialchars($ejercicio["titulo"]); ?></strong>
                                    <span class="estado-resumen-ejercicio <?php echo $claseResumen; ?>"><?php echo htmlspecialchars($estadoResumen); ?></span>
                                    <span class="ejercicios-flecha" aria-hidden="true">⌄</span>
                                </summary>
                                <article class="ejercicio-alumno">
                                <div class="ejercicio-alumno-cabecera">
                                    <div>
                                        <span class="tipo-ejercicio-etiqueta"><?php echo $ejercicio["tipo"] === "multiple" ? "Opción múltiple · autoevaluado" : "Respuesta abierta"; ?></span>
                                        <h4><?php echo htmlspecialchars($ejercicio["titulo"]); ?></h4>
                                    </div>
                                    <span>Máximo: <?php echo number_format((float) $ejercicio["puntuacion_maxima"], 2); ?> puntos</span>
                                </div>
                                <p class="ejercicio-instrucciones"><?php echo nl2br(htmlspecialchars($ejercicio["instrucciones"])); ?></p>

                                <?php if ($ejercicio["entrega_id"] === null): ?>
                                    <?php if ($curso["estado"] === "finalizado"): ?>
                                        <p class="ejercicio-pendiente">El curso finalizó y ya no se aceptan nuevas entregas.</p>
                                    <?php elseif ($ejercicio["tipo"] === "multiple" && !$opcionesEjercicio): ?>
                                        <p class="ejercicio-pendiente">Este ejercicio no tiene opciones configuradas. Contacta al equipo docente.</p>
                                    <?php else: ?>
                                        <form method="POST" action="../acciones_entrega_ejercicio.php" class="form-entrega-ejercicio">
                                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION["csrf_ejercicios"]); ?>">
                                            <input type="hidden" name="curso_id" value="<?php echo $curso_id; ?>">
                                            <input type="hidden" name="ejercicio_id" value="<?php echo (int) $ejercicio["id"]; ?>">
                                            <?php if ($ejercicio["tipo"] === "multiple"): ?>
                                                <fieldset class="opciones-respuesta-alumno">
                                                    <legend>Elige una respuesta</legend>
                                                    <?php foreach ($opcionesEjercicio as $indice => $opcion): ?>
                                                        <label class="opcion-respuesta-alumno" for="respuesta-opcion-<?php echo (int) $ejercicio["id"]; ?>-<?php echo (int) $opcion["id"]; ?>">
                                                            <input id="respuesta-opcion-<?php echo (int) $ejercicio["id"]; ?>-<?php echo (int) $opcion["id"]; ?>" type="radio" name="opcion_id" value="<?php echo (int) $opcion["id"]; ?>" required>
                                                            <span class="opcion-respuesta-letra"><?php echo chr(65 + $indice); ?></span>
                                                            <span><?php echo htmlspecialchars($opcion["texto"]); ?></span>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </fieldset>
                                            <?php else: ?>
                                                <label for="respuesta-ejercicio-<?php echo (int) $ejercicio["id"]; ?>">Tu respuesta</label>
                                                <textarea id="respuesta-ejercicio-<?php echo (int) $ejercicio["id"]; ?>" name="respuesta" rows="5" maxlength="60000" required placeholder="Escribe tu respuesta aquí…"></textarea>
                                            <?php endif; ?>
                                            <button type="submit" class="btn-principal"><?php echo $ejercicio["tipo"] === "multiple" ? "Comprobar respuesta" : "Enviar respuesta"; ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="respuesta-alumno">
                                        <strong><?php echo $ejercicio["tipo"] === "multiple" ? "Respuesta elegida" : "Tu respuesta"; ?> · <?php echo date("d/m/Y H:i", strtotime($ejercicio["entregado_en"])); ?></strong>
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
                                        <div class="resultado-ejercicio <?php echo $ejercicio["tipo"] === "multiple" ? 'resultado-automatico' : ''; ?>">
                                            <strong><?php echo $ejercicio["tipo"] === "multiple" ? ((float) $ejercicio["puntuacion"] > 0 ? "Respuesta correcta" : "Respuesta incorrecta") : "Calificación"; ?>: <?php echo number_format((float) $ejercicio["puntuacion"], 2); ?> / <?php echo number_format((float) $ejercicio["puntuacion_maxima"], 2); ?></strong>
                                            <span>(<?php echo number_format((float) $ejercicio["puntuacion"] / (float) $ejercicio["puntuacion_maxima"] * 100, 2); ?>%)</span>
                                            <?php if ($ejercicio["tipo"] === "multiple" && isset($opcionCorrectaPorEjercicio[(int) $ejercicio["id"]])): ?>
                                                <p><strong>Respuesta correcta:</strong> <?php echo htmlspecialchars($opcionCorrectaPorEjercicio[(int) $ejercicio["id"]]); ?></p>
                                            <?php endif; ?>
                                            <?php if ($ejercicio["tipo"] !== "multiple" && trim((string) $ejercicio["comentario"]) !== ""): ?>
                                                <p><strong>Comentario del evaluador:</strong><br><?php echo nl2br(htmlspecialchars($ejercicio["comentario"])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                </article>
                            </details>
                        <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    </section>
</main>
<script src="curso.js" defer></script>
</body>
</html>