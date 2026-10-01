<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Registro de compras</title>

</head>

<body>

    <h1>Registro de compras</h1>

    <form method="POST" action="venta.php">

        <label for="cliente">CLIENTE:</label>
        <input type="text" id="cliente" name="cliente" required>

        <br><br>

        <h2>Selecciona un producto</h2>

        <div class="productos">

            <!--pizza-->
            <div class="producto">

                <img src="imagenes/pizza.jpeg" alt="Pizza">

                <h3>Pizza</h3>
                <p>Precio: $150.00</p>

                <label>Cantidad:</label>
                <input type="number" name="cantidad" min="1" value="1">
                <br><br>

                <button type="button" onclick="seleccionarProducto('Pizza', 150, this)">
                    Seleccionar
                </button>

            </div>


            <!--hamburgedsa-->
            <div class="producto">

                <img src="imagenes/hamburguesa.jpeg" alt="Hamburguesa">

                <h3>Hamburguesa</h3>
                <p>Precio: $120.00</p>

                <label>Cantidad:</label>
                <input type="number" name="cantidad" min="1" value="1">
                <br><br>

                <button type="button" onclick="seleccionarProducto('Hamburguesa', 120, this)">
                    Seleccionar
                </button>

            </div>


            <!--coke-->
            <div class="producto">

                <img src="imagenes/refresco.jpeg" alt="Refresco">

                <h3>Refresco</h3>
                <p>Precio: $30.00</p>

                <label>Cantidad:</label>
                <input type="number" name="cantidad" min="1" value="1">
                <br><br>

                <button type="button" onclick="seleccionarProducto('Refresco', 30, this)">
                    Seleccionar
                </button>

            </div>

        </div>

        <br>

        <input type="hidden" id="producto" name="producto">
        <input type="hidden" id="precio" name="precio">
        <input type="hidden" id="cantidadSeleccionada" name="cantidadSeleccionada">

        <p id="seleccion"></p>

        <button type="submit">CALCULAR</button>

    </form>


    <script>

        function seleccionarProducto(nombre, precio, boton) {

            document.getElementById("producto").value = nombre;
            document.getElementById("precio").value = precio;

            let cantidad = boton.parentElement.querySelector(
                'input[name="cantidad"]'
            ).value;

            document.getElementById("cantidadSeleccionada").value = cantidad;

            document.getElementById("seleccion").innerHTML =
                "Seleccionaste <strong>" + cantidad + " x " + nombre + "</strong>";

        }

    </script>

</body>
</html>