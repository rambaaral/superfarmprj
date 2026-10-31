<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\DB\AbstractMigration;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

/**
 * Drop redundant columns from the MetaTags table now that the placeholder
 * system stores everything in the `template` column.
 */
class M20260706000001_DropRedundantMetaTagsColumns extends AbstractMigration
{
    private const COLUMNS_TO_DROP = ['variables', 'unique_key', 'content', 'auto_generated'];

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

        foreach (self::COLUMNS_TO_DROP as $column) {
            if (!$this->columnExists($tableName, $column)) {
                continue;
            }

            $sql = "ALTER TABLE `$tableName` DROP COLUMN `$column`";

            try {
                $this->dbManager->db()->db->hide_errors();
                $this->dbManager->db()->db->suppress_errors();
                $result = $this->dbManager->db()->db->query($sql);

                if ($result === false) {
                    $this->log("Error dropping column `$column` on table $tableName: " . $this->dbManager->db()->db->last_error, 'ERROR');
                    $success = false;
                }
            } catch (\Throwable $e) {
                $this->log("Error dropping column `$column` on table $tableName: " . $e->getMessage(), 'ERROR');
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

        $columnsDefinitions = [
            'content' => "TEXT NOT NULL",
            'auto_generated' => "BOOLEAN NOT NULL DEFAULT FALSE",
            'variables' => "TEXT NOT NULL",
            'unique_key' => "VARCHAR(255) NOT NULL",
        ];

        $success = true;

        foreach ($columnsDefinitions as $column => $definition) {
            if ($this->columnExists($tableName, $column)) {
                continue;
            }

            $sql = "ALTER TABLE `$tableName` ADD COLUMN `$column` $definition";

            try {
                $this->dbManager->db()->db->hide_errors();
                $this->dbManager->db()->db->suppress_errors();
                $result = $this->dbManager->db()->db->query($sql);

                if ($result === false) {
                    $this->log("Error re-adding column `$column` on table $tableName: " . $this->dbManager->db()->db->last_error, 'ERROR');
                    $success = false;
                }
            } catch (\Throwable $e) {
                $this->log("Error re-adding column `$column` on table $tableName: " . $e->getMessage(), 'ERROR');
                $success = false;
            } finally {
                $this->dbManager->db()->db->show_errors();
            }
        }

        return $success;
    }

    /**
     * Check whether a given column already exists on a table
     *
     * @param string $tableName Fully-prefixed table name
     * @param string $columnName Column name to look for
     * @return bool
     */
    private function columnExists(string $tableName, string $columnName): bool
    {
        $sql = "SHOW COLUMNS FROM `$tableName` LIKE '$columnName'";
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
        return 'Drop redundant columns (variables, unique_key, content, auto_generated) from MetaTags table';
    }
}
