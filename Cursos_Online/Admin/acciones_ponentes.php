<?php
session_start();
require_once __DIR__ . "/../conexion.php";
require_once __DIR__ . "/../permisos.php";

$rol = strtolower($_SESSION["rol"] ?? "");
$uid = (int) ($_SESSION["usuario_id"] ?? 0);

// Solo admin o director, y solo por POST
if ($uid === 0 || !in_array($rol, ["admin", "director"], true)
    || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php");
    exit;
}

$conexion = conectar();
$panel    = $rol === "director" ? "director.php" : "admin.php";

$accion    = $_POST["accion"] ?? "";
$cursoId   = (int) ($_POST["curso_id"] ?? 0);
$usuarioId = (int) ($_POST["usuario_id"] ?? 0);
$msg       = "error";

// El curso debe existir y la persona debe poder editarlo
if (!puedeEditarCurso($conexion, $cursoId, $rol, $uid)) {
    header("Location: " . $panel . "?seccion=cursos&msg=sin_permiso");
    exit;
}

if ($accion === "asignar") {

    // Solo usuarios aprobados con rol ponente
    $stmt = $conexion->prepare(
        "SELECT u.id FROM usuarios u
         INNER JOIN roles r ON u.rol_id = r.id
         WHERE u.id = ? AND r.nombre = 'ponente' AND u.aceptado = 1"
    );
    $stmt->bind_param("i", $usuarioId);
    $stmt->execute();
    $esPonente = $stmt->get_result()->num_rows === 1;
    $stmt->close();

    if ($esPonente) {
        // INSERT IGNORE: si ya estaba asignado no da error ni duplica
        $stmt = $conexion->prepare(
            "INSERT IGNORE INTO curso_ponentes (curso_id, usuario_id) VALUES (?, ?)"
        );
        $stmt->bind_param("ii", $cursoId, $usuarioId);
        $stmt->execute();
        $msg = $stmt->affected_rows > 0 ? "ponente_asignado" : "error";
        $stmt->close();
    }

} elseif ($accion === "quitar") {

    $stmt = $conexion->prepare(
        "DELETE FROM curso_ponentes WHERE curso_id = ? AND usuario_id = ?"
    );
    $stmt->bind_param("ii", $cursoId, $usuarioId);
    $stmt->execute();
    $msg = $stmt->affected_rows > 0 ? "ponente_quitado" : "error";
    $stmt->close();
}

header("Location: " . $panel . "?seccion=cursos&gestionar=" . $cursoId . "&msg=" . $msg);
exit;