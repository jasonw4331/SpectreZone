<?php
declare(strict_types=1);
namespace jasonwynn10\SpectreZone\item;

use customies\item\ItemComponents;
use customies\item\ItemComponentsTrait;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;

class Ectoplasm extends Item implements ItemComponents{
	use ItemComponentsTrait;

	public function __construct(){
		parent::__construct(new ItemIdentifier(600, 0), 'Ectoplasm');
		$this->initComponent('ectoplasm', 64);
		$this->addProperty('creative_group', 'Items');
		$this->addProperty('creative_category', 4);
	}
}