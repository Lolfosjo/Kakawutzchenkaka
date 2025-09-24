<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\overworld;

use muqsit\vanillagenerator\generator\Environment;
use muqsit\vanillagenerator\generator\ground\DirtAndStonePatchGroundGenerator;
use muqsit\vanillagenerator\generator\ground\DirtPatchGroundGenerator;
use muqsit\vanillagenerator\generator\ground\GravelPatchGroundGenerator;
use muqsit\vanillagenerator\generator\ground\GroundGenerator;
use muqsit\vanillagenerator\generator\ground\MesaGroundGenerator;
use muqsit\vanillagenerator\generator\ground\MycelGroundGenerator;
use muqsit\vanillagenerator\generator\ground\RockyGroundGenerator;
use muqsit\vanillagenerator\generator\ground\SandyGroundGenerator;
use muqsit\vanillagenerator\generator\ground\SnowyGroundGenerator;
use muqsit\vanillagenerator\generator\ground\StonePatchGroundGenerator;
use muqsit\vanillagenerator\generator\noise\glowstone\PerlinOctaveGenerator;
use muqsit\vanillagenerator\generator\noise\glowstone\SimplexOctaveGenerator;
use muqsit\vanillagenerator\generator\overworld\biome\BiomeHeightManager;
use muqsit\vanillagenerator\generator\overworld\biome\BiomeIds;
use muqsit\vanillagenerator\generator\overworld\populator\OverworldPopulator;
use muqsit\vanillagenerator\generator\overworld\populator\SnowPopulator;
use muqsit\vanillagenerator\generator\overworld\terrain\TerrainBlender;
use muqsit\vanillagenerator\generator\utils\preset\SimpleGeneratorPreset;
use muqsit\vanillagenerator\generator\utils\WorldOctaves;
use muqsit\vanillagenerator\generator\VanillaBiomeGrid;
use muqsit\vanillagenerator\generator\VanillaGenerator;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function array_key_exists;

/**
 * @extends VanillaGenerator<WorldOctaves<PerlinOctaveGenerator, PerlinOctaveGenerator, PerlinOctaveGenerator, SimplexOctaveGenerator>>
 */
class OverworldGenerator extends VanillaGenerator{

	/** @var float[] */
	protected static array $ELEVATION_WEIGHT = [];

	/** @var GroundGenerator[] */
	protected static array $GROUND_MAP = [];

	/**
	 * @param int $x 0-4
	 * @param int $z 0-4
	 * @return int
	 */
	private static function elevationWeightHash(int $x, int $z) : int{
		return ($x << 3) | $z;
	}

	/**
	 * @param int $i 0-4
	 * @param int $j 0-4
	 * @param int $k 0-32
	 * @return int
	 */
	private static function densityHash(int $i, int $j, int $k) : int{
		return ($k << 6) | ($j << 3) | $i;
	}

	public static function init() : void{
		// ENTFERNT: Alte deprecated BiomeTerrainManager::init() und registerTerrain() Aufrufe
		// Das TerrainBlender-System benötigt keine explizite Registrierung
		
		// GROUND GENERATORS (behalten wir für Oberflächendetails)
		self::setBiomeSpecificGround(new SandyGroundGenerator(), BiomeIds::BEACH, BiomeIds::COLD_BEACH, BiomeIds::DESERT, BiomeIds::DESERT_HILLS, BiomeIds::DESERT_MUTATED);
		self::setBiomeSpecificGround(new RockyGroundGenerator(), BiomeIds::STONE_BEACH);
		self::setBiomeSpecificGround(new SnowyGroundGenerator(), BiomeIds::ICE_PLAINS_SPIKES);
		self::setBiomeSpecificGround(new MycelGroundGenerator(), BiomeIds::MUSHROOM_ISLAND, BiomeIds::MUSHROOM_ISLAND_SHORE);
		self::setBiomeSpecificGround(new StonePatchGroundGenerator(), BiomeIds::EXTREME_HILLS);
		self::setBiomeSpecificGround(new GravelPatchGroundGenerator(), BiomeIds::EXTREME_HILLS_MUTATED, BiomeIds::EXTREME_HILLS_PLUS_TREES_MUTATED);
		self::setBiomeSpecificGround(new DirtAndStonePatchGroundGenerator(), BiomeIds::SAVANNA_MUTATED, BiomeIds::SAVANNA_PLATEAU_MUTATED);
		self::setBiomeSpecificGround(new DirtPatchGroundGenerator(), BiomeIds::MEGA_TAIGA, BiomeIds::MEGA_TAIGA_HILLS, BiomeIds::REDWOOD_TAIGA_MUTATED, BiomeIds::REDWOOD_TAIGA_HILLS_MUTATED);
		self::setBiomeSpecificGround(new MesaGroundGenerator(), BiomeIds::MESA, BiomeIds::MESA_PLATEAU, BiomeIds::MESA_PLATEAU_STONE);
		self::setBiomeSpecificGround(new MesaGroundGenerator(MesaGroundGenerator::BRYCE), BiomeIds::MESA_BRYCE);
		self::setBiomeSpecificGround(new MesaGroundGenerator(MesaGroundGenerator::FOREST), BiomeIds::MESA_PLATEAU_STONE, BiomeIds::MESA_PLATEAU_STONE_MUTATED);

		// fill a 5x5 array with values that acts as elevation weight on chunk neighboring
		for($x = 0; $x < 5; ++$x){
			for($z = 0; $z < 5; ++$z){
				$sq_x = $x - 2;
				$sq_x *= $sq_x;
				$sq_z = $z - 2;
				$sq_z *= $sq_z;
				self::$ELEVATION_WEIGHT[self::elevationWeightHash($x, $z)] = 10.0 / sqrt($sq_x + $sq_z + 0.2);
			}
		}
	}

