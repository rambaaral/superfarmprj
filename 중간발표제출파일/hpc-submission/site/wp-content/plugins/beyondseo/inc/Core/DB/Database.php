<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use mysqli_result;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Traits\SingletonTrait;
use wpdb;

/**
 * Database utility class for plugin.
 * Provides a fluent interface for building and executing database queries.
 */
class Database
{
    use SingletonTrait;
    use RcLoggerTrait;

    /**
     * Holds $wpdb instance.
     *
     * @var ?wpdb $db
     */
    public ?wpdb $db = null;

    /**
     * Holds $wpdb prefix.
     *
     * @var string
     */
    public string $prefix = '';

    /**
     * The database table in use by this query.
     *
     * @var string $table
     */
    private string $table = '';
    
    /**
     * The database table name without alias.
     * This is used for join conditions when the main table has an alias.
     *
     * @var string $tableName
     */
    private string $tableName = '';

    /**
     * The sql statement (SELECT, INSERT, UPDATE, DELETE, etc.).
     *
     * @var string $statement
     */
    private string $statement = '';

    /**
     * The limit clause for the SQL query.
     *
     * @var string|int $limit
     */
    private string|int $limit = '';

    /**
     * The group clause for the SQL query.
     *
     * @var array $group
     */
    private array $group = [];

    /**
     * The order by clause for the SQL query.
     *
     * @var array $order
     */
    private array $order = [];

    /**
     * The select clause for the SQL query.
     *
     * @var array $select
     */
    private array $select = [];

    /**
     * The set clause for the SQL query.
     *
     * @var array $set
     */
    private array $set = [];

    /**
     * Duplicate clause for the INSERT query.
     *
     * @var array $onDuplicate
     */
    private array $onDuplicate = [];

    /**
     * Ignore clause for the INSERT query.
     *
     * @var bool $ignore
     */
    private bool $ignore = false;

    /**
     * The where clause for the SQL query.
     *
     * @var array $where
     */
    private array $where = [];

    /**
     * The join clause for the SQL query.
     *
     * @var array $join
     */
    private array $join = [];

    /**
     * Determines whether the select statement should be distinct.
     *
     * @var bool $distinct
     */
    private bool $distinct = false;

    /**
     * The method in which $wpdb will output results.
     *
     * @var string $output
     */
    private string $output = 'OBJECT';

    /**
     * Whether or not to strip tags.
     *
     * @var bool $stripTags
     */
    private bool $stripTags = false;

    /**
     * Set which option to use to escape the SQL query.
     *
     * @var int $escapeOptions
     */
    protected int $escapeOptions = 0;

    /**
     * A cache of all queries and their results.
     *
     * @var array $cache
     */
    private array $cache = [];

    /**
     * Whether or not to reset the cached results.
     *
     * @var bool $shouldResetCache
     */
    private bool $shouldResetCache = false;

    /**
     * The last query that ran, stringified.
     *
     * @var string $lastQuery
     */
    public string $lastQuery = '';

    /**
     * Constant for escape options.
     *
     * @var int
     */
    public const ESCAPE_STRIP_HTML = 4;

    /**
     * Constant for escape options.
     *
     * @var int
     */
    public const ESCAPE_QUOTE = 8;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Initializes the DB class.
     * This needs to be called after the class is instantiated or when switching between sites in a multisite environment.
     *
     * @return void
     */
    public function init(): void
    {
        global $wpdb;
        $this->db = $wpdb;
        $this->prefix = $wpdb->prefix;
        $this->escapeOptions = self::ESCAPE_STRIP_HTML | self::ESCAPE_QUOTE;
    }

    /**
     * If this is a clone, lets reset all the data.
     */
    public function __clone()
    {
        $this->reset(['result']);
        $this->reset();
    }

    /**
     * Reset the query builder.
     *
     * @param array $exclusions Properties to exclude from reset
     * @return self
     */
    public function reset(array $exclusions = []): self
    {
        $properties = get_object_vars($this);
        
        foreach ($properties as $property => $value) {
            if (in_array($property, ['db', 'prefix', 'escapeOptions', 'cache', 'lastQuery', 'output']) || in_array($property, $exclusions, true)) {
                continue;
            }

            if (is_array($value)) {
                $this->$property = [];
            } elseif (is_bool($value)) {
                $this->$property = false;
            } elseif (is_int($value)) {
                $this->$property = 0;
            } elseif (is_string($value)) {
                $this->$property = '';
            } else {
                $this->$property = null;
            }
        }

        return $this;
    }

