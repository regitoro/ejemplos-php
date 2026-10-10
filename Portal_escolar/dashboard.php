<?php
session_start();
include_once "conexion.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
$usuario_id = $_SESSION['usuario_id'];

//ontener datos del alumno
$sql = "SELECT id, nombre, email, create_at
        FROM usuarios 
        WHERE id = ?";
$stmt = $conexion->prepare($sql);

$stmt->bind_param("i", $usuario_id);

$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

//obteenr calificaciones
$sql = "SELECT materias.nombre AS materia, calificacion.calificacion
        FROM calificacion
        INNER JOIN materias ON materias.id = calificacion.materia_id
        WHERE calificacion.usuario_id = ?";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$calificaciones = $stmt->get_result();
?>

<h1>Bienvenido, 
    <?php 
        echo htmlspecialchars($usuario["nombre"]);   
    ?>
</h1>

<h2>Mis datos</h2>
<p>
    <strong>ID:</strong> <?php echo $usuario["id"]; ?>
</p>
<p>
    <strong>Email:</strong> 
    <?php 
        echo htmlspecialchars($usuario["email"]); 
    ?>
</p>
<p>
    <strong>Fecha de registro:</strong>
    <?php 
        echo $usuario["create_at"];
    ?>
</p>

<hr>
<h2> Mis materias</h2>
<table border="1">
<tr>
    <th>Materia</th>
    <th>Calificación</th>
</tr>
<?php while($fila = $calificaciones->fetch_assoc()){
    ?>
    <tr>
        <td><?php echo htmlspecialchars($fila["materia"]); ?>
    </td>
        <td><?php echo $fila["calificacion"]; ?>
    </td>
    </tr>

<?php }?>

</table>
<a href="logout.php">Cerrar sesión</a>

