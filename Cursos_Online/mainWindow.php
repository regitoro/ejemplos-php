<<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$conexion = conectar();
$id = (int) $_SESSION["usuario_id"];

$sql = "SELECT usuarios.nombre AS nombre_usuario, usuarios.email,
               roles.nombre AS nombre_rol
        FROM usuarios
        INNER JOIN roles ON usuarios.rol_id = roles.id
        WHERE usuarios.id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$nombre = $fila["nombre_usuario"];
$email  = $fila["email"];
$rol    = strtolower($fila["nombre_rol"]);

// Cada rol con panel propio va a su carpeta
if ($rol === "admin") {
    header("Location: Admin/admin.php");
    exit;
}
if ($rol === "alumno") {
    header("Location: Alumno/alumno.php");
    exit;
}

if ($rol === "director") {
    header("Location: Director/director.php");
    exit;
}

if ($rol === "ponente") {
    header("Location: Ponente/ponente.php");
    exit;
}

$saludos = [
    
    "ponente"  => "Hola ponente",
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
        <h1><?php echo htmlspecialchars($saludo . ", " . $nombre); ?></h1>
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
        <a href="logout.php" class="btn secundario">Cerrar sesión</a>
    </div>

</main>
</body>
</html>