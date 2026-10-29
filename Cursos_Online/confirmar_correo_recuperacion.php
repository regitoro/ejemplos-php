<?php
session_start();
require_once __DIR__ . "/conexion.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Referrer-Policy: no-referrer");
header("X-Robots-Tag: noindex, nofollow");

$host = strtolower((string) ($_SERVER["HTTP_HOST"] ?? ""));
$host = preg_replace('/:\\d+$/', '', $host);
$ip = (string) ($_SERVER["REMOTE_ADDR"] ?? "");
$esLocal = in_array($host, ["localhost", "127.0.0.1", "::1"], true)
    && in_array($ip, ["127.0.0.1", "::1"], true);

$token = trim($_GET["token"] ?? "");
$tokenValido = preg_match('/^[a-f0-9]{64}$/', $token) === 1;
$confirmado = false;

if ($esLocal && $tokenValido) {
    $conexion = conectar();
    $tokenHash = hash("sha256", $token);
    $stmt = $conexion->prepare(
        "SELECT id FROM password_reset_tokens
         WHERE token_hash = ? AND usado_en IS NULL AND expira_en > NOW()
         LIMIT 1"
    );
    $stmt->bind_param("s", $tokenHash);
    $stmt->execute();
    $confirmado = $stmt->get_result()->num_rows === 1;
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Continuar recuperación | Learnex</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-flow auth-confirmacion-recuperacion">
<main class="card recuperacion-card">
    <a class="recuperacion-volver" href="login.php">← Volver a iniciar sesión</a>
    <div class="recuperacion-icono" aria-hidden="true"><?php echo $confirmado ? '✓' : '!'; ?></div>
    <span class="recuperacion-kicker">RECUPERACIÓN DE CUENTA</span>
    <?php if ($confirmado): ?>
        <h1>Solicitud confirmada</h1>
        <div class="recuperacion-confirmacion" role="status">
            <strong>Enlace válido</strong>
            <p>Ya puedes continuar con el cambio de contraseña.</p>
        </div>
        <p class="subtitulo">El enlace vence en 45 minutos y se puede utilizar una sola vez.</p>
        <a class="btn enlace-login-recuperacion" href="restablecer_contrasena.php?token=<?php echo urlencode($token); ?>">Continuar para crear nueva contraseña</a>
    <?php else: ?>
        <h1>Enlace no disponible</h1>
        <div class="recuperacion-error" role="alert">
            <p>El enlace no es válido, ya se utilizó o venció.</p>
        </div>
        <a class="btn enlace-login-recuperacion" href="recuperar_contrasena.php">Solicitar otra recuperación</a>
    <?php endif; ?>
</main>
</body>
</html>
