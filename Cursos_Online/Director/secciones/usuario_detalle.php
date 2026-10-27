<?php
$verId = (int) ($_GET["ver"] ?? 0);

// El usuario debe estar en alguno de mis cursos (como alumno o como ponente)
$stmt = $conexion->prepare(
    "SELECT u.id, u.nombre, u.email, r.nombre AS rol
     FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE u.id = ?
       AND (
            EXISTS (SELECT 1 FROM inscripciones i
                     INNER JOIN cursos c ON i.curso_id = c.id
                     WHERE i.usuario_id = u.id AND c.director_id = ?)
         OR EXISTS (SELECT 1 FROM curso_ponentes cp
                     INNER JOIN cursos c ON cp.curso_id = c.id
                     WHERE cp.usuario_id = u.id AND c.director_id = ?)
       )"
);
$stmt->bind_param("iii", $verId, $dirId, $dirId);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    echo '<div class="aviso-admin error">El usuario no existe o no está en tus cursos.</div>';
    echo '<a href="director.php?seccion=usuarios" class="btn-link">← Volver a usuarios</a>';
    return;
}

// Sus inscripciones en MIS cursos, con progreso
$stmt = $conexion->prepare(
    "SELECT i.id AS inscripcion_id, c.id, c.nombre, c.estado,
            (SELECT COUNT(*) FROM subbloques s
              INNER JOIN bloques b ON s.bloque_id = b.id
              WHERE b.curso_id = c.id) AS lecciones,
            (SELECT COUNT(*) FROM progreso_subbloques p
              WHERE p.inscripcion_id = i.id) AS hechas
     FROM inscripciones i
     INNER JOIN cursos c ON i.curso_id = c.id
     WHERE i.usuario_id = ? AND c.director_id = ?
     ORDER BY c.nombre"
);
$stmt->bind_param("ii", $verId, $dirId);
$stmt->execute();
$comoAlumno = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Mis cursos que imparte
$stmt = $conexion->prepare(
    "SELECT c.id, c.nombre, c.estado
     FROM curso_ponentes cp
     INNER JOIN cursos c ON cp.curso_id = c.id
     WHERE cp.usuario_id = ? AND c.director_id = ?
     ORDER BY c.nombre"
);
$stmt->bind_param("ii", $verId, $dirId);
$stmt->execute();
$comoPonente = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="barra-superior">
    <a href="director.php?seccion=usuarios" class="btn-link">← Volver a usuarios</a>
</div>

<div class="tarjeta">
    <h2>
        <?php echo htmlspecialchars($usuario["nombre"]); ?>
        <span class="etiqueta rol-<?php echo htmlspecialchars(strtolower($usuario["rol"])); ?>">
            <?php echo htmlspecialchars($usuario["rol"]); ?>
        </span>
    </h2>

    <ul class="datos-usuario">
        <li><strong>Correo</strong> <span><?php echo htmlspecialchars($usuario["email"]); ?></span></li>
    </ul>
</div>

<?php if (count($comoAlumno) > 0): ?>
    <div class="tarjeta">
        <h2>En mis cursos como alumno <span class="contador"><?php echo count($comoAlumno); ?></span></h2>

        <div class="tabla-scroll">
        <table class="tabla">
            <thead>
                <tr><th>Curso</th><th>Estado</th><th>Progreso</th><th>Acciones</th></tr>
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
                        <div class="acciones">
                            <a class="btn-link"
                               href="director.php?seccion=cursos&gestionar=<?php echo (int) $c["id"]; ?>">Ver curso</a>
                            <form method="POST" action="../acciones_usuarios_director.php"
                                  onsubmit="return confirm('¿Quitar a este alumno del curso? Se borrará también su progreso.');">
                                <input type="hidden" name="accion" value="quitar_inscripcion">
                                <input type="hidden" name="inscripcion_id" value="<?php echo (int) $c["inscripcion_id"]; ?>">
                                <input type="hidden" name="usuario_id" value="<?php echo $verId; ?>">
                                <button type="submit" class="btn-peligro mini">Quitar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
<?php endif; ?>

<?php if (count($comoPonente) > 0): ?>
    <div class="tarjeta">
        <h2>Cursos míos que imparte <span class="contador"><?php echo count($comoPonente); ?></span></h2>

        <ul class="lista-personas">
            <?php foreach ($comoPonente as $c): ?>
                <li>
                    <div>
                        <strong><?php echo htmlspecialchars($c["nombre"]); ?></strong>
                        <small><?php echo htmlspecialchars($c["estado"]); ?></small>
                    </div>
                    <div class="acciones">
                        <a class="btn-link"
                           href="director.php?seccion=cursos&gestionar=<?php echo (int) $c["id"]; ?>">Ver curso</a>
                        <form method="POST" action="../acciones_usuarios_director.php"
                              onsubmit="return confirm('¿Quitar a este ponente del curso?');">
                            <input type="hidden" name="accion" value="quitar_ponente">
                            <input type="hidden" name="curso_id" value="<?php echo (int) $c["id"]; ?>">
                            <input type="hidden" name="usuario_id" value="<?php echo $verId; ?>">
                            <button type="submit" class="btn-peligro mini">Quitar</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="tarjeta zona-peligro">
    <h2>Quitar de todos mis cursos</h2>
    <p>
        Se sacará a este usuario de todos tus cursos y se borrará su progreso en ellos.
        Su cuenta y los cursos de otros directores no se tocan.
    </p>
    <form method="POST" action="../acciones_usuarios_director.php"
          onsubmit="return confirm('¿Quitar a este usuario de todos tus cursos?');">
        <input type="hidden" name="accion" value="quitar_todo">
        <input type="hidden" name="usuario_id" value="<?php echo $verId; ?>">
        <button type="submit" class="btn-peligro btn-grande">Quitar de todos mis cursos</button>
    </form>
</div>