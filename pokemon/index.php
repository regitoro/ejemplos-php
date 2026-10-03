<?php
require 'functions.php';

const POR_PAGINA = 20;

$iconosTipo = [//para que se mas cool
    'normal' => '⭐', 'fire' => '🔥', 'water' => '💧', 'electric' => '⚡',
    'grass' => '🍃', 'ice' => '❄️', 'fighting' => '🥊', 'poison' => '☠️',
    'ground' => '⛰️', 'flying' => '🕊️', 'psychic' => '🔮', 'bug' => '🐛',
    'rock' => '🪨', 'ghost' => '👻', 'dragon' => '🐉', 'dark' => '🌙',
    'steel' => '⚙️', 'fairy' => '✨',
];

$lista = obtenerListaPokemon();
$tiposPorId = empty($lista) ? [] : obtenerTiposPorId();
$busqueda = trim($_GET['buscar'] ?? '');

if ($busqueda !== '') {
    $termino = strtolower(str_replace(' ', '-', $busqueda));

    $visibles = array_values(array_filter(
        $lista,
        fn($p) => strpos($p['nombre'], $termino) !== false
    ));
    $haySiguiente = false;
} else {
    $cantidad = (int) ($_GET['cantidad'] ?? POR_PAGINA);
    $cantidad = max(POR_PAGINA, min(LIMITE_POKEMON, $cantidad));
    $cantidad = (int) (ceil($cantidad / POR_PAGINA) * POR_PAGINA);

    $visibles = array_slice($lista, 0, $cantidad);
    $haySiguiente = $cantidad < count($lista);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pokédex</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="pagina-lista">

    <header class="barra">
        <a class="marca" href="index.php"><?= pokeballSvg() ?><span>Pokédex</span></a>
    </header>

    <main>
        <div class="encabezado">
            <h1>Pokédex</h1>
            <div class="divisor"><span></span><?= pokeballSvg() ?><span></span></div>
        </div>

        <?php if (empty($lista)): ?>
            <p class="error">No se pudo cargar la lista de Pokémon. Intenta de nuevo en un momento.</p>
        <?php else: ?>

            <form class="buscar" method="GET" action="index.php">
                <label class="campo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
                    </svg>
                    <input type="text" name="buscar"
                           placeholder="Buscar Pokémon por nombre..."
                           value="<?= htmlspecialchars($busqueda) ?>">
                </label>
                <button type="submit"><?= pokeballSvg() ?>Buscar</button>
                <?php if ($busqueda !== ''): ?>
                    <a class="limpiar" href="index.php">Limpiar</a>
                <?php endif; ?>
            </form>

            <?php if (empty($visibles)): ?>
                <p class="centrado">No se encontró ningún Pokémon con "<?= htmlspecialchars($busqueda) ?>".</p>
            <?php else: ?>

                <div class="rejilla">
                    <?php foreach ($visibles as $pokemon): ?>
                        <?php
                            $tipos = $tiposPorId[$pokemon['id']] ?? ['normal'];
                            $nombre = ucfirst(str_replace('-', ' ', $pokemon['nombre']));
                        ?>
                        <a class="tarjeta tema-<?= htmlspecialchars($tipos[0]) ?>"
                           id="carta-<?= $pokemon['id'] ?>"
                           href="detalle.php?id=<?= $pokemon['id'] ?>">

                            <span class="insignia"><?= pokeballSvg() ?>#<?= str_pad($pokemon['id'], 3, '0', STR_PAD_LEFT) ?></span>

                            <div class="tarjeta-img">
                                <img src="<?= urlImagenPokemon($pokemon['id']) ?>"
                                     alt="<?= htmlspecialchars($pokemon['nombre']) ?>"
                                     loading="lazy">
                            </div>

                            <div class="tarjeta-pie">
                                <span class="tarjeta-nombre"><?= htmlspecialchars($nombre) ?></span>
                                <span class="iconos">
                                    <?php foreach ($tipos as $t): ?>
                                        <span class="icono" title="<?= htmlspecialchars($t) ?>"><?= $iconosTipo[$t] ?? '•' ?></span>
                                    <?php endforeach; ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if ($busqueda !== ''): ?>
                    <p class="contador"><?= count($visibles) ?> resultado(s) para "<?= htmlspecialchars($busqueda) ?>"</p>
                <?php else: ?>
                    <p class="contador">Mostrando <?= count($visibles) ?> de <?= count($lista) ?></p>
                <?php endif; ?>

                <?php if ($haySiguiente): ?>
                    <p class="centrado">
                        <a class="boton"
                           href="?cantidad=<?= $cantidad + POR_PAGINA ?>#carta-<?= $lista[$cantidad]['id'] ?>">Ver más</a>
                    </p>
                <?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>
    </main>

</body>
</html>