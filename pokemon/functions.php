
<?php
$url = "https://pokeapi.co/api/v2/pokemon/?offset=0&limit=200";

$respuesta = file_get_contents($url);
$datos = json_decode($respuesta, true);
foreach ($datos['results'] as $pokemon) {
    echo $pokemon['name'] . "<br>";
}