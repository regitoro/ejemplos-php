<?php
require_once __DIR__ . "/../../permisos.php";   // trae ETIQUETAS_REVISION

$dirId = (int) $_SESSION["usuario_id"];

// Cuenta filas con una consulta que recibe solo el id del director
function contarDirector(mysqli $c, string $sql, int $dirId): int
{
    $stmt = $c->prepare($sql);
    $stmt->bind_param("i", $dirId);
    $stmt->execute();
    $n = (int) $stmt->get_result()->fetch_assoc()["n"];
    $stmt->close();
    return $n;
}

// ---------- Tarjetas ----------
$totalAlumnos = contarDirector($conexion,
    "SELECT COUNT(DISTINCT i.usuario_id) AS n
     FROM inscripciones i
     INNER JOIN cursos c ON i.curso_id = c.id
     WHERE c.director_id = ?", $dirId);

$totalCursos = contarDirector($conexion,
    "SELECT COUNT(*) AS n FROM cursos WHERE director_id = ?", $dirId);

$cursosActivos = contarDirector($conexion,
    "SELECT COUNT(*) AS n FROM cursos WHERE director_id = ? AND estado = 'activo'", $dirId);

$solicitudes = contarDirector($conexion,
    "SELECT COUNT(*) AS n
     FROM solicitudes_inscripcion s
     INNER JOIN cursos c ON s.curso_id = c.id
     WHERE s.estado = 'pendiente' AND c.director_id = ?", $dirId);

// ---------- Cursos con alumnos y progreso ----------
$stmt = $conexion->prepare(
    "SELECT c.id, c.nombre, c.estado, c.aprobacion, c.revision_comentario,
            (SELECT COUNT(*) FROM inscripciones i
              WHERE i.curso_id = c.id) AS alumnos,
            (SELECT COUNT(*) FROM subbloques s
              INNER JOIN bloques b ON s.bloque_id = b.id
              WHERE b.curso_id = c.id) AS lecciones,
            (SELECT COUNT(*) FROM progreso_subbloques p
              INNER JOIN inscripciones i ON p.inscripcion_id = i.id
              WHERE i.curso_id = c.id) AS completadas
     FROM cursos c
     WHERE c.director_id = ?
     ORDER BY c.id DESC"
);
$stmt->bind_param("i", $dirId);
$stmt->execute();
$cursos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Para escalar las barras de la gráfica
$maxAlumnos = 1;
foreach ($cursos as $c) {
    $maxAlumnos = max($maxAlumnos, (int) $c["alumnos"]);
}

// ---------- Cursos que requieren atención ----------
$cambios      = array_filter($cursos, fn($c) => $c["aprobacion"] === "rechazado");
$porPublicar  = array_filter($cursos, fn($c) => $c["aprobacion"] === "aprobado" && $c["estado"] === "borrador");
$enRevision   = array_filter($cursos, fn($c) => $c["aprobacion"] === "en_revision");
$hayAtencion  = count($cambios) + count($porPublicar) + count($enRevision) > 0;
?>

<!-- Tarjetas -->
<div class="resumen">
    <a class="tarjeta dato enlace-tarjeta" href="director.php?seccion=usuarios">
        <div class="tarjeta-dato-cabecera">
            <span class="tarjeta-emoji" aria-hidden="true">🎓</span>
        </div>
        <span class="numero"><?php echo $totalAlumnos; ?></span>
        <span class="etiqueta-dato">Alumnos inscritos</span>
    </a>
    <a class="tarjeta dato enlace-tarjeta" href="director.php?seccion=cursos">
        <div class="tarjeta-dato-cabecera">
            <span class="tarjeta-emoji" aria-hidden="true">📚</span>
        </div>
        <span class="numero"><?php echo $totalCursos; ?></span>
        <span class="etiqueta-dato">Mis cursos</span>
    </a>
    <a class="tarjeta dato enlace-tarjeta" href="director.php?seccion=cursos">
        <div class="tarjeta-dato-cabecera">
            <span class="tarjeta-emoji" aria-hidden="true">⚡</span>
        </div>
        <span class="numero"><?php echo $cursosActivos; ?></span>
        <span class="etiqueta-dato">Cursos activos</span>
    </a>
    <a class="tarjeta dato enlace-tarjeta" href="director.php?seccion=inscripciones">
        <div class="tarjeta-dato-cabecera">
            <span class="tarjeta-emoji" aria-hidden="true">📥</span>
        </div>
        <span class="numero"><?php echo $solicitudes; ?></span>
        <span class="etiqueta-dato">Solicitudes de inscripción</span>
    </a>
</div>

<?php if (count($cursos) === 0): ?>

    <div class="tarjeta">
        <p>
            Todavía no tienes cursos. Crea el primero y, cuando esté listo, envíalo a revisión
            para que el administrador lo apruebe.
        </p>
        <a href="director.php?seccion=cursos&nuevo=1" class="btn-principal">+ Nuevo curso</a>
    </div>

<?php else: ?>

    <!-- Requiere atención -->
    <?php if ($hayAtencion): ?>
        <div class="tarjeta">
            <h2>Requiere tu atención</h2>
            <ul class="lista-personas">

                <?php foreach ($cambios as $c): ?>
                    <li>
                        <div>
                            <strong><?php echo htmlspecialchars($c["nombre"]); ?></strong>
                            <span class="etiqueta rev-rechazado">Cambios solicitados</span>
                            <?php if (trim((string) $c["revision_comentario"]) !== ""): ?>
                                <small><?php echo nl2br(htmlspecialchars($c["revision_comentario"])); ?></small>
                            <?php endif; ?>
                        </div>
                        <a class="btn-principal"
                           href="director.php?seccion=cursos&gestionar=<?php echo (int) $c["id"]; ?>">Corregir</a>
                    </li>
                <?php endforeach; ?>

                <?php foreach ($porPublicar as $c): ?>
                    <li>
                        <div>
                            <strong><?php echo htmlspecialchars($c["nombre"]); ?></strong>
                            <span class="etiqueta rev-aprobado">Aprobado</span>
                            <small>Listo para publicar.</small>
                        </div>
                        <a class="btn-principal"
                           href="director.php?seccion=cursos&gestionar=<?php echo (int) $c["id"]; ?>">Publicar</a>
                    </li>
                <?php endforeach; ?>

                <?php foreach ($enRevision as $c): ?>
                    <li>
                        <div>
                            <strong><?php echo htmlspecialchars($c["nombre"]); ?></strong>
                            <span class="etiqueta rev-en_revision">En revisión</span>
                            <small>Esperando la respuesta del administrador.</small>
                        </div>
                        <a class="btn-link"
                           href="director.php?seccion=cursos&gestionar=<?php echo (int) $c["id"]; ?>">Ver</a>
                    </li>
                <?php endforeach; ?>

            </ul>
        </div>
    <?php endif; ?>

    <!-- Gráfica -->
    <div class="tarjeta">
        <h2>Alumnos por curso</h2>
        <?php foreach ($cursos as $c):
            $ancho = round($c["alumnos"] / $maxAlumnos * 100);
        ?>
            <div class="grafica-fila">
                <span class="grafica-nombre"><?php echo htmlspecialchars($c["nombre"]); ?></span>
                <div class="grafica-pista">
                    <div class="grafica-barra" style="width: <?php echo $ancho; ?>%"></div>
                </div>
                <span class="grafica-valor"><?php echo (int) $c["alumnos"]; ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Progreso de los cursos -->
    <div class="tarjeta">
        <h2>Progreso de los cursos</h2>
        <div class="tabla-scroll">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Curso</th>
                    <th>Estado</th>
                    <th>Revisión</th>
                    <th>Alumnos</th>
                    <th>Progreso promedio</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cursos as $c):
                $posibles = (int) $c["alumnos"] * (int) $c["lecciones"];
                $progreso = $posibles > 0 ? round($c["completadas"] / $posibles * 100) : 0;
                [$revTexto, $revClase] = ETIQUETAS_REVISION[$c["aprobacion"]];
            ?>
                <tr>
                    <td>
                        <a class="enlace-nombre"
                           href="director.php?seccion=cursos&gestionar=<?php echo (int) $c["id"]; ?>">
                            <?php echo htmlspecialchars($c["nombre"]); ?>
                        </a>
                    </td>
                    <td>
                        <span class="etiqueta estado-<?php echo htmlspecialchars($c["estado"]); ?>">
                            <?php echo htmlspecialchars($c["estado"]); ?>
                        </span>
                    </td>
                    <td><span class="etiqueta <?php echo $revClase; ?>"><?php echo $revTexto; ?></span></td>
                    <td><?php echo (int) $c["alumnos"]; ?></td>
                    <td>
                        <div class="progreso">
                            <div class="progreso-barra" style="width: <?php echo $progreso; ?>%"></div>
                        </div>
                        <small><?php echo $progreso; ?>%</small>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

<?php endif; ?>