<?php
declare(strict_types=1);

namespace jasonw4331\SpectreZone\block;

use customiesdevs\customies\block\CustomiesBlockFactory;
use pocketmine\block\Block;
use pocketmine\utils\CloningRegistryTrait;

/**
 * This doc-block is generated automatically, do not modify it manually.
 * This must be regenerated whenever registry members are added, removed or changed.
 * @see build/generate-registry-annotations.php
 * @generate-registry-docblock
 *
 * @method static SpectreBlock SPECTRE_BLOCK()
 * @method static SpectreCoreBlock SPECTRE_CORE()
 */
final class CustomBlocksRegistry{
	use CloningRegistryTrait;

	private function __construct(){
		//NOOP
	}

	protected static function register(string $name, Block $block) : void{
		self::_registryRegister($name, $block);
	}

	/**
	 * @return Block[]
	 * @phpstan-return array<string, Block>
	 */
	public static function getAll() : array{
		//phpstan doesn't support generic traits yet :(
		/** @var Block[] $result */
		$result = self::_registryGetAll();
		return $result;
	}

	protected static function setup() : void{
		$blockFactory = CustomiesBlockFactory::getInstance();
		self::register("spectre_block", $blockFactory->get("spectre_block"));
		self::register("spectre_core", $blockFactory->get("spectre_core"));
	}
}