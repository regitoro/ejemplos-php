<?php
session_start();
require_once __DIR__ . "/conexion.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Referrer-Policy: no-referrer");

$conexion = conectar();
$mensaje = "";

if (empty($_SESSION["csrf_recuperacion"])) {
    $_SESSION["csrf_recuperacion"] = bin2hex(random_bytes(32));
}

function baseAplicacion(): string
{
    $configurada = trim((string) getenv("APP_BASE_URL"));
    if ($configurada !== "") {
        return rtrim($configurada, "/");
    }

    $host = $_SERVER["HTTP_HOST"] ?? "localhost";
    if (!preg_match('/^(localhost|[a-z0-9.-]+)(:\\d{1,5})?$/i', $host)) {
        $host = "localhost";
    }
    $https = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";
    return ($https ? "https" : "http") . "://" . $host;
}

function permiteContinuarRecuperacionLocal(): bool
{
    $host = strtolower((string) ($_SERVER["HTTP_HOST"] ?? ""));
    $host = preg_replace('/:\\d+$/', '', $host);
    $ip = (string) ($_SERVER["REMOTE_ADDR"] ?? "");
    return in_array($host, ["localhost", "127.0.0.1", "::1"], true)
        && in_array($ip, ["127.0.0.1", "::1"], true);
}

$enlaceRecuperacionLocal = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf"] ?? "";
    $email = trim($_POST["email"] ?? "");
    $confirmarEmail = trim($_POST["confirmar_email"] ?? "");

    if (!hash_equals($_SESSION["csrf_recuperacion"], $csrf)) {
        $mensaje = "No se pudo procesar la solicitud. Recarga la página e inténtalo otra vez.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !hash_equals(strtolower($email), strtolower($confirmarEmail))) {
        $mensaje = "Revisa que ambos correos sean válidos y estén escritos exactamente igual.";
    } else {
        // La respuesta es la misma exista o no la cuenta, para evitar enumerar correos.
        $respuestaGenerica = "Si el correo está registrado y verificado, se generará una solicitud para restablecer tu contraseña.";
        $conexion->query("DELETE FROM password_reset_tokens WHERE creado_en < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt = $conexion->prepare(
            "SELECT id, nombre FROM usuarios WHERE email = ? AND email_verificado = 1 LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($usuario) {
            $usuarioId = (int) $usuario["id"];
            $stmt = $conexion->prepare(
                "SELECT COUNT(*) AS solicitudes FROM password_reset_tokens
                 WHERE usuario_id = ? AND creado_en >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
            $stmt->bind_param("i", $usuarioId);
            $stmt->execute();
            $solicitudes = (int) $stmt->get_result()->fetch_assoc()["solicitudes"];
            $stmt->close();

            if ($solicitudes < 3) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash("sha256", $token);
                $stmt = $conexion->prepare(
                    "INSERT INTO password_reset_tokens (usuario_id, token_hash, expira_en)
                     VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 45 MINUTE))"
                );
                $stmt->bind_param("is", $usuarioId, $tokenHash);
                $guardado = $stmt->execute();
                $stmt->close();

                if ($guardado) {
                    $url = baseAplicacion() . "/restablecer_contrasena.php?token=" . urlencode($token);
                    $nombre = trim((string) $usuario["nombre"]);
                    $asunto = "Restablece tu contraseña de Cursos Online";
                    $cuerpo = "Hola " . ($nombre !== "" ? $nombre : "") . ",\n\n"
                        . "Recibimos una solicitud para cambiar la contraseña de tu cuenta. "
                        . "Abre este enlace dentro de los próximos 45 minutos:\n\n"
                        . $url . "\n\n"
                        . "Si no solicitaste este cambio, ignora este mensaje. Tu contraseña no cambiará.\n";
                    $remitente = getenv("RESET_MAIL_FROM") ?: "no-reply@localhost";
                    if (!filter_var($remitente, FILTER_VALIDATE_EMAIL)) {
                        $remitente = "no-reply@localhost";
                    }
                    $cabeceras = "From: Cursos Online <" . $remitente . ">\r\n"
                        . "Content-Type: text/plain; charset=UTF-8\r\n"
                        . "X-Mailer: PHP/" . phpversion();

                    if (!@mail($email, "=?UTF-8?B?" . base64_encode($asunto) . "?=", $cuerpo, $cabeceras)) {
                        if (permiteContinuarRecuperacionLocal()) {
                            $enlaceRecuperacionLocal = baseAplicacion() . "/confirmar_correo_recuperacion.php?token=" . urlencode($token);
                        } else {
                            $stmt = $conexion->prepare("DELETE FROM password_reset_tokens WHERE token_hash = ? AND usado_en IS NULL");
                            $stmt->bind_param("s", $tokenHash);
                            $stmt->execute();
                            $stmt->close();
                        }
                        error_log("No se pudo enviar correo de recuperación; revisa la configuración de mail()/SMTP del servidor.");
                    }
                }
            }
        }

        $mensaje = $respuestaGenerica;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="card recuperacion-card">
    <a class="recuperacion-volver" href="login.php">← Volver a iniciar sesión</a>
    <div class="recuperacion-icono" aria-hidden="true">↗</div>
    <span class="recuperacion-kicker">ACCESO A TU CUENTA</span>
    <h1>Recuperar contraseña</h1>
    <p class="subtitulo">Indica el correo asociado a tu cuenta para iniciar el restablecimiento de contraseña.</p>

    <?php if ($mensaje !== "" && strpos($mensaje, "Si el correo") === 0): ?>
        <div class="recuperacion-confirmacion" role="status">
            <strong>Solicitud procesada</strong>
            <p><?php echo htmlspecialchars($mensaje); ?></p>
            <small>El enlace, si corresponde, caduca en 45 minutos.</small>
            <?php if ($enlaceRecuperacionLocal !== ""): ?>
                <div class="continuar-recuperacion">
                    <a class="btn enlace-login-recuperacion" href="<?php echo htmlspecialchars($enlaceRecuperacionLocal); ?>">Continuar con recuperación</a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <form method="POST" class="formulario-recuperacion">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION["csrf_recuperacion"]); ?>">
            <div class="campo">
                <label for="email">Correo electrónico de tu cuenta</label>
                <input type="email" id="email" name="email" autocomplete="email" maxlength="254" required>
            </div>
            <div class="campo">
                <label for="confirmar_email">Confirma tu correo electrónico</label>
                <input type="email" id="confirmar_email" name="confirmar_email" autocomplete="email" maxlength="254" required>
            </div>
            <?php if ($mensaje !== ""): ?><p class="mensaje error-recuperacion" role="alert"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
            <button type="submit">Enviar enlace de recuperación</button>
        </form>
        <p class="recuperacion-nota">Por seguridad, el enlace solo se envía al correo verificado asociado a la cuenta.</p>
    <?php endif; ?>
</main>
</body>
</html>
