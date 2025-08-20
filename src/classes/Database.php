<?php

namespace Danupe\Plugin\Database\Classes;

use Danupe\Core\Classes\Language;
use PDO;
use PDOStatement;

class Database
{
    private PDO $pdo;
    private string $table;
    private array $queryConditions = []; // key => value for simple where
    private array $rawConditions = [];   // raw SQL condition strings
    private array $rawParams = [];       // named parameters for rawConditions
    private ?int $limit = null;
    private ?int $offset = null;
    private array $orderByConditions = [];
    private Language $language;

    public string $databaseName = '';

    public function __construct(string $table = '', ?Language $language = null)
    {
        $this->language = $language ?? new Language();

        if ($table) {
            $this->table($table);
        }

        $config = $this->getDatabaseConfig();
        $this->databaseName = $config['database'];
        $dsn = "{$config['type']}:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }

    private function getDatabaseConfig(): array
    {
        $type = danupe()->config()->get('plugin-database.type', 'mysql');
        return danupe()->config()->get("plugin-database.$type");
    }

    public function table(string $table): static
    {
        $this->resetQuery();
        $this->table = $table;
        return $this;
    }

    private function resetQuery(): void
    {
        $this->queryConditions = [];
        $this->rawConditions = [];
    $this->limit = null;
    $this->offset = null;
    $this->orderByConditions = [];
    $this->rawParams = [];
    }

    public function where(array $conditions): static
    {
        $count = count($conditions);

        if ($count === 1) {
            $this->queryConditions[key($conditions)] = current($conditions);
        } elseif ($count === 2) {
            [$column, $value] = $conditions;
            $this->queryConditions[$column] = $value;
        } elseif ($count === 3) {
            [$column, , $value] = $conditions;
            $this->queryConditions[$column] = $value;
        } else {
            throw new \InvalidArgumentException(
                $this->language->get('database.errors.invalid_where_clause')
            );
        }

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    public function orderBy(array $conditions): static
    {
        foreach ($conditions as $column => $direction) {
            $direction = strtoupper($direction);
            if (!in_array($direction, ['ASC', 'DESC'])) {
                throw new \InvalidArgumentException(
                    $this->language->get('database.errors.invalid_sort_direction', ['column' => $column])
                );
            }
            $this->orderByConditions[] = $this->quoteIdentifier($column) . " $direction";
        }
        return $this;
    }

    public function all(array $fields = []): array
    {
        return $this->executeQuery($this->buildQuery($fields))->fetchAll();
    }

    public function first(array $fields = []): ?array
    {
        $result = $this->executeQuery($this->buildQuery($fields) . " LIMIT 1")->fetch();
        return $result ?: null;
    }

    public function last(): ?array
    {
        $sql = $this->buildQuery() . " ORDER BY id DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->queryConditions);

        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    public function get(): array
    {
        $sql = $this->buildQuery();
        if ($this->limit) {
            $sql .= " LIMIT {$this->limit}";
        }
        if ($this->offset) {
            $sql .= " OFFSET {$this->offset}";
        }
        return $this->executeQuery($sql)->fetchAll();
    }

    private function buildQuery(array $fields = []): string
    {
        $fields = empty($fields) ? '*' : implode(", ", $fields);
        $sql = "SELECT $fields FROM {$this->table}";

        $conditions = $this->buildConditions();
        if ($conditions) {
            $sql .= " WHERE $conditions";
        }

        if ($this->orderByConditions) {
            $sql .= " ORDER BY " . implode(", ", $this->orderByConditions);
        }

        return $sql;
    }

    private function buildConditions(): string
    {
        $conditions = [];
        if ($this->queryConditions) {
            $conditions[] = implode(" AND ", array_map(fn($key) => $this->quoteIdentifier($key) . " = :$key", array_keys($this->queryConditions)));
        }
        if ($this->rawConditions) {
            $conditions[] = implode(" AND ", $this->rawConditions);
        }
        return implode(" AND ", $conditions);
    }

    private function executeQuery(string $sql): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $params = array_merge($this->queryConditions, $this->rawParams);
        $stmt->execute($params);
        return $stmt;
    }

    public function create(array $data): bool
    {
        $keys = array_keys($data);
        $sql = "INSERT INTO {$this->table} (" . implode(", ", $keys) . ") VALUES (:" . implode(", :", $keys) . ")";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($data);
    }

    public function raw(string $sql, array $params = []): PDOStatement|bool
    {
        $stmt = $this->pdo->prepare($sql);
        $executeResult = $stmt->execute($params);

        return $executeResult ? $stmt : false;
    }

    public function update(array $data, array $conditions): bool
    {
        $set = implode(", ", array_map(fn($key) => "`$key` = :$key", array_keys($data)));
        $where = implode(" AND ", array_map(fn($key) => "`$key` = :$key", array_keys($conditions)));
        $sql = "UPDATE `{$this->table}` SET $set WHERE $where";

        $stmt = $this->pdo->prepare($sql);
        $params = array_merge($data, $conditions);
        return $stmt->execute($params);
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
        $sql = "SELECT * FROM $table WHERE $foreignKey = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll();
    }

    public function attach(string $pivotTable, array $data): bool
    {
        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), ":value"));
        $sql = "INSERT INTO $pivotTable ($columns) VALUES ($placeholders)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    public function getManyToMany(string $pivotTable, string $foreignKey, mixed $foreignValue, string $relatedTable, string $relatedKey): array
    {
        $sql = "SELECT $relatedTable.* FROM $relatedTable 
                JOIN $pivotTable ON $pivotTable.$relatedKey = $relatedTable.id 
                WHERE $pivotTable.$foreignKey = :foreignValue";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['foreignValue' => $foreignValue]);

        return $stmt->fetchAll();
    }

