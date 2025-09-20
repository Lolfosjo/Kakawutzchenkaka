<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\terrain;

use muqsit\vanillagenerator\generator\overworld\biome\BiomeIds;
use muqsit\vanillagenerator\generator\overworld\terrain\types\DefaultTerrain;
use muqsit\vanillagenerator\generator\overworld\terrain\types\OceanTerrain;
use muqsit\vanillagenerator\generator\VanillaBiomeGrid;
use pocketmine\world\ChunkManager;
use pocketmine\utils\Random;

class BiomeTerrainManager {

    /** @var BiomeTerrainGenerator[] */
    private static array $terrains = [];
    
    /** @var BiomeTerrainGenerator */
    private static BiomeTerrainGenerator $default_terrain;
    
    /** @var BiomeTerrainGenerator */
    private static BiomeTerrainGenerator $ocean_terrain;
    
    public static function init(): void {
        // Standard-Terrain für unregistrierte Biome
        self::$default_terrain = new DefaultTerrain();
        
        // Ozean-Terrain (eingebaut)
        self::$ocean_terrain = new OceanTerrain();
        
        // Registriere Ozean-Biome direkt hier
        self::registerOceanBiomes();
        
        // TODO: Hier können weitere Terrain-Typen registriert werden
        // self::registerTerrain(new DesertTerrain(), BiomeIds::DESERT, BiomeIds::DESERT_HILLS);
        // self::registerTerrain(new MountainTerrain(), BiomeIds::EXTREME_HILLS);
    }
    
    private static function registerOceanBiomes(): void {
        $ocean_biomes = [
            BiomeIds::OCEAN,
            BiomeIds::DEEP_OCEAN,
            BiomeIds::FROZEN_OCEAN,
            // Weitere Ozean-Biome hier hinzufügen
        ];
        
        foreach ($ocean_biomes as $biome_id) {
            self::$terrains[$biome_id] = self::$ocean_terrain;
        }
    }
    
    /**
     * Registriert ein Terrain für bestimmte Biome
     */
    public static function registerTerrain(BiomeTerrainGenerator $terrain, int ...$biome_ids): void {
        foreach ($biome_ids as $biome_id) {
            self::$terrains[$biome_id] = $terrain;
        }
    }
    
    /**
     * Gibt das Terrain für ein bestimmtes Biom zurück
     */
    public static function getTerrainForBiome(int $biome_id): BiomeTerrainGenerator {
        return self::$terrains[$biome_id] ?? self::$default_terrain;
    }
    
    /**
     * Generiert das Terrain für eine Chunk-Spalte (ohne Blending)
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
        $terrain->generateTerrainColumn($world, $random, $x, $z, $biome_id, $surface_noise, $density, $sea_level);
    }
    
    /**
     * Generiert das Terrain für eine Chunk-Spalte mit Blending
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
        TerrainBlender::generateBlendedTerrainColumn(
            $world, $random, $world_x, $world_z, $grid, $surface_noise, $density, $chunk_x, $chunk_z, $sea_level
        );
    }
}