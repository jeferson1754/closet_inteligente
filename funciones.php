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


// --- NEW FUNCTION: Get Usage Limit and Status ---
/**
 * Determines the maximum allowed uses for a garment type and checks if it's overused.
 *
 * @param string $garmentType The type of garment (e.g., 'polera', 'pantalon').
 * @param int $currentUses The current number of uses for the garment.
 * @return array An associative array with 'max_uses' and 'is_overused' (boolean).
 */
function getUsageLimitStatus(string $garmentType, int $currentUses): array
{
    $garmentTypeLower = strtolower($garmentType);
    $maxUses = 2; // Default limit for most garments

    // Define usage limits by garment type
    if ($garmentTypeLower === 'polera') {
        $maxUses = 1; // Poleras and first layers: 1 use
    } elseif ($garmentTypeLower === 'pantalon' || $garmentTypeLower === 'short'|| $garmentTypeLower === 'zapatillas') {
        $maxUses = 3; // Pants and shorts: 3 uses
    }
    // For other types, it keeps the default $maxUses = 2;

    $isOverused = ($currentUses >= $maxUses);

    return [
        'max_uses' => $maxUses,
        'is_overused' => $isOverused
    ];
}
