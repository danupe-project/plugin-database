<?php

namespace Danupe\Plugin\Database\Classes;

use PDO;

class Database
{
    private PDO $pdo;
    private string $table;

    public function __construct()
    {
        $type = config('plugin-database.type', 'mysql');
        $config = config("plugin-database.$type");
        $dsn = "{$type}:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }

    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    public function all(): array
    {
        return $this->pdo->query("SELECT * FROM {$this->table}")->fetchAll();
    }

    public function first(array $conditions): ?array
    {
        return $this->get($conditions, 1)[0] ?? null;
    }

    public function get(array $conditions = [], ?int $limit = null): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($conditions) {
            $sql .= " WHERE " . implode(" AND ", array_map(fn($key) => "$key = :$key", array_keys($conditions)));
        }
        if ($limit) {
            $sql .= " LIMIT $limit";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($conditions);
        
        return $stmt->fetchAll();
    }

    public function create(array $data): bool
    {
        $keys = array_keys($data);
        $sql = "INSERT INTO {$this->table} (" . implode(", ", $keys) . ") VALUES (:" . implode(", :", $keys) . ")";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($data);
    }

    public function update(array $data, array $conditions): bool
    {
        $set = implode(", ", array_map(fn($key) => "$key = :$key", array_keys($data)));
        $where = implode(" AND ", array_map(fn($key) => "$key = :cond_$key", array_keys($conditions)));
        $sql = "UPDATE {$this->table} SET $set WHERE $where";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($conditions as $key => $value) {
            $data["cond_$key"] = $value;
        }
        
        return $stmt->execute($data);
    }

    public function delete(array $conditions): bool
    {
        $where = implode(" AND ", array_map(fn($key) => "$key = :$key", array_keys($conditions)));
        $sql = "DELETE FROM {$this->table} WHERE $where";
        
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($conditions);
    }

    public function hasMany(string $table, string $foreignKey, mixed $id): array
    {
        $sql = "SELECT * FROM $table WHERE $foreignKey = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetchAll();
    }

    public function attach(string $pivotTable, array $data): bool
    {
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $sql = "INSERT INTO $pivotTable ($columns) VALUES ($placeholders)";
        
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute(array_values($data));
    }

    public function getManyToMany(string $pivotTable, string $foreignKey, mixed $foreignValue, string $relatedTable, string $relatedKey): array
    {
        $sql = "SELECT $relatedTable.* FROM $relatedTable 
                JOIN $pivotTable ON $pivotTable.$relatedKey = $relatedTable.id 
                WHERE $pivotTable.$foreignKey = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$foreignValue]);
        
        return $stmt->fetchAll();
    }

    public function detach(string $pivotTable, array $where): bool
    {
        $conditions = implode(" AND ", array_map(fn($col) => "$col = ?", array_keys($where)));
        $sql = "DELETE FROM $pivotTable WHERE $conditions";
        
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute(array_values($where));
    }
}
