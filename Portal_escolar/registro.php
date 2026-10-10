<?php
include_once "conexion.php";

$mensaje = "";

if($_SERVER["REQUEST_METHOD"] === "POST"){
    $id = (int) trim ($_POST["id"]);
    $nombre = trim($_POST["nombre"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    //convertir contraseña a hash
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    //crear token
    $token = bin2hex(random_bytes(32));
    $sql = "INSERT INTO usuarios 
            (id, nombre, email, password, token_verificacion) 
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $conexion->prepare($sql);

    $stmt->bind_param(
        "issss", 
        $id, $nombre, $email, $passwordHash, $token);

    if($stmt->execute()){
        $mensaje = "Usuario registrado correctamente. Por favor, verifica tu correo electrónico.";
        echo "<p>
        Para confirmar tu correo: 
        <a href='verificar.php?token=$token'>Confirmar email</a>
        </p>";

    } else{

        $mensaje = "Error al registrar el usuario: ";
        }
    
    }
    ?>

<h1>Registro de alumnos</h1>
<form method="POST">
    <label>Numero de control:</label>
    <input type="number" name="id" min="1" required>
    <label>Nombre:</label>
    <input type="text" name="nombre" required>
    <label>Email:</label>   
    <input type="email" name="email" required>
    <label>Contraseña:</label>
    <input type="password" name="password" required>
    <button type="submit">Registrarse</button>
</form>

<p><?php echo $mensaje; ?></p>
<a href="login.php">Iniciar sesión</a>


