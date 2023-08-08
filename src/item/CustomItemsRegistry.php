<?php
declare(strict_types=1);

namespace jasonw4331\SpectreZone\item;

use customiesdevs\customies\item\CustomiesItemFactory;
use pocketmine\item\Item;
use pocketmine\utils\CloningRegistryTrait;

/**
 * @generate-registry-docblock
 */
final class CustomItemsRegistry{
	use CloningRegistryTrait;

	private function __construct(){
		//NOOP
	}

	protected static function register(string $name, Item $block) : void{
		self::_registryRegister($name, $block);
	}

	/**
	 * @return Item[]
	 * @phpstan-return array<string, Item>
	 */
	public static function getAll() : array{
		//phpstan doesn't support generic traits yet :(
		/** @var Item[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup() : void{
		$itemFactory = CustomiesItemFactory::getInstance();
		self::register("ectoplasm", $itemFactory->get("ectoplasm"));
		self::register("spectre_ingot", $itemFactory->get("spectre_ingot"));
		self::register("spectre_key", $itemFactory->get("spectre_key"));
	}
}