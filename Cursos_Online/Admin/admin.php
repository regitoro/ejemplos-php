<?php
session_start();
require_once __DIR__ . "/../conexion.php";
require_once __DIR__ . "/../permisos.php";
require_once __DIR__ . "/../ui_icons.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: ../login.php");
    exit;
}

$conexion = conectar();
sincronizarRol($conexion);

// Solo el admin puede entrar aquí (si cambió de rol, mainWindow lo manda a su panel)
if (strtolower($_SESSION["rol"] ?? "") !== "admin") {
    header("Location: ../mainWindow.php");
    exit;
}

$enRevision = (int) $conexion->query(
    "SELECT COUNT(*) AS n FROM cursos WHERE aprobacion = 'en_revision'"
)->fetch_assoc()["n"];
$usuariosPendientes = (int) $conexion->query(
    "SELECT COUNT(*) AS n FROM usuarios WHERE aceptado = 0"
)->fetch_assoc()["n"];
$solicitudesInscripcionPendientes = (int) $conexion->query(
    "SELECT COUNT(*) AS n FROM solicitudes_inscripcion WHERE estado = 'pendiente'"
)->fetch_assoc()["n"];
$stmt = $conexion->prepare(
    "SELECT COUNT(*) AS n FROM entregas_ejercicios ee
     INNER JOIN ejercicios e ON e.id = ee.ejercicio_id
    WHERE ee.puntuacion IS NULL AND e.tipo = 'abierta'"
);
$stmt->execute();
$ejerciciosPendientes = (int) $stmt->get_result()->fetch_assoc()["n"];
$stmt->close();

// Secciones permitidas (lista blanca) con su texto e ícono
$secciones = [
    "inicio"     => ["Inicio",     "inicio"],
    "cursos"     => ["Cursos",     "cursos"],
    "calificar_ejercicios" => ["Calificar ejercicios", "tareas"],
    "revision"      => ["Revisión de cursos", "revision"],
    "inscripciones" => ["Inscripciones", "fecha"],
    "usuarios"   => ["Usuarios",   "usuarios"],
    "perfil"     => ["Perfil",     "perfil"],
];

$seccion = $_GET["seccion"] ?? "inicio";
if (!isset($secciones[$seccion])) {
    $seccion = "inicio";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel de administración</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="layout">

    <aside class="sidebar">
        <div class="logo">Cursos Online</div>

        <nav class="menu-lateral">
            <?php foreach ($secciones as $clave => [$texto, $icono]): ?>
                <a href="admin.php?seccion=<?php echo $clave; ?>"
                    class="<?php echo $clave === $seccion ? 'activo' : ''; ?>">
                    <span class="icono"><?php echo iconoInterfaz($icono); ?></span>
                    <?php echo $texto; ?>
                    <?php if (($clave === "usuarios" && $usuariosPendientes > 0)
                        || ($clave === "inscripciones" && $solicitudesInscripcionPendientes > 0)
                        || ($clave === "revision" && $enRevision > 0)
                        || ($clave === "calificar_ejercicios" && $ejerciciosPendientes > 0)): ?>
                        <span class="indicador-pendiente" aria-label="Hay tareas pendientes" title="Hay tareas pendientes"></span>
                    <?php endif; ?>
                    <?php if ($clave === "revision" && $enRevision > 0): ?>
                        <span class="insignia"><?php echo $enRevision; ?></span>
                    <?php endif; ?>
                    <?php if ($clave === "usuarios" && $usuariosPendientes > 0): ?>
                        <span class="insignia"><?php echo $usuariosPendientes; ?></span>
                    <?php endif; ?>
                    <?php if ($clave === "inscripciones" && $solicitudesInscripcionPendientes > 0): ?>
                        <span class="insignia"><?php echo $solicitudesInscripcionPendientes; ?></span>
                    <?php endif; ?>
                    <?php if ($clave === "calificar_ejercicios" && $ejerciciosPendientes > 0): ?>
                        <span class="insignia"><?php echo $ejerciciosPendientes; ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-pie">
            <span class="admin-nombre"><?php echo htmlspecialchars($_SESSION["nombre"] ?? "Admin"); ?></span>
            <a href="../logout.php" class="salir">Cerrar sesión</a>
        </div>
    </aside>

    <main class="contenido">
        <h1><?php echo $secciones[$seccion][0]; ?></h1>
        <?php include __DIR__ . "/secciones/" . $seccion . ".php"; ?>
    </main>

</div>
</body>
</html>