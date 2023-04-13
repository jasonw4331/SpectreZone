<?php

declare(strict_types=1);

namespace jasonwynn10\SpectreZone\item;

use customiesdevs\customies\item\component\ThrowableComponent;
use customiesdevs\customies\item\CreativeInventoryInfo;
use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use jasonwynn10\SpectreZone\SpectreZone;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemUseResult;
use pocketmine\item\Releasable;
use pocketmine\player\Player;
use pocketmine\Server;

final class SpectreKey extends Item implements Releasable, ItemComponents{
	use ItemComponentsTrait;

	public function __construct(ItemIdentifier $identifier, string $name = 'Spectre Key'){
		parent::__construct($identifier, $name);
		$this->initComponent('spectre_key', new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_ITEMS, CreativeInventoryInfo::NONE));
		$this->addComponent(new ThrowableComponent(true));
	}

	public function getMaxStackSize() : int{
		return 1;
	}

	public function onReleaseUsing(Player $player) : ItemUseResult{
		/** @var SpectreZone $plugin */
		$plugin = Server::getInstance()->getPluginManager()->getPlugin('SpectreZone');
		if($player->getWorld() === Server::getInstance()->getWorldManager()->getWorldByName('SpectreZone')){
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
		return 20 * 2; // 2 second hold for particles
	}

	public function canStartUsingItem(Player $player) : bool{
		return true;
	}
}
