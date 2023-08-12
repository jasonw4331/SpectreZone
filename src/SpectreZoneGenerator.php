<?php

declare(strict_types=1);

namespace jasonw4331\SpectreZone;

use jasonw4331\SpectreZone\block\CustomBlocksRegistry;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\LightArray;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\generator\Generator;
use function abs;
use function array_fill;
use function ceil;
use function json_decode;
use const JSON_THROW_ON_ERROR;

final class SpectreZoneGenerator extends Generator{

	protected int $height = 4;
	protected int $multiplier = 0;

	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);
		/**
		 * @phpstan-var array{"Default Height": ?int, "Chunk Offset": ?int} $parsedData
		 */
		$parsedData = json_decode($preset, true, flags: JSON_THROW_ON_ERROR);
		$this->height = abs($parsedData["Default Height"] ?? 4);
		$this->multiplier = abs($parsedData["Chunk Offset"] ?? 1);
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$block = CustomBlocksRegistry::SPECTRE_BLOCK();

		$chunk = new Chunk(
			array_fill(Chunk::MIN_SUBCHUNK_INDEX, Chunk::MAX_SUBCHUNK_INDEX - Chunk::MIN_SUBCHUNK_INDEX,
				new SubChunk(
					BlockTypeIds::AIR << Block::INTERNAL_STATE_DATA_BITS,
					[new PalettedBlockArray($block->getStateId())],
					new PalettedBlockArray(BiomeIds::JUNGLE), // set to jungle so we can get bright green grass
					LightArray::fill(15),
					LightArray::fill(15)
				)
			),
			true
		);

		if($this->isChunkValid($chunkX, $chunkZ)){
			$block = CustomBlocksRegistry::SPECTRE_CORE();

			$center = (int) ceil(Chunk::EDGE_LENGTH / 2);

			for($x = 0; $x <= Chunk::EDGE_LENGTH; ++$x){
				for($z = 0; $z <= Chunk::EDGE_LENGTH; ++$z){
					for($y = $world->getMinY(); $y < $world->getMaxY(); ++$y){
						if($y > $world->getMinY() && $y <= $world->getMinY() + $this->height){
							$chunk->setBlockStateId($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK, VanillaBlocks::AIR()->getStateId());
						}elseif(
							($x === $center || $x === $center + 1) &&
							($z === $center || $z === $center + 1)
						){
							$chunk->setBlockStateId($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK, $block->getStateId());
						}
					}
				}
			}
		}
		$world->setChunk($chunkX, $chunkZ, $chunk);
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{ }

	protected function isChunkValid(int $chunkX, int $chunkZ) : bool{
		return $chunkX % (3 + $this->multiplier) === 0 && $chunkZ % (3 + $this->multiplier) === 0;
	}
}
