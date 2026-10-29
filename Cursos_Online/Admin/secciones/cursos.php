<?php
require_once __DIR__ . "/../../permisos.php";

// Lo usan Admin y Director. $panel indica el archivo del panel para los enlaces.
$panel = $panel ?? "admin.php";

$rolPanel = strtolower($_SESSION["rol"] ?? "");
$esAdmin  = $rolPanel === "admin";
$dirId    = $rolPanel === "director" ? (int) $_SESSION["usuario_id"] : 0;   // 0 = sin límite

// Si se pidió gestionar un curso, mostrar su detalle y terminar aquí
if (isset($_GET["gestionar"])) {
    include __DIR__ . "/curso_detalle.php";
    return;
}

$mensajes = [
    "creado"          => ["ok",    "Curso creado correctamente."],
    "actualizado"     => ["ok",    "Curso actualizado correctamente."],
    "eliminado"       => ["ok",    "Curso eliminado."],
    "datos_invalidos" => ["error", "Revisa los datos: el nombre es obligatorio y la fecha de fin no puede ser anterior a la de inicio."],
    "sin_permiso"     => ["error", "No tienes permiso para hacer esa acción en este curso."],
    "no_aprobado"     => ["error", "El curso necesita la aprobación del administrador antes de salir de borrador."],
    "no_eliminar"     => ["error", "Solo se pueden eliminar cursos en borrador. Pide al administrador que elimine los publicados."],
    "bloqueado"       => ["error", "El curso está en revisión y no se puede editar. Retíralo de revisión si necesitas hacer cambios."],
    "requisito_bloques" => ["error", "Agrega al menos un bloque antes de enviar o publicar el curso."],
    "requisito_lecciones" => ["error", "Agrega al menos una lección dentro de un bloque antes de enviar o publicar el curso."],
    "requisito_ponentes" => ["error", "Asigna al menos un ponente al curso antes de enviarlo o publicarlo."],
    "requisito_director" => ["error", "Asigna un director al curso antes de publicarlo."],
    "curso_borrador_requerido" => ["error", "Los cursos nuevos se crean como borrador; agrega bloques, lecciones y asignaciones antes de activarlos."],
    "error"           => ["error", "No se pudo completar la acción."],
];
$msg = $_GET["msg"] ?? "";

$editarId = (int) ($_GET["editar"] ?? 0);
$esNuevo  = isset($_GET["nuevo"]);
$curso    = null;

if ($editarId > 0) {
    $stmt = $conexion->prepare(
        "SELECT * FROM cursos WHERE id = ? AND (? = 0 OR director_id = ?)"
    );
    $stmt->bind_param("iii", $editarId, $dirId, $dirId);
    $stmt->execute();
    $curso = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Un director no edita mientras el curso está en revisión
    if ($curso && $dirId > 0 && $curso["aprobacion"] === "en_revision") {
        $curso = null;
        $msg   = "bloqueado";
    }
}

$mostrarFormulario = $esNuevo || $curso !== null;
?>

<?php if (isset($mensajes[$msg])): ?>
    <div class="aviso-admin <?php echo $mensajes[$msg][0]; ?>">
        <?php echo htmlspecialchars($mensajes[$msg][1]); ?>
    </div>
<?php endif; ?>

<?php if ($mostrarFormulario): ?>

    <!-- ================= FORMULARIO (crear / editar) ================= -->
    <?php
    // El estado solo es libre para el admin o cuando el curso ya está aprobado
    $estadoLibre = $esAdmin || ($curso && $curso["aprobacion"] === "aprobado");
    ?>
    <div class="tarjeta">
        <h2><?php echo $curso ? "Editar curso" : "Nuevo curso"; ?></h2>

        <form method="POST" action="acciones_cursos.php" class="formulario">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?php echo $curso ? (int) $curso["id"] : 0; ?>">

            <div class="campo-admin">
                <label for="nombre">Nombre del curso</label>
                <input type="text" id="nombre" name="nombre" maxlength="150" required
                       value="<?php echo htmlspecialchars($curso["nombre"] ?? ""); ?>">
            </div>

            <div class="campo-admin">
                <label for="descripcion">Descripción</label>
                <textarea id="descripcion" name="descripcion" rows="4"><?php
                    echo htmlspecialchars($curso["descripcion"] ?? "");
                ?></textarea>
            </div>

            <div class="fila-campos">
                <div class="campo-admin">
                    <label for="fecha_inicio">Fecha de inicio</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio"
                           value="<?php echo htmlspecialchars($curso["fecha_inicio"] ?? ""); ?>">
                </div>
                <div class="campo-admin">
                    <label for="fecha_fin">Fecha de fin</label>
                    <input type="date" id="fecha_fin" name="fecha_fin"
                           value="<?php echo htmlspecialchars($curso["fecha_fin"] ?? ""); ?>">
                </div>

                <?php if ($estadoLibre && $curso): ?>
                    <div class="campo-admin">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado">
                            <?php foreach (["borrador", "activo", "finalizado"] as $e): ?>
                                <option value="<?php echo $e; ?>"
                                    <?php echo ($curso["estado"] ?? "borrador") === $e ? "selected" : ""; ?>>
                                    <?php echo ucfirst($e); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="estado" value="borrador">
                <?php endif; ?>
            </div>

            <?php if (!$estadoLibre): ?>
                <p class="nota">
                    El curso se guarda como borrador. Cuando esté listo, envíalo a revisión
                    desde su pantalla de gestión; podrás publicarlo cuando el administrador lo apruebe.
                </p>
            <?php endif; ?>

            <div class="acciones-form">
                <button type="submit" class="btn-principal">Guardar</button>
                <a href="<?php echo $panel; ?>?seccion=cursos" class="btn-link">Cancelar</a>
            </div>
        </form>
    </div>

