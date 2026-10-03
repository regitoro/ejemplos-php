<?php

const LIMITE_POKEMON = 200;
const DIR_CACHE = __DIR__ . '/cache/';

/**
 * Pide una URL a una API y devuelve el JSON como arreglo.
 * Guarda la respuesta en cache/ para no repetir peticiones.
 * Si algo falla, devuelve null.
 */
function obtenerApi(string $url, int $segundosCache = 86400): ?array
{
    if (!is_dir(DIR_CACHE)) {
        mkdir(DIR_CACHE, 0777, true);
    }

    $archivo = DIR_CACHE . md5($url) . '.json';

    // Si existe en caché y no está vencido, lo usamos
    if (file_exists($archivo) && (time() - filemtime($archivo)) < $segundosCache) {
        return json_decode(file_get_contents($archivo), true);
    }

    $contexto = stream_context_create([
        'http' => [
            'timeout' => 10,
            'header'  => "User-Agent: PokedexEscolar/1.0\r\n",
        ],
    ]);

    $respuesta = @file_get_contents($url, false, $contexto);

    if ($respuesta === false) {
        return null;
    }

    file_put_contents($archivo, $respuesta);
    return json_decode($respuesta, true);
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
        // La URL termina en .../pokemon/25/, de ahí sacamos el id
        $id = (int) basename(rtrim($pokemon['url'], '/'));
        $lista[] = [
            'id'     => $id,
            'nombre' => $pokemon['name'],
        ];
    }

    return $lista;
}