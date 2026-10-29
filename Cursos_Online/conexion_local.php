<?php
function conectar() {
    $host = "localhost";
    $usuario = "regi";
    $password = "2006";
    $base_de_datos = "cursos_online";

    $conexion = new mysqli(
        $host,
        $usuario,
        $password,
        $base_de_datos
    );

    if ($conexion->connect_error) {
        die("Error de conexion" . $conexion->connect_error);
    }

    return $conexion;
        
}

?>
