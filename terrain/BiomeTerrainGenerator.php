<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\terrain;

use pocketmine\world\ChunkManager;
use pocketmine\utils\Random;

interface BiomeTerrainGenerator {
    
    /**
     * Generiert das Terrain für eine bestimmte Spalte (x, z Position)
     * 
     * @param ChunkManager $world Der ChunkManager
     * @param Random $random Zufallsgenerator
     * @param int $x X-Koordinate
     * @param int $z Z-Koordinate  
     * @param int $biome_id Die Biom-ID
     * @param float $surface_noise Surface-Noise-Wert für diese Position
     * @param array $density Density-Array für das Terrain
     * @param int $sea_level Meeresspiegel (Standard: 64)
     */
    public function generateTerrainColumn(
        ChunkManager $world,
        Random $random,
        int $x,
        int $z,
        int $biome_id,
        float $surface_noise,
        array $density,
        int $sea_level = 64
    ): void;
    
    /**
     * Gibt die Priorität dieses Terrain-Generators zurück
     * Höhere Werte = höhere Priorität bei Konflikten
     */
    public function getPriority(): int;
    
    /**
     * Gibt den Namen dieses Terrain-Generators zurück
     */
    public function getName(): string;
}