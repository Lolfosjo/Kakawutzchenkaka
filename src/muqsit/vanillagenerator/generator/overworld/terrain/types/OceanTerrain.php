<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\terrain\types;

use muqsit\vanillagenerator\generator\overworld\terrain\BiomeTerrainGenerator;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\utils\Random;

/**
 * Ozean-Terrain - eingebaut in den OverworldGenerator
 */
class OceanTerrain implements BiomeTerrainGenerator {
    
    public function generateTerrainColumn(
        ChunkManager $world,
        Random $random,
        int $x,
        int $z,
        int $biome_id,
        float $surface_noise,
        array $density,
        int $sea_level = 64
    ): void {
        $chunk_x = $x >> Chunk::COORD_BIT_SIZE;
        $chunk_z = $z >> Chunk::COORD_BIT_SIZE;
        $chunk = $world->getChunk($chunk_x, $chunk_z);
        
        $local_x = $x & (Chunk::EDGE_LENGTH - 1);
        $local_z = $z & (Chunk::EDGE_LENGTH - 1);
        
        // Ozean-spezifische Blöcke - NUR Basis-Struktur
        $water = VanillaBlocks::WATER()->getStillForm()->getStateId();
        $sandstone = VanillaBlocks::SANDSTONE()->getStateId();
        $stone = VanillaBlocks::STONE()->getStateId();
        $air = VanillaBlocks::AIR()->getStateId();
        
        $min_y = $world->getMinY();
        $max_y = $world->getMaxY();
        
        // Ermittle Ozean-Boden-Höhe (normalerweise tiefer als Meeresspiegel)
        $ocean_floor = $this->calculateOceanFloor($density, $min_y, $sea_level, $surface_noise);
        
        // Generiere die Ozean-Säule - NUR Basis-Struktur
        for ($y = $min_y; $y < $max_y; $y++) {
            $y_block_pos = $y & 0xf;
            $sub_chunk = $chunk->getSubChunk($y >> Chunk::COORD_BIT_SIZE);
            
            if ($y < $ocean_floor - 5) {
                // Tiefer Untergrund: Stein
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $stone);
            } elseif ($y <= $ocean_floor) {
                // Ozean-Boden-Bereich: Sandstein (GroundGenerator ersetzt später mit Sand/Kies)
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $sandstone);
            } elseif ($y < $sea_level) {
                // Wasser bis zum Meeresspiegel
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $water);
            } else {
                // Luft oberhalb des Meeresspiegels
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $air);
            }
        }
    }
    
    /**
     * Berechnet die Ozean-Boden-Höhe
     */
    /**
 * Berechnet die Ozean-Boden-Höhe (Vanilla-ähnlich)
 */
private function calculateOceanFloor(array $density, int $min_y, int $sea_level, float $surface_noise): int {
    // Basis-Tiefe: ca. 10–15 Blöcke unter Meeresspiegel
    $base_depth = $sea_level - 12;

    // Variation nur leicht (±2 bis 3 Blöcke)
    $variation = (int)($surface_noise * 3);

    return max($min_y + 5, $base_depth + $variation);
}
    
    public function getPriority(): int {
        return 10; // Höhere Priorität für Ozean-Biome
    }
    
    public function getName(): string {
        return "Ocean Terrain";
    }
}