<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;

/**
 * Abstract base class for database migrations
 */
abstract class AbstractMigration implements MigrationInterface
{
    use RcLoggerTrait;

    /** @var DatabaseManager Database manager instance */
    protected $dbManager;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->dbManager = DatabaseManager::getInstance();
    }

    /**
     * Execute raw SQL query
     *
     * @param string $sql SQL query to execute
     * @return bool Success status
     */
    protected function executeQuery(string $sql): bool
    {
        try {
            // dbDelta() never throws — a query MySQL rejects (e.g. an index over the
            // host's key-length limit) only lands in $wpdb->last_error. Without checking
            // it, a failed CREATE reports success, the migration gets recorded, and the
            // table silently never exists.
            $wpdb = $this->dbManager->db()->db;
            $wpdb->last_error = '';

            // Use the dbDelta method from DatabaseManager instead of calling dbDelta directly
            $this->dbManager->dbDelta($sql);

            if (!empty($wpdb->last_error)) {
                $this->log('Migration query failed: ' . $wpdb->last_error . ' — SQL: ' . $sql, 'ERROR');
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->log('Migration error: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Get the charset collate string
     *
     * @return string
     */
    protected function getCharsetCollate(): string
    {
        return $this->dbManager->db()->getCharsetCollate();
    }

    /**
     * Get the table name with prefix
     *
     * @param string $tableName Table name without prefix
     * @return string Table name with prefix
     */
    protected function getTableName(string $tableName): string
    {
        // Use the DatabaseManager's prefixTable method
        return $this->dbManager->prefixTable($tableName);
    }
}