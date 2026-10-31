<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\DB\AbstractMigration;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

/**
 * Drop all foreign keys from all tables
 */
class M20260127000000_DropAllForeignKeys extends AbstractMigration
{
    /**
     * Run the migration
     *
     * @return bool Success status
     */
    public function up(): bool
    {
        $success = true;

        // Foreign keys defined in M20240601000000_CreateAllTables.php
        $foreignKeys = [
            DatabaseTablesManager::DATABASE_SEO_CONTEXTS   => 'fk_contexts_optimisers',
            DatabaseTablesManager::DATABASE_SEO_FACTORS    => 'fk_factors_contexts',
            DatabaseTablesManager::DATABASE_SEO_OPERATIONS => 'fk_operations_factors',
        ];

        foreach ($foreignKeys as $table => $fk) {
            $tableName = $this->getTableName($table);
            
            // Check if table exists before trying to drop foreign key
            if (!$this->dbManager->tableExists($table)) {
                continue;
            }

            $sql = "ALTER TABLE `$tableName` DROP FOREIGN KEY `$fk`";
            
            // Execute query and ignore errors if the FK doesn't exist
            try {
                $this->dbManager->db()->db->hide_errors();
                $this->dbManager->db()->db->suppress_errors();
                $this->dbManager->db()->db->query($sql);
            } catch (\Throwable $e) {
                $this->log("Error dropping foreign key $fk on table $tableName: " . $e->getMessage(), 'WARNING');
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
        return true;
    }

    /**
     * Get the migration description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Drop all foreign keys from all tables';
    }
}
