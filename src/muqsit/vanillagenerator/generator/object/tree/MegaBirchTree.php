<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\object\tree;

use muqsit\RegisterBlockDemoPM5\ExtraVanillaBlocks;
use customiesdevs\customies\block\CustomiesBlockFactory;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;

class MegaBirchTree extends GenericTree{

    private array $trunkPositions = [];

    public function __construct(Random $random, BlockTransaction $transaction){
        parent::__construct($random, $transaction);
        $this->setHeight(12 + $random->nextBoundedInt(4));
        $this->setType(VanillaBlocks::BIRCH_LOG(), VanillaBlocks::BIRCH_LEAVES());
        $this->trunkPositions = []; 
    }

    public function canPlaceOn(Block $soil) : bool{
        $type = $soil->getTypeId();
        
        $mossBlockTypeId = null;
        try {
            $mossBlock = ExtraVanillaBlocks::MOSS_BLOCK();
            if ($mossBlock !== null) {
                $mossBlockTypeId = $mossBlock->getTypeId();
            }
        } catch (\Error $e) {
            $mossBlockTypeId = null;
        }
        
        return $type === BlockTypeIds::GRASS || 
               $type === BlockTypeIds::DIRT || 
               $type === BlockTypeIds::PODZOL || 
               ($mossBlockTypeId !== null && $type === $mossBlockTypeId);
    }

    public function generate(ChunkManager $world, Random $random, int $source_x, int $source_y, int $source_z) : bool{
        if($this->cannotGenerateAt($source_x, $source_y, $source_z, $world)){
            return false;
        }

        $this->trunkPositions = [];

        $trunk_base_x = $source_x + 7;
        $trunk_base_z = $source_z + 3;
        
        if(!$this->checkAreaClear($world, $trunk_base_x, $source_y, $trunk_base_z)){
            return false;
        }
        
        $ground_y = $this->findGroundLevel($world, $trunk_base_x, $source_y, $trunk_base_z);
        
        $this->generateTrunk($world, $random, $trunk_base_x, $ground_y, $trunk_base_z);
        $this->generateBranches($world, $random, $trunk_base_x, $ground_y, $trunk_base_z);

        $canopy_y = $ground_y + $this->height - 3;
        $this->generateMainCanopy($world, $trunk_base_x, $canopy_y, $trunk_base_z);
        $this->generateBranchCanopies($world, $random, $trunk_base_x, $ground_y, $trunk_base_z);

        return true;
    }

    private function generateTrunk(ChunkManager $world, Random $random, int $trunk_x, int $base_y, int $trunk_z) : void {
        for($y = 0; $y < $this->height - 3; ++$y){
            $this->setLog($trunk_x, $base_y + $y, $trunk_z, $world);
            
            if($random->nextBoundedInt(100) < 60){
                $this->tryPlaceBaumpilz($world, $random, $trunk_x, $base_y + $y, $trunk_z);
            }
        }

        $extraCount = mt_rand(2, 5);
        for($i = 0; $i < $extraCount; $i++){
            $offsetX = mt_rand(-1, 1);
            $offsetZ = mt_rand(-1, 1);
            $offsetY = mt_rand(1, $this->height - 2);

            if($offsetX !== 0 || $offsetZ !== 0){
                $this->setWood($trunk_x + $offsetX, $base_y + $offsetY, $trunk_z + $offsetZ, $world);
                
                if($random->nextBoundedInt(100) < 10){
                    $this->tryPlaceBaumpilz($world, $random, $trunk_x + $offsetX, $base_y + $offsetY, $trunk_z + $offsetZ);
                }
            }
        }
    }

