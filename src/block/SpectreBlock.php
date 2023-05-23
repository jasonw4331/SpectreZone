<?php

declare(strict_types=1);

namespace jasonw4331\SpectreZone\block;

use pocketmine\block\Bedrock;

final class SpectreBlock extends Bedrock{
	public function getLightLevel() : int{
		return 13;
	}
}
