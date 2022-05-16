<?php
declare(strict_types=1);
namespace jasonwynn10\SpectreZone;

use jasonwynn10\SpectreZone\item\SpectreKey;
use pocketmine\color\Color;
use pocketmine\crafting\ShapedRecipe;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\convert\GlobalItemTypeDictionary;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ItemComponentPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\CacheableNbt;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\ItemComponentPacketEntry;
use pocketmine\network\mcpe\protocol\types\ItemTypeEntry;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\resourcepacks\ResourcePack;
use pocketmine\resourcepacks\ZippedResourcePack;
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
use Webmozart\PathUtil\Path;

final class SpectreZone extends PluginBase {
	private static array $packetEntries = [];
	private static ?ResourcePack $pack = null;
	/** @var array<int, array<Position|int>> $savedPositions */
	private array $savedPositions = [];
	private int $defaultHeight = 4;
	private int $chunkOffset = 0;

	public function onEnable() : void {
		$server = $this->getServer();

		// register custom items
		$this->registerCustomItem($ectoplasm = new Item(new ItemIdentifier(600, 0), "Ectoplasm"), $this->getName());
		$this->registerCustomItem($spectreIngot = new Item(new ItemIdentifier(601, 0), "Spectre Ingot"), $this->getName());
		$this->registerCustomItem(
			$spectreKey = new SpectreKey(),
			$this->getName(),
			CompoundTag::create()
				->setInt("max_stack_size", 1),
			CompoundTag::create()
				->setTag('minecraft:projectile', CompoundTag::create())
				->setTag('minecraft:throwable', CompoundTag::create()
					->setByte('do_swing_animation', 0)
					->setFloat('launch_power_scale', 1.0)
					->setFloat('max_draw_duration', 15.0)
					->setFloat('max_launch_power', 30.0)
					->setFloat('min_draw_duration', 5.0)
					->setByte('scale_power_by_draw_duration', 1)
				)
		);
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
		$this->getLogger()->debug('Registered custom recipes');

		// TODO: register custom blocks

		// Compile resource pack
		$zip = new \ZipArchive();
		$zip->open(Path::join($this->getDataFolder(), $this->getName().'.mcpack'), \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		foreach($this->getResources() as $resource){
			if($resource->isFile() and str_contains($resource->getPathname(), 'SpectreZone Pack')){
				$relativePath = Path::normalize(preg_replace("/.*[\/\\\\]SpectreZone\hPack[\/\\\\].*/U", '', $resource->getPathname()));
				$zip->addFile(Path::normalize($resource->getPathname()), $relativePath);
			}
		}
		$zip->close();
		$this->getLogger()->debug('Resource pack compiled');

		// Register resource pack
		$this->registerResourcePack(self::$pack = new ZippedResourcePack(Path::join($this->getDataFolder(), $this->getName().'.mcpack')));
		$this->getLogger()->debug('Resource pack registered');

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
			),
			false // There should never be another generator with the same name
		);
		$this->getLogger()->debug('World generator registered');

