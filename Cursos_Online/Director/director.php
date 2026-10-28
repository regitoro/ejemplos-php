<?php
session_start();
require_once __DIR__ . "/../conexion.php";
require_once __DIR__ . "/../permisos.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../login.php");
    exit;
}

$conexion = conectar();
sincronizarRol($conexion);
$stmt = $conexion->prepare(
    "SELECT COUNT(*) AS n FROM entregas_ejercicios ee
     INNER JOIN ejercicios e ON e.id = ee.ejercicio_id
     INNER JOIN cursos c ON c.id = e.curso_id
     WHERE c.director_id = ? AND ee.puntuacion IS NULL"
);
$stmt->bind_param("i", $_SESSION["usuario_id"]);
$stmt->execute();
$ejerciciosPendientes = (int) $stmt->get_result()->fetch_assoc()["n"];
$stmt->close();

if (strtolower($_SESSION["rol"] ?? "") !== "director") {
    header("Location: ../mainWindow.php");
    exit;
}

// Secciones permitidas (lista blanca) con su texto e ícono
$secciones = [
    "inicio"        => ["Inicio",        "🏠"],
    "cursos"        => ["Cursos",        "📚"],
    "calificar_ejercicios" => ["Calificar ejercicios", "📝"],
    "inscripciones" => ["Inscripciones", "📝"],
    "usuarios"      => ["Usuarios",      "👥"],
    "perfil"        => ["Perfil",        "👤"],
];

$seccion = $_GET["seccion"] ?? "inicio";
if (!isset($secciones[$seccion])) {
    $seccion = "inicio";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel del director</title>
    <link rel="stylesheet" href="../Admin/admin.css">
</head>
<body>
<div class="layout">

    <aside class="sidebar">
        <div class="logo">Cursos Online</div>

        <nav class="menu-lateral">
            <?php foreach ($secciones as $clave => [$texto, $icono]): ?>
                <a href="director.php?seccion=<?php echo $clave; ?>"
                   class="<?php echo $clave === $seccion ? 'activo' : ''; ?>">
                    <span class="icono"><?php echo $icono; ?></span>
                    <?php echo $texto; ?>
                    <?php if ($clave === "calificar_ejercicios" && $ejerciciosPendientes > 0): ?>
                        <span class="insignia"><?php echo $ejerciciosPendientes; ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-pie">
            <span class="admin-nombre"><?php echo htmlspecialchars($_SESSION["nombre"] ?? "Director"); ?></span>
            <a href="../logout.php" class="salir">Cerrar sesión</a>
        </div>
    </aside>

    <main class="contenido">
        <h1><?php echo $secciones[$seccion][0]; ?></h1>
        <?php include __DIR__ . "/secciones/" . $seccion . ".php"; ?>
    </main>

</div>
</body>
</html>