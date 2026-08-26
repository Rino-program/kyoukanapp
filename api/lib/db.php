<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

final class Database {
	private static ?PDO $instance = null;
	public static function connection(): PDO {
		if (!self::$instance) {
			self::$instance = new PDO((string) config('db.dsn'), (string) config('db.user'), (string) config('db.password'), [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => false,
			]);
		}
		return self::$instance;
	}
}
