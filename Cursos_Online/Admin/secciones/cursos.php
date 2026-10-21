<!-- la lista de cursos y el formulario, que sirve para crear y para editar. -->
 <?php
$mensajes = [
    "creado"          => ["ok",    "Curso creado correctamente."],
    "actualizado"     => ["ok",    "Curso actualizado correctamente."],
    "eliminado"       => ["ok",    "Curso eliminado."],
    "datos_invalidos" => ["error", "Revisa los datos: el nombre es obligatorio y la fecha de fin no puede ser anterior a la de inicio."],
    "error"           => ["error", "No se pudo completar la acción."],
];
$msg = $_GET["msg"] ?? "";

$editarId  = (int) ($_GET["editar"] ?? 0);
$esNuevo   = isset($_GET["nuevo"]);
$curso     = null;

if ($editarId > 0) {
    $stmt = $conexion->prepare("SELECT * FROM cursos WHERE id = ?");
    $stmt->bind_param("i", $editarId);
    $stmt->execute();
    $curso = $stmt->get_result()->fetch_assoc();
    $stmt->close();
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
            </div>

            <div class="acciones-form">
                <button type="submit" class="btn-principal">Guardar</button>
                <a href="admin.php?seccion=cursos" class="btn-link">Cancelar</a>
            </div>
        </form>
    </div>

<?php else: ?>

    <!-- ================= LISTA ================= -->
    <?php
    $cursos = $conexion->query(
        "SELECT c.id, c.nombre, c.estado, c.fecha_inicio, c.fecha_fin,
                (SELECT COUNT(*) FROM inscripciones i WHERE i.curso_id = c.id) AS alumnos,
                (SELECT COUNT(*) FROM bloques b WHERE b.curso_id = c.id) AS bloques
         FROM cursos c
         ORDER BY c.id DESC"
    )->fetch_all(MYSQLI_ASSOC);
    ?>

    <div class="barra-superior">
        <a href="admin.php?seccion=cursos&nuevo=1" class="btn-principal">+ Nuevo curso</a>
    </div>

    <div class="tarjeta">
        <?php if (count($cursos) === 0): ?>
            <p>Todavía no hay cursos. Crea el primero con el botón de arriba.</p>
        <?php else: ?>
            <div class="tabla-scroll">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Curso</th>
                        <th>Estado</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Bloques</th>
                        <th>Alumnos</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cursos as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c["nombre"]); ?></td>
                        <td>
                            <span class="etiqueta estado-<?php echo htmlspecialchars($c["estado"]); ?>">
                                <?php echo htmlspecialchars($c["estado"]); ?>
                            </span>
                        </td>
                        <td><?php echo $c["fecha_inicio"] ? date("d/m/Y", strtotime($c["fecha_inicio"])) : "—"; ?></td>
                        <td><?php echo $c["fecha_fin"] ? date("d/m/Y", strtotime($c["fecha_fin"])) : "—"; ?></td>
                        <td><?php echo (int) $c["bloques"]; ?></td>
                        <td><?php echo (int) $c["alumnos"]; ?></td>
                        <td>
                            <div class="acciones">
                                <a href="admin.php?seccion=cursos&editar=<?php echo (int) $c["id"]; ?>"
                                   class="btn-link">Editar</a>
                                <form method="POST" action="acciones_cursos.php"
                                      onsubmit="return confirm('¿Eliminar este curso? Se borrarán también sus bloques, lecciones e inscripciones.');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?php echo (int) $c["id"]; ?>">
                                    <button type="submit" class="btn-peligro">Eliminar</button>
                                </form>
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
