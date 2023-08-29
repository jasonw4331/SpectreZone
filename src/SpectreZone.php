<?php

declare(strict_types=1);

namespace jasonw4331\SpectreZone;

use Closure;
use customiesdevs\customies\block\CustomiesBlockFactory;
use customiesdevs\customies\item\CreativeInventoryInfo;
use customiesdevs\customies\item\CustomiesItemFactory;
use jasonw4331\SpectreZone\block\SpectreBlock;
use jasonw4331\SpectreZone\block\SpectreCoreBlock;
use jasonw4331\SpectreZone\item\CustomItemsRegistry;
use jasonw4331\SpectreZone\item\Ectoplasm;
use jasonw4331\SpectreZone\item\SpectreIngot;
use jasonw4331\SpectreZone\item\SpectreKey;
use libCustomPack\libCustomPack;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeInfo;
use pocketmine\color\Color;
use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\crafting\ShapedRecipe;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\data\BaseNbtWorldData;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;
use Symfony\Component\Filesystem\Path;
use function abs;
use function assert;
use function is_int;
use function json_decode;
use function json_encode;
use function lcg_value;
use function mb_strtolower;
use function str_replace;
use function ucwords;
use function unlink;
use const JSON_THROW_ON_ERROR;

final class SpectreZone extends PluginBase{
	private static ResourcePack $pack;
	/** @var array<string, array{Position, int}> $savedPositions */
	private array $savedPositions = [];
	private int $defaultHeight = 4;
	private int $chunkOffset = 0;

	protected function onLoad() : void{
		// Build and register resource pack
		libCustomPack::registerResourcePack(self::$pack = libCustomPack::generatePackFromResources($this));
		$this->getLogger()->debug('Resource pack installed');
	}

	protected function onEnable() : void{
		$server = $this->getServer();

		// register custom items
		$itemFactory = CustomiesItemFactory::getInstance();
		$namespace = mb_strtolower($this->getName()) . ':';

		foreach([
			'ectoplasm' => Ectoplasm::class,
			'spectre_ingot' => SpectreIngot::class,
			'spectre_key' => SpectreKey::class
		] as $itemName => $class){
			$itemFactory->registerItem($class, $namespace . $itemName, ucwords(str_replace('_', ' ', $itemName)));
		}

		$this->getLogger()->debug('Registered custom items');

		// register custom recipes
		$craftManager = $server->getCraftingManager();

		$craftManager->registerShapedRecipe(new ShapedRecipe(
			[
				' A ',
				' B ',
				' C '
			],
			[
				'A' => new ExactRecipeIngredient(VanillaItems::LAPIS_LAZULI()),
				'B' => new ExactRecipeIngredient(VanillaItems::GOLD_INGOT()),
				'C' => new ExactRecipeIngredient(CustomItemsRegistry::ECTOPLASM())
			],
			[
				CustomItemsRegistry::ECTOPLASM()
			]
		));
		$craftManager->registerShapedRecipe(new ShapedRecipe(
			[
				'CAC',
				'CBC',
				'CCC'
			],
			[
				'A' => new ExactRecipeIngredient(VanillaItems::LAPIS_LAZULI()),
				'B' => new ExactRecipeIngredient(VanillaItems::GOLD_INGOT()),
				'C' => new ExactRecipeIngredient(CustomItemsRegistry::ECTOPLASM())
			],
			[
				CustomItemsRegistry::SPECTRE_INGOT()->setCount(9)
			]
		));
		$craftManager->registerShapedRecipe(new ShapedRecipe(
			[
				'A  ',
				'AB ',
				'  A'
			],
			[
				'A' => new ExactRecipeIngredient(CustomItemsRegistry::SPECTRE_INGOT()),
				'B' => new ExactRecipeIngredient(VanillaItems::ENDER_PEARL())
			],
			[
				CustomItemsRegistry::SPECTRE_KEY()
			]
		));

		$this->getLogger()->debug('Registered custom recipes');

		// register custom blocks
		$blockFactory = CustomiesBlockFactory::getInstance();

		/**
		 * @phpstan-var array{Block, BlockBreakInfo, ?CreativeInventoryInfo, ?Closure, ?Closure} $blockInfo
		 */
		foreach([
			'spectre_block' => [SpectreBlock::class, BlockBreakInfo::indestructible(), new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_CONSTRUCTION, CreativeInventoryInfo::NONE)],
			'spectre_core' => [SpectreCoreBlock::class, BlockBreakInfo::indestructible(), new CreativeInventoryInfo(CreativeInventoryInfo::CATEGORY_CONSTRUCTION, CreativeInventoryInfo::NONE)]
		] as $blockName => $blockInfo){
			$blockFactory->registerBlock(
				static fn() => new $blockInfo[0](new BlockIdentifier(BlockTypeIds::newId()), ucwords(str_replace('_', ' ', $blockName)), new BlockTypeInfo($blockInfo[1])),
				$namespace . $blockName,
				null,
				$blockInfo[2] ?? null,
				$blockInfo[3] ?? null,
				$blockInfo[4] ?? null
			);
		}

