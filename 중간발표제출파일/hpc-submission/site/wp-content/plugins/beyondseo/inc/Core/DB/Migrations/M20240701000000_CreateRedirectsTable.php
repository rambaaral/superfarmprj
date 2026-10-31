<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\DB\AbstractMigration;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

/**
 * Create redirects table for the Redirect Manager module
 */
class M20240701000000_CreateRedirectsTable extends AbstractMigration
{
    /**
     * Run the migration
     *
     * @return bool Success status
     */
    public function up(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_MOD_REDIRECTS);
        $charsetCollate = $this->getCharsetCollate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_uri varchar(2083) NOT NULL,
            destination_url varchar(2083) NOT NULL,
            redirect_code smallint(3) NOT NULL DEFAULT 301,
            active tinyint(1) NOT NULL DEFAULT 1,
            hit_count bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY source_uri (source_uri(191)),
            KEY active (active)
        ) $charsetCollate;";
        
        return $this->executeQuery($sql);
    }

    /**
     * Reverse the migration
     *
     * @return bool Success status
     */
    public function down(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_MOD_REDIRECTS);
        $sql = "DROP TABLE IF EXISTS $tableName";
        
        return $this->executeQuery($sql);
    }

    /**
     * Get the migration description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create redirects table for the Redirect Manager module';
    }
}