<?php
declare(strict_types=1);
namespace jasonwynn10\SpectreZone\block;

use jasonwynn10\SpectreZone\item\Ectoplasm;
use pocketmine\block\Bedrock;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\format\Chunk;
use twistedasylummc\customies\block\CustomiesBlockFactory;

class SpectreCoreBlock extends Bedrock{
	public function getLightLevel() : int{
		return 15;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($item instanceof Ectoplasm) {
			$item->pop();

			$position = $this->getPosition();

			$chunk = $this->getPosition()->getWorld()->getChunk(
				$position->getFloorX() >> Chunk::COORD_BIT_SIZE,
				$position->getFloorZ() >> Chunk::COORD_BIT_SIZE
			);

			$block = CustomiesBlockFactory::getInstance()->get('spectrezone:spectre_block');
			// find lowest block in chunk above y = 2
			for($y = 2; $y < $position->getWorld()->getMaxY(); ++$y){
				if($chunk->getFullBlock(0, $y, 0) === $block->getFullId()) {
					// remove all blocks at this height
					for($x = 0; $x <= Chunk::EDGE_LENGTH; ++$x){
						for($z = 0; $z <= Chunk::EDGE_LENGTH; ++$z){
							$chunk->setFullBlock($x, $y, $z, VanillaBlocks::AIR()->getFullId());
						}
					}
					return true;
				}
			}
		}
		return false;
	}
}