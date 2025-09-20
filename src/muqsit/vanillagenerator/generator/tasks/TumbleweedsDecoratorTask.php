<?php

namespace muqsit\vanillagenerator\generator\tasks;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\world\Position;
use muqsit\vanillagenerator\generator\overworld\biome\BiomeIds;
use pocketmine\block\VanillaBlocks;
use mobs\entities\Tumbleweed;
use pocketmine\entity\Location;

class TumbleweedsDecoratorTask extends Task {
    
    private int $spawnChance;
    private int $maxTumbleweeds;
    private int $spawnRadius;
    private int $minSpawnDistance;
    private float $maxSpawnHeight;
    

    private array $cachedBiomes = [];
    private int $biomeCacheLifetime = 100;
    private int $lastBiomeCleanup = 0;
    
    private const DESERT_BIOMES = [
        BiomeIds::DESERT => true,
        BiomeIds::DESERT_HILLS => true,
        BiomeIds::DESERT_MUTATED => true
    ];
    
    public function __construct(
        int $spawnChance = 15,      
        int $maxTumbleweeds = 15,     
        int $spawnRadius = 35,         
        int $minSpawnDistance = 5,   
        float $maxSpawnHeight = 150.0
    ) {
        $this->spawnChance = $spawnChance;
        $this->maxTumbleweeds = $maxTumbleweeds;
        $this->spawnRadius = $spawnRadius;
        $this->minSpawnDistance = $minSpawnDistance;
        $this->maxSpawnHeight = $maxSpawnHeight;
    }
    
    public function onRun(): void {
        $server = Server::getInstance();
        $currentTick = $server->getTick();
        
        if ($currentTick - $this->lastBiomeCleanup > $this->biomeCacheLifetime) {
            $this->cleanupBiomeCache($currentTick);
            $this->lastBiomeCleanup = $currentTick;
        }
        
        $onlinePlayers = $server->getOnlinePlayers();
        if (empty($onlinePlayers)) {
            return;
        }
        
        $playersToProcess = array_slice($onlinePlayers, 0, min(count($onlinePlayers), 20));
        
        foreach ($playersToProcess as $player) {
            if (!$player->isOnline() || $player->getWorld() === null) {
                continue;
            }
            
            $playerPos = $player->getPosition();
            
            if ($playerPos->getY() > $this->maxSpawnHeight) {
                continue;
            }
            
            if (!$this->isDesertBiomeCached($playerPos)) {
                continue;
            }
            
            if (mt_rand(1, $this->spawnChance) !== 1) {
                continue;
            }
            
            for ($i = 0; $i < 2; $i++) {
                $this->trySpawnTumbleweedOptimized($playerPos);
            }
        }
    }
    
    private function isDesertBiomeCached(Position $pos): bool {
        $chunkX = $pos->getFloorX() >> 4;
        $chunkZ = $pos->getFloorZ() >> 4;
        $cacheKey = $chunkX . ":" . $chunkZ;
        $currentTick = Server::getInstance()->getTick();

        if (isset($this->cachedBiomes[$cacheKey])) {
            $cacheData = $this->cachedBiomes[$cacheKey];
            if ($currentTick - $cacheData['time'] < $this->biomeCacheLifetime) {
                return $cacheData['isDesert'];
            }
        }

        $world = $pos->getWorld();
        $x = $pos->getFloorX();
        $y = $pos->getFloorY();
        $z = $pos->getFloorZ();
        $biome = $world->getBiome($x, $y, $z);
        $biomeId = $biome->getId();
        $biomeName = $biome->getName();

        $isDesert = isset(self::DESERT_BIOMES[$biomeId]);

        $this->cachedBiomes[$cacheKey] = [
            'isDesert' => $isDesert,
            'time' => $currentTick
        ];

        return $isDesert;
    }

    private function cleanupBiomeCache(int $currentTick): void {
        foreach ($this->cachedBiomes as $key => $data) {
            if ($currentTick - $data['time'] >= $this->biomeCacheLifetime) {
                unset($this->cachedBiomes[$key]);
            }
        }
    }
    
    private function trySpawnTumbleweedOptimized(Position $playerPos): void {
        $world = $playerPos->getWorld();
        $maxAttempts = 12;
        
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $angle = mt_rand(0, 628) / 100.0;
            $distance = mt_rand($this->minSpawnDistance, $this->spawnRadius);
            
            $x = $playerPos->getX() + cos($angle) * $distance;
            $z = $playerPos->getZ() + sin($angle) * $distance;
            
            $floorX = (int) floor($x);
            $floorZ = (int) floor($z);
            
            if (!$world->isChunkLoaded($floorX >> 4, $floorZ >> 4)) {
                continue;
            }
            
            $y = min($world->getHighestBlockAt($floorX, $floorZ) + 1, (int) $this->maxSpawnHeight);
            
            $location = new Location($x, $y, $z, $world, 0.0, 0.0);
            
            if (!$this->isValidSpawnLocationFast($location)) {
                continue;
            }
            
            if (!$this->isDesertBiomeCached($location)) {
                continue;
            }
            
            $entity = new Tumbleweed($location);
            $entity->spawnToAll();
            
            return;
        }
    }
    
    private function isValidSpawnLocationFast(Position $pos): bool {
        $world = $pos->getWorld();
        $x = $pos->getFloorX();
        $y = $pos->getFloorY();
        $z = $pos->getFloorZ();
        
        if ($y <= 0 || $y >= 255) {
            return false;
        }
        
        $block1 = $world->getBlockAt($x, $y, $z);
        $block2 = $world->getBlockAt($x, $y + 1, $z);
        
        if (!$block1->canBeReplaced() || !$block2->canBeReplaced()) {
            return false;
        }
        
        $groundBlock = $world->getBlockAt($x, $y - 1, $z);
        if (!$groundBlock->isSolid() || $groundBlock->isTransparent()) {
            return false;
        }
        
        if ($block1->getTypeId() === VanillaBlocks::WATER()->getTypeId() || 
            $block1->getTypeId() === VanillaBlocks::LAVA()->getTypeId()) {
            return false;
        }
        
        return true;
    }
    
    public function getSpawnChance(): int { return $this->spawnChance; }
    public function getMaxTumbleweeds(): int { return $this->maxTumbleweeds; }
    public function getSpawnRadius(): int { return $this->spawnRadius; }
    public function getMinSpawnDistance(): int { return $this->minSpawnDistance; }
    public function getMaxSpawnHeight(): float { return $this->maxSpawnHeight; }
    
    public function setSpawnChance(int $chance): void { $this->spawnChance = max(1, $chance); }
    public function setMaxTumbleweeds(int $max): void { $this->maxTumbleweeds = max(1, $max); }
    public function setSpawnRadius(int $radius): void { $this->spawnRadius = max(5, $radius); }
    public function setMinSpawnDistance(int $distance): void { $this->minSpawnDistance = max(1, $distance); }
    public function setMaxSpawnHeight(float $height): void { $this->maxSpawnHeight = max(50.0, $height); }
    
    public function getCacheStats(): array {
        return [
            'cached_chunks' => count($this->cachedBiomes),
            'cache_lifetime' => $this->biomeCacheLifetime
        ];
    }
}