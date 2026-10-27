<?php
session_start();
require_once __DIR__ . "/../conexion.php";

// Solo alumnos, y solo por POST
if (!isset($_SESSION["usuario_id"]) || strtolower($_SESSION["rol"] ?? "") !== "alumno"
    || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php");
    exit;
}

$conexion  = conectar();
$usuarioId = (int) $_SESSION["usuario_id"];
$cursoId   = (int) ($_POST["curso_id"] ?? 0);
$accion    = $_POST["accion"] ?? "";
$msg       = "error";

// Texto de búsqueda, para volver a la misma pantalla
$q = trim($_POST["q"] ?? "");
if (strlen($q) > 100) {
    $q = substr($q, 0, 100);
}

if ($accion === "solicitar") {

    // El curso debe existir y estar activo
    $stmt = $conexion->prepare("SELECT id FROM cursos WHERE id = ? AND estado = 'activo'");
    $stmt->bind_param("i", $cursoId);
    $stmt->execute();
    $activo = $stmt->get_result()->num_rows === 1;
    $stmt->close();

    // No debe estar inscrito ya
    $stmt = $conexion->prepare("SELECT id FROM inscripciones WHERE curso_id = ? AND usuario_id = ?");
    $stmt->bind_param("ii", $cursoId, $usuarioId);
    $stmt->execute();
    $yaInscrito = $stmt->get_result()->num_rows === 1;
    $stmt->close();

    if ($activo && !$yaInscrito) {
        // Si había una solicitud rechazada, vuelve a quedar pendiente.
        // Si ya estaba pendiente no cambia nada.
        $stmt = $conexion->prepare(
            "INSERT INTO solicitudes_inscripcion (curso_id, usuario_id) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE
                 creada_en = IF(estado = 'pendiente', creada_en, NOW()),
                 estado = 'pendiente'"
        );
        $stmt->bind_param("ii", $cursoId, $usuarioId);
        $stmt->execute();
        $msg = $stmt->affected_rows > 0 ? "solicitud_enviada" : "solicitud_existe";
        $stmt->close();
    }

} elseif ($accion === "cancelar") {

    $stmt = $conexion->prepare(
        "DELETE FROM solicitudes_inscripcion
         WHERE curso_id = ? AND usuario_id = ? AND estado = 'pendiente'"
    );
    $stmt->bind_param("ii", $cursoId, $usuarioId);
    $stmt->execute();
    $msg = $stmt->affected_rows > 0 ? "solicitud_cancelada" : "error";
    $stmt->close();
}

$destino = "alumno.php?seccion=explorar&msg=" . $msg;
if ($q !== "") {
    $destino .= "&q=" . urlencode($q);
}
header("Location: " . $destino);
exit;