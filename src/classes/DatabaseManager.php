<?php

namespace Danupe\Plugin\Database\Classes;

use Danupe\Core\Classes\File;
use Danupe\Plugin\Database\Classes\Database;

class DatabaseManager
{
    public Database $db;
    private string $migrationsTable = 'migrations';
    private string $seedsTable = 'seeds';

    public function __construct()
    {
        $this->db = new Database();
    }

    private function ensureMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationsTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->table($this->migrationsTable)->raw($sql);
    }

    private function ensureSeedsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->seedsTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seed VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->table($this->seedsTable)->raw($sql);
    }

    public function migrate(bool $clearDatabase = false): void
    {
        if ($clearDatabase) {
            echo "Leere die Datenbank...\n";
            $this->db->raw("DROP DATABASE IF EXISTS {$this->db->getDatabaseName()}");
            $this->db->raw("CREATE DATABASE {$this->db->getDatabaseName()}");

            $this->db = new Database();

            echo "Datenbank wurde geleert.\n";
        }

        $this->ensureMigrationsTable();
        $this->ensureSeedsTable();
        $migrations = $this->getPendingMigrations();

        foreach ($migrations as $migration) {
            $functions = File::get($migration);
            $this->db->raw($functions['up']);
            $this->logMigration($migration);
            echo "Migrated: {$migration}\n";
        }
    }

    public function rollback(): void
    {
        $batch = $this->getLastBatch();
        if ($batch === 0) {
            echo "Keine Migrationen zum Zurücksetzen gefunden.\n";
            return;
        }
        $migrations = $this->getMigrationsByBatch($batch);
        foreach ($migrations as $migration) {
            $this->rollbackMigration($migration['migration']);
        }
    }

    public function rollbackSpecificMigration(string $migration): void
    {
        $this->rollbackMigration($migration);
    }

    public function rollbackBatch(int $batch): void
    {
        $migrations = $this->getMigrationsByBatch($batch);
        foreach ($migrations as $migration) {
            $this->rollbackMigration($migration['migration']);
        }
    }

    private function rollbackMigration(string $migration): void
    {
        $functions = File::get($migration);
        $this->db->raw($functions['down']);
        $this->removeMigration($migration);
        echo "Rolled back: {$migration}\n";
    }

    public function seed(): void
    {
        $seeds = $this->getPendingSeeds();
        foreach ($seeds as $seed) {
            $functions = File::get($seed);
            $this->db->raw($functions['up']);
            $this->logSeed($seed);
            echo "Seeded: {$seed}\n";
        }
    }

    public function rollbackSpecificSeed(string $seed): void
    {
        $this->rollbackSeed($seed);
    }

    private function rollbackSeed(string $seed): void
    {
        $functions = File::get($seed);
        $this->db->raw($functions['down']);
        $this->removeSeed($seed);
        echo "Rolled back seed: {$seed}\n";
    }

    private function logMigration(string $migration): void
    {
        $this->db->table($this->migrationsTable)->create([
            'migration' => $migration,
            'batch' => $this->getCurrentBatch() + 1
        ]);
    }

    private function logSeed(string $seed): void
    {
        $this->db->table($this->seedsTable)->create([
            'seed' => $seed
        ]);
    }

    private function getPendingMigrations(): array
    {
        $appliedMigrations = $this->getAppliedMigrations($this->migrationsTable);
        return $this->getPendingFiles('migrations', $appliedMigrations);
    }

    private function getPendingSeeds(): array
    {
        $appliedSeeds = $this->getAppliedMigrations($this->seedsTable);
        return $this->getPendingFiles('seeds', $appliedSeeds);
    }

    private function getAppliedMigrations(string $table): array
    {
        $applied = $this->db->table($table)->all();
        return array_column($applied, 'migration');
    }

    private function getPendingFiles(string $type, array $applied): array
    {
        $files = [];
        foreach (danupe()->path()->plugins() as $plugin) {
            $path = danupe()->path()->base() . $plugin . '/src/database/' . $type;
            if (File::exists($path)) {
                $files = array_merge($files, danupe()->path()->getAllFiles($path));
            }
        }
        return array_filter($files, fn($file) => !in_array(basename($file), $applied));
    }

    private function getLastBatch(): int
    {
        $last = $this->db->table($this->migrationsTable)->last();
        return $last['batch'] ?? 0;  // Wenn $last null ist, wird 0 zurückgegeben
    }

    private function getCurrentBatch(): int
    {
        $batch = $this->db->table($this->migrationsTable)->first();
        return $batch['batch'] ?? 0;
    }

    private function getMigrationsByBatch(int $batch): array
    {
        return $this->db->table($this->migrationsTable)->where(['batch' => $batch])->get();
    }

    private function removeMigration(string $migration): void
    {
        $this->db->table($this->migrationsTable)->delete(['migration' => $migration]);
    }

    private function removeSeed(string $seed): void
    {
        $this->db->table($this->seedsTable)->delete(['seed' => $seed]);
    }
}