	protected static function setBiomeSpecificGround(GroundGenerator $gen, int ...$biomes) : void{
		foreach($biomes as $biome){
			self::$GROUND_MAP[$biome] = $gen;
		}
	}

	protected const COORDINATE_SCALE = 684.412;
	protected const HEIGHT_SCALE = 684.412;
	protected const HEIGHT_NOISE_SCALE_X = 200.0;
	protected const HEIGHT_NOISE_SCALE_Z = 200.0;
	protected const DETAIL_NOISE_SCALE_X = 80.0;
	protected const DETAIL_NOISE_SCALE_Y = 160.0;
	protected const DETAIL_NOISE_SCALE_Z = 80.0;
	protected const SURFACE_SCALE = 0.0625;
	protected const BASE_SIZE = 8.5;
	protected const STRETCH_Y = 12.0;
	protected const BIOME_HEIGHT_OFFSET = 0.0;
	protected const BIOME_HEIGHT_WEIGHT = 1.0;
	protected const BIOME_SCALE_OFFSET = 0.0;
	protected const BIOME_SCALE_WEIGHT = 1.0;
	protected const DENSITY_FILL_MODE = 0;
	protected const DENSITY_FILL_SEA_MODE = 0;
	protected const DENSITY_FILL_OFFSET = 0.0;

	private GroundGenerator $ground_gen;
	private string $type = WorldType::NORMAL;

	public function __construct(int $seed, string $preset_string){
		$preset = SimpleGeneratorPreset::parse($preset_string);
		parent::__construct(
			$seed,
			$preset->exists("environment") ? Environment::fromString($preset->getString("environment")) : Environment::OVERWORLD,
			$preset->exists("worldtype") ? WorldType::fromString($preset->getString("worldtype")) : null,
			$preset
		);
		$this->ground_gen = new GroundGenerator();
		$this->addPopulators(new OverworldPopulator(), new SnowPopulator());
	}

	public function getGroundGenerator() : GroundGenerator{
		return $this->ground_gen;
	}

