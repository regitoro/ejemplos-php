<?php
session_start();
require_once __DIR__ . "/../conexion.php";
require_once __DIR__ . "/../permisos.php";

$rol = strtolower($_SESSION["rol"] ?? "");
$uid = (int) ($_SESSION["usuario_id"] ?? 0);

// Admin, director o ponente, y solo por POST
// (cada uno solo puede tocar los cursos que puedeEditarCurso() le permite)
if ($uid === 0 || !in_array($rol, ["admin", "director", "ponente"], true)
    || $_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../login.php");
    exit;
}

$conexion = conectar();
$panel    = ["admin" => "admin.php", "director" => "director.php", "ponente" => "ponente.php"][$rol];


// ---------- Funciones auxiliares ----------

// Ejecuta una consulta preparada y devuelve las filas afectadas
function ejecutar(mysqli $c, string $sql, string $tipos, ...$params): int {
    $stmt = $c->prepare($sql);
    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}

function existeCurso(mysqli $c, int $id): bool {
    global $rol, $uid;
    return puedeEditarCurso($c, $id, $rol, $uid);
}

// Devuelve el curso al que pertenece un bloque (0 si no existe o no tiene permiso)
function cursoDeBloque(mysqli $c, int $id): int {
    global $rol, $uid;
    $stmt = $c->prepare("SELECT curso_id FROM bloques WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $cursoId = (int) ($fila["curso_id"] ?? 0);
    return puedeEditarCurso($c, $cursoId, $rol, $uid) ? $cursoId : 0;
}

// Devuelve el curso al que pertenece un subbloque (0 si no existe o no tiene permiso)
function cursoDeSubbloque(mysqli $c, int $id): int {
    global $rol, $uid;
    $stmt = $c->prepare(
        "SELECT b.curso_id FROM subbloques s
         INNER JOIN bloques b ON s.bloque_id = b.id
         WHERE s.id = ?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $cursoId = (int) ($fila["curso_id"] ?? 0);
    return puedeEditarCurso($c, $cursoId, $rol, $uid) ? $cursoId : 0;
}

// Siguiente número de orden dentro de un padre (solo se llama con nombres fijos)
function siguienteOrden(mysqli $c, string $tabla, string $colPadre, int $padreId): int {
    $stmt = $c->prepare("SELECT COALESCE(MAX(orden), 0) + 1 AS n FROM $tabla WHERE $colPadre = ?");
    $stmt->bind_param("i", $padreId);
    $stmt->execute();
    $n = (int) $stmt->get_result()->fetch_assoc()["n"];
    $stmt->close();
    return $n;
}

// Sube o baja un elemento y renumera a sus hermanos (1, 2, 3...)
function mover(mysqli $c, string $tabla, string $colPadre, int $id, string $dir): void {
    $stmt = $c->prepare("SELECT $colPadre AS padre FROM $tabla WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$fila) return;

    $stmt = $c->prepare("SELECT id FROM $tabla WHERE $colPadre = ? ORDER BY orden, id");
    $stmt->bind_param("i", $fila["padre"]);
    $stmt->execute();
    $ids = array_map("intval", array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), "id"));
    $stmt->close();

    $pos   = array_search($id, $ids, true);
    $nueva = $dir === "arriba" ? $pos - 1 : $pos + 1;
    if ($pos === false || $nueva < 0 || $nueva >= count($ids)) return;

    [$ids[$pos], $ids[$nueva]] = [$ids[$nueva], $ids[$pos]];

    foreach ($ids as $i => $idElemento) {
        ejecutar($c, "UPDATE $tabla SET orden = ? WHERE id = ?", "ii", $i + 1, $idElemento);
    }
}

// ---------- Procesar la acción ----------

$accion    = $_POST["accion"] ?? "";
$id        = (int) ($_POST["id"] ?? 0);
$titulo    = trim($_POST["titulo"] ?? "");
$contenido = trim($_POST["contenido"] ?? "");
$tituloOk  = $titulo !== "" && strlen($titulo) <= 150;

$cursoId = 0;
$msg     = "error";

switch ($accion) {

    case "crear_bloque":
        $cursoId = (int) ($_POST["curso_id"] ?? 0);
        if (!existeCurso($conexion, $cursoId)) break;
        if (!$tituloOk) { $msg = "datos_invalidos"; break; }
        $orden = siguienteOrden($conexion, "bloques", "curso_id", $cursoId);
        ejecutar($conexion, "INSERT INTO bloques (curso_id, titulo, orden) VALUES (?, ?, ?)",
                 "isi", $cursoId, $titulo, $orden);
        $msg = "bloque_creado";
        break;

    case "editar_bloque":
        $cursoId = cursoDeBloque($conexion, $id);
        if (!$cursoId) break;
        if (!$tituloOk) { $msg = "datos_invalidos"; break; }
        ejecutar($conexion, "UPDATE bloques SET titulo = ? WHERE id = ?", "si", $titulo, $id);
        $msg = "bloque_actualizado";
        break;

    case "eliminar_bloque":
        $cursoId = cursoDeBloque($conexion, $id);
        if (!$cursoId) break;
        ejecutar($conexion, "DELETE FROM bloques WHERE id = ?", "i", $id);
        $msg = "bloque_eliminado";
        break;

    case "crear_subbloque":
        $bloqueId = (int) ($_POST["bloque_id"] ?? 0);
        $cursoId  = cursoDeBloque($conexion, $bloqueId);
        if (!$cursoId) break;
        if (!$tituloOk) { $msg = "datos_invalidos"; break; }
        $orden = siguienteOrden($conexion, "subbloques", "bloque_id", $bloqueId);
        ejecutar($conexion,
                 "INSERT INTO subbloques (bloque_id, titulo, orden) VALUES (?, ?, ?)",
                 "isi", $bloqueId, $titulo, $orden);
        $msg = "sub_creado";
        break;

    case "editar_subbloque":
        $cursoId = cursoDeSubbloque($conexion, $id);
        if (!$cursoId) break;
        if (!$tituloOk) { $msg = "datos_invalidos"; break; }
        ejecutar($conexion, "UPDATE subbloques SET titulo = ? WHERE id = ?",
                 "si", $titulo, $id);
        $msg = "sub_actualizado";
        break;

    case "eliminar_subbloque":
        $cursoId = cursoDeSubbloque($conexion, $id);
        if (!$cursoId) break;
        ejecutar($conexion, "DELETE FROM subbloques WHERE id = ?", "i", $id);
        $msg = "sub_eliminado";
        break;

    case "mover":
        $tipo = $_POST["tipo"] ?? "";
        $dir  = $_POST["dir"] ?? "";
        if (!in_array($dir, ["arriba", "abajo"], true)) break;

        if ($tipo === "bloque") {
            $cursoId = cursoDeBloque($conexion, $id);
            if ($cursoId) { mover($conexion, "bloques", "curso_id", $id, $dir); $msg = "movido"; }
        } elseif ($tipo === "subbloque") {
            $cursoId = cursoDeSubbloque($conexion, $id);
            if ($cursoId) { mover($conexion, "subbloques", "bloque_id", $id, $dir); $msg = "movido"; }
        }
        break;
}

$destino = $cursoId > 0
    ? $panel . "?seccion=cursos&gestionar=" . $cursoId
    : $panel . "?seccion=cursos";

header("Location: " . $destino . "&msg=" . $msg);
exit;