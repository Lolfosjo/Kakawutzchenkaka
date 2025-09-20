<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator;

use muqsit\vanillagenerator\generator\nether\NetherGenerator;
use muqsit\vanillagenerator\generator\overworld\OverworldGenerator;
use muqsit\vanillagenerator\generator\tasks\TumbleweedsDecoratorTask;
use muqsit\vanillagenerator\generator\events\BiomeDisplayListener;
use pocketmine\plugin\PluginBase;
use pocketmine\world\generator\GeneratorManager;

final class Loader extends PluginBase{

	private TumbleweedsDecoratorTask $tumbleweedSpawner;

	public function onLoad() : void{
		$generator_manager = GeneratorManager::getInstance();
		$generator_manager->addGenerator(OverworldGenerator::class, "vanilla_overworld", fn() => null);
	}

	public function onEnable() : void{
		$this->tumbleweedSpawner = new TumbleweedsDecoratorTask(
			spawnChance: 10,   
			maxTumbleweeds: 30,  
			spawnRadius: 70,       
			minSpawnDistance: 20,
			maxSpawnHeight: 200.0 
		);
		
		$this->getScheduler()->scheduleRepeatingTask($this->tumbleweedSpawner, 5);

		$this->getServer()->getPluginManager()->registerEvents(new BiomeDisplayListener($this), $this);
	}
	public function getTumbleweedSpawner() : TumbleweedsDecoratorTask{
		return $this->tumbleweedSpawner;
	}
}