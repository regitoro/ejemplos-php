<?php
// Si se pidió ver un usuario, mostrar su detalle y terminar aquí
if (isset($_GET["ver"])) {
    include __DIR__ . "/usuario_detalle.php";
    return;
}
// Mensajes permitidos (lista blanca)
$mensajes = [
    "aprobado"  => "Usuario aprobado correctamente.",
    "rechazado" => "Solicitud rechazada y cuenta eliminada.",
    "error"     => "No se pudo completar la acción.",
    "usuario_eliminado" => "Usuario eliminado correctamente.",
    "usuario_creado"   => "Usuario creado. Ya puede iniciar sesión con la contraseña que le asignaste.",
    "email_repetido"   => "Ya existe un usuario con ese correo.",
    "usuario_invalido" => "Revisa los datos: nombre y correo válidos, y contraseña de al menos 8 caracteres.",
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
    <div class="aviso-admin <?php echo in_array($msg, ["error", "email_repetido", "usuario_invalido"], true) ? 'error' : 'ok'; ?>">
        <?php echo htmlspecialchars($mensajes[$msg]); ?>
    </div>
<?php endif; ?>

<div class="tarjeta">
    <details class="editar">
        <summary>+ Crear usuario</summary>

        <form method="POST" action="../acciones_usuarios.php" class="formulario estrecho">
            <input type="hidden" name="accion" value="crear">

            <div class="campo-admin">
                <label for="nuevo_nombre">Nombre</label>
                <input type="text" id="nuevo_nombre" name="nombre" maxlength="100" required autocomplete="off">
            </div>

            <div class="campo-admin">
                <label for="nuevo_email">Correo</label>
                <input type="email" id="nuevo_email" name="email" maxlength="150" required autocomplete="off">
            </div>

            <div class="campo-admin">
                <label for="nuevo_password">Contraseña inicial (mínimo 8 caracteres)</label>
                <input type="password" id="nuevo_password" name="password" minlength="8" required
                       autocomplete="new-password">
            </div>

            <div class="campo-admin">
                <label for="nuevo_rol">Rol</label>
                <select id="nuevo_rol" name="rol_id" required>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo (int) $r["id"]; ?>"
                            <?php echo strtolower($r["nombre"]) === "alumno" ? "selected" : ""; ?>>
                            <?php echo htmlspecialchars($r["nombre"]); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p class="nota">
                La cuenta queda aprobada y con el correo verificado, así que puede entrar de
                inmediato. Comunícale la contraseña; podrá cambiarla desde su Perfil.
            </p>

            <div class="acciones-form">
                <button type="submit" class="btn-principal">Crear usuario</button>
            </div>
        </form>
    </details>
</div>

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

<?php
// ---------- Usuarios registrados (aprobados) ----------
$q = trim($_GET["q"] ?? "");
if (strlen($q) > 100) {
    $q = substr($q, 0, 100);
}
$rolFiltro = (int) ($_GET["rol"] ?? 0);

// Escapar % y _ para que se busquen como texto normal
$like = "%" . addcslashes($q, "%_\\") . "%";

//columnas que aparecen: nombre, email, y rol
$sql = "SELECT u.id, u.nombre, u.email, r.nombre AS rol
        FROM usuarios u
        INNER JOIN roles r ON u.rol_id = r.id
        WHERE u.aceptado = 1
          AND (u.nombre LIKE ? OR u.email LIKE ?)
          AND (? = 0 OR u.rol_id = ?)
        ORDER BY u.nombre";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ssii", $like, $like, $rolFiltro, $rolFiltro);
$stmt->execute();
$registrados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="tarjeta">
    <h2>Usuarios registrados <span class="contador"><?php echo count($registrados); ?></span></h2>

    <form method="GET" action="admin.php" class="buscador">
        <input type="hidden" name="seccion" value="usuarios">
        <input type="search" name="q" maxlength="100" placeholder="Buscar por nombre o correo…"
               value="<?php echo htmlspecialchars($q); ?>">
        <select name="rol" class="select-linea" style="flex: 0 0 auto; min-width: 150px;">
            <option value="0">Todos los roles</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?php echo (int) $r["id"]; ?>"
                    <?php echo $rolFiltro === (int) $r["id"] ? "selected" : ""; ?>>
                    <?php echo htmlspecialchars($r["nombre"]); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-principal">Filtrar</button>
        <?php if ($q !== "" || $rolFiltro > 0): ?>
            <a href="admin.php?seccion=usuarios" class="btn-link">Limpiar</a>
        <?php endif; ?>
    </form>

    <?php if (count($registrados) === 0): ?>
        <p>No se encontraron usuarios con esos criterios.</p>
    <?php else: ?>
        <div class="tabla-scroll">
        <table class="tabla">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($registrados as $u): ?>
                <tr>
                    <td>
                        <a class="enlace-nombre"
                           href="admin.php?seccion=usuarios&ver=<?php echo (int) $u["id"]; ?>">
                            <?php echo htmlspecialchars($u["nombre"]); ?>
                        </a>
                    </td>
                    <td><?php echo htmlspecialchars($u["email"]); ?></td>
                    <td><span class="etiqueta rol-<?php echo htmlspecialchars(strtolower($u["rol"])); ?>">
                        <?php echo htmlspecialchars($u["rol"]); ?></span></td>
                    
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>