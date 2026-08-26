<?php
declare(strict_types=1);

$configFile = __DIR__ . '/../config.php';
$config = is_file($configFile) ? require $configFile : require __DIR__ . '/../config.example.php';

date_default_timezone_set('Asia/Tokyo');

function config(string $key, mixed $default = null): mixed {
	global $config;
	$value = $config;
	foreach (explode('.', $key) as $part) {
		if (!is_array($value) || !array_key_exists($part, $value)) return $default;
		$value = $value[$part];
	}
	return $value;
}

function jsonResponse(mixed $data, int $status = 200): never {
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

function requestBody(): array {
	$raw = file_get_contents('php://input') ?: '';
	$data = json_decode($raw, true);
	return is_array($data) ? $data : [];
}

function fail(string $message, int $status = 400): never { jsonResponse(['error' => $message], $status); }
