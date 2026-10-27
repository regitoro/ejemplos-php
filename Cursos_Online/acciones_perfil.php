<?php
session_start();
require_once "conexion.php";

if (!isset($_SESSION["usuario_id"]) || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$conexion = conectar();

$id     = (int) $_SESSION["usuario_id"];
$accion = $_POST["accion"] ?? "";
$msg    = "error";

if ($accion === "nombre") {

    $nombre = trim($_POST["nombre"] ?? "");

    if ($nombre === "" || strlen($nombre) > 100) {
        $msg = "nombre_invalido";
    } else {
        $stmt = $conexion->prepare("UPDATE usuarios SET nombre = ? WHERE id = ?");
        $stmt->bind_param("si", $nombre, $id);
        $stmt->execute();
        $stmt->close();

        $_SESSION["nombre"] = $nombre;   // para que el menú lateral lo muestre ya
        $msg = "nombre_actualizado";
    }

} elseif ($accion === "password") {

    $actual    = $_POST["actual"] ?? "";
    $nueva     = $_POST["nueva"] ?? "";
    $confirmar = $_POST["confirmar"] ?? "";

    // Contraseña guardada
    $stmt = $conexion->prepare("SELECT password FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$fila || !password_verify($actual, $fila["password"])) {
        $msg = "password_incorrecta";
    } elseif (strlen($nueva) < 8) {
        $msg = "password_corta";
    } elseif ($nueva !== $confirmar) {
        $msg = "password_no_coincide";
    } else {
        $hash = password_hash($nueva, PASSWORD_DEFAULT);
        $stmt = $conexion->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $id);
        $stmt->execute();
        $stmt->close();

        session_regenerate_id(true);
        $msg = "password_actualizada";
    }
}

// Volver al perfil del panel que corresponda
$rol = strtolower($_SESSION["rol"] ?? "");
$destinos = [
    "admin"    => "Admin/admin.php?seccion=perfil",
    "director" => "Director/director.php?seccion=perfil",
    "ponente"  => "Ponente/ponente.php?seccion=perfil",
    "alumno"   => "Alumno/alumno.php?seccion=perfil",
];

if (!isset($destinos[$rol])) {
    header("Location: mainWindow.php");
    exit;
}

header("Location: " . $destinos[$rol] . "&msg=" . $msg);
exit;