		$this->getLogger()->debug('Registered custom blocks');

		// Register world generator
		GeneratorManager::getInstance()->addGenerator(
			SpectreZoneGenerator::class,
			'SpectreZone',
			function(string $generatorOptions){
				/**
				 * @phpstan-var array{"Chunk Offset": int, "Default Height": int} $parsedOptions
				 */
				$parsedOptions = json_decode($generatorOptions, true, flags: JSON_THROW_ON_ERROR);
				if(!is_int($parsedOptions['Chunk Offset']) || $parsedOptions['Chunk Offset'] < 0){
					return new InvalidGeneratorOptionsException();
				}elseif(!is_int($parsedOptions['Default Height']) || $parsedOptions['Default Height'] < 2 || $parsedOptions['Default Height'] > World::Y_MAX){
					return new InvalidGeneratorOptionsException();
				}
				return null;
			},
			false // There should never be another generator with the same name
		);
		$this->getLogger()->debug('World generator registered');

		// Load or generate the SpectreZone dimension 1 tick after blocks are registered on the generation thread
		$worldManager = $server->getWorldManager();
		if(!$worldManager->loadWorld('SpectreZone')){
			$this->getLogger()->debug('SpectreZone dimension was not loaded. Generating now...');
			$worldManager->generateWorld(
				'SpectreZone',
				WorldCreationOptions::create()
					->setGeneratorClass(SpectreZoneGenerator::class)
					->setDifficulty(World::DIFFICULTY_PEACEFUL)
					->setSpawnPosition(new Vector3(0.5, 1, 0.5))
					->setGeneratorOptions(json_encode($this->getConfig()->getAll(), flags: JSON_THROW_ON_ERROR)),
				false, // Don't generate until blocks are registered on generation thread
				false // keep this for NativeDimensions compatibility
			);
		}
		$this->getLogger()->debug('SpectreZone dimension loaded');

		$spectreZone = $worldManager->getWorldByName('SpectreZone');

		/**
		 * @phpstan-var array{"Chunk Offset": ?int, "Default Height": ?int} $options
		 */
		$options = json_decode($spectreZone?->getProvider()->getWorldData()->getGeneratorOptions() ?? '', true, flags: JSON_THROW_ON_ERROR);

		$this->defaultHeight = abs($options["Default Height"] ?? 4);
		$this->chunkOffset = abs($options["Chunk Offset"] ?? 0);

		// register events
		$pluginManager = $server->getPluginManager();

