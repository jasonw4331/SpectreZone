<?php
declare(strict_types=1);
namespace jasonwynn10\SpectreZone;

use alvin0319\CustomItemLoader\CustomItemManager;
use alvin0319\CustomItemLoader\item\CustomItem;
use jasonwynn10\SpectreZone\item\CustomReleasableItem;
use jasonwynn10\SpectreZone\lib\JsonStreamingParser\Listener\SimpleObjectQueueListener;
use jasonwynn10\SpectreZone\lib\JsonStreamingParser\Parser;
use pocketmine\crafting\ShapedRecipe;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\ItemUseResult;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\generator\InvalidGeneratorOptionsException;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\WorldCreationOptions;

final class SpectreZone extends PluginBase {
	private array $savedPositions = [];
	private int $defaultHeight = 4;
	private int $chunkOffset = 1;

	public function onEnable() : void {
		$server = $this->getServer();

		// register custom items
		$itemManager = CustomItemManager::getInstance();
		$ectoplasm = new CustomItem(
			'Ectoplasm',
			[
				'id' => 400,
				'meta' => 0,
				'namespace' => 'spectrezone:ectoplasm',
				'creative_category' => 'items',
				'max_stack_size' => 64,
				'texture' => 'ectoplasm'
			]
		);
		$itemManager->registerItem($ectoplasm);
		$spectreIngot = new CustomItem(
			'Spectre Ingot',
			[
				'id' => 401,
				'meta' => 0,
				'namespace' => 'spectrezone:spectre_ingot',
				'creative_category' => 'items',
				'max_stack_size' => 64,
				'texture' => 'spectre_ingot'
			]
		);
		$itemManager->registerItem($spectreIngot);
		$spectreKey = new CustomReleasableItem(
			'Spectre Key',
			[
				'id' => 402,
				'meta' => 0,
				'namespace' => 'spectrezone:spectre_key',
				'creative_category' => 'items',
				'use_duration' => 5,
				'max_stack_size' => 1,
				'texture' => 'spectre_key'
			],
			function(Player $player) {
				if($player->getWorld() === $this->getServer()->getWorldManager()->getWorldByName('SpectreZone')) {
					$position = $this->getSavedPosition($player);
				}else{
					$this->savePlayerPosition($player);
					$position = $this->getSpectreSpawn($player);
				}
				$player->teleport($position);

				return ItemUseResult::NONE(); // TODO: test return ItemUseResult::SUCCESS()
			}
		);
		$spectreKey->getProperties()->getNbt()->setTag('minecraft:throwable', CompoundTag::create()
			->setByte('throwable', 1) // Enable item use-by-throw animation
			->setFloat('min_draw_duration', 5.0) // Only activate key after 5 seconds
			->setFloat('max_draw_duration', 5.0) // Force key to activate after 5 seconds
		);
		$itemManager->registerItem($spectreKey);

		// register custom item recipes
		$craftManager = $server->getCraftingManager();
		$craftManager->registerShapedRecipe(new ShapedRecipe(
			[
				' A ',
				' B ',
				' C '
			],
			[
				'A' => VanillaItems::LAPIS_LAZULI(),
				'B' => VanillaItems::GOLD_INGOT(),
				'C' => $ectoplasm
			],
			[
				$spectreIngot
			]
		));
		$countedSpectreIngot = (clone $spectreIngot)->setCount(9);
		$craftManager->registerShapedRecipe(new ShapedRecipe(
			[
				'CAC',
				'CBC',
				'CCC'
			],
			[
				'A' => VanillaItems::LAPIS_LAZULI(),
				'B' => VanillaItems::GOLD_INGOT(),
				'C' => $ectoplasm
			],
			[
				$countedSpectreIngot
			]
		));
		$craftManager->registerShapedRecipe(new ShapedRecipe(
			[
				'A  ',
				'AB ',
				'  A'
			],
			[
				'A' => $spectreIngot,
				'B' => VanillaItems::ENDER_PEARL()
			],
			[
				$spectreKey
			]
		));

		// TODO: register custom blocks

		// Register world generator

		GeneratorManager::getInstance()->addGenerator(
			SpectreZoneGenerator::class,
			'SpectreZone',
			\Closure::fromCallable(
				function(string $generatorOptions) {
					$parsedOptions = \json_decode($generatorOptions, true, flags: JSON_THROW_ON_ERROR);
					if(!is_int($parsedOptions['Chunk Offset']) or $parsedOptions['Chunk Offset'] < 0) {
						return new InvalidGeneratorOptionsException();
					}elseif(!is_int($parsedOptions['Default Height']) or $parsedOptions['Default Height'] < 2) {
						return new InvalidGeneratorOptionsException();
					}
					return null;
				}
			)
		);

		// Load or generate the SpectreZone dimension
		$worldManager = $server->getWorldManager();
		if(!$worldManager->loadWorld('SpectreZone')) {
			$server->getWorldManager()->generateWorld(
				'SpectreZone',
				WorldCreationOptions::create()
					->setGeneratorClass(SpectreZoneGenerator::class)
					->setDifficulty(World::DIFFICULTY_PEACEFUL)
					->setSpawnPosition(new Vector3(0.5, 1, 0.5))
					->setGeneratorOptions(\json_encode($this->getConfig()->getAll(), JSON_THROW_ON_ERROR)),
				true,
				false // keep this for NativeDimensions compatibility
			);
		}

		$spectreZone = $worldManager->getWorldByName('SpectreZone');
		$options = \json_decode($spectreZone->getProvider()->getWorldData()->getGeneratorOptions(), true, flags: JSON_THROW_ON_ERROR);

		$this->defaultHeight = (int) \abs($options["Default Height"] ?? 4);
		$this->chunkOffset = (int) \abs($options["Chunk Offset"] ?? 1);

		// register events
		$server->getPluginManager()->registerEvent(
			PlayerQuitEvent::class,
			\Closure::fromCallable(
				function(PlayerQuitEvent $event) {
					$player = $event->getPlayer();
					if(isset($this->savedPositions[$player->getUniqueId()->toString()])) { // if set, the player is in the SpectreZone world
						$position = $this->savedPositions[$player->getUniqueId()->toString()];
						unset($this->savedPositions[$player->getUniqueId()->toString()]);
						$player->teleport($position); // teleport the player back to their last position
					}
				}
			),
			EventPriority::MONITOR,
			$this,
			true // doesn't really matter because event cannot be cancelled
		);
	}

