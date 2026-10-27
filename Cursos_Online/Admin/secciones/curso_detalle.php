<?php
require_once __DIR__ . "/../../permisos.php";

$cursoId = (int) ($_GET["gestionar"] ?? 0);

$rolDetalle = strtolower($_SESSION["rol"] ?? "");
$esAdmin    = $rolDetalle === "admin";
$esPonente  = $rolDetalle === "ponente";

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
    "sin_permiso"       => ["error", "No tienes permiso para hacer esa acción en este curso."],
    "insc_creadas" => ["ok",    "Alumnos inscritos correctamente."],
    "insc_quitada" => ["ok",    "Alumno quitado del curso, junto con su progreso."],
    "insc_nada"    => ["error", "No se inscribió a nadie: elige al menos un alumno disponible."],
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
<div class="tarjeta">
    <h2>
        Revisión y publicación
        <span class="etiqueta <?php echo $revClase; ?>"><?php echo $revTexto; ?></span>
    </h2>

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
                <button type="submit" class="btn-principal">Enviar a revisión</button>
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
                <button type="submit" class="btn-principal">Volver a enviar a revisión</button>
            </form>
        <?php endif; ?>

    <?php else: /* aprobado */ ?>

        <?php if ($curso["estado"] === "borrador"): ?>
            <p>El curso está aprobado. Cuando quieras, publícalo para que los alumnos puedan verlo.</p>
            <form method="POST" action="acciones_cursos.php">
                <input type="hidden" name="accion" value="publicar">
                <input type="hidden" name="id" value="<?php echo $cursoId; ?>">
                <button type="submit" class="btn-principal">Publicar curso</button>
            </form>
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
<div class="tarjeta">
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

<div class="tarjeta">
    <h2>
        <?php echo htmlspecialchars($curso["nombre"]); ?>
        <span class="etiqueta estado-<?php echo htmlspecialchars($curso["estado"]); ?>">
            <?php echo htmlspecialchars($curso["estado"]); ?>
        </span>
    </h2>

    <h3 class="subtitulo-seccion">Contenido del curso</h3>

    <!-- Nuevo bloque -->
    <form method="POST" action="acciones_bloques.php" class="form-linea">
        <input type="hidden" name="accion" value="crear_bloque">
        <input type="hidden" name="curso_id" value="<?php echo $cursoId; ?>">
        <input type="text" name="titulo" maxlength="150" placeholder="Título del nuevo bloque" required>
        <button type="submit" class="btn-principal">+ Añadir bloque</button>
    </form>
</div>

<?php if (!$esPonente): ?>
<!-- Ponentes del curso -->
<div class="tarjeta">
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
<div class="tarjeta">
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

<?php if (count($bloques) === 0): ?>

    <div class="tarjeta">
        <p>Este curso todavía no tiene bloques. Crea el primero con el formulario de arriba.</p>
    </div>

<?php endif; ?>

<?php foreach ($bloques as $b): ?>
    <div class="tarjeta bloque">

        <!-- Cabecera del bloque -->
        <div class="fila-item">
            <strong class="titulo-item"><?php echo htmlspecialchars($b["titulo"]); ?></strong>
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
            <summary>Renombrar bloque</summary>
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
            <?php foreach ($subs[$b["id"]] ?? [] as $s): ?>
                <div class="subbloque">
                    <div class="fila-item">
                        <span class="titulo-item">• <?php echo htmlspecialchars($s["titulo"]); ?></span>
                        <div class="controles">
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
                        <summary>Editar subbloque</summary>
                        <form method="POST" action="acciones_bloques.php" class="formulario">
                            <input type="hidden" name="accion" value="editar_subbloque">
                            <input type="hidden" name="id" value="<?php echo (int) $s["id"]; ?>">
                            <div class="campo-admin">
                                <label>Título</label>
                                <input type="text" name="titulo" maxlength="150" required
                                       value="<?php echo htmlspecialchars($s["titulo"]); ?>">
                            </div>
                            <div class="campo-admin">
                                <label>Contenido</label>
                                <textarea name="contenido" rows="4"><?php
                                    echo htmlspecialchars($s["contenido"] ?? "");
                                ?></textarea>
                            </div>
                            <div class="acciones-form">
                                <button type="submit" class="btn-principal">Guardar</button>
                            </div>
                        </form>
                    </details>
                </div>
            <?php endforeach; ?>

            <!-- Nuevo subbloque -->
            <form method="POST" action="acciones_bloques.php" class="form-linea">
                <input type="hidden" name="accion" value="crear_subbloque">
                <input type="hidden" name="bloque_id" value="<?php echo (int) $b["id"]; ?>">
                <input type="text" name="titulo" maxlength="150" placeholder="Título del nuevo subbloque" required>
                <button type="submit" class="btn-link">+ Añadir subbloque</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</fieldset>