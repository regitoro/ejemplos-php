<?php
    
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $cliente = trim((string) ($_POST["cliente"] ?? ""));
        $producto = trim((string) ($_POST["producto"] ?? ""));
        $precio = (float) ($_POST["precio"] ?? 0);
        $cantidad = (int) ($_POST["cantidadSeleccionada"] ?? 0);

        

        if (
            $cliente === "" ||
            $producto === "" ||
            $precio === 0 ||
            $cantidad === 0
        ) {
            echo "<p style='color: red;'>Ingresa los datos</p>";
        } else {
            echo "<h2>Datos registrados</h2>";
            echo "<p><strong>Cliente</strong> " . htmlspecialchars($cliente, ENT_QUOTES, "UTF-8") . "</p>";
            echo "<p><strong>Producto:</strong> " . htmlspecialchars($producto, ENT_QUOTES, "UTF-8") . "</p>";
            echo "<p><strong>Precio:</strong> " . number_format($precio, 2) . "</p>"; 
            echo "<p><strong>Cantidad:</strong> " . number_format($cantidad, 0) . "</p>";
            $total = $cantidad * $precio;
                if($total > 500){
                    $TotalDescuento = $total*.90;
                    echo "<p>Descuento del 10% aplicado</p>";
                    echo "<p><strong>Total:</strong> " . number_format($TotalDescuento, 2) . "</p>";
                    
                        }else{
                    echo "<p><strong>Total:</strong> " . number_format($total, 2) . "</p>";
                        }    
            
        }
    }

    echo "<br>";
    echo "<a href='index.html'>Nueva compra</a>";
    ?>