    private function generateBranches(ChunkManager $world, Random $random, int $base_x, int $base_y, int $base_z) : void{
        $branch_y = $base_y + 7;
        for($i = 1; $i < 3; ++$i){
            $this->setRotatedLog($base_x + $i, $branch_y, $base_z, $world, 'x');

            if($random->nextBoundedInt(100) < 8){
                $this->tryPlaceBaumpilz($world, $random, $base_x + $i, $branch_y, $base_z);
            }
        }

        $branch_y = $base_y + 9;
        $this->setRotatedLog($base_x + 1, $branch_y, $base_z - 1, $world, 'y');
        $this->setRotatedLog($base_x + 2, $branch_y, $base_z - 2, $world, 'z');
        
        if($random->nextBoundedInt(100) < 8){
            $this->tryPlaceBaumpilz($world, $random, $base_x + 1, $branch_y, $base_z - 1);
        }
        if($random->nextBoundedInt(100) < 8){
            $this->tryPlaceBaumpilz($world, $random, $base_x + 2, $branch_y, $base_z - 2);
        }

        $branch_y = $base_y + 6;
        $this->setRotatedLog($base_x - 1, $branch_y, $base_z, $world, 'x');
        $this->setRotatedLog($base_x - 2, $branch_y, $base_z, $world, 'x');
        
        if($random->nextBoundedInt(100) < 8){
            $this->tryPlaceBaumpilz($world, $random, $base_x - 1, $branch_y, $base_z);
        }
        if($random->nextBoundedInt(100) < 8){
            $this->tryPlaceBaumpilz($world, $random, $base_x - 2, $branch_y, $base_z);
        }

        $branch_y = $base_y + 8;
        $this->setRotatedLog($base_x, $branch_y, $base_z + 1, $world, 'z');
        $this->setRotatedLog($base_x, $branch_y, $base_z + 2, $world, 'z');
        
        if($random->nextBoundedInt(100) < 8){
            $this->tryPlaceBaumpilz($world, $random, $base_x, $branch_y, $base_z + 1);
        }
        if($random->nextBoundedInt(100) < 8){
            $this->tryPlaceBaumpilz($world, $random, $base_x, $branch_y, $base_z + 2);
        }
    }

    private function tryPlaceBaumpilz(ChunkManager $world, Random $random, int $logX, int $logY, int $logZ) : void {
        $directions = [
            [0, 0, 1], 
            [0, 0, -1], 
            [1, 0, 0], 
            [-1, 0, 0], 
        ];
        
        $direction = $directions[$random->nextBoundedInt(count($directions))];
        $pilzX = $logX + $direction[0];
        $pilzY = $logY + $direction[1];
        $pilzZ = $logZ + $direction[2];
        
        $targetBlock = $world->getBlockAt($pilzX, $pilzY, $pilzZ);
        if($this->canReplaceBlock($targetBlock)){
            try {
                $baumpilzBlock = CustomiesBlockFactory::getInstance()->get("custom:baumpilz");
                if($baumpilzBlock !== null){
                    $this->transaction->addBlockAt($pilzX, $pilzY, $pilzZ, $baumpilzBlock);
                }
            } catch (\Error $e) {
                
            }
        }
    }

    private function generateMainCanopy(ChunkManager $world, int $center_x, int $center_y, int $center_z) : void{
        for($layer = 0; $layer < 4; ++$layer){
            $y = $center_y + $layer;
            $radius = 4 - $layer; 
            
            for($x = -$radius; $x <= $radius; ++$x){
                for($z = -$radius; $z <= $radius; ++$z){
                    $distance = sqrt($x * $x + $z * $z);
                    
                    if($distance <= $radius){
                        if($distance < $radius - 0.5 || mt_rand(0, 100) < 85){
                            $this->setLeaves($center_x + $x, $y, $center_z + $z, $world);
                        }
                    }
                }
            }
        }
    }

    private function generateBranchCanopies(ChunkManager $world, Random $random, int $base_x, int $base_y, int $base_z) : void{
        $canopy_centers = [
            [$base_x + 2, $base_y + 8, $base_z],    
            [$base_x + 2, $base_y + 10, $base_z - 2],   
            [$base_x - 2, $base_y + 7, $base_z],   
            [$base_x, $base_y + 9, $base_z + 2],     
        ];

        foreach($canopy_centers as [$cx, $cy, $cz]){
            for($layer = -1; $layer <= 2; ++$layer){
                $y = $cy + $layer;
                $radius = $layer === -1 || $layer === 2 ? 1 : 2;
                
                for($x = -$radius; $x <= $radius; ++$x){
                    for($z = -$radius; $z <= $radius; ++$z){
                        $distance = abs($x) + abs($z);
                        
                        if($distance <= $radius + ($layer === 0 ? 1 : 0)){
                            if($distance <= $radius || $random->nextBoundedInt(100) < 60){
                                $this->setLeaves($cx + $x, $y, $cz + $z, $world);
                            }
                        }
                    }
                }
            }
        }
    }

    private function setLog(int $x, int $y, int $z, ChunkManager $world) : void{
        if($this->canReplaceBlock($world->getBlockAt($x, $y, $z))){
            $this->transaction->addBlockAt($x, $y, $z, $this->log_type);
            $this->trunkPositions["$x,$y,$z"] = true;
        }
    }