    /**
     * Check if a table exists.
     *
     * @param string $table The name of the table.
     * @return bool Whether the table exists.
     */
    public function tableExists(string $table): bool
    {
        $table = $this->prefixTable($table);
        $results = $this->db->get_results("SHOW TABLES LIKE '$table'");
        
        return !empty($results);
    }

    /**
     * Check if a column exists on a given table.
     *
     * @param string $table The name of the table.
     * @param string $column The name of the column.
     * @return bool Whether the column exists.
     */
    public function columnExists(string $table, string $column): bool
    {
        if (!$this->tableExists($table)) {
            return false;
        }

        $columns = $this->getColumns($table);
        return in_array($column, $columns, true);
    }

    /**
     * Gets all columns from a table.
     *
     * @param string $table The name of the table to lookup columns for.
     * @return array An array of columns.
     */
    public function getColumns(string $table): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        $table = $this->prefixTable($table);
        return $this->db->get_col("SHOW COLUMNS FROM `$table`");
    }

    /**
     * Gets the size of a table in bytes.
     *
     * @param string $table The table to check.
     * @return int The size of the table in bytes.
     */
    public function getTableSize(string $table): int
    {
        $prefixedTable = $this->prefixTable($table);
        $this->db->query('ANALYZE TABLE ' . $prefixedTable);
        $results = $this->db->get_results('
            SELECT
                TABLE_NAME AS `table`,
                ROUND(SUM(DATA_LENGTH + INDEX_LENGTH)) AS `size`
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = "' . $this->db->dbname . '"
            AND TABLE_NAME = "' . $prefixedTable . '"
            ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;
        ');

        return !empty($results) ? (int)$results[0]->size : 0;
    }
    
    /**
     * Gets the database charset.
     *
     * @return string The database charset.
     */
    public function getCharset(): string
    {
        return $this->db->charset ?: 'utf8';
    }
    
    /**
     * Gets the database charset collate.
     *
     * @return string The database charset collate.
     */
    public function getCharsetCollate(): string
    {
        return $this->db->get_charset_collate();
    }
    
    /**
     * Adds the database prefix to a table name if it doesn't already have it.
     *
     * @param string $table The table name.
     * @return string The table name with prefix.
     */
    public function prefixTable(string $table): string
    {
        // Only add prefix if it's not already there
        if (!str_starts_with($table, $this->prefix)) {
            return $this->prefix . $table;
        }
        return $table;
    }

    /**
     * Set the table for the query.
     *
     * @param string $table The table name.
     * @param string|null $as Optional alias for the table.
     * @return self
     */
    public function table(string $table, ?string $as = null): self
    {
        $this->reset();
        $prefixedTable = $this->prefixTable($table);
        $this->tableName = $prefixedTable; // Store the table name without alias
        $this->table = $as ? "$prefixedTable AS $as" : $prefixedTable;
        return $this;
    }

    /**
     * Alias for table() method.
     * 
     * @param string $table The table name.
     * @param string|null $as Optional alias for the table.
     * @return self
     */
    public function from(string $table, ?string $as = null): self
    {
        return $this->table($table, $as);
    }

    /**
     * Set the statement type for the query.
     *
     * @param string $statement The statement type (SELECT, INSERT, UPDATE, DELETE, etc.).
     * @return self
     */
    public function statement(string $statement): self
    {
        $this->statement = strtoupper($statement);
        return $this;
    }

    /**
     * Set the query to be a SELECT statement.
     *
     * @param array|string $select The columns to select.
     * @return self
     */
    public function select($select = '*'): self
    {
        $this->statement = 'SELECT';
        
        if (is_array($select)) {
            foreach ($select as $field) {
                $this->select[] = $field;
            }
        } else {
            $this->select[] = $select;
        }
        
        return $this;
    }

    /**
     * Set the query to be an INSERT statement.
     *
     * @return self
     */
    public function insert(): self
    {
        $this->statement = 'INSERT';
        return $this;
    }

    /**
     * Set the query to be an UPDATE statement.
     *
     * @return self
     */
    public function update(): self
    {
        $this->statement = 'UPDATE';
        return $this;
    }

    /**
     * Set the query to be a DELETE statement.
     *
     * @return self
     */
    public function delete(): self
    {
        $this->statement = 'DELETE';
        return $this;
    }

    /**
     * Set the query to be a TRUNCATE statement.
     *
     * @return self
     */
    public function truncate(): self
    {
        $this->statement = 'TRUNCATE';
        return $this;
    }

    /**
     * Set the query to be a REPLACE statement.
     *
     * @return self
     */
    public function replace(): self
    {
        $this->statement = 'REPLACE';
        return $this;
    }

    /**
     * Set the IGNORE flag for INSERT statements.
     *
     * @param bool $ignore Whether to ignore duplicate entries.
     * @return self
     */
    public function ignore(bool $ignore = true): self
    {
        $this->ignore = $ignore;
        return $this;
    }

    /**
     * Add a WHERE clause to the query.
     *
     * @param string $column The column name.
     * @param mixed $value The value to compare against.
     * @param string $operator The comparison operator.
     * @return self
     */
    public function where(string $column, mixed $value, string $operator = '='): self
    {
        if ($value instanceof \Closure) {
            $subQuery = new self();
            $value($subQuery);
            $this->where[] = "$column $operator (" . $subQuery->toSql() . ")";
        } else {
            $escapedValue = $this->escapeValue($value);
            $this->where[] = "$column $operator $escapedValue";
        }
        return $this;
    }

    /**
     * Add a WHERE IN clause to the query.
     *
     * @param string $column The column name.
     * @param array $values The values to compare against.
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        $escapedValues = array_map([$this, 'escapeValue'], $values);
        $this->where[] = "$column IN (" . implode(', ', $escapedValues) . ')';
        return $this;
    }

    /**
     * Add a WHERE NOT IN clause to the query.
     *
     * @param string $column The column name.
     * @param array $values The values to compare against.
     * @return self
     */
    public function whereNotIn(string $column, array $values): self
    {
        $escapedValues = array_map([$this, 'escapeValue'], $values);
        $this->where[] = "$column NOT IN (" . implode(', ', $escapedValues) . ')';
        return $this;
    }

    /**
     * Add a WHERE LIKE clause to the query.
     *
     * @param string $column The column name.
     * @param string $value The value to compare against.
     * @return self
     */
    public function whereLike(string $column, string $value): self
    {
        $escapedValue = $this->escapeValue("%$value%");
        $this->where[] = "$column LIKE $escapedValue";
        return $this;
    }
    
    /**
     * Add a WHERE clause with OR conditions using a closure.
     * The closure receives a WhereGroup instance that can be used to build the OR conditions.
     * All conditions added within the closure will be joined with OR and wrapped in parentheses.
     *
     * @param callable $callback A closure that receives a WhereGroup instance and adds where conditions to it.
     * @return self
     */
    public function whereOr(callable $callback): self
    {
        // Create a new WhereGroup instance for building the OR conditions
        $whereGroup = new WhereGroup($this);
        
        // Call the callback with the WhereGroup instance
        $callback($whereGroup);
        
        // If there are conditions in the WhereGroup, add them to the main query
        if (!empty($whereGroup->getConditions())) {
            // Join the conditions with OR and wrap in parentheses
            $this->where[] = '(' . implode(' OR ', $whereGroup->getConditions()) . ')';
        }
        
        return $this;
    }

    /**
     * Add a WHERE NULL clause to the query.
     *
     * @param string $column The column name.
     * @return self
     */
    public function whereNull(string $column): self
    {
        $this->where[] = "$column IS NULL";
        return $this;
    }

    /**
     * Add a WHERE NOT NULL clause to the query.
     *
     * @param string $column The column name.
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $this->where[] = "$column IS NOT NULL";
        return $this;
    }

    /**
     * Add a raw WHERE clause to the query.
     *
     * @param string $clause The raw WHERE clause.
     * @return self
     */
    public function whereRaw(string $clause): self
    {
        $this->where[] = $clause;
        return $this;
    }

    /**
     * Add a JOIN clause to the query.
     *
     * @param string $table The table to join.
     * @param string|array $on The ON condition.
     * @param string $type The join type (INNER, LEFT, RIGHT).
     * @return self
     */
    public function join(string $table, $on, string $type = 'INNER'): self
    {
        $table = $this->prefixTable($table);
        $this->join[] = [$table, $on, strtoupper($type)];
        return $this;
    }

    /**
     * Add a LEFT JOIN clause to the query.
     *
     * @param string $table The table to join.
     * @param string|array $on The ON condition.
     * @return self
     */
    public function leftJoin(string $table, $on): self
    {
        return $this->join($table, $on, 'LEFT');
    }

    /**
     * Add a RIGHT JOIN clause to the query.
     *
     * @param string $table The table to join.
     * @param string|array $on The ON condition.
     * @return self
     */
    public function rightJoin(string $table, $on): self
    {
        return $this->join($table, $on, 'RIGHT');
    }

    /**
     * Add a GROUP BY clause to the query.
     *
     * @param array|string $columns The columns to group by.
     * @return self
     */
    public function groupBy(array|string $columns): self
    {
        if (is_array($columns)) {
            foreach ($columns as $column) {
                $this->group[] = "$column";
            }
        } else {
            $this->group[] = "$columns";
        }
        
        return $this;
    }

    /**
     * Add an ORDER BY clause to the query.
     *
     * @param string|array $columns The columns to order by.
     * @param string $direction The sort direction.
     * @return self
     */
    public function orderBy($columns, string $direction = 'ASC'): self
    {
        $orderDirection = strtoupper($direction);
        
        if (is_array($columns)) {
            foreach ($columns as $column) {
                $this->order[] = "$column " . $orderDirection;
            }
        } else {
            $this->order[] = "$columns " . $orderDirection;
        }
        
        return $this;
    }

    /**
     * Add a LIMIT clause to the query.
     *
     * @param int $limit The limit value.
     * @param int|null $offset The offset value.
     * @return self
     */
    public function limit(int $limit, ?int $offset = null): self
    {
        if ($offset !== null) {
            $this->limit = "$offset, $limit";
        } else {
            $this->limit = $limit;
        }
        
        return $this;
    }

    /**
     * Set the DISTINCT flag for SELECT statements.
     *
     * @param bool $distinct Whether to use DISTINCT.
     * @return self
     */
    public function distinct(bool $distinct = true): self
    {
        $this->distinct = $distinct;
        return $this;
    }

    /**
     * Set values for INSERT, UPDATE, or REPLACE statements.
     *
     * @param array $data The data to set.
     * @return self
     */
    public function set(array $data): self
    {
        foreach ($data as $column => $value) {
            $escapedValue = $this->escapeValue($value);
            $this->set[] = "$column = $escapedValue";
        }
        
        return $this;
    }

    /**
     * Set values for ON DUPLICATE KEY UPDATE clause.
     *
     * @param array $data The data to set.
     * @return self
     */
    public function onDuplicate(array $data): self
    {
        foreach ($data as $column => $value) {
            $escapedValue = $this->escapeValue($value);
            $this->onDuplicate[] = "$column = $escapedValue";
        }
        
        return $this;
    }

    /**
     * Set the output format for the query results.
     *
     * @param string $output The output format (OBJECT, ARRAY_A, ARRAY_N).
     * @return self
     */
    public function output(string $output): self
    {
        $this->output = $output;
        return $this;
    }

    /**
     * Execute the query and return the results.
     *
     * @return mixed The query results.
     */
    public function get(): mixed
    {
        $query = $this->__toString();
        $this->lastQuery = $query;
        
        // Check cache first
        $cacheKey = md5($query);
        if (isset($this->cache[$cacheKey]) && !$this->shouldResetCache) {
            return $this->cache[$cacheKey];
        }
        
        switch ($this->statement) {
            case 'SELECT':
                $result = $this->db->get_results($query, $this->output);
                break;
                
            case 'INSERT':
            case 'REPLACE':
                $this->db->query($query);
                $result = $this->db->insert_id;
                break;
                
            case 'UPDATE':
            case 'DELETE':
                $this->db->query($query);
                $result = $this->db->rows_affected;
                break;
                
            case 'TRUNCATE':
                $this->db->query($query);
                $result = true;
                break;
                
            default:
                $result = $this->db->query($query);
                break;
        }
        
        // Cache the result
        $this->cache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Execute the query and return a single row.
     *
     * @return mixed The first row of the query results.
     */
    public function first(): mixed
    {
        $this->limit(1);
        $results = $this->get();
        
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Execute the query and return a single column value.
     *
     * @param string $column The column to return.
     * @return mixed The column value.
     */
    public function value(string $column): mixed
    {
        $this->select($column);
        $this->limit(1);
        $result = $this->first();
        
        // Extract the alias if it exists in the column name
        if ($result && preg_match('/\s+as\s+(\w+)$/i', $column, $matches)) {
            $alias = $matches[1];
            return $result->$alias;
        }
        
        return $result ? $result->$column : null;
    }

    /**
     * Execute the query and return a count of the results.
     *
     * @return int The count of the results.
     */
    public function count(): int
    {
        $this->select('COUNT(*) as count');
        $result = $this->first();
        
        return $result ? (int)$result->count : 0;
    }

    /**
     * Execute the query and return the maximum value of a column.
     *
     * @param string $column The column to get the maximum value of.
     * @return mixed The maximum value.
     */
    public function max(string $column): mixed
    {
        $this->select("MAX($column) as max_value");
        $result = $this->first();
        
        return $result ? $result->max_value : null;
    }

    /**
     * Execute the query and return the minimum value of a column.
     *
     * @param string $column The column to get the minimum value of.
     * @return mixed The minimum value.
     */
    public function min(string $column): mixed
    {
        $this->select("MIN($column) as min_value");
        $result = $this->first();
        
        return $result ? $result->min_value : null;
    }

    /**
     * Execute the query and return the sum of a column.
     *
     * @param string $column The column to sum.
     * @return mixed The sum.
     */
    public function sum(string $column): mixed
    {
        $this->select("SUM($column) as sum_value");
        $result = $this->first();
        
        return $result ? $result->sum_value : null;
    }

    /**
     * Execute the query and return the average of a column.
     *
     * @param string $column The column to average.
     * @return mixed The average.
     */
    public function avg(string $column): mixed
    {
        $this->select("AVG($column) as avg_value");
        $result = $this->first();
        
        return $result ? $result->avg_value : null;
    }

    /**
     * Clear the query cache.
     *
     * @return self
     */
    public function clearCache(): self
    {
        $this->cache = [];
        $this->shouldResetCache = true;
        return $this;
    }

    /**
     * Escape a value for use in a query.
     *
     * @param mixed $value The value to escape.
     * @return string The escaped value.
     */
    public function escapeValue($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if (!is_scalar($value)) {
            try {
                $value = json_encode($value, JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $value = '';
            }
        }
        
        if ($this->stripTags || ($this->escapeOptions & self::ESCAPE_STRIP_HTML)) {
            $value = wp_strip_all_tags((string)$value);
        }
        
        if ($this->escapeOptions & self::ESCAPE_QUOTE) {
            $value = $this->db->prepare('%s', $value);
        } else {
            $value = "'" . esc_sql($value) . "'";
        }
        
        return $value;
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
        if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i', $sql)) {
            return $this->db->get_results($sql, $output);
        }
        
        return $this->db->query($sql);
    }
    
    /**
     * Retrieve a single variable from the database.
     *
     * @param string $sql The SQL query to execute.
     * @param int $column Optional. Column of value to return. Default 0.
     * @param int $row Optional. Row of value to return. Default 0.
     * @return mixed Database query result in format specified by $output or null on failure.
     */
    public function get_var(string $sql, int $column = 0, int $row = 0): mixed
    {
        $this->lastQuery = $sql;
        
        // Check cache first
        $cacheKey = md5($sql . $column . $row);
        if (isset($this->cache[$cacheKey]) && !$this->shouldResetCache) {
            return $this->cache[$cacheKey];
        }
        
        $result = $this->db->get_var($sql, $column, $row);
        
        // Cache the result
        $this->cache[$cacheKey] = $result;
        
        return $result;
    }

    /**
     * Begin a database transaction
     *
     * @return int|bool|mysqli_result|null Success status
     */
    public function beginTransaction(): int|bool|null|mysqli_result
    {
        return $this->db->query('START TRANSACTION');
    }

    /**
     * Commit a database transaction
     *
     * @return int|bool|mysqli_result|null Success status
     */
    public function commit(): int|bool|mysqli_result|null
    {
        return $this->db->query('COMMIT');
    }

    /**
     * Rollback a database transaction
     *
     * @return int|bool|mysqli_result|null Success status
     */
    public function rollback(): int|bool|mysqli_result|null
    {
        return $this->db->query('ROLLBACK');
    }

    /**
     * Build the query string.
     *
     * @return string The query string.
     */
    /**
     * Return the SQL string for the query.
     *
     * @return string The SQL string.
     */
    public function toSql(): string
    {
        return $this->__toString();
    }

    public function __toString(): string
    {
        switch (strtoupper($this->statement)) {
            case 'INSERT':
                $insert = 'INSERT ';
                if ($this->ignore) {
                    $insert .= 'IGNORE ';
                }
                $insert   .= 'INTO ' . $this->table;
                $clauses   = [];
                $clauses[] = $insert;
                $clauses[] = 'SET ' . implode(', ', $this->set);
                if (!empty($this->onDuplicate)) {
                    $clauses[] = 'ON DUPLICATE KEY UPDATE ' . implode(', ', $this->onDuplicate);
                }
                break;
                
            case 'REPLACE':
                $clauses   = [];
                $clauses[] = "REPLACE INTO $this->table";
                $clauses[] = 'SET ' . implode(', ', $this->set);
                break;
                
            case 'UPDATE':
                $clauses   = [];
                $clauses[] = "UPDATE $this->table";

                $clauses = $this->setJoins($clauses);

                $clauses[] = 'SET ' . implode(', ', $this->set);

                if (count($this->where) > 0) {
                    $clauses[] = "WHERE 1 = 1 AND\n\t" . implode("\n\tAND ", $this->where);
                }

                if (count($this->order) > 0) {
                    $clauses[] = 'ORDER BY ' . implode(', ', $this->order);
                }

                if ($this->limit) {
                    $clauses[] = 'LIMIT ' . $this->limit;
                }
                break;

            case 'TRUNCATE':
                $clauses   = [];
                $clauses[] = "TRUNCATE TABLE $this->table";
                break;

            case 'DELETE':
                $clauses   = [];
                $clauses[] = "DELETE FROM $this->table";

                if (count($this->where) > 0) {
                    $clauses[] = "WHERE 1 = 1 AND\n\t" . implode("\n\tAND ", $this->where);
                }

                if (count($this->order) > 0) {
                    $clauses[] = 'ORDER BY ' . implode(', ', $this->order);
                }

                if ($this->limit) {
                    $clauses[] = 'LIMIT ' . $this->limit;
                }
                break;
                
            case 'SELECT':
            default:
                $clauses = [];
                
                $select = 'SELECT ';
                if ($this->distinct) {
                    $select .= 'DISTINCT ';
                }
                
                $select .= implode(', ', !empty($this->select) ? $this->select : ['*']);
                $clauses[] = $select;
                
                $clauses[] = "FROM $this->table";

                $clauses = $this->setJoins($clauses);
                
                if (count($this->where) > 0) {
                    $clauses[] = "WHERE 1 = 1 AND\n\t" . implode("\n\tAND ", $this->where);
                }
                
                if (count($this->group) > 0) {
                    $clauses[] = 'GROUP BY ' . implode(', ', $this->group);
                }
                
                if (count($this->order) > 0) {
                    $clauses[] = 'ORDER BY ' . implode(', ', $this->order);
                }
                
                if ($this->limit) {
                    $clauses[] = 'LIMIT ' . $this->limit;
                }
                break;
        }

        return implode("\n", $clauses);
    }

    /**
     * Set the JOIN clauses for the query.
     * This method is used internally to build the JOIN clauses based on the join array.
     * @param array $clauses
     * @return array
     */
    private function setJoins(array $clauses): array
    {
        if (count($this->join) > 0) {
            foreach ($this->join as $join) {
                if (is_array($join[1])) {
                    $join_on = [];
                    foreach ($join[1] as $left => $right) {
                        // Use tableName instead of table to avoid issues with aliases
                        $join_on[] = "$this->tableName.`$left` = `{$join[0]}`.`$right`";
                    }
                    $clauses[] = "\t" . (($join[2] === 'LEFT' || $join[2] === 'RIGHT') ? $join[2] . ' JOIN ' : 'JOIN ') . $join[0] . ' ON ' . implode(' AND ', $join_on);
                } else {
                    $clauses[] = "\t" . (($join[2] === 'LEFT' || $join[2] === 'RIGHT') ? $join[2] . ' JOIN ' : 'JOIN ') . "{$join[0]} ON {$join[1]}";
                }
            }
        }
        return $clauses;
    }
}
