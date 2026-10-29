



<?php

function conectar() {

    $hostActual = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Detectar si Learnex se ejecuta en Ubuntu
    $esLocal = in_array($hostActual, [
        'localhost:8000',
        '127.0.0.1:8000',
        'localhost',
        '127.0.0.1'
    ], true);

    if ($esLocal) {
        // Base de datos local de Ubuntu
        $host = "localhost";
        $usuario = "regi";
        $password = "2006";
        $base_de_datos = "cursos_online";

    } else {
        // Base de datos de InfinityFree
        $host = "sql113.infinityfree.com";
    $usuario = "if0_43306498";
    $password = "ZUKE2014";
    $base_de_datos = "if0_43306498_XXX";
    }

    $conexion = new mysqli(
        $host,
        $usuario,
        $password,
        $base_de_datos
    );

    if ($conexion->connect_error) {
        error_log("Error de conexión: " . $conexion->connect_error);
        die("No fue posible conectar con la base de datos.");
    }

    $conexion->set_charset("utf8mb4");

    return $conexion;
}