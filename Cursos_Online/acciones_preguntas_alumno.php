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
$alumnoId = (int) $_SESSION["usuario_id"];

if ($_SERVER["REQUEST_METHOD"] !== "POST" || strtolower($_SESSION["rol"] ?? "") !== "alumno"
    || empty($_SESSION["csrf_preguntas"])
    || !hash_equals($_SESSION["csrf_preguntas"], $_POST["csrf"] ?? "")) {
    header("Location: mainWindow.php");
    exit;
}

$subbloqueId = (int) ($_POST["subbloque_id"] ?? 0);
$ponenteId = (int) ($_POST["ponente_id"] ?? 0);
$pregunta = trim($_POST["pregunta"] ?? "");

// Solo se permite preguntar desde una lección de un curso en el que el alumno está inscrito.
$stmt = $conexion->prepare(
    "SELECT c.id AS curso_id
     FROM subbloques s
     INNER JOIN bloques b ON b.id = s.bloque_id
     INNER JOIN cursos c ON c.id = b.curso_id
     INNER JOIN inscripciones i ON i.curso_id = c.id
     WHERE s.id = ? AND i.usuario_id = ? AND c.estado <> 'borrador'"
);
$stmt->bind_param("ii", $subbloqueId, $alumnoId);
$stmt->execute();
$leccion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$leccion || $ponenteId <= 0 || $pregunta === "" || strlen($pregunta) > 9000) {
    header("Location: Alumno/leccion.php?id=" . $subbloqueId . "&msg=pregunta_error");
    exit;
}
$cursoId = (int) $leccion["curso_id"];

// El destinatario debe estar asignado al mismo curso.
$stmt = $conexion->prepare(
    "SELECT cp.usuario_id
     FROM curso_ponentes cp
     INNER JOIN usuarios u ON u.id = cp.usuario_id
     INNER JOIN roles r ON r.id = u.rol_id
     WHERE cp.curso_id = ? AND cp.usuario_id = ? AND LOWER(r.nombre) = 'ponente' AND u.aceptado = 1"
);
$stmt->bind_param("ii", $cursoId, $ponenteId);
$stmt->execute();
$ponenteValido = $stmt->get_result()->num_rows === 1;
$stmt->close();

if (!$ponenteValido) {
    header("Location: Alumno/leccion.php?id=" . $subbloqueId . "&msg=pregunta_error");
    exit;
}

$stmt = $conexion->prepare(
    "INSERT INTO preguntas_ponente (alumno_id, ponente_id, curso_id, subbloque_id, pregunta)
     VALUES (?, ?, ?, ?, ?)"
);
$stmt->bind_param("iiiis", $alumnoId, $ponenteId, $cursoId, $subbloqueId, $pregunta);
$ok = $stmt->execute();
$stmt->close();

$msg = $ok ? "pregunta_enviada" : "pregunta_error";
header("Location: Alumno/leccion.php?id=" . $subbloqueId . "&msg=" . $msg);
exit;
