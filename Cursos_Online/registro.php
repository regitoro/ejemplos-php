<?php
include_once "conexion.php";
   $conexion = conectar();

$mensaje = "";

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

            $mensaje = "Usuario registrado correctamente. Tu cuenta está pendiente de aprobación.";

            echo "<p>
                    Para confirmar tu correo:
                    <a href='verificar.php?token=" . urlencode($token) . "'>
                        Confirmar email
                    </a>
                  </p>";

        } else {

            $mensaje = "Error al registrar el usuario. Comprueba que el correo no esté registrado.";

        }

        $stmt->close();

    } else {

        $mensaje = "Error: no se encontró el rol de alumno en la base de datos.";

    }
}

?>
<h1>Registro de alumnos</h1>

<form method="POST">

    <label>Nombre:</label>
    <input type="text" name="nombre" required>

    <label>Email:</label>
    <input type="email" name="email" required>

    <label>Contraseña:</label>
    <input type="password" name="password" required>

    <button type="submit">Registrarse</button>

</form>

<p><?php echo htmlspecialchars($mensaje); ?></p>

<a href="login.php">Iniciar sesión</a>