<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\populator\biome;

use muqsit\vanillagenerator\generator\overworld\biome\BiomeIds;

class MesaPopulator extends BiomePopulator{

	protected function initPopulators() : void{
		$this->cactus_decorator->setAmount(3);
		$this->sukkulenten_decorator->setAmount(2);
		$this->desert_grass_decorator->setAmount(2);
	}

	public function getBiomes() : ?array{
		return [BiomeIds::MESA_BRYCE, BiomeIds::MESA, BiomeIds::MESA_PLATEAU, BiomeIds::MESA_PLATEAU_MUTATED, BiomeIds::MESA_PLATEAU_STONE, BiomeIds::MESA_PLATEAU_STONE_MUTATED];
	}
}