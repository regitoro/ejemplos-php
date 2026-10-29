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
if ($ejercicioId <= 0) {
    header("Location: " . $destino . "&msg=ejercicio_error");
    exit;
}

$stmt = $conexion->prepare(
    "SELECT e.id, e.tipo, e.puntuacion_maxima
     FROM ejercicios e
     INNER JOIN inscripciones i ON i.curso_id = e.curso_id
     INNER JOIN cursos c ON c.id = e.curso_id
    WHERE e.id = ? AND e.curso_id = ? AND i.usuario_id = ? AND c.estado = 'activo'"
);
$stmt->bind_param("iii", $ejercicioId, $cursoId, $alumnoId);
$stmt->execute();
$ejercicio = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ejercicio) {
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

if ($ejercicio["tipo"] === "multiple") {
    $opcionId = (int) ($_POST["opcion_id"] ?? 0);
    $stmt = $conexion->prepare(
        "SELECT texto, es_correcta FROM opciones_ejercicio WHERE id = ? AND ejercicio_id = ?"
    );
    $stmt->bind_param("ii", $opcionId, $ejercicioId);
    $stmt->execute();
    $opcion = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$opcion || $entrega) {
        header("Location: " . $destino . "&msg=ejercicio_error");
        exit;
    }

    $puntuacion = (int) $opcion["es_correcta"] === 1 ? (float) $ejercicio["puntuacion_maxima"] : 0.0;
    $comentario = "Calificación automática.";
    $conexion->begin_transaction();
    $stmt = $conexion->prepare(
        "INSERT INTO entregas_ejercicios
            (ejercicio_id, alumno_id, respuesta, puntuacion, comentario, calificado_en)
         VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
    );
    $stmt->bind_param("iisds", $ejercicioId, $alumnoId, $opcion["texto"], $puntuacion, $comentario);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        $conexion->commit();
    } else {
        $conexion->rollback();
    }
    header("Location: " . $destino . "&msg=" . ($ok ? "ejercicio_auto_calificado" : "ejercicio_error"));
    exit;
}

if ($respuesta === "" || strlen($respuesta) > 60000) {
    header("Location: " . $destino . "&msg=ejercicio_error");
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
