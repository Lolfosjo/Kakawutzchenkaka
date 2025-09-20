<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\ground;

use pmmp\RegisterBlockDemoPM5\ExtraVanillaBlocks;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

class MossPatchGroundGenerator extends GroundGenerator{

    public function generateTerrainColumn(ChunkManager $world, Random $random, int $x, int $z, int $biome, float $surface_noise) : void{
        $chance = $random->nextFloat();

        $this->setTopMaterial(
            ($surface_noise > 0.6 && $chance > 0.7) 
                ? ExtraVanillaBlocks::MOSS_BLOCK() 
                : VanillaBlocks::GRASS()
        );

        $this->setGroundMaterial(VanillaBlocks::GRASS());
        parent::generateTerrainColumn($world, $random, $x, $z, $biome, $surface_noise);
    }
}