	public function getDefaultHeight() : int{
		return $this->defaultHeight;
	}

	public function getChunkOffset() : int{
		return $this->chunkOffset;
	}

	public function getSpectreSpawn(Player $player) : Position {
		$spectreZone = $this->getServer()->getWorldManager()->getWorldByName('SpectreZone');
		\assert($spectreZone !== null);

		$stream = fopen($this->getDataFolder().'zones.json', 'r');
		$listener = new SimpleObjectQueueListener(fn(array $currentObject) => \var_dump($currentObject));
		try {
			$parser = new Parser($stream, $listener);
			$parser->parse();
		} catch (\Exception $e) {
			$this->getLogger()->logException($e);
		}finally{
			fclose($stream);
		}

		return $player->getPosition(); // TODO: replace placeholder with actual spawn position
	}

	private function isUsableChunk(int $chunkX, int $chunkZ) : bool{
		return $chunkX % (3 + $this->chunkOffset) === 0 and $chunkZ % (3 + $this->chunkOffset) === 0;
	}

	private function savePlayerPosition(Player $player) : void {
		$this->savedPositions[$player->getUniqueId()->toString()] = $player->getPosition();
	}

	private function getSavedPosition(Player $player) : Position {
		if(isset($this->savedPositions[$player->getUniqueId()->toString()])) {
			$position = $this->savedPositions[$player->getUniqueId()->toString()];
			unset($this->savedPositions[$player->getUniqueId()->toString()]);
			return $position;
		}
		return $player->getSpawn(); // return the player's spawn position as a fallback
	}
}