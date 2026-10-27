<?php
session_start();
require_once "conexion.php";

$rol = strtolower($_SESSION["rol"] ?? "");

if (!isset($_SESSION["usuario_id"]) || !in_array($rol, ["admin", "director"], true)
    || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$conexion = conectar();

// Alcance: el director solo toca sus cursos; el admin (0) todos
$dirId = $rol === "director" ? (int) $_SESSION["usuario_id"] : 0;

$accion  = $_POST["accion"] ?? "";
// Si la acción vino de la pantalla de un curso, se vuelve a esa pantalla
$volverACurso = ($_POST["volver"] ?? "") === "curso";
$cursoId = 0;      // curso al que se regresa en el filtro
$msg     = "error";

if ($accion === "inscribir") {

    $cursoId = (int) ($_POST["curso_id"] ?? 0);

    // El curso debe existir y estar dentro del alcance
    $stmt = $conexion->prepare(
        "SELECT id FROM cursos WHERE id = ? AND (? = 0 OR director_id = ?)"
    );
    $stmt->bind_param("iii", $cursoId, $dirId, $dirId);
    $stmt->execute();
    $permitido = $stmt->get_result()->num_rows === 1;
    $stmt->close();

    if ($permitido) {
        $ids = $_POST["usuario_ids"] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }

        // Solo entra si el usuario es un alumno aprobado; INSERT IGNORE evita duplicados
        $ins = $conexion->prepare(
            "INSERT IGNORE INTO inscripciones (curso_id, usuario_id)
             SELECT ?, u.id
             FROM usuarios u
             INNER JOIN roles r ON u.rol_id = r.id
             WHERE u.id = ? AND r.nombre = 'alumno' AND u.aceptado = 1"
        );
        // Si tenía una solicitud pendiente de ese curso, ya no hace falta
        $del = $conexion->prepare(
            "DELETE FROM solicitudes_inscripcion WHERE curso_id = ? AND usuario_id = ?"
        );

        $nuevas = 0;
        foreach ($ids as $idCrudo) {
            $usuarioId = (int) $idCrudo;
            if ($usuarioId <= 0) continue;

            $ins->bind_param("ii", $cursoId, $usuarioId);
            $ins->execute();
            if ($ins->affected_rows > 0) {
                $nuevas++;
                $del->bind_param("ii", $cursoId, $usuarioId);
                $del->execute();
            }
        }
        $ins->close();
        $del->close();

        $msg = $nuevas > 0 ? "insc_creadas" : "insc_nada";
    }

} elseif ($accion === "quitar") {

    $inscripcionId = (int) ($_POST["inscripcion_id"] ?? 0);
    $cursoId       = (int) ($_POST["curso_filtro"] ?? 0);   // solo para conservar el filtro

    // El progreso se borra solo (ON DELETE CASCADE)
    $stmt = $conexion->prepare(
        "DELETE i FROM inscripciones i
         INNER JOIN cursos c ON i.curso_id = c.id
         WHERE i.id = ? AND (? = 0 OR c.director_id = ?)"
    );
    $stmt->bind_param("iii", $inscripcionId, $dirId, $dirId);
    $stmt->execute();
    $msg = $stmt->affected_rows > 0 ? "insc_quitada" : "error";
    $stmt->close();

} elseif ($accion === "aprobar" || $accion === "rechazar") {

    $solicitudId = (int) ($_POST["solicitud_id"] ?? 0);
    $cursoId     = (int) ($_POST["curso_filtro"] ?? 0);   // solo para conservar el filtro

    // La solicitud debe seguir pendiente y ser de un curso dentro del alcance
    $stmt = $conexion->prepare(
        "SELECT s.curso_id, s.usuario_id
         FROM solicitudes_inscripcion s
         INNER JOIN cursos c ON s.curso_id = c.id
         WHERE s.id = ? AND s.estado = 'pendiente' AND (? = 0 OR c.director_id = ?)"
    );
    $stmt->bind_param("iii", $solicitudId, $dirId, $dirId);
    $stmt->execute();
    $sol = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($sol && $accion === "rechazar") {

        $stmt = $conexion->prepare(
            "UPDATE solicitudes_inscripcion SET estado = 'rechazada' WHERE id = ?"
        );
        $stmt->bind_param("i", $solicitudId);
        $stmt->execute();
        $stmt->close();
        $msg = "solicitud_rechazada";

    } elseif ($sol) {

        $sCurso = (int) $sol["curso_id"];
        $sUser  = (int) $sol["usuario_id"];

        // Inscribir solo si sigue siendo un alumno aprobado
        $stmt = $conexion->prepare(
            "INSERT IGNORE INTO inscripciones (curso_id, usuario_id)
             SELECT ?, u.id
             FROM usuarios u
             INNER JOIN roles r ON u.rol_id = r.id
             WHERE u.id = ? AND r.nombre = 'alumno' AND u.aceptado = 1"
        );
        $stmt->bind_param("ii", $sCurso, $sUser);
        $stmt->execute();
        $stmt->close();

        // Comprobar que quedó inscrito (nuevo o ya lo estaba)
        $stmt = $conexion->prepare(
            "SELECT id FROM inscripciones WHERE curso_id = ? AND usuario_id = ?"
        );
        $stmt->bind_param("ii", $sCurso, $sUser);
        $stmt->execute();
        $inscrito = $stmt->get_result()->num_rows === 1;
        $stmt->close();

        if ($inscrito) {
            $stmt = $conexion->prepare("DELETE FROM solicitudes_inscripcion WHERE id = ?");
            $stmt->bind_param("i", $solicitudId);
            $stmt->execute();
            $stmt->close();
            $msg = "solicitud_aprobada";
        }
    } else {
        $msg = "solicitud_invalida";
    }
}

// Volver al panel que corresponda
$panelRaiz = $rol === "admin" ? "Admin/admin.php" : "Director/director.php";

if ($volverACurso && $cursoId > 0) {
    // Se inscribió o quitó desde la pantalla del curso: se queda ahí
    $destino = $panelRaiz . "?seccion=cursos&gestionar=" . $cursoId;
}  else {
    $destino = $panelRaiz . "?seccion=inscripciones";
    if ($cursoId > 0) {
        $destino .= "&curso=" . $cursoId;
    }

    // Conservar el orden y la búsqueda que tenía la persona
    $ordenesOk = ["fecha_desc", "fecha_asc", "curso", "alumno", "progreso_desc", "progreso_asc"];
    $ordenPost = $_POST["orden"] ?? "";
    if (in_array($ordenPost, $ordenesOk, true)) {
        $destino .= "&orden=" . $ordenPost;
    }

    $qPost = trim($_POST["q"] ?? "");
    if (strlen($qPost) > 100) {
        $qPost = substr($qPost, 0, 100);
    }
    if ($qPost !== "") {
        $destino .= "&q=" . urlencode($qPost);
    }
}

header("Location: " . $destino . "&msg=" . $msg);
exit;


