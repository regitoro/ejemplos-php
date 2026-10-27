<!-- quien puede editar un curso -->
 <?php
// Etiquetas de revisión: clave => [texto, clase CSS]
const ETIQUETAS_REVISION = [
    "sin_enviar"  => ["Sin enviar",          "rev-sin_enviar"],
    "en_revision" => ["En revisión",         "rev-en_revision"],
    "aprobado"    => ["Aprobado",            "rev-aprobado"],
    "rechazado"   => ["Cambios solicitados", "rev-rechazado"],
];

/**
 * ¿Esta persona puede EDITAR el contenido del curso (datos, bloques, ponentes)?
 *  - admin: cualquier curso.
 *  - director: solo sus cursos, y no mientras estén en revisión.
 *  - ponente: solo los cursos a los que está asignado, y no mientras estén en revisión.
 */
function puedeEditarCurso(mysqli $c, int $cursoId, string $rol, int $uid): bool
{
    if ($cursoId <= 0) {
        return false;
    }

    if ($rol === "admin") {
        $stmt = $c->prepare("SELECT id FROM cursos WHERE id = ?");
        $stmt->bind_param("i", $cursoId);
    } elseif ($rol === "director") {
        $stmt = $c->prepare(
            "SELECT id FROM cursos
             WHERE id = ? AND director_id = ? AND aprobacion <> 'en_revision'"
        );
        $stmt->bind_param("ii", $cursoId, $uid);
    } elseif ($rol === "ponente") {
        $stmt = $c->prepare(
            "SELECT c.id FROM cursos c
             INNER JOIN curso_ponentes cp ON cp.curso_id = c.id
             WHERE c.id = ? AND cp.usuario_id = ? AND c.aprobacion <> 'en_revision'"
        );
        $stmt->bind_param("ii", $cursoId, $uid);
    } else {
        return false;
    }

    $stmt->execute();
    $ok = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    return $ok;
}

/**
 * ¿Esta persona puede ABRIR (ver) la pantalla del curso?
 * A diferencia de editar, también se permite mientras está en revisión.
 */
function puedeVerCurso(mysqli $c, int $cursoId, string $rol, int $uid): bool
{
    if ($cursoId <= 0) {
        return false;
    }

    if ($rol === "admin") {
        $stmt = $c->prepare("SELECT id FROM cursos WHERE id = ?");
        $stmt->bind_param("i", $cursoId);
    } elseif ($rol === "director") {
        $stmt = $c->prepare("SELECT id FROM cursos WHERE id = ? AND director_id = ?");
        $stmt->bind_param("ii", $cursoId, $uid);
    } elseif ($rol === "ponente") {
        $stmt = $c->prepare(
            "SELECT c.id FROM cursos c
             INNER JOIN curso_ponentes cp ON cp.curso_id = c.id
             WHERE c.id = ? AND cp.usuario_id = ?"
        );
        $stmt->bind_param("ii", $cursoId, $uid);
    } else {
        return false;
    }

    $stmt->execute();
    $ok = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    return $ok;
}


// ¿El curso ya fue aprobado por el admin?
function cursoAprobado(mysqli $c, int $cursoId): bool
{
    $stmt = $c->prepare("SELECT id FROM cursos WHERE id = ? AND aprobacion = 'aprobado'");
    $stmt->bind_param("i", $cursoId);
    $stmt->execute();
    $ok = $stmt->get_result()->num_rows === 1;
    $stmt->close();
    return $ok;
}

/**
 * Mantiene la sesión al día con la base de datos.
 * Si el usuario fue eliminado o ya no está aprobado, cierra la sesión.
 * Si le cambiaron el rol o el nombre, actualiza la sesión.
 */
function sincronizarRol(mysqli $c, string $login = "../login.php"): void
{
    $uid = (int) ($_SESSION["usuario_id"] ?? 0);
    if ($uid === 0) {
        return;
    }

    $stmt = $c->prepare(
        "SELECT u.nombre, r.nombre AS rol
         FROM usuarios u
         INNER JOIN roles r ON u.rol_id = r.id
         WHERE u.id = ? AND u.aceptado = 1"
    );
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$fila) {
        session_unset();
        session_destroy();
        header("Location: " . $login);
        exit;
    }

    $_SESSION["rol"]    = $fila["rol"];
    $_SESSION["nombre"] = $fila["nombre"];
}