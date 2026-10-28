<?php
session_start();
require_once __DIR__ . "/conexion.php";
require_once __DIR__ . "/permisos.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$conexion = conectar();
sincronizarRol($conexion, "login.php");
$ponenteId = (int) $_SESSION["usuario_id"];
$destino = "Ponente/ponente.php?seccion=preguntas";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || strtolower($_SESSION["rol"] ?? "") !== "ponente"
    || empty($_SESSION["csrf_preguntas"])
    || !hash_equals($_SESSION["csrf_preguntas"], $_POST["csrf"] ?? "")) {
    header("Location: " . $destino . "&msg=respuesta_error");
    exit;
}

$preguntaId = (int) ($_POST["pregunta_id"] ?? 0);
$respuesta = trim($_POST["respuesta"] ?? "");
if ($preguntaId <= 0 || $respuesta === "" || strlen($respuesta) > 9000) {
    header("Location: " . $destino . "&msg=respuesta_error");
    exit;
}

// Verificar que la pregunta pertenece al ponente y que este sigue asignado al curso.
$stmt = $conexion->prepare(
    "SELECT pp.id
     FROM preguntas_ponente pp
     INNER JOIN curso_ponentes cp
        ON cp.curso_id = pp.curso_id AND cp.usuario_id = pp.ponente_id
     WHERE pp.id = ? AND pp.ponente_id = ? AND pp.estado = 'pendiente'"
);
$stmt->bind_param("ii", $preguntaId, $ponenteId);
$stmt->execute();
$autorizado = $stmt->get_result()->num_rows === 1;
$stmt->close();

if (!$autorizado) {
    header("Location: " . $destino . "&msg=respuesta_error");
    exit;
}

$stmt = $conexion->prepare(
    "UPDATE preguntas_ponente
     SET respuesta = ?, estado = 'respondida', respondido_en = CURRENT_TIMESTAMP
     WHERE id = ? AND ponente_id = ? AND estado = 'pendiente'"
);
$stmt->bind_param("sii", $respuesta, $preguntaId, $ponenteId);
$stmt->execute();
$actualizada = $stmt->affected_rows === 1;
$stmt->close();

header("Location: " . $destino . "&msg=" . ($actualizada ? "respuesta_enviada" : "respuesta_error"));
exit;
