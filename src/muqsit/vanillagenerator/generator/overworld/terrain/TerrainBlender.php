<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\terrain;

use muqsit\vanillagenerator\generator\VanillaBiomeGrid;
use muqsit\vanillagenerator\generator\overworld\terrain\BiomeTerrainManager;
use pocketmine\world\ChunkManager;
use pocketmine\utils\Random;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\SubChunk;

/**
 * Improved terrain blender with better performance and fewer bugs
 */
class TerrainBlender {
    
    private static array $blockStateCache = [];
    private static array $transitionZones = [];
    
    // Configuration constants
    private const BLEND_RADIUS = 8;
    private const GRADIENT_SAMPLES = 16;
    private const MAX_CACHE_SIZE = 1000;
    
    // Performance tracking
    private static int $cacheHits = 0;
    private static int $cacheMisses = 0;
    
    /**
     * Main entry point for blended terrain generation
     */
    public static function generateBlendedTerrainColumn(
        ChunkManager $world,
        Random $random,
        int $world_x,
        int $world_z,
        VanillaBiomeGrid $grid,
        float $surface_noise,
        array $density,
        int $chunk_x,
        int $chunk_z,
        int $sea_level = 64
    ): void {
        try {
            self::initializeStatic();
            
            $local_x = $world_x & 15;
            $local_z = $world_z & 15;

            // Analyze biome environment for blending needs
            $biome_analysis = self::analyzeBiomeEnvironment($grid, $local_x, $local_z);
            
            if ($biome_analysis['is_uniform']) {
                // No blending needed - use standard generation
                BiomeTerrainManager::generateTerrainColumn(
                    $world, $random, $world_x, $world_z, 
                    $biome_analysis['center_biome'], $surface_noise, $density, $sea_level
                );
                return;
            }

            // Generate blended terrain
            self::generateBlendedTerrain(
                $world, $random, $world_x, $world_z,
                $biome_analysis, $surface_noise, $density, $sea_level
            );
            
        } catch (\Throwable $e) {
            // Fallback to standard generation on any error
            error_log("TerrainBlender error: " . $e->getMessage());
            BiomeTerrainManager::generateTerrainColumn(
                $world, $random, $world_x, $world_z, 1, $surface_noise, $density, $sea_level
            );
        }
    }

    /**
     * Initialize static data structures
     */
    private static function initializeStatic(): void {
        if (empty(self::$blockStateCache)) {
            self::$blockStateCache = [
                'stone' => VanillaBlocks::STONE()->getStateId(),
                'grass_block' => VanillaBlocks::GRASS()->getStateId(),
                'dirt' => VanillaBlocks::DIRT()->getStateId(),
                'coarse_dirt' => VanillaBlocks::DIRT()->getStateId(),
                'sand' => VanillaBlocks::SAND()->getStateId(),
                'red_sand' => VanillaBlocks::SAND()->getStateId(), // Fallback
                'water' => VanillaBlocks::WATER()->getStillForm()->getStateId(),
                'air' => VanillaBlocks::AIR()->getStateId(),
                'sandstone' => VanillaBlocks::SANDSTONE()->getStateId(),
                'gravel' => VanillaBlocks::GRAVEL()->getStateId(),
                'clay' => VanillaBlocks::CLAY()->getStateId(),
            ];
        }
        
        if (empty(self::$transitionZones)) {
            self::$transitionZones = [
                '0_16' => 12,   // Ocean -> Beach
                '16_1' => 8,    // Beach -> Plains  
                '1_4' => 6,     // Plains -> Forest
                '0_1' => 10,    // Ocean -> Plains
                '2_16' => 8,    // Desert -> Beach
                '3_1' => 15,    // Mountains -> Plains
                '2_1' => 10,    // Desert -> Plains
                '4_3' => 12,    // Forest -> Mountains
            ];
        }
    }

