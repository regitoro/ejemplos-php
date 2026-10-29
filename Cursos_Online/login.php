<?php
session_start();
require_once "conexion.php";

$conexion = conectar();

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    // Buscar al usuario por su correo, junto con el nombre de su rol
    $sql = "SELECT usuarios.*, roles.nombre AS nombre_rol
            FROM usuarios
            INNER JOIN roles ON usuarios.rol_id = roles.id
            WHERE usuarios.email = ?";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {

        $usuario = $resultado->fetch_assoc();

        if (!password_verify($password, $usuario["password"])) {

            $mensaje = "Correo o contraseña incorrectos.";

        } elseif (!$usuario["email_verificado"]) {

            $mensaje = "Por favor, verifica tu correo electrónico antes de iniciar sesión.";

        } elseif (!$usuario["aceptado"]) {

            // Cuenta aún no autorizada: mandar a la ventana de espera
            header("Location: pendiente.php");
            exit;

        } else {

            // Iniciar sesión
            session_regenerate_id(true);

            $_SESSION["usuario_id"] = $usuario["id"];
            $_SESSION["nombre"]     = $usuario["nombre"];
            $_SESSION["email"]      = $usuario["email"];
            $_SESSION["rol_id"]     = $usuario["rol_id"];
            $_SESSION["rol"]        = $usuario["nombre_rol"];

            header("Location: mainWindow.php");
            exit;
        }

    } else {

        $mensaje = "Correo o contraseña incorrectos.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="card">
 
    <h1>Iniciar sesión</h1>
    <p class="subtitulo">Bienvenido de nuevo a Cursos Online</p>
 
    <form method="POST">
 
        <div class="campo">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" autocomplete="email" required>
        </div>
 
        <div class="campo">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>

        <p class="enlace-recuperacion"><a href="recuperar_contrasena.php">¿Olvidaste tu contraseña?</a></p>
 
        <button type="submit">Entrar</button>
 
    </form>
 
    <p class="mensaje"><?php echo htmlspecialchars($mensaje); ?></p>
 
    <p class="enlace-pie">¿No tienes cuenta? <a href="registro.php">Crear una cuenta</a></p>
 
</main>
</body>
</html>
 
