<?php
/**
 * Database Adapter for MySQL to PostgreSQL Migration
 * 
 * This class provides a compatibility layer to handle differences
 * between MySQL and PostgreSQL, allowing the existing codebase to
 * work with minimal changes.
 */

class DatabaseAdapter {
    private PDO $pdo;
    private string $driver;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
    
    /**
     * Check if using PostgreSQL
     */
    public function isPostgreSQL(): bool {
        return $this->driver === 'pgsql';
    }
    
    /**
     * Check if using MySQL
     */
    public function isMySQL(): bool {
        return in_array($this->driver, ['mysql', 'mysqli']);
    }
    
    /**
     * Execute INSERT and return the inserted ID
     * Handles PostgreSQL RETURNING clause automatically
     */
    public function insert(string $sql, array $params = []): int {
        if ($this->isPostgreSQL() && stripos($sql, 'RETURNING') === false) {
            // Add RETURNING id for PostgreSQL
            $sql = rtrim($sql, ';') . ' RETURNING id';
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($this->isPostgreSQL()) {
            // For PostgreSQL with RETURNING, fetch the returned ID
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($result['id'] ?? 0);
        } else {
            // For MySQL, use lastInsertId
            return (int) $this->pdo->lastInsertId();
        }
    }
    
    /**
     * Execute SELECT query and return all rows
     */
    public function select(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Execute SELECT and return single row
     */
    public function selectOne(string $sql, array $params = []): ?array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Execute UPDATE/DELETE and return affected rows
     */
    public function execute(string $sql, array $params = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction(): bool {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit(): bool {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback(): bool {
        return $this->pdo->rollBack();
    }
    
    /**
     * Convert LIMIT syntax
     * MySQL: LIMIT offset, count
     * PostgreSQL: LIMIT count OFFSET offset
     */
    public function limit(int $count, int $offset = 0): string {
        if ($this->isPostgreSQL()) {
            $sql = "LIMIT $count";
            if ($offset > 0) $sql .= " OFFSET $offset";
            return $sql;
        } else {
            if ($offset > 0) {
                return "LIMIT $offset, $count";
            }
            return "LIMIT $count";
        }
    }
    
    /**
     * Convert boolean value
     * MySQL: 1/0
     * PostgreSQL: TRUE/FALSE or 't'/'f'
     */
    public function bool($value): mixed {
        if ($this->isPostgreSQL()) {
            return $value ? 'TRUE' : 'FALSE';
        } else {
            return $value ? 1 : 0;
        }
    }
    
    /**
     * Get current timestamp expression
     * MySQL: NOW() or CURRENT_TIMESTAMP
     * PostgreSQL: CURRENT_TIMESTAMP or NOW()
     */
    public function now(): string {
        return 'CURRENT_TIMESTAMP';
    }
    
    /**
     * Convert LIKE pattern safely
     */
    public function likEscape(string $pattern): string {
        if ($this->isPostgreSQL()) {
            return addcslashes($pattern, '%_\\');
        } else {
            return addcslashes($pattern, '%_');
        }
    }
    
    /**
     * Quote identifier (table/column name)
     * MySQL: `table`
     * PostgreSQL: "table"
     */
    public function quoteIdentifier(string $identifier): string {
        if ($this->isPostgreSQL()) {
            return '"' . str_replace('"', '""', $identifier) . '"';
        } else {
            return '`' . str_replace('`', '``', $identifier) . '`';
        }
    }
    
    /**
     * Get date format string for queries
     * MySQL: %Y-%m-%d
     * PostgreSQL: YYYY-MM-DD
     */
    public function dateFormat(string $column, string $format = 'Y-m-d'): string {
        if ($this->isPostgreSQL()) {
            // PostgreSQL uses TO_CHAR
            $pgFormat = str_replace(
                ['Y', 'm', 'd', 'H', 'i', 's'],
                ['YYYY', 'MM', 'DD', 'HH24', 'MI', 'SS'],
                $format
            );
            return "TO_CHAR($column, '$pgFormat')";
        } else {
            // MySQL uses DATE_FORMAT
            $mysqlFormat = str_replace(
                ['Y', 'm', 'd', 'H', 'i', 's'],
                ['%Y', '%m', '%d', '%H', '%i', '%s'],
                $format
            );
            return "DATE_FORMAT($column, '$mysqlFormat')";
        }
    }
    
    /**
     * Convert CONCAT syntax
     * MySQL: CONCAT(a, b, c)
     * PostgreSQL: a || b || c or CONCAT(a, b, c)
     */
    public function concat(array $parts): string {
        if ($this->isPostgreSQL()) {
            // PostgreSQL supports both, use CONCAT for compatibility
            return 'CONCAT(' . implode(', ', $parts) . ')';
        } else {
            return 'CONCAT(' . implode(', ', $parts) . ')';
        }
    }
    
    /**
     * Get database version info
     */
    public function version(): string {
        return $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    }
    
    /**
     * Check if table exists
     */
    public function tableExists(string $table): bool {
        if ($this->isPostgreSQL()) {
            $sql = "SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = ?
            )";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } else {
            $sql = "SHOW TABLES LIKE ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$table]);
            return $stmt->rowCount() > 0;
        }
    }
    
    /**
     * Get PDO instance
     */
    public function getPDO(): PDO {
        return $this->pdo;
    }
    
    /**
     * Prepare statement (proxy to PDO)
     */
    public function prepare(string $sql): PDOStatement {
        return $this->pdo->prepare($sql);
    }
    
    /**
     * Quote value (proxy to PDO)
     */
    public function quote(string $value): string {
        return $this->pdo->quote($value);
    }
}

/**
 * Global database adapter instance
 */
function dbAdapter(): DatabaseAdapter {
    static $adapter = null;
    if (!$adapter) {
        $adapter = new DatabaseAdapter(db());
    }
    return $adapter;
}
