<?php
include_once "conexion.php";
   $conexion = conectar();

$mensaje = "";
$registrado = false;
$enlaceVerificacion = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST["nombre"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    // Convertir la contraseña a hash
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Generar token de verificación
    $token = bin2hex(random_bytes(32));

    // Buscar automáticamente el rol de alumno
    $sqlRol = "SELECT id FROM roles WHERE nombre = 'alumno'";
    $resultado = $conexion->query($sqlRol);

    if ($resultado && $fila = $resultado->fetch_assoc()) {

        $rol_id = (int) $fila["id"];

        // Registrar al usuario como alumno y dejarlo pendiente de aprobación
        $sql = "INSERT INTO usuarios
                (nombre, email, password, rol_id, aceptado,
                 email_verificado, token_verificacion)
                VALUES (?, ?, ?, ?, 0, 0, ?)";

        $stmt = $conexion->prepare($sql);

        $stmt->bind_param(
            "sssis",
            $nombre,
            $email,
            $passwordHash,
            $rol_id,
            $token
        );

        if ($stmt->execute()) {
            $registrado = true;
            $mensaje = "Usuario registrado correctamente. Tu cuenta está pendiente de aprobación.";
            $enlaceVerificacion = "verificar.php?token=" . urlencode($token);

        } else {

            $mensaje = "Error al registrar el usuario. Comprueba que el correo no esté registrado.";

        }

        $stmt->close();

    } else {

        $mensaje = "Error: no se encontró el rol de alumno en la base de datos.";

    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro de alumnos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="card">
    <h1>Registro de alumnos</h1>

    <?php if (!$registrado): ?>

        <p class="subtitulo">Crea tu cuenta para entrar a Cursos Online</p>
        <form method="POST">
            <div class="campo">
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" name="nombre" autocomplete="name" required>
            </div>
            <div class="campo">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" autocomplete="email" required>
            </div>
            <div class="campo">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required>
            </div>
            <button type="submit">Registrarse</button>
        </form>
        <p class="mensaje"><?php echo htmlspecialchars($mensaje); ?></p>
        <p class="enlace-pie">¿Ya tienes cuenta? <a href="login.php">Iniciar sesión</a></p>

    <?php else: ?>

        <p class="mensaje"><?php echo htmlspecialchars($mensaje); ?></p>
        <div class="aviso">
            Para confirmar tu correo:
            <a href="<?php echo htmlspecialchars($enlaceVerificacion); ?>">Confirmar email</a>
        </div>

    <?php endif; ?>
</main>
</body>
</html>