	protected function generateChunkData(ChunkManager $world, int $chunk_x, int $chunk_z, VanillaBiomeGrid $grid) : void{
		// Generiere zuerst das grundlegende Terrain mit Density
		$density = $this->generateTerrainDensity($chunk_x, $chunk_z);

		$cx = $chunk_x << Chunk::COORD_BIT_SIZE;
		$cz = $chunk_z << Chunk::COORD_BIT_SIZE;

		/** @var SimplexOctaveGenerator $octave_generator */
		$octave_generator = $this->getWorldOctaves()->surface;
		$size_x = $octave_generator->size_x;
		$size_z = $octave_generator->size_z;

		$surface_noise = $octave_generator->getFractalBrownianMotion($cx, 0.0, $cz, 0.5, 0.5);

		/** @var Chunk $chunk */
		$chunk = $world->getChunk($chunk_x, $chunk_z);

		$min_y = $world->getMinY();
		$max_y = $world->getMaxY();
		
		// Setze Biom-IDs für alle Y-Ebenen
		for($x = 0; $x < $size_x; ++$x){
			for($z = 0; $z < $size_z; ++$z){
				$id = $grid->getBiome($x, $z);
				for($y = $min_y; $y < $max_y; ++$y){
					$chunk->setBiomeId($x, $y, $z, $id);
				}
				
				// AKTUALISIERT: Direkter TerrainBlender-Aufruf (ersetzt deprecated BiomeTerrainManager)
				if($id !== null){
					TerrainBlender::generateBlendedTerrainColumn(
						$world, 
						$this->random, 
						$cx + $x, 
						$cz + $z, 
						$grid,
						$surface_noise[$x | $z << Chunk::COORD_BIT_SIZE],
						$density,
						$chunk_x,
						$chunk_z,
						64 // sea_level - Standard Minecraft Wert
					);
				}
			}
		}
		
		// Führe nach dem Terrain-Generation die GroundGenerator aus
		// Diese überschreiben nur die obersten 1-3 Blöcke für Biom-spezifische Details
		$this->applyGroundGenerators($world, $chunk_x, $chunk_z, $grid, $surface_noise);
	}

	/**
	 * Wendet die GroundGeneratoren an (nur für oberflächliche Details)
	 */
	private function applyGroundGenerators(ChunkManager $world, int $chunk_x, int $chunk_z, VanillaBiomeGrid $grid, array $surface_noise): void {
		$cx = $chunk_x << Chunk::COORD_BIT_SIZE;
		$cz = $chunk_z << Chunk::COORD_BIT_SIZE;
		
		/** @var SimplexOctaveGenerator $octave_generator */
		$octave_generator = $this->getWorldOctaves()->surface;
		$size_x = $octave_generator->size_x;
		$size_z = $octave_generator->size_z;
		
		// Wende GroundGenerators nur auf die obersten Schichten an
		for($x = 0; $x < $size_x; ++$x){
			for($z = 0; $z < $size_z; ++$z){
				$id = $grid->getBiome($x, $z);
				if($id !== null){
					// Prüfe ob für dieses Biom ein spezieller GroundGenerator registriert ist
					if(array_key_exists($id, self::$GROUND_MAP)){
						// Verwende den spezifischen GroundGenerator nur für Oberflächendetails
						self::$GROUND_MAP[$id]->generateTerrainColumn(
							$world, 
							$this->random, 
							$cx + $x, 
							$cz + $z, 
							$id, 
							$surface_noise[$x | $z << Chunk::COORD_BIT_SIZE]
						);
					} else {
						// Verwende den Standard-GroundGenerator nur für Oberflächendetails
						$this->ground_gen->generateTerrainColumn(
							$world, 
							$this->random, 
							$cx + $x, 
							$cz + $z, 
							$id, 
							$surface_noise[$x | $z << Chunk::COORD_BIT_SIZE]
						);
					}
				}
			}
		}
	}

	protected function createWorldOctaves() : WorldOctaves{
		$seed = new Random($this->random->getSeed());

		$height = PerlinOctaveGenerator::fromRandomAndOctaves($seed, 16, 5, 1, 5);
		$height->x_scale = self::HEIGHT_NOISE_SCALE_X;
		$height->z_scale = self::HEIGHT_NOISE_SCALE_Z;

		$roughness = PerlinOctaveGenerator::fromRandomAndOctaves($seed, 16, 5, 33, 5);
		$roughness->x_scale = self::COORDINATE_SCALE;
		$roughness->y_scale = self::HEIGHT_SCALE;
		$roughness->z_scale = self::COORDINATE_SCALE;

		$roughness2 = PerlinOctaveGenerator::fromRandomAndOctaves($seed, 16, 5, 33, 5);
		$roughness2->x_scale = self::COORDINATE_SCALE;
		$roughness2->y_scale = self::HEIGHT_SCALE;
		$roughness2->z_scale = self::COORDINATE_SCALE;

		$detail = PerlinOctaveGenerator::fromRandomAndOctaves($seed, 8, 5, 33, 5);
		$detail->x_scale = self::COORDINATE_SCALE / self::DETAIL_NOISE_SCALE_X;
		$detail->y_scale = self::HEIGHT_SCALE / self::DETAIL_NOISE_SCALE_Y;
		$detail->z_scale = self::COORDINATE_SCALE / self::DETAIL_NOISE_SCALE_Z;

		$surface = SimplexOctaveGenerator::fromRandomAndOctaves($seed, 4, 16, 1, 16);
		$surface->setScale(self::SURFACE_SCALE);

		return new WorldOctaves($height, $roughness, $roughness2, $detail, $surface);
	}

