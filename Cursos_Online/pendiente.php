<?php
// No requiere sesión: el usuario aún no ha iniciado sesión
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cuenta pendiente</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="card estado-pendiente-cuenta">
    <a class="pendiente-volver" href="login.php">← Volver a iniciar sesión</a>
    <div class="pendiente-icono" aria-hidden="true">
        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="24" cy="24" r="21" stroke="currentColor" stroke-width="2.5"/>
            <path d="M24 12.5V24L32 29" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <span class="pendiente-etiqueta">ESTADO DE LA CUENTA</span>
    <h1>Estamos revisando tu cuenta</h1>
    <p class="pendiente-descripcion">Tu correo está verificado y la solicitud de acceso fue recibida. Un administrador debe aprobar la cuenta antes de que puedas entrar.</p>

    <div class="pendiente-pasos" aria-label="Pasos de aprobación">
        <div class="pendiente-paso completado">
            <span class="pendiente-paso-icono" aria-hidden="true">✓</span>
            <span>Registro enviado</span>
        </div>
        <span class="pendiente-conector" aria-hidden="true"></span>
        <div class="pendiente-paso actual">
            <span class="pendiente-paso-icono" aria-hidden="true">2</span>
            <span>Aprobación del administrador</span>
        </div>
    </div>

    <div class="pendiente-nota">
        <strong>¿Qué sigue?</strong>
        <p>Cuando tu cuenta sea aprobada, podrás iniciar sesión con el correo y la contraseña que registraste.</p>
    </div>

    <a class="btn pendiente-accion" href="login.php">Ir al inicio de sesión</a>
</main>
</body>
</html>