<?php
declare(strict_types=1);

namespace jasonw4331\SpectreZone\item;

use customiesdevs\customies\item\CustomiesItemFactory;
use pocketmine\item\Item;
use pocketmine\utils\CloningRegistryTrait;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see build/generate-registry-annotations.php
 * @generate-registry-docblock
 *
 * @method static Ectoplasm ECTOPLASM()
 * @method static SpectreIngot SPECTRE_INGOT()
 * @method static SpectreKey SPECTRE_KEY()
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