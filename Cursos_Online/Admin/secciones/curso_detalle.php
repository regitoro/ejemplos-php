<?php
require_once __DIR__ . "/../../permisos.php";

$cursoId = (int) ($_GET["gestionar"] ?? 0);

$rolDetalle = strtolower($_SESSION["rol"] ?? "");
$esAdmin    = $rolDetalle === "admin";
$esPonente  = $rolDetalle === "ponente";
if (empty($_SESSION["csrf_ejercicios"])) {
    $_SESSION["csrf_ejercicios"] = bin2hex(random_bytes(32));
}

$stmt = $conexion->prepare(
    "SELECT id, nombre, estado, director_id, aprobacion, revision_comentario
     FROM cursos WHERE id = ?"
);
$stmt->bind_param("i", $cursoId);
$stmt->execute();
$curso = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Cada rol solo puede abrir los cursos que le corresponden
if ($curso && !puedeVerCurso($conexion, $cursoId, $rolDetalle, (int) $_SESSION["usuario_id"])) {
    $curso = null;
}

if (!$curso) {
    echo '<div class="aviso-admin error">El curso no existe.</div>';
    echo '<a href="' . $panel . '?seccion=cursos" class="btn-link">← Volver a cursos</a>';
    return;
}

$stmt = $conexion->prepare(
    "SELECT e.id, e.bloque_id, e.tipo, e.titulo, e.instrucciones, e.puntuacion_maxima,
            b.titulo AS bloque,
            (SELECT COUNT(*) FROM entregas_ejercicios ee WHERE ee.ejercicio_id = e.id) AS entregas
     FROM ejercicios e
     LEFT JOIN bloques b ON b.id = e.bloque_id
     WHERE e.curso_id = ? ORDER BY b.orden, b.id, e.creado_en, e.id"
);
$stmt->bind_param("i", $cursoId);
$stmt->execute();
$ejerciciosCurso = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$ejerciciosPorBloque = [];
foreach ($ejerciciosCurso as $ejercicio) {
    $claveBloque = $ejercicio["bloque_id"] === null ? "sin-bloque" : (string) $ejercicio["bloque_id"];
    if (!isset($ejerciciosPorBloque[$claveBloque])) {
        $ejerciciosPorBloque[$claveBloque] = [
            "titulo" => $ejercicio["bloque"] ?? "Sin bloque asignado",
            "ejercicios" => [],
        ];
    }
    $ejerciciosPorBloque[$claveBloque]["ejercicios"][] = $ejercicio;
}

$opcionesEjercicios = [];
$stmt = $conexion->prepare(
    "SELECT o.id, o.ejercicio_id, o.texto, o.es_correcta, o.orden
     FROM opciones_ejercicio o
     INNER JOIN ejercicios e ON e.id = o.ejercicio_id
     WHERE e.curso_id = ? ORDER BY o.orden, o.id"
);
$stmt->bind_param("i", $cursoId);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $opcion) {
    $opcionesEjercicios[(int) $opcion["ejercicio_id"]][] = $opcion;
}
$stmt->close();

// No se edita mientras el curso está en revisión (salvo el admin)
$bloqueado = !$esAdmin && $curso["aprobacion"] === "en_revision";
[$revTexto, $revClase] = ETIQUETAS_REVISION[$curso["aprobacion"]];

$mensajes = [
    "bloque_creado"      => ["ok",    "Bloque creado."],
    "bloque_actualizado" => ["ok",    "Bloque actualizado."],
    "bloque_eliminado"   => ["ok",    "Bloque eliminado junto con sus subbloques."],
    "sub_creado"         => ["ok",    "Subbloque creado."],
    "sub_actualizado"    => ["ok",    "Subbloque actualizado."],
    "sub_eliminado"      => ["ok",    "Subbloque eliminado."],
    "movido"             => ["ok",    "Orden actualizado."],
    "datos_invalidos"    => ["error", "El título es obligatorio (máximo 150 caracteres)."],
    "error"              => ["error", "No se pudo completar la acción."],
    "ponente_asignado"   => ["ok", "Ponente asignado al curso."],
    "ponente_quitado"    => ["ok", "Ponente quitado del curso."],
    "alumnos_inscritos"  => ["ok", "Alumnos inscritos en el curso."],
    "alumno_quitado"     => ["ok", "Alumno quitado del curso."],
    "director_actualizado" => ["ok", "Director del curso actualizado."],
    "creado"            => ["ok",    "Curso creado. Agrega bloques y lecciones; cuando esté listo, envíalo a revisión."],
    "enviado_revision"  => ["ok",    "Curso enviado a revisión. El administrador lo revisará."],
    "revision_retirada" => ["ok",    "Curso retirado de revisión. Ya puedes editarlo."],
    "publicado"         => ["ok",    "Curso publicado. Los alumnos ya pueden verlo y solicitar inscripción."],
    "revision_vacio"    => ["error", "Antes de enviar a revisión, el curso necesita al menos un bloque con una lección."],
    "no_aprobado"       => ["error", "El curso necesita la aprobación del administrador para publicarse."],
    "requisito_bloques" => ["error", "Agrega al menos un bloque antes de continuar."],
    "requisito_lecciones" => ["error", "Agrega al menos una lección dentro de un bloque antes de continuar."],
    "requisito_ponentes" => ["error", "Asigna al menos un ponente al curso antes de enviarlo o publicarlo."],
    "requisito_director" => ["error", "Asigna un director al curso antes de publicarlo."],
    "curso_borrador_requerido" => ["error", "El curso se crea como borrador. Agrega bloques, lecciones y asignaciones antes de activarlo."],
    "sin_permiso"       => ["error", "No tienes permiso para hacer esa acción en este curso."],
    "insc_creadas" => ["ok",    "Alumnos inscritos correctamente."],
    "insc_quitada" => ["ok",    "Alumno quitado del curso, junto con su progreso."],
    "insc_nada"    => ["error", "No se inscribió a nadie: elige al menos un alumno disponible."],
    "ejercicio_creado" => ["ok", "Ejercicio creado."],
    "ejercicio_actualizado" => ["ok", "Ejercicio actualizado."],
    "ejercicio_eliminado" => ["ok", "Ejercicio eliminado."],
    "ejercicio_invalido" => ["error", "Completa todos los datos: selecciona un bloque, elige el tipo y, si es opción múltiple, define cuatro respuestas y la correcta."],
    "ejercicio_con_entregas" => ["error", "No se puede editar ni eliminar este ejercicio porque ya tiene entregas."],
];
$msg = $_GET["msg"] ?? "";

