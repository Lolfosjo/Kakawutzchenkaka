<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\events;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\plugin\Plugin;

class BiomeDisplayListener implements Listener {

    private Plugin $plugin;
    /** @var array<string, int> Speichert die letzte bekannte Biom-ID für jeden Spieler */
    private array $lastBiomes = [];
    /** @var array<string, int> Speichert die letzten Chunk-Koordinaten für jeden Spieler */
    private array $lastChunkPositions = [];

    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        // Zeige das Biom beim Betreten des Servers
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player): void {
            if ($player->isOnline()) {
                $this->showBiomeInfo($player);
            }
        }), 20); // 1 Sekunde Verzögerung
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        $to = $event->getTo();
        
        // Berechne Chunk-Koordinaten
        $chunkX = $to->getFloorX() >> 4;
        $chunkZ = $to->getFloorZ() >> 4;
        $chunkKey = $chunkX . ":" . $chunkZ;
        
        // Überprüfe nur, wenn sich der Chunk geändert hat
        $lastChunkKey = $this->lastChunkPositions[$playerName] ?? "";
        if ($chunkKey !== $lastChunkKey) {
            $this->lastChunkPositions[$playerName] = $chunkKey;
            
            // Überprüfe Biom-Änderung
            $currentBiome = $to->getWorld()->getBiome($to->getFloorX(), $to->getFloorY(), $to->getFloorZ());
            $currentBiomeId = $currentBiome->getId();
            
            $lastBiomeId = $this->lastBiomes[$playerName] ?? -1;
            if ($currentBiomeId !== $lastBiomeId) {
                $this->lastBiomes[$playerName] = $currentBiomeId;
                $this->showBiomeInfo($player, $currentBiome->getName(), $currentBiomeId);
            }
        }
    }

    private function showBiomeInfo(Player $player, ?string $biomeName = null, ?int $biomeId = null): void {
        if ($biomeName === null || $biomeId === null) {
            $position = $player->getPosition();
            $biome = $position->getWorld()->getBiome($position->getFloorX(), $position->getFloorY(), $position->getFloorZ());
            $biomeName = $biome->getName();
            $biomeId = $biome->getId();
        }

        // Zeige das Biom als Popup/Tip
        $player->sendTip("§aBiom: §f{$biomeName} §7(ID: {$biomeId})");
        
        // Alternativ als Chat-Nachricht (auskommentiert)
        // $player->sendMessage("§a[Biom] §fDu befindest dich in: §e{$biomeName} §7(ID: {$biomeId})");
        
        // Alternativ als ActionBar (auskommentiert)
        // $player->sendActionBarMessage("§aBiom: §f{$biomeName} §7(ID: {$biomeId})");
    }

    public function onPlayerQuit(\pocketmine\event\player\PlayerQuitEvent $event): void {
        $playerName = $event->getPlayer()->getName();
        // Cleanup beim Verlassen des Servers
        unset($this->lastBiomes[$playerName]);
        unset($this->lastChunkPositions[$playerName]);
    }
}