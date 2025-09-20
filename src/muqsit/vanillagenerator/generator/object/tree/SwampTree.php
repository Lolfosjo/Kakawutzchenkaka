<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\object\tree;

use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Leaves;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\math\Facing;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;
use function array_key_exists;

class SwampTree extends CocoaTree{

	public function __construct(Random $random, BlockTransaction $transaction){
		parent::__construct($random, $transaction);
		$this->setOverridables(
			BlockTypeIds::AIR,
			BlockTypeIds::ACACIA_LEAVES,
			BlockTypeIds::BIRCH_LEAVES,
			BlockTypeIds::DARK_OAK_LEAVES,
			BlockTypeIds::JUNGLE_LEAVES,
			BlockTypeIds::OAK_LEAVES,
			BlockTypeIds::SPRUCE_LEAVES
		);
		$this->setHeight($random->nextRange(6, 8));
		$this->setType(VanillaBlocks::OAK_LOG(), VanillaBlocks::OAK_LEAVES());
	}

	public function canPlaceOn(Block $soil) : bool{
		$id = $soil->getTypeId();
		return $id === BlockTypeIds::GRASS || $id === BlockTypeIds::DIRT;
	}

	public function canPlace(int $base_x, int $base_y, int $base_z, ChunkManager $world) : bool{
		for($y = $base_y; $y <= $base_y + 1 + $this->height; ++$y){
			if($y < 0 || $y >= World::Y_MAX){
				return false;
			}

			$radius = match(true){
				$y === $base_y => 0,
				$y >= $base_y + 1 + $this->height - 2 => 3,
				default => 1
			};
			for($x = $base_x - $radius; $x <= $base_x + $radius; ++$x){
				for($z = $base_z - $radius; $z <= $base_z + $radius; ++$z){
					$type = $world->getBlockAt($x, $y, $z)->getTypeId();
					if(array_key_exists($type, $this->overridables)){
						continue;
					}

					if($type === BlockTypeIds::WATER){
						if($y > $base_y){
							return false;
						}
					}else{
						return false;
					}
				}
			}
		}
		return true;
	}

	public function generate(ChunkManager $world, Random $random, int $source_x, int $source_y, int $source_z) : bool{
		/** @var Chunk $chunk */
		$chunk = $world->getChunk($source_x >> Chunk::COORD_BIT_SIZE, $source_z >> Chunk::COORD_BIT_SIZE);
		$chunk_block_x = $source_x & Chunk::COORD_MASK;
		$chunk_block_z = $source_z & Chunk::COORD_MASK;
		$block_state_registry = RuntimeBlockStateRegistry::getInstance();
		
		while($block_state_registry->fromStateId($chunk->getBlockStateId($chunk_block_x, $source_y, $chunk_block_z))->getTypeId() === BlockTypeIds::WATER){
			--$source_y;
		}

		++$source_y;
		if($this->cannotGenerateAt($source_x, $source_y, $source_z, $world)){
			return false;
		}

		$this->placeCanopy($source_x, $source_y, $source_z, $random, $world);

		$world_height = $world->getMaxY();
		for($y = 0; $y < $this->height; ++$y){
			if($source_y + $y < $world_height){
				$material = $block_state_registry->fromStateId($chunk->getBlockStateId($chunk_block_x, $source_y + $y, $chunk_block_z));
				if(
					$material->getTypeId() === BlockTypeIds::AIR ||
					$material->getTypeId() === BlockTypeIds::WATER ||
					$material instanceof Leaves
				){
					$this->transaction->addBlockAt($source_x, $source_y + $y, $source_z, $this->log_type);
				}
			}
		}

		$this->placeVines($source_x, $source_y, $source_z, $random);

		$this->transaction->addBlockAt($source_x, $source_y - 1, $source_z, VanillaBlocks::DIRT());
		return true;
	}

