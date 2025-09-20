<?php
declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\object;

use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pmmp\RegisterBlockDemoPM5\ExtraVanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

class Cactus extends TerrainObject{
	private const FACES = [Facing::NORTH, Facing::EAST, Facing::SOUTH, Facing::WEST];
	
	public function generate(ChunkManager $world, Random $random, int $source_x, int $source_y, int $source_z) : bool{
		if($world->getBlockAt($source_x, $source_y, $source_z)->getTypeId() === BlockTypeIds::AIR){
			$height = $random->nextBoundedInt($random->nextBoundedInt(3) + 1) + 1;
			$top_cactus_y = null;
			$successfully_placed = false;
			
			for($n = $source_y; $n < $source_y + $height; ++$n){
				$vec = new Vector3($source_x, $n, $source_z);
				$type_below = $world->getBlockAt($source_x, $n - 1, $source_z)->getTypeId();
				
				if(($type_below === BlockTypeIds::SAND || $type_below === BlockTypeIds::CACTUS) && $world->getBlockAt($source_x, $n + 1, $source_z)->getTypeId() === BlockTypeIds::AIR){
					$blocked = false;
					foreach(self::FACES as $face){
						$face_pos = $vec->getSide($face);
						if($world->getBlockAt($face_pos->x, $face_pos->y, $face_pos->z)->isSolid()){
							$blocked = true;
							break;
						}
					}
					
					if($blocked){
						if($top_cactus_y !== null && $random->nextFloat() < 0.3){
							$this->placeCactusFlower($world, $source_x, $top_cactus_y, $source_z);
						}
						return $successfully_placed;
					}
					
					$world->setBlockAt($source_x, $n, $source_z, VanillaBlocks::CACTUS());
					$top_cactus_y = $n;
					$successfully_placed = true;
				} else {
					break;
				}
			}
			
			if($top_cactus_y !== null && $successfully_placed){
				if($random->nextFloat() < 0.4){
					$this->placeCactusFlower($world, $source_x, $top_cactus_y, $source_z);
				}
			}
			
			return $successfully_placed;
		}
		return false;
	}
	
	private function placeCactusFlower(ChunkManager $world, int $x, int $cactus_y, int $z) : void{
		$flower_y = $cactus_y + 1;
		
		if($world->getBlockAt($x, $flower_y, $z)->getTypeId() === BlockTypeIds::AIR){
			$vec = new Vector3($x, $flower_y, $z);
			
			foreach(self::FACES as $face){
				$face_pos = $vec->getSide($face);
				if($world->getBlockAt($face_pos->x, $face_pos->y, $face_pos->z)->isSolid()){
					return;
				}
			}
			
			$world->setBlockAt($x, $flower_y, $z, ExtraVanillaBlocks::CACTUS_FLOWER());
		}
	}
}
