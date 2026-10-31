<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\DB\AbstractMigration;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

/**
 * Create broken link checker table for the Broken Link Checker module
 */
class M20240901000000_CreateBrokenLinkCheckerTable extends AbstractMigration
{
    
    /**
     * Run the migration
     *
     * @return bool Success status
     */
    public function up(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_MOD_BROKEN_LINK_CHECKER);
        $charsetCollate = $this->getCharsetCollate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            url varchar(2083) NOT NULL,
            urlHash varchar(40) NOT NULL,
            status ENUM('active', 'broken', 'unscanned') NOT NULL DEFAULT 'unscanned',
            scan_date datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY urlHash (urlHash)
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
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_MOD_BROKEN_LINK_CHECKER);
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
        return 'Create broken link checker table for the Broken Link Checker module';
    }
}