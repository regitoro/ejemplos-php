<?php
// Se incluye desde Admin/secciones/perfil.php y Alumno/secciones/perfil.php.
// Usa $conexion, que ya crea la plantilla de cada panel.
$perfilId = (int) $_SESSION["usuario_id"];

$mensajesPerfil = [
    "nombre_actualizado"   => ["ok",    "Nombre actualizado."],
    "password_actualizada" => ["ok",    "Contraseña actualizada."],
    "nombre_invalido"      => ["error", "El nombre es obligatorio (máximo 100 caracteres)."],
    "password_incorrecta"  => ["error", "La contraseña actual no es correcta."],
    "password_corta"       => ["error", "La nueva contraseña debe tener al menos 8 caracteres."],
    "password_no_coincide" => ["error", "La confirmación no coincide con la nueva contraseña."],
    "error"                => ["error", "No se pudo completar la acción."],
];
$msgPerfil = $_GET["msg"] ?? "";

$stmt = $conexion->prepare(
    "SELECT u.id, u.nombre, u.email, r.nombre AS rol
     FROM usuarios u
     INNER JOIN roles r ON u.rol_id = r.id
     WHERE u.id = ?"
);
$stmt->bind_param("i", $perfilId);
$stmt->execute();
$perfil = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$perfil) {
    echo '<div class="aviso-admin error">No se pudieron cargar tus datos.</div>';
    return;
}
?>

<?php if (isset($mensajesPerfil[$msgPerfil])): ?>
    <div class="aviso-admin <?php echo $mensajesPerfil[$msgPerfil][0]; ?>">
        <?php echo htmlspecialchars($mensajesPerfil[$msgPerfil][1]); ?>
    </div>
<?php endif; ?>

<div class="tarjeta">
    <h2>
        Mis datos
        <span class="etiqueta rol-<?php echo htmlspecialchars(strtolower($perfil["rol"])); ?>">
            <?php echo htmlspecialchars($perfil["rol"]); ?>
        </span>
    </h2>

    <ul class="datos-usuario">
        <li><strong>ID</strong> <span><?php echo (int) $perfil["id"]; ?></span></li>
        <li><strong>Correo</strong> <span><?php echo htmlspecialchars($perfil["email"]); ?></span></li>
    </ul>

    <form method="POST" action="../acciones_perfil.php" class="formulario estrecho">
        <input type="hidden" name="accion" value="nombre">
        <div class="campo-admin">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="100" required
                   autocomplete="name"
                   value="<?php echo htmlspecialchars($perfil["nombre"]); ?>">
        </div>
        <div class="acciones-form">
            <button type="submit" class="btn-principal">Guardar nombre</button>
        </div>
    </form>
</div>

<div class="tarjeta">
    <h2>Cambiar contraseña</h2>

    <form method="POST" action="../acciones_perfil.php" class="formulario estrecho">
        <input type="hidden" name="accion" value="password">

        <div class="campo-admin">
            <label for="actual">Contraseña actual</label>
            <input type="password" id="actual" name="actual" required autocomplete="current-password">
        </div>
        <div class="campo-admin">
            <label for="nueva">Nueva contraseña (mínimo 8 caracteres)</label>
            <input type="password" id="nueva" name="nueva" minlength="8" required autocomplete="new-password">
        </div>
        <div class="campo-admin">
            <label for="confirmar">Confirmar nueva contraseña</label>
            <input type="password" id="confirmar" name="confirmar" minlength="8" required autocomplete="new-password">
        </div>

        <div class="acciones-form">
            <button type="submit" class="btn-principal">Cambiar contraseña</button>
        </div>
    </form>
</div>