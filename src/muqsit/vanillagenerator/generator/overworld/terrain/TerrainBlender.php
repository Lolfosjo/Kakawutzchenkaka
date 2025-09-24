<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\terrain;

use muqsit\vanillagenerator\generator\VanillaBiomeGrid;
use muqsit\vanillagenerator\generator\overworld\terrain\BiomeTerrainManager;
use pocketmine\world\ChunkManager;
use pocketmine\utils\Random;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\format\Chunk;

/**
 * Improved terrain blender that properly integrates with the density system
 */
class TerrainBlender {
    
    private static array $blockStateCache = [];
    
    // Blending configuration
    private const BLEND_RADIUS = 2;
    private const SAMPLE_RADIUS = 3;
    
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
            self::initializeBlockStates();
            
            $local_x = $world_x & 15;
            $local_z = $world_z & 15;

            // Get the center biome
            $center_biome = $grid->getBiome($local_x, $local_z);
            if ($center_biome === null) {
                $center_biome = 1; // Plains fallback
            }

            // Analyze the biome environment
            $biome_data = self::analyzeBiomeEnvironment($grid, $local_x, $local_z);
            
            // Check if blending is needed
            if ($biome_data['needs_blending']) {
                self::generateBlendedColumn($world, $random, $world_x, $world_z, 
                    $biome_data, $surface_noise, $density, $sea_level);
            } else {
                // No blending needed, use standard generation
                self::generateStandardColumn($world, $random, $world_x, $world_z, 
                    $center_biome, $surface_noise, $density, $sea_level);
            }
            
        } catch (\Throwable $e) {
            error_log("TerrainBlender error at ($world_x, $world_z): " . $e->getMessage());
            // Fallback to standard generation
            self::generateStandardColumn($world, $random, $world_x, $world_z, 
                1, $surface_noise, $density, $sea_level);
        }
    }

    private static function initializeBlockStates(): void {
        if (empty(self::$blockStateCache)) {
            self::$blockStateCache = [
                'stone' => VanillaBlocks::STONE()->getStateId(),
                'grass' => VanillaBlocks::GRASS()->getStateId(),
                'dirt' => VanillaBlocks::DIRT()->getStateId(),
                'coarse_dirt' => VanillaBlocks::DIRT()->getStateId(), // Fallback
                'sand' => VanillaBlocks::SAND()->getStateId(),
                'sandstone' => VanillaBlocks::SANDSTONE()->getStateId(),
                'gravel' => VanillaBlocks::GRAVEL()->getStateId(),
                'clay' => VanillaBlocks::CLAY()->getStateId(),
                'water' => VanillaBlocks::WATER()->getStillForm()->getStateId(),
                'air' => VanillaBlocks::AIR()->getStateId(),
                'bedrock' => VanillaBlocks::BEDROCK()->getStateId(),
            ];
        }
    }

    private static function analyzeBiomeEnvironment(VanillaBiomeGrid $grid, int $local_x, int $local_z): array {
        $center_biome = $grid->getBiome($local_x, $local_z) ?? 1;
        
        // Sample surrounding biomes in a 5x5 grid
        $surrounding_biomes = [];
        $biome_weights = [];
        
        for ($dx = -self::SAMPLE_RADIUS; $dx <= self::SAMPLE_RADIUS; $dx++) {
            for ($dz = -self::SAMPLE_RADIUS; $dz <= self::SAMPLE_RADIUS; $dz++) {
                $sample_x = $local_x + $dx;
                $sample_z = $local_z + $dz;
                
                // Handle chunk boundaries
                if ($sample_x >= 0 && $sample_x < 16 && $sample_z >= 0 && $sample_z < 16) {
                    $biome = $grid->getBiome($sample_x, $sample_z) ?? $center_biome;
                    $distance = max(abs($dx), abs($dz));
                    $weight = 1.0 / (1.0 + $distance * 0.5);
                    
                    if (!isset($biome_weights[$biome])) {
                        $biome_weights[$biome] = 0;
                    }
                    $biome_weights[$biome] += $weight;
                    $surrounding_biomes[] = $biome;
                }
            }
        }
        
        // Check if blending is needed
        $unique_biomes = array_keys($biome_weights);
        $needs_blending = count($unique_biomes) > 1;
        
        // Calculate transition influences
        $transitions = [];
        if ($needs_blending) {
            $total_weight = array_sum($biome_weights);
            foreach ($biome_weights as $biome => $weight) {
                if ($biome !== $center_biome && $weight > 0.1) {
                    $transitions[$biome] = [
                        'influence' => $weight / $total_weight,
                        'transition_width' => self::getTransitionWidth($center_biome, $biome)
                    ];
                }
            }
        }
        
        return [
            'center_biome' => $center_biome,
            'needs_blending' => $needs_blending,
            'biome_weights' => $biome_weights,
            'transitions' => $transitions
        ];
    }

    private static function generateBlendedColumn(
        ChunkManager $world, Random $random, int $world_x, int $world_z,
        array $biome_data, float $surface_noise, array $density, int $sea_level
    ): void {
        $chunk = $world->getChunk($world_x >> 4, $world_z >> 4);
        if ($chunk === null) return;
        
        $local_x = $world_x & 15;
        $local_z = $world_z & 15;
        $min_y = $world->getMinY();
        $max_y = $world->getMaxY();
        
        // Calculate blended terrain properties
        $terrain_props = self::calculateBlendedProperties($biome_data, $surface_noise, $sea_level, $density);
        
        // Generate the terrain column
        self::placeBlocks($chunk, $local_x, $local_z, $terrain_props, $min_y, $max_y, $sea_level);
    }

    private static function generateStandardColumn(
        ChunkManager $world, Random $random, int $world_x, int $world_z,
        int $biome_id, float $surface_noise, array $density, int $sea_level
    ): void {
        $chunk = $world->getChunk($world_x >> 4, $world_z >> 4);
        if ($chunk === null) return;
        
        $local_x = $world_x & 15;
        $local_z = $world_z & 15;
        $min_y = $world->getMinY();
        $max_y = $world->getMaxY();
        
        // Calculate standard terrain properties
        $terrain_props = self::calculateStandardProperties($biome_id, $surface_noise, $sea_level, $density);
        
        // Generate the terrain column
        self::placeBlocks($chunk, $local_x, $local_z, $terrain_props, $min_y, $max_y, $sea_level);
    }

    private static function calculateBlendedProperties(
        array $biome_data, float $surface_noise, int $sea_level, array $density
    ): array {
        $center_biome = $biome_data['center_biome'];
        $transitions = $biome_data['transitions'];
        
        // Start with center biome properties
        $base_props = self::getBiomeTerrainProperties($center_biome, $surface_noise, $sea_level);
        
        // Apply transitions with SMOOTHER blending
        $final_height = $base_props['surface_height'];
        $final_materials = $base_props['materials'];
        
        foreach ($transitions as $biome_id => $transition_data) {
            if ($transition_data['influence'] > 0.15) { // Niedrigere Schwelle für sanftere Übergänge
                $other_props = self::getBiomeTerrainProperties($biome_id, $surface_noise, $sea_level);
                
                // SANFTERE Höhen-Interpolation
                $height_influence = $transition_data['influence'] * 0.4; // Reduziert von 0.6
                $height_diff = $other_props['surface_height'] - $base_props['surface_height'];
                
                // Spezielle Logik für Küsten-Übergänge
                if (self::isCoastalTransition($center_biome, $biome_id)) {
                    $height_influence *= 0.7; // Noch sanftere Küsten-Übergänge
                }
                
                $final_height += $height_diff * $height_influence;
                
                // Material-Blending mit niedrigerer Schwelle
                if ($transition_data['influence'] > 0.3) { // Reduziert von 0.4
                    $final_materials = self::blendMaterials(
                        $final_materials, 
                        $other_props['materials'], 
                        $transition_data['influence']
                    );
                }
            }
        }
        
        return [
            'surface_height' => (int)round($final_height),
            'materials' => $final_materials
        ];
    }

    private static function calculateStandardProperties(
        int $biome_id, float $surface_noise, int $sea_level, array $density
    ): array {
        return self::getBiomeTerrainProperties($biome_id, $surface_noise, $sea_level);
    }

    private static function getBiomeTerrainProperties(int $biome_id, float $surface_noise, int $sea_level): array {
        // Define terrain properties for each biome - VERBESSERTE Höhen für sanftere Übergänge
        $biome_configs = [
            0 => [ // Ocean
                'base_height' => $sea_level - 8,  // Weniger tief für sanfteren Übergang
                'height_variation' => 2,          // Reduzierte Variation
                'materials' => [
                    'surface' => 'sand',
                    'subsurface' => 'sand',
                    'deep' => 'stone'
                ]
            ],
            24 => [ // Deep Ocean
                'base_height' => $sea_level - 15, // Weniger extrem tief
                'height_variation' => 3,
                'materials' => [
                    'surface' => 'gravel',
                    'subsurface' => 'clay',
                    'deep' => 'stone'
                ]
            ],
            16 => [ // Beach - KRITISCH für sanfte Übergänge
                'base_height' => $sea_level,      // Genau auf Meeresspiegel
                'height_variation' => 2,          // Minimale Variation
                'materials' => [
                    'surface' => 'sand',
                    'subsurface' => 'sand',
                    'deep' => 'sandstone'
                ]
            ],
            1 => [ // Plains
                'base_height' => $sea_level + 3,  // Näher zum Meeresspiegel
                'height_variation' => 3,          // Reduzierte Variation
                'materials' => [
                    'surface' => 'grass',
                    'subsurface' => 'dirt',
                    'deep' => 'stone'
                ]
            ],
            2 => [ // Desert
                'base_height' => $sea_level + 2,  // Näher zum Meeresspiegel
                'height_variation' => 4,
                'materials' => [
                    'surface' => 'sand',
                    'subsurface' => 'sandstone',
                    'deep' => 'stone'
                ]
            ],
            3 => [ // Mountains
                'base_height' => $sea_level + 20, // Etwas weniger extrem
                'height_variation' => 12,
                'materials' => [
                    'surface' => 'stone',
                    'subsurface' => 'stone',
                    'deep' => 'stone'
                ]
            ],
            4 => [ // Forest
                'base_height' => $sea_level + 4,  // Näher zum Meeresspiegel
                'height_variation' => 4,
                'materials' => [
                    'surface' => 'grass',
                    'subsurface' => 'dirt',
                    'deep' => 'stone'
                ]
            ]
        ];

        $config = $biome_configs[$biome_id] ?? $biome_configs[1]; // Plains fallback
        
        // Spezielle Beach-Logik für realistischere Küstenformen
        if ($biome_id === 16) { // Beach
            $surface_height = self::calculateBeachHeight($config['base_height'], $surface_noise, $sea_level);
        } else {
            $surface_height = $config['base_height'] + ($surface_noise * $config['height_variation']);
        }
        
        return [
            'surface_height' => (int)round($surface_height),
            'materials' => $config['materials']
        ];
    }

    /**
     * VERBESSERTE Beach-Höhen-Berechnung für natürlichere Küstenlinien
     */
    private static function calculateBeachHeight(int $base_height, float $surface_noise, int $sea_level): float {
        // Beaches sollten sehr sanft vom Meeresspiegel ansteigen
        $height = $base_height;
        
        // Sehr sanfte Dünen - reduzierte Amplitude
        $primary_dune = sin($surface_noise * 1.5) * 1.0;    // Reduziert von 2.0
        $secondary_dune = sin($surface_noise * 3.0) * 0.5;  // Reduziert von 1.0
        
        $height += $primary_dune + $secondary_dune;
        
        // Beaches bleiben sehr nah am Meeresspiegel für sanfte Übergänge
        return max($sea_level - 2, min($sea_level + 2, $height));
    }

    private static function blendMaterials(array $mat1, array $mat2, float $influence): array {
        $result = [];
        
        foreach (['surface', 'subsurface', 'deep'] as $layer) {
            if ($influence > 0.7) {
                $result[$layer] = $mat2[$layer];
            } elseif ($influence > 0.5) {
                $result[$layer] = self::getTransitionMaterial($mat1[$layer], $mat2[$layer]);
            } else {
                $result[$layer] = $mat1[$layer];
            }
        }
        
        return $result;
    }

    private static function getTransitionMaterial(string $mat1, string $mat2): string {
        // Define material transitions
        $transitions = [
            'grass_sand' => 'coarse_dirt',
            'sand_grass' => 'coarse_dirt',
            'dirt_sand' => 'coarse_dirt',
            'sand_dirt' => 'coarse_dirt',
            'sand_stone' => 'sandstone',
            'stone_sand' => 'sandstone',
            'grass_gravel' => 'dirt',
            'gravel_grass' => 'dirt',
            'sand_gravel' => 'sand',
            'gravel_sand' => 'sand',
            'grass_water' => 'sand',
            'water_grass' => 'sand'
        ];
        
        $key = $mat1 . '_' . $mat2;
        return $transitions[$key] ?? $mat1;
    }

    private static function getTransitionWidth(int $biome1, int $biome2): int {
        // Define transition widths between specific biomes - ERWEITERT für sanftere Übergänge
        $water_biomes = [0, 24]; // Ocean, Deep Ocean
        $coastal_biomes = [16]; // Beach
        
        $is_water1 = in_array($biome1, $water_biomes);
        $is_water2 = in_array($biome2, $water_biomes);
        $is_coastal1 = in_array($biome1, $coastal_biomes);
        $is_coastal2 = in_array($biome2, $coastal_biomes);
        
        // Ocean-Beach Übergänge - erweitert für sanftere Küsten
        if (($is_water1 && $is_coastal2) || ($is_coastal1 && $is_water2)) {
            return 6; // Erweitert von 4
        }
        
        // Beach-Land Übergänge - erweitert für sanftere Landübergänge  
        if (($is_coastal1 && !$is_water2) || ($is_coastal2 && !$is_water1)) {
            return 5; // Erweitert von 3
        }
        
        // Direkte Wasser-Land Übergänge (falls keine Beach dazwischen)
        if (($is_water1 && !$is_water2 && !$is_coastal2) || ($is_water2 && !$is_water1 && !$is_coastal1)) {
            return 10; // Erweitert von 8
        }
        
        // Standard Land-Land Übergänge
        return 4; // Erweitert von 3
    }

    /**
     * Hilfsmethode zur Erkennung von Küsten-Übergängen
     */
    private static function isCoastalTransition(int $biome1, int $biome2): bool {
        $water_biomes = [0, 24]; // Ocean, Deep Ocean
        $coastal_biomes = [16]; // Beach
        
        $is_water1 = in_array($biome1, $water_biomes);
        $is_water2 = in_array($biome2, $water_biomes);
        $is_coastal1 = in_array($biome1, $coastal_biomes);
        $is_coastal2 = in_array($biome2, $coastal_biomes);
        
        // Jeder Übergang der Wasser oder Beach beinhaltet ist ein Küsten-Übergang
        return $is_water1 || $is_water2 || $is_coastal1 || $is_coastal2;
    }

    private static function placeBlocks(
        Chunk $chunk, int $local_x, int $local_z, array $terrain_props,
        int $min_y, int $max_y, int $sea_level
    ): void {
        $surface_height = $terrain_props['surface_height'];
        $materials = $terrain_props['materials'];
        
        // Cache subchunks for better performance
        $subchunk_cache = [];
        
        // KORREKTUR: Generiere VOLLSTÄNDIGE Säule von min_y bis max_y
        for ($y = $min_y; $y < $max_y; $y++) {
            $subchunk_index = $y >> 4;
            
            if (!isset($subchunk_cache[$subchunk_index])) {
                $subchunk_cache[$subchunk_index] = $chunk->getSubChunk($subchunk_index);
            }
            
            $subchunk = $subchunk_cache[$subchunk_index];
            if ($subchunk === null) continue;
            
            $y_in_subchunk = $y & 15;
            
            // Determine block type based on position
            $block_type = self::determineBlockType($y, $surface_height, $sea_level, $materials, $min_y);
            $block_state = self::$blockStateCache[$block_type] ?? self::$blockStateCache['stone'];
            
            $subchunk->setBlockStateId($local_x, $y_in_subchunk, $local_z, $block_state);
        }
    }

    private static function determineBlockType(
        int $y, int $surface_height, int $sea_level, array $materials, int $min_y
    ): string {
        // KORREKTUR: Vollständige Terrain-Generierung
        
        // Bedrock layer (unterste 5 Blöcke)
        if ($y <= $min_y + 5) {
            return 'bedrock';
        }
        
        // Über der Oberfläche
        if ($y > $surface_height) {
            return $y < $sea_level ? 'water' : 'air';
        }
        
        // Oberfläche
        if ($y === $surface_height) {
            return $materials['surface'];
        }
        
        // Subsurface layer (4-8 Blöcke unter Oberfläche)
        if ($y > $surface_height - 8) {
            return $materials['subsurface'];
        }
        
        // Deep layer (alles andere bis Bedrock)
        return $materials['deep'];
    }
}