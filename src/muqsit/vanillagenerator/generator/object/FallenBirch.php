<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\object;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pmmp\RegisterBlockDemoPM5\ExtraVanillaBlocks;

class FallenBirch extends TerrainObject {

    private const MIN_LENGTH = 4;
    private const MAX_LENGTH = 7;

    public function generate(ChunkManager $world, Random $random, int $source_x, int $source_y, int $source_z) : bool {
        $treeLength = $random->nextBoundedInt(self::MAX_LENGTH - self::MIN_LENGTH + 1) + self::MIN_LENGTH;

        if(!$this->canPlaceStructure($world, $source_x, $source_y, $source_z, $treeLength)){
            return false;
        }

        $isXAxis = $random->nextBoolean();
        
        $treeY = $this->findGroundLevel($world, $source_x, $source_y, $source_z);
        
        for($i = 0; $i < $treeLength; ++$i) {
            $currentX = $source_x + ($isXAxis ? $i : 0);
            $currentZ = $source_z + ($isXAxis ? 0 : $i);
            
            $logBlock = VanillaBlocks::BIRCH_LOG()->setAxis($isXAxis ? Axis::X : Axis::Z);
            $world->setBlockAt($currentX, $treeY, $currentZ, $logBlock);
        }

        $this->addMossOnLogs($world, $random, $source_x, $source_y, $source_z, $treeLength, $isXAxis, $treeY);

        $this->addDecorations($world, $random, $source_x, $source_y, $source_z, $treeLength, $isXAxis);

        return true;
    }

    private function canPlaceStructure(ChunkManager $world, int $x, int $y, int $z, int $length) : bool {
        $groundBlock = $world->getBlockAt($x, $y - 1, $z);
        $validBlocks = [
            VanillaBlocks::GRASS()->getTypeId(),
            VanillaBlocks::DIRT()->getTypeId(),
            VanillaBlocks::PODZOL()->getTypeId(),
            VanillaBlocks::STONE()->getTypeId()
        ];
        
        return in_array($groundBlock->getTypeId(), $validBlocks, true);
    }

    private function findGroundLevel(ChunkManager $world, int $x, int $start_y, int $z) : int {
        for($y = $start_y; $y >= $start_y - 5; --$y) {
            $block = $world->getBlockAt($x, $y - 1, $z);
            if($this->isValidGround($block)) {
                return $y;
            }
        }
        return $start_y;
    }

    private function isValidGround(Block $block) : bool {
        $validTypes = [
            VanillaBlocks::GRASS()->getTypeId(),
            VanillaBlocks::DIRT()->getTypeId(),
            VanillaBlocks::PODZOL()->getTypeId(),
            VanillaBlocks::STONE()->getTypeId()
        ];
        
        return in_array($block->getTypeId(), $validTypes, true);
    }

    private function addMossOnLogs(ChunkManager $world, Random $random, int $x, int $y, int $z, int $length, bool $isXAxis, int $treeY) : void {
        for($i = 0; $i < $length; ++$i) {
            if($random->nextBoundedInt(100) < 30) { 
                $logX = $x + ($isXAxis ? $i : 0);
                $logZ = $z + ($isXAxis ? 0 : $i);
                
                $world->setBlockAt($logX, $treeY + 1, $logZ, ExtraVanillaBlocks::MOSS_CARPET());
            }
        }
    }

    private function addDecorations(ChunkManager $world, Random $random, int $x, int $y, int $z, int $length, bool $isXAxis) : void {
        for($i = -2; $i < $length + 2; ++$i) {
            for($side = -1; $side <= 1; ++$side) {
                if($random->nextBoundedInt(100) < 15) {
                    $decorX = $x + ($isXAxis ? $i : $side);
                    $decorZ = $z + ($isXAxis ? $side : $i);
                    
                    $groundY = $this->findGroundLevel($world, $decorX, $y, $decorZ);
                    $groundBlock = $world->getBlockAt($decorX, $groundY - 1, $decorZ);
                    $currentBlock = $world->getBlockAt($decorX, $groundY, $decorZ);
                    
                    if($currentBlock->getTypeId() === VanillaBlocks::AIR()->getTypeId() && 
                       $this->isValidGround($groundBlock)) {
                        
                        $decorationRoll = $random->nextBoundedInt(100);
                        
                        if($decorationRoll < 25) {
                            $world->setBlockAt($decorX, $groundY, $decorZ, ExtraVanillaBlocks::MOSS_CARPET());
                        } elseif($decorationRoll < 40) {
                            $world->setBlockAt($decorX, $groundY, $decorZ, VanillaBlocks::BROWN_MUSHROOM());
                        } elseif($decorationRoll < 50) {
                            $world->setBlockAt($decorX, $groundY, $decorZ, VanillaBlocks::RED_MUSHROOM());
                        } elseif($decorationRoll < 65) {
                            $world->setBlockAt($decorX, $groundY, $decorZ, VanillaBlocks::GRASS());
                        } elseif($decorationRoll < 75) {
                            $world->setBlockAt($decorX, $groundY, $decorZ, VanillaBlocks::FERN());
                        } else {
                            $world->setBlockAt($decorX, $groundY, $decorZ, VanillaBlocks::DEAD_BUSH());
                        }
                    }
                }
            }
        }
    }
}