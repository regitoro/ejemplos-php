<?php
session_start();
require_once __DIR__ . "/conexion.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Referrer-Policy: no-referrer");
header("X-Robots-Tag: noindex, nofollow");

$conexion = conectar();
$mensaje = "";
$exito = false;
$token = trim($_GET["token"] ?? $_POST["token"] ?? "");
$tokenValido = preg_match('/^[a-f0-9]{64}$/', $token) === 1;

if (empty($_SESSION["csrf_restaurar"])) {
    $_SESSION["csrf_restaurar"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $csrf = $_POST["csrf"] ?? "";
    $password = $_POST["password"] ?? "";
    $confirmarPassword = $_POST["confirmar_password"] ?? "";

    if (!hash_equals($_SESSION["csrf_restaurar"], $csrf)) {
        $mensaje = "No se pudo procesar la solicitud. Recarga la página e inténtalo otra vez.";
    } elseif (!$tokenValido) {
        $mensaje = "El enlace no es válido o ya venció. Solicita uno nuevo.";
    } elseif (strlen($password) < 10) {
        $mensaje = "La nueva contraseña debe tener al menos 10 caracteres.";
    } elseif (!hash_equals($password, $confirmarPassword)) {
        $mensaje = "Las contraseñas no coinciden.";
    } else {
        $tokenHash = hash("sha256", $token);
        $conexion->begin_transaction();
        try {
            $stmt = $conexion->prepare(
                "SELECT id, usuario_id FROM password_reset_tokens
                 WHERE token_hash = ? AND usado_en IS NULL AND expira_en > NOW()
                 LIMIT 1 FOR UPDATE"
            );
            $stmt->bind_param("s", $tokenHash);
            $stmt->execute();
            $registro = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$registro) {
                $conexion->rollback();
                $mensaje = "El enlace no es válido o ya venció. Solicita uno nuevo.";
            } else {
                $usuarioId = (int) $registro["usuario_id"];
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $passwordHash, $usuarioId);
                if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                    throw new RuntimeException("No se pudo actualizar la contraseña.");
                }
                $stmt->close();

                $stmt = $conexion->prepare(
                    "UPDATE password_reset_tokens SET usado_en = NOW()
                     WHERE usuario_id = ? AND usado_en IS NULL"
                );
                $stmt->bind_param("i", $usuarioId);
                if (!$stmt->execute()) {
                    throw new RuntimeException("No se pudieron invalidar los enlaces anteriores.");
                }
                $stmt->close();
                $conexion->commit();
                $exito = true;
                $mensaje = "Tu contraseña se actualizó correctamente. Ya puedes iniciar sesión.";
                session_regenerate_id(true);
            }
        } catch (Throwable $e) {
            $conexion->rollback();
            error_log("Error al restablecer contraseña: " . $e->getMessage());
            $mensaje = "No se pudo guardar la contraseña. Solicita un enlace nuevo e inténtalo otra vez.";
        }
    }
}

if (!$tokenValido && !$exito && $mensaje === "") {
    $mensaje = "El enlace no es válido o ya venció. Solicita uno nuevo.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva contraseña | Learnex</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-flow auth-restablecer">
<main class="card recuperacion-card">
    <a class="recuperacion-volver" href="login.php">← Volver a iniciar sesión</a>
    <div class="recuperacion-icono" aria-hidden="true">•••</div>
    <span class="recuperacion-kicker">SEGURIDAD DE CUENTA</span>
    <h1>Nueva contraseña</h1>

    <?php if ($exito): ?>
        <div class="recuperacion-confirmacion" role="status">
            <strong>Contraseña actualizada</strong>
            <p><?php echo htmlspecialchars($mensaje); ?></p>
        </div>
        <a class="btn enlace-login-recuperacion" href="login.php">Iniciar sesión</a>
    <?php elseif (!$tokenValido || strpos($mensaje, "enlace") === 0): ?>
        <div class="recuperacion-error" role="alert">
            <strong>Enlace no disponible</strong>
            <p><?php echo htmlspecialchars($mensaje); ?></p>
        </div>
        <a class="btn enlace-login-recuperacion" href="recuperar_contrasena.php">Solicitar otro enlace</a>
    <?php else: ?>
        <p class="subtitulo">Elige una contraseña nueva para proteger tu cuenta.</p>
        <form method="POST" class="formulario-recuperacion">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION["csrf_restaurar"]); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div class="campo">
                <label for="password">Nueva contraseña</label>
                <input type="password" id="password" name="password" autocomplete="new-password" minlength="10" required>
                <small class="ayuda-password">Usa al menos 10 caracteres.</small>
            </div>
            <div class="campo">
                <label for="confirmar_password">Confirma la nueva contraseña</label>
                <input type="password" id="confirmar_password" name="confirmar_password" autocomplete="new-password" minlength="10" required>
            </div>
            <?php if ($mensaje !== ""): ?><p class="mensaje error-recuperacion" role="alert"><?php echo htmlspecialchars($mensaje); ?></p><?php endif; ?>
            <button type="submit">Guardar nueva contraseña</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
