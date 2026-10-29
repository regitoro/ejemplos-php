<?php
session_start();
require_once __DIR__ . "/../conexion.php";
require_once __DIR__ . "/../permisos.php";
require_once __DIR__ . "/certificado_datos.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../login.php");
    exit;
}

$conexion = conectar();
sincronizarRol($conexion, "../login.php");
if (strtolower($_SESSION["rol"] ?? "") !== "alumno") {
    header("Location: ../mainWindow.php");
    exit;
}

$cursoId = (int) ($_GET["id"] ?? 0);
$resultado = obtenerResultadoCertificado($conexion, (int) $_SESSION["usuario_id"], $cursoId);
if (!$resultado || !$resultado["aprobado"]) {
    http_response_code(403);
    $disponible = false;
} else {
    $disponible = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $disponible ? "Certificado · " . htmlspecialchars($resultado["curso"]) : "Certificado no disponible"; ?></title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="alumno.css">
</head>
<body class="certificado-app">
<?php if (!$disponible): ?>
    <main class="certificado-no-disponible">
        <span class="curso-kicker">CERTIFICACIÓN DEL CURSO</span>
        <h1>Certificado no disponible</h1>
        <p>Para obtenerlo, completa todas las lecciones, entrega todos los ejercicios y espera a que estén calificados. El promedio mínimo de aprobación es 80 / 100.</p>
        <a class="btn-principal" href="curso.php?id=<?php echo $cursoId; ?>">Volver al curso</a>
    </main>
<?php else: ?>
    <nav class="certificado-acciones" aria-label="Acciones del certificado">
        <a href="curso.php?id=<?php echo $cursoId; ?>" class="btn-link">← Volver al curso</a>
        <button type="button" class="btn-principal" onclick="window.print()">Imprimir o guardar como PDF</button>
    </nav>
    <main class="certificado-hoja">
        <div class="certificado-marco">
            <div class="certificado-detalle-superior" aria-hidden="true"></div>
            <span class="certificado-marca">CURSOS ONLINE</span>
            <span class="certificado-kicker">CERTIFICADO DE APROBACIÓN</span>
            <h1>Reconocimiento académico</h1>
            <p class="certificado-intro">Se otorga el presente certificado a</p>
            <p class="certificado-alumno"><?php echo htmlspecialchars($resultado["alumno"]); ?></p>
            <p class="certificado-cuerpo">
                Por haber completado satisfactoriamente el curso
                <strong><?php echo htmlspecialchars($resultado["curso"]); ?></strong>,
                incluyendo todas sus lecciones y ejercicios evaluables.
            </p>
            <div class="certificado-calificacion">
                <span>Calificación final</span>
                <strong><?php echo number_format((float) $resultado["promedio"], 2); ?><small> / 100</small></strong>
                <em>Aprobado</em>
            </div>
            <div class="certificado-pie">
                <div>
                    <span class="certificado-linea"></span>
                    <strong>Cursos Online</strong>
                    <small>Plataforma de aprendizaje</small>
                </div>
                <div class="certificado-fecha">
                    <span>Fecha de emisión</span>
                    <strong><?php echo date("d/m/Y"); ?></strong>
                </div>
            </div>
            <p class="certificado-folio">Folio: <?php echo htmlspecialchars($resultado["folio"]); ?></p>
            <div class="certificado-detalle-inferior" aria-hidden="true"></div>
        </div>
    </main>
<?php endif; ?>
</body>
</html>