// Bloques del curso
$stmt = $conexion->prepare("SELECT id, titulo FROM bloques WHERE curso_id = ? ORDER BY orden, id");
$stmt->bind_param("i", $cursoId);
$stmt->execute();
$bloques = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Subbloques del curso, agrupados por bloque
$stmt = $conexion->prepare(
    "SELECT s.id, s.bloque_id, s.titulo, s.contenido
     FROM subbloques s
     INNER JOIN bloques b ON s.bloque_id = b.id
     WHERE b.curso_id = ?
     ORDER BY s.orden, s.id"
);
$stmt->bind_param("i", $cursoId);
$stmt->execute();
$subs = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $s) {
    $subs[$s["bloque_id"]][] = $s;
}

// Ponentes asignados a este curso
$stmt = $conexion->prepare(
    "SELECT u.id, u.nombre, u.email
     FROM curso_ponentes cp
     INNER JOIN usuarios u ON cp.usuario_id = u.id
     WHERE cp.curso_id = ?
     ORDER BY u.nombre"
);
$stmt->bind_param("i", $cursoId);
$stmt->execute();
$ponentes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ponentes aprobados que todavía no están en este curso
$stmt = $conexion->prepare(
    "SELECT u.id, u.nombre, u.email
     FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE r.nombre = 'ponente' AND u.aceptado = 1
       AND u.id NOT IN (SELECT usuario_id FROM curso_ponentes WHERE curso_id = ?)
     ORDER BY u.nombre"
);

// Directores aprobados, para elegir uno
$directores = $conexion->query(
    "SELECT u.id, u.nombre, u.email
     FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE r.nombre = 'director' AND u.aceptado = 1
     ORDER BY u.nombre"
)->fetch_all(MYSQLI_ASSOC);

$stmt->bind_param("i", $cursoId);
$stmt->execute();
$disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


// Total de lecciones del curso (ya las tenemos agrupadas en $subs)
$totalLecciones = 0;
foreach ($subs as $lista) {
    $totalLecciones += count($lista);
}
$requisitosPublicacion = [
    "bloques" => count($bloques) > 0,
    "lecciones" => $totalLecciones > 0,
    "director" => !empty($curso["director_id"]),
    "ponentes" => count($ponentes) > 0,
];
$listoParaPublicar = !in_array(false, $requisitosPublicacion, true);
$listoParaRevision = $requisitosPublicacion["bloques"]
    && $requisitosPublicacion["lecciones"]
    && $requisitosPublicacion["ponentes"];

// Alumnos inscritos, con cuántas lecciones completó cada uno
$stmt = $conexion->prepare(
    "SELECT i.id AS inscripcion_id, u.id, u.nombre, u.email,
            (SELECT COUNT(*) FROM progreso_subbloques p
              WHERE p.inscripcion_id = i.id) AS hechas
     FROM inscripciones i
     INNER JOIN usuarios u ON i.usuario_id = u.id
     WHERE i.curso_id = ?
     ORDER BY u.nombre"
);
$stmt->bind_param("i", $cursoId);
$stmt->execute();
$alumnos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Alumnos aprobados que todavía no están en este curso
$stmt = $conexion->prepare(
    "SELECT u.id, u.nombre, u.email
     FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE r.nombre = 'alumno' AND u.aceptado = 1
       AND u.id NOT IN (SELECT usuario_id FROM inscripciones WHERE curso_id = ?)
     ORDER BY u.nombre"
);
$stmt->bind_param("i", $cursoId);
$stmt->execute();
$alumnosDisponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();



