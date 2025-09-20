<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\decorator;

use muqsit\vanillagenerator\generator\Decorator;
use muqsit\vanillagenerator\generator\object\FallenBirch;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

class FallenBirchDecorator extends Decorator{

	public function decorate(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		if($random->nextBoundedInt(10) >= 8){
			return;
		}

		$source_x = $chunk_x << Chunk::COORD_BIT_SIZE;
		$source_z = $chunk_z << Chunk::COORD_BIT_SIZE;
		$x = $random->nextBoundedInt(16);
		$z = $random->nextBoundedInt(16);
		$sourceY = $random->nextBoundedInt($chunk->getHighestBlockAt($x, $z) << 1);

		for($l = 0; $l < 2; ++$l){
			$i = $random->nextBoundedInt(8) - $random->nextBoundedInt(8);
			$k = $random->nextBoundedInt(8) - $random->nextBoundedInt(8);
			$j = $sourceY + $random->nextBoundedInt(4) - $random->nextBoundedInt(4);
			(new FallenBirch())->generate($world, $random, $source_x + $x + $i, $j, $source_z + $z + $k);
		}
	}
}