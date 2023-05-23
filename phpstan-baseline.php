<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Call to function is_int\\(\\) with int will always evaluate to true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/SpectreZone.php',
];
$ignoreErrors[] = [
	'message' => '#^Method customiesdevs\\\\customies\\\\block\\\\CustomiesBlockFactory\\:\\:registerBlock\\(\\) invoked with 6 parameters, 2\\-4 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZone.php',
];
$ignoreErrors[] = [
	'message' => '#^Method pocketmine\\\\world\\\\WorldManager\\:\\:generateWorld\\(\\) invoked with 4 parameters, 2\\-3 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZone.php',
];
$ignoreErrors[] = [
	'message' => '#^Offset 2 on array\\{pocketmine\\\\block\\\\Block, pocketmine\\\\block\\\\BlockBreakInfo, customiesdevs\\\\customies\\\\item\\\\CreativeInventoryInfo, Closure\\|null, Closure\\|null\\} on left side of \\?\\? always exists and is not nullable\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZone.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZone.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to constant AIR on an unknown class pocketmine\\\\block\\\\BlockLegacyIds\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZoneGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to undefined constant pocketmine\\\\block\\\\Block\\:\\:INTERNAL_METADATA_BITS\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZoneGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method pocketmine\\\\block\\\\Air\\:\\:getFullId\\(\\)\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZoneGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to an undefined method pocketmine\\\\block\\\\Block\\:\\:getFullId\\(\\)\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/SpectreZoneGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'Chunk Offset\' on mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZoneGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'Default Height\' on mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZoneGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method setBiomeId\\(\\) on pocketmine\\\\world\\\\format\\\\Chunk\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZoneGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method setFullBlock\\(\\) on pocketmine\\\\world\\\\format\\\\Chunk\\|null\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/SpectreZoneGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method setSubChunk\\(\\) on pocketmine\\\\world\\\\format\\\\Chunk\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZoneGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$number of function abs expects float\\|int\\|string, mixed given\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/SpectreZoneGenerator.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#3 \\$biomes of class pocketmine\\\\world\\\\format\\\\SubChunk constructor expects pocketmine\\\\world\\\\format\\\\PalettedBlockArray, pocketmine\\\\world\\\\format\\\\LightArray given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZoneGenerator.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