// Botones ↑ ↓ de un bloque o subbloque
function botonesMover(string $tipo, int $id): void {
    foreach (["arriba" => "↑", "abajo" => "↓"] as $dir => $flecha) {
        echo '<form method="POST" action="acciones_bloques.php">'
           . '<input type="hidden" name="accion" value="mover">'
           . '<input type="hidden" name="tipo" value="' . $tipo . '">'
           . '<input type="hidden" name="id" value="' . $id . '">'
           . '<input type="hidden" name="dir" value="' . $dir . '">'
           . '<button type="submit" class="mini-btn" title="Mover ' . $dir . '">' . $flecha . '</button>'
           . '</form>';
    }
}
?>

<?php if (isset($mensajes[$msg])): ?>
    <div class="aviso-admin <?php echo $mensajes[$msg][0]; ?>">
        <?php echo htmlspecialchars($mensajes[$msg][1]); ?>
    </div>
<?php endif; ?>


<div class="barra-superior barra-detalle">
    <a href="<?php echo $panel; ?>?seccion=cursos" class="btn-link">← Volver a cursos</a>
    <?php if (!$esPonente): ?>
        <a href="<?php echo $panel; ?>?seccion=cursos&editar=<?php echo $cursoId; ?>" class="btn-link">Editar datos del curso</a>
    <?php endif; ?>
</div>


<?php if ($esPonente): ?>

    <?php if ($bloqueado): ?>
        <div class="aviso-admin error">
            El curso está en revisión del administrador y no se puede editar por ahora.
        </div>
    <?php endif; ?>

<?php else: ?>


<!-- Revisión y publicación -->
<div class="tarjeta panel-curso-revision">
    <h2>
        Revisión y publicación
        <span class="etiqueta <?php echo $revClase; ?>"><?php echo $revTexto; ?></span>
    </h2>

    <?php if ($esAdmin && $curso["estado"] === "borrador"): ?>
        <div class="requisitos-publicacion">
            <strong>Antes de publicar, verifica estos requisitos:</strong>
            <ul>
                <li class="<?php echo $requisitosPublicacion["bloques"] ? 'requisito-ok' : 'requisito-falta'; ?>">
                    <?php echo $requisitosPublicacion["bloques"] ? 'Listo' : 'Pendiente'; ?> · Al menos un bloque
                </li>
                <li class="<?php echo $requisitosPublicacion["lecciones"] ? 'requisito-ok' : 'requisito-falta'; ?>">
                    <?php echo $requisitosPublicacion["lecciones"] ? 'Listo' : 'Pendiente'; ?> · Al menos una lección dentro de un bloque
                </li>
                <li class="<?php echo $requisitosPublicacion["director"] ? 'requisito-ok' : 'requisito-falta'; ?>">
                    <?php echo $requisitosPublicacion["director"] ? 'Listo' : 'Pendiente'; ?> · Director asignado
                </li>
                <li class="<?php echo $requisitosPublicacion["ponentes"] ? 'requisito-ok' : 'requisito-falta'; ?>">
                    <?php echo $requisitosPublicacion["ponentes"] ? 'Listo' : 'Pendiente'; ?> · Al menos un ponente asignado
                </li>
            </ul>
        </div>
    <?php elseif (!$esAdmin && !$esPonente && in_array($curso["aprobacion"], ["sin_enviar", "rechazado"], true)): ?>
        <div class="requisitos-publicacion">
            <strong>Requisitos para enviar a revisión:</strong>
            <ul>
                <li class="<?php echo $requisitosPublicacion["bloques"] ? 'requisito-ok' : 'requisito-falta'; ?>">
                    <?php echo $requisitosPublicacion["bloques"] ? 'Listo' : 'Pendiente'; ?> · Al menos un bloque
                </li>
                <li class="<?php echo $requisitosPublicacion["lecciones"] ? 'requisito-ok' : 'requisito-falta'; ?>">
                    <?php echo $requisitosPublicacion["lecciones"] ? 'Listo' : 'Pendiente'; ?> · Al menos una lección
                </li>
                <li class="<?php echo $requisitosPublicacion["ponentes"] ? 'requisito-ok' : 'requisito-falta'; ?>">
                    <?php echo $requisitosPublicacion["ponentes"] ? 'Listo' : 'Pendiente'; ?> · Al menos un ponente asignado
                </li>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($curso["aprobacion"] === "rechazado" && trim((string) $curso["revision_comentario"]) !== ""): ?>
        <div class="comentario-revision">
            <strong>Cambios solicitados por el administrador:</strong><br>
            <?php echo nl2br(htmlspecialchars($curso["revision_comentario"])); ?>
        </div>
    <?php endif; ?>

    <?php if ($curso["aprobacion"] === "sin_enviar"): ?>

        <?php if ($esAdmin): ?>
            <p>El director todavía no ha enviado este curso a revisión.</p>
        <?php else: ?>
            <p>
                Cuando el curso esté listo, envíalo a revisión. El administrador lo revisará y,
                si lo aprueba, podrás publicarlo.
            </p>
            <form method="POST" action="acciones_cursos.php">
                <input type="hidden" name="accion" value="enviar_revision">
                <input type="hidden" name="id" value="<?php echo $cursoId; ?>">
                <button type="submit" class="btn-principal" <?php echo $listoParaRevision ? '' : 'disabled'; ?>>Enviar a revisión</button>
            </form>
        <?php endif; ?>

    <?php elseif ($curso["aprobacion"] === "en_revision"): ?>

        <?php if ($esAdmin): ?>
            <p>Este curso está esperando la revisión del administrador.</p>
        <?php else: ?>
            <p>Tu curso está en revisión y no se puede editar mientras tanto.</p>
            <form method="POST" action="acciones_cursos.php">
                <input type="hidden" name="accion" value="retirar_revision">
                <input type="hidden" name="id" value="<?php echo $cursoId; ?>">
                <button type="submit" class="btn-link">Retirar de revisión</button>
            </form>
        <?php endif; ?>

    <?php elseif ($curso["aprobacion"] === "rechazado"): ?>

        <?php if ($esAdmin): ?>
            <p>Se pidieron cambios al director. Está pendiente que los haga y lo reenvíe.</p>
        <?php else: ?>
            <p>Haz los cambios y vuelve a enviarlo a revisión.</p>
            <form method="POST" action="acciones_cursos.php">
                <input type="hidden" name="accion" value="enviar_revision">
                <input type="hidden" name="id" value="<?php echo $cursoId; ?>">
                <button type="submit" class="btn-principal" <?php echo $listoParaRevision ? '' : 'disabled'; ?>>Volver a enviar a revisión</button>
            </form>
        <?php endif; ?>

    <?php else: /* aprobado */ ?>

        <?php if ($curso["estado"] === "borrador"): ?>
            <?php if ($listoParaPublicar): ?>
                <p>El curso está aprobado y cumple los requisitos. Puedes publicarlo para que los alumnos puedan verlo.</p>
                <form method="POST" action="acciones_cursos.php">
                    <input type="hidden" name="accion" value="publicar">
                    <input type="hidden" name="id" value="<?php echo $cursoId; ?>">
                    <button type="submit" class="btn-principal">Publicar curso</button>
                </form>
            <?php else: ?>
                <p class="nota">Completa los requisitos pendientes para habilitar la publicación.</p>
            <?php endif; ?>
        <?php else: ?>
            <p>El curso está aprobado y su estado actual es <strong><?php echo htmlspecialchars($curso["estado"]); ?></strong>.</p>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php endif; ?>