    /**
     * Analyze biome environment for transition detection
     */
    private static function analyzeBiomeEnvironment(
        VanillaBiomeGrid $grid, 
        int $local_x, 
        int $local_z
    ): array {
        $center_biome = $grid->getBiome($local_x, $local_z);
        
        if ($center_biome === null) {
            return ['is_uniform' => true, 'center_biome' => 1]; // Plains fallback
        }

        // Sample surrounding biomes efficiently
        $surrounding_biomes = self::sampleSurroundingBiomes($grid, $local_x, $local_z);
        
        // Check for biome transitions
        $unique_biomes = array_unique($surrounding_biomes);
        if (count($unique_biomes) <= 1 && $unique_biomes[0] === $center_biome) {
            return ['is_uniform' => true, 'center_biome' => $center_biome];
        }

        // Calculate transition data
        $transitions = self::calculateTransitions($center_biome, $surrounding_biomes);
        
        return [
            'is_uniform' => false,
            'center_biome' => $center_biome,
            'surrounding_biomes' => $surrounding_biomes,
            'transitions' => $transitions
        ];
    }

    /**
     * Sample biomes in a 3x3 grid around the center point
     */
    private static function sampleSurroundingBiomes(
        VanillaBiomeGrid $grid, 
        int $center_x, 
        int $center_z
    ): array {
        $biomes = [];
        
        // Sample in a 3x3 grid (more efficient than circular sampling)
        for ($dx = -1; $dx <= 1; $dx++) {
            for ($dz = -1; $dz <= 1; $dz++) {
                $sample_x = $center_x + $dx;
                $sample_z = $center_z + $dz;
                
                // Handle chunk boundaries safely
                if ($sample_x >= 0 && $sample_x < 16 && $sample_z >= 0 && $sample_z < 16) {
                    $biome = $grid->getBiome($sample_x, $sample_z);
                    if ($biome !== null) {
                        $biomes[] = $biome;
                    }
                }
            }
        }
        
        return $biomes;
    }

    /**
     * Calculate transition properties between biomes
     */
    private static function calculateTransitions(int $center_biome, array $surrounding_biomes): array {
        $transitions = [];
        $biome_counts = array_count_values($surrounding_biomes);
        
        foreach ($biome_counts as $biome_id => $count) {
            if ($biome_id === $center_biome) continue;
            
            $transition_width = self::getTransitionWidth($center_biome, $biome_id);
            $influence = min(1.0, $count / 9.0); // Max influence based on presence
            
            $transitions[$biome_id] = [
                'width' => $transition_width,
                'influence' => $influence,
                'materials' => self::calculateTransitionMaterials($center_biome, $biome_id, $influence)
            ];
        }
        
        return $transitions;
    }

    /**
     * Get transition width between two biomes
     */
    private static function getTransitionWidth(int $biome1, int $biome2): int {
        $key1 = $biome1 . '_' . $biome2;
        $key2 = $biome2 . '_' . $biome1;
        
        if (isset(self::$transitionZones[$key1])) {
            return self::$transitionZones[$key1];
        }
        if (isset(self::$transitionZones[$key2])) {
            return self::$transitionZones[$key2];
        }
        
        // Default transition widths based on biome compatibility
        return self::getDefaultTransitionWidth($biome1, $biome2);
    }

    /**
     * Calculate default transition width based on biome types
     */
    private static function getDefaultTransitionWidth(int $biome1, int $biome2): int {
        $water_biomes = [0, 24]; // Ocean, Deep Ocean
        $land_biomes = [1, 2, 3, 4]; // Plains, Desert, Mountains, Forest
        $coastal_biomes = [16]; // Beach
        
        $is_water1 = in_array($biome1, $water_biomes, true);
        $is_water2 = in_array($biome2, $water_biomes, true);
        $is_coastal1 = in_array($biome1, $coastal_biomes, true);
        $is_coastal2 = in_array($biome2, $coastal_biomes, true);
        
        if (($is_water1 && !$is_water2) || ($is_water2 && !$is_water1)) {
            return 15; // Water-land transitions
        }
        
        if ($is_coastal1 || $is_coastal2) {
            return 10; // Coastal transitions
        }
        
        // Check for extreme elevation differences
        if ($biome1 === 3 || $biome2 === 3) { // Mountains
            return 12;
        }
        
        return 6; // Standard land-land transitions
    }

