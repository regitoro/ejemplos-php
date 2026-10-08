<?php

    include_once "conexion.php";
    $sql = "SELECT * FROM calificaciones";
    $resultado = $conexion->query($sql);
?>


<h1>Lista de calificaciones</h1>
<table border="1">
    <tr>
        <th>ID</th>
        <th>Nombre</th>
        <th>Materia</th>
        <th>Calificacion</th>
    </tr>

    <?php while ($fila = $resultado->fetch_assoc()) { ?>
        <tr>
            <td><?php echo htmlspecialchars($fila['id']); ?></td>
            <td><?php echo htmlspecialchars($fila['nombre']); ?></td>
            <td><?php echo htmlspecialchars($fila['materia']); ?></td>
            <td><?php echo htmlspecialchars($fila['calificacion']); ?></td>
        </tr>
    <?php } ?>

</table>