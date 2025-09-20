<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\object\tree;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Axis;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;

class PalmTree extends GenericTree{

    private array $trunkPositions = [];

    public function __construct(Random $random, BlockTransaction $transaction){
        parent::__construct($random, $transaction);
        $this->setHeight(14 + $random->nextBoundedInt(3));
        $this->setType(VanillaBlocks::JUNGLE_LOG(), VanillaBlocks::JUNGLE_LEAVES());
        $this->trunkPositions = [];
    }

    public function canPlaceOn(Block $soil) : bool{
        $type = $soil->getTypeId();
        return $type === BlockTypeIds::GRASS || $type === BlockTypeIds::DIRT || $type === BlockTypeIds::SAND || $type === BlockTypeIds::PODZOL;
    }

    public function generate(ChunkManager $world, Random $random, int $source_x, int $source_y, int $source_z) : bool{
        if($this->cannotGenerateAt($source_x, $source_y, $source_z, $world)){
            return false;
        }

        $this->trunkPositions = [];

        $trunk_base_x = $source_x + 6;
        $trunk_base_z = $source_z + 5;
        
        if(!$this->checkAreaClear($world, $trunk_base_x, $source_y, $trunk_base_z)){
            return false;
        }
        
        $ground_y = $this->findGroundLevel($world, $trunk_base_x, $source_y, $trunk_base_z);
        
        $this->generateTrunk($world, $random, $trunk_base_x, $ground_y, $trunk_base_z);

        $this->generatePalmFronds($world, $random, $trunk_base_x, $ground_y, $trunk_base_z);

        return true;
    }

    private function generateTrunk(ChunkManager $world, Random $random, int $trunk_x, int $base_y, int $trunk_z) : void {
    
        for($y = 0; $y < $this->height - 6; ++$y){
            $this->setLog($trunk_x, $base_y + $y, $trunk_z, $world);
        }

        $curve_start_y = $base_y + $this->height - 6;
        
        $this->setLog($trunk_x, $curve_start_y, $trunk_z, $world);
        $this->setLog($trunk_x + 1, $curve_start_y + 1, $trunk_z, $world);
        $this->setLog($trunk_x + 2, $curve_start_y + 2, $trunk_z, $world);
        
        $this->setLog($trunk_x + 1, $curve_start_y + 3, $trunk_z, $world);
        $this->setLog($trunk_x, $curve_start_y + 4, $trunk_z, $world);
        $this->setLog($trunk_x, $curve_start_y + 5, $trunk_z, $world);

        $thickening_count = $random->nextBoundedInt(3) + 2;
        for($i = 0; $i < $thickening_count; ++$i){
            $offset_y = $random->nextBoundedInt($this->height - 8) + 1;
            $offset_x = $random->nextBoundedInt(3) - 1;
            $offset_z = $random->nextBoundedInt(3) - 1;
            
            if($offset_x !== 0 || $offset_z !== 0){
                $this->setWood($trunk_x + $offset_x, $base_y + $offset_y, $trunk_z + $offset_z, $world);
            }
        }
    }

    private function generatePalmFronds(ChunkManager $world, Random $random, int $center_x, int $base_y, int $center_z) : void{
        $frond_y = $base_y + $this->height - 2;
        
        $directions = [
            [4, 0],   
            [3, 1],   
            [0, 4],  
            [-3, 1],  
            [-4, 0],  
            [-3, -1], 
            [0, -4],
            [3, -1]  
        ];

        foreach($directions as $i => [$dir_x, $dir_z]){
            $this->generatePalmFrond($world, $random, $center_x, $frond_y, $center_z, $dir_x, $dir_z, $i);
        }

        for($layer = -1; $layer <= 1; ++$layer){
            $y = $frond_y + $layer;
            $radius = 2 - abs($layer);
            
            for($x = -$radius; $x <= $radius; ++$x){
                for($z = -$radius; $z <= $radius; ++$z){
                    $distance = abs($x) + abs($z);
                    
                    if($distance <= $radius && ($distance > 0 || $layer === 0)){
                        if($random->nextBoundedInt(100) < 75){
                            $this->setLeaves($center_x + $x, $y, $center_z + $z, $world);
                        }
                    }
                }
            }
        }
    }

    private function generatePalmFrond(ChunkManager $world, Random $random, int $start_x, int $start_y, int $start_z, int $dir_x, int $dir_z, int $frond_index) : void{
        $length = sqrt($dir_x * $dir_x + $dir_z * $dir_z);
        if($length == 0) return;
        
        $norm_x = $dir_x / $length;
        $norm_z = $dir_z / $length;
        
        $frond_length = 3 + $random->nextBoundedInt(2);
        
        for($i = 1; $i <= $frond_length; ++$i){
            $pos_x = $start_x + (int)round($norm_x * $i);
            $pos_z = $start_z + (int)round($norm_z * $i);
            
            $this->setLeaves($pos_x, $start_y, $pos_z, $world);
            
            if($i > 1){
                $side_x = (int)round(-$norm_z);
                $side_z = (int)round($norm_x);
                
                $side_width = max(1, 3 - $i);
                
                for($side = -$side_width; $side <= $side_width; ++$side){
                    if($side === 0) continue;
                    
                    $side_pos_x = $pos_x + $side_x * $side;
                    $side_pos_z = $pos_z + $side_z * $side;
                    
                    $probability = 80 - (abs($side) * 20) - ($i * 10);
                    if($random->nextBoundedInt(100) < $probability){
                        $this->setLeaves($side_pos_x, $start_y, $side_pos_z, $world);
                        
                        if($random->nextBoundedInt(100) < 30){
                            $this->setLeaves($side_pos_x, $start_y - 1, $side_pos_z, $world);
                        }
                    }
                }
            }
            
            if($i >= $frond_length - 1){
                $this->setLeaves($pos_x, $start_y - 1, $pos_z, $world);
                if($random->nextBoundedInt(100) < 50){
                    $this->setLeaves($pos_x, $start_y - 2, $pos_z, $world);
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

    private function setWood(int $x, int $y, int $z, ChunkManager $world) : void{
        if($this->canReplaceBlock($world->getBlockAt($x, $y, $z))){
            $this->transaction->addBlockAt($x, $y, $z, VanillaBlocks::JUNGLE_WOOD());
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
        $check_radius = 2;
        $check_height = 18;
        
        for($x = $center_x - $check_radius; $x <= $center_x + $check_radius; ++$x){
            for($z = $center_z - $check_radius; $z <= $center_z + $check_radius; ++$z){
                for($y = $center_y; $y < $center_y + $check_height; ++$y){
                    $block = $world->getBlockAt($x, $y, $z);
                    
                    if($this->isWoodBlock($block)){
                        return false;
                    }
                    
                    if($block instanceof Leaves){
                        $leaf_count = $this->countNearbyLeaves($world, $x, $y, $z, 2);
                        if($leaf_count > 15){
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
               $type === BlockTypeIds::BEDROCK ||
               $type === BlockTypeIds::SANDSTONE){
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
               $type === BlockTypeIds::DANDELION ||
               $type === BlockTypeIds::CACTUS;
    }

    public static function getSize(): array {
        return [14, 16, 11];
    }
}