<!-- Mientras está en revisión, el director no puede editar nada de lo de abajo -->
<fieldset class="sin-borde" <?php echo $bloqueado ? "disabled" : ""; ?>>

<?php if ($esAdmin): ?>
<!-- Director del curso (solo uno por curso) -->
<div class="tarjeta panel-asignacion panel-director">
    <h2>Director</h2>

    <?php if (count($directores) === 0): ?>
        <p class="nota">
            No hay directores disponibles. Para tener uno, aprueba un usuario
            con el rol "director" en la sección Usuarios.
        </p>
    <?php else: ?>
        <form method="POST" action="acciones_director.php" class="form-linea">
            <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
            <select name="director_id" class="select-linea">
                <option value="0">Sin director</option>
                <?php foreach ($directores as $d): ?>
                    <option value="<?php echo (int) $d["id"]; ?>"
                        <?php echo (int) $curso["director_id"] === (int) $d["id"] ? "selected" : ""; ?>>
                        <?php echo htmlspecialchars($d["nombre"] . " (" . $d["email"] . ")"); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-principal">Guardar director</button>
        </form>
        <p class="nota">Cada curso tiene un solo director. Al elegir otro, reemplaza al anterior.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<fieldset class="sin-borde" <?php echo $bloqueado ? "disabled" : ""; ?>>


<?php if (!$esPonente): ?>
<!-- Ponentes del curso -->
<div class="tarjeta panel-asignacion panel-ponentes">
    <h2>Ponentes</h2>

    <?php if (count($ponentes) === 0): ?>
        <p>Este curso todavía no tiene ponentes asignados.</p>
    <?php else: ?>
        <ul class="lista-personas">
            <?php foreach ($ponentes as $p): ?>
                <li>
                    <div>
                        <strong><?php echo htmlspecialchars($p["nombre"]); ?></strong>
                        <small><?php echo htmlspecialchars($p["email"]); ?></small>
                    </div>
                    <form method="POST" action="acciones_ponentes.php"
                          onsubmit="return confirm('¿Quitar a este ponente del curso?');">
                        <input type="hidden" name="accion" value="quitar">
                        <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                        <input type="hidden" name="usuario_id" value="<?php echo (int) $p["id"]; ?>">
                        <button type="submit" class="btn-peligro mini">Quitar</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (count($disponibles) === 0): ?>
        <p class="nota">
            No hay más ponentes disponibles. Para tener uno, aprueba un usuario
            con el rol "ponente" en la sección Usuarios.
        </p>
    <?php else: ?>
        <form method="POST" action="acciones_ponentes.php" class="form-linea">
            <input type="hidden" name="accion" value="asignar">
            <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
            <select name="usuario_id" required class="select-linea">
                <option value="">Elige un ponente…</option>
                <?php foreach ($disponibles as $d): ?>
                    <option value="<?php echo (int) $d["id"]; ?>">
                        <?php echo htmlspecialchars($d["nombre"] . " (" . $d["email"] . ")"); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-principal">+ Asignar ponente</button>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Alumnos del curso -->
