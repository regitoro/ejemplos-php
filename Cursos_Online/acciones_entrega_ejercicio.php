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
$cursoId = (int) ($_POST["curso_id"] ?? 0);
$destino = "Alumno/curso.php?id=" . $cursoId;

if ($_SERVER["REQUEST_METHOD"] !== "POST" || strtolower($_SESSION["rol"] ?? "") !== "alumno"
    || empty($_SESSION["csrf_ejercicios"])
    || !hash_equals($_SESSION["csrf_ejercicios"], $_POST["csrf"] ?? "")) {
    header("Location: " . $destino . "&msg=ejercicio_error");
    exit;
}

$ejercicioId = (int) ($_POST["ejercicio_id"] ?? 0);
$respuesta = trim($_POST["respuesta"] ?? "");
if ($ejercicioId <= 0 || $respuesta === "" || strlen($respuesta) > 60000) {
    header("Location: " . $destino . "&msg=ejercicio_error");
    exit;
}

$stmt = $conexion->prepare(
    "SELECT e.id
     FROM ejercicios e
     INNER JOIN inscripciones i ON i.curso_id = e.curso_id
     INNER JOIN cursos c ON c.id = e.curso_id
    WHERE e.id = ? AND e.curso_id = ? AND i.usuario_id = ? AND c.estado = 'activo'"
);
$stmt->bind_param("iii", $ejercicioId, $cursoId, $alumnoId);
$stmt->execute();
$permitido = $stmt->get_result()->num_rows === 1;
$stmt->close();

if (!$permitido) {
    header("Location: " . $destino . "&msg=ejercicio_error");
    exit;
}

$stmt = $conexion->prepare(
    "SELECT id, puntuacion FROM entregas_ejercicios WHERE ejercicio_id = ? AND alumno_id = ?"
);
$stmt->bind_param("ii", $ejercicioId, $alumnoId);
$stmt->execute();
$entrega = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($entrega && $entrega["puntuacion"] !== null) {
    header("Location: " . $destino . "&msg=ejercicio_calificado");
    exit;
}

if ($entrega) {
    $entregaId = (int) $entrega["id"];
    $stmt = $conexion->prepare(
        "UPDATE entregas_ejercicios SET respuesta = ?, entregado_en = CURRENT_TIMESTAMP
         WHERE id = ? AND alumno_id = ? AND puntuacion IS NULL"
    );
    $stmt->bind_param("sii", $respuesta, $entregaId, $alumnoId);
} else {
    $stmt = $conexion->prepare(
        "INSERT INTO entregas_ejercicios (ejercicio_id, alumno_id, respuesta)
         VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iis", $ejercicioId, $alumnoId, $respuesta);
}
$ok = $stmt->execute();
$stmt->close();

header("Location: " . $destino . "&msg=" . ($ok ? "ejercicio_entregado" : "ejercicio_error"));
exit;
