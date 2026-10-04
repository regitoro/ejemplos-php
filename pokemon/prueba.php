<?php
require 'functions.php';

$lista = obtenerListaPokemon();

echo "Total: " . count($lista) . "<br>";
foreach (array_slice($lista, 0, 5) as $p) {
    echo $p['id'] . " - " . $p['nombre'] . "<br>";
}