<div class="tarjeta panel-asignacion panel-alumnos">
    <h2>Alumnos <span class="contador"><?php echo count($alumnos); ?></span></h2>

    <?php if (count($alumnos) === 0): ?>
        <p>Este curso todavía no tiene alumnos inscritos.</p>
    <?php else: ?>
        <ul class="lista-personas">
            <?php foreach ($alumnos as $a):
                $pct = $totalLecciones > 0 ? round($a["hechas"] / $totalLecciones * 100) : 0;
            ?>
                <li>
                    <div>
                        <strong><?php echo htmlspecialchars($a["nombre"]); ?></strong>
                        <small><?php echo htmlspecialchars($a["email"]); ?></small>
                    </div>
                    <div class="progreso-alumno">
                        <div class="progreso">
                            <div class="progreso-barra" style="width: <?php echo $pct; ?>%"></div>
                        </div>
                        <small><?php echo $pct; ?>% (<?php echo (int) $a["hechas"]; ?> de <?php echo $totalLecciones; ?>)</small>
                    </div>

                    <?php if (!$esPonente): ?>
                        <form method="POST" action="../acciones_inscripciones.php"
                              onsubmit="return confirm('¿Quitar a este alumno del curso? Se borrará también su progreso.');">
                            <input type="hidden" name="accion" value="quitar">
                            <input type="hidden" name="inscripcion_id" value="<?php echo (int) $a["inscripcion_id"]; ?>">
                            <input type="hidden" name="curso_filtro" value="<?php echo $cursoId; ?>">
                            <input type="hidden" name="volver" value="curso">
                            <button type="submit" class="btn-peligro mini">Quitar</button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!$esPonente): ?>

        <?php if (count($alumnosDisponibles) === 0): ?>
            <p class="nota">
                No hay más alumnos disponibles para inscribir. Los alumnos aparecen aquí
                cuando se registran y el administrador los aprueba.
            </p>
        <?php else: ?>
            <details class="editar">
                <summary>+ Inscribir alumnos</summary>
                <form method="POST" action="../acciones_inscripciones.php" class="formulario">
                    <input type="hidden" name="accion" value="inscribir">
                    <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                    <input type="hidden" name="volver" value="curso">

                    <div class="lista-checks">
                        <?php foreach ($alumnosDisponibles as $d): ?>
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
            </details>
        <?php endif; ?>

        <p class="nota">
            Las solicitudes de los alumnos se aprueban en
            <a href="<?php echo $panel; ?>?seccion=inscripciones&curso=<?php echo $cursoId; ?>">Inscripciones</a>.
        </p>

    <?php endif; ?>
</div>



<div class="tarjeta panel-estructura">
    <h2>
        <?php echo htmlspecialchars($curso["nombre"]); ?>
        <span class="etiqueta estado-<?php echo htmlspecialchars($curso["estado"]); ?>">
            <?php echo htmlspecialchars($curso["estado"]); ?>
        </span>
    </h2>

    <h3 class="subtitulo-seccion">Estructura del curso</h3>
    <p class="ayuda-estructura">
        Organiza el contenido por bloques (unidades o temas) y agrega dentro de cada uno sus lecciones.
    </p>

    <!-- Crear bloque -->
    <form method="POST" action="acciones_bloques.php" class="form-linea formulario-bloque">
        <input type="hidden" name="accion" value="crear_bloque">
        <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
        <div class="campo-admin">
            <label for="titulo-nuevo-bloque">Nombre del bloque</label>
            <input id="titulo-nuevo-bloque" type="text" name="titulo" maxlength="150"
                   placeholder="Ej. Unidad 1: Introducción" required>
        </div>
        <button type="submit" class="btn-principal">Crear bloque</button>
    </form>
</div>









<?php if (count($bloques) === 0): ?>

    <div class="tarjeta">
        <p>Este curso todavía no tiene bloques. Crea el primero con el formulario de arriba.</p>
    </div>

<?php endif; ?>

