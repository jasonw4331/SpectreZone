<?php
declare(strict_types=1);
namespace jasonwynn10\SpectreZone\item;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use twistedasylummc\customies\item\ItemComponents;
use twistedasylummc\customies\item\ItemComponentsTrait;

final class Ectoplasm extends Item implements ItemComponents{
	use ItemComponentsTrait;

	public function __construct(ItemIdentifier $identifier, string $name = 'Ectoplasm'){
		parent::__construct($identifier, $name);
		$this->initComponent('ectoplasm', 64);
		$this->addProperty('creative_group', 'Items');
		$this->addProperty('creative_category', 4);
	}
}