    /**
     * Calculate materials for transition between biomes
     */
    private static function calculateTransitionMaterials(
        int $biome1, 
        int $biome2, 
        float $influence
    ): array {
        $materials1 = self::getBiomeMaterials($biome1);
        $materials2 = self::getBiomeMaterials($biome2);
        
        $blended = [];
        foreach (['surface', 'subsurface', 'deep'] as $layer) {
            $mat1 = $materials1[$layer];
            $mat2 = $materials2[$layer];
            
            if ($influence > 0.6) {
                $blended[$layer] = $mat2;
            } elseif ($influence > 0.3) {
                $blended[$layer] = self::getTransitionMaterial($mat1, $mat2);
            } else {
                $blended[$layer] = $mat1;
            }
        }
        
        return $blended;
    }

    /**
     * Get appropriate transition material between two materials
     */
    private static function getTransitionMaterial(string $mat1, string $mat2): string {
        $transitions = [
            'grass_block_sand' => 'coarse_dirt',
            'sand_grass_block' => 'coarse_dirt',
            'dirt_sand' => 'coarse_dirt',
            'sand_dirt' => 'coarse_dirt',
            'sand_stone' => 'sandstone',
            'stone_sand' => 'sandstone',
            'grass_block_stone' => 'dirt',
            'stone_grass_block' => 'dirt',
        ];
        
        $key = $mat1 . '_' . $mat2;
        if (isset($transitions[$key])) {
            return $transitions[$key];
        }
        
        // Fallback to more natural material
        $preference = ['grass_block', 'dirt', 'coarse_dirt', 'sand', 'clay', 'gravel', 'stone'];
        foreach ($preference as $preferred) {
            if ($mat1 === $preferred || $mat2 === $preferred) {
                return $preferred;
            }
        }
        
        return $mat1;
    }

    /**
     * Generate blended terrain using transition data
     */
    private static function generateBlendedTerrain(
        ChunkManager $world,
        Random $random,
        int $world_x,
        int $world_z,
        array $biome_analysis,
        float $surface_noise,
        array $density,
        int $sea_level
    ): void {
        $chunk_x = $world_x >> 4;
        $chunk_z = $world_z >> 4;
        $chunk = $world->getChunk($chunk_x, $chunk_z);
        
        if ($chunk === null) {
            return;
        }
        
        $local_x = $world_x & 15;
        $local_z = $world_z & 15;
        
        // Calculate final terrain properties
        $terrain_props = self::calculateFinalTerrainProperties(
            $biome_analysis, $surface_noise, $sea_level
        );
        
        // Generate the terrain column
        self::generateTerrainColumn(
            $chunk, $local_x, $local_z, $terrain_props, 
            $world->getMinY(), $world->getMaxY(), $sea_level
        );
    }

    /**
     * Calculate final terrain properties with blending
     */
    private static function calculateFinalTerrainProperties(
        array $biome_analysis, 
        float $surface_noise, 
        int $sea_level
    ): array {
        $center_biome = $biome_analysis['center_biome'];
        $transitions = $biome_analysis['transitions'] ?? [];
        
        // Base properties from center biome
        $base_height = self::getBiomeBaseHeight($center_biome, $surface_noise, $sea_level);
        $base_materials = self::getBiomeMaterials($center_biome);
        
        // Apply transitions
        $final_height = $base_height;
        $final_materials = $base_materials;
        
        foreach ($transitions as $biome_id => $transition) {
            if ($transition['influence'] > 0.1) {
                $other_height = self::getBiomeBaseHeight($biome_id, $surface_noise, $sea_level);
                
                // Blend height
                $height_influence = $transition['influence'] * 0.5;
                $final_height += ($other_height - $base_height) * $height_influence;
                
                // Blend materials
                if ($transition['influence'] > 0.4) {
                    $final_materials = $transition['materials'];
                }
            }
        }
        
        return [
            'surface_height' => (int)round($final_height),
            'materials' => $final_materials
        ];
    }