<?php foreach ($bloques as $indiceBloque => $b): ?>
    <div class="tarjeta bloque tarjeta-bloque">

        <!-- Cabecera del bloque -->
        <div class="fila-item bloque-cabecera">
            <div class="bloque-identidad">
                <span class="bloque-numero">Bloque <?php echo $indiceBloque + 1; ?></span>
                <h3 class="titulo-item"><?php echo htmlspecialchars($b["titulo"]); ?></h3>
                <span class="contador contador-lecciones">
                    <?php echo count($subs[$b["id"]] ?? []); ?> lecciones
                </span>
            </div>
            <div class="controles">
                <?php botonesMover("bloque", (int) $b["id"]); ?>
                <form method="POST" action="acciones_bloques.php"
                      onsubmit="return confirm('¿Eliminar este bloque y todos sus subbloques?');">
                    <input type="hidden" name="accion" value="eliminar_bloque">
                    <input type="hidden" name="id" value="<?php echo (int) $b["id"]; ?>">
                    <button type="submit" class="btn-peligro mini">Eliminar</button>
                </form>
            </div>
        </div>

        <details class="editar">
            <summary>Editar nombre del bloque</summary>
            <form method="POST" action="acciones_bloques.php" class="form-linea">
                <input type="hidden" name="accion" value="editar_bloque">
                <input type="hidden" name="id" value="<?php echo (int) $b["id"]; ?>">
                <input type="text" name="titulo" maxlength="150" required
                       value="<?php echo htmlspecialchars($b["titulo"]); ?>">
                <button type="submit" class="btn-principal">Guardar</button>
            </form>
        </details>

        <!-- Subbloques -->
        <div class="subbloques">
            <?php if (empty($subs[$b["id"]])): ?>
                <p class="bloque-vacio">Este bloque todavía no tiene lecciones. Puedes agregar la primera aquí.</p>
            <?php endif; ?>
            <?php foreach ($subs[$b["id"]] ?? [] as $s): ?>
                <div class="subbloque">
                    <div class="fila-item">
                        <span class="titulo-item titulo-leccion"><?php echo htmlspecialchars($s["titulo"]); ?></span>
                        <div class="controles">


                        <?php if (trim((string) $s["contenido"]) === ""): ?>
                            <span class="etiqueta estado-borrador">Sin contenido</span>
                        <?php endif; ?>
                            <a class="btn-principal mini-enlace"
                            href="../editor_leccion.php?id=<?php echo (int) $s["id"]; ?>">Editar contenido</a>



                            <?php botonesMover("subbloque", (int) $s["id"]); ?>
                            <form method="POST" action="acciones_bloques.php"
                                  onsubmit="return confirm('¿Eliminar este subbloque?');">
                                <input type="hidden" name="accion" value="eliminar_subbloque">
                                <input type="hidden" name="id" value="<?php echo (int) $s["id"]; ?>">
                                <button type="submit" class="btn-peligro mini">Eliminar</button>
                            </form>
                        </div>
                    </div>

                    <details class="editar">
                        <summary>Editar nombre de la lección</summary>
                        <form method="POST" action="acciones_bloques.php" class="form-linea">
                            <input type="hidden" name="accion" value="editar_subbloque">
                            <input type="hidden" name="id" value="<?php echo (int) $s["id"]; ?>">
                            <input type="text" name="titulo" maxlength="150" required
                                value="<?php echo htmlspecialchars($s["titulo"]); ?>">
                            <button type="submit" class="btn-principal">Guardar</button>
                            </form>
                    </details>
                </div>
            <?php endforeach; ?>

            <!-- Nueva lección -->
            <form method="POST" action="acciones_bloques.php" class="form-linea formulario-leccion">
                <input type="hidden" name="accion" value="crear_subbloque">
                <input type="hidden" name="bloque_id" value="<?php echo (int) $b["id"]; ?>">
                <div class="campo-admin">
                    <label for="titulo-leccion-<?php echo (int) $b["id"]; ?>">Título de la nueva lección</label>
                    <input id="titulo-leccion-<?php echo (int) $b["id"]; ?>" type="text" name="titulo"
                           maxlength="150" placeholder="Ej. Conceptos básicos" required>
                </div>
                <button type="submit" class="btn-link">Añadir lección</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>


