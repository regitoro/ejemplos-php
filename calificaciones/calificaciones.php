<?php
    if ($_SERVER["REQUEST_METHOD"] === "GET") {
        $nombre = trim((string) ($_GET["nombre"] ?? ""));
        $materia = trim((string) ($_GET["materia"] ?? ""));
        $calificacion = filter_var(
            $_GET["calificacion"] ?? "",
            FILTER_VALIDATE_FLOAT
        );

        if (
            $nombre === "" ||
            $materia === "" ||
            $calificacion === false ||
            $calificacion < 0 ||
            $calificacion > 10
        ) {
            echo "<p style='color: red;'>Ingresa todos los datos y una calificación válida entre 0 y 10.</p>";
        } else {
            echo "<h2>Datos registrados</h2>";
            echo "<p><strong>Nombre:</strong> " . htmlspecialchars($nombre, ENT_QUOTES, "UTF-8") . "</p>";
            echo "<p><strong>Materia:</strong> " . htmlspecialchars($materia, ENT_QUOTES, "UTF-8") . "</p>";
            echo "<p><strong>Calificación:</strong> " . number_format($calificacion, 1) . "</p>";
        }
    }
    ?>