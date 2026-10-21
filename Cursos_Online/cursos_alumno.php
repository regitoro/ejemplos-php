<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$conexion   = conectar();
$usuario_id = (int) $_SESSION["usuario_id"];
$curso_id   = (int) ($_GET["id"] ?? 0);

// 1. Inscripción del alumno en este curso (si no está inscrito, fuera)
$stmt = $conexion->prepare(
    "SELECT id FROM inscripciones WHERE curso_id = ? AND usuario_id = ?"
);
$stmt->bind_param("ii", $curso_id, $usuario_id);
$stmt->execute();
$insc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$insc) {
    header("Location: mainWindow.php");
    exit;
}
$inscripcion_id = (int) $insc["id"];

// 2. Lecciones con su estado (la consulta de la captura)
$sql = "SELECT s.id, s.titulo, b.titulo AS bloque,
               (p.subbloque_id IS NOT NULL) AS completada
        FROM bloques b
        INNER JOIN subbloques s ON s.bloque_id = b.id
        LEFT JOIN progreso_subbloques p
               ON p.subbloque_id = s.id AND p.inscripcion_id = ?
        WHERE b.curso_id = ?
        ORDER BY b.orden, s.orden";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $inscripcion_id, $curso_id);
$stmt->execute();
$lecciones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3. Progreso: se calcula con las mismas lecciones, sin otra consulta
$total  = count($lecciones);
$hechas = count(array_filter($lecciones, fn($l) => $l["completada"]));
$porcentaje = $total > 0 ? round($hechas / $total * 100) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi curso</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="card ancha">
    <p>Progreso: <?php echo $porcentaje; ?>% (<?php echo $hechas; ?> de <?php echo $total; ?>)</p>

    <?php foreach ($lecciones as $l): ?>
        <form method="POST" action="completar_leccion.php"
              class="leccion <?php echo $l["completada"] ? 'hecha' : ''; ?>">
            <input type="hidden" name="subbloque_id" value="<?php echo (int) $l["id"]; ?>">
            <button type="submit" class="check" title="Marcar como completada">
                <?php echo $l["completada"] ? "✔" : ""; ?>
            </button>
            <span><?php echo htmlspecialchars($l["titulo"]); ?></span>
        </form>
    <?php endforeach; ?>
</main>
</body>
</html>