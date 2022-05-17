<?php
declare(strict_types=1);
namespace jasonwynn10\SpectreZone\block;

use pocketmine\block\Bedrock;

class SpectreBlock extends Bedrock{
	public function getLightLevel() : int{
		return 13;
	}
}