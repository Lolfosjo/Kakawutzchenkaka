<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\ground;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

class SandstonePatchGroundGenerator extends GroundGenerator{

	public function generateTerrainColumn(ChunkManager $world, Random $random, int $x, int $z, int $biome, float $surface_noise) : void{
		$this->setTopMaterial(match(true){
			$surface_noise > 1.75 => VanillaBlocks::SANDSTONE(),
			default => VanillaBlocks::SAND()
		});
		$this->setGroundMaterial(VanillaBlocks::SAND());
		parent::generateTerrainColumn($world, $random, $x, $z, $biome, $surface_noise);
	}
}