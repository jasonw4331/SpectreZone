<?php
declare(strict_types=1);
namespace jasonwynn10\SpectreZone\item;

use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;

final class SpectreIngot extends Item implements ItemComponents{
	use ItemComponentsTrait;

	public function __construct(ItemIdentifier $identifier, string $name = 'Spectre Ingot'){
		parent::__construct($identifier, $name);
		$this->initComponent('spectre_ingot', 64);
		$this->addProperty('creative_group', 'Items');
		$this->addProperty('creative_category', 4);
	}
}