    /**
     * Generate terrain column with materials
     */
    private static function generateTerrainColumn(
        Chunk $chunk,
        int $local_x,
        int $local_z,
        array $terrain_props,
        int $min_y,
        int $max_y,
        int $sea_level
    ): void {
        $surface_height = $terrain_props['surface_height'];
        $materials = $terrain_props['materials'];
        
        // Optimize y-loop range
        $start_y = max($min_y, $surface_height - 8);
        $end_y = min($max_y, max($surface_height + 5, $sea_level + 1));
        
        $subchunk_cache = [];
        
        for ($y = $start_y; $y <= $end_y; $y++) {
            $subchunk_index = $y >> 4;
            
            if (!isset($subchunk_cache[$subchunk_index])) {
                $subchunk_cache[$subchunk_index] = $chunk->getSubChunk($subchunk_index);
            }
            
            $subchunk = $subchunk_cache[$subchunk_index];
            if ($subchunk === null) continue;
            
            $y_in_subchunk = $y & 15;
            
            $material = self::determineMaterial($y, $surface_height, $sea_level, $materials);
            $block_state = self::$blockStateCache[$material] ?? self::$blockStateCache['stone'];
            
            $subchunk->setBlockStateId($local_x, $y_in_subchunk, $local_z, $block_state);
        }
    }

    /**
     * Get base height for a biome
     */
    private static function getBiomeBaseHeight(int $biome_id, float $surface_noise, int $sea_level): float {
        $heights = [
            0 => $sea_level - 12,    // Ocean
            24 => $sea_level - 20,   // Deep Ocean  
            16 => $sea_level + 2,    // Beach
            1 => $sea_level + 6,     // Plains
            2 => $sea_level + 4,     // Desert
            3 => $sea_level + 28,    // Mountains
            4 => $sea_level + 8,     // Forest
        ];
        
        $base = $heights[$biome_id] ?? $sea_level;
        
        $variation = match ($biome_id) {
            3 => 20,      // Mountains: high variation
            0, 24 => 3,   // Ocean: low variation
            16 => 1,      // Beach: minimal variation
            default => 6  // Standard variation
        };
        
        return $base + ($surface_noise * $variation);
    }

    /**
     * Get material layers for a biome
     */
    private static function getBiomeMaterials(int $biome_id): array {
        return match ($biome_id) {
            0, 24 => [ // Ocean
                'surface' => 'sand',
                'subsurface' => 'sand', 
                'deep' => 'stone'
            ],
            16 => [ // Beach
                'surface' => 'sand',
                'subsurface' => 'sand',
                'deep' => 'sandstone'
            ],
            2 => [ // Desert
                'surface' => 'sand',
                'subsurface' => 'sandstone',
                'deep' => 'stone'
            ],
            1, 4 => [ // Plains, Forest
                'surface' => 'grass_block',
                'subsurface' => 'dirt',
                'deep' => 'stone'
            ],
            3 => [ // Mountains
                'surface' => 'stone',
                'subsurface' => 'stone',
                'deep' => 'stone'
            ],
            default => [
                'surface' => 'grass_block',
                'subsurface' => 'dirt',
                'deep' => 'stone'
            ]
        };
    }

    /**
     * Determine material for a specific block position
     */
    private static function determineMaterial(
        int $y, 
        int $surface_height, 
        int $sea_level, 
        array $materials
    ): string {
        if ($y > $surface_height) {
            return $y < $sea_level ? 'water' : 'air';
        }
        
        if ($y === $surface_height) {
            return $materials['surface'];
        }
        
        if ($y > $surface_height - 4) {
            return $materials['subsurface'];
        }
        
        return $materials['deep'];
    }

    /**
     * Clear static caches to prevent memory leaks
     */
    public static function clearCache(): void {
        // Only clear if caches are getting too large
        if (count(self::$blockStateCache) > self::MAX_CACHE_SIZE) {
            self::$blockStateCache = [];
        }
    }

    /**
     * Get cache statistics for debugging
     */
    public static function getCacheStats(): array {
        return [
            'cache_hits' => self::$cacheHits,
            'cache_misses' => self::$cacheMisses,
            'hit_ratio' => self::$cacheHits > 0 ? 
                round(self::$cacheHits / (self::$cacheHits + self::$cacheMisses) * 100, 2) : 0
        ];
    }
}