    private function setRotatedLog(int $x, int $y, int $z, ChunkManager $world, string $axis) : void{
        if($this->canReplaceBlock($world->getBlockAt($x, $y, $z))){
            $log_block = VanillaBlocks::BIRCH_LOG();
            
            switch($axis){
                case 'x':
                    $log_block = VanillaBlocks::BIRCH_LOG()->setAxis(Axis::X);
                    break;
                case 'z':
                    $log_block = VanillaBlocks::BIRCH_LOG()->setAxis(Axis::Z);
                    break;
                case 'y':
                default:
                    $log_block = VanillaBlocks::BIRCH_LOG()->setAxis(Axis::Y);
                    break;
            }
            
            $this->transaction->addBlockAt($x, $y, $z, $log_block);
            $this->trunkPositions["$x,$y,$z"] = true;
        }
    }

    private function setWood(int $x, int $y, int $z, ChunkManager $world) : void{
        if($this->canReplaceBlock($world->getBlockAt($x, $y, $z))){
            $this->transaction->addBlockAt($x, $y, $z, VanillaBlocks::BIRCH_WOOD());
            $this->trunkPositions["$x,$y,$z"] = true;
        }
    }

    private function setLeaves(int $x, int $y, int $z, ChunkManager $world) : void{
        if(isset($this->trunkPositions["$x,$y,$z"])){
            return;
        }
        
        if($this->canReplaceBlock($world->getBlockAt($x, $y, $z))){
            $this->transaction->addBlockAt($x, $y, $z, $this->leaves_type);
        }
    }

    private function checkAreaClear(ChunkManager $world, int $center_x, int $center_y, int $center_z) : bool{
        $check_radius = 3;
        $check_height = 20;
        
        for($x = $center_x - $check_radius; $x <= $center_x + $check_radius; ++$x){
            for($z = $center_z - $check_radius; $z <= $center_z + $check_radius; ++$z){
                for($y = $center_y; $y < $center_y + $check_height; ++$y){
                    $block = $world->getBlockAt($x, $y, $z);
                    
                    if($this->isWoodBlock($block)){
                        return false;
                    }
                    
                    if($block instanceof Leaves){
                        $leaf_count = $this->countNearbyLeaves($world, $x, $y, $z, 3);
                        if($leaf_count >15){
                            return false;
                        }
                    }
                }
            }
        }
        
        return true;
    }

    private function isWoodBlock(Block $block) : bool{
        $type = $block->getTypeId();
        return $type === BlockTypeIds::OAK_LOG ||
               $type === BlockTypeIds::SPRUCE_LOG ||
               $type === BlockTypeIds::BIRCH_LOG ||
               $type === BlockTypeIds::JUNGLE_LOG ||
               $type === BlockTypeIds::ACACIA_LOG ||
               $type === BlockTypeIds::DARK_OAK_LOG ||
               $type === BlockTypeIds::CRIMSON_STEM ||
               $type === BlockTypeIds::WARPED_STEM ||
               $type === BlockTypeIds::OAK_WOOD ||
               $type === BlockTypeIds::SPRUCE_WOOD ||
               $type === BlockTypeIds::BIRCH_WOOD ||
               $type === BlockTypeIds::JUNGLE_WOOD ||
               $type === BlockTypeIds::ACACIA_WOOD ||
               $type === BlockTypeIds::DARK_OAK_WOOD;
    }

    private function countNearbyLeaves(ChunkManager $world, int $center_x, int $center_y, int $center_z, int $radius) : int{
        $count = 0;
        
        for($x = $center_x - $radius; $x <= $center_x + $radius; ++$x){
            for($y = $center_y - $radius; $y <= $center_y + $radius; ++$y){
                for($z = $center_z - $radius; $z <= $center_z + $radius; ++$z){
                    if($world->getBlockAt($x, $y, $z) instanceof Leaves){
                        ++$count;
                    }
                }
            }
        }
        
        return $count;
    }
    
    private function findGroundLevel(ChunkManager $world, int $x, int $start_y, int $z) : int{
        for($y = $start_y; $y >= 0; --$y){
            $block = $world->getBlockAt($x, $y, $z);
            if($this->canPlaceOn($block)){
                return $y + 1;
            }
            
            $type = $block->getTypeId();
            if($type === BlockTypeIds::STONE || 
               $type === BlockTypeIds::DEEPSLATE || 
               $type === BlockTypeIds::BEDROCK){
                return $y + 1;
            }
        }
        
        return $start_y;
    }

    private function canReplaceBlock(Block $block) : bool{
        $type = $block->getTypeId();
        return $type === BlockTypeIds::AIR || 
               $block instanceof Leaves ||
               $type === BlockTypeIds::GRASS ||
               $type === BlockTypeIds::FERN ||
               $type === BlockTypeIds::DEAD_BUSH ||
               $type === BlockTypeIds::TALL_GRASS ||
               $type === BlockTypeIds::POPPY ||
               $type === BlockTypeIds::DANDELION;
    }

    public static function getSize(): array {
        return [14, 15, 8];
    }
}