		// Load or generate the SpectreZone dimension
		$worldManager = $server->getWorldManager();
		if(!$worldManager->loadWorld('SpectreZone')) {
			$this->getLogger()->debug('SpectreZone dimension was not loaded. Generating now...');
			$worldManager->generateWorld(
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
		$this->getLogger()->debug('SpectreZone dimension loaded');

		$spectreZone = $worldManager->getWorldByName('SpectreZone');
		$options = \json_decode($spectreZone->getProvider()->getWorldData()->getGeneratorOptions(), true, flags: JSON_THROW_ON_ERROR);

		$this->defaultHeight = (int) \abs($options["Default Height"] ?? 4);
		$this->chunkOffset = (int) \abs($options["Chunk Offset"] ?? 1);

		// register events
		$pluginManager = $server->getPluginManager();
		$pluginManager->registerEvent(
			PlayerCreationEvent::class, // use PlayerCreationEvent because it's the first event fired after the player receives resource packs
			\Closure::fromCallable(
				function(PlayerCreationEvent $event) {
					$event->getNetworkSession()->sendDataPacket(ItemComponentPacket::create(self::$packetEntries));
				}
			),
			EventPriority::MONITOR,
			$this
		);
		$pluginManager->registerEvent(
			DataPacketSendEvent::class,
			\Closure::fromCallable(
				function(DataPacketSendEvent $event) {
					$packets = $event->getPackets();
					foreach($packets as $packet){
						if($packet instanceof StartGamePacket){
							$packet->levelSettings->experiments = new Experiments([
								"data_driven_items" => true,
								'holiday_creator_features' => true,
								'upcoming_creator_features' => true,
							], true);
						}elseif($packet instanceof ResourcePackStackPacket){
							$packet->experiments = new Experiments([
								"data_driven_items" => true,
								'holiday_creator_features' => true,
								'upcoming_creator_features' => true,
							], true);
						}elseif($packet instanceof ItemComponentPacket and
							count(array_filter($packet->getEntries(),
								function(ItemComponentPacketEntry $entry) {
									return str_contains($entry->getName(), mb_strtolower($this->getName()));
								}
							)) < 3
						) {
							$event->cancel();

							$entries = $packet->getEntries();
							array_push($entries, ...self::$packetEntries);
							$this->getServer()->broadcastPackets(
								array_map(fn(NetworkSession $session) => $session->getPlayer(), $event->getTargets()),
								[ItemComponentPacket::create($entries)]
							);
						}
					}
				}
			),
			EventPriority::LOWEST,
			$this,
			false // Don't waste time on cancelled events
		);
		$pluginManager->registerEvent(
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
		$pluginManager->registerEvent(
			PlayerItemUseEvent::class,
			\Closure::fromCallable(
				function(PlayerItemUseEvent $event) {
					$player = $event->getPlayer();
					if($event->getItem() instanceof SpectreKey and !$player->isUsingItem()) {
						$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(
							\Closure::fromCallable(
								function() use ($player) {
									if($player->getInventory()->getItemInHand() instanceof SpectreKey and $player->isUsingItem()) {
										$this->spawnParticles($player->getPosition());
									}else{
										throw new CancelTaskException();
									}
								}
							)
						), 1);
					}
				}
			),
			EventPriority::MONITOR,
			$this,
			false // Don't waste time on cancelled events
		);
		$this->getLogger()->debug('Event listeners registered');
	}

	public function onDisable() : void {
		$manager = $this->getServer()->getResourcePackManager();
		$pack = self::$pack;

		$reflection = new \ReflectionClass($manager);

		$property = $reflection->getProperty("resourcePacks");
		$property->setAccessible(true);
		$currentResourcePacks = $property->getValue($manager);
		$key = array_search($pack, $currentResourcePacks);
		if($key !== false){
			unset($currentResourcePacks[$key]);
			$property->setValue($manager, $currentResourcePacks);
		}

		$property = $reflection->getProperty("uuidList");
		$property->setAccessible(true);
		$currentUUIDPacks = $property->getValue($manager);
		if(isset($currentResourcePacks[strtolower($pack->getPackId())])) {
			unset($currentUUIDPacks[strtolower($pack->getPackId())]);
			$property->setValue($manager, $currentUUIDPacks);
		}
		unlink(Path::join($this->getDataFolder(), $this->getName().'.mcpack'));
	}

	private function registerCustomItem(Item $item, string $namespace, ?CompoundTag $propertiesTag = null, ?CompoundTag $componentTag = null): void{
		$itemTranslator = ItemTranslator::getInstance();

		// Get the current net id map information from the core
		$ref = new \ReflectionClass($itemTranslator);
		$coreToNetMap = $ref->getProperty("simpleCoreToNetMapping");
		$netToCoreMap = $ref->getProperty("simpleNetToCoreMapping");
		$coreToNetMap->setAccessible(true);
		$netToCoreMap->setAccessible(true);

		$coreToNetValues = $coreToNetMap->getValue($itemTranslator);
		$netToCoreValues = $netToCoreMap->getValue($itemTranslator);

		$legacyId = $item->getId();
		$runtimeId = $legacyId + ($legacyId > 0 ? 5000 : -5000);

		// Add the new custom item to the core mapping
		$coreToNetValues[$legacyId] = $runtimeId;
		$netToCoreValues[$runtimeId] = $legacyId;

		// Save the new core mapping
		$coreToNetMap->setValue($itemTranslator, $coreToNetValues);
		$netToCoreMap->setValue($itemTranslator, $netToCoreValues);

		$typeDictionary = GlobalItemTypeDictionary::getInstance()->getDictionary();

		// Get the current item type map information from the core
		$ref_1 = new \ReflectionClass($typeDictionary);
		$itemTypeMap = $ref_1->getProperty("itemTypes");
		$itemTypeMap->setAccessible(true);

		$itemTypeEntries = $itemTypeMap->getValue($typeDictionary);

		$simpleName = mb_strtolower(str_replace(' ', '_', $item->getVanillaName()));
		$fullName = mb_strtolower($namespace).':'.$simpleName;

		// Add the new custom item's type entry to the type map
		$itemTypeEntries[] = new ItemTypeEntry($fullName, $runtimeId, true);

		// Save the new type map
		$itemTypeMap->setValue($typeDictionary, $itemTypeEntries);

		self::$packetEntries[] = new ItemComponentPacketEntry($fullName,
			new CacheableNbt(CompoundTag::create()
				->setTag("components", CompoundTag::create()
					->setTag("item_properties", CompoundTag::create()
						->setString("creative_group", 'Items')
						->setByte('creative_category', 4)
						->setInt('max_stack_size', 64)
						->setTag('minecraft:icon', CompoundTag::create()
							->setString('texture', $simpleName)
						)
						->merge($propertiesTag ?? CompoundTag::create())
					)
					->setTag('minecraft:display_name', CompoundTag::create()
						->setString('value', $item->getName())
					)
					->merge($componentTag ?? CompoundTag::create())
				)
			)
		);

		ItemFactory::getInstance()->register($item, false); // Item should be unique, so we don't override here
		CreativeInventory::getInstance()->add($item);
		$cloneItem = clone $item;
		StringToItemParser::getInstance()->register($item->getVanillaName(), fn() => $cloneItem);
	}

	private function registerResourcePack(ResourcePack $pack){
		$manager = $this->getServer()->getResourcePackManager();

		$reflection = new \ReflectionClass($manager);

		$property = $reflection->getProperty("resourcePacks");
		$property->setAccessible(true);
		$currentResourcePacks = $property->getValue($manager);
		$currentResourcePacks[] = $pack;
		$property->setValue($manager, $currentResourcePacks);

		$property = $reflection->getProperty("uuidList");
		$property->setAccessible(true);
		$currentUUIDPacks = $property->getValue($manager);
		$currentUUIDPacks[strtolower($pack->getPackId())] = $pack;
		$property->setValue($manager, $currentUUIDPacks);

		$property = $reflection->getProperty("serverForceResources");
		$property->setAccessible(true);
		$property->setValue($manager, true);
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
		$worldData = $spectreZone->getProvider()->getWorldData();

		$ref = new \ReflectionClass(BaseNbtWorldData::class);
		$prop = $ref->getProperty('compoundTag');
		$prop->setAccessible(true);

		/** @var CompoundTag $root */
		$root = $prop->getValue($worldData);
		/** @var ListTag $tag */
		$tag = $root->getTag('spawnList') ?? new ListTag([], NBT::TAG_Compound);

		$highestX = 0;
		$highestZ = 0;
		/** @var CompoundTag $value */
		foreach($tag->getValue() as $value) {
			$chunkX = $value->getInt('chunkX');
			$chunkZ = $value->getInt('chunkZ');
			if($value->getString('UUID') === $player->getUniqueId()->toString()) {
				return new Position(($chunkX << Chunk::COORD_BIT_SIZE) + (Chunk::COORD_BIT_SIZE / 2), 2, ($chunkZ << Chunk::COORD_BIT_SIZE) + (Chunk::COORD_BIT_SIZE / 2), $spectreZone);
			}
			if(\abs($chunkX) > \abs($highestX)) {
				$highestX = $chunkX;
			}
			if(\abs($chunkZ) > \abs($highestZ)) {
				$highestZ = $chunkZ;
			}
		}

		$alternator = true;
		while(!$this->isUsableChunk($highestX, $highestZ)) {
			if($alternator){
				if($highestX > 0) {
					$highestX--;
				} else {
					$highestX++;
				}
			}else{
				if($highestZ > 0) {
					$highestZ--;
				} else {
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
		$prop->setValue($worldData, $root);

		return new Position(($highestX << Chunk::COORD_BIT_SIZE) + (Chunk::COORD_BIT_SIZE / 2), 2, ($highestZ << Chunk::COORD_BIT_SIZE) + (Chunk::COORD_BIT_SIZE / 2), $spectreZone);
	}

	private function isUsableChunk(int $chunkX, int $chunkZ) : bool{
		return $chunkX % (3 + $this->chunkOffset) === 0 and $chunkZ % (3 + $this->chunkOffset) === 0;
	}

	public function savePlayerInfo(Player $player) : void {
		$this->savedPositions[$player->getUniqueId()->toString()] = [$player->getPosition(), $player->getViewDistance()];
	}

	/**
	 * @param Player $player
	 *
	 * @return array<Position|int>
	 */
	public function getSavedInfo(Player $player) : array {
		if(isset($this->savedPositions[$player->getUniqueId()->toString()])) {
			[$position, $viewDistance] = $this->savedPositions[$player->getUniqueId()->toString()];
			unset($this->savedPositions[$player->getUniqueId()->toString()]);
			return [$position, $viewDistance];
		}
		return [$player->getSpawn(), $this->getServer()->getViewDistance()];
	}

	private function spawnParticles(Position $position){

		$xOffset = lcg_value() > 0.5 ? lcg_value() : -lcg_value();
		$zOffset = lcg_value() > 0.5 ? lcg_value() : -lcg_value();

		$position->getWorld()->addParticle($position->add($xOffset, 1, $zOffset), new DustParticle(new Color(68, 188, 255)));
	}
}