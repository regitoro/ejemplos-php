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

// 1. El alumno debe estar inscrito y el curso no puede ser borrador
$stmt = $conexion->prepare(
    "SELECT i.id AS inscripcion_id, c.nombre, c.descripcion, c.fecha_inicio, c.fecha_fin
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

            <details class="al-detalle">
                <summary><?php echo htmlspecialchars($l["titulo"]); ?></summary>
                <div class="al-contenido">
                    <?php echo $l["contenido"] !== null && trim($l["contenido"]) !== ""
                        ? nl2br(htmlspecialchars($l["contenido"]))
                        : "<em>Esta lección todavía no tiene contenido.</em>"; ?>
                </div>
            </details>
        </div>

    <?php endforeach; ?>

</main>
</body>
</html>