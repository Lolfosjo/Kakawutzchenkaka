<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\terrain\types;

use muqsit\vanillagenerator\generator\overworld\terrain\BiomeTerrainGenerator;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\utils\Random;

/**
 * Beispiel: Wüsten-Terrain
 * Diese Datei zeigt, wie ein spezifisches Biom-Terrain implementiert werden kann
 */
class DesertTerrain implements BiomeTerrainGenerator {
    
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
        
        // Wüsten-spezifische Basis-Struktur (KEINE Oberflächenblöcke)
        $sandstone = VanillaBlocks::SANDSTONE()->getStateId();
        $stone = VanillaBlocks::STONE()->getStateId();
        $air = VanillaBlocks::AIR()->getStateId();
        
        $min_y = $world->getMinY();
        $max_y = $world->getMaxY();
        
        // Ermittle die Oberflächen-Höhe
        $surface_height = $this->calculateDesertSurfaceHeight($density, $min_y, $max_y, $sea_level, $surface_noise);
        
        // Generiere die Wüsten-Basis-Struktur
        for ($y = $min_y; $y < $max_y; $y++) {
            $y_block_pos = $y & 0xf;
            $sub_chunk = $chunk->getSubChunk($y >> Chunk::COORD_BIT_SIZE);
            
            if ($y > $surface_height) {
                // Oberhalb der Oberfläche: Luft
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $air);
            } elseif ($y >= $surface_height - 8) {
                // Sandstein-Schicht (GroundGenerator ersetzt später die obersten Blöcke mit Sand)
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $sandstone);
            } else {
                // Stein darunter
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $stone);
            }
        }
    }
    
    /**
     * Berechnet die Wüsten-Oberflächen-Höhe mit etwas mehr Variation
     */
    private function calculateDesertSurfaceHeight(array $density, int $min_y, int $max_y, int $sea_level, float $surface_noise): int {
        // Basis-Höhe etwas über Meeresspiegel
        $base_height = $sea_level + 5;
        
        // Füge Dünen-artige Variation hinzu
        $variation = (int)($surface_noise * 8);
        
        return max($sea_level, min($max_y - 10, $base_height + $variation));
    }
    
    public function getPriority(): int {
        return 15; // Hohe Priorität für Wüsten-Biome
    }
    
    public function getName(): string {
        return "Desert Terrain";
    }
}