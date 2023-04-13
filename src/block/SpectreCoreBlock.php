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

final class SpectreCoreBlock extends Bedrock{

	public function getLightLevel() : int{
		return 15;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if(!$item instanceof Ectoplasm){
			return false;
		}

		$position = $this->getPosition();
		$world = $position->getWorld();

		$cornerX = ($position->getFloorX() >> Chunk::COORD_BIT_SIZE) << Chunk::COORD_BIT_SIZE;
		$cornerZ = ($position->getFloorZ() >> Chunk::COORD_BIT_SIZE) << Chunk::COORD_BIT_SIZE;

		// find lowest block in chunk above y = 2
		for($y = 2; $y < $position->getWorld()->getMaxY() - 1; ++$y){
			if($world->getBlockAt($cornerX, $y, $cornerZ) instanceof SpectreBlock){
				// remove all blocks at this height
				for($x = 0; $x < Chunk::EDGE_LENGTH; ++$x){ // cornerX is at 1 relatively
					for($z = 0; $z < Chunk::EDGE_LENGTH; ++$z){  // cornerZ is at 1 relatively
						$world->setBlockAt($cornerX + $x, $y, $cornerZ + $z, VanillaBlocks::AIR(), false);
					}
				}
				$item->pop(); // consume one ectoplasm on success
				return true;
			}
		}
		return false;
	}
}
