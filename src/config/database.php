<?php
// Simple PDO Postgres connector using environment variables
// Expected env: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD

function getDatabaseConnection(): PDO
{
	$host = getenv('DB_HOST') ?: '127.0.0.1';
	$port = getenv('DB_PORT') ?: '5432';
	$db   = getenv('DB_NAME') ?: 'dmg';
	$user = getenv('DB_USER') ?: 'dmg_user';
	$pass = getenv('DB_PASSWORD') ?: '';

	$dsn = "pgsql:host={$host};port={$port};dbname={$db}";

	try {
		$pdo = new PDO($dsn, $user, $pass, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]);
		return $pdo;
	} catch (PDOException $e) {
		// For development, echo error and exit. In production, log and show generic message.
		echo "Database connection failed: " . $e->getMessage();
		exit(1);
	}
}

