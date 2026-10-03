<?php
require 'functions.php';

const POR_PAGINA = 20;

function e($texto): string
{
    return htmlspecialchars((string) $texto);
}

// Validamos el id: solo del 1 al 200
$id = (int) ($_GET['id'] ?? 0);
$pokemon = null;
$especie = null;

if ($id >= 1 && $id <= LIMITE_POKEMON) {
    $pokemon = obtenerApi("https://pokeapi.co/api/v2/pokemon/{$id}");
    $especie = obtenerApi("https://pokeapi.co/api/v2/pokemon-species/{$id}");
    $etapas = $especie !== null ? obtenerEvoluciones($especie) : [];
}

//enlace de regreso: vuelve a la lista justo en la carta que se abrió
$cantidadVolver = (int) (ceil(max(1, $id) / POR_PAGINA) * POR_PAGINA);
$urlVolver = "index.php?cantidad={$cantidadVolver}#carta-{$id}";

// Descripción y categoría 
$descripcion = '';
$categoria = '';

if ($especie !== null) {
    foreach ($especie['flavor_text_entries'] as $entrada) {
        if ($entrada['language']['name'] === 'en') {
            $descripcion = str_replace(["\n", "\f"], ' ', $entrada['flavor_text']);
            break;
        }
    }
    foreach ($especie['genera'] as $genero) {
        if ($genero['language']['name'] === 'en') {
            $categoria = $genero['genus'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $pokemon ? e(ucfirst($pokemon['name'])) . ' - Pokédex' : 'Pokédex' ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="tema-<?= $pokemon ? e($pokemon['types'][0]['type']['name']) : 'normal' ?>">

<?php if ($pokemon === null): ?>

    <p class="error">No se pudo cargar ese Pokémon. Revisa el id (del 1 al <?= LIMITE_POKEMON ?>) o intenta de nuevo.</p>
    <p class="centrado"><a class="boton" href="index.php">Volver a la lista</a></p>

<?php else: ?>

    <p class="volver"><a href="<?= e($urlVolver) ?>">&larr; Volver a la lista</a></p>

    <div class="detalle">
        <div class="detalle-imagen">
            <img src="<?= urlImagenPokemon($id) ?>" alt="<?= e($pokemon['name']) ?>">
        </div>

        <div class="detalle-info">
            <h1>
                <?= e(ucfirst(str_replace('-', ' ', $pokemon['name']))) ?>
                <span class="numero">#<?= str_pad($id, 3, '0', STR_PAD_LEFT) ?></span>
            </h1>

            <?php if ($categoria !== ''): ?>
                <p class="categoria"><?= e($categoria) ?></p>
            <?php endif; ?>

            <div class="tipos">
                <?php foreach ($pokemon['types'] as $t): ?>
                    <?php $tipo = $t['type']['name']; ?>
                    <span class="tipo tipo-<?= e($tipo) ?>"><?= e(ucfirst($tipo)) ?></span>
                <?php endforeach; ?>
            </div>

            <?php if ($descripcion !== ''): ?>
                <p class="descripcion"><?= e($descripcion) ?></p>
            <?php endif; ?>

            <div class="datos">
                <div><strong>Altura</strong><br><?= number_format($pokemon['height'] / 10, 1) ?> m</div>
                <div><strong>Peso</strong><br><?= number_format($pokemon['weight'] / 10, 1) ?> kg</div>
                <div><strong>Exp. base</strong><br><?= e($pokemon['base_experience']) ?></div>
            </div>

            <h2>Habilidades</h2>
            <ul class="habilidades">
                <?php foreach ($pokemon['abilities'] as $h): ?>
                    <li>
                        <?= e(ucfirst(str_replace('-', ' ', $h['ability']['name']))) ?>
                        <?= $h['is_hidden'] ? '<small>(oculta)</small>' : '' ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <h2>Estadísticas base</h2>
            <?php foreach ($pokemon['stats'] as $s): ?>
                <?php
                    $n = $s['stat']['name'];
                    $nombreStat = ($n === 'hp') ? 'HP' : ucfirst(str_replace('-', ' ', $n));
                    $porcentaje = min(100, round($s['base_stat'] / 255 * 100));
                ?>
                <div class="stat">
                    <span class="stat-nombre"><?= e($nombreStat) ?></span>
                    <span class="stat-valor"><?= e($s['base_stat']) ?></span>
                    <div class="stat-barra"><div style="width: <?= $porcentaje ?>%"></div></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <section class="evoluciones">
    <h2>Evoluciones</h2>

    <?php if (count($etapas) <= 1): ?>
        <p>Este Pokémon no tiene evoluciones.</p>
    <?php else: ?>
        <div class="cadena">
            <?php foreach ($etapas as $i => $etapa): ?>

                <?php if ($i > 0): ?>
                    <div class="flecha">&rarr;</div>
                <?php endif; ?>

                <div class="etapa">
                    <?php foreach ($etapa as $evo): ?>
                        <?php
                            $esActual = ($evo['id'] === $id);
                            $dentroDelLimite = ($evo['id'] <= LIMITE_POKEMON);
                            // Solo es enlace si es otro Pokémon y está entre los 200
                            $etiqueta = ($dentroDelLimite && !$esActual) ? 'a' : 'div';
                        ?>
                        <<?= $etiqueta ?> class="evo <?= $esActual ? 'actual' : '' ?>"
                            <?= $etiqueta === 'a' ? 'href="detalle.php?id=' . $evo['id'] . '"' : '' ?>>
                            <img src="<?= urlImagenPokemon($evo['id']) ?>"
                                 alt="<?= e($evo['nombre']) ?>"
                                 loading="lazy">
                            <span><?= e(ucfirst(str_replace('-', ' ', $evo['nombre']))) ?></span>
                            <?php if (!$dentroDelLimite): ?>
                                <small>#<?= $evo['id'] ?> (fuera de la lista)</small>
                            <?php endif; ?>
                        </<?= $etiqueta ?>>
                    <?php endforeach; ?>
                </div>

            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php endif; ?>

</body>
</html>