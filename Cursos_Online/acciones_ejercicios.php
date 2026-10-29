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
    $tipo = $_POST["tipo"] ?? "abierta";
    $bloqueId = (int) ($_POST["bloque_id"] ?? 0);
    $opcionesEnviadas = $_POST["opciones"] ?? [];
    $opciones = is_array($opcionesEnviadas) ? array_map("trim", $opcionesEnviadas) : [];
    $correcta = filter_var($_POST["correcta"] ?? null, FILTER_VALIDATE_INT);
    $bloqueValido = false;
    if ($bloqueId > 0) {
        $stmt = $conexion->prepare("SELECT id FROM bloques WHERE id = ? AND curso_id = ?");
        $stmt->bind_param("ii", $bloqueId, $cursoId);
        $stmt->execute();
        $bloqueValido = $stmt->get_result()->num_rows === 1;
        $stmt->close();
    }
    $quizValido = $tipo !== "multiple"
        || (count($opciones) === 4 && !in_array("", $opciones, true)
            && max(array_map("strlen", $opciones)) <= 500 && $correcta !== false
            && $correcta >= 0 && $correcta < count($opciones));

    if ($titulo !== "" && strlen($titulo) <= 150 && $instrucciones !== ""
        && strlen($instrucciones) <= 20000 && $maximo !== false && $maximo > 0 && $maximo <= 1000
        && in_array($tipo, ["abierta", "multiple"], true) && $bloqueValido && $quizValido) {
        $conexion->begin_transaction();
        $stmt = $conexion->prepare(
            "INSERT INTO ejercicios (curso_id, bloque_id, tipo, creado_por, titulo, instrucciones, puntuacion_maxima)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iisissd", $cursoId, $bloqueId, $tipo, $uid, $titulo, $instrucciones, $maximo);
        $ok = $stmt->execute();
        $nuevoEjercicioId = $stmt->insert_id;
        $stmt->close();
        if ($ok && $tipo === "multiple") {
            $stmt = $conexion->prepare(
                "INSERT INTO opciones_ejercicio (ejercicio_id, texto, es_correcta, orden) VALUES (?, ?, ?, ?)"
            );
            foreach ($opciones as $indice => $textoOpcion) {
                $esCorrecta = (int) ($indice === $correcta);
                $orden = $indice + 1;
                $stmt->bind_param("isii", $nuevoEjercicioId, $textoOpcion, $esCorrecta, $orden);
                $ok = $stmt->execute() && $ok;
            }
            $stmt->close();
        }
        if ($ok) {
            $conexion->commit();
            $msg = "ejercicio_creado";
        } else {
            $conexion->rollback();
        }
    } else {
        $msg = "ejercicio_invalido";
    }

} elseif ($accion === "editar" && $ejercicioId > 0) {
    $titulo = trim($_POST["titulo"] ?? "");
    $instrucciones = trim($_POST["instrucciones"] ?? "");
    $maximo = filter_var($_POST["puntuacion_maxima"] ?? null, FILTER_VALIDATE_FLOAT);
    $tipo = $_POST["tipo"] ?? "abierta";
    $bloqueId = (int) ($_POST["bloque_id"] ?? 0);
    $opcionesEnviadas = $_POST["opciones"] ?? [];
    $opciones = is_array($opcionesEnviadas) ? array_map("trim", $opcionesEnviadas) : [];
    $correcta = filter_var($_POST["correcta"] ?? null, FILTER_VALIDATE_INT);
    $bloqueValido = false;
    if ($bloqueId > 0) {
        $stmt = $conexion->prepare("SELECT id FROM bloques WHERE id = ? AND curso_id = ?");
        $stmt->bind_param("ii", $bloqueId, $cursoId);
        $stmt->execute();
        $bloqueValido = $stmt->get_result()->num_rows === 1;
        $stmt->close();
    }
    $quizValido = $tipo !== "multiple"
        || (count($opciones) === 4 && !in_array("", $opciones, true)
            && max(array_map("strlen", $opciones)) <= 500 && $correcta !== false
            && $correcta >= 0 && $correcta < count($opciones));
    $stmt = $conexion->prepare(
        "SELECT COUNT(*) AS n FROM entregas_ejercicios WHERE ejercicio_id = ?"
    );
    $stmt->bind_param("i", $ejercicioId);
    $stmt->execute();
    $tieneEntregas = (int) $stmt->get_result()->fetch_assoc()["n"] > 0;
    $stmt->close();

    if ($titulo === "" || strlen($titulo) > 150 || $instrucciones === "" || strlen($instrucciones) > 20000
        || $maximo === false || $maximo <= 0 || $maximo > 1000
        || !in_array($tipo, ["abierta", "multiple"], true) || !$bloqueValido || !$quizValido) {
        $msg = "ejercicio_invalido";
    } elseif ($tieneEntregas) {
        $msg = "ejercicio_con_entregas";
    } else {
        $conexion->begin_transaction();
        $stmt = $conexion->prepare(
            "UPDATE ejercicios SET bloque_id = ?, tipo = ?, titulo = ?, instrucciones = ?, puntuacion_maxima = ?
             WHERE id = ? AND curso_id = ?"
        );
        $stmt->bind_param("isssdii", $bloqueId, $tipo, $titulo, $instrucciones, $maximo, $ejercicioId, $cursoId);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            $stmt = $conexion->prepare("DELETE FROM opciones_ejercicio WHERE ejercicio_id = ?");
            $stmt->bind_param("i", $ejercicioId);
            $ok = $stmt->execute();
            $stmt->close();
        }
        if ($ok && $tipo === "multiple") {
            $stmt = $conexion->prepare(
                "INSERT INTO opciones_ejercicio (ejercicio_id, texto, es_correcta, orden) VALUES (?, ?, ?, ?)"
            );
            foreach ($opciones as $indice => $textoOpcion) {
                $esCorrecta = (int) ($indice === $correcta);
                $orden = $indice + 1;
                $stmt->bind_param("isii", $ejercicioId, $textoOpcion, $esCorrecta, $orden);
                $ok = $stmt->execute() && $ok;
            }
            $stmt->close();
        }
        if ($ok) {
            $conexion->commit();
            $msg = "ejercicio_actualizado";
        } else {
            $conexion->rollback();
        }
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
