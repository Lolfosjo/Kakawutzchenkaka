<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\populator\biome;

use muqsit\vanillagenerator\generator\object\tree\PalmTree;
use muqsit\vanillagenerator\generator\overworld\biome\BiomeIds;
use muqsit\vanillagenerator\generator\overworld\decorator\types\TreeDecoration;

class BeachPopulator extends PlainsPopulator{

	private const BIOMES = [BiomeIds::BEACH];

	/** @var TreeDecoration[] */
	protected static array $TREES;

	protected static function initTrees() : void{
		self::$TREES = [
			new TreeDecoration(PalmTree::class, 5),
			
		];
	}

	protected function initPopulators() : void{
		$this->tree_decorator->setAmount(1);
		$this->tree_decorator->setTrees(...self::$TREES);
        $this->vanilla_bush_decorator->setAmount(5);
	}

	public function getBiomes() : ?array{
		return self::BIOMES;
	}
}

BeachPopulator::init();