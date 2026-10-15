<?php
session_start();

// Si no hay sesión iniciada, mandar al login
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$nombre = $_SESSION["nombre"] ?? "";
$rol = strtolower($_SESSION["rol"] ?? "");

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
</head>
<body>

    <h1><?php echo htmlspecialchars($saludo); ?></h1>
    <p>Sesión iniciada como: <?php echo htmlspecialchars($nombre); ?></p>

    <?php if ($rol === "admin"): ?>
        <!-- Contenido solo para admin -->
    <?php elseif ($rol === "director"): ?>
        <!-- Contenido solo para director -->
    <?php elseif ($rol === "alumno"): ?>
        <!-- Contenido solo para alumno -->
    <?php elseif ($rol === "ponente"): ?>
        <!-- Contenido solo para ponente -->
    <?php endif; ?>

    <?php if ($rol === "admin"): ?>
        <p><a href="aprobar_usuarios.php">Aprobar usuarios pendientes</a></p>
    <?php endif; ?>

    <a href="logout.php">Cerrar sesión</a>

</body>
</html>