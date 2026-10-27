<?php
session_start();
require_once __DIR__ . "/../conexion.php";

// Solo el admin, y solo por POST
if (!isset($_SESSION["usuario_id"]) || strtolower($_SESSION["rol"] ?? "") !== "admin"
    || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php");
    exit;
}

$conexion = conectar();

$accion     = $_POST["accion"] ?? "";
$id         = (int) ($_POST["id"] ?? 0);
$comentario = trim($_POST["comentario"] ?? "");
if (strlen($comentario) > 1000) {
    $comentario = substr($comentario, 0, 1000);
}
$msg = "error";

if ($id > 0 && $accion === "aprobar") {

    $stmt = $conexion->prepare(
        "UPDATE cursos
         SET aprobacion = 'aprobado', revision_comentario = NULL
         WHERE id = ? AND aprobacion = 'en_revision'"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $msg = $stmt->affected_rows > 0 ? "curso_aprobado" : "error";
    $stmt->close();

} elseif ($id > 0 && $accion === "rechazar") {

    if ($comentario === "") {
        $msg = "comentario_requerido";
    } else {
        $stmt = $conexion->prepare(
            "UPDATE cursos
             SET aprobacion = 'rechazado', revision_comentario = ?
             WHERE id = ? AND aprobacion = 'en_revision'"
        );
        $stmt->bind_param("si", $comentario, $id);
        $stmt->execute();
        $msg = $stmt->affected_rows > 0 ? "cambios_pedidos" : "error";
        $stmt->close();
    }
}

header("Location: admin.php?seccion=revision&msg=" . $msg);
exit;