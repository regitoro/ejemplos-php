<!-- Comprueba que el alumno esté inscrito y que el curso no sea borrador. 
Si entra un admin, director o ponente con acceso al curso, ve la vista previa sin poder marcar progreso. -->
<?php
session_start();
require_once __DIR__ . "/../conexion.php";
require_once __DIR__ . "/../permisos.php";
require_once __DIR__ . "/../contenido.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../login.php");
    exit;
}

$conexion = conectar();
sincronizarRol($conexion);

$rol   = strtolower($_SESSION["rol"] ?? "");
$uid   = (int) $_SESSION["usuario_id"];
$subId = (int) ($_GET["id"] ?? 0);

// La lección con su bloque y su curso
$stmt = $conexion->prepare(
    "SELECT s.id, s.titulo, s.contenido, b.titulo AS bloque,
            c.id AS curso_id, c.nombre AS curso
     FROM subbloques s
     INNER JOIN bloques b ON s.bloque_id = b.id
     INNER JOIN cursos c ON b.curso_id = c.id
     WHERE s.id = ?"
);
$stmt->bind_param("i", $subId);
$stmt->execute();
$leccion = $stmt->get_result()->fetch_assoc();
$stmt->close();

$inscripcionId = 0;
$vistaPrevia   = false;

if ($leccion && $rol === "alumno") {

    // Debe estar inscrito y el curso no puede ser borrador
    $stmt = $conexion->prepare(
        "SELECT i.id FROM inscripciones i
         INNER JOIN cursos c ON c.id = i.curso_id
         WHERE i.curso_id = ? AND i.usuario_id = ? AND c.estado <> 'borrador'"
    );
    $cursoTmp = (int) $leccion["curso_id"];
    $stmt->bind_param("ii", $cursoTmp, $uid);
    $stmt->execute();
    $insc = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($insc) {
        $inscripcionId = (int) $insc["id"];
    } else {
        $leccion = null;
    }

} elseif ($leccion && puedeVerCurso($conexion, (int) $leccion["curso_id"], $rol, $uid)) {

    $vistaPrevia = true;      // admin, director o ponente mirando cómo se ve

} else {
    $leccion = null;
}

if (!$leccion) {
    header("Location: ../mainWindow.php");
    exit;
}

$cursoId = (int) $leccion["curso_id"];

// Todas las lecciones del curso, para Anterior / Siguiente y el progreso
$stmt = $conexion->prepare(
    "SELECT s.id, s.titulo, (p.subbloque_id IS NOT NULL) AS completada
     FROM bloques b
     INNER JOIN subbloques s ON s.bloque_id = b.id
     LEFT JOIN progreso_subbloques p
            ON p.subbloque_id = s.id AND p.inscripcion_id = ?
     WHERE b.curso_id = ?
     ORDER BY b.orden, b.id, s.orden, s.id"
);
$stmt->bind_param("ii", $inscripcionId, $cursoId);
$stmt->execute();
$todas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pos = null;
foreach ($todas as $i => $l) {
    if ((int) $l["id"] === $subId) {
        $pos = $i;
    }
}
$anterior   = ($pos !== null && $pos > 0) ? $todas[$pos - 1] : null;
$siguiente  = ($pos !== null && $pos < count($todas) - 1) ? $todas[$pos + 1] : null;
$completada = $pos !== null && $todas[$pos]["completada"];
$total      = count($todas);
$hechas     = count(array_filter($todas, fn($l) => $l["completada"]));

$volver = $vistaPrevia
    ? "../editor_leccion.php?id=" . $subId
    : "curso.php?id=" . $cursoId . "#l" . $subId;

$html = htmlParaMostrar($leccion["contenido"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($leccion["titulo"]); ?></title>
    <link rel="stylesheet" href="../contenido.css">
    <link rel="stylesheet" href="lector.css">
</head>
<body class="lector">

<header class="lector-barra">
    <a href="<?php echo htmlspecialchars($volver); ?>" class="lector-volver">
        ← <?php echo $vistaPrevia ? "Volver al editor" : "Volver al curso"; ?>
    </a>
    <span class="lector-ruta">
        <?php echo htmlspecialchars($leccion["curso"]); ?> ›
        <?php echo htmlspecialchars($leccion["bloque"]); ?>
        <?php if (!$vistaPrevia): ?>
            · <?php echo $hechas; ?> de <?php echo $total; ?> completadas
        <?php endif; ?>
    </span>
    <button type="button" id="btnPantalla" class="lector-btn suave">⛶ Pantalla completa</button>
</header>

<main class="lector-contenido">

    <?php if ($vistaPrevia): ?>
        <span class="lector-previa">Vista previa: así la ve el alumno</span>
    <?php endif; ?>

    <h1 class="lector-titulo"><?php echo htmlspecialchars($leccion["titulo"]); ?></h1>

    <?php if ($html === ""): ?>
        <p class="lector-vacio">Esta lección todavía no tiene contenido.</p>
    <?php else: ?>
        <article class="contenido-leccion"><?php echo $html; ?></article>
    <?php endif; ?>
</main>

<footer class="lector-pie">

    <?php if ($anterior): ?>
        <a href="leccion.php?id=<?php echo (int) $anterior["id"]; ?>" class="lector-btn suave">← Anterior</a>
    <?php else: ?>
        <span class="lector-btn suave apagado">← Anterior</span>
    <?php endif; ?>

    <?php if (!$vistaPrevia): ?>
        <form method="POST" action="completar_leccion.php">
            <input type="hidden" name="subbloque_id" value="<?php echo $subId; ?>">
            <input type="hidden" name="volver" value="leccion">
            <button type="submit" class="lector-btn <?php echo $completada ? 'hecha' : 'completar'; ?>">
                <?php echo $completada ? "✔ Completada (clic para desmarcar)" : "Marcar como completada"; ?>
            </button>
        </form>
    <?php endif; ?>

    <?php if ($siguiente): ?>
        <a href="leccion.php?id=<?php echo (int) $siguiente["id"]; ?>" class="lector-btn primario">Siguiente →</a>
    <?php else: ?>
        <span class="lector-btn primario apagado">Siguiente →</span>
    <?php endif; ?>
</footer>

<script>
// Pantalla completa real del navegador
const boton = document.getElementById('btnPantalla');
boton.addEventListener('click', () => {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen && document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen && document.exitFullscreen();
    }
});
document.addEventListener('fullscreenchange', () => {
    boton.textContent = document.fullscreenElement ? '✕ Salir de pantalla completa' : '⛶ Pantalla completa';
});
</script>

</body>
</html>