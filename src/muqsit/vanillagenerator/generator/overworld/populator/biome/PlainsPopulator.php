<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld\populator\biome;

use muqsit\vanillagenerator\generator\noise\bukkit\OctaveGenerator;
use muqsit\vanillagenerator\generator\noise\glowstone\SimplexOctaveGenerator;
use muqsit\vanillagenerator\generator\object\DoubleTallPlant;
use muqsit\vanillagenerator\generator\object\Flower;
use muqsit\vanillagenerator\generator\object\TallGrass;
use muqsit\vanillagenerator\generator\overworld\decorator\types\TreeDecoration;
use muqsit\vanillagenerator\generator\object\tree\NormalBush;
use muqsit\vanillagenerator\generator\overworld\biome\BiomeIds;
use customiesdevs\customies\block\CustomiesBlockFactory;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;

class PlainsPopulator extends BiomePopulator{

	/** @var Block[] */
	protected static array $PLAINS_FLOWERS;

	/** @var Block[] */
	protected static array $PLAINS_TULIPS;

	protected static function initTrees() : void{
		self::$TREES = [
			new TreeDecoration(NormalBush::class, 10),
		];
	}

	public static function init() : void{
		parent::init();

		$coneflower = CustomiesBlockFactory::getInstance()->get("foxymc:coneflower");

		self::$PLAINS_FLOWERS = [
			VanillaBlocks::POPPY(),
			VanillaBlocks::AZURE_BLUET(),
			VanillaBlocks::OXEYE_DAISY(),
			$coneflower
		];

		self::$PLAINS_TULIPS = [
			VanillaBlocks::RED_TULIP(),
			VanillaBlocks::ORANGE_TULIP(),
			VanillaBlocks::WHITE_TULIP(),
			VanillaBlocks::PINK_TULIP()
		];
	}

	private OctaveGenerator $noise_gen;

	public function __construct(){
		parent::__construct();
		$this->noise_gen = SimplexOctaveGenerator::fromRandomAndOctaves(new Random(2345), 1, 0, 0, 0);
		$this->noise_gen->setScale(1 / 200.0);
	}

	protected function initPopulators() : void{
		$this->tree_decorator->setAmount(1);
		$this->tree_decorator->setTrees(...self::$TREES);
		$this->flower_decorator->setAmount(0);
		$this->tall_grass_decorator->setAmount(0);
		$this->vanilla_bush_decorator->setAmount(5);
		$this->coneflower_decorator->setAmount(30);
	}

	public function getBiomes() : ?array{
		return [BiomeIds::PLAINS];
	}

	public function populateOnGround(ChunkManager $world, Random $random, int $chunk_x, int $chunk_z, Chunk $chunk) : void{
		$source_x = $chunk_x << Chunk::COORD_BIT_SIZE;
		$source_z = $chunk_z << Chunk::COORD_BIT_SIZE;

		$flower_amount = 15;
		$tall_grass_amount = 5;
		if($this->noise_gen->noise($source_x + 8, $source_z + 8, 0, 0.5, 2.0, false) >= -0.8){
			$flower_amount = 4;
			$tall_grass_amount = 10;
			for($i = 0; $i < 7; ++$i){
				$x = $random->nextBoundedInt(16);
				$z = $random->nextBoundedInt(16);
				$y = $random->nextBoundedInt($chunk->getHighestBlockAt($x, $z) + 32);
				(new DoubleTallPlant(VanillaBlocks::DOUBLE_TALLGRASS()))->generate($world, $random, $source_x + $x, $y, $source_z + $z);
			}
		}

		$flower = match(true){
			$this->noise_gen->noise($source_x + 8, $source_z + 8, 0, 0.5, 2.0, false) < -0.8 => self::$PLAINS_TULIPS[$random->nextBoundedInt(count(self::$PLAINS_TULIPS))],
			$random->nextBoundedInt(3) > 0 => self::$PLAINS_FLOWERS[$random->nextBoundedInt(count(self::$PLAINS_FLOWERS))],
			default => VanillaBlocks::DANDELION()
		};

		for($i = 0; $i < $flower_amount; ++$i){
			$x = $random->nextBoundedInt(16);
			$z = $random->nextBoundedInt(16);
			$y = $random->nextBoundedInt($chunk->getHighestBlockAt($x, $z) + 32);
			(new Flower($flower))->generate($world, $random, $source_x + $x, $y, $source_z + $z);
		}

		for($i = 0; $i < $tall_grass_amount; ++$i){
			$x = $random->nextBoundedInt(16);
			$z = $random->nextBoundedInt(16);
			$y = $random->nextBoundedInt($chunk->getHighestBlockAt($x, $z) << 1);
			(new TallGrass(VanillaBlocks::TALL_GRASS()))->generate($world, $random, $source_x + $x, $y, $source_z + $z);
		}

		parent::populateOnGround($world, $random, $chunk_x, $chunk_z, $chunk);
	}
}

PlainsPopulator::init();