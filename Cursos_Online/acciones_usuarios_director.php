<?php
session_start();
require_once "conexion.php";

$rol   = strtolower($_SESSION["rol"] ?? "");
$dirId = (int) ($_SESSION["usuario_id"] ?? 0);

// Solo directores, y solo por POST
if ($dirId === 0 || $rol !== "director" || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$conexion = conectar();

$accion    = $_POST["accion"] ?? "";
$usuarioId = (int) ($_POST["usuario_id"] ?? 0);
$msg       = "error";

if ($accion === "quitar_inscripcion") {

    $inscripcionId = (int) ($_POST["inscripcion_id"] ?? 0);

    // El progreso se borra solo (ON DELETE CASCADE)
    $stmt = $conexion->prepare(
        "DELETE i FROM inscripciones i
         INNER JOIN cursos c ON i.curso_id = c.id
         WHERE i.id = ? AND c.director_id = ?"
    );
    $stmt->bind_param("ii", $inscripcionId, $dirId);
    $stmt->execute();
    $msg = $stmt->affected_rows > 0 ? "inscripcion_quitada" : "error";
    $stmt->close();

} elseif ($accion === "quitar_ponente") {

    $cursoId = (int) ($_POST["curso_id"] ?? 0);

    $stmt = $conexion->prepare(
        "DELETE cp FROM curso_ponentes cp
         INNER JOIN cursos c ON cp.curso_id = c.id
         WHERE cp.curso_id = ? AND cp.usuario_id = ? AND c.director_id = ?"
    );
    $stmt->bind_param("iii", $cursoId, $usuarioId, $dirId);
    $stmt->execute();
    $msg = $stmt->affected_rows > 0 ? "ponente_quitado" : "error";
    $stmt->close();

} elseif ($accion === "quitar_todo" && $usuarioId > 0) {

    // Inscripciones en mis cursos
    $stmt = $conexion->prepare(
        "DELETE i FROM inscripciones i
         INNER JOIN cursos c ON i.curso_id = c.id
         WHERE i.usuario_id = ? AND c.director_id = ?"
    );
    $stmt->bind_param("ii", $usuarioId, $dirId);
    $stmt->execute();
    $quitadas = $stmt->affected_rows;
    $stmt->close();

    // Asignaciones como ponente en mis cursos
    $stmt = $conexion->prepare(
        "DELETE cp FROM curso_ponentes cp
         INNER JOIN cursos c ON cp.curso_id = c.id
         WHERE cp.usuario_id = ? AND c.director_id = ?"
    );
    $stmt->bind_param("ii", $usuarioId, $dirId);
    $stmt->execute();
    $quitadas += $stmt->affected_rows;
    $stmt->close();

    $msg = $quitadas > 0 ? "todo_quitado" : "error";
}

if (($_POST["volver"] ?? "") === "lista") {

    // Se quitó desde la lista por cursos: se vuelve a la lista, con su filtro y búsqueda
    $destino = "Director/director.php?seccion=usuarios";

    $cursoPost = (int) ($_POST["curso"] ?? 0);
    if ($cursoPost > 0) {
        $destino .= "&curso=" . $cursoPost;
    }

    $qPost = trim($_POST["q"] ?? "");
    if (strlen($qPost) > 100) {
        $qPost = substr($qPost, 0, 100);
    }
    if ($qPost !== "") {
        $destino .= "&q=" . urlencode($qPost);
    }

} else {

    // Se quitó desde el detalle: si la persona sigue en mis cursos, se vuelve a su detalle
    $stmt = $conexion->prepare(
        "SELECT 1 WHERE EXISTS (
             SELECT 1 FROM inscripciones i
             INNER JOIN cursos c ON i.curso_id = c.id
             WHERE i.usuario_id = ? AND c.director_id = ?)
         OR EXISTS (
             SELECT 1 FROM curso_ponentes cp
             INNER JOIN cursos c ON cp.curso_id = c.id
             WHERE cp.usuario_id = ? AND c.director_id = ?)"
    );
    $stmt->bind_param("iiii", $usuarioId, $dirId, $usuarioId, $dirId);
    $stmt->execute();
    $sigue = $stmt->get_result()->num_rows === 1;
    $stmt->close();

    $destino = "Director/director.php?seccion=usuarios";
    if ($sigue && $usuarioId > 0) {
        $destino .= "&ver=" . $usuarioId;
    }
}

header("Location: " . $destino . "&msg=" . $msg);
exit;