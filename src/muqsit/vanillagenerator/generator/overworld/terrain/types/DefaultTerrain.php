<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\terrain\types;

use muqsit\vanillagenerator\generator\overworld\terrain\BiomeTerrainGenerator;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\utils\Random;

/**
 * Standard-Fallback-Terrain für alle Biome ohne spezifisches Terrain
 */
class DefaultTerrain implements BiomeTerrainGenerator {
    
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
        
        // Standard-Terrain: Nur die Basis-Struktur, KEINE Oberflächenblöcke
        $stone = VanillaBlocks::STONE()->getStateId();
        $water = VanillaBlocks::WATER()->getStillForm()->getStateId();
        $air = VanillaBlocks::AIR()->getStateId();
        
        $min_y = $world->getMinY();
        $max_y = $world->getMaxY();
        
        // Ermittle die Oberflächen-Höhe basierend auf der Density
        $surface_height = $this->calculateSurfaceHeight($density, $min_y, $max_y, $sea_level);
        
        // Generiere nur die Basis-Terrain-Struktur
        for ($y = $min_y; $y < $max_y; $y++) {
            $y_block_pos = $y & 0xf;
            $sub_chunk = $chunk->getSubChunk($y >> Chunk::COORD_BIT_SIZE);
            
            if ($y > $surface_height) {
                // Oberhalb der Oberfläche
                if ($y < $sea_level) {
                    $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $water);
                } else {
                    $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $air);
                }
            } else {
                // Unterhalb/auf der Oberfläche: Stein (GroundGenerator überschreibt dann die Oberflächenblöcke)
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $stone);
            }
        }
    }
    
    /**
     * Berechnet die Oberflächen-Höhe basierend auf dem Density-Array
     */
    private function calculateSurfaceHeight(array $density, int $min_y, int $max_y, int $sea_level): int {
        // Vereinfachte Berechnung - kann je nach Bedarf angepasst werden
        // Findet den höchsten "soliden" Block
        for ($y = $max_y - 1; $y >= $min_y; $y--) {
            $density_index = $y - $min_y;
            if (isset($density[$density_index]) && $density[$density_index] > 0) {
                return $y;
            }
        }
        
        return $sea_level - 1; // Fallback zum Meeresspiegel
    }
    
    public function getPriority(): int {
        return 0; // Niedrigste Priorität als Fallback
    }
    
    public function getName(): string {
        return "Default Terrain";
    }
}