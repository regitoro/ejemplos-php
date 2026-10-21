<?php
session_start();
require_once "conexion.php";

// Solo el admin, y solo por POST
if (!isset($_SESSION["usuario_id"]) || strtolower($_SESSION["rol"] ?? "") !== "admin"
    || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$conexion = conectar();

$id     = (int) ($_POST["id"] ?? 0);
$accion = $_POST["accion"] ?? "";
$msg    = "error";

if ($id > 0 && $id !== (int) $_SESSION["usuario_id"]) {

    if ($accion === "aprobar") {
        $rol_id = (int) ($_POST["rol_id"] ?? 0);

        // Comprobar que el rol exista
        $stmt = $conexion->prepare("SELECT id FROM roles WHERE id = ?");
        $stmt->bind_param("i", $rol_id);
        $stmt->execute();
        $existe = $stmt->get_result()->num_rows === 1;
        $stmt->close();

        if ($existe) {
            $stmt = $conexion->prepare(
                "UPDATE usuarios SET aceptado = 1, rol_id = ? WHERE id = ? AND aceptado = 0"
            );
            $stmt->bind_param("ii", $rol_id, $id);
            $stmt->execute();
            $msg = $stmt->affected_rows > 0 ? "aprobado" : "error";
            $stmt->close();
        }

    } elseif ($accion === "rechazar") {
        // Solo se pueden rechazar cuentas pendientes
        $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ? AND aceptado = 0");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $msg = $stmt->affected_rows > 0 ? "rechazado" : "error";
        $stmt->close();
    }
}

header("Location: Admin/admin.php?seccion=usuarios&msg=" . $msg);
exit;