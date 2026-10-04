<?php

const LIMITE_POKEMON = 200;
const DIR_CACHE = __DIR__ . '/cache/';


/**
 * Guarda la respuesta en cache/ para no repetir peticiones.
 * Si algo falla, devuelve null.
 */
function obtenerApi(string $url, int $segundosCache = 86400, int $timeout = 10, array $cabeceras = []): ?array
{
    if (!is_dir(DIR_CACHE)) {
        mkdir(DIR_CACHE, 0777, true);
    }

    $archivo = DIR_CACHE . md5($url) . '.json';

    if (file_exists($archivo) && (time() - filemtime($archivo)) < $segundosCache) {
        return json_decode(file_get_contents($archivo), true);
    }

    $textoCabeceras = "User-Agent: PokedexEscolar/1.0\r\n";
    foreach ($cabeceras as $nombre => $valor) {
        $textoCabeceras .= "$nombre: $valor\r\n";
    }

    $contexto = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'header'  => $textoCabeceras,
        ],
    ]);

    $respuesta = @file_get_contents($url, false, $contexto);

    if ($respuesta === false) {
        return null;
    }

    file_put_contents($archivo, $respuesta);
    return json_decode($respuesta, true);
}

function urlImagenPokemon(int $id): string
{
    return "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/{$id}.png";
}

/**
 * Devuelve los 200 Pokémon como lista de ['id' => 25, 'nombre' => 'pikachu'].
 */
function obtenerListaPokemon(): array
{
    $url = "https://pokeapi.co/api/v2/pokemon/?offset=0&limit=" . LIMITE_POKEMON;
    $datos = obtenerApi($url);

    if ($datos === null) {
        return [];
    }

    $lista = [];
    foreach ($datos['results'] as $pokemon) {
        $id = (int) basename(rtrim($pokemon['url'], '/'));
        $lista[] = [
            'id'     => $id,
            'nombre' => $pokemon['name'],
        ];
    }

    return $lista;
}

/**
 * Devuelve las evoluciones agrupadas por etapas:
 * [
 *   [ ['id' => 1, 'nombre' => 'bulbasaur'] ],
 *   [ ['id' => 2, 'nombre' => 'ivysaur'] ],
 *   [ ['id' => 3, 'nombre' => 'venusaur'] ],
 * ]
 */
function obtenerEvoluciones(array $especie): array
{
    $cadena = obtenerApi($especie['evolution_chain']['url']);

    if ($cadena === null) {
        return [];
    }

    $etapas = [];
    $nivelActual = [$cadena['chain']];

    while (!empty($nivelActual)) {
        $etapa = [];
        $siguiente = [];

        foreach ($nivelActual as $nodo) {
            $etapa[] = [
                'id'     => (int) basename(rtrim($nodo['species']['url'], '/')),
                'nombre' => $nodo['species']['name'],
            ];

            //guardamos las evoluciones
            foreach ($nodo['evolves_to'] as $hijo) {
                $siguiente[] = $hijo;
            }
        }

        $etapas[] = $etapa;
        $nivelActual = $siguiente;
    }

    return $etapas;
}


/**
 * mapa id => lista de tipos, ej. [1 => ['grass', 'poison'], 4 => ['fire']].
 */
function obtenerTiposPorId(): array
{
    $tipos = ['normal', 'fire', 'water', 'electric', 'grass', 'ice', 'fighting', 'poison',
              'ground', 'flying', 'psychic', 'bug', 'rock', 'ghost', 'dragon', 'dark',
              'steel', 'fairy'];

    $mapa = [];

    foreach ($tipos as $tipo) {
        $datos = obtenerApi("https://pokeapi.co/api/v2/type/{$tipo}");

        if ($datos === null) {
            continue;
        }

        foreach ($datos['pokemon'] as $entrada) {
            $id = (int) basename(rtrim($entrada['pokemon']['url'], '/'));

            if ($id > LIMITE_POKEMON) {
                continue;
            }
            $mapa[$id][(int) $entrada['slot']] = $tipo;
        }
    }

    foreach ($mapa as &$lista) {
        ksort($lista);
        $lista = array_values($lista);
    }
    unset($lista);

    return $mapa;
}

function pokeballSvg(): string
{
    return '<svg class="pokeball" viewBox="0 0 100 100" aria-hidden="true">'
         . '<circle cx="50" cy="50" r="46" fill="#fff" stroke="#2b2f55" stroke-width="6"/>'
         . '<path d="M4 50a46 46 0 0 1 92 0z" fill="#ef5b5b" stroke="#2b2f55" stroke-width="6" stroke-linejoin="round"/>'
         . '<line x1="4" y1="50" x2="96" y2="50" stroke="#2b2f55" stroke-width="6"/>'
         . '<circle cx="50" cy="50" r="13" fill="#fff" stroke="#2b2f55" stroke-width="6"/>'
         . '</svg>';
}