	protected function placeCanopy(int $x, int $y, int $z, Random $random, ChunkManager $world): void{
		$baseY = $y + $this->height;
		
		for($xx = -2; $xx <= 2; $xx++) {
			for($zz = -2; $zz <= 2; $zz++) {
				for($yy = -1; $yy <= 0; $yy++) {
					if(abs($xx) === 2 && abs($zz) === 2) {
						if($yy === -1 && $random->nextBoundedInt(2) === 0 && $this->canOverride($world->getBlockAt($x + $xx, $baseY + $yy, $z + $zz))){
							$this->transaction->addBlockAt($x + $xx, $baseY + $yy, $z + $zz, $this->leaves_type);
						}
					} elseif($this->canOverride($world->getBlockAt($x + $xx, $baseY + $yy, $z + $zz))) {
						$this->transaction->addBlockAt($x + $xx, $baseY + $yy, $z + $zz, $this->leaves_type);
					}
				}
			}
		}

		for($xx = -3; $xx <= 3; $xx++) {
			for($zz = -3; $zz <= 3; $zz++) {
				for($yy = -3; $yy <= -2; $yy++) {
					if(abs($xx) === 3 && abs($zz) === 3) {
						if($random->nextBoundedInt(3) === 0 && $this->canOverride($world->getBlockAt($x + $xx, $baseY + $yy, $z + $zz))){
							$this->transaction->addBlockAt($x + $xx, $baseY + $yy, $z + $zz, $this->leaves_type);
						}
					} elseif($this->canOverride($world->getBlockAt($x + $xx, $baseY + $yy, $z + $zz))) {
						$this->transaction->addBlockAt($x + $xx, $baseY + $yy, $z + $zz, $this->leaves_type);
					}
				}
			}
		}
	}

	protected function placeVines(int $x, int $y, int $z, Random $random): void {
		$baseY = $y + $this->height;
		$air = VanillaBlocks::AIR();

		for($xx = -3; $xx <= 3; $xx++) {
			for($zz = -3; $zz <= 3; $zz++) {
				for($yy = -1; $yy <= 0; $yy++) {
					if($random->nextBoundedInt(2) !== 0 || !$this->transaction->fetchBlockAt($x + $xx, $baseY + $yy, $z + $zz)->isSameState($air)) {
						continue;
					}
					$vector3 = new Vector3($x + $xx, $baseY + $yy, $z + $zz);
					$sides = [];
					foreach(Facing::HORIZONTAL as $facing) {
						$side = $vector3->getSide($facing);
						if(!$this->transaction->fetchBlock($side)->isSameState($this->leaves_type)) {
							continue;
						}
						$sides[] = $facing;
					}
					if(count($sides) > 0) {
						$this->transaction->addBlock($vector3, VanillaBlocks::VINES()->setFaces($sides));
					}
				}
			}
		}

		for($xx = -4; $xx <= 4; $xx++) {
			for($zz = -4; $zz <= 4; $zz++) {
				for($yy = -3; $yy <= 2; $yy++) {
					if($random->nextBoundedInt(3) !== 0 || !$this->transaction->fetchBlockAt($x + $xx, $baseY + $yy, $z + $zz)->isSameState($air)) {
						continue;
					}
					$vector3 = new Vector3($x + $xx, $baseY + $yy, $z + $zz);
					$sides = [];
					foreach(Facing::HORIZONTAL as $facing) {
						$side = $vector3->getSide($facing);
						if(!$this->transaction->fetchBlock($side)->isSameState($this->leaves_type)) {
							continue;
						}
						$sides[] = $facing;
					}
					if(count($sides) > 0) {
						$vines = VanillaBlocks::VINES()->setFaces($sides);
						$this->transaction->addBlock($vector3, $vines);

						for($yyy = $vector3->getFloorY() - 1; $yyy >= $y; $yyy--) {
							if($this->transaction->fetchBlockAt($vector3->getX(), $yyy, $vector3->getZ())->isSameState($air)) {
								$this->transaction->addBlockAt($vector3->getX(), $yyy, $vector3->getZ(), $vines);
							} else {
								break;
							}
						}
					}
				}
			}
		}
	}

	private function canOverride(Block $block): bool {
		return array_key_exists($block->getTypeId(), $this->overridables);
	}
}