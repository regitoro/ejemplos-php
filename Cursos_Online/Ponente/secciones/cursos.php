<?php
require_once __DIR__ . "/../../permisos.php";

$panel  = "ponente.php";
$ponId  = (int) $_SESSION["usuario_id"];

// Pantalla de un curso: reutiliza la del admin/director (ahí se ocultan las partes que no son del ponente)
if (isset($_GET["gestionar"])) {
    include __DIR__ . "/../../Admin/secciones/curso_detalle.php";
    return;
}

$mensajes = [
    "sin_permiso" => ["error", "No puedes editar ese curso: no estás asignado o está en revisión."],
    "error"       => ["error", "No se pudo completar la acción."],
];
$msg = $_GET["msg"] ?? "";

$stmt = $conexion->prepare(
    "SELECT c.id, c.nombre, c.estado, c.aprobacion, c.fecha_inicio, c.fecha_fin,
            (SELECT u.nombre FROM usuarios u WHERE u.id = c.director_id) AS director,
            (SELECT COUNT(*) FROM inscripciones i WHERE i.curso_id = c.id) AS alumnos,
            (SELECT COUNT(*) FROM bloques b WHERE b.curso_id = c.id) AS bloques
     FROM cursos c
     INNER JOIN curso_ponentes cp ON cp.curso_id = c.id
     WHERE cp.usuario_id = ?
     ORDER BY c.nombre"
);
$stmt->bind_param("i", $ponId);
$stmt->execute();
$cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<?php if (isset($mensajes[$msg])): ?>
    <div class="aviso-admin <?php echo $mensajes[$msg][0]; ?>">
        <?php echo htmlspecialchars($mensajes[$msg][1]); ?>
    </div>
<?php endif; ?>

<div class="tarjeta">
    <?php if (count($cursos) === 0): ?>
        <p>Todavía no tienes cursos asignados.</p>
    <?php else: ?>
        <div class="tabla-scroll">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Curso</th>
                    <th>Estado</th>
                    <th>Director</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                    <th>Bloques</th>
                    <th>Alumnos</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cursos as $c): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($c["nombre"]); ?>
                        <?php if ($c["aprobacion"] === "en_revision"): ?>
                            <span class="etiqueta rev-en_revision">En revisión</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="etiqueta estado-<?php echo htmlspecialchars($c["estado"]); ?>">
                            <?php echo htmlspecialchars($c["estado"]); ?>
                        </span>
                    </td>
                    <td><?php echo $c["director"] ? htmlspecialchars($c["director"]) : "—"; ?></td>
                    <td><?php echo $c["fecha_inicio"] ? date("d/m/Y", strtotime($c["fecha_inicio"])) : "—"; ?></td>
                    <td><?php echo $c["fecha_fin"] ? date("d/m/Y", strtotime($c["fecha_fin"])) : "—"; ?></td>
                    <td><?php echo (int) $c["bloques"]; ?></td>
                    <td><?php echo (int) $c["alumnos"]; ?></td>
                    <td>
                        <a href="ponente.php?seccion=cursos&gestionar=<?php echo (int) $c["id"]; ?>"
                           class="btn-principal">Gestionar contenido</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>