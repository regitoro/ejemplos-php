<?php
session_start();
require_once __DIR__ . "/permisos.php";
require_once __DIR__ . "/conexion.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$conexion = conectar();
sincronizarRol($conexion, "login.php");
$rol = strtolower($_SESSION["rol"] ?? "");
$uid = (int) $_SESSION["usuario_id"];
$paneles = [
    "admin" => "Admin/admin.php",
    "director" => "Director/director.php",
    "ponente" => "Ponente/ponente.php",
];
$panel = $paneles[$rol] ?? "mainWindow.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($paneles[$rol])
    || empty($_SESSION["csrf_ejercicios"])
    || !hash_equals($_SESSION["csrf_ejercicios"], $_POST["csrf"] ?? "")) {
    header("Location: " . $panel . "?seccion=cursos&msg=sin_permiso");
    exit;
}

$accion = $_POST["accion"] ?? "";
$cursoId = (int) ($_POST["curso_id"] ?? 0);
$ejercicioId = (int) ($_POST["ejercicio_id"] ?? 0);
$msg = "error";

if (!puedeEditarCurso($conexion, $cursoId, $rol, $uid)) {
    $msg = "sin_permiso";
} elseif ($accion === "crear") {
    $titulo = trim($_POST["titulo"] ?? "");
    $instrucciones = trim($_POST["instrucciones"] ?? "");
    $maximo = filter_var($_POST["puntuacion_maxima"] ?? null, FILTER_VALIDATE_FLOAT);
    if ($titulo !== "" && strlen($titulo) <= 150 && $instrucciones !== ""
        && strlen($instrucciones) <= 20000 && $maximo !== false && $maximo > 0 && $maximo <= 1000) {
        $stmt = $conexion->prepare(
            "INSERT INTO ejercicios (curso_id, creado_por, titulo, instrucciones, puntuacion_maxima)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iissd", $cursoId, $uid, $titulo, $instrucciones, $maximo);
        $msg = $stmt->execute() ? "ejercicio_creado" : "error";
        $stmt->close();
    } else {
        $msg = "ejercicio_invalido";
    }

} elseif ($accion === "editar" && $ejercicioId > 0) {
    $titulo = trim($_POST["titulo"] ?? "");
    $instrucciones = trim($_POST["instrucciones"] ?? "");
    $maximo = filter_var($_POST["puntuacion_maxima"] ?? null, FILTER_VALIDATE_FLOAT);
    $stmt = $conexion->prepare(
        "SELECT COUNT(*) AS n FROM entregas_ejercicios WHERE ejercicio_id = ?"
    );
    $stmt->bind_param("i", $ejercicioId);
    $stmt->execute();
    $tieneEntregas = (int) $stmt->get_result()->fetch_assoc()["n"] > 0;
    $stmt->close();

    if ($titulo === "" || strlen($titulo) > 150 || $instrucciones === "" || strlen($instrucciones) > 20000
        || $maximo === false || $maximo <= 0 || $maximo > 1000) {
        $msg = "ejercicio_invalido";
    } elseif ($tieneEntregas) {
        $msg = "ejercicio_con_entregas";
    } else {
        $stmt = $conexion->prepare(
            "UPDATE ejercicios SET titulo = ?, instrucciones = ?, puntuacion_maxima = ?
             WHERE id = ? AND curso_id = ?"
        );
        $stmt->bind_param("ssdii", $titulo, $instrucciones, $maximo, $ejercicioId, $cursoId);
        $msg = $stmt->execute() ? "ejercicio_actualizado" : "error";
        $stmt->close();
    }

} elseif ($accion === "eliminar" && $ejercicioId > 0) {
    $stmt = $conexion->prepare(
        "SELECT COUNT(*) AS n FROM entregas_ejercicios WHERE ejercicio_id = ?"
    );
    $stmt->bind_param("i", $ejercicioId);
    $stmt->execute();
    $tieneEntregas = (int) $stmt->get_result()->fetch_assoc()["n"] > 0;
    $stmt->close();

    if ($tieneEntregas) {
        $msg = "ejercicio_con_entregas";
    } else {
        $stmt = $conexion->prepare("DELETE FROM ejercicios WHERE id = ? AND curso_id = ?");
        $stmt->bind_param("ii", $ejercicioId, $cursoId);
        $stmt->execute();
        $msg = $stmt->affected_rows === 1 ? "ejercicio_eliminado" : "error";
        $stmt->close();
    }
}

header("Location: " . $panel . "?seccion=cursos&gestionar=" . $cursoId . "&msg=" . $msg);
exit;
