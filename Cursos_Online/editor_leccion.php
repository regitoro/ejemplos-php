<?php
session_start();
require_once "conexion.php";
require_once "permisos.php";
require_once "contenido.php";

if (!isset($_SESSION["usuario_id"])) {
    header("Location: login.php");
    exit;
}

$conexion = conectar();
sincronizarRol($conexion, "login.php");

$rol = strtolower($_SESSION["rol"] ?? "");
$uid = (int) $_SESSION["usuario_id"];

// Alumnos y otros roles no editan contenido
if (!in_array($rol, ["admin", "director", "ponente"], true)) {
    header("Location: mainWindow.php");
    exit;
}

$subId = (int) ($_GET["id"] ?? $_POST["id"] ?? 0);

// La lección con su bloque y su curso
$stmt = $conexion->prepare(
    "SELECT s.id, s.titulo, s.contenido, b.titulo AS bloque,
            c.id AS curso_id, c.nombre AS curso
     FROM subbloques s
     INNER JOIN bloques b ON s.bloque_id = b.id
     INNER JOIN cursos c ON b.curso_id = c.id
     WHERE s.id = ?"
);
$stmt->bind_param("i", $subId);
$stmt->execute();
$leccion = $stmt->get_result()->fetch_assoc();
$stmt->close();

$paneles = [
    "admin"    => "Admin/admin.php",
    "director" => "Director/director.php",
    "ponente"  => "Ponente/ponente.php",
];

// Debe poder al menos ver el curso
if (!$leccion || !puedeVerCurso($conexion, (int) $leccion["curso_id"], $rol, $uid)) {
    header("Location: " . $paneles[$rol] . "?seccion=cursos");
    exit;
}

$cursoId     = (int) $leccion["curso_id"];
$volverCurso = $paneles[$rol] . "?seccion=cursos&gestionar=" . $cursoId;
$puedeEditar = puedeEditarCurso($conexion, $cursoId, $rol, $uid);

$mensajes = [
    "guardado"        => ["ok",    "Cambios guardados."],
    "titulo_invalido" => ["error", "El título es obligatorio (máximo 150 caracteres)."],
    "muy_largo"       => ["error", "El contenido es demasiado largo."],
    "sin_permiso"     => ["error", "No tienes permiso para editar esta lección."],
];
$msg = $_GET["msg"] ?? "";

$tituloForm    = $leccion["titulo"];
$contenidoForm = htmlParaEditor($leccion["contenido"]);

