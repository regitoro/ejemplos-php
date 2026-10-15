<?php
session_start();

// Si no hay sesión iniciada, mandar al login
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$id     = $_SESSION["usuario_id"];
$nombre = $_SESSION["nombre"] ?? "";
$correo = $_SESSION["correo"] ?? "";
$rol    = strtolower($_SESSION["rol"] ?? "");

switch ($rol) {
    case "admin":
        $saludo = "Hola admin";
        break;
    case "director":
        $saludo = "Hola director";
        break;
    case "alumno":
        $saludo = "Hola alumno";
        break;
    case "ponente":
        $saludo = "Hola ponente";
        break;
    default:
        $saludo = "Hola";
        break;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel principal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="app">
<main class="card ancha">
 
    <div class="encabezado">
        <h1><?php echo htmlspecialchars($saludo); ?></h1>
        <span class="badge rol"><?php echo htmlspecialchars($rol); ?></span>
    </div>
 
    <h2>Datos del usuario</h2>
    <ul class="datos">
        <li><strong>ID</strong> <span><?php echo htmlspecialchars($id); ?></span></li>
        <li><strong>Nombre</strong> <span><?php echo htmlspecialchars($nombre); ?></span></li>
        <li><strong>Correo</strong> <span><?php echo htmlspecialchars($correo); ?></span></li>
        <li><strong>Rol</strong> <span><?php echo htmlspecialchars($rol); ?></span></li>
    </ul>
 
    <div class="menu">
        <?php if ($rol === "admin"): ?>
            <a href="aprobar_usuarios.php" class="btn">Aprobar usuarios pendientes</a>
        <?php endif; ?>
 
        <a href="logout.php" class="btn secundario">Cerrar sesión</a>
    </div>
 
</main>
</body>
</html>
 
