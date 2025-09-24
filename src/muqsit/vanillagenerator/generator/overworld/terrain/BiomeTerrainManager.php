<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\terrain;

use muqsit\vanillagenerator\generator\overworld\biome\BiomeIds;
use muqsit\vanillagenerator\generator\VanillaBiomeGrid;
use pocketmine\world\ChunkManager;
use pocketmine\utils\Random;

class BiomeTerrainManager {

    /** @var BiomeTerrainGenerator[] */
    private static array $terrains = [];
    
    /** @var bool */
    private static bool $initialized = false;
    
    public static function init(): void {
        if (self::$initialized) {
            return;
        }
        
        // Register default terrain types - these work with the density system
        self::registerDefaultTerrains();
        
        self::$initialized = true;
    }
    
    private static function registerDefaultTerrains(): void {
        // Ocean biomes - handled by TerrainBlender now
        $ocean_biomes = [
            BiomeIds::OCEAN,
            BiomeIds::DEEP_OCEAN,
            BiomeIds::FROZEN_OCEAN,
        ];
        
        // Beach biomes
        $beach_biomes = [
            BiomeIds::BEACH,
            BiomeIds::COLD_BEACH,
            BiomeIds::STONE_BEACH
        ];
        
        // Desert biomes
        $desert_biomes = [
            BiomeIds::DESERT,
            BiomeIds::DESERT_HILLS,
            BiomeIds::DESERT_MUTATED
        ];
        
        // Plains-like biomes
        $plains_biomes = [
            BiomeIds::PLAINS,
            BiomeIds::SUNFLOWER_PLAINS,
            BiomeIds::BIRCH_FOREST,
            BiomeIds::FOREST,
            BiomeIds::RIVER
        ];
        
        // Mountain biomes
        $mountain_biomes = [
            BiomeIds::EXTREME_HILLS,
            BiomeIds::EXTREME_HILLS_MUTATED,
            BiomeIds::EXTREME_HILLS_PLUS_TREES_MUTATED
        ];
        
        // Mark biome types for the blender (no actual terrain generators needed)
        foreach ($ocean_biomes as $biome_id) {
            self::$terrains[$biome_id] = null; // Handled by TerrainBlender
        }
        
        foreach ($beach_biomes as $biome_id) {
            self::$terrains[$biome_id] = null; // Handled by TerrainBlender
        }
        
        foreach ($desert_biomes as $biome_id) {
            self::$terrains[$biome_id] = null; // Handled by TerrainBlender
        }
        
        foreach ($plains_biomes as $biome_id) {
            self::$terrains[$biome_id] = null; // Handled by TerrainBlender
        }
        
        foreach ($mountain_biomes as $biome_id) {
            self::$terrains[$biome_id] = null; // Handled by TerrainBlender
        }
    }
    
    /**
     * Register a custom terrain generator for specific biomes
     * @deprecated Use TerrainBlender system instead for better integration
     */
    public static function registerTerrain(BiomeTerrainGenerator $terrain, int ...$biome_ids): void {
        foreach ($biome_ids as $biome_id) {
            self::$terrains[$biome_id] = $terrain;
        }
    }
    
    /**
     * Check if a biome has a custom terrain generator
     */
    public static function hasCustomTerrain(int $biome_id): bool {
        return isset(self::$terrains[$biome_id]) && self::$terrains[$biome_id] !== null;
    }
    
    /**
     * Get terrain generator for a biome (legacy support)
     */
    public static function getTerrainForBiome(int $biome_id): ?BiomeTerrainGenerator {
        return self::$terrains[$biome_id] ?? null;
    }
    
    /**
     * Legacy terrain generation (fallback for custom terrains)
     */
    public static function generateTerrainColumn(
        ChunkManager $world,
        Random $random,
        int $x,
        int $z,
        int $biome_id,
        float $surface_noise,
        array $density,
        int $sea_level = 64
    ): void {
        $terrain = self::getTerrainForBiome($biome_id);
        if ($terrain !== null) {
            $terrain->generateTerrainColumn($world, $random, $x, $z, $biome_id, $surface_noise, $density, $sea_level);
        }
        // Otherwise, TerrainBlender will handle it
    }
    
    /**
     * Main terrain generation entry point - always uses TerrainBlender
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
        // Always use the TerrainBlender for consistent results
        TerrainBlender::generateBlendedTerrainColumn(
            $world, $random, $world_x, $world_z, $grid, 
            $surface_noise, $density, $chunk_x, $chunk_z, $sea_level
        );
    }
    
    /**
     * Get biome category for terrain generation
     */
    public static function getBiomeCategory(int $biome_id): string {
        $ocean_biomes = [BiomeIds::OCEAN, BiomeIds::DEEP_OCEAN, BiomeIds::FROZEN_OCEAN];
        $beach_biomes = [BiomeIds::BEACH, BiomeIds::COLD_BEACH, BiomeIds::STONE_BEACH];
        $desert_biomes = [BiomeIds::DESERT, BiomeIds::DESERT_HILLS, BiomeIds::DESERT_MUTATED];
        $mountain_biomes = [BiomeIds::EXTREME_HILLS, BiomeIds::EXTREME_HILLS_MUTATED, BiomeIds::EXTREME_HILLS_PLUS_TREES_MUTATED];
        
        if (in_array($biome_id, $ocean_biomes)) {
            return 'ocean';
        } elseif (in_array($biome_id, $beach_biomes)) {
            return 'beach';
        } elseif (in_array($biome_id, $desert_biomes)) {
            return 'desert';
        } elseif (in_array($biome_id, $mountain_biomes)) {
            return 'mountain';
        } else {
            return 'plains'; // Default category
        }
    }
    
    /**
     * Check if two biomes are compatible for smooth transitions
     */
    public static function areBiomesCompatible(int $biome1, int $biome2): bool {
        $category1 = self::getBiomeCategory($biome1);
        $category2 = self::getBiomeCategory($biome2);
        
        // Define compatible transitions
        $compatible_transitions = [
            'ocean_beach' => true,
            'beach_plains' => true,
            'beach_desert' => true,
            'plains_forest' => true,
            'plains_mountain' => true,
            'desert_mountain' => true,
        ];
        
        $transition_key = $category1 . '_' . $category2;
        $reverse_key = $category2 . '_' . $category1;
        
        return isset($compatible_transitions[$transition_key]) || 
               isset($compatible_transitions[$reverse_key]) ||
               $category1 === $category2;
    }
    
    /**
     * Get transition priority between two biomes
     */
    public static function getTransitionPriority(int $biome1, int $biome2): int {
        $category1 = self::getBiomeCategory($biome1);
        $category2 = self::getBiomeCategory($biome2);
        
        // Higher priority = more important in transitions
        $priorities = [
            'ocean' => 10,
            'beach' => 15,
            'desert' => 12,
            'mountain' => 8,
            'plains' => 5
        ];
        
        $priority1 = $priorities[$category1] ?? 5;
        $priority2 = $priorities[$category2] ?? 5;
        
        return max($priority1, $priority2);
    }
}