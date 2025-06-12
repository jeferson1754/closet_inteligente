<?php

function determinarCategoriaId($nombrePrenda)
{
    $nombre = strtolower($nombrePrenda);

    if (str_contains($nombre, 'jeans') || str_contains($nombre, 'pantalón') || str_contains($nombre, 'falda') || str_contains($nombre, 'short')) {
        return 2; // parte_inferior
    } elseif (str_contains($nombre, 'zapatilla') || str_contains($nombre, 'zapato') || str_contains($nombre, 'bota') || str_contains($nombre, 'sandalia')) {
        return 3; // calzado
    } elseif (str_contains($nombre, 'polera') || str_contains($nombre, 'camisa') || str_contains($nombre, 'blusa') || str_contains($nombre, 'chaqueta') || str_contains($nombre, 'abrigo')) {
        return 1; // parte_superior
    } else {
        return 4; // accesorio
    }
}
