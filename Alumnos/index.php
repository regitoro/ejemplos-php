<?php
    include_once "conexion.php";
    $sql = "SELECT * FROM alumnos";
    $respuesta = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista de Alumnos</title>
</head>
<body>

    <div class="table-main-container">

        <h1>Lista de estudiantes</h1>

        <div class="table-container">
            <table>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Carrera</th>
                    <th>Semestre</th>
                    <th>Edad</th>
                </tr>

                <?php
                    while($registro = $respuesta->fetch_assoc()){
                ?>
                    <tr>
                        <td><?php echo $registro["id"] ?></td>
                        <td><?php echo $registro["nombre"] ?></td>
                        <td><?php echo $registro["carrera"] ?></td>
                        <td><?php echo $registro["semestre"] ?></td>
                        <td><?php echo $registro["edad"] ?></td>
                    </tr>
                <?php
                    }
                ?>

            </table>
        </div>

    </div>

</body>
</html>