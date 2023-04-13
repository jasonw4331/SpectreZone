<?php

declare(strict_types=1);

namespace jasonwynn10\SpectreZone;

use customiesdevs\customies\block\CustomiesBlockFactory;
use pocketmine\block\Block;
use pocketmine\block\BlockLegacyIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\format\LightArray;
use pocketmine\world\format\PalettedBlockArray;
use pocketmine\world\format\SubChunk;
use pocketmine\world\generator\Generator;
use function abs;
use function ceil;
use function json_decode;
use const JSON_THROW_ON_ERROR;

final class SpectreZoneGenerator extends Generator{

	protected int $height = 4;
	protected int $multiplier = 0;

	public function __construct(int $seed, string $preset){
		parent::__construct($seed, $preset);
		$parsedData = json_decode($preset, true, flags: JSON_THROW_ON_ERROR);
		$this->height = (int) abs($parsedData["Default Height"] ?? 4);
		$this->multiplier = (int) abs($parsedData["Chunk Offset"] ?? 1);
	}

	public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{
		$chunk = $world->getChunk($chunkX, $chunkZ);

		$blockFactory = CustomiesBlockFactory::getInstance();
		$block = $blockFactory->get('spectrezone:spectre_block');

		for($subChunkY = Chunk::MIN_SUBCHUNK_INDEX; $subChunkY <= Chunk::MAX_SUBCHUNK_INDEX; ++$subChunkY){
			$chunk->setSubChunk($subChunkY, new SubChunk(BlockLegacyIds::AIR << Block::INTERNAL_METADATA_BITS, [new PalettedBlockArray($block->getFullId())], LightArray::fill(15), LightArray::fill(15)));
		}

		if($this->isChunkValid($chunkX, $chunkZ)){
			$block = $blockFactory->get('spectrezone:spectre_core');

			$center = (int) ceil(Chunk::EDGE_LENGTH / 2);

			for($x = 0; $x <= Chunk::EDGE_LENGTH; ++$x){
				for($z = 0; $z <= Chunk::EDGE_LENGTH; ++$z){
					for($y = $world->getMinY(); $y < $world->getMaxY(); ++$y){
						if($y > $world->getMinY() && $y <= $world->getMinY() + $this->height){
							$chunk->setFullBlock($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK, VanillaBlocks::AIR()->getFullId());
						}elseif(($x === $center ||
								$x === $center + 1) &&
							($z === $center ||
								$z === $center + 1)
						){
							$chunk->setFullBlock($x & Chunk::COORD_MASK, $y, $z & Chunk::COORD_MASK, $block->getFullId());
						}
					}
					$chunk->setBiomeId($x & Chunk::COORD_MASK, $z & Chunk::COORD_MASK, BiomeIds::JUNGLE); // set to jungle so we can get bright green grass
				}
			}
		}
	}

	public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ) : void{ }

	protected function isChunkValid(int $chunkX, int $chunkZ) : bool{
		return $chunkX % (3 + $this->multiplier) === 0 && $chunkZ % (3 + $this->multiplier) === 0;
	}
}
