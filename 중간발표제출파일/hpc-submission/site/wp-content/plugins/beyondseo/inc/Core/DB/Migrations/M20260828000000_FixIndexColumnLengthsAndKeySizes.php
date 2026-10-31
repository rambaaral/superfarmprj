<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\DB\AbstractMigration;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

/**
 * Bring every index key under the smallest key-length limit found on shared
 * hosts. utf8mb4 costs 4 bytes/char, so an un-prefixed VARCHAR(255) index is
 * 1020 bytes — over both the 767-byte limit (InnoDB COMPACT, no large index
 * prefixes) and the 1000-byte MyISAM limit — and MySQL rejects the CREATE.
 *
 * Two repair paths:
 * - Existing tables: shrink the indexed VARCHAR columns to their real content
 *   size and drop indexes that duplicate a unique key on the same column.
 * - Missing tables (hosts where the original CREATE was rejected but dbDelta
 *   swallowed the error and the migration was recorded as applied anyway):
 *   recreate collectors, steps and metatags with host-safe schemas, re-seed
 *   their fixed rows, and re-run the dependent seeding/backfill that silently
 *   no-oped while they were absent (questions rows, legacy postmeta migration).
 *   The setup table itself is created by M20260827000000_RepairSetupTable,
 *   which runs before this migration.
 */
class M20260828000000_FixIndexColumnLengthsAndKeySizes extends AbstractMigration
{
    /**
     * Run the migration
     *
     * @return bool Success status
     */
    public function up(): bool
    {
        $success = $this->fixMigrationsTable();
        $success = $this->fixCollectorsTable() && $success;
        $success = $this->fixStepsTable() && $success;
        $success = $this->fixSetupTable() && $success;
        $success = $this->fixMetaTagsTable() && $success;

        return $success;
    }

    /**
     * Reverse the migration
     *
     * Reversing would restore index keys over the host limits this migration
     * exists to respect, so the safe schema is kept.
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
        return 'Shrink indexed VARCHAR columns below the 767-byte utf8mb4 index key limit and recreate tables missing on hosts where their original CREATE was rejected';
    }

    /**
     * Shrink migration_name so its unique key fits the 767-byte limit
     * (191 * 4 = 764 bytes).
     *
     * @return bool Success status
     */
    private function fixMigrationsTable(): bool
    {
        if (!$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_MIGRATIONS)) {
            return true;
        }

