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
    header("Location: " . $panel . "?seccion=calificar_ejercicios&msg=sin_permiso");
    exit;
}

$entregaId = (int) ($_POST["entrega_id"] ?? 0);
$puntuacion = filter_var($_POST["puntuacion"] ?? null, FILTER_VALIDATE_FLOAT);
$comentario = trim($_POST["comentario"] ?? "");
$msg = "calificacion_error";

if ($entregaId > 0 && $puntuacion !== false && $puntuacion >= 0 && strlen($comentario) <= 5000) {
    $stmt = $conexion->prepare(
        "SELECT ee.id, e.curso_id, e.puntuacion_maxima
         FROM entregas_ejercicios ee
         INNER JOIN ejercicios e ON e.id = ee.ejercicio_id
         WHERE ee.id = ?"
    );
    $stmt->bind_param("i", $entregaId);
    $stmt->execute();
    $entrega = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($entrega && puedeEditarCurso($conexion, (int) $entrega["curso_id"], $rol, $uid)
        && $puntuacion <= (float) $entrega["puntuacion_maxima"]) {
        $stmt = $conexion->prepare(
            "UPDATE entregas_ejercicios
             SET puntuacion = ?, comentario = ?, calificado_en = CURRENT_TIMESTAMP
             WHERE id = ?"
        );
        $stmt->bind_param("dsi", $puntuacion, $comentario, $entregaId);
        $msg = $stmt->execute() ? "calificacion_guardada" : "calificacion_error";
        $stmt->close();
    }
}

header("Location: " . $panel . "?seccion=calificar_ejercicios&msg=" . $msg);
exit;
