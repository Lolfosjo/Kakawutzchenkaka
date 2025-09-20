<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\populator\biome;

use muqsit\vanillagenerator\generator\object\tree\BirchTree;
use muqsit\vanillagenerator\generator\overworld\biome\BiomeIds;
use muqsit\vanillagenerator\generator\overworld\decorator\types\TreeDecoration;
use muqsit\vanillagenerator\generator\object\tree\MegaBirchTree;

class BirchForestPopulator extends ForestPopulator{

	private const BIOMES = [BiomeIds::BIRCH_FOREST, BiomeIds::BIRCH_FOREST_HILLS];

	/** @var TreeDecoration[] */
	protected static array $TREES;

	protected static function initTrees() : void{
		self::$TREES = [
			new TreeDecoration(BirchTree::class, 1),
			new TreeDecoration(MegaBirchTree::class, 1)
		];
	}

	protected function initPopulators() : void{
		parent::initPopulators();
		$this->tree_decorator->setTrees(...self::$TREES);
		$this->fallen_birch_decorator->setAmount(4);
		$this->vanilla_bush_decorator->setAmount(5);
		$this->fallen_leaves_decorator->setAmount(30);
		$this->custom_rock_decorator->setAmount(10);
		$this->coneflower_decorator->setAmount(30);
	}

	public function getBiomes() : ?array{
		return self::BIOMES;
	}
}

BirchForestPopulator::init();