	/**
	 * @param int $x
	 * @param int $z
	 * @return float[]
	 */
	protected function generateTerrainDensity(int $x, int $z) : array{
		$density = [];

		// Scaling chunk x and z coordinates (4x, see below)
		$x <<= 2;
		$z <<= 2;

		$biomeGrid = $this->getBiomeGridAtLowerRes($x - 2, $z - 2, 10, 10);

		$octaves = $this->getWorldOctaves();
		$height_noise = $octaves->height->getFractalBrownianMotion($x, 0, $z, 0.5, 2.0);
		$roughness_noise = $octaves->roughness->getFractalBrownianMotion($x, 0, $z, 0.5, 2.0);
		$roughness_noise_2 = $octaves->roughness_2->getFractalBrownianMotion($x, 0, $z, 0.5, 2.0);
		$detail_noise = $octaves->detail->getFractalBrownianMotion($x, 0, $z, 0.5, 2.0);

		$index = 0;
		$index_height = 0;

		for($i = 0; $i < 5; ++$i){
			for($j = 0; $j < 5; ++$j){
				$avg_height_scale = 0.0;
				$avg_height_base = 0.0;
				$total_weight = 0.0;
				$biome = $biomeGrid[$i + 2 + ($j + 2) * 10];
				$biome_height = BiomeHeightManager::get($biome);
				for($m = 0; $m < 5; ++$m){
					for($n = 0; $n < 5; ++$n){
						$near_biome = $biomeGrid[$i + $m + ($j + $n) * 10];
						$near_biome_height = BiomeHeightManager::get($near_biome);
						$height_base = self::BIOME_HEIGHT_OFFSET + $near_biome_height->height * self::BIOME_HEIGHT_WEIGHT;
						$height_scale = self::BIOME_SCALE_OFFSET + $near_biome_height->scale * self::BIOME_SCALE_WEIGHT;
						if($this->type === WorldType::AMPLIFIED && $height_base > 0){
							$height_base = 1.0 + $height_base * 2.0;
							$height_scale = 1.0 + $height_scale * 4.0;
						}

						$weight = self::$ELEVATION_WEIGHT[self::elevationWeightHash($m, $n)] / ($height_base + 2.0);
						if($near_biome_height->height > $biome_height->height){
							$weight *= 0.5;
						}

						$avg_height_scale += $height_scale * $weight;
						$avg_height_base += $height_base * $weight;
						$total_weight += $weight;
					}
				}
				$avg_height_scale /= $total_weight;
				$avg_height_base /= $total_weight;
				$avg_height_scale = $avg_height_scale * 0.9 + 0.1;
				$avg_height_base = ($avg_height_base * 4.0 - 1.0) / 8.0;

				$noise_h = $height_noise[$index_height++] / 8000.0;
				if($noise_h < 0){
					$noise_h = -$noise_h * 0.3;
				}

				$noise_h = $noise_h * 3.0 - 2.0;
				if($noise_h < 0){
					$noise_h = max($noise_h * 0.5, -1) / 1.4 * 0.5;
				}else{
					$noise_h = min($noise_h, 1) / 8.0;
				}

				$noise_h = ($noise_h * 0.2 + $avg_height_base) * self::BASE_SIZE / 8.0 * 4.0 + self::BASE_SIZE;
				for($k = 0; $k < 33; ++$k){
					$nh = ($k - $noise_h) * self::STRETCH_Y * 128.0 / 256.0 / $avg_height_scale;
					if($nh < 0.0){
						$nh *= 4.0;
					}

					$noise_r = $roughness_noise[$index] / 512.0;
					$noise_r_2 = $roughness_noise_2[$index] / 512.0;
					$noise_d = ($detail_noise[$index] / 10.0 + 1.0) / 2.0;

					$dens = $noise_d < 0 ? $noise_r : ($noise_d > 1 ? $noise_r_2 : $noise_r + ($noise_r_2 - $noise_r) * $noise_d);
					$dens -= $nh;
					++$index;
					if($k > 29){
						$lowering = ($k - 29) / 3.0;
						$dens = $dens * (1.0 - $lowering) + -10.0 * $lowering;
					}
					$density[self::densityHash($i, $j, $k)] = $dens;
				}
			}
		}
		return $density;
	}
}

OverworldGenerator::init();