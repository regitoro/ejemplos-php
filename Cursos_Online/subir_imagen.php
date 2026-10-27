<!-- Recibe la imagen del editor y devuelve su dirección. Comprueba que la persona pueda editar ese curso, y detecta el tipo real de la imagen con getimagesize -->
 <?php
session_start();
require_once "conexion.php";
require_once "permisos.php";

header("Content-Type: application/json; charset=utf-8");

function responder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos);
    exit;
}

$rol = strtolower($_SESSION["rol"] ?? "");
$uid = (int) ($_SESSION["usuario_id"] ?? 0);

if ($uid === 0 || !in_array($rol, ["admin", "director", "ponente"], true)
    || $_SERVER["REQUEST_METHOD"] !== "POST") {
    responder(403, ["error" => "No tienes permiso."]);
}

$conexion = conectar();

// La lección debe existir y la persona debe poder editar su curso
$subId = (int) ($_POST["subbloque_id"] ?? 0);
$stmt = $conexion->prepare(
    "SELECT b.curso_id FROM subbloques s
     INNER JOIN bloques b ON s.bloque_id = b.id
     WHERE s.id = ?"
);
$stmt->bind_param("i", $subId);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$fila || !puedeEditarCurso($conexion, (int) $fila["curso_id"], $rol, $uid)) {
    responder(403, ["error" => "No puedes editar esta lección."]);
}

// El archivo
if (!isset($_FILES["imagen"])) {
    responder(400, ["error" => "No se recibió ninguna imagen."]);
}
$archivo = $_FILES["imagen"];

if ($archivo["error"] === UPLOAD_ERR_INI_SIZE || $archivo["error"] === UPLOAD_ERR_FORM_SIZE) {
    responder(413, ["error" => "La imagen es demasiado grande para el servidor."]);
}
if ($archivo["error"] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo["tmp_name"])) {
    responder(400, ["error" => "No se pudo recibir la imagen."]);
}
if ($archivo["size"] > 5 * 1024 * 1024) {
    responder(413, ["error" => "La imagen no puede pesar más de 5 MB."]);
}

// Tipo real de la imagen
$info  = @getimagesize($archivo["tmp_name"]);
$tipos = [
    IMAGETYPE_JPEG => "jpg",
    IMAGETYPE_PNG  => "png",
    IMAGETYPE_GIF  => "gif",
    IMAGETYPE_WEBP => "webp",
];
if (!$info || !isset($tipos[$info[2]])) {
    responder(415, ["error" => "Formato no permitido. Usa JPG, PNG, GIF o WEBP."]);
}

// Carpeta de destino
$carpeta = __DIR__ . "/uploads/lecciones";
if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true)) {
    responder(500, ["error" => "No se pudo crear la carpeta de imágenes."]);
}

// Nombre aleatorio; la extensión sale del tipo detectado, no del nombre original
$nombre = bin2hex(random_bytes(16)) . "." . $tipos[$info[2]];
if (!move_uploaded_file($archivo["tmp_name"], $carpeta . "/" . $nombre)) {
    responder(500, ["error" => "No se pudo guardar la imagen."]);
}

responder(200, ["url" => "/uploads/lecciones/" . $nombre]);