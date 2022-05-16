<?php
declare(strict_types=1);
namespace jasonwynn10\SpectreZone\item;

use jasonwynn10\SpectreZone\SpectreZone;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemUseResult;
use pocketmine\item\Releasable;
use pocketmine\player\Player;
use pocketmine\Server;

class SpectreKey extends Item implements Releasable{

	public function __construct(){
		parent::__construct(new ItemIdentifier(602, 0), 'Spectre Key');
	}

	public function getMaxStackSize() : int{
		return 1;
	}

	public function onReleaseUsing(Player $player) : ItemUseResult{
		/** @var SpectreZone $plugin */
		$plugin = Server::getInstance()->getPluginManager()->getPlugin('SpectreZone');
		if($player->getWorld() === Server::getInstance()->getWorldManager()->getWorldByName('SpectreZone')) {
			[$position, $viewDistance] = $plugin->getSavedInfo($player);
			$player->setViewDistance($viewDistance);
		}else{
			$plugin->savePlayerInfo($player);
			$position = $plugin->getSpectreSpawn($player);
			$player->setViewDistance(3);
		}
		$player->teleport($position);
		return ItemUseResult::NONE();
	}

	/**
	 * Returns the number of ticks a player must wait before activating this item again.
	 */
	public function getCooldownTicks() : int{
		return 20 * 5; // 5 second hold for particles
	}

	public function canStartUsingItem(Player $player) : bool{
		return true;
	}
}