<!-- Ejercicios del curso -->
<div class="tarjeta ejercicios-admin panel-ejercicios">
    <h2>Ejercicios <span class="contador"><?php echo count($ejerciciosCurso); ?></span></h2>
    <p>Asigna cada ejercicio a un bloque. Las preguntas de opción múltiple se califican automáticamente; las respuestas abiertas las revisa el equipo docente.</p>

    <?php if (!$bloques): ?>
        <p class="nota">Crea primero un bloque para poder asignarle ejercicios.</p>
    <?php else: ?>
    <details class="editar crear-ejercicio-detalle">
        <summary>+ Crear ejercicio</summary>
        <form method="POST" action="../acciones_ejercicios.php" class="formulario formulario-ejercicio" data-form-ejercicio>
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION["csrf_ejercicios"]); ?>">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
            <div class="fila-campos">
                <div class="campo-admin">
                    <label for="bloque-ejercicio-nuevo">Bloque</label>
                    <select id="bloque-ejercicio-nuevo" name="bloque_id" required>
                        <option value="">Selecciona el bloque</option>
                        <?php foreach ($bloques as $bloque): ?>
                            <option value="<?php echo (int) $bloque["id"]; ?>"><?php echo htmlspecialchars($bloque["titulo"]); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo-admin">
                    <label for="tipo-ejercicio-nuevo">Tipo de ejercicio</label>
                    <select id="tipo-ejercicio-nuevo" name="tipo" data-tipo-ejercicio>
                        <option value="abierta">Respuesta abierta</option>
                        <option value="multiple">Opción múltiple · calificación automática</option>
                    </select>
                </div>
            </div>
            <div class="campo-admin">
                <label for="titulo-ejercicio">Título</label>
                <input id="titulo-ejercicio" type="text" name="titulo" maxlength="150" required>
            </div>
            <div class="campo-admin">
                <label for="instrucciones-ejercicio">Pregunta o instrucciones</label>
                <textarea id="instrucciones-ejercicio" name="instrucciones" rows="5" maxlength="20000" required></textarea>
            </div>
            <div class="campo-admin ejercicio-maximo">
                <label for="maximo-ejercicio">Puntuación máxima</label>
                <input id="maximo-ejercicio" type="number" name="puntuacion_maxima" min="0.01" max="1000" step="0.01" value="10" required>
            </div>
            <fieldset class="opciones-quiz" data-opciones-quiz hidden>
                <legend>Respuestas posibles</legend>
                <p>Escribe cuatro opciones y marca la respuesta correcta. Se otorgará el puntaje completo o cero.</p>
                <?php foreach (range(0, 3) as $indiceOpcion): ?>
                    <div class="opcion-quiz-campo">
                        <label class="opcion-quiz-correcta" for="correcta-nueva-<?php echo $indiceOpcion; ?>">
                            <input id="correcta-nueva-<?php echo $indiceOpcion; ?>" type="radio" name="correcta" value="<?php echo $indiceOpcion; ?>" <?php echo $indiceOpcion === 0 ? 'checked' : ''; ?>>
                            <span>Correcta</span>
                        </label>
                        <div class="campo-admin">
                            <label for="opcion-nueva-<?php echo $indiceOpcion; ?>">Opción <?php echo chr(65 + $indiceOpcion); ?></label>
                            <input id="opcion-nueva-<?php echo $indiceOpcion; ?>" type="text" name="opciones[]" maxlength="500" data-opcion-quiz required>
                        </div>
                    </div>
                <?php endforeach; ?>
            </fieldset>
            <button type="submit" class="btn-principal">Crear ejercicio</button>
        </form>
    </details>
    <?php endif; ?>

    <?php if (!$ejerciciosCurso): ?>
        <p>Aún no hay ejercicios en este curso.</p>
    <?php else: ?>
        <div class="ejercicios-bloques-admin">
            <?php foreach ($ejerciciosPorBloque as $grupoEjercicios): ?>
                <section class="ejercicios-bloque-admin">
                    <header class="ejercicios-bloque-admin-cabecera">
                        <div>
                            <span class="ejercicios-bloque-admin-etiqueta">Bloque</span>
                            <h3><?php echo htmlspecialchars($grupoEjercicios["titulo"]); ?></h3>
                        </div>
                        <span class="contador"><?php echo count($grupoEjercicios["ejercicios"]); ?> ejercicios</span>
                    </header>
                    <div class="ejercicios-lista-admin">
                    <?php foreach ($grupoEjercicios["ejercicios"] as $ejercicio): ?>
                <article class="ejercicio-admin-item">
                    <div class="fila-item">
                        <div class="ejercicio-admin-identidad">
                            <strong><?php echo htmlspecialchars($ejercicio["titulo"]); ?></strong>
                            <span class="etiqueta tipo-ejercicio-etiqueta"><?php echo $ejercicio["tipo"] === "multiple" ? "Opción múltiple · automática" : "Respuesta abierta"; ?></span>
                        </div>
                        <span class="etiqueta estado-activo">Máximo: <?php echo number_format((float) $ejercicio["puntuacion_maxima"], 2); ?> puntos</span>
                    </div>
                    <p><?php echo nl2br(htmlspecialchars($ejercicio["instrucciones"])); ?></p>
                    <small><?php echo (int) $ejercicio["entregas"]; ?> entregas</small>

                    <?php if ((int) $ejercicio["entregas"] === 0): ?>
                        <details class="editar">
                            <summary>Editar ejercicio</summary>
                            <form method="POST" action="../acciones_ejercicios.php" class="formulario formulario-ejercicio" data-form-ejercicio>
                                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION["csrf_ejercicios"]); ?>">
                                <input type="hidden" name="accion" value="editar">
                                <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                                <input type="hidden" name="ejercicio_id" value="<?php echo (int) $ejercicio["id"]; ?>">
                                <div class="fila-campos">
                                    <div class="campo-admin">
                                        <label for="bloque-ejercicio-<?php echo (int) $ejercicio["id"]; ?>">Bloque</label>
                                        <select id="bloque-ejercicio-<?php echo (int) $ejercicio["id"]; ?>" name="bloque_id" required>
                                            <?php foreach ($bloques as $bloque): ?>
                                                <option value="<?php echo (int) $bloque["id"]; ?>" <?php echo (int) $ejercicio["bloque_id"] === (int) $bloque["id"] ? 'selected' : ''; ?>><?php echo htmlspecialchars($bloque["titulo"]); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="campo-admin">
                                        <label for="tipo-ejercicio-<?php echo (int) $ejercicio["id"]; ?>">Tipo de ejercicio</label>
                                        <select id="tipo-ejercicio-<?php echo (int) $ejercicio["id"]; ?>" name="tipo" data-tipo-ejercicio>
                                            <option value="abierta" <?php echo $ejercicio["tipo"] === "abierta" ? 'selected' : ''; ?>>Respuesta abierta</option>
                                            <option value="multiple" <?php echo $ejercicio["tipo"] === "multiple" ? 'selected' : ''; ?>>Opción múltiple · calificación automática</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="campo-admin">
                                    <label for="titulo-ejercicio-<?php echo (int) $ejercicio["id"]; ?>">Título</label>
                                    <input id="titulo-ejercicio-<?php echo (int) $ejercicio["id"]; ?>" type="text" name="titulo" maxlength="150" required value="<?php echo htmlspecialchars($ejercicio["titulo"]); ?>">
                                </div>
                                <div class="campo-admin">
                                    <label for="instrucciones-ejercicio-<?php echo (int) $ejercicio["id"]; ?>">Pregunta o instrucciones</label>
                                    <textarea id="instrucciones-ejercicio-<?php echo (int) $ejercicio["id"]; ?>" name="instrucciones" rows="4" maxlength="20000" required><?php echo htmlspecialchars($ejercicio["instrucciones"]); ?></textarea>
                                </div>
                                <div class="campo-admin ejercicio-maximo">
                                    <label for="maximo-ejercicio-<?php echo (int) $ejercicio["id"]; ?>">Puntuación máxima</label>
                                    <input id="maximo-ejercicio-<?php echo (int) $ejercicio["id"]; ?>" type="number" name="puntuacion_maxima" min="0.01" max="1000" step="0.01" required value="<?php echo htmlspecialchars((string) $ejercicio["puntuacion_maxima"]); ?>">
                                </div>
                                <fieldset class="opciones-quiz" data-opciones-quiz <?php echo $ejercicio["tipo"] === "multiple" ? '' : 'hidden'; ?>>
                                    <legend>Respuestas posibles</legend>
                                    <p>Marca cuál es la respuesta correcta. Se otorgará el puntaje completo o cero.</p>
                                    <?php
                                    $opcionesActuales = $opcionesEjercicios[(int) $ejercicio["id"]] ?? [];
                                    for ($indiceOpcion = 0; $indiceOpcion < 4; $indiceOpcion++):
                                        $opcionActual = $opcionesActuales[$indiceOpcion] ?? null;
                                    ?>
                                        <div class="opcion-quiz-campo">
                                            <label class="opcion-quiz-correcta" for="correcta-<?php echo (int) $ejercicio["id"]; ?>-<?php echo $indiceOpcion; ?>">
                                                <input id="correcta-<?php echo (int) $ejercicio["id"]; ?>-<?php echo $indiceOpcion; ?>" type="radio" name="correcta" value="<?php echo $indiceOpcion; ?>" <?php echo ($opcionActual && (int) $opcionActual["es_correcta"] === 1) || (!$opcionesActuales && $indiceOpcion === 0) ? 'checked' : ''; ?>>
                                                <span>Correcta</span>
                                            </label>
                                            <div class="campo-admin">
                                                <label for="opcion-<?php echo (int) $ejercicio["id"]; ?>-<?php echo $indiceOpcion; ?>">Opción <?php echo chr(65 + $indiceOpcion); ?></label>
                                                <input id="opcion-<?php echo (int) $ejercicio["id"]; ?>-<?php echo $indiceOpcion; ?>" type="text" name="opciones[]" maxlength="500" data-opcion-quiz value="<?php echo htmlspecialchars($opcionActual["texto"] ?? ''); ?>" <?php echo $ejercicio["tipo"] === "multiple" ? 'required' : ''; ?>>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                </fieldset>
                                <button type="submit" class="btn-principal">Guardar cambios</button>
                            </form>
                        </details>
                        <form method="POST" action="../acciones_ejercicios.php" onsubmit="return confirm('¿Eliminar este ejercicio?');">
                            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION["csrf_ejercicios"]); ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
                            <input type="hidden" name="ejercicio_id" value="<?php echo (int) $ejercicio["id"]; ?>">
                            <button type="submit" class="btn-peligro mini">Eliminar</button>
                        </form>
                    <?php else: ?>
                        <p class="nota">No se puede editar ni eliminar después de recibir entregas para conservar el historial de calificaciones.</p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</fieldset>

<script>
document.querySelectorAll("[data-form-ejercicio]").forEach((formulario) => {
    const tipo = formulario.querySelector("[data-tipo-ejercicio]");
    const opciones = formulario.querySelector("[data-opciones-quiz]");
    const actualizarTipo = () => {
        const esMultiple = tipo.value === "multiple";
        opciones.hidden = !esMultiple;
        opciones.querySelectorAll("[data-opcion-quiz]").forEach((campo) => {
            campo.required = esMultiple;
            campo.disabled = !esMultiple;
        });
        opciones.querySelectorAll("input[type=radio]").forEach((campo) => {
            campo.disabled = !esMultiple;
        });
    };
    tipo.addEventListener("change", actualizarTipo);
    actualizarTipo();
});
</script>