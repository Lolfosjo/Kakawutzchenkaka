<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\populator\biome;

use muqsit\vanillagenerator\generator\object\tree\BirchTree;
use muqsit\vanillagenerator\generator\object\tree\MegaBirchTree;
use muqsit\vanillagenerator\generator\object\tree\TallBirchTree;
use muqsit\vanillagenerator\generator\overworld\biome\BiomeIds;
use muqsit\vanillagenerator\generator\overworld\decorator\types\TreeDecoration;

class BirchForestMountainsPopulator extends ForestPopulator{

	private const BIOMES = [BiomeIds::BIRCH_FOREST_MUTATED, BiomeIds::BIRCH_FOREST_HILLS_MUTATED];

	/** @var TreeDecoration[] */
	protected static array $TREES;

	protected static function initTrees() : void{
		self::$TREES = [
			new TreeDecoration(BirchTree::class, 1),
			new TreeDecoration(TallBirchTree::class, 1),
			new TreeDecoration(MegaBirchTree::class, 1)
			
		];
	}

	protected function initPopulators() : void{
		$this->tree_decorator->setTrees(...self::$TREES);
		$this->fallen_birch_decorator->setAmount(2);
		$this->vanilla_bush_decorator->setAmount(5);
		$this->fallen_leaves_decorator->setAmount(30);
		$this->custom_rock_decorator->setAmount(10);
		$this->coneflower_decorator->setAmount(20);
	}

	public function getBiomes() : ?array{
		return self::BIOMES;
	}
}

BirchForestMountainsPopulator::init();