<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB;

if (!defined('ABSPATH')) { exit; }


use mysqli_result;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Traits\SingletonTrait;
use Exception;

/**
 * Database Manager for RankingCoach
 * 
 * This class serves as a facade for both the Database and DatabaseTablesManager classes,
 * providing a unified interface for database operations.
 */
class DatabaseManager
{
    use SingletonTrait;
    use RcLoggerTrait;

    /**
     * @var string Database version
     */
    private const BEYONDSEO_DB_VERSION = '1.0.4';

    /**
     * @var Database The Database instance
     */
    private Database $db;

    /**
     * @var DatabaseTablesManager The DatabaseTablesManager instance
     */
    private DatabaseTablesManager $tablesManager;

    /**
     * Initialize the DatabaseManager
     * 
     * This method initializes the Database and DatabaseTablesManager instances.
     * The DatabaseTablesManager is created with a reference to this DatabaseManager
     * to avoid circular dependencies.
     * 
     * @return void
     */
    protected function __construct() {
        $this->db = Database::getInstance();
        $this->tablesManager = new DatabaseTablesManager($this);
    }

    /**
     * Get the Database version
     *
     * @return string
     */
    public function getDbVersion(): string
    {
        return self::BEYONDSEO_DB_VERSION;
    }

    /**
     * Get the Database instance
     *
     * @return Database
     */
    public function db(): Database
    {
        return $this->db;
    }

    /**
     * Get the DatabaseTablesManager instance
     *
     * @return DatabaseTablesManager
     */
    public function tables(): DatabaseTablesManager
    {
        return $this->tablesManager;
    }

    /**
     * Create a new query builder for a table
     *
     * @param string $table The table name
     * @return Database
     */
    public function table(string $table, ?string $alias = null): Database
    {
        return $this->db->table($table, $alias);
    }

    /**
     * Create all database tables
     *
     * @return void
     * @throws Exception
     */
    public function createAllTables(): void
    {
        // Log the table creation process
        $this->log('Starting creation of all database tables.');
        $this->tablesManager->createAllTables();
    }



    /**
     * Get the prefixed table name
     *
     * @param string $tableName The table name without prefix
     * @return string The table name with prefix
     */
    public function prefixTable(string $tableName): string
    {
        return $this->db->prefixTable($tableName);
    }
    
    /**
     * Check if a table exists
     *
     * @param string $tableName The table name (with or without prefix)
     * @return bool
     */
    public function tableExists(string $tableName): bool
    {
        $prefixedTable = $this->prefixTable($tableName);
        
        // Check if table exists using direct query
        return $this->db->get_var("SHOW TABLES LIKE '$prefixedTable'") === $prefixedTable;
    }

    /**
     * Remove all database tables
     *
     * @return void
     * @throws Exception
     */
    public function removeAllTables(): void
    {
        $this->log('Starting removal of all database tables.');
        $this->tablesManager->removeAllTables();
    }

    /**
     * Run all pending migrations
     *
     * @return array Array of executed migrations with status
     * @throws Exception
     */
    public function runMigrations(): array
    {
        return $this->tablesManager->runMigrations();
    }

    /**
     * Rollback the last batch of migrations
     *
     * @return array Array of rolled back migrations with status
     */
    public function rollbackMigrations(): array
    {
        return $this->tablesManager->rollbackMigrations();
    }

    /**
     * Get migration status summary
     *
     * @return array Migration status summary
     */
    public function getMigrationStatus(): array
    {
        return $this->tablesManager->getMigrationStatus();
    }

    /**
     * Get all available migrations
     *
     * @return array Array of migration file paths
     */
    public function getAvailableMigrations(): array
    {
        return $this->tablesManager->getAvailableMigrations();
    }

    /**
     * Get all applied migrations
     *
     * @return array Array of applied migration records
     */
    public function getAppliedMigrations(): array
    {
        return $this->tablesManager->getAppliedMigrations();
    }

    /**
     * Get pending migrations that haven't been applied yet
     *
     * @return array Array of pending migration file paths
     */
    public function getPendingMigrations(): array
    {
        return $this->tablesManager->getPendingMigrations();
    }

    /**
     * Insert data into a table
     *
     * @param string $table The table name
     * @param array $data The data to insert
     * @return int|false The inserted ID or false on failure
     */
    public function insert(string $table, array $data): bool|int
    {
        return $this->table($table)
            ->insert()
            ->set($data)
            ->get();
    }

    /**
     * Insert data into a table, ignoring duplicate entries
     *
     * @param string $table The table name
     * @param array $data The data to insert
     * @return int|false The inserted ID or false on failure
     */
    public function insertIgnore(string $table, array $data): bool|int
    {
        return $this->table($table)
            ->insert()
            ->ignore()
            ->set($data)
            ->get();
    }

