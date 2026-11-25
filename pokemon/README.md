# Pokédex en PHP

Pokédex web hecha en PHP puro (sin frameworks ni base de datos) que consume la [PokeAPI](https://pokeapi.co/) para mostrar los primeros **200 Pokémon**, con buscador, paginación, ficha de detalle y cadena de evoluciones.

## Estructura

```
pokemon/
├── index.php      # Lista de Pokémon: rejilla de tarjetas, búsqueda y botón "Ver más"
├── detalle.php    # Ficha de un Pokémon: tipos, descripción, datos, habilidades, stats y evoluciones
├── functions.php  # Funciones compartidas: llamadas a la API con caché, listas, tipos y evoluciones
├── style.css      # Estilos (colores por tipo, tarjetas, barras de estadísticas)
├── prueba.php     # Script de prueba: imprime el total y los primeros 5 Pokémon
└── cache/         # Respuestas JSON de la API guardadas en disco (se crea sola)
```

## Funcionamiento

- **`obtenerApi($url)`** ([functions.php](functions.php)): hace la petición HTTP con `file_get_contents` y guarda la respuesta en `cache/<md5(url)>.json`. Durante 24 h las siguientes peticiones a la misma URL se leen del disco, sin volver a llamar a la API. Si la petición falla, devuelve `null`.
- **Lista (`index.php`)**:
  - Obtiene los 200 Pokémon (`/pokemon?limit=200`) y sus tipos (consulta los 18 endpoints `/type/{tipo}`) para darle a cada tarjeta el color y los iconos de su tipo.
  - Muestra 20 por página; "Ver más" suma 20 más (`?cantidad=40`, `60`, …) y lleva la vista a la última tarjeta que se veía.
  - `?buscar=nombre` filtra por coincidencia parcial del nombre.
- **Detalle (`detalle.php?id=N`)**:
  - Solo acepta ids del 1 al 200.
  - Consulta `/pokemon/{id}` y `/pokemon-species/{id}` para obtener la descripción y la categoría (en inglés), la altura, el peso, la experiencia base, las habilidades (marca las ocultas) y las estadísticas base con barras.
  - Recorre la cadena de evolución por etapas. Las evoluciones con un id mayor a 200 se muestran, pero sin enlace.
  - "Volver a la lista" regresa a la misma posición de la lista.
- Las imágenes son el *official artwork* del repositorio de sprites de PokeAPI en GitHub.

## Cómo correrlo

Requisitos: **PHP 7.4 o superior** (usa funciones flecha y tipos de retorno *nullable*) con `allow_url_fopen` activado (viene activado por defecto) y conexión a internet.

```bash
cd pokemon
php -S localhost:8000
```

Abre <http://localhost:8000> en el navegador.

También funciona copiando la carpeta a `htdocs/` en XAMPP o a `/var/www/html/` en Apache. La carpeta `cache/` debe tener permisos de escritura.

> La primera carga tarda unos segundos porque se descargan la lista y los 18 tipos. Después se sirve desde `cache/`. Para forzar datos nuevos, borra los archivos de esa carpeta.
