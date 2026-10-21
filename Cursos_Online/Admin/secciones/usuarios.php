<?php
// Mensajes permitidos (lista blanca)
$mensajes = [
    "aprobado"  => "Usuario aprobado correctamente.",
    "rechazado" => "Solicitud rechazada y cuenta eliminada.",
    "error"     => "No se pudo completar la acción.",
];
$msg = $_GET["msg"] ?? "";

// Roles disponibles para el selector
$roles = [];
$res = $conexion->query("SELECT id, nombre FROM roles ORDER BY id");
while ($r = $res->fetch_assoc()) {
    $roles[] = $r;
}

// Usuarios pendientes de aprobación
$pendientes = $conexion->query(
    "SELECT id, nombre, email, email_verificado, rol_id
     FROM usuarios
     WHERE aceptado = 0
     ORDER BY id"
);
?>

<?php if (isset($mensajes[$msg])): ?>
    <div class="aviso-admin <?php echo $msg === 'error' ? 'error' : 'ok'; ?>">
        <?php echo htmlspecialchars($mensajes[$msg]); ?>
    </div>
<?php endif; ?>

<div class="tarjeta">
    <h2>Solicitudes pendientes</h2>

    <?php if ($pendientes->num_rows === 0): ?>
        <p>No hay usuarios pendientes de aprobación.</p>
    <?php else: ?>
        <div class="tabla-scroll">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Correo verificado</th>
                    <th>Rol</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($u = $pendientes->fetch_assoc()): ?>
                <tr>
                    <form method="POST" action="../acciones_usuarios.php">
                        <input type="hidden" name="id" value="<?php echo (int) $u["id"]; ?>">
                        <td><?php echo htmlspecialchars($u["nombre"]); ?></td>
                        <td><?php echo htmlspecialchars($u["email"]); ?></td>
                        <td>
                            <?php if ($u["email_verificado"]): ?>
                                <span class="etiqueta ok">Sí</span>
                            <?php else: ?>
                                <span class="etiqueta no">No</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <select name="rol_id">
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?php echo (int) $r["id"]; ?>"
                                        <?php echo $r["id"] == $u["rol_id"] ? "selected" : ""; ?>>
                                        <?php echo htmlspecialchars($r["nombre"]); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="acciones">
                            <button type="submit" name="accion" value="aprobar" class="btn-ok">Aprobar</button>
                            <button type="submit" name="accion" value="rechazar" class="btn-peligro"
                                    onclick="return confirm('¿Rechazar y eliminar esta solicitud?');">Rechazar</button>
                        </td>
                    </form>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>