    /**
     * Insert data into a table with ON DUPLICATE KEY UPDATE
     *
     * @param string $table The table name
     * @param array $data The data to insert
     * @param array $updateData The data to update on duplicate key
     * @return int|false The inserted ID or false on failure
     */
    public function insertOrUpdate(string $table, array $data, array $updateData = []): bool|int
    {
        $query = $this->table($table)
            ->insert()
            ->set($data);
            
        if (!empty($updateData)) {
            $query->onDuplicate($updateData);
        } else {
            $query->onDuplicate($data);
        }
        
        return $query->get();
    }

    /**
     * Update data in a table
     *
     * @param string $table The table name
     * @param array $data The data to update
     * @param array $where The where conditions
     * @return int|false The number of rows affected or false on failure
     */
    public function update(string $table, array $data, array $where): bool|int
    {
        $query = $this->table($table)
            ->update()
            ->set($data);
            
        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }
        
        return $query->get();
    }

    /**
     * Delete data from a table
     *
     * @param string $table The table name
     * @param array $where The where conditions
     * @return int|false The number of rows affected or false on failure
     */
    public function delete(string $table, array $where): bool|int
    {
        $query = $this->table($table)
            ->delete();
            
        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }
        
        return $query->get();
    }

    /**
     * Delete WordPress options matching a prefix pattern, optionally excluding specific options
     *
     * @param string $optionPrefix The option name prefix to match (will add % for LIKE)
     * @param array $excludeOptions Option names to exclude from deletion
     * @return int|false Number of rows deleted or false on failure
     */
    public function deleteOptionsByPrefix(string $optionPrefix, array $excludeOptions = []): int|false
    {
        $likePattern = $optionPrefix . '%';
        $optionsTable = $this->db->prefixTable('options');
        
        $sql = "DELETE FROM {$optionsTable} WHERE option_name LIKE %s";
        $params = [$likePattern];
        
        if (!empty($excludeOptions)) {
            $placeholders = implode(', ', array_fill(0, count($excludeOptions), '%s'));
            $sql .= " AND option_name NOT IN ({$placeholders})";
            $params = array_merge($params, $excludeOptions);
        }
        
        $preparedSql = $this->db->db->prepare($sql, ...$params);
        return $this->db->db->query($preparedSql);
    }

    /**
     * Truncate a table
     *
     * @param string $table The table name
     * @return bool Success status
     */
    public function truncate(string $table): bool
    {
        return $this->table($table)
            ->truncate()
            ->get();
    }

    /**
     * Truncate all plugin tables managed by DatabaseTablesManager
     *
     * Temporarily disables foreign key checks to avoid constraint issues.
     *
     * @return void
     */
    public function truncateAllTables(): void
    {
        try {
            $tables = $this->tablesManager->getAllPluginTables();
            if (empty($tables)) {
                return;
            }

            // Disable foreign key checks
            $this->queryRaw('SET FOREIGN_KEY_CHECKS = 0');

            foreach ($tables as $table) {
                try {
                    // Ignore failures for individual tables, continue with others
                    $this->truncate($table);
                } catch (\Throwable $e) {
                    $this->log('Error truncating table ' . $table . ': ' . $e->getMessage());
                }
            }
        } catch (Exception $e) {
            $this->log('Error truncating tables: ' . $e->getMessage());
        } finally {
            // Always re-enable foreign key checks
            $this->queryRaw('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /**
     * Get all rows from a table
     *
     * @param string $table The table name
     * @param array $columns The columns to select
     * @param array $where The where conditions
     * @param string $orderBy The column to order by
     * @param string $orderDir The order direction
     * @param int|null $limit The limit
     * @param int|null $offset The offset
     * @return array The rows
     */
    public function getAll(
        string $table, 
        array $columns = ['*'], 
        array $where = [], 
        string $orderBy = 'id', 
        string $orderDir = 'ASC', 
        ?int $limit = null, 
        ?int $offset = null
    ): array {
        $query = $this->table($table)
            ->select($columns);
            
        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }
        
        $query->orderBy($orderBy, $orderDir);
        
        if ($limit !== null) {
            $query->limit($limit, $offset);
        }
        
        return $query->get() ?: [];
    }

    /**
     * Get a single row from a table
     *
     * @param string $table The table name
     * @param array $columns The columns to select
     * @param array $where The where conditions
     * @return object|null The row or null if not found
     */
    public function getRow(string $table, array $columns = ['*'], array $where = [])
    {
        $query = $this->table($table)
            ->select($columns);
            
        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }
        
        return $query->first();
    }

    /**
     * Get a single column value from a table
     *
     * @param string $table The table name
     * @param string $column The column to select
     * @param array $where The where conditions
     * @return mixed The column value or null if not found
     */
    public function getValue(string $table, string $column, array $where = []): mixed
    {
        $query = $this->table($table)
            ->select($column);
            
        foreach ($where as $col => $value) {
            $query->where($col, $value);
        }
        
        return $query->value($column);
    }

    /**
     * Count rows in a table
     *
     * @param string $table The table name
     * @param array $where The where conditions
     * @return int The count
     */
    public function count(string $table, array $where = []): int
    {
        $query = $this->table($table);
            
        foreach ($where as $column => $value) {
            $query->where($column, $value);
        }
        
        return $query->count();
    }

    /**
     * Execute a raw SQL query
     *
     * @param string $sql The SQL query
     * @param string $output The output format
     * @return mixed The query results
     */
    public function queryRaw(string $sql, string $output = 'OBJECT'): mixed
    {
        return $this->db->queryRaw($sql, $output);
    }

    /**
     * Begin a database transaction
     *
     * @return int|bool|null|mysqli_result Success status
     */
    public function beginTransaction(): int|bool|null|mysqli_result
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit a database transaction
     *
     * @return int|bool|null|mysqli_result Success status
     */
    public function commit(): int|bool|null|mysqli_result
    {
        return $this->db->commit();
    }

    /**
     * Rollback a database transaction
     *
     * @return int|bool|null|mysqli_result Success status
     */
    public function rollback(): int|bool|null|mysqli_result
    {
        return $this->db->rollback();
    }

    /**
     * Delete old entries from any table using the builder pattern based on a date column
     * 
     * This method provides a generic fluent interface for deleting old entries from any table
     * based on a date column and retention period.
     *
     * @param string $tableName The name of the table to clean up
     * @param string $dateColumn The name of the date column to use for comparison
     * @param int $retentionDays Number of days to keep entries (entries older than this will be deleted)
     * @return int|false The number of rows deleted or false on failure
     */
    public function deleteOldEntriesByDate(string $tableName, string $dateColumn, int $retentionDays): int|false
    {
        try {
            // Calculate the cutoff date
            $cutoffDate = gmdate('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
            
            // Use the builder pattern to construct and execute the delete query
            return $this->table($tableName)
                ->delete()
                ->where($dateColumn, $cutoffDate, '<')
                ->get();
        } catch (\Throwable $e) {
            // Log any errors that occur during the deletion process
            $this->log_json([
                'operation_type' => 'table_cleanup',
                'operation_status' => 'error',
                'context_entity' => 'database_manager',
                'context_type' => 'cleanup',
                'table_name' => $tableName,
                'date_column' => $dateColumn,
                'retention_days' => $retentionDays,
                'error_details' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'timestamp' => current_time('mysql')
            ]);
            
            return false;
        }
    }

    /**
     * Execute dbDelta function for creating or updating database tables
     *
     * This method ensures the WordPress dbDelta function is available
     * and properly loaded before executing it.
     *
     * @param string $sql The SQL query to execute
     * @return array Results of the dbDelta operation
     */
    public function dbDelta(string $sql): array
    {
        // Make sure the WordPress dbDelta function is available
        // We dont remove this guard because we dont have any replacement functionality for that
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }

        // Execute dbDelta with the provided SQL
        return dbDelta($sql);
    }

    /**
     * Drop one or multiple database tables
     *
     * This method disables foreign key checks before dropping tables and
     * re-enables them afterward to avoid constraint errors.
     *
     * @param string|array $tables Table name(s) to drop (with or without prefix)
     * @return bool Success status
     * @throws Exception If an error occurs during the operation
     */
    public function dropTables(string|array $tables): bool
    {
        try {
            // Convert single table to array for consistent processing
            if (is_string($tables)) {
                $tables = [$tables];
            }

            // Ensure we have tables to drop
            if (empty($tables)) {
                return false;
            }

            // Prefix all table names
            $prefixedTables = [];
            foreach ($tables as $table) {
                $prefixedTable = $this->prefixTable($table);
                
                // Verify table exists before attempting to drop
                if ($this->tableExists($table)) {
                    $prefixedTables[] = $prefixedTable;
                }
            }

            // If no valid tables to drop, return early
            if (empty($prefixedTables)) {
                return false;
            }

            // Disable foreign key checks
            $this->queryRaw('SET FOREIGN_KEY_CHECKS = 0');

            // Drop each table
            $success = true;
            foreach ($prefixedTables as $table) {
                $result = $this->queryRaw("DROP TABLE IF EXISTS `{$table}`");
                if ($result === false) {
                    $success = false;
                }
            }

            return $success;
        } catch (Exception $e) {
            $this->log('Error dropping tables: ' . $e->getMessage());
            throw $e;
        } finally {
            // Always re-enable foreign key checks, even if an exception occurs
            $this->queryRaw('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
