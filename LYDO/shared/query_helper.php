<?php
/**
 * Query Helper for PostgreSQL Compatibility
 * Automatically handles INSERT queries with RETURNING clause
 */

/**
 * Execute INSERT query and return the inserted ID
 * Works with both MySQL and PostgreSQL
 * 
 * Usage:
 *   $userId = insertAndGetId($pdo, 'INSERT INTO users (...) VALUES (...)', [...]);
 * 
 * @param PDO $pdo
 * @param string $sql
 * @param array $params
 * @return int The inserted ID
 */
function insertAndGetId(PDO $pdo, string $sql, array $params = []): int {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    if ($driver === 'pgsql') {
        // PostgreSQL: Add RETURNING id if not present
        if (stripos($sql, 'RETURNING') === false) {
            $sql = rtrim($sql, ';') . ' RETURNING id';
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($result['id'] ?? 0);
    } else {
        // MySQL: Use lastInsertId()
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $pdo->lastInsertId();
    }
}

/**
 * Get last insert ID (backwards compatible)
 * For PostgreSQL, this should be used only when the INSERT already included RETURNING
 * 
 * @param PDO $pdo
 * @return int
 */
function getLastId(PDO $pdo): int {
    return (int) $pdo->lastInsertId();
}

/**
 * Convert MySQL LIMIT syntax to PostgreSQL
 * MySQL: LIMIT offset, count
 * PostgreSQL: LIMIT count OFFSET offset
 * 
 * @param int $count
 * @param int $offset
 * @return string
 */
function limitQuery(int $count, int $offset = 0): string {
    global $pdo;
    $driver = $pdo ? $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) : 'mysql';
    
    if ($driver === 'pgsql') {
        $sql = "LIMIT $count";
        if ($offset > 0) {
            $sql .= " OFFSET $offset";
        }
        return $sql;
    } else {
        if ($offset > 0) {
            return "LIMIT $offset, $count";
        }
        return "LIMIT $count";
    }
}

/**
 * Convert boolean value for database
 * PostgreSQL: TRUE/FALSE
 * MySQL: 1/0
 * 
 * @param mixed $value
 * @return string|int
 */
function dbBool($value) {
    global $pdo;
    $driver = $pdo ? $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) : 'mysql';
    
    if ($driver === 'pgsql') {
        return $value ? 'TRUE' : 'FALSE';
    } else {
        return $value ? 1 : 0;
    }
}

/**
 * Execute a query that may need PostgreSQL conversion
 * Automatically handles INSERT with RETURNING
 * 
 * @param PDO $pdo
 * @param string $sql
 * @param array $params
 * @param bool $returnInsertId If true and it's an INSERT, return the ID
 * @return PDOStatement|int
 */
function executeQuery(PDO $pdo, string $sql, array $params = [], bool $returnInsertId = false) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isInsert = stripos(trim($sql), 'INSERT') === 0;
    
    if ($driver === 'pgsql' && $isInsert && $returnInsertId) {
        return insertAndGetId($pdo, $sql, $params);
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt;
}

/**
 * Check if using PostgreSQL
 * 
 * @param PDO $pdo
 * @return bool
 */
function isPostgreSQL(PDO $pdo): bool {
    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
}

/**
 * Check if using MySQL
 * 
 * @param PDO $pdo
 * @return bool
 */
function isMySQL(PDO $pdo): bool {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    return in_array($driver, ['mysql', 'mysqli']);
}

/**
 * Convert date/time functions
 * MySQL: NOW()
 * PostgreSQL: CURRENT_TIMESTAMP or NOW()
 * 
 * @return string
 */
function dbNow(): string {
    return 'CURRENT_TIMESTAMP';
}

/**
 * Safe LIKE escape
 * 
 * @param string $string
 * @param PDO $pdo
 * @return string
 */
function escapeLike(string $string, PDO $pdo): string {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    if ($driver === 'pgsql') {
        return addcslashes($string, '%_\\');
    } else {
        return addcslashes($string, '%_');
    }
}
