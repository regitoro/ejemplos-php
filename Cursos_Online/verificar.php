<?php
include_once "conexion.php";

$conexion = conectar();

$mensaje = "";
$exito = false;

$token = $_GET["token"] ?? "";

// El token generado en registro.php son 64 caracteres hexadecimales
if (!preg_match('/^[0-9a-f]{64}$/', $token)) {

    $mensaje = "El enlace de verificación no es válido.";

} else {

    // Marcar el correo como verificado e invalidar el token
    $sql = "UPDATE usuarios
            SET email_verificado = 1, token_verificacion = NULL
            WHERE token_verificacion = ? AND email_verificado = 0";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $exito = true;
        $mensaje = "Tu correo fue verificado correctamente.";
    } else {
        $mensaje = "El enlace no es válido o ya fue utilizado.";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificar correo</title>
</head>
<body>

    <h1>Verificación de correo</h1>

    <p><?php echo htmlspecialchars($mensaje); ?></p>

    <?php if ($exito): ?>
        <p>
            Ahora debes esperar a que un administrador autorice tu cuenta
            para poder entrar.
        </p>
    <?php endif; ?>

    <a href="login.php">Ir a iniciar sesión</a>

</body>
</html>