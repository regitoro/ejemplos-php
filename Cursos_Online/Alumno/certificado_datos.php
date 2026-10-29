<?php
/** Calcula el promedio por ejercicio y valida los requisitos del certificado. */
function obtenerResultadoCertificado(mysqli $conexion, int $alumnoId, int $cursoId): ?array
{
    $stmt = $conexion->prepare(
        "SELECT i.id AS inscripcion_id, c.id AS curso_id, c.nombre AS curso,
                u.nombre AS alumno
         FROM inscripciones i
         INNER JOIN cursos c ON c.id = i.curso_id
         INNER JOIN usuarios u ON u.id = i.usuario_id
         WHERE i.curso_id = ? AND i.usuario_id = ? AND c.estado <> 'borrador'"
    );
    $stmt->bind_param("ii", $cursoId, $alumnoId);
    $stmt->execute();
    $datos = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$datos) {
        return null;
    }

    $inscripcionId = (int) $datos["inscripcion_id"];
    $stmt = $conexion->prepare(
        "SELECT COUNT(*) AS total,
                COUNT(p.subbloque_id) AS completadas
         FROM bloques b
         INNER JOIN subbloques s ON s.bloque_id = b.id
         LEFT JOIN progreso_subbloques p
                ON p.subbloque_id = s.id AND p.inscripcion_id = ?
         WHERE b.curso_id = ?"
    );
    $stmt->bind_param("ii", $inscripcionId, $cursoId);
    $stmt->execute();
    $lecciones = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conexion->prepare(
        "SELECT COUNT(e.id) AS total,
                COUNT(ee.id) AS entregados,
                COALESCE(SUM(ee.puntuacion IS NOT NULL), 0) AS calificados,
                AVG(CASE
                    WHEN ee.puntuacion IS NOT NULL AND e.puntuacion_maxima > 0
                    THEN ee.puntuacion / e.puntuacion_maxima * 100
                    ELSE NULL
                END) AS promedio
         FROM ejercicios e
         LEFT JOIN entregas_ejercicios ee
                ON ee.ejercicio_id = e.id AND ee.alumno_id = ?
         WHERE e.curso_id = ?"
    );
    $stmt->bind_param("ii", $alumnoId, $cursoId);
    $stmt->execute();
    $ejercicios = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $leccionesTotal = (int) $lecciones["total"];
    $leccionesCompletadas = (int) $lecciones["completadas"];
    $ejerciciosTotal = (int) $ejercicios["total"];
    $ejerciciosEntregados = (int) $ejercicios["entregados"];
    $ejerciciosCalificados = (int) $ejercicios["calificados"];
    $promedio = $ejercicios["promedio"] !== null ? (float) $ejercicios["promedio"] : null;

    $aprobado = $leccionesTotal > 0
        && $leccionesCompletadas === $leccionesTotal
        && $ejerciciosTotal > 0
        && $ejerciciosEntregados === $ejerciciosTotal
        && $ejerciciosCalificados === $ejerciciosTotal
        && $promedio !== null
        && $promedio >= 80.0;

    return [
        "curso" => $datos["curso"],
        "alumno" => $datos["alumno"],
        "lecciones_total" => $leccionesTotal,
        "lecciones_completadas" => $leccionesCompletadas,
        "ejercicios_total" => $ejerciciosTotal,
        "ejercicios_entregados" => $ejerciciosEntregados,
        "ejercicios_calificados" => $ejerciciosCalificados,
        "promedio" => $promedio,
        "aprobado" => $aprobado,
        "folio" => "CO-" . strtoupper(substr(hash("sha256", $alumnoId . ":" . $cursoId), 0, 10)),
    ];
}
