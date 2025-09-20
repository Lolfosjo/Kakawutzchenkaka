<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\decorator;

use muqsit\vanillagenerator\generator\Decorator;
use customiesdevs\customies\block\CustomiesBlockFactory;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

class DesertGrassDecorator extends Decorator{

	private const SOIL_TYPES = [BlockTypeIds::SAND, BlockTypeIds::SANDSTONE];

	public function decorate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$source_x = ($chunk_x << Chunk::COORD_BIT_SIZE) + $random->nextBoundedInt(16);
		$source_z = ($chunk_z << Chunk::COORD_BIT_SIZE) + $random->nextBoundedInt(16);
		$source_y = $random->nextBoundedInt($chunk->getHighestBlockAt($source_x & Chunk::COORD_MASK, $source_z & Chunk::COORD_MASK) << 1);
		
		while($source_y > 0
			&& ($world->getBlockAt($source_x, $source_y, $source_z)->getTypeId() === BlockTypeIds::AIR
				|| $world->getBlockAt($source_x, $source_y, $source_z) instanceof Leaves)){
			--$source_y;
		}

		$group_size = $random->nextBoundedInt(5) + 1;
		
		$this->generateGrassGroup($world, $random, $source_x, $source_y, $source_z, $group_size);
	}

	private function generateGrassGroup(ChunkManager $world, Random $random, int $center_x, int $center_y, int $center_z, int $group_size) : void{
		$desertGrass = CustomiesBlockFactory::getInstance()->get("foxymc:desert_grass");
		if($desertGrass === null){
			return;
		}

		$placed_count = 0;
		$attempts = 0;
		$max_attempts = $group_size * 10;

		while($placed_count < $group_size && $attempts < $max_attempts){
			$attempts++;

			$radius = ($group_size > 1) ? $random->nextBoundedInt(4) + 1 : 0;
			$x = $center_x + $random->nextBoundedInt($radius * 2 + 1) - $radius;
			$z = $center_z + $random->nextBoundedInt($radius * 2 + 1) - $radius;
			$y = $center_y + $random->nextBoundedInt(3) - $random->nextBoundedInt(3);

			if($this->canPlaceGrass($world, $x, $y, $z)){
				$world->setBlockAt($x, $y, $z, $desertGrass);
				$placed_count++;
			}
		}
	}

	private function canPlaceGrass(ChunkManager $world, int $x, int $y, int $z) : bool{
		if($world->getBlockAt($x, $y, $z)->getTypeId() !== BlockTypeIds::AIR){
			return false;
		}

		$block_below = $world->getBlockAt($x, $y - 1, $z)->getTypeId();
		foreach(self::SOIL_TYPES as $soil){
			if($soil === $block_below){
				return true;
			}
		}

		return false;
	}
}