// ---------- Guardar ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!$puedeEditar) {
        $msg = "sin_permiso";
    } else {
        $tituloForm = trim($_POST["titulo"] ?? "");
        $bruto      = $_POST["contenido"] ?? "";

        if ($tituloForm === "" || strlen($tituloForm) > 150) {
            $msg           = "titulo_invalido";
            $contenidoForm = limpiarHtml($bruto);      // no se pierde lo escrito
        } elseif (strlen($bruto) > 1000000) {
            $msg           = "muy_largo";
            $tituloForm    = $leccion["titulo"];
        } else {
            $limpio = limpiarHtml($bruto);
            $valor  = contenidoVacio($limpio) ? null : $limpio;

            $stmt = $conexion->prepare("UPDATE subbloques SET titulo = ?, contenido = ? WHERE id = ?");
            $stmt->bind_param("ssi", $tituloForm, $valor, $subId);
            $stmt->execute();
            $stmt->close();

            if (($_POST["salir"] ?? "0") === "1") {
                header("Location: " . $volverCurso . "&msg=sub_actualizado");
            } else {
                header("Location: editor_leccion.php?id=" . $subId . "&msg=guardado");
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar lección</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", system-ui, sans-serif; background: #f3f4f6; color: #1f2937; }

        .ed-barra {
            position: sticky; top: 0; z-index: 10;
            display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
            padding: 12px 24px; background: #fff; border-bottom: 1px solid #e5e7eb;
        }
        .ed-ruta { flex: 1; min-width: 200px; color: #6b7280; font-size: 0.9rem; }
        .ed-btn {
            border: none; border-radius: 8px; padding: 10px 16px; font: inherit;
            font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block;
        }
        .ed-primario { background: #4f46e5; color: #fff; }
        .ed-primario:hover { background: #4338ca; }
        .ed-suave { background: #eef2ff; color: #4f46e5; }
        .ed-suave:hover { background: #e0e7ff; }

        .ed-pagina { max-width: 900px; margin: 24px auto; padding: 0 16px 64px; }
        .ed-aviso { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; }
        .ed-aviso.ok { background: #dcfce7; color: #166534; }
        .ed-aviso.error { background: #fee2e2; color: #991b1b; }

        .ed-titulo {
            width: 100%; padding: 12px 14px; margin-bottom: 16px;
            font: inherit; font-size: 1.4rem; font-weight: 700;
            border: 1px solid #d1d5db; border-radius: 10px; background: #fff;
        }
        #estado { min-height: 1.4em; margin: 8px 0; font-size: 0.9rem; color: #4b5563; }
        #estado.error { color: #991b1b; }

        /* Que el editor use toda la altura y la barra de herramientas se quede arriba */
        .ql-toolbar.ql-snow { position: sticky; top: 64px; z-index: 5; background: #fff; border-radius: 10px 10px 0 0; }
        .ql-container.ql-snow { height: auto; background: #fff; border-radius: 0 0 10px 10px; font-size: 1.05rem; }
        .ql-editor { min-height: 60vh; overflow-y: visible; line-height: 1.7; }
        .ql-editor img { max-width: 100%; height: auto; }

        /* Paleta Obsidian Dark: solo colores; se conserva el layout del editor. */
        :root {
            color-scheme: dark;
            --ed-abyss: #171717;
            --ed-surface: #1e1e1e;
            --ed-hover: #262626;
            --ed-graphite: #3f3f3f;
            --ed-bright: #eeeeee;
            --ed-medium: #bcbcbc;
            --ed-muted: #a3a3a3;
            --ed-amethyst: #7c3aed;
            --ed-amethyst-hover: #6d28d9;
            --ed-lavender: #a78bfa;
            --ed-success: #4ade80;
            --ed-error: #f87171;
        }
        body { background: var(--ed-abyss); color: var(--ed-bright); }
        .ed-barra { background: #141414; border-bottom-color: var(--ed-graphite); }
        .ed-ruta, #estado { color: var(--ed-muted); }
        .ed-btn { color: var(--ed-bright); }
        .ed-primario { background: var(--ed-amethyst); color: #fff; }
        .ed-primario:hover { background: var(--ed-amethyst-hover); }
        .ed-suave { background: var(--ed-surface); color: var(--ed-lavender); box-shadow: inset 0 0 0 1px var(--ed-graphite); }
        .ed-suave:hover { background: var(--ed-hover); color: var(--ed-bright); }
        .ed-aviso.ok { background: rgba(74, 222, 128, 0.12); color: var(--ed-success); }
        .ed-aviso.error, #estado.error { background: rgba(248, 113, 113, 0.12); color: var(--ed-error); }
        .ed-titulo { border-color: var(--ed-graphite); background: var(--ed-surface); color: var(--ed-bright); }
        .ed-titulo::placeholder { color: var(--ed-muted); }
        .ed-titulo:focus { outline: none; border-color: var(--ed-lavender); box-shadow: inset 0 0 0 1px rgba(167, 139, 250, 0.35), 0 0 14px rgba(124, 58, 237, 0.2); }

        .ql-toolbar.ql-snow,
        .ql-container.ql-snow { border-color: var(--ed-graphite); background: var(--ed-surface); }
        .ql-toolbar.ql-snow { box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05); }
        .ql-editor { color: var(--ed-bright); caret-color: var(--ed-lavender); }
        .ql-editor.ql-blank::before { color: var(--ed-muted); }
        .ql-snow .ql-stroke { stroke: var(--ed-medium); }
        .ql-snow .ql-fill,
        .ql-snow .ql-stroke.ql-fill { fill: var(--ed-medium); }
        .ql-snow .ql-picker { color: var(--ed-medium); }
        .ql-snow .ql-picker-options,
        .ql-snow .ql-tooltip { border-color: var(--ed-graphite); background: var(--ed-surface); color: var(--ed-bright); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05); }
        .ql-snow .ql-tooltip input[type="text"] { border-color: var(--ed-graphite); background: var(--ed-abyss); color: var(--ed-bright); }
        .ql-snow .ql-tooltip a { color: var(--ed-lavender); }
        .ql-snow .ql-toolbar button:hover,
        .ql-snow .ql-toolbar button:focus,
        .ql-snow .ql-toolbar button.ql-active,
        .ql-snow .ql-toolbar .ql-picker-label:hover,
        .ql-snow .ql-toolbar .ql-picker-label.ql-active,
        .ql-snow .ql-toolbar .ql-picker-item:hover,
        .ql-snow .ql-toolbar .ql-picker-item.ql-selected { color: var(--ed-lavender); }
        .ql-snow .ql-toolbar button:hover .ql-stroke,
        .ql-snow .ql-toolbar button:focus .ql-stroke,
        .ql-snow .ql-toolbar button.ql-active .ql-stroke,
        .ql-snow .ql-toolbar .ql-picker-label:hover .ql-stroke,
        .ql-snow .ql-toolbar .ql-picker-label.ql-active .ql-stroke { stroke: var(--ed-lavender); }
        .ql-snow .ql-toolbar button:hover .ql-fill,
        .ql-snow .ql-toolbar button:focus .ql-fill,
        .ql-snow .ql-toolbar button.ql-active .ql-fill { fill: var(--ed-lavender); }
        .ql-snow .ql-toolbar .ql-picker-options .ql-picker-item { color: var(--ed-medium); }
        .ql-snow .ql-toolbar .ql-picker-options .ql-picker-item:hover,
        .ql-snow .ql-toolbar .ql-picker-options .ql-picker-item.ql-selected { color: var(--ed-lavender); }
        .ql-snow .ql-tooltip::before { color: var(--ed-medium); }
        .ql-snow .ql-tooltip[data-mode="link"]::before { color: var(--ed-medium); }
    </style>
</head>
<body>

<form method="POST" action="editor_leccion.php" id="formulario">
    <input type="hidden" name="id" value="<?php echo $subId; ?>">
    <textarea name="contenido" id="contenido" hidden></textarea>

    <header class="ed-barra">
        <a href="<?php echo htmlspecialchars($volverCurso); ?>" class="ed-btn ed-suave">← Volver al curso</a>
        <span class="ed-ruta">
            <?php echo htmlspecialchars($leccion["curso"]); ?> ›
            <?php echo htmlspecialchars($leccion["bloque"]); ?>
        </span>
        <a href="Alumno/leccion.php?id=<?php echo $subId; ?>" target="_blank" class="ed-btn ed-suave">
            Vista previa (lo guardado)
        </a>
        <?php if ($puedeEditar): ?>
            <button type="submit" name="salir" value="0" class="ed-btn ed-suave">Guardar</button>
            <button type="submit" name="salir" value="1" class="ed-btn ed-primario">Guardar y volver al curso</button>
        <?php endif; ?>
    </header>

    <main class="ed-pagina">

        <?php if (isset($mensajes[$msg])): ?>
            <div class="ed-aviso <?php echo $mensajes[$msg][0]; ?>">
                <?php echo htmlspecialchars($mensajes[$msg][1]); ?>
            </div>
        <?php endif; ?>

        <?php if (!$puedeEditar): ?>

            <div class="ed-aviso error">
                Este curso está en revisión del administrador y no se puede editar por ahora.
            </div>

        <?php else: ?>

            <input type="text" name="titulo" class="ed-titulo" maxlength="150" required
                   placeholder="Título de la lección"
                   value="<?php echo htmlspecialchars($tituloForm); ?>">

            <div id="estado"></div>

            <div id="editor"><?php echo $contenidoForm; ?></div>

        <?php endif; ?>
    </main>
</form>

<?php if ($puedeEditar): ?>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
const SUB_ID = <?php echo $subId; ?>;
const estado = document.getElementById('estado');
let sinGuardar = false;

function avisar(texto, esError) {
    estado.textContent = texto;
    estado.className = esError ? 'error' : '';
}

// Sube una imagen al servidor y la inserta en el editor. Devuelve true si salió bien.
async function subirYInsertar(archivo, indice) {
    const datos = new FormData();
    datos.append('imagen', archivo);
    datos.append('subbloque_id', SUB_ID);

    avisar('Subiendo imagen…', false);
    try {
        const resp = await fetch('subir_imagen.php', { method: 'POST', body: datos });
        const json = await resp.json();
        if (!resp.ok || !json.url) {
            throw new Error(json.error || 'No se pudo subir la imagen.');
        }
        quill.insertEmbed(indice, 'image', json.url, 'user');
        avisar('', false);
        return true;
    } catch (e) {
        avisar(e.message || 'No se pudo subir la imagen.', true);
        return false;
    }
}

// Botón de imagen de la barra de herramientas
async function seleccionarImagen() {
    const entrada = document.createElement('input');
    entrada.type = 'file';
    entrada.accept = 'image/jpeg,image/png,image/gif,image/webp';
    entrada.onchange = async () => {
        const archivo = entrada.files[0];
        if (!archivo) return;
        const rango = quill.getSelection(true);
        if (await subirYInsertar(archivo, rango.index)) {
            quill.setSelection(rango.index + 1, 0);
        }
    };
    entrada.click();
}

const quill = new Quill('#editor', {
    theme: 'snow',
    placeholder: 'Escribe el contenido de la lección…',
    modules: {
        toolbar: {
            container: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ color: [] }, { background: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ align: [] }],
                ['blockquote', 'code-block'],
                ['link', 'image'],
                ['clean']
            ],
            handlers: { image: seleccionarImagen }
        },
        // Imágenes arrastradas o pegadas: también se suben al servidor
        uploader: {
            mimetypes: ['image/png', 'image/jpeg', 'image/gif', 'image/webp'],
            handler: async (rango, archivos) => {
                let indice = rango ? rango.index : quill.getLength();
                for (const archivo of archivos) {
                    if (await subirYInsertar(archivo, indice)) indice += 1;
                }
                quill.setSelection(indice, 0);
            }
        }
    }
});

// No se aceptan imágenes incrustadas (data:) al pegar contenido de otras páginas
const Delta = Quill.import('delta');
quill.clipboard.addMatcher('img', (nodo, delta) => {
    const origen = nodo.getAttribute('src') || '';
    return origen.startsWith('data:') ? new Delta() : delta;
});

// Aviso si se intenta salir con cambios sin guardar
quill.on('text-change', (cambio, anterior, origen) => {
    if (origen === 'user') sinGuardar = true;
});
document.querySelector('.ed-titulo').addEventListener('input', () => { sinGuardar = true; });
window.addEventListener('beforeunload', (e) => {
    if (sinGuardar) { e.preventDefault(); e.returnValue = ''; }
});

// Al guardar, el contenido del editor pasa al formulario
document.getElementById('formulario').addEventListener('submit', () => {
    document.getElementById('contenido').value = quill.getSemanticHTML();
    sinGuardar = false;
});
</script>
<?php endif; ?>

</body>
</html>