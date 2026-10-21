<!-- Valida los datos en el servidor: nombre obligatorio, estado dentro de la lista permitida, fechas válidas y fecha de fin no anterior a la de inicio. -->
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

// Convierte "" en NULL y valida el formato AAAA-MM-DD. Devuelve false si es inválida.
function fechaValida(string $valor) {
    if ($valor === "") {
        return null;
    }
    $d = DateTime::createFromFormat("Y-m-d", $valor);
    return ($d && $d->format("Y-m-d") === $valor) ? $valor : false;
}

$accion = $_POST["accion"] ?? "";
$id     = (int) ($_POST["id"] ?? 0);
$msg    = "error";

if ($accion === "guardar") {

    $nombre      = trim($_POST["nombre"] ?? "");
    $descripcion = trim($_POST["descripcion"] ?? "");
    $estado      = $_POST["estado"] ?? "borrador";
    $inicio      = fechaValida(trim($_POST["fecha_inicio"] ?? ""));
    $fin         = fechaValida(trim($_POST["fecha_fin"] ?? ""));

    $estadosPermitidos = ["borrador", "activo", "finalizado"];

    if ($nombre === "" || mb_strlen($nombre) > 150
        || !in_array($estado, $estadosPermitidos, true)
        || $inicio === false || $fin === false
        || ($inicio !== null && $fin !== null && $fin < $inicio)) {

        $msg = "datos_invalidos";

    } elseif ($id > 0) {

        $stmt = $conexion->prepare(
            "UPDATE cursos
             SET nombre = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, estado = ?
             WHERE id = ?"
        );
        $stmt->bind_param("sssssi", $nombre, $descripcion, $inicio, $fin, $estado, $id);
        $msg = $stmt->execute() ? "actualizado" : "error";
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

    $stmt = $conexion->prepare("DELETE FROM cursos WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $msg = $stmt->affected_rows > 0 ? "eliminado" : "error";
    $stmt->close();
}

header("Location: admin.php?seccion=cursos&msg=" . $msg);
exit;