<?php
    $host = "localhost";
    $usuario = 'regi';
    $password = '2006';
    $baseDatos = "escuela";
    $table = "alumnos";

    $conexion = new mysqli(
        $host,
        $usuario,
        $password,
        $baseDatos
    );

    if ($conexion->connect_error) {
        die("Error de conexion" . $conexion->connect_error);
    }

?>