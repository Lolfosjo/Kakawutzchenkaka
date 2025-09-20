<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\terrain\types;

use muqsit\vanillagenerator\generator\overworld\terrain\BiomeTerrainGenerator;
use muqsit\vanillagenerator\generator\VanillaBiomeGrid;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\utils\Random;

/**
 * Strand-Terrain – Übergang zwischen Ozean und Land
 */
class BeachTerrain implements BiomeTerrainGenerator {

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

        $sand = VanillaBlocks::SAND()->getStateId();
        $sandstone = VanillaBlocks::SANDSTONE()->getStateId();
        $stone = VanillaBlocks::STONE()->getStateId();
        $water = VanillaBlocks::WATER()->getStillForm()->getStateId();
        $air = VanillaBlocks::AIR()->getStateId();

        $min_y = $world->getMinY();
        $max_y = $world->getMaxY();

        // Höhe an Ozean und Nachbar-Biome anpassen
        $beach_height = $this->calculateBlendedBeachHeight($sea_level, $surface_noise);

        for ($y = $min_y; $y < $max_y; $y++) {
            $y_block_pos = $y & 0xf;
            $sub_chunk = $chunk->getSubChunk($y >> Chunk::COORD_BIT_SIZE);

            if ($y > $beach_height) {
                if ($y < $sea_level) {
                    $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $water);
                } else {
                    $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $air);
                }
            } elseif ($y === $beach_height) {
                // Oberfläche
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $sand);
            } elseif ($y >= $beach_height - 2) {
                // 1–2 Blöcke Sand
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $sand);
            } elseif ($y >= $beach_height - 4) {
                // Danach Sandstein
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $sandstone);
            } else {
                // Darunter Stein
                $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $stone);
            }
        }
    }

    /**
     * Berechnet Strandhöhe so, dass Übergänge zu Ozean und Land smooth sind
     */
    private function calculateBlendedBeachHeight(int $sea_level, float $surface_noise): int {
        // Basis = Meeresspiegel
        $base = $sea_level;

        // sanfte Variation (-1 bis +1)
        $variation = (int)round($surface_noise * 1.2);

        // Strände dürfen max. 2 Blöcke über oder unter Meeresspiegel liegen
        return max($sea_level - 2, min($sea_level + 2, $base + $variation));
    }

    public function getPriority(): int {
        return 12; // Höher als Ozean, niedriger als Plains
    }

    public function getName(): string {
        return "Beach Terrain";
    }
}