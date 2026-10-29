<?php
$verId = (int) ($_GET["ver"] ?? 0);

$stmt = $conexion->prepare(
    "SELECT u.id, u.nombre, u.email, u.email_verificado, u.aceptado, r.nombre AS rol
     FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE u.id = ?"
);
$stmt->bind_param("i", $verId);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    echo '<div class="aviso-admin error">El usuario no existe.</div>';
    echo '<a href="admin.php?seccion=usuarios" class="btn-link">← Volver a usuarios</a>';
    return;
}

$mensajesDet = [
    "rol_cambiado" => ["ok",    "Rol actualizado. La persona verá su panel nuevo al navegar."],
    "rol_igual"    => ["error", "El usuario ya tiene ese rol."],
    "error"        => ["error", "No se pudo completar la acción."],
];
$msgDet = $_GET["msg"] ?? "";

// Roles disponibles
$rolesDisp = $conexion->query("SELECT id, nombre FROM roles ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// Cuenta registros de un usuario con una consulta que recibe solo su id
function contarPorUsuario(mysqli $c, string $sql, int $uid): int
{
    $stmt = $c->prepare($sql);
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $n = (int) $stmt->get_result()->fetch_assoc()["n"];
    $stmt->close();
    return $n;
}

// Qué se perdería al cambiar su rol
$avisosRol = [];
$rolActualNombre = strtolower($usuario["rol"]);
if ($rolActualNombre === "alumno") {
    $n = contarPorUsuario($conexion, "SELECT COUNT(*) AS n FROM inscripciones WHERE usuario_id = ?", $verId);
    if ($n > 0) $avisosRol[] = "Sus $n inscripciones y su progreso en esos cursos.";
    $n = contarPorUsuario($conexion, "SELECT COUNT(*) AS n FROM solicitudes_inscripcion WHERE usuario_id = ?", $verId);
    if ($n > 0) $avisosRol[] = "Sus $n solicitudes de inscripción.";
} elseif ($rolActualNombre === "ponente") {
    $n = contarPorUsuario($conexion, "SELECT COUNT(*) AS n FROM curso_ponentes WHERE usuario_id = ?", $verId);
    if ($n > 0) $avisosRol[] = "Su asignación como ponente en $n cursos.";
} elseif ($rolActualNombre === "director") {
    $n = contarPorUsuario($conexion, "SELECT COUNT(*) AS n FROM cursos WHERE director_id = ?", $verId);
    if ($n > 0) $avisosRol[] = "Dejará de ser director de $n cursos (los cursos quedan sin director).";
}




// Cursos en los que está inscrito, con su progreso
$stmt = $conexion->prepare(
    "SELECT c.id, c.nombre, c.estado,
            (SELECT COUNT(*) FROM subbloques s
              INNER JOIN bloques b ON s.bloque_id = b.id
              WHERE b.curso_id = c.id) AS lecciones,
            (SELECT COUNT(*) FROM progreso_subbloques p
              WHERE p.inscripcion_id = i.id) AS hechas
     FROM inscripciones i
     INNER JOIN cursos c ON i.curso_id = c.id
     WHERE i.usuario_id = ?
     ORDER BY c.nombre"
);
$stmt->bind_param("i", $verId);
$stmt->execute();
$comoAlumno = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Cursos que imparte
$stmt = $conexion->prepare(
    "SELECT c.id, c.nombre, c.estado
     FROM curso_ponentes cp
     INNER JOIN cursos c ON cp.curso_id = c.id
     WHERE cp.usuario_id = ?
     ORDER BY c.nombre"
);
$stmt->bind_param("i", $verId);
$stmt->execute();
$comoPonente = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$esAdmin = strtolower($usuario["rol"]) === "admin";
$esYo    = (int) $usuario["id"] === (int) $_SESSION["usuario_id"];
$puedeEliminar = !$esAdmin && !$esYo && $usuario["aceptado"];
?>

<div class="barra-superior">
    <a href="admin.php?seccion=usuarios" class="btn-link">← Volver a usuarios</a>
</div>

<?php if (isset($mensajesDet[$msgDet])): ?>
    <div class="aviso-admin <?php echo $mensajesDet[$msgDet][0]; ?>">
        <?php echo htmlspecialchars($mensajesDet[$msgDet][1]); ?>
    </div>
<?php endif; ?>

<div class="tarjeta">
    <h2>
        <?php echo htmlspecialchars($usuario["nombre"]); ?>
        <span class="etiqueta rol-<?php echo htmlspecialchars(strtolower($usuario["rol"])); ?>">
            <?php echo htmlspecialchars($usuario["rol"]); ?>
        </span>
    </h2>

    <ul class="datos-usuario">
        <li><strong>ID</strong> <span><?php echo (int) $usuario["id"]; ?></span></li>
        <li><strong>Correo</strong> <span><?php echo htmlspecialchars($usuario["email"]); ?></span></li>
        <li><strong>Correo verificado</strong>
            <span><?php echo $usuario["email_verificado"] ? "Sí" : "No"; ?></span></li>
        <li><strong>Cuenta aprobada</strong>
            <span><?php echo $usuario["aceptado"] ? "Sí" : "Pendiente"; ?></span></li>
    </ul>
</div>

<?php if ($comoAlumno || strtolower($usuario["rol"]) === "alumno"): ?>
    <div class="tarjeta">
        <h2>Cursos como alumno <span class="contador"><?php echo count($comoAlumno); ?></span></h2>

        <?php if (count($comoAlumno) === 0): ?>
            <p>No está inscrito en ningún curso.</p>
        <?php else: ?>
            <div class="tabla-scroll">
            <table class="tabla">
                <thead>
                    <tr><th>Curso</th><th>Estado</th><th>Progreso</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($comoAlumno as $c):
                    $pct = $c["lecciones"] > 0 ? round($c["hechas"] / $c["lecciones"] * 100) : 0;
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c["nombre"]); ?></td>
                        <td><span class="etiqueta estado-<?php echo htmlspecialchars($c["estado"]); ?>">
                            <?php echo htmlspecialchars($c["estado"]); ?></span></td>
                        <td>
                            <div class="progreso">
                                <div class="progreso-barra" style="width: <?php echo $pct; ?>%"></div>
                            </div>
                            <small><?php echo $pct; ?>% (<?php echo (int) $c["hechas"]; ?> de <?php echo (int) $c["lecciones"]; ?>)</small>
                        </td>
                        <td>
                            <a class="btn-link"
                               href="admin.php?seccion=cursos&gestionar=<?php echo (int) $c["id"]; ?>">Ver curso</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($comoPonente || strtolower($usuario["rol"]) === "ponente"): ?>
    <div class="tarjeta">
        <h2>Cursos que imparte <span class="contador"><?php echo count($comoPonente); ?></span></h2>

        <?php if (count($comoPonente) === 0): ?>
            <p>No tiene cursos asignados.</p>
        <?php else: ?>
            <ul class="lista-personas">
                <?php foreach ($comoPonente as $c): ?>
                    <li>
                        <div>
                            <strong><?php echo htmlspecialchars($c["nombre"]); ?></strong>
                            <small><?php echo htmlspecialchars($c["estado"]); ?></small>
                        </div>
                        <a class="btn-link"
                           href="admin.php?seccion=cursos&gestionar=<?php echo (int) $c["id"]; ?>">Ver curso</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (!$esYo && $usuario["aceptado"]): ?>
