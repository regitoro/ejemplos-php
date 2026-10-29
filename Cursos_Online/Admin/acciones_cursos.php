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

// Convierte "" en NULL y valida el formato AAAA-MM-DD. Devuelve false si es inválida.
function fechaValida(string $valor) {
    if ($valor === "") {
        return null;
    }
    $d = DateTime::createFromFormat("Y-m-d", $valor);
    return ($d && $d->format("Y-m-d") === $valor) ? $valor : false;
}

/** Devuelve el primer requisito faltante para habilitar revisión o publicación. */
function requisitoCursoPendiente(mysqli $conexion, int $cursoId, bool $requiereDirector): ?string
{
    $stmt = $conexion->prepare(
        "SELECT c.director_id,
                (SELECT COUNT(*) FROM bloques b WHERE b.curso_id = c.id) AS bloques,
                (SELECT COUNT(*) FROM subbloques s
                 INNER JOIN bloques b ON b.id = s.bloque_id
                 WHERE b.curso_id = c.id) AS lecciones,
                (SELECT COUNT(*) FROM curso_ponentes cp WHERE cp.curso_id = c.id) AS ponentes
         FROM cursos c WHERE c.id = ?"
    );
    $stmt->bind_param("i", $cursoId);
    $stmt->execute();
    $curso = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$curso || (int) $curso["bloques"] < 1) return "requisito_bloques";
    if ((int) $curso["lecciones"] < 1) return "requisito_lecciones";
    if ((int) $curso["ponentes"] < 1) return "requisito_ponentes";
    if ($requiereDirector && empty($curso["director_id"])) return "requisito_director";
    return null;
}

$accion    = $_POST["accion"] ?? "";
$id        = (int) ($_POST["id"] ?? 0);
$msg       = "error";
$irAlCurso = 0;      // si es > 0, se vuelve a la pantalla de gestión de ese curso

if ($accion === "guardar") {

    $nombre      = trim($_POST["nombre"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");
    $estado      = $_POST["estado"] ?? "borrador";
    $inicio      = fechaValida(trim($_POST["fecha_inicio"] ?? ""));
    $fin         = fechaValida(trim($_POST["fecha_fin"] ?? ""));

    $estadosPermitidos = ["borrador", "activo", "finalizado"];

    if ($nombre === "" || strlen($nombre) > 150
        || !in_array($estado, $estadosPermitidos, true)
        || $inicio === false || $fin === false
        || ($inicio !== null && $fin !== null && $fin < $inicio)) {

        $msg = "datos_invalidos";

    } elseif ($id > 0) {

        if (!puedeEditarCurso($conexion, $id, $rol, $uid)) {
            $msg = "sin_permiso";
        } elseif ($rol === "director" && $estado !== "borrador" && !cursoAprobado($conexion, $id)) {
            // Un director no puede sacar de borrador un curso que no está aprobado
            $msg = "no_aprobado";
        } elseif ($estado === "activo" && ($pendiente = requisitoCursoPendiente($conexion, $id, true)) !== null) {
            $msg = $pendiente;
        } else {
            $stmt = $conexion->prepare(
                "UPDATE cursos
                      SET nombre = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, estado = ?
                 WHERE id = ?"
            );
                  $stmt->bind_param("sssssi", $nombre, $descripcion, $inicio, $fin, $estado, $id);
            $msg = $stmt->execute() ? "actualizado" : "error";
            $stmt->close();
        }

    } elseif ($estado !== "borrador") {

        // Los cursos nuevos siempre se crean como borrador; primero hay que crear y asignar su contenido.
        $msg = "curso_borrador_requerido";

    } elseif ($rol === "director") {

        // El curso nace en borrador, asignado al director y sin enviar a revisión
        $stmt = $conexion->prepare(
            "INSERT INTO cursos
                 (nombre, descripcion, fecha_inicio, fecha_fin, estado, director_id, aprobacion)
             VALUES (?, ?, ?, ?, 'borrador', ?, 'sin_enviar')"
        );
        $stmt->bind_param("ssssi", $nombre, $descripcion, $inicio, $fin, $uid);
        if ($stmt->execute()) {
            $msg       = "creado";
            $irAlCurso = $stmt->insert_id;   // para que agregue bloques enseguida
        }
        $stmt->close();

    } else {

        $stmt = $conexion->prepare(
            "INSERT INTO cursos (nombre, descripcion, fecha_inicio, fecha_fin, estado)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssss", $nombre, $descripcion, $inicio, $fin, $estado);
        $msg = $stmt->execute() ? "creado" : "error";
        $stmt->close();
    }

} elseif ($accion === "eliminar" && $id > 0) {

    if (!puedeEditarCurso($conexion, $id, $rol, $uid)) {
        $msg = "sin_permiso";
    } else {
        // El admin borra cualquiera; el director solo cursos en borrador
        $esAdmin = $rol === "admin" ? 1 : 0;
        $stmt = $conexion->prepare(
            "DELETE FROM cursos WHERE id = ? AND (? = 1 OR estado = 'borrador')"
        );
        $stmt->bind_param("ii", $id, $esAdmin);
        $stmt->execute();
        $msg = $stmt->affected_rows > 0 ? "eliminado" : "no_eliminar";
        $stmt->close();
    }

} elseif ($accion === "enviar_revision" && $id > 0) {

    $irAlCurso = $id;

    if ($rol !== "director" || !puedeEditarCurso($conexion, $id, $rol, $uid)) {
        $msg = "sin_permiso";
    } else {
        $pendiente = requisitoCursoPendiente($conexion, $id, false);
        if ($pendiente !== null) {
            $msg = $pendiente;
        } else {
            $stmt = $conexion->prepare(
                "UPDATE cursos
                SET aprobacion = 'en_revision', revision_comentario = NULL, enviado_en = NOW()
                WHERE id = ? AND director_id = ?
                AND aprobacion IN ('sin_enviar', 'rechazado')"
            );
            $stmt->bind_param("ii", $id, $uid);
            $stmt->execute();
            $msg = $stmt->affected_rows > 0 ? "enviado_revision" : "error";
            $stmt->close();
        }
    }

} elseif ($accion === "retirar_revision" && $id > 0) {

    $irAlCurso = $id;

    if ($rol !== "director") {
        $msg = "sin_permiso";
    } else {
        $stmt = $conexion->prepare(
            "UPDATE cursos SET aprobacion = 'sin_enviar'
             WHERE id = ? AND director_id = ? AND aprobacion = 'en_revision'"
        );
        $stmt->bind_param("ii", $id, $uid);
        $stmt->execute();
        $msg = $stmt->affected_rows > 0 ? "revision_retirada" : "error";
        $stmt->close();
    }

} elseif ($accion === "publicar" && $id > 0) {

    $irAlCurso = $id;

    if (!puedeEditarCurso($conexion, $id, $rol, $uid)) {
        $msg = "sin_permiso";
    } elseif (($pendiente = requisitoCursoPendiente($conexion, $id, true)) !== null) {
        $msg = $pendiente;
    } else {
        // Solo se publica un curso aprobado que siga en borrador
        $stmt = $conexion->prepare(
            "UPDATE cursos SET estado = 'activo'
             WHERE id = ? AND aprobacion = 'aprobado' AND estado = 'borrador'"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $msg = $stmt->affected_rows > 0 ? "publicado" : "no_aprobado";
        $stmt->close();
    }
}

$destino = $panel . "?seccion=cursos";
if ($irAlCurso > 0) {
    $destino .= "&gestionar=" . $irAlCurso;
}

header("Location: " . $destino . "&msg=" . $msg);
exit;