		$pluginManager->registerEvent(
			PlayerQuitEvent::class,
			function(PlayerQuitEvent $event){
				$player = $event->getPlayer();
				if(isset($this->savedPositions[$player->getUniqueId()->toString()])){ // if set, the player is in the SpectreZone world
					[$position, $viewDistance] = $this->savedPositions[$player->getUniqueId()->toString()];
					unset($this->savedPositions[$player->getUniqueId()->toString()]);
					$player->setViewDistance($viewDistance);
					$player->teleport($position); // teleport the player back to their last position
				}
			},
			EventPriority::MONITOR,
			$this,
			true // doesn't really matter because event cannot be cancelled
		);
		$pluginManager->registerEvent(
			PlayerItemUseEvent::class,
			function(PlayerItemUseEvent $event) : void{
				$player = $event->getPlayer();
				if($event->getItem() instanceof SpectreKey && !$player->isUsingItem()){
					$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
						function() use ($player) : void{
							if($player->getInventory()->getItemInHand() instanceof SpectreKey && $player->isUsingItem()){
								$this->spawnParticles($player->getPosition());
							}else{
								throw new CancelTaskException();
							}
						}
					), 1);
				}
			},
			EventPriority::MONITOR,
			$this,
			false // Don't waste time on cancelled events
		);

		$this->getLogger()->debug('Event listeners registered');
	}

	public function onDisable() : void{
		libCustomPack::unregisterResourcePack(self::$pack);
		$this->getLogger()->debug('Resource pack uninstalled');

		unlink(Path::join($this->getDataFolder(), self::$pack->getPackName() . '.mcpack'));
		$this->getLogger()->debug('Resource pack file deleted');
	}

	public function getDefaultHeight() : int{
		return $this->defaultHeight;
	}

	public function getChunkOffset() : int{
		return $this->chunkOffset;
	}

	public function getSpectreSpawn(Player $player) : Position{
		$spectreZone = $this->getServer()->getWorldManager()->getWorldByName('SpectreZone');
		assert($spectreZone !== null);
		/** @var BaseNbtWorldData $worldData */
		$worldData = $spectreZone->getProvider()->getWorldData();
		$root = $worldData->getCompoundTag();
		$tag = $root->getListTag('spawnList') ?? new ListTag([], NBT::TAG_Compound);

		$highestX = 0;
		$highestZ = 0;
		/** @var CompoundTag $value */
		foreach($tag->getValue() as $value){
			$chunkX = $value->getInt('chunkX');
			$chunkZ = $value->getInt('chunkZ');
			if($value->getString('UUID') === $player->getUniqueId()->toString()){
				return new Position(($chunkX << Chunk::COORD_BIT_SIZE) + (Chunk::COORD_BIT_SIZE / 2), 2, ($chunkZ << Chunk::COORD_BIT_SIZE) + (Chunk::COORD_BIT_SIZE / 2), $spectreZone);
			}
			if(abs($chunkX) > abs($highestX)){
				$highestX = $chunkX;
			}
			if(abs($chunkZ) > abs($highestZ)){
				$highestZ = $chunkZ;
			}
		}

		$alternator = true;
		while(!$this->isUsableChunk($highestX, $highestZ)){
			if($alternator){
				if($highestX > 0){
					$highestX--;
				}else{
					$highestX++;
				}
			}else{
				if($highestZ > 0){
					$highestZ--;
				}else{
					$highestZ++;
				}
			}
			$alternator = !$alternator;
		}

		$tag->push(CompoundTag::create()
			->setString('UUID', $player->getUniqueId()->toString())
			->setInt('chunkX', $highestX)
			->setInt('chunkZ', $highestZ)
		);
		$root->setTag('spawnList', $tag);

		return new Position(($highestX << Chunk::COORD_BIT_SIZE) + (Chunk::COORD_BIT_SIZE / 2), 2, ($highestZ << Chunk::COORD_BIT_SIZE) + (Chunk::COORD_BIT_SIZE / 2), $spectreZone);
	}

	private function isUsableChunk(int $chunkX, int $chunkZ) : bool{
		return $chunkX % (3 + $this->chunkOffset) === 0 && $chunkZ % (3 + $this->chunkOffset) === 0;
	}

	public function savePlayerInfo(Player $player) : void{
		$this->savedPositions[$player->getUniqueId()->toString()] = [$player->getPosition(), $player->getViewDistance()];
	}

	/**
	 * @phpstan-return array{Position, int}
	 */
	public function getSavedInfo(Player $player) : array{
		if(isset($this->savedPositions[$player->getUniqueId()->toString()])){
			[$position, $viewDistance] = $this->savedPositions[$player->getUniqueId()->toString()];
			unset($this->savedPositions[$player->getUniqueId()->toString()]);
			return [$position, $viewDistance];
		}
		return [$player->getSpawn(), $this->getServer()->getViewDistance()];
	}

	private function spawnParticles(Position $position) : void{

		$xOffset = lcg_value() * 1.8 - 0.9;
		$zOffset = lcg_value() * 1.8 - 0.9;

		$position->getWorld()->addParticle($position->add($xOffset, 1.8, $zOffset), new DustParticle(new Color(122, 197, 205)));
	}
}
