<?php
declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\decorator;

use muqsit\vanillagenerator\generator\Decorator;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

class PumpkinDecorator extends Decorator{
	private const FACES = [Facing::NORTH, Facing::EAST, Facing::SOUTH, Facing::WEST];
	
	public function decorate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		if($random->nextBoundedInt(8) === 0){
			$source_x = ($chunk_x << Chunk::COORD_BIT_SIZE) + $random->nextBoundedInt(16);
			$source_z = ($chunk_z << Chunk::COORD_BIT_SIZE) + $random->nextBoundedInt(16);
			$source_y = $random->nextBoundedInt($chunk->getHighestBlockAt($source_x & Chunk::COORD_MASK, $source_z & Chunk::COORD_MASK) << 1);
			
			$pumpkin_count = $random->nextBoundedInt(3) + 1;
			
			for($i = 0; $i < $pumpkin_count; ++$i){
				$x = $source_x + $random->nextBoundedInt(32) - $random->nextBoundedInt(32);
				$z = $source_z + $random->nextBoundedInt(32) - $random->nextBoundedInt(32);
				$y = $source_y + $random->nextBoundedInt(8) - $random->nextBoundedInt(8);
				
				if($this->isValidPosition($world, $x, $y, $z)){
					$world->setBlockAt($x, $y, $z, VanillaBlocks::CARVED_PUMPKIN()->setFacing(self::FACES[$random->nextBoundedInt(count(self::FACES))]));
				}
			}
		}
	}
	
	private function isValidPosition(ChunkManager $world, int $x, int $y, int $z) : bool{
		if($world->getBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::AIR){
			return false;
		}
		
		$ground_block = $world->getBlockAt($x, $y - 1, $z)->getTypeId();
		$valid_ground = [
			BlockTypeIds::GRASS,
			BlockTypeIds::DIRT,
			BlockTypeIds::COARSE_DIRT,
			BlockTypeIds::PODZOL
		];
		
		if(!in_array($ground_block, $valid_ground, true)){
			return false;
		}
		
		for($dx = -2; $dx <= 2; ++$dx){
			for($dz = -2; $dz <= 2; ++$dz){
				if($dx === 0 && $dz === 0) continue;
				
				$check_block = $world->getBlockAt($x + $dx, $y, $z + $dz)->getTypeId();
				if($check_block === BlockTypeIds::CARVED_PUMPKIN || $check_block === BlockTypeIds::PUMPKIN){
					return false;
				}
			}
		}
		
		return true;
	}
}