<div class="tarjeta">
    <h2>Cambiar rol</h2>

    <?php if (count($avisosRol) > 0): ?>
        <p>Al cambiar su rol se quitará:</p>
        <ul class="lista-avisos">
            <?php foreach ($avisosRol as $a): ?>
                <li><?php echo htmlspecialchars($a); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST" action="../acciones_usuarios.php" class="form-linea"
          onsubmit="return confirm('¿Cambiar el rol de este usuario?<?php echo count($avisosRol) > 0 ? ' Se quitarán los datos indicados.' : ''; ?>');">
        <input type="hidden" name="accion" value="cambiar_rol">
        <input type="hidden" name="id" value="<?php echo (int) $usuario["id"]; ?>">

        <select name="rol_id" class="select-linea">
            <?php foreach ($rolesDisp as $r): ?>
                <option value="<?php echo (int) $r["id"]; ?>"
                    <?php echo strtolower($r["nombre"]) === $rolActualNombre ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($r["nombre"]); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-principal">Cambiar rol</button>
    </form>
</div>
<?php endif; ?>


<div class="tarjeta zona-peligro">
    <h2>Eliminar usuario</h2>

    <?php if ($puedeEliminar): ?>
        <p>
            Se borrará la cuenta junto con sus inscripciones, su progreso y sus
            asignaciones como ponente. Esta acción no se puede deshacer.
        </p>
        <form method="POST" action="../acciones_usuarios.php"
              onsubmit="return confirm('¿Eliminar a este usuario definitivamente?');">
            <input type="hidden" name="accion" value="eliminar">
            <input type="hidden" name="id" value="<?php echo (int) $usuario["id"]; ?>">
            <button type="submit" class="btn-peligro" style="border:none;border-radius:8px;padding:10px 16px;cursor:pointer;font-weight:600;color:#fff;">
                Eliminar usuario
            </button>
        </form>
    <?php elseif ($esYo): ?>
        <p>No puedes eliminar tu propia cuenta.</p>
    <?php elseif ($esAdmin): ?>
        <p>Las cuentas de administrador no se pueden eliminar desde aquí.</p>
    <?php else: ?>
        <p>Esta cuenta está pendiente de aprobación. Recházala desde la lista de solicitudes.</p>
    <?php endif; ?>
</div>