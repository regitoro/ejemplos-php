<?php
// session_start();
// require_once __DIR__ . "/../conexion.php";

// // Solo el admin, y solo por POST
// if (!isset($_SESSION["usuario_id"]) || strtolower($_SESSION["rol"] ?? "") !== "admin"
//     || $_SERVER["REQUEST_METHOD"] !== "POST") {
//     header("Location: ../login.php");
//     exit;
// }

// $conexion = conectar();

// $accion  = $_POST["accion"] ?? "";
// $cursoId = (int) ($_POST["curso_id"] ?? 0);
// $msg     = "error";

// // El curso debe existir
// $stmt = $conexion->prepare("SELECT id FROM cursos WHERE id = ?");
// $stmt->bind_param("i", $cursoId);
// $stmt->execute();
// $cursoExiste = $stmt->get_result()->num_rows === 1;
// $stmt->close();

// if (!$cursoExiste) {
//     header("Location: admin.php?seccion=cursos&msg=error");
//     exit;
// }

// if ($accion === "inscribir") {

//     $ids = $_POST["usuario_ids"] ?? [];
//     if (!is_array($ids)) {
//         $ids = [];
//     }

//     // INSERT ... SELECT: solo entra si el usuario es un alumno aprobado.
//     // INSERT IGNORE evita duplicados si ya estaba inscrito.
//     $stmt = $conexion->prepare(
//         "INSERT IGNORE INTO inscripciones (curso_id, usuario_id)
//          SELECT ?, u.id
//          FROM usuarios u
//          INNER JOIN roles r ON u.rol_id = r.id
//          WHERE u.id = ? AND r.nombre = 'alumno' AND u.aceptado = 1"
//     );

//     $inscritos = 0;
//     foreach ($ids as $idCrudo) {
//         $usuarioId = (int) $idCrudo;
//         if ($usuarioId <= 0) continue;
//         $stmt->bind_param("ii", $cursoId, $usuarioId);
//         $stmt->execute();
//         $inscritos += $stmt->affected_rows > 0 ? 1 : 0;
//     }
//     $stmt->close();

//     $msg = $inscritos > 0 ? "alumnos_inscritos" : "error";

// } elseif ($accion === "quitar") {

//     $usuarioId = (int) ($_POST["usuario_id"] ?? 0);

//     // El progreso se borra solo (ON DELETE CASCADE)
//     $stmt = $conexion->prepare(
//         "DELETE FROM inscripciones WHERE curso_id = ? AND usuario_id = ?"
//     );
//     $stmt->bind_param("ii", $cursoId, $usuarioId);
//     $stmt->execute();
//     $msg = $stmt->affected_rows > 0 ? "alumno_quitado" : "error";
//     $stmt->close();
// }

// header("Location: admin.php?seccion=cursos&gestionar=" . $cursoId . "&msg=" . $msg);
// exit;