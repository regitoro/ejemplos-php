<?php
session_start();
require_once __DIR__ . "/../conexion.php"; 

// Solo el admin puede entrar aquí
if (!isset($_SESSION["usuario_id"]) || strtolower($_SESSION["rol"] ?? "") !== "admin") {
    header("Location: ../login.php"); 
    exit;
}

$conexion = conectar();

// Secciones permitidas (lista blanca) con su texto e ícono
$secciones = [
    "inicio"     => ["Inicio",     "🏠"],
    "cursos"     => ["Cursos",     "📚"],
    "calendario" => ["Calendario", "📅"],
    "usuarios"   => ["Usuarios",   "👥"],
    "perfil"     => ["Perfil",     "👤"],
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
    <title>Panel de administración</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="layout">

    <aside class="sidebar">
        <div class="logo">Cursos Online</div>

        <nav class="menu-lateral">
            <?php foreach ($secciones as $clave => [$texto, $icono]): ?>
                <a href="admin.php?seccion=<?php echo $clave; ?>"
                   class="<?php echo $clave === $seccion ? 'activo' : ''; ?>">
                    <span class="icono"><?php echo $icono; ?></span>
                    <?php echo $texto; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-pie">
            <span class="admin-nombre"><?php echo htmlspecialchars($_SESSION["nombre"] ?? "Admin"); ?></span>
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