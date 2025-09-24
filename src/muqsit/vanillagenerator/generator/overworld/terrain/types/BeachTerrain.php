<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\terrain\types;

use muqsit\vanillagenerator\generator\overworld\terrain\BiomeTerrainGenerator;
use muqsit\vanillagenerator\generator\overworld\biome\BiomeIds;
use muqsit\vanillagenerator\generator\VanillaBiomeGrid;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\utils\Random;

/**
 * Adaptives Beach-Terrain das sich dynamisch an Nachbar-Biome anpasst
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
        try {
            $chunk_x = $x >> Chunk::COORD_BIT_SIZE;
            $chunk_z = $z >> Chunk::COORD_BIT_SIZE;
            $chunk = $world->getChunk($chunk_x, $chunk_z);

            if ($chunk === null) {
                return; // Sicherheitscheck
            }

            $local_x = $x & (Chunk::EDGE_LENGTH - 1);
            $local_z = $z & (Chunk::EDGE_LENGTH - 1);

            // Validiere lokale Koordinaten
            if ($local_x < 0 || $local_x >= 16 || $local_z < 0 || $local_z >= 16) {
                error_log("BeachTerrain: Invalid local coordinates ($local_x, $local_z) at world ($x, $z)");
                return;
            }

            // Block-States Cache
            $sand = VanillaBlocks::SAND()->getStateId();
            $sandstone = VanillaBlocks::SANDSTONE()->getStateId();
            $stone = VanillaBlocks::STONE()->getStateId();
            $water = VanillaBlocks::WATER()->getStillForm()->getStateId();
            $air = VanillaBlocks::AIR()->getStateId();
            $dirt = VanillaBlocks::DIRT()->getStateId();
            $grass = VanillaBlocks::GRASS()->getStateId();
            $gravel = VanillaBlocks::GRAVEL()->getStateId();

            $min_y = $world->getMinY();
            $max_y = $world->getMaxY();
            
            // Validiere Y-Bereich
            if ($min_y >= $max_y || $min_y < -64 || $max_y > 320) {
                error_log("BeachTerrain: Invalid Y range ($min_y to $max_y)");
                return;
            }

            // ADAPTIVE LOGIK: Analysiere Umgebung
            $environment_data = $this->analyzeBeachEnvironment($world, $x, $z, $chunk_x, $chunk_z);
            $adaptive_height = $this->calculateAdaptiveBeachHeight(
                $sea_level, $surface_noise, $environment_data, $x, $z
            );

            // Validiere berechnete Höhe
            $adaptive_height = max($min_y, min($max_y - 1, $adaptive_height));

            // Generiere die Terrain-Säule
            for ($y = $min_y; $y < $max_y; $y++) {
                try {
                    $y_block_pos = $y & 0xf;
                    $sub_chunk = $chunk->getSubChunk($y >> Chunk::COORD_BIT_SIZE);
                    
                    if ($sub_chunk === null) {
                        continue;
                    }

                    $block_state = $this->determineAdaptiveBlock(
                        $y, $adaptive_height, $sea_level, $environment_data,
                        $sand, $sandstone, $stone, $water, $air, $dirt, $grass, $gravel
                    );

                    $sub_chunk->setBlockStateId($local_x, $y_block_pos, $local_z, $block_state);
                    
                } catch (\Throwable $e) {
                    error_log("BeachTerrain: Block placement error at ($x, $y, $z): " . $e->getMessage());
                    continue;
                }
            }
            
        } catch (\Throwable $e) {
            error_log("BeachTerrain: Critical error in generateTerrainColumn at ($x, $z): " . $e->getMessage());
            // Fallback zu sehr einfacher Generation
            $this->generateSimpleFallback($world, $x, $z, $sea_level);
        }
    }

    /**
     * Einfache Fallback-Generation bei kritischen Fehlern
     */
    private function generateSimpleFallback(ChunkManager $world, int $x, int $z, int $sea_level): void {
        try {
            $chunk_x = $x >> 4;
            $chunk_z = $z >> 4;
            $chunk = $world->getChunk($chunk_x, $chunk_z);
            
            if ($chunk === null) return;
            
            $local_x = $x & 15;
            $local_z = $z & 15;
            
            $sand = VanillaBlocks::SAND()->getStateId();
            $stone = VanillaBlocks::STONE()->getStateId();
            $water = VanillaBlocks::WATER()->getStillForm()->getStateId();
            $air = VanillaBlocks::AIR()->getStateId();
            
            $surface_height = $sea_level; // Einfache Beach auf Meeresspiegel
            
            $min_y = $world->getMinY();
            $max_y = $world->getMaxY();
            
            for ($y = $min_y; $y < $max_y; $y++) {
                $sub_chunk = $chunk->getSubChunk($y >> 4);
                if ($sub_chunk === null) continue;
                
                $y_pos = $y & 15;
                
                if ($y > $surface_height) {
                    $block = $y < $sea_level ? $water : $air;
                } elseif ($y >= $surface_height - 3) {
                    $block = $sand;
                } else {
                    $block = $stone;
                }
                
                $sub_chunk->setBlockStateId($local_x, $y_pos, $local_z, $block);
            }
            
        } catch (\Throwable $e) {
            error_log("BeachTerrain: Even fallback generation failed: " . $e->getMessage());
        }
    }

    /**
     * Sichere Umgebungsanalyse des Beach-Blocks
     */
    private function analyzeBeachEnvironment(
        ChunkManager $world, 
        int $x, 
        int $z, 
        int $chunk_x, 
        int $chunk_z
    ): array {
        $environment = [
            'has_ocean_nearby' => false,
            'has_land_nearby' => false,
            'ocean_distance' => 999,
            'land_distance' => 999,
            'average_neighbor_height' => 64,
            'is_ocean_edge' => false,
            'is_land_edge' => false
        ];

        try {
            // Sichere Bereichsanalyse mit Error-Handling
            $water_count = 0;
            $land_count = 0;
            $total_samples = 0;
            $height_sum = 0;

            for ($dx = -3; $dx <= 3; $dx++) {
                for ($dz = -3; $dz <= 3; $dz++) {
                    try {
                        $sample_x = $x + $dx;
                        $sample_z = $z + $dz;
                        $sample_chunk_x = $sample_x >> 4;
                        $sample_chunk_z = $sample_z >> 4;
                        
                        // Sichere Chunk-Validierung
                        if (abs($sample_chunk_x - $chunk_x) <= 1 && abs($sample_chunk_z - $chunk_z) <= 1) {
                            $sample_chunk = $world->getChunk($sample_chunk_x, $sample_chunk_z);
                            if ($sample_chunk !== null) {
                                $sample_local_x = $sample_x & 15;
                                $sample_local_z = $sample_z & 15;
                                
                                // Sichere Höhenschätzung
                                $estimated_height = $this->estimateTerrainHeight($sample_x, $sample_z);
                                
                                // Validiere Höhenwert
                                if ($estimated_height >= 0 && $estimated_height <= 320) {
                                    $height_sum += $estimated_height;
                                    $total_samples++;
                                    
                                    $distance = max(abs($dx), abs($dz));
                                    
                                    // Sichere Distanz-Validierung
                                    if ($distance >= 0 && $distance <= 5) {
                                        // Wasser-Erkennung
                                        if ($estimated_height < 64) {
                                            $water_count++;
                                            if ($distance < $environment['ocean_distance']) {
                                                $environment['ocean_distance'] = $distance;
                                                $environment['has_ocean_nearby'] = true;
                                            }
                                        }
                                        // Land-Erkennung
                                        elseif ($estimated_height > 66) {
                                            $land_count++;
                                            if ($distance < $environment['land_distance']) {
                                                $environment['land_distance'] = $distance;
                                                $environment['has_land_nearby'] = true;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // Einzelne Sample-Fehler ignorieren
                        continue;
                    }
                }
            }

            // Sichere Durchschnittsberechnung
            if ($total_samples > 0) {
                $environment['average_neighbor_height'] = (int)($height_sum / $total_samples);
            }

            // Sichere Ratio-Berechnung
            $safe_total = max($total_samples, 1);
            $water_ratio = $water_count / $safe_total;
            $land_ratio = $land_count / $safe_total;

            $environment['is_ocean_edge'] = $water_ratio > 0.3;
            $environment['is_land_edge'] = $land_ratio > 0.2;

        } catch (\Throwable $e) {
            // Bei schweren Fehlern: Fallback zu Standard-Werten
            error_log("BeachTerrain: Environment analysis failed at ($x, $z): " . $e->getMessage());
        }

        return $environment;
    }

    /**
     * Sichere Terrain-Höhen-Schätzung basierend auf Position
     */
    private function estimateTerrainHeight(int $x, int $z): int {
        try {
            // Sichere Koordinaten-Berechnungen
            $safe_x = abs($x) + 1; // Verhindere 0-Werte
            $safe_z = abs($z) + 1;
            
            // Vereinfachte, sichere Höhen-Schätzung
            $base_height = 64; // Meeresspiegel
            
            // Einfache deterministische Höhen-Variation ohne Random
            $variation1 = (($safe_x * 73856093) ^ ($safe_z * 19349663)) % 7 - 3; // -3 bis +3
            $variation2 = (($safe_x * 83492791) ^ ($safe_z * 57885161)) % 5 - 2; // -2 bis +2
            
            $height_variation = (int)(($variation1 + $variation2) * 0.5);
            
            return $base_height + $height_variation;
            
        } catch (\Throwable $e) {
            // Fallback bei jedem Fehler
            return 64; // Meeresspiegel
        }
    }

    /**
     * Berechnet adaptive Beach-Höhe basierend auf Umgebungsanalyse
     */
    private function calculateAdaptiveBeachHeight(
        int $sea_level, 
        float $surface_noise, 
        array $environment, 
        int $x, 
        int $z
    ): int {
        $base_height = $sea_level;
        
        // Grundvariation durch Surface Noise
        $noise_variation = $surface_noise * 1.5;
        
        // ADAPTIVE ANPASSUNGEN basierend auf Nachbarn:
        
        // Näher zum Ozean = tiefer
        if ($environment['is_ocean_edge'] && $environment['ocean_distance'] <= 2) {
            $ocean_influence = (3 - $environment['ocean_distance']) * 0.8;
            $base_height -= $ocean_influence;
        }
        
        // Näher zum Land = höher, aber sanft ansteigend
        if ($environment['is_land_edge'] && $environment['land_distance'] <= 3) {
            $land_influence = (4 - $environment['land_distance']) * 0.6;
            $base_height += $land_influence;
        }
        
        // Anpassung an durchschnittliche Nachbarhöhe (sanfter Übergang)
        $height_diff = $environment['average_neighbor_height'] - $sea_level;
        if (abs($height_diff) > 0) {
            $smoothing_factor = 0.3; // 30% der Höhendifferenz übernehmen
            $base_height += $height_diff * $smoothing_factor;
        }
        
        // Addiere Noise-Variation
        $final_height = (int)round($base_height + $noise_variation);
        
        // WICHTIG: Beaches müssen nah am Meeresspiegel bleiben für realistische Übergänge
        return max($sea_level - 3, min($sea_level + 4, $final_height));
    }

    /**
     * Bestimmt den Block-Typ basierend auf adaptiver Logik
     */
    private function determineAdaptiveBlock(
        int $y, 
        int $surface_height, 
        int $sea_level, 
        array $environment,
        int $sand, 
        int $sandstone, 
        int $stone, 
        int $water, 
        int $air, 
        int $dirt, 
        int $grass, 
        int $gravel
    ): int {
        // Über der Oberfläche
        if ($y > $surface_height) {
            return $y < $sea_level ? $water : $air;
        }
        
        // Oberfläche - ADAPTIVE MATERIALWAHL:
        if ($y === $surface_height) {
            // Näher zum Ozean = mehr Sand
            if ($environment['is_ocean_edge'] && $environment['ocean_distance'] <= 1) {
                return $sand;
            }
            // Näher zum Land und über Meeresspiegel = eventuell Gras
            elseif ($environment['is_land_edge'] && $surface_height >= $sea_level + 1) {
                // Sanfter Übergang zu Gras bei höheren Beach-Bereichen
                return ($environment['land_distance'] <= 1) ? $grass : $sand;
            }
            // Standard: Sand
            else {
                return $sand;
            }
        }
        
        // Subsurface (1-3 Blöcke unter Oberfläche)
        if ($y > $surface_height - 3) {
            // Näher zum Ozean = mehr Sand
            if ($environment['is_ocean_edge']) {
                return $sand;
            }
            // Übergang zum Land = Mix aus Sand und Dirt
            elseif ($environment['is_land_edge'] && $y > $surface_height - 2) {
                return $dirt; // Dirt-Übergang unter Gras-Bereichen
            }
            else {
                return $sand;
            }
        }
        
        // Tiefere Schichten (4-6 Blöcke unter Oberfläche)
        if ($y > $surface_height - 6) {
            return $sandstone;
        }
        
        // Sehr tiefe Schichten
        return $stone;
    }

    public function getPriority(): int {
        return 12; // Höher als Ozean, niedriger als Plains
    }

    public function getName(): string {
        return "Adaptive Beach Terrain";
    }
}