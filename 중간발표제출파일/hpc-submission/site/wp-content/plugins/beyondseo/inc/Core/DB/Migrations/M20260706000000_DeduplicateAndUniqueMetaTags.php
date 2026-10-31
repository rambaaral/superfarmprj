<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\DB\AbstractMigration;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

/**
 * Deduplicate existing rows in the MetaTags table (keeping the highest id
 * per post_id+type pair) and add a unique index on (post_id, type) so that
 * upsert semantics can be enforced at the database level.
 */
class M20260706000000_DeduplicateAndUniqueMetaTags extends AbstractMigration
{
    private const UNIQUE_INDEX_NAME = 'uidx_post_id_type';

    /**
     * Run the migration
     *
     * @return bool Success status
     */
    public function up(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_MOD_METATAGS);

        if (!$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_MOD_METATAGS)) {
            return true;
        }

        $success = true;

        // Deduplicate: for each (post_id, type) group with duplicates, keep only the row with MAX(id).
        $dedupSql = "DELETE t1 FROM `$tableName` t1
            INNER JOIN `$tableName` t2
                ON t1.post_id = t2.post_id
                AND t1.type = t2.type
                AND t1.id < t2.id";

        try {
            $this->dbManager->db()->db->hide_errors();
            $this->dbManager->db()->db->suppress_errors();
            $result = $this->dbManager->db()->db->query($dedupSql);

            if ($result === false) {
                $this->log("Error deduplicating rows on table $tableName: " . $this->dbManager->db()->db->last_error, 'ERROR');
                $success = false;
            }
        } catch (\Throwable $e) {
            $this->log("Error deduplicating rows on table $tableName: " . $e->getMessage(), 'ERROR');
            $success = false;
        } finally {
            $this->dbManager->db()->db->show_errors();
        }

        // Add unique index, guarding against re-running the migration.
        if (!$this->indexExists($tableName, self::UNIQUE_INDEX_NAME)) {
            $addIndexSql = "ALTER TABLE `$tableName` ADD UNIQUE KEY `" . self::UNIQUE_INDEX_NAME . "` (post_id, type)";

            try {
                $this->dbManager->db()->db->hide_errors();
                $this->dbManager->db()->db->suppress_errors();
                $result = $this->dbManager->db()->db->query($addIndexSql);

                if ($result === false) {
                    $this->log("Error adding unique index on table $tableName: " . $this->dbManager->db()->db->last_error, 'ERROR');
                    $success = false;
                }
            } catch (\Throwable $e) {
                $this->log("Error adding unique index on table $tableName: " . $e->getMessage(), 'ERROR');
                $success = false;
            } finally {
                $this->dbManager->db()->db->show_errors();
            }
        }

        return $success;
    }

    /**
     * Reverse the migration
     *
     * @return bool Success status
     */
    public function down(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_MOD_METATAGS);

        if (!$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_MOD_METATAGS)) {
            return true;
        }

        if (!$this->indexExists($tableName, self::UNIQUE_INDEX_NAME)) {
            return true;
        }

        $sql = "ALTER TABLE `$tableName` DROP INDEX `" . self::UNIQUE_INDEX_NAME . "`";

        try {
            $this->dbManager->db()->db->hide_errors();
            $this->dbManager->db()->db->suppress_errors();
            $result = $this->dbManager->db()->db->query($sql);

            return $result !== false;
        } catch (\Throwable $e) {
            $this->log("Error dropping unique index on table $tableName: " . $e->getMessage(), 'ERROR');
            return false;
        } finally {
            $this->dbManager->db()->db->show_errors();
        }
    }

    /**
     * Check whether a given index already exists on a table
     *
     * @param string $tableName Fully-prefixed table name
     * @param string $indexName Index name to look for
     * @return bool
     */
    private function indexExists(string $tableName, string $indexName): bool
    {
        $sql = "SHOW INDEX FROM `$tableName` WHERE Key_name = '$indexName'";
        $result = $this->dbManager->queryRaw($sql);

        return !empty($result);
    }

    /**
     * Get the migration description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Deduplicate MetaTags rows (keep highest id per post_id+type) and add unique index on (post_id, type)';
    }
}
