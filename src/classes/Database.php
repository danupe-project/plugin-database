<?php

namespace Danupe\Plugin\Database\Classes;

use PDO;
use PDOStatement;

class Database
{
    private PDO $pdo;
    private string $table;
    private array $queryConditions = [];
    private array $rawConditions = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $orderByConditions = [];

    public string $databaseName = '';

    public function __construct(string $table = '')
    {
        if ($table) {
            $this->table($table);
        }
        $type = danupe()->config()->get('plugin-database.type', 'mysql');
        $config = danupe()->config()->get("plugin-database.$type");
        $this->databaseName = $config['database'];
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

    public function where(array $conditions): static
    {
        if (count($conditions) === 1) {
            $this->queryConditions[key($conditions)] = $conditions[key($conditions)];
        } elseif (count($conditions) === 2) {
            $this->queryConditions[$conditions[0]] = $conditions[1];
        } elseif (count($conditions) === 3) {
            $this->queryConditions[$conditions[0]] = $conditions[2];
        } else {
            throw new \InvalidArgumentException('Invalid number of arguments for where clause.');
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
                throw new \InvalidArgumentException("Invalid sort direction for column '$column'. Use 'ASC' or 'DESC'.");
            }
            $this->orderByConditions[] = "$column $direction";
        }
        return $this;
    }

    public function all(array $fields=[]): array
    {
        $sql = $this->buildQuery($fields);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->queryConditions);

        return $stmt->fetchAll();
    }

    public function first(array $fields=[]): ?array
    {
        $sql = $this->buildQuery($fields) . " LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->queryConditions);

        $result = $stmt->fetch();

        return $result === false ? null : $result;  // Gibt null zurück, wenn kein Ergebnis gefunden wurde
    }

    public function last(): ?array
    {
        $sql = $this->buildQuery() . " ORDER BY id DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->queryConditions);

        $result = $stmt->fetch();

        return $result === false ? null : $result;  // Gibt null zurück, wenn kein Ergebnis gefunden wurde
    }

    public function get(): array
    {
        $sql = $this->buildQuery();
        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit;
        }

        if ($this->offset) {
            $sql .= " OFFSET " . $this->offset;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->queryConditions);

        return $stmt->fetchAll();
    }

    private function buildQuery(array $fields=[]): string
    {
        if(empty($fields)) {
            $fields = ['*'];
        }
        $fields = implode(", ", $fields);
        $sql = "SELECT $fields FROM {$this->table}";
        $conditions = [];
        if ($this->queryConditions) {
            $conditions[] = implode(" AND ", array_map(fn($key) => "$key = :$key", array_keys($this->queryConditions)));
        }
        if ($this->rawConditions) {
            $conditions[] = implode(" AND ", $this->rawConditions);
        }
        if ($conditions) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit;
        }
        if ($this->offset) {
            $sql .= " OFFSET " . $this->offset;
        }

        if ($this->orderByConditions) {
            $sql .= " ORDER BY " . implode(", ", $this->orderByConditions);
        }

        return $sql;
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
        if ($this->queryConditions) {
            $sql .= " WHERE " . implode(" AND ", array_map(fn($key) => "$key = :$key", array_keys($this->queryConditions)));
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->queryConditions);

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

        if($params){
            $this->queryConditions = array_merge($this->queryConditions, $params);
        }



        return $this;
    }

    public function getDatabaseName(): string
    {
        return $this->databaseName;
    }




}
