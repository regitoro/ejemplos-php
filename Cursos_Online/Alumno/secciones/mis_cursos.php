<?php
// Cursos del alumno con su progreso (los borradores no se muestran)
$sql = "SELECT c.id, c.nombre, c.estado, c.fecha_inicio,
               (SELECT COUNT(*) FROM subbloques s
                 INNER JOIN bloques b ON s.bloque_id = b.id
                 WHERE b.curso_id = c.id) AS lecciones,
               (SELECT COUNT(*) FROM progreso_subbloques p
                 WHERE p.inscripcion_id = i.id) AS hechas
        FROM inscripciones i
        INNER JOIN cursos c ON i.curso_id = c.id
        WHERE i.usuario_id = ? AND c.estado <> 'borrador'
        ORDER BY c.id DESC";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$misCursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="tarjeta">
    <?php if (count($misCursos) === 0): ?>
        <p>
            Todavía no estás inscrito en ningún curso. Explora los cursos disponibles
            en <a href="alumno.php?seccion=explorar">Explorar cursos</a>; el administrador
            te inscribirá y aparecerá aquí.
        </p>
    <?php else: ?>
        <div class="al-cursos">
            <?php foreach ($misCursos as $c):
                $pct = $c["lecciones"] > 0 ? round($c["hechas"] / $c["lecciones"] * 100) : 0;
            ?>
                <a class="al-curso" href="curso.php?id=<?php echo (int) $c["id"]; ?>">
                    <strong><?php echo htmlspecialchars($c["nombre"]); ?></strong>
                    <small>
                        <?php echo $c["fecha_inicio"]
                            ? "Inicio: " . date("d/m/Y", strtotime($c["fecha_inicio"]))
                            : "Sin fecha de inicio"; ?>
                    </small>
                    <div class="al-progreso"><div style="width: <?php echo $pct; ?>%"></div></div>
                    <small><?php echo $pct; ?>% (<?php echo (int) $c["hechas"]; ?> de <?php echo (int) $c["lecciones"]; ?> lecciones)</small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>