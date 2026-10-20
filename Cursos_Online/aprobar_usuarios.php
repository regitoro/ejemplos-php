<?php
session_start();
include_once "conexion.php";

// Debe haber sesión iniciada
if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

// Solo el admin puede entrar
$rol = strtolower($_SESSION["rol"] ?? "");

if ($rol !== "admin") {
    http_response_code(403);
    echo "No tienes permiso para ver esta página.";
    exit;
}

$conexion = conectar();

// Token CSRF para proteger los botones
if (empty($_SESSION["csrf"])) {
    $_SESSION["csrf"] = bin2hex(random_bytes(32));
}

$mensaje = "";

// Obtener roles disponibles, excepto admin
$sqlRoles = "SELECT id, nombre FROM roles WHERE LOWER(nombre) != 'admin'";
$rolesDisponibles = $conexion->query($sqlRoles);

$roles = [];

while ($filaRol = $rolesDisponibles->fetch_assoc()) {
    $roles[] = $filaRol;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals($_SESSION["csrf"], $_POST["csrf"] ?? "")) {

        $mensaje = "Solicitud no válida.";

    } else {

        $id = (int) ($_POST["id"] ?? 0);
        $accion = $_POST["accion"] ?? "";

        if ($accion === "aceptar") {

            $rol_id = (int) ($_POST["rol_id"] ?? 0);

            // Comprobar que el rol exista y no sea admin
            $stmtRol = $conexion->prepare(
                "SELECT id FROM roles
                 WHERE id = ? AND LOWER(nombre) != 'admin'"
            );

            $stmtRol->bind_param("i", $rol_id);
            $stmtRol->execute();

            $resultadoRol = $stmtRol->get_result();
            $rolValido = $resultadoRol->num_rows > 0;

            $stmtRol->close();

            if (!$rolValido) {

                $mensaje = "El rol seleccionado no es válido.";

            } else {

                // Asignar el rol y aceptar al usuario
                $stmt = $conexion->prepare(
                    "UPDATE usuarios
                     SET rol_id = ?, aceptado = 1
                     WHERE id = ? AND aceptado = 0"
                );

                $stmt->bind_param("ii", $rol_id, $id);
                $stmt->execute();

                $mensaje = $stmt->affected_rows > 0
                    ? "Usuario aceptado y rol asignado correctamente."
                    : "No se pudo aceptar al usuario.";

                $stmt->close();
            }

        } elseif ($accion === "rechazar") {

            // Solo eliminar solicitudes pendientes
            $stmt = $conexion->prepare(
                "DELETE FROM usuarios
                 WHERE id = ? AND aceptado = 0"
            );

            $stmt->bind_param("i", $id);
            $stmt->execute();

            $mensaje = $stmt->affected_rows > 0
                ? "Solicitud rechazada y eliminada."
                : "No se pudo rechazar la solicitud.";

            $stmt->close();
        }
    }
}



// Usuarios pendientes de aprobación
$sql = "SELECT u.id, u.nombre, u.email, u.email_verificado, r.nombre AS rol
        FROM usuarios u
        JOIN roles r ON r.id = u.rol_id
        WHERE u.aceptado = 0
        ORDER BY u.id";

$pendientes = $conexion->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aprobar usuarios</title>
</head>
<body>

    <h1>Usuarios pendientes de aprobación</h1>

    <p><?php echo htmlspecialchars($mensaje); ?></p>

    <?php if ($pendientes && $pendientes->num_rows > 0): ?>

        <table border="1" cellpadding="6">
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Email verificado</th>
                <th>Acciones</th>
            </tr>

            <?php while ($fila = $pendientes->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($fila["nombre"]); ?></td>
                    <td><?php echo htmlspecialchars($fila["email"]); ?></td>
                    <td>
    <select name="rol_id" form="form_<?php echo (int) $fila["id"]; ?>" required>
        <?php foreach ($roles as $rolDisponible): ?>
            <option
                value="<?php echo (int) $rolDisponible["id"]; ?>"
                <?php echo strtolower($rolDisponible["nombre"]) === "alumno" ? "selected" : ""; ?>
            >
                <?php echo htmlspecialchars($rolDisponible["nombre"]); ?>
            </option>
        <?php endforeach; ?>
    </select>
</td>
                    <td><?php echo $fila["email_verificado"] ? "Sí" : "No"; ?></td>
                    <td>
                        <form method="POST"
      id="form_<?php echo (int) $fila["id"]; ?>"
      style="display:inline">

    <input type="hidden" name="csrf"
           value="<?php echo htmlspecialchars($_SESSION["csrf"]); ?>">

    <input type="hidden" name="id"
           value="<?php echo (int) $fila["id"]; ?>">

    <button type="submit" name="accion" value="aceptar">
        Aceptar
    </button>

    <button type="submit" name="accion" value="rechazar"
            onclick="return confirm('¿Rechazar y eliminar esta solicitud?');">
        Rechazar
    </button>

</form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>

    <?php else: ?>

        <p>No hay usuarios pendientes.</p>

    <?php endif; ?>

    <p><a href="mainWindow.php">Volver al panel</a></p>

</body>
</html>