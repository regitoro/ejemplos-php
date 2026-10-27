<?php
session_start();
require_once "conexion.php";

// Solo el admin, y solo por POST
if (!isset($_SESSION["usuario_id"]) || strtolower($_SESSION["rol"] ?? "") !== "admin"
    || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit;
}

$conexion = conectar();
$yo       = (int) $_SESSION["usuario_id"];

$id      = (int) ($_POST["id"] ?? 0);
$accion  = $_POST["accion"] ?? "";
$msg     = "error";
$verUsuario = 0;     // si es > 0, se vuelve al detalle de ese usuario

// Ejecuta una consulta preparada y devuelve true si salió bien
function ejecutarSql(mysqli $c, string $sql, string $tipos, ...$params): bool
{
    $stmt = $c->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param($tipos, ...$params);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// Devuelve el nombre del rol (minúsculas) o null si el id no existe
function nombreDeRol(mysqli $c, int $rolId): ?string
{
    $stmt = $c->prepare("SELECT nombre FROM roles WHERE id = ?");
    $stmt->bind_param("i", $rolId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $fila ? strtolower($fila["nombre"]) : null;
}

if ($accion === "crear") {

    $nombre   = trim($_POST["nombre"] ?? "");
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $rolId    = (int) ($_POST["rol_id"] ?? 0);

    if ($nombre === "" || strlen($nombre) > 100
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150
        || strlen($password) < 8
        || nombreDeRol($conexion, $rolId) === null) {

        $msg = "usuario_invalido";

    } else {

        // El correo no debe estar registrado
        $stmt = $conexion->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $repetido = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($repetido) {
            $msg = "email_repetido";
        } else {
            // Aprobado y con el correo ya verificado: puede entrar de inmediato
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare(
                "INSERT INTO usuarios
                     (nombre, email, password, rol_id, aceptado, email_verificado, token_verificacion)
                 VALUES (?, ?, ?, ?, 1, 1, NULL)"
            );
            $stmt->bind_param("sssi", $nombre, $email, $hash, $rolId);
            $msg = $stmt->execute() ? "usuario_creado" : "error";
            $stmt->close();
        }
    }

} elseif ($accion === "cambiar_rol" && $id > 0 && $id !== $yo) {

    $verUsuario = $id;
    $nuevoRolId = (int) ($_POST["rol_id"] ?? 0);
    $nuevoRol   = nombreDeRol($conexion, $nuevoRolId);

    // Rol actual del usuario
    $stmt = $conexion->prepare(
        "SELECT r.nombre AS rol
         FROM usuarios u INNER JOIN roles r ON u.rol_id = r.id
         WHERE u.id = ? AND u.aceptado = 1"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($nuevoRol === null || !$fila) {
        $msg = "error";
    } elseif (strtolower($fila["rol"]) === $nuevoRol) {
        $msg = "rol_igual";
    } else {
        $rolAnterior = strtolower($fila["rol"]);

        try {
            $conexion->begin_transaction();

            $ok = ejecutarSql($conexion, "UPDATE usuarios SET rol_id = ? WHERE id = ?",
                              "ii", $nuevoRolId, $id);

            // Limpiar lo que dependía del rol anterior
            if ($rolAnterior === "alumno") {
                $ok = $ok && ejecutarSql($conexion, "DELETE FROM inscripciones WHERE usuario_id = ?", "i", $id);
                $ok = $ok && ejecutarSql($conexion, "DELETE FROM solicitudes_inscripcion WHERE usuario_id = ?", "i", $id);
            } elseif ($rolAnterior === "ponente") {
                $ok = $ok && ejecutarSql($conexion, "DELETE FROM curso_ponentes WHERE usuario_id = ?", "i", $id);
            } elseif ($rolAnterior === "director") {
                $ok = $ok && ejecutarSql($conexion, "UPDATE cursos SET director_id = NULL WHERE director_id = ?", "i", $id);
            }

            if ($ok) {
                $conexion->commit();
                $msg = "rol_cambiado";
            } else {
                $conexion->rollback();
            }
        } catch (Throwable $e) {
            $conexion->rollback();
            $msg = "error";
        }
    }

} elseif ($id > 0 && $id !== $yo) {

    if ($accion === "aprobar") {
        $rol_id = (int) ($_POST["rol_id"] ?? 0);

        if (nombreDeRol($conexion, $rol_id) !== null) {
            $stmt = $conexion->prepare(
                "UPDATE usuarios SET aceptado = 1, rol_id = ? WHERE id = ? AND aceptado = 0"
            );
            $stmt->bind_param("ii", $rol_id, $id);
            $stmt->execute();
            $msg = $stmt->affected_rows > 0 ? "aprobado" : "error";
            $stmt->close();
        }

    } elseif ($accion === "rechazar") {
        // Solo se pueden rechazar cuentas pendientes
        $stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ? AND aceptado = 0");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $msg = $stmt->affected_rows > 0 ? "rechazado" : "error";
        $stmt->close();

    } elseif ($accion === "eliminar") {
        // Solo usuarios aprobados que NO sean admin.
        // Sus inscripciones, progreso y asignaciones como ponente se borran solos (CASCADE).
        $stmt = $conexion->prepare(
            "DELETE FROM usuarios
             WHERE id = ? AND aceptado = 1
               AND rol_id NOT IN (SELECT id FROM roles WHERE nombre = 'admin')"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $msg = $stmt->affected_rows > 0 ? "usuario_eliminado" : "error";
        $stmt->close();
    }
}

$destino = "Admin/admin.php?seccion=usuarios";
if ($verUsuario > 0) {
    $destino .= "&ver=" . $verUsuario;
}

header("Location: " . $destino . "&msg=" . $msg);
exit;