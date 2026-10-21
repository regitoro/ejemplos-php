<?php
session_start();
require_once "conexion.php";
$conexion = conectar();

// Si no hay sesión iniciada, mandar al login
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$id     = $_SESSION["usuario_id"];
//Obtener solo los datos del usuario logueado
$sql = "SELECT
            usuarios.id,
            usuarios.nombre AS nombre_usuario,
            usuarios.email,
            roles.nombre AS nombre_rol
        FROM usuarios
        INNER JOIN roles ON usuarios.rol_id = roles.id
        WHERE usuarios.id = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Si el usuario ya no existe, cerrar la sesión
if (!$fila) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$nombre = $_SESSION["nombre"] ?? "";
$email = $_SESSION["email"] ?? "";
$rol    = strtolower($_SESSION["rol"] ?? "");

if ($rol === "admin") {
    header("Location: Admin/admin.php");
    exit;
}

// saludo según el rol
$saludos = [
    "admin"   => "Hola admin",
    "director" => "Hola director",
    "alumno"  => "Hola alumno",
    "ponente" => "Hola ponente",
];
$saludo = $saludos[$rol] ?? "Hola";
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
        <li><strong>Correo</strong> <span><?php echo htmlspecialchars($email); ?></span></li>
        <li><strong>Rol</strong> <span><?php echo htmlspecialchars($rol); ?></span></li>
    </ul>
 
    <div class="menu">
        <!-- <?php 
        //if ($rol === "admin") ?>
            <a href="aprobar_usuarios.php" class="btn">Aprobar usuarios pendientes</a>
        <?php 
    //endif; ?> -->
 
        <a href="logout.php" class="btn secundario">Cerrar sesión</a>
    </div>
 
</main>
</body>
</html>
 
