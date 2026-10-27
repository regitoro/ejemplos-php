<?php
session_start();
require_once __DIR__ . "/../conexion.php";

// Solo el admin, y solo por POST
if (!isset($_SESSION["usuario_id"]) || strtolower($_SESSION["rol"] ?? "") !== "admin"
    || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php");
    exit;
}

$conexion = conectar();

$cursoId    = (int) ($_POST["curso_id"] ?? 0);
$directorId = (int) ($_POST["director_id"] ?? 0);   // 0 = sin director
$msg        = "error";

// El curso debe existir
$stmt = $conexion->prepare("SELECT id FROM cursos WHERE id = ?");
$stmt->bind_param("i", $cursoId);
$stmt->execute();
$cursoExiste = $stmt->get_result()->num_rows === 1;
$stmt->close();

if (!$cursoExiste) {
    header("Location: admin.php?seccion=cursos&msg=error");
    exit;
}

if ($directorId === 0) {

    $stmt = $conexion->prepare("UPDATE cursos SET director_id = NULL WHERE id = ?");
    $stmt->bind_param("i", $cursoId);
    $stmt->execute();
    $stmt->close();
    $msg = "director_actualizado";

} else {

    // Debe ser un usuario aprobado con rol director
    $stmt = $conexion->prepare(
        "SELECT u.id FROM usuarios u
         INNER JOIN roles r ON u.rol_id = r.id
         WHERE u.id = ? AND r.nombre = 'director' AND u.aceptado = 1"
    );
    $stmt->bind_param("i", $directorId);
    $stmt->execute();
    $esDirector = $stmt->get_result()->num_rows === 1;
    $stmt->close();

    if ($esDirector) {
        $stmt = $conexion->prepare("UPDATE cursos SET director_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $directorId, $cursoId);
        $stmt->execute();
        $stmt->close();
        $msg = "director_actualizado";
    }
}

header("Location: admin.php?seccion=cursos&gestionar=" . $cursoId . "&msg=" . $msg);
exit;