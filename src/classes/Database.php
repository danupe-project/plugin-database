<?php

namespace Danupe\Plugin\Database\Classes;

use Danupe\Core\Classes\Language;
use PDO;
use PDOStatement;

class Database
{
    private PDO $pdo;
    private string $table;
    private array $columns = ['*'];
    private array $joins = [];
    private array $queryConditions = []; 
    private array $rawConditions = [];   
    private array $rawParams = [];       
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
        $this->joins = [];
        $this->columns = ['*'];
        $this->limit = null;
        $this->offset = null;
        $this->orderByConditions = [];
        $this->rawParams = [];
    }

    // --- QUERY BUILDER (SELECT & JOIN) ---

    public function select(array $columns): static
    {
        $this->columns = $columns;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): static
    {
        $this->joins[] = strtoupper($type) . " JOIN $table ON $first $operator $second";
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): static
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function where(array $conditions): static
    {
        if (array_keys($conditions) !== range(0, count($conditions) - 1)) {
            foreach ($conditions as $column => $value) {
                $this->queryConditions[$column] = $value;
            }
        } else {
            $count = count($conditions);
            if ($count === 2) {
                $this->queryConditions[$conditions[0]] = $conditions[1];
            } elseif ($count === 3) {
                [$column, $operator, $value] = $conditions;
                $paramName = str_replace('.', '_', $column) . '_' . count($this->rawParams);
                $this->whereRaw($this->quoteIdentifier($column) . " $operator :$paramName", [$paramName => $value]);
            }
        }
        return $this;
    }

    public function whereRaw(string $sql, array $params = []): static
    {
        $this->rawConditions[] = $sql;
        if ($params) {
            $this->rawParams = array_merge($this->rawParams, $params);
        }
        return $this;
    }

    // --- FETCH METHODS ---

    public function get(array $fields = []): array
    {
        $sql = $this->buildQuery($fields);
        if ($this->limit) $sql .= " LIMIT {$this->limit}";
        if ($this->offset) $sql .= " OFFSET {$this->offset}";
        return $this->executeQuery($sql)->fetchAll();
    }

    public function first(array $fields = []): ?array
    {
        $sql = $this->buildQuery($fields) . " LIMIT 1";
        $result = $this->executeQuery($sql)->fetch();
        return $result ?: null;
    }

    public function last(array $fields = []): ?array
    {
        $sql = $this->buildQuery($fields) . " ORDER BY id DESC LIMIT 1";
        $result = $this->executeQuery($sql)->fetch();
        return $result ?: null;
    }

    public function all(array $fields = []): array
    {
        return $this->get($fields);
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        if (!empty($this->joins)) $sql .= " " . implode(" ", $this->joins);
        $conditions = $this->buildConditions();
        if ($conditions) $sql .= " WHERE $conditions";
        
        return (int)$this->executeQuery($sql)->fetchColumn();
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    // --- WRITE METHODS ---

    public function insert(array $data): bool
    {
        $columns = implode(", ", array_map(fn($c) => $this->quoteIdentifier($c), array_keys($data)));
        $placeholders = implode(", ", array_map(fn($k) => ":$k", array_keys($data)));
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        return $this->pdo->prepare($sql)->execute($data);
    }

    public function create(array $data): bool
    {
        return $this->insert($data);
    }

    public function update(array $data, array $conditions): bool
    {
        $setParts = [];
        foreach ($data as $key => $val) $setParts[] = $this->quoteIdentifier($key) . " = :s_$key";
        
        $whereParts = [];
        $whereParams = [];
        foreach ($conditions as $key => $val) {
            $safeKey = str_replace('.', '_', $key);
            $whereParts[] = $this->quoteIdentifier($key) . " = :w_$safeKey";
            $whereParams["w_$safeKey"] = $val;
        }

        $sql = "UPDATE {$this->table} SET " . implode(", ", $setParts) . " WHERE " . implode(" AND ", $whereParts);
        
        $params = [];
        foreach ($data as $key => $val) $params["s_$key"] = $val;
        return $this->pdo->prepare($sql)->execute(array_merge($params, $whereParams));
    }

    public function delete(array $conditions): bool
    {
        $parts = [];
        $params = [];
        foreach ($conditions as $key => $val) {
            $safeKey = str_replace('.', '_', $key);
            $parts[] = $this->quoteIdentifier($key) . " = :$safeKey";
            $params[$safeKey] = $val;
        }
        $sql = "DELETE FROM {$this->table} WHERE " . implode(" AND ", $parts);
        return $this->pdo->prepare($sql)->execute($params);
    }

    // --- RELATIONSHIP METHODS (WICHTIG FÜR SEEDER) ---

    public function hasMany(string $table, string $foreignKey, mixed $id): array
    {
        return $this->table($table)->where([$foreignKey => $id])->get();
    }

    public function attach(string $pivotTable, array $data): bool
    {
        $columns = implode(", ", array_map(fn($c) => $this->quoteIdentifier($c), array_keys($data)));
        $placeholders = implode(", ", array_map(fn($k) => ":$k", array_keys($data)));
        $sql = "INSERT INTO $pivotTable ($columns) VALUES ($placeholders)";
        return $this->pdo->prepare($sql)->execute($data);
    }

    public function detach(string $pivotTable, array $where): bool
    {
        $parts = [];
        foreach ($where as $key => $val) $parts[] = $this->quoteIdentifier($key) . " = :$key";
        $sql = "DELETE FROM $pivotTable WHERE " . implode(" AND ", $parts);
        return $this->pdo->prepare($sql)->execute($where);
    }

    public function getManyToMany(string $pivotTable, string $foreignKey, mixed $foreignValue, string $relatedTable, string $relatedKey): array
    {
        $sql = "SELECT r.* FROM {$relatedTable} r 
                JOIN {$pivotTable} p ON p.{$relatedKey} = r.id 
                WHERE p.{$foreignKey} = :val";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['val' => $foreignValue]);
        return $stmt->fetchAll();
    }

    // --- UTILS & SYSTEM ---

    public function limit(int $limit): static { $this->limit = $limit; return $this; }
    public function offset(int $offset): static { $this->offset = $offset; return $this; }
    
    public function orderBy(array $conditions): static
    {
        foreach ($conditions as $col => $dir) {
            $this->orderByConditions[] = $this->quoteIdentifier($col) . " " . (strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC');
        }
        return $this;
    }

    public function raw(string $sql, array $params = []): PDOStatement|bool
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params) ? $stmt : false;
    }

    public function dropAllTables(): void
    {
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) $this->pdo->exec("DROP TABLE IF EXISTS `$table` ");
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    public function random(array $fields = [], int $count = 1): mixed
    {
        $sql = $this->buildQuery($fields) . " ORDER BY RAND() LIMIT $count";
        $stmt = $this->executeQuery($sql);
        return ($count === 1) ? $stmt->fetch() : $stmt->fetchAll();
    }

    public function toSql(): string
    {
        $sql = $this->buildQuery();
        foreach (array_merge($this->queryConditions, $this->rawParams) as $key => $val) {
            $sql = str_replace(":$key", is_string($val) ? "'$val'" : $val, $sql);
        }
        return $sql;
    }

    private function buildQuery(array $fields = []): string
    {
        $fields = (empty($fields) || $fields === ['*']) ? $this->columns : $fields;
        $fieldStrings = array_map(fn($f) => (strpos(strtoupper($f), ' AS ') !== false) ? $f : $this->quoteIdentifier($f), $fields);
        
        $sql = "SELECT " . implode(", ", $fieldStrings) . " FROM {$this->table}";
        if (!empty($this->joins)) $sql .= " " . implode(" ", $this->joins);
        $conditions = $this->buildConditions();
        if ($conditions) $sql .= " WHERE $conditions";
        if ($this->orderByConditions) $sql .= " ORDER BY " . implode(", ", $this->orderByConditions);
        
        return $sql;
    }

    private function buildConditions(): string
    {
        $parts = [];
        if ($this->queryConditions) {
            foreach ($this->queryConditions as $key => $value) {
                $placeholder = str_replace('.', '_', $key);
                $parts[] = $this->quoteIdentifier($key) . " = :$placeholder";
            }
        }
        if ($this->rawConditions) $parts[] = implode(" AND ", $this->rawConditions);
        return implode(" AND ", $parts);
    }

    private function executeQuery(string $sql): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $mappedParams = [];
        foreach ($this->queryConditions as $key => $value) {
            $mappedParams[str_replace('.', '_', $key)] = $value;
        }
        $stmt->execute(array_merge($mappedParams, $this->rawParams));
        return $stmt;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if ($identifier === '*' || strpos($identifier, '(') !== false) return $identifier;
        if (strpos($identifier, '.') !== false) {
            return implode('.', array_map([$this, 'quoteIdentifier'], explode('.', $identifier)));
        }
        return "`" . str_replace("`", "", $identifier) . "`";
    }

    public function getLastInsertId(): int { return (int)$this->pdo->lastInsertId(); }
    public function getDatabaseName(): string { return $this->databaseName; }
}