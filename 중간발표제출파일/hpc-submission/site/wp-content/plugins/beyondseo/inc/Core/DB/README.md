# RankingCoach Database Management

This directory contains the database management classes for the RankingCoach WordPress plugin.

## Overview

The database management system consists of three main components:

1. **DatabaseTablesManager**: Manages the creation, population, and removal of database tables.
2. **Database**: Provides a fluent interface for building and executing database queries.
3. **DatabaseManager**: Serves as a facade for both the Database and DatabaseTablesManager classes.

## Database Builder Pattern

The Database class provides a fluent interface for building SQL queries, similar to Laravel's query builder. This makes it easier to write and maintain database queries.

### Basic Usage

```php
// Get the DatabaseManager instance
$dbManager = \RankingCoach\Inc\Core\DB\DatabaseManager::getInstance();

// Query the database
$keywords = $dbManager->table('rankingcoach_app_keywords')
    ->select(['id', 'name', 'alias'])
    ->where('name', 'example keyword')
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();
```

### Query Building

The Database class provides a fluent interface for building SQL queries:

```php
// SELECT query
$query = $dbManager->table('rankingcoach_app_keywords')
    ->select(['id', 'name', 'alias'])
    ->where('name', 'example keyword')
    ->orderBy('id', 'DESC')
    ->limit(10);

// INSERT query
$query = $dbManager->table('rankingcoach_app_keywords')
    ->insert()
    ->set([
        'name' => 'New Keyword',
        'alias' => 'new-keyword',
        'hash' => md5('New Keyword')
    ]);

// UPDATE query
$query = $dbManager->table('rankingcoach_app_keywords')
    ->update()
    ->set(['alias' => 'updated-keyword'])
    ->where('id', 1);

// DELETE query
$query = $dbManager->table('rankingcoach_app_keywords')
    ->delete()
    ->where('id', 1);
```

### Executing Queries

To execute a query, call the `get()` method:

```php
$results = $query->get();
```

For SELECT queries, this returns an array of results. For INSERT queries, it returns the inserted ID. For UPDATE and DELETE queries, it returns the number of affected rows.

### Convenience Methods

The DatabaseManager class provides several convenience methods for common operations:

```php
// Insert data
$newId = $dbManager->insert('rankingcoach_app_keywords', [
    'name' => 'Direct Insert',
    'alias' => 'direct-insert'
]);

// Update data
$updatedRows = $dbManager->update(
    'rankingcoach_app_keywords',
    ['alias' => 'updated-direct-insert'],
    ['id' => $newId]
);

// Delete data
$deletedRows = $dbManager->delete(
    'rankingcoach_app_keywords',
    ['id' => $newId]
);

// Get all rows
$allKeywords = $dbManager->getAll(
    'rankingcoach_app_keywords',
    ['id', 'name', 'alias'],
    ['name' => 'example keyword'],
    'id',
    'DESC',
    10
);

// Get a single row
$singleKeyword = $dbManager->getRow(
    'rankingcoach_app_keywords',
    ['id', 'name', 'alias'],
    ['id' => 1]
);

// Get a single value
$keywordName = $dbManager->getValue(
    'rankingcoach_app_keywords',
    'name',
    ['id' => 1]
);

// Count rows
$keywordCount = $dbManager->count(
    'rankingcoach_app_keywords',
    ['name' => 'example keyword']
);
```

## Database Migrations

The migration system allows for versioned database schema changes that can be applied and rolled back in a controlled manner. Each migration is a PHP class that implements the `MigrationInterface` and extends the `AbstractMigration` class.

## Creating a New Migration

To create a new migration, use the `createMigration` method of the `DatabaseTablesManager` class:

```php
$dbManager = new \RankingCoach\Inc\Core\DB\DatabaseTablesManager();
$migrationFile = $dbManager->createMigration('Add new column to table');
```

This will create a new migration file with a timestamp prefix in the format `YYYYMMDDHHMMSS` to ensure migrations are applied in the correct order.

## Migration File Structure

Each migration file should:

1. Extend the `AbstractMigration` class
2. Implement the `up()` method to apply the migration
3. Implement the `down()` method to roll back the migration
4. Implement the `getDescription()` method to provide a human-readable description

Example:

```php
<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

use RankingCoach\Inc\Core\DB\AbstractMigration;

class M20240501000000_CreateSampleTable extends AbstractMigration
{
    public function up(): bool
    {
        $tableName = $this->getTableName('rankingcoach_sample');
        $charsetCollate = $this->getCharsetCollate();

        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL
        ) $charsetCollate;";

        return $this->executeQuery($sql);
    }

    public function down(): bool
    {
        $tableName = $this->getTableName('rankingcoach_sample');
        $sql = "DROP TABLE IF EXISTS $tableName;";
        
        return $this->wpdb->query($sql) !== false;
    }

    public function getDescription(): string
    {
        return 'Create sample table';
    }
}
```

