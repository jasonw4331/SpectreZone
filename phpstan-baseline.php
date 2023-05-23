<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Call to function is_int\\(\\) with int will always evaluate to true\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/SpectreZone.php',
];
$ignoreErrors[] = [
	'message' => '#^Method pocketmine\\\\world\\\\WorldManager\\:\\:generateWorld\\(\\) invoked with 4 parameters, 2\\-3 required\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZone.php',
];
$ignoreErrors[] = [
	'message' => '#^Right side of && is always false\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpectreZone.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
