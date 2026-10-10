<?php
include_once "conexion.php";
$token = $_GET['token'] ?? '';
$sql = "SELECT id FROM usuarios WHERE token_verificacion = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param(
    "s", 
    $token
    );
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {
    $usuario = $resultado->fetch_assoc();
    $sql = "UPDATE usuarios 
            SET email_verificado = 1, 
                token_verificacion = NULL 
            WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param(
        "i", $usuario['id']);
    $stmt->execute();

    echo "<h1>Email confirmado</h1>";
    echo "<a href='login.php'>Iniciar sesión</a>";

}else {
    echo "Token inválido";
}