## Running Migrations

To run all pending migrations:

```php
$dbManager = new \RankingCoach\Inc\Core\DB\DatabaseTablesManager();
$results = $dbManager->runMigrations();
```

## Rolling Back Migrations

To roll back the last batch of migrations:

```php
$dbManager = new \RankingCoach\Inc\Core\DB\DatabaseTablesManager();
$results = $dbManager->rollbackMigrations();
```

## Checking Migration Status

To get the status of all migrations:

```php
$dbManager = new \RankingCoach\Inc\Core\DB\DatabaseTablesManager();
$status = $dbManager->getMigrationStatus();
```

## Best Practices

1. **Idempotent Migrations**: Make migrations idempotent (can be run multiple times without side effects) by checking if the changes have already been applied.

2. **Small, Focused Changes**: Each migration should make a small, focused change to the database schema.

3. **Descriptive Names**: Use descriptive names for migration files to make it clear what changes they make.

4. **Test Both Up and Down**: Test both the `up()` and `down()` methods to ensure they work correctly.

5. **Use Transactions**: For complex migrations, use transactions to ensure all changes are applied or none are.

6. **Document Changes**: Include a clear description of what the migration does in the `getDescription()` method.

7. **Handle Errors**: Properly handle errors and log them for debugging.

## Index Key Length Rules (utf8mb4)

WordPress uses `utf8mb4`, which costs **4 bytes per character** in index keys. The smallest
index key limit across the hosting environments we run on is **767 bytes** (InnoDB COMPACT /
`innodb_large_prefix = OFF`; MyISAM allows 1000). MySQL rejects any `CREATE TABLE` or
`ADD INDEX` whose key exceeds the host's limit — and `dbDelta()` swallows that rejection, so
the table silently never exists (see `M20260828000000_FixIndexColumnLengthsAndKeySizes`).

Rules for every new table or index:

1. **Never index an un-prefixed `VARCHAR(255)`.** The key is 1020 bytes and fails on
   constrained hosts. `VARCHAR(191)` (764 bytes) is the absolute maximum for a single
   indexed string column — but prefer sizing the column to its real content, e.g.
   `VARCHAR(50)` or `VARCHAR(100)`.

2. **Sum composite keys.** For an index over several columns, `sum(char_length × 4)`
   plus the byte size of any integer columns must stay ≤ 767. Example:
   `(setupRequirement VARCHAR(100), entityAlias VARCHAR(50))` = 400 + 200 = 600 bytes ✓.

3. **Long strings get a prefix or a hash.** For URLs/paths (`VARCHAR(2083)` etc.), index a
   prefix — `KEY (source_uri(191))` — or index a hash column (`url_hash VARCHAR(40)`) instead.

4. **Unique keys can't be prefix-truncated.** A `UNIQUE KEY` must fit the limit at full
   column width, so keep unique-keyed string columns short.

5. **Verify the query really ran.** `AbstractMigration::executeQuery()` checks
   `$wpdb->last_error` after `dbDelta()`; direct `$wpdb->query()` calls in migrations must
   check the return value and log `last_error` themselves.

## Advanced Database Usage

### Joins

```php
$results = $dbManager->table('rankingcoach_app_keywords AS k')
    ->select(['k.id', 'k.name', 'c.name AS category_name'])
    ->join('rankingcoach_setup_categories AS c', 'k.id = c.categoryId')
    ->where('k.name', 'example keyword')
    ->get();
```

### Complex Conditions

```php
$results = $dbManager->table('rankingcoach_app_keywords')
    ->select(['id', 'name', 'alias'])
    ->where('name', 'example keyword')
    ->whereIn('id', [1, 2, 3])
    ->whereLike('alias', 'keyword')
    ->whereNotNull('hash')
    ->groupBy('name')
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();
```

### Transactions

```php
$dbManager->beginTransaction();

try {
    $dbManager->insert('rankingcoach_app_keywords', [
        'name' => 'Transaction Keyword 1',
        'alias' => 'transaction-keyword-1'
    ]);
    
    $dbManager->insert('rankingcoach_app_keywords', [
        'name' => 'Transaction Keyword 2',
        'alias' => 'transaction-keyword-2'
    ]);
    
    $dbManager->commit();
} catch (Exception $e) {
    $dbManager->rollback();
    // Handle error
}
```

### Raw SQL

```php
$results = $dbManager->query(
    "SELECT * FROM {$dbManager->db()->prefix}rankingcoach_app_keywords WHERE name LIKE '%keyword%'"
);
```
