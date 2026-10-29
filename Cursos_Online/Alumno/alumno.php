<?php
session_start();
require_once __DIR__ . "/../conexion.php";
require_once __DIR__ . "/../permisos.php";
require_once __DIR__ . "/../ui_icons.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../login.php");
    exit;
}

$conexion = conectar();
sincronizarRol($conexion);

if (strtolower($_SESSION["rol"] ?? "") !== "alumno") {
    header("Location: ../mainWindow.php");
    exit;
}

$id = (int) $_SESSION["usuario_id"];

// Secciones permitidas (lista blanca) con su texto e ícono
$secciones = [
    "explorar"   => ["Explorar cursos", "buscar"],
    "mis_cursos" => ["Mis cursos",      "cursos"],
    "perfil"     => ["Perfil",          "perfil"],
];

$seccion = $_GET["seccion"] ?? "mis_cursos";
if (!isset($secciones[$seccion])) {
    $seccion = "mis_cursos";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel del alumno</title>
    <link rel="stylesheet" href="../Admin/admin.css">
    <link rel="stylesheet" href="alumno.css">
</head>
<body>
<div class="layout">

    <aside class="sidebar">
        <div class="logo">Cursos Online</div>

        <nav class="menu-lateral">
            <?php foreach ($secciones as $clave => [$texto, $icono]): ?>
                <a href="alumno.php?seccion=<?php echo $clave; ?>"
                   class="<?php echo $clave === $seccion ? 'activo' : ''; ?>">
                    <span class="icono"><?php echo iconoInterfaz($icono); ?></span>
                    <?php echo $texto; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-pie">
            <span class="admin-nombre"><?php echo htmlspecialchars($_SESSION["nombre"] ?? "Alumno"); ?></span>
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