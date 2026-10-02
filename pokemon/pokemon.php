
<?php

$pokemon = $_GET['pokemon'] ?? "pikachu";

$url = "https://pokeapi.co/api/v2/pokemon/" . strtolower($pokemon);

$respuesta = file_get_contents($url);

$datos = json_decode($respuesta, true);

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Pokedex shida</title>
</head>

<body>
    <h1>Pokémon</h1>
    <form method="GET">
        <input
            type="text"
            name="pokemon"
            placeholder="Ingrese el nombre del Pokémon">
        <button type="submit">Buscar</button>
    </form>
    <hr>

    <h2>
        <?php echo $datos['name']; ?>
    </h2>

    <img src="<?php echo $datos['sprites']['front_default']; ?>" width="150">

    <p>
        <p>Tipo: <?php echo $datos["types"][0]["type"]["name"] ; ?> 
    </p>
    <p>
        Altura:
        <?php echo $datos['height']; ?>
    </p>
    <p>
        Peso:
        <?php echo $datos['weight']; ?>
    </p>

</body>

</html>