



    <?php
    include "header.php";
    ?>

    <h1>Registro de calificaciones</h1>

    <form method="GET" action="calificaciones.php">
        <label for="nombre">NOMBRE: </label>
        <input type="text" id="nombre" name="nombre" required>
        <br><br>

        <label for="materia">MATERIA: </label>
        <input type="text" id="materia" name="materia" required>
        <br><br>

        <label for="calificacion">CALIFICACIÓN: </label>
        <input type="number" id="calificacion" name="calificacion" 
               min="0" max="10" step="0.1" required>
        <br><br>

        <button type="submit">ENVIAR</button>
    </form>

        <?php
        include "footer.php";
        ?>

