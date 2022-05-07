<?php
declare(strict_types=1);
namespace jasonwynn10\SpectreZone\item;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemUseResult;
use pocketmine\item\Releasable;
use pocketmine\player\Player;

class CustomReleasableItem extends Item implements Releasable {

	/** @var callable|null $onRelease */
	protected $onRelease;

	public function __construct(ItemIdentifier $identifier, string $name = "Unknown", ?callable $onRelease = null){
		parent::__construct($identifier, $name);
		$this->onRelease = $onRelease;
	}

	public function getMaxStackSize() : int{
		return 1;
	}

	public function onReleaseUsing(Player $player) : ItemUseResult{
		return ($this->onRelease)($player);
	}

	public function canStartUsingItem(Player $player) : bool{
		return true;
	}
}