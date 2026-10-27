<?php
session_start();
require_once __DIR__ . "/../conexion.php";

if (!isset($_SESSION["usuario_id"]) || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php");
    exit;
}

$conexion = conectar();

$usuario_id   = (int) $_SESSION["usuario_id"];
$subbloque_id = (int) ($_POST["subbloque_id"] ?? 0);

// Buscar la inscripción del alumno en el curso al que pertenece esa lección
$sql = "SELECT i.id AS inscripcion_id, b.curso_id
        FROM subbloques s
        INNER JOIN bloques b ON s.bloque_id = b.id
        INNER JOIN cursos c ON c.id = b.curso_id AND c.estado <> 'borrador'
        INNER JOIN inscripciones i ON i.curso_id = b.curso_id AND i.usuario_id = ?
        WHERE s.id = ?";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $usuario_id, $subbloque_id);
$stmt->execute();
$datos = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$datos) {
    header("Location: mainWindow.php");   // no está inscrito o la lección no existe
    exit;
}



$inscripcion_id = (int) $datos["inscripcion_id"];

// Si ya estaba completada, se desmarca; si no, se marca
$stmt = $conexion->prepare(
    "DELETE FROM progreso_subbloques WHERE inscripcion_id = ? AND subbloque_id = ?"
);
$stmt->bind_param("ii", $inscripcion_id, $subbloque_id);
$stmt->execute();
$borrada = $stmt->affected_rows > 0;
$stmt->close();

if (!$borrada) {
    $stmt = $conexion->prepare(
        "INSERT INTO progreso_subbloques (inscripcion_id, subbloque_id) VALUES (?, ?)"
    );
    $stmt->bind_param("ii", $inscripcion_id, $subbloque_id);
    $stmt->execute();
    $stmt->close();
}

$destino = (($_POST["volver"] ?? "") === "leccion")
    ? "leccion.php?id=" . $subbloque_id
    : "curso.php?id=" . (int) $datos["curso_id"] . "#l" . $subbloque_id;

header("Location: " . $destino);
exit;