    public function detach(string $pivotTable, array $where): bool
    {
        $conditions = implode(" AND ", array_map(fn($col) => "$col = :$col", array_keys($where)));
        $sql = "DELETE FROM $pivotTable WHERE $conditions";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($where);
    }

    public function exists(): bool
    {
        $sql = $this->buildQuery() . " LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->queryConditions);

        return $stmt->fetch() !== false;
    }

    public function insert(array $data): bool
    {
        $columns = implode(", ", array_map(fn($column) => "`$column`", array_keys($data)));
        $placeholders = implode(", ", array_map(fn($key) => ":$key", array_keys($data)));
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    public function toSql(): string
    {
        $sql = $this->buildQuery();
        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit;
        }

        if ($this->offset) {
            $sql .= " OFFSET " . $this->offset;
        }

        foreach ($this->queryConditions as $key => $value) {
            $sql = str_replace(":$key", is_string($value) ? "'$value'" : $value, $sql);
        }

        return $sql;
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $parts = [];
        if ($this->queryConditions) {
            $parts[] = implode(" AND ", array_map(fn($key) => $this->quoteIdentifier($key) . " = :$key", array_keys($this->queryConditions)));
        }
        if ($this->rawConditions) {
            $parts[] = implode(' AND ', $this->rawConditions);
        }
        if ($parts) {
            $sql .= ' WHERE ' . implode(' AND ', $parts);
        }
        $stmt = $this->pdo->prepare($sql);
        $params = array_merge($this->queryConditions, $this->rawParams);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function getLastInsertId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }


    public function dropAllTables(): void
    {
        $sql = "SHOW TABLES";
        $stmt = $this->pdo->query($sql);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
        }
    }

    public function random(array $fields = [], int $count = 1): null|string|array
    {
        $sql = $this->buildQuery($fields) . " ORDER BY RAND() LIMIT $count";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->queryConditions);

        if($count === 1) {
            return danupe()->data()->get($stmt->fetch(),'text');
        }else{
            return $stmt->fetchAll();
        }
    }


    public function whereRaw(string $sql, array $params = []): static
    {
        $this->rawConditions[] = $sql;
        if ($params) {
            $this->rawParams = array_merge($this->rawParams, $params);
        }
        return $this;
    }

    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }

    private function quoteIdentifier(string $identifier): string
    {
        // very basic whitelist: allow letters, numbers, underscore
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new \InvalidArgumentException('Invalid identifier: ' . $identifier);
        }
        return "`" . $identifier . "`";
    }



}
