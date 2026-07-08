<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PDO;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $this->configureFallbackTestingDatabase();

        return parent::createApplication();
    }

    private function configureFallbackTestingDatabase(): void
    {
        $configuredConnection = $_ENV['DB_CONNECTION'] ?? $_SERVER['DB_CONNECTION'] ?? getenv('DB_CONNECTION') ?: null;

        if ($configuredConnection !== 'sqlite' || extension_loaded('pdo_sqlite')) {
            return;
        }

        $basePath = dirname(__DIR__);
        \Dotenv\Dotenv::createImmutable($basePath)->safeLoad();

        $host = $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? '3306';
        $username = $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? '';
        $database = $_ENV['DB_DATABASE_TEST'] ?? $_SERVER['DB_DATABASE_TEST'] ?? 'dayang_inventory_test';

        $this->setEnvironmentValue('DB_CONNECTION', 'mysql');
        $this->setEnvironmentValue('DB_HOST', $host);
        $this->setEnvironmentValue('DB_PORT', $port);
        $this->setEnvironmentValue('DB_DATABASE', $database);
        $this->setEnvironmentValue('DB_USERNAME', $username);
        $this->setEnvironmentValue('DB_PASSWORD', $password);

        $this->ensureMysqlDatabaseExists($host, $port, $username, $password, $database);
    }

    private function setEnvironmentValue(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function ensureMysqlDatabaseExists(
        string $host,
        string $port,
        string $username,
        string $password,
        string $database,
    ): void {
        $dsn = "mysql:host={$host};port={$port}";

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec(sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                str_replace('`', '``', $database)
            ));
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                "Unable to prepare MySQL test database [{$database}]: {$exception->getMessage()}",
                previous: $exception,
            );
        }
    }
}
