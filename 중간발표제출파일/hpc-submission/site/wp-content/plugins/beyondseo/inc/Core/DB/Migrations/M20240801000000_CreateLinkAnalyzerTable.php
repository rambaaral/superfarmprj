<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\DB\AbstractMigration;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

/**
 * Create link analyzer table for the Link Analyzer module
 */
class M20240801000000_CreateLinkAnalyzerTable extends AbstractMigration
{
    
    /**
     * Run the migration
     *
     * @return bool Success status
     */
    public function up(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_MOD_LINK_ANALYZER);
        $charsetCollate = $this->getCharsetCollate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            url varchar(2083) NOT NULL,
            url_hash varchar(40) NOT NULL,
            hostname varchar(255) NOT NULL,
            hostname_hash varchar(40) NOT NULL,
            external tinyint(1) NOT NULL DEFAULT 0,
            link_status_id mediumint(9) DEFAULT NULL,
            link_text text DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY url_hash (url_hash),
            KEY hostname_hash (hostname_hash),
            KEY external (external),
            KEY link_status_id (link_status_id)
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
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_MOD_LINK_ANALYZER);
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
        return 'Create link analyzer table for the Link Analyzer module';
    }
}