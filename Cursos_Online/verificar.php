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
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="card estado-verificacion">
    <a class="verificacion-volver" href="login.php">← Volver a iniciar sesión</a>
    <div class="verificacion-icono <?php echo $exito ? 'correcto' : 'error'; ?>" aria-hidden="true">
        <?php echo $exito ? '✓' : '!'; ?>
    </div>
    <span class="verificacion-kicker">ESTADO DE TU CUENTA</span>
    <h1><?php echo $exito ? 'Correo verificado' : 'No se pudo verificar'; ?></h1>
    <p class="verificacion-mensaje" role="<?php echo $exito ? 'status' : 'alert'; ?>">
        <?php echo htmlspecialchars($mensaje); ?>
    </p>

    <?php if ($exito): ?>
        <div class="verificacion-siguiente-paso">
            <span class="verificacion-paso-numero">Siguiente paso</span>
            <p>Tu cuenta está pendiente de aprobación. Podrás iniciar sesión cuando el administrador la autorice.</p>
        </div>
    <?php else: ?>
        <p class="verificacion-ayuda">Revisa que el enlace esté completo. Si ya fue utilizado, puedes volver a iniciar sesión.</p>
    <?php endif; ?>

    <a class="btn verificacion-accion" href="login.php">Ir a iniciar sesión</a>
</main>
</body>
</html>