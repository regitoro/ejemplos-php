<!-- contenido de los sublloques --><?php
/**
 * Utilidades para el contenido de las lecciones (HTML escrito con el editor).
 */

// ¿El texto trae etiquetas HTML? Si no, es texto plano de antes del editor.
function esHtml(string $texto): bool
{
    return $texto !== strip_tags($texto);
}

// Estilos permitidos en un atributo style: solo color y color de fondo
function estiloSeguro(string $css): ?string
{
    $color = '(#[0-9a-fA-F]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*[0-9.]+\s*)?\)|[a-zA-Z]{3,20})';
    $salida = [];

    foreach (explode(";", $css) as $declaracion) {
        $partes = explode(":", $declaracion, 2);
        if (count($partes) !== 2) {
            continue;
        }
        $propiedad = strtolower(trim($partes[0]));
        $valor     = trim($partes[1]);

        if (in_array($propiedad, ["color", "background-color"], true)
            && preg_match('/^' . $color . '$/', $valor)) {
            $salida[] = $propiedad . ": " . $valor;
        }
    }

    return $salida ? implode("; ", $salida) : null;
}

// Deja en un elemento solo los atributos permitidos
function limpiarAtributos(DOMElement $el, string $etiqueta, array $permitidos): void
{
    foreach (iterator_to_array($el->attributes) as $attr) {
        $nombre = strtolower($attr->nodeName);
        $valor  = trim($attr->nodeValue);
        $nuevo  = null;

        if ($nombre === "class") {
            // Solo las clases del editor (alineación, etc.)
            $clases = array_filter(
                preg_split('/\s+/', $valor),
                fn($c) => preg_match('/^ql-[a-z0-9-]+$/', $c)
            );
            $nuevo = $clases ? implode(" ", $clases) : null;

        } elseif ($nombre === "style" && in_array($etiqueta, ["span", "p", "li", "h1", "h2", "h3"], true)) {
            $nuevo = estiloSeguro($valor);

        } elseif (in_array($nombre, $permitidos, true)) {
            if ($nombre === "href") {
                // Solo enlaces http, https o de correo
                $nuevo = preg_match('#^(https?://|mailto:)#i', $valor) ? $valor : null;
            } elseif ($nombre === "src") {
                // Solo imágenes subidas por el propio sistema
                $nuevo = preg_match('#^/uploads/lecciones/[a-f0-9]{32}\.(jpg|png|gif|webp)$#', $valor) ? $valor : null;
            } elseif ($nombre === "alt") {
                $nuevo = substr($valor, 0, 200);
            }
        }

        if ($nuevo === null) {
            $el->removeAttribute($attr->nodeName);
        } else {
            $el->setAttribute($attr->nodeName, $nuevo);
        }
    }
}

// Limpia los hijos de un nodo, de forma recursiva
function limpiarNodo(DOMNode $nodo, array $permitidas, array $eliminar): void
{
    foreach (iterator_to_array($nodo->childNodes) as $hijo) {

        if ($hijo->nodeType === XML_TEXT_NODE) {
            continue;
        }
        if ($hijo->nodeType !== XML_ELEMENT_NODE) {
            $nodo->removeChild($hijo);          // comentarios y otros
            continue;
        }

        $etiqueta = strtolower($hijo->nodeName);

        // Etiquetas peligrosas: se borran con todo su contenido
        if (in_array($etiqueta, $eliminar, true)) {
            $nodo->removeChild($hijo);
            continue;
        }

        limpiarNodo($hijo, $permitidas, $eliminar);

        // Etiqueta no permitida: se conserva su contenido, sin la etiqueta
        if (!isset($permitidas[$etiqueta])) {
            while ($hijo->firstChild) {
                $nodo->insertBefore($hijo->firstChild, $hijo);
            }
            $nodo->removeChild($hijo);
            continue;
        }

        limpiarAtributos($hijo, $etiqueta, $permitidas[$etiqueta]);

        // Una imagen sin una fuente válida no sirve
        if ($etiqueta === "img" && !$hijo->hasAttribute("src")) {
            $nodo->removeChild($hijo);
            continue;
        }

        // Los enlaces siempre se abren en otra pestaña y sin dar acceso a esta
        if ($etiqueta === "a" && $hijo->hasAttribute("href")) {
            $hijo->setAttribute("target", "_blank");
            $hijo->setAttribute("rel", "noopener noreferrer");
        }
    }
}

// Limpia un HTML: solo deja las etiquetas y atributos permitidos
function limpiarHtml(string $html): string
{
    if (trim($html) === "") {
        return "";
    }

    $permitidas = [
        "p" => [], "br" => [], "strong" => [], "b" => [], "em" => [], "i" => [],
        "u" => [], "s" => [], "h1" => [], "h2" => [], "h3" => [],
        "ul" => [], "ol" => [], "li" => [], "blockquote" => [],
        "pre" => [], "code" => [], "span" => [],
        "a" => ["href"], "img" => ["src", "alt"],
    ];
    $eliminar = [
        "script", "style", "iframe", "object", "embed", "form", "input", "button",
        "textarea", "select", "link", "meta", "svg", "math", "video", "audio",
        "noscript", "template",
    ];

    $dom = new DOMDocument();
    $previo = libxml_use_internal_errors(true);
    $dom->loadHTML(
        '<?xml encoding="UTF-8"><div>' . $html . '</div>',
        LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previo);

    $raiz = $dom->getElementsByTagName("div")->item(0);
    if (!$raiz) {
        return "";
    }

    limpiarNodo($raiz, $permitidas, $eliminar);

    $salida = "";
    foreach ($raiz->childNodes as $hijo) {
        $salida .= $dom->saveHTML($hijo);
    }
    return trim($salida);
}

// ¿El contenido no tiene ni texto ni imágenes?
function contenidoVacio(string $html): bool
{
    return trim(strip_tags(str_replace("&nbsp;", " ", $html))) === ""
        && stripos($html, "<img") === false;
}

// HTML listo para MOSTRAR (siempre limpio)
function htmlParaMostrar(?string $contenido): string
{
    $contenido = (string) $contenido;
    if (trim($contenido) === "") {
        return "";
    }
    return esHtml($contenido) ? limpiarHtml($contenido) : nl2br(htmlspecialchars($contenido));
}

// HTML listo para cargar en el EDITOR
function htmlParaEditor(?string $contenido): string
{
    $contenido = (string) $contenido;
    if (trim($contenido) === "") {
        return "";
    }
    if (esHtml($contenido)) {
        return limpiarHtml($contenido);
    }
    // Texto plano de antes del editor: una línea = un párrafo
    return implode("", array_map(
        fn($linea) => "<p>" . htmlspecialchars($linea) . "</p>",
        preg_split('/\R/', $contenido)
    ));
}