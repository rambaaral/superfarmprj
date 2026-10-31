<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\DB\AbstractMigration;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

/**
 * Repair the setup (requirements) table on installs where CreateAllTables was
 * recorded as successful but the table was never created.
 *
 * The original CREATE carried INDEX (setupRequirement, entityAlias) — 2040 bytes
 * under utf8mb4, over the 767-byte key limit on hosts without large index
 * prefixes — and dbDelta swallowed the rejection. On such installs every
 * requirement read/write (onboarding prefill, summary edits, submit payload)
 * silently no-ops against a nonexistent table.
 */
class M20260827000000_RepairSetupTable extends AbstractMigration
{
    /**
     * Run the migration
     *
     * @return bool Success status
     */
    public function up(): bool
    {
        if (!$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_SETUP)) {
            $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP);
            $charsetCollate = $this->getCharsetCollate();

            // Columns sized to real content keep the index at 400 bytes, under
            // every engine's key limit (matches the fixed CreateAllTables schema).
            $sql = "CREATE TABLE IF NOT EXISTS $tableName (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setupRequirement VARCHAR(100) NOT NULL,
                entityAlias VARCHAR(50) NOT NULL,
                value TEXT NULL,
                INDEX idx_setup_requirement (setupRequirement)
            ) $charsetCollate;";

            if (!$this->executeQuery($sql) || !$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_SETUP)) {
                $this->log("Repair failed: could not create $tableName", 'ERROR');
                return false;
            }
        }

        $this->seedMissingRequirementRows();

        return true;
    }

    /**
     * Reverse the migration
     *
     * A repair migration restores state the original migration should have
     * produced; rolling it back must not destroy requirement data.
     *
     * @return bool Success status
     */
    public function down(): bool
    {
        return true;
    }

    /**
     * Insert any requirement rows that are missing. Idempotent: existing rows
     * (with or without values) are left untouched, so this is safe on healthy
     * installs and on tables partially filled by RequirementHelper upserts.
     */
    private function seedMissingRequirementRows(): void
    {
        $allRequirements = [
            'businessEmailAddress',
            'businessWebsiteUrl',
            'businessName',
            'businessDescription',
            'businessAddress',
            'businessGeoAddress',
            'businessServiceArea',
            'businessKeywords',
            'businessCategories',
            'businessSpecificDescription',
        ];

        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP);
        $existingRows = $this->dbManager->queryRaw("SELECT DISTINCT setupRequirement FROM `$tableName`") ?: [];
        $existingNames = array_map(static fn($row) => ((array) $row)['setupRequirement'] ?? '', $existingRows);

        foreach ($allRequirements as $requirement) {
            if (in_array($requirement, $existingNames, true)) {
                continue;
            }

            $this->dbManager->insert(
                DatabaseTablesManager::DATABASE_SETUP,
                [
                    'setupRequirement' => $requirement,
                    'entityAlias'      => strtolower(str_replace('business', '', $requirement)),
                    'value'            => null,
                ]
            );
        }
    }

    /**
     * Get the migration description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Recreate the setup requirements table with a host-safe index where its original creation silently failed, and seed missing requirement rows';
    }
}
