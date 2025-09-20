<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\object\tree;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;

class NormalBush extends GenericTree{

	/**
	 * Initializes this bush, preparing it to attempt to generate.
	 * @param Random $random
	 * @param BlockTransaction $transaction
	 */
	public function __construct(Random $random, BlockTransaction $transaction){
		parent::__construct($random, $transaction);
		$this->setType(VanillaBlocks::OAK_LOG(), VanillaBlocks::OAK_LEAVES());
	}

	public function canPlaceOn(Block $soil) : bool{
		$id = $soil->getTypeId();
		return $id === BlockTypeIds::GRASS || $id === BlockTypeIds::DIRT;
	}

	public function generate(ChunkManager $world, Random $random, int $source_x, int $source_y, int $source_z) : bool{
		while((
			($block = $world->getBlockAt($source_x, $source_y, $source_z))->getTypeId() === BlockTypeIds::AIR ||
			$block instanceof Leaves
		) && $source_y > 0){
			--$source_y;
		}

		if(!$this->canPlaceOn($world->getBlockAt($source_x, $source_y, $source_z))){
			return false;
		}

		$adjust_y = $source_y;
		$this->transaction->addBlockAt($source_x, $adjust_y + 1, $source_z, $this->log_type);

		for($y = $adjust_y + 1; $y <= $adjust_y + 2; ++$y){
			$radius = match($y - $adjust_y){
				1 => 1,
				2 => 1
			};

			for($x = $source_x - $radius; $x <= $source_x + $radius; ++$x){
				for($z = $source_z - $radius; $z <= $source_z + $radius; ++$z){
					if(
						!$this->transaction->fetchBlockAt($x, $y, $z)->isSolid() &&
						(
							abs($x - $source_x) !== $radius ||
							abs($z - $source_z) !== $radius ||
							$random->nextBoolean()
						)
					){
						$this->transaction->addBlockAt($x, $y, $z, $this->leaves_type);
					}
				}
			}
		}

		return true;
	}
}