        return $this->shrinkColumn(
            DatabaseTablesManager::DATABASE_MIGRATIONS,
            'migration_name',
            191,
            'VARCHAR(191) NOT NULL'
        );
    }

    /**
     * Shrink collector (unique-keyed) and drop the index duplicating its
     * unique key; recreate the table where its original CREATE was rejected.
     *
     * @return bool Success status
     */
    private function fixCollectorsTable(): bool
    {
        if (!$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_SETUP_COLLECTORS)) {
            return $this->recreateCollectorsTable();
        }

        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP_COLLECTORS);

        $success = $this->shrinkColumn(
            DatabaseTablesManager::DATABASE_SETUP_COLLECTORS,
            'collector',
            100,
            'VARCHAR(100) NOT NULL'
        );

        return $this->dropIndexIfExists($tableName, 'idx_collector') && $success;
    }

    /**
     * Shrink step (unique-keyed) and drop the index duplicating its unique
     * key; recreate the table where its original CREATE was rejected. Also
     * seeds the questions table if it was left empty because the step IDs it
     * depends on did not exist when CreateAllTables tried to populate it.
     *
     * @return bool Success status
     */
    private function fixStepsTable(): bool
    {
        if (!$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_SETUP_STEPS)) {
            $success = $this->recreateStepsTable();

            if ($success) {
                $this->seedQuestionsIfEmpty();
            }

            return $success;
        }

        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP_STEPS);

        $success = $this->shrinkColumn(
            DatabaseTablesManager::DATABASE_SETUP_STEPS,
            'step',
            100,
            'VARCHAR(100) NOT NULL'
        );

        $success = $this->dropIndexIfExists($tableName, 'idx_step') && $success;

        $this->seedQuestionsIfEmpty();

        return $success;
    }

    /**
     * Shrink the setup requirement columns; on installs created before the
     * fixed baseline this also brings the (setupRequirement, entityAlias)
     * composite index from 2040 down to 600 bytes.
     *
     * @return bool Success status
     */
    private function fixSetupTable(): bool
    {
        // Creation and row seeding are RepairSetupTable's job (it sorts before
        // this migration); if that failed, retrying here would fail the same way.
        if (!$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_SETUP)) {
            return true;
        }

        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP);

        $success = $this->shrinkColumn(
            DatabaseTablesManager::DATABASE_SETUP,
            'setupRequirement',
            100,
            'VARCHAR(100) NOT NULL'
        );

        $success = $this->shrinkColumn(
            DatabaseTablesManager::DATABASE_SETUP,
            'entityAlias',
            50,
            'VARCHAR(50) NOT NULL'
        ) && $success;

        // Installs created before 1.3.4 carry idx_setup_entity (safe at 600 bytes
        // after the shrink); only add the lookup index where neither variant exists.
        if (!$this->indexExists($tableName, 'idx_setup_entity')
            && !$this->indexExists($tableName, 'idx_setup_requirement')
        ) {
            $success = $this->execute(
                "ALTER TABLE `$tableName` ADD INDEX idx_setup_requirement (setupRequirement)"
            ) && $success;
        }

        return $success;
    }

    /**
     * Shrink the indexed metatags columns and ensure the (post_id, type)
     * unique key exists; recreate the table where its original CREATE was
     * rejected.
     *
     * @return bool Success status
     */
    private function fixMetaTagsTable(): bool
    {
        if (!$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_MOD_METATAGS)) {
            return $this->recreateMetaTagsTable();
        }

        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_MOD_METATAGS);

        $success = $this->shrinkColumn(
            DatabaseTablesManager::DATABASE_MOD_METATAGS,
            'type',
            100,
            'VARCHAR(100) NOT NULL'
        );

        // Normally dropped by DropRedundantMetaTagsColumns; where that drop
        // failed, at least bring its index under the key limit.
        $success = $this->shrinkColumn(
            DatabaseTablesManager::DATABASE_MOD_METATAGS,
            'unique_key',
            191,
            'VARCHAR(191) NOT NULL'
        ) && $success;

        // DeduplicateAndUniqueMetaTags could not add this key while type was
        // VARCHAR(255) (8 + 1020 = 1028 bytes) — and on installs where the table
        // itself was missing it was recorded as applied without doing anything.
        // unique_post_type is the module dbDelta schema's name for the same key.
        if (!$this->indexExists($tableName, 'uidx_post_id_type')
            && !$this->indexExists($tableName, 'unique_post_type')
        ) {
            $dedupSql = "DELETE t1 FROM `$tableName` t1
                INNER JOIN `$tableName` t2
                    ON t1.post_id = t2.post_id
                    AND t1.type = t2.type
                    AND t1.id < t2.id";

            $success = $this->execute($dedupSql) && $success;
            $success = $this->execute(
                "ALTER TABLE `$tableName` ADD UNIQUE KEY uidx_post_id_type (post_id, type)"
            ) && $success;
        }

        return $success;
    }

    /**
     * Recreate the collectors table with a host-safe schema and seed its
     * fixed rows.
     *
     * @return bool Success status
     */
    private function recreateCollectorsTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP_COLLECTORS);
        $charsetCollate = $this->getCharsetCollate();

        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT NOT NULL AUTO_INCREMENT,
            collector VARCHAR(100) NOT NULL,
            settings TEXT DEFAULT NULL,
            className VARCHAR(255) NOT NULL,
            priority INT(11) NOT NULL DEFAULT 0,
            active tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY unique_collector (collector),
            KEY idx_active (active)
        ) $charsetCollate;";

        if (!$this->executeQuery($sql) || !$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_SETUP_COLLECTORS)) {
            $this->log("Repair failed: could not create $tableName", 'ERROR');
            return false;
        }

        if (!$this->tableIsEmpty($tableName)) {
            return true;
        }

        // className holds the short prefixes: UpdateCollectorsClassName already
        // ran (and skipped) on installs that were missing this table.
        $collectors = ['Database', 'WordPress', 'Extendify', 'RankingCoach'];

        foreach ($collectors as $index => $collectorItem) {
            $this->dbManager->insert(
                DatabaseTablesManager::DATABASE_SETUP_COLLECTORS,
                [
                    'collector' => $collectorItem,
                    'settings' => null,
                    'className' => $collectorItem,
                    'priority' => $index + 1,
                    'active' => 1,
                ]
            );
        }

        return true;
    }

    /**
     * Recreate the steps table with a host-safe schema and seed its fixed rows.
     *
     * @return bool Success status
     */
    private function recreateStepsTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP_STEPS);
        $charsetCollate = $this->getCharsetCollate();

        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT NOT NULL AUTO_INCREMENT,
            step VARCHAR(100) NOT NULL,
            requirements VARCHAR(255) NOT NULL,
            priority INT(11) NOT NULL DEFAULT 0,
            isFinalStep tinyint(1) NOT NULL DEFAULT 0,
            active tinyint(1) NOT NULL DEFAULT 1,
            completed tinyint(1) NOT NULL DEFAULT 0,
            userSaveCount INT(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY unique_step (step),
            KEY idx_isFinalStep (isFinalStep),
            KEY idx_active (active),
            KEY idx_completed (completed)
        ) $charsetCollate;";

        if (!$this->executeQuery($sql) || !$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_SETUP_STEPS)) {
            $this->log("Repair failed: could not create $tableName", 'ERROR');
            return false;
        }

        if (!$this->tableIsEmpty($tableName)) {
            return true;
        }

        $steps = [
            'SETUP_STEP_BUSINESS_SHORT_DESCRIPTION' => 'businessWebsiteUrl,businessDescription',
            'SETUP_STEP_BUSINESS_NAME' => 'businessName',
            'SETUP_STEP_BUSINESS_DETAILED_DESCRIPTION' => 'businessKeywords,businessCategories',
            'SETUP_STEP_BUSINESS_LOCATION_ADDRESS' => 'businessAddress',
            'SETUP_STEP_BUSINESS_SERVICE_AREA' => 'businessServiceArea',
            'SETUP_STEP_BUSINESS_SPECIFIC_DESCRIPTION' => 'businessDescription,businessKeywords,businessCategories',
        ];
        $countSteps = count($steps);
        $index = 0;

        foreach ($steps as $stepName => $requirements) {
            $index = $index + 1;

            $this->dbManager->insert(
                DatabaseTablesManager::DATABASE_SETUP_STEPS,
                [
                    'step' => $stepName,
                    'requirements' => $requirements,
                    'priority' => $index,
                    'isFinalStep' => $index === $countSteps ? 1 : 0,
                    'active' => 1,
                    'completed' => 0,
                ]
            );
        }

        return true;
    }

    /**
     * Recreate the metatags table with a host-safe schema (in its final shape:
     * the redundant columns dropped by DropRedundantMetaTagsColumns are omitted
     * and the (post_id, type) unique key is built in), then re-run the legacy
     * postmeta backfill that was recorded as applied while the table was missing.
     *
     * @return bool Success status
     */
    private function recreateMetaTagsTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_MOD_METATAGS);
        $charsetCollate = $this->getCharsetCollate();

        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(100) NOT NULL,
            template TEXT NOT NULL,
            UNIQUE KEY uidx_post_id_type (post_id, type),
            INDEX idx_post_id (post_id),
            INDEX idx_type (type)
        ) $charsetCollate;";

        if (!$this->executeQuery($sql) || !$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_MOD_METATAGS)) {
            $this->log("Repair failed: could not create $tableName", 'ERROR');
            return false;
        }

        try {
            (new M20260707000000_MigrateLegacyMetaToMetaTagsTable())->up();
        } catch (\Throwable $e) {
            // Best effort: the table itself is repaired even if the backfill fails.
            $this->log("Legacy meta backfill after recreating $tableName failed: " . $e->getMessage(), 'ERROR');
        }

        return true;
    }

    /**
     * Seed the questions table when it exists but is empty. CreateAllTables
     * populates it by looking up step IDs — on installs where the steps table
     * was missing, every lookup returned null and the table stayed empty.
     */
    private function seedQuestionsIfEmpty(): void
    {
        if (!$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_SETUP_QUESTIONS)) {
            return;
        }

        $questionsTable = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP_QUESTIONS);

        if (!$this->tableIsEmpty($questionsTable)) {
            return;
        }

        $questionsStepsConfig = [
            'SETUP_STEP_BUSINESS_SHORT_DESCRIPTION' => [
                'Let\'s get started.',
                'First, could you tell me what your website or project is about?'
            ],
            'SETUP_STEP_BUSINESS_NAME' => [
                'Awesome!',
                'Do you already have a name for your website, project, or business?'
            ],
            'SETUP_STEP_BUSINESS_DETAILED_DESCRIPTION' => [
                'Wonderful!',
                'Could you describe in more detail what you plan to do with your website? For example, will you offer products or services, share blog articles, or something else?'
            ],
            'SETUP_STEP_BUSINESS_LOCATION_ADDRESS' => [
                'Just tasty! Thanks for sharing!',
                'Is your project or business tied to a specific location? Do you serve customers locally, or operate in multiple areas?'
            ],
            'SETUP_STEP_BUSINESS_SERVICE_AREA' => [
                'I see.',
                'Where do you primarily want to focus your reach? Is there a particular city or region you\'d like to target, or do you want to go nationwide?'
            ],
            'SETUP_STEP_BUSINESS_SPECIFIC_DESCRIPTION' => [
                'Thanks for providing that!',
                'Lastly, is there anything else you\'d like to highlight about your project or business, something that makes it unique or special?'
            ]
        ];

        foreach ($questionsStepsConfig as $stepName => $stepQuestions) {
            $step = $this->dbManager->getRow(
                DatabaseTablesManager::DATABASE_SETUP_STEPS,
                ['id'],
                ['step' => $stepName]
            );

            $stepId = $step?->id;

            if (!$stepId) {
                continue;
            }

            foreach ($stepQuestions as $index => $question) {
                $this->dbManager->insert(
                    DatabaseTablesManager::DATABASE_SETUP_QUESTIONS,
                    [
                        'parentId' => null,
                        'stepId' => $stepId,
                        'question' => $question,
                        'sequence' => $index + 1,
                        'aiContext' => null,
                        'isAiGenerated' => false,
                    ]
                );
            }
        }
    }

    /**
     * Narrow a VARCHAR column, skipping when it is already narrow enough,
     * absent, or holds values longer than the new width (which the app never
     * writes — shrinking would then truncate or fail, so it is left alone).
     *
     * @param string $table Table name without prefix
     * @param string $column Column name
     * @param int $maxChars Target character width
     * @param string $definition Full new column definition
     * @return bool Success status
     */
    private function shrinkColumn(string $table, string $column, int $maxChars, string $definition): bool
    {
        $tableName = $this->getTableName($table);

        $currentLength = $this->getVarcharLength($tableName, $column);

        if ($currentLength === null || $currentLength <= $maxChars) {
            return true;
        }

        $overLength = $this->dbManager->queryRaw(
            "SELECT 1 FROM `$tableName` WHERE CHAR_LENGTH(`$column`) > $maxChars LIMIT 1"
        );

        if (!empty($overLength)) {
            $this->log("Skipping shrink of $tableName.$column to VARCHAR($maxChars): longer values exist", 'ERROR');
            return true;
        }

        return $this->execute("ALTER TABLE `$tableName` MODIFY `$column` $definition");
    }

    /**
     * Get the declared character width of a VARCHAR column
     *
     * @param string $tableName Fully-prefixed table name
     * @param string $column Column name
     * @return int|null Width in characters, or null if absent / not a VARCHAR
     */
    private function getVarcharLength(string $tableName, string $column): ?int
    {
        $rows = $this->dbManager->queryRaw("SHOW COLUMNS FROM `$tableName` LIKE '$column'");

        if (empty($rows)) {
            return null;
        }

        $type = ((array) $rows[0])['Type'] ?? '';

        if (!preg_match('/^varchar\((\d+)\)/i', (string) $type, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * Check whether a given index exists on a table
     *
     * @param string $tableName Fully-prefixed table name
     * @param string $indexName Index name to look for
     * @return bool
     */
    private function indexExists(string $tableName, string $indexName): bool
    {
        $result = $this->dbManager->queryRaw("SHOW INDEX FROM `$tableName` WHERE Key_name = '$indexName'");

        return !empty($result);
    }

    /**
     * Drop an index when it exists
     *
     * @param string $tableName Fully-prefixed table name
     * @param string $indexName Index name to drop
     * @return bool Success status
     */
    private function dropIndexIfExists(string $tableName, string $indexName): bool
    {
        if (!$this->indexExists($tableName, $indexName)) {
            return true;
        }

        return $this->execute("ALTER TABLE `$tableName` DROP INDEX `$indexName`");
    }

    /**
     * Whether a table currently holds no rows
     *
     * @param string $tableName Fully-prefixed table name
     * @return bool
     */
    private function tableIsEmpty(string $tableName): bool
    {
        $rows = $this->dbManager->queryRaw("SELECT 1 FROM `$tableName` LIMIT 1");

        return empty($rows);
    }

    /**
     * Execute a single DDL/DML statement directly (dbDelta only understands
     * CREATE TABLE), logging the database error on failure.
     *
     * @param string $sql SQL statement
     * @return bool Success status
     */
    private function execute(string $sql): bool
    {
        try {
            $this->dbManager->db()->db->hide_errors();
            $this->dbManager->db()->db->suppress_errors();
            $result = $this->dbManager->db()->db->query($sql);

            if ($result === false) {
                $this->log('Migration query failed: ' . $this->dbManager->db()->db->last_error . ' — SQL: ' . $sql, 'ERROR');
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->log('Migration query failed: ' . $e->getMessage() . ' — SQL: ' . $sql, 'ERROR');
            return false;
        } finally {
            $this->dbManager->db()->db->show_errors();
        }
    }
}
