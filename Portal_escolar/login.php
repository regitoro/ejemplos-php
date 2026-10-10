<?php
session_start();
include_once "conexion.php";

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $sql = "SELECT * FROM usuarios WHERE email = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $resultado = $stmt->get_result();

    

    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();

        if (!$usuario["email_verificado"]) {
            $mensaje = "Por favor, verifica tu correo electrónico antes de iniciar sesión.";
        } elseif (password_verify($password, $usuario["password"])) {
            session_regenerate_id(true);
            $_SESSION["usuario_id"] = $usuario["id"];
            $_SESSION["nombre"] = $usuario["nombre"];
            header("Location: dashboard.php");
            exit;
        } else {
            $mensaje = "Contraseña incorrecta.";
        }
    } else {
        $mensaje = "Usuario no encontrado.";
    }
}
?>

<h1>Iniciar sesión</h1>
<form method="POST">
    <label>Email:</label>
    <input type="email" name="email" required>
    <label>Contraseña:</label>
    <input type="password" name="password" required>
    <button type="submit">Entrar</button>
</form>
<p><?php echo htmlspecialchars($mensaje); ?></p>
<a href="registro.php">Crear cuenta</a>