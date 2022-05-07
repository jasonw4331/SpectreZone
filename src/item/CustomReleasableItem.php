<?php
declare(strict_types=1);
namespace jasonwynn10\SpectreZone\item;

use alvin0319\CustomItemLoader\item\CustomItem;
use pocketmine\item\ItemUseResult;
use pocketmine\item\Releasable;
use pocketmine\player\Player;

class CustomReleasableItem extends CustomItem implements Releasable {

	/** @var callable */
	protected $onRelease;

	public function __construct(string $name, array $data, callable $onRelease){
		parent::__construct($name, $data);
		$this->onRelease = $onRelease;
	}

	public function onReleaseUsing(Player $player) : ItemUseResult{
		return ($this->onRelease)($player);
	}

	public function canStartUsingItem(Player $player) : bool{
		return true;
	}
}