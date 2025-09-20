<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\terrain\types;

use muqsit\vanillagenerator\generator\overworld\terrain\BiomeTerrainGenerator;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\utils\Random;

class PlainsTerrain implements BiomeTerrainGenerator {
    
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
       
        $grass = VanillaBlocks::GRASS()->getStateId();
        $stone = VanillaBlocks::STONE()->getStateId();
        $air = VanillaBlocks::AIR()->getStateId();
        
        $min_y = $world->getMinY();
        $max_y = $world->getMaxY();
        
        // Höhe der Oberfläche berechnen
        $surface_height = $this->calculatePlainsSurfaceHeight($x, $z, $density, $min_y, $max_y, $sea_level, $surface_noise);
        
        // Terrain generieren
        for ($y = $min_y; $y < $max_y; $y++) {
            $y_block_pos = $y & 0xf;
            $sub_chunk = $chunk->getSubChunk($y >> Chunk::COORD_BIT_SIZE);
            
            if ($y > $surface_height) {
                // Luft über Oberfläche
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $air);
            } elseif ($y >= $surface_height - 4) {
                // Oberboden (wird später durch Grass/Dirt ersetzt)
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $grass);
            } else {
                // Untergrund: Stein
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $stone);
            }
        }
    }
    
    /**
     * Berechnet die Plains-Oberflächen-Höhe
     */
    private function calculatePlainsSurfaceHeight(int $x, int $z, array $density, int $min_y, int $max_y, int $sea_level, float $surface_noise): int {
        // Basis-Höhe: leicht über Meeresspiegel
        $base_height = $sea_level + 5;

        // Sanfte Variation aus Surface Noise
        $variation = (int)($surface_noise * 6);

        // Kleine zusätzliche Hügel (breit, nicht steil)
        $hill_factor = (int)(sin($x * 0.02) + cos($z * 0.02));

        $height = $base_height + $variation + $hill_factor;

        return max($sea_level, min($max_y - 10, $height));
    }
    
    public function getPriority(): int {
        return 15; // Priorität für Plains
    }
    
    public function getName(): string {
        return "Plains Terrain";
    }
}