<?php else: ?>

    <!-- ================= LISTA ================= -->
    <?php
    $stmt = $conexion->prepare(
        "SELECT c.id, c.nombre, c.estado, c.aprobacion, c.fecha_inicio, c.fecha_fin,
                (SELECT COUNT(*) FROM inscripciones i WHERE i.curso_id = c.id) AS alumnos,
                (SELECT COUNT(*) FROM bloques b WHERE b.curso_id = c.id) AS bloques,
                (SELECT u.nombre FROM usuarios u WHERE u.id = c.director_id) AS director
         FROM cursos c
         WHERE (? = 0 OR c.director_id = ?)
         ORDER BY c.id DESC"
    );
    $stmt->bind_param("ii", $dirId, $dirId);
    $stmt->execute();
    $cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    ?>

    <div class="barra-superior">
        <a href="<?php echo $panel; ?>?seccion=cursos&nuevo=1" class="btn-principal">+ Nuevo curso</a>
    </div>

    <div class="tarjeta">
        <?php if (count($cursos) === 0): ?>
            <p>
                <?php echo $esAdmin
                    ? "Todavía no hay cursos. Crea el primero con el botón de arriba."
                    : "Todavía no tienes cursos. Crea el primero con el botón de arriba."; ?>
            </p>
        <?php else: ?>
            <div class="tabla-scroll">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th>Estado</th>
                        <th>Revisión</th>
                        <?php if ($esAdmin): ?><th>Director</th><?php endif; ?>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Bloques</th>
                        <th>Alumnos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cursos as $c):
                    [$revTexto, $revClase] = ETIQUETAS_REVISION[$c["aprobacion"]];
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c["nombre"]); ?></td>
                        <td>
                            <span class="etiqueta estado-<?php echo htmlspecialchars($c["estado"]); ?>">
                                <?php echo htmlspecialchars($c["estado"]); ?>
                            </span>
                        </td>
                        <td><span class="etiqueta <?php echo $revClase; ?>"><?php echo $revTexto; ?></span></td>
                        <?php if ($esAdmin): ?>
                            <td><?php echo $c["director"] ? htmlspecialchars($c["director"]) : "—"; ?></td>
                        <?php endif; ?>
                        <td><?php echo $c["fecha_inicio"] ? date("d/m/Y", strtotime($c["fecha_inicio"])) : "—"; ?></td>
                        <td><?php echo $c["fecha_fin"] ? date("d/m/Y", strtotime($c["fecha_fin"])) : "—"; ?></td>
                        <td><?php echo (int) $c["bloques"]; ?></td>
                        <td><?php echo (int) $c["alumnos"]; ?></td>
                        <td>
                            <div class="acciones">
                                <a href="<?php echo $panel; ?>?seccion=cursos&gestionar=<?php echo (int) $c["id"]; ?>"
                                   class="btn-principal">Gestionar</a>
                                <a href="<?php echo $panel; ?>?seccion=cursos&editar=<?php echo (int) $c["id"]; ?>"
                                   class="btn-link">Editar</a>

                                <?php if ($esAdmin || $c["estado"] === "borrador"): ?>
                                    <form method="POST" action="acciones_cursos.php"
                                          onsubmit="return confirm('¿Eliminar este curso? Se borrarán también sus bloques, lecciones e inscripciones.');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo (int) $c["id"]; ?>">
                                        <button type="submit" class="btn-peligro">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>