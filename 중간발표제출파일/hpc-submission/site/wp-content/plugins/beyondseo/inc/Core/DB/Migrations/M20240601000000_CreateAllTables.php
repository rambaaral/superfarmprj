<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\DB\AbstractMigration;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

/**
 * Create all database tables
 */
class M20240601000000_CreateAllTables extends AbstractMigration
{
    /**
     * Run the migration
     *
     * @return bool Success status
     */
    public function up(): bool
    {
        $success = true;
        
        // Create tables in the correct order to handle foreign key constraints
        $success = $success && $this->createKeywordsTable();
        $success = $success && $this->createCollectorsTable();
        $success = $success && $this->createStepsTable();
        $success = $success && $this->createCompletionsTable();
        $success = $success && $this->createQuestionsTable();
        $success = $success && $this->createSetupCategoriesTable();
        $success = $success && $this->createSetupTable();
        $success = $success && $this->createSeoOptimisersTable();
        $success = $success && $this->createSeoContextsTable();
        $success = $success && $this->createSeoFactorsTable();
        $success = $success && $this->createSeoOperationsTable();
        
        return $success;
    }

    /**
     * Reverse the migration
     *
     * @return bool Success status
     */
    public function down(): bool
    {
        $success = true;
        
        // Drop tables in reverse order to handle foreign key constraints
        $success = $success && $this->dropTable(DatabaseTablesManager::DATABASE_SEO_OPERATIONS);
        $success = $success && $this->dropTable(DatabaseTablesManager::DATABASE_SEO_FACTORS);
        $success = $success && $this->dropTable(DatabaseTablesManager::DATABASE_SEO_CONTEXTS);
        $success = $success && $this->dropTable(DatabaseTablesManager::DATABASE_SEO_OPTIMISERS);
        $success = $success && $this->dropTable(DatabaseTablesManager::DATABASE_SETUP);
        $success = $success && $this->dropTable(DatabaseTablesManager::DATABASE_SETUP_CATEGORIES);
        $success = $success && $this->dropTable(DatabaseTablesManager::DATABASE_SETUP_QUESTIONS);
        $success = $success && $this->dropTable(DatabaseTablesManager::DATABASE_SETUP_COMPLETIONS);
        $success = $success && $this->dropTable(DatabaseTablesManager::DATABASE_SETUP_STEPS);
        $success = $success && $this->dropTable(DatabaseTablesManager::DATABASE_SETUP_COLLECTORS);
        $success = $success && $this->dropTable(DatabaseTablesManager::DATABASE_APP_KEYWORDS);
        
        return $success;
    }

    /**
     * Get the migration description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create all database tables';
    }
    
    /**
     * Create keywords table
     *
     * @return bool Success status
     */
    private function createKeywordsTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_APP_KEYWORDS);
        $charsetCollate = $this->getCharsetCollate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            externalId BIGINT UNSIGNED,
            name VARCHAR(255) NOT NULL,
            alias VARCHAR(255),
            hash VARCHAR(255),
            INDEX idx_external_id (externalId)
        ) $charsetCollate;";
        
        return $this->executeQuery($sql);
    }
    
    /**
     * Create collectors table
     *
     * @return bool Success status
     */
    private function createCollectorsTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP_COLLECTORS);
        $charsetCollate = $this->getCharsetCollate();
        
        // collector is unique-indexed: VARCHAR(100) keeps the key at 400 bytes,
        // under the 767-byte limit on hosts without large index prefixes.
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
        
        $result = $this->executeQuery($sql);
        
        if ($result) {
            $this->populateCollectorsTable($tableName);
        }
        
        return $result;
    }
    
    /**
     * Create steps table
     *
     * @return bool Success status
     */
    private function createStepsTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP_STEPS);
        $charsetCollate = $this->getCharsetCollate();
        
        // step is unique-indexed: VARCHAR(100) keeps the key at 400 bytes,
        // under the 767-byte limit on hosts without large index prefixes.
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
        
        $result = $this->executeQuery($sql);
        
        if ($result) {
            $this->populateStepsTable($tableName);
        }
        
        return $result;
    }
    
    /**
     * Create completions table
     *
     * @return bool Success status
     */
    private function createCompletionsTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP_COMPLETIONS);
        $charsetCollate = $this->getCharsetCollate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT NOT NULL AUTO_INCREMENT,
            stepId int(11) NOT NULL,
            collectorId INT(11) DEFAULT NULL,
            questionId INT(11) DEFAULT NULL,
            answer VARCHAR(255) NOT NULL,
            data TEXT DEFAULT NULL,
            timeOfCompletion INT(11) DEFAULT NULL,
            isCompleted tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_stepId (stepId),
            KEY idx_collectorId (collectorId),
            KEY idx_questionId (questionId),
            KEY idx_timeOfCompletion (timeOfCompletion),
            KEY idx_isCompleted (isCompleted)
        ) $charsetCollate;";
        
        return $this->executeQuery($sql);
    }
    
    /**
     * Create questions table
     *
     * @return bool Success status
     */
    private function createQuestionsTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP_QUESTIONS);
        $charsetCollate = $this->getCharsetCollate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            stepId INT UNSIGNED NOT NULL,
            question TEXT NOT NULL,
            sequence INT NOT NULL DEFAULT 1,
            aiContext TEXT NULL,
            parentId INT UNSIGNED NULL,
            isAiGenerated BOOLEAN NOT NULL DEFAULT FALSE,
            INDEX idx_step_id (stepId),
            INDEX idx_parent_question (parentId)
        ) $charsetCollate;";
        
        $result = $this->executeQuery($sql);
        
        if ($result) {
            $this->populateQuestionsTable($tableName);
        }
        
        return $result;
    }
    
    /**
     * Create setup categories table
     *
     * @return bool Success status
     */
    private function createSetupCategoriesTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP_CATEGORIES);
        $charsetCollate = $this->getCharsetCollate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            categoryId BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            externalId VARCHAR(255) NULL,
            FULLTEXT KEY name (name),
            INDEX idx_category_id (categoryId)
        ) $charsetCollate;";
        
        return $this->executeQuery($sql);
    }
    
    /**
     * Create setup table
     *
     * @return bool Success status
     */
    private function createSetupTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SETUP);
        $charsetCollate = $this->getCharsetCollate();
        
        // The original (setupRequirement, entityAlias) VARCHAR(255) composite index was
        // 2040 bytes under utf8mb4, over the 767-byte key limit on hosts without large
        // index prefixes (MyISAM defaults, InnoDB COMPACT) — there CREATE fails, dbDelta
        // swallows the error, and the table silently never exists. Columns are sized to
        // their real content (requirement names <= ~30 chars, aliases <= ~20).
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            setupRequirement VARCHAR(100) NOT NULL,
            entityAlias VARCHAR(50) NOT NULL,
            value TEXT NULL,
            INDEX idx_setup_requirement (setupRequirement)
        ) $charsetCollate;";
        
        $result = $this->executeQuery($sql);
        
        if ($result) {
            $this->populateSetupTable($tableName);
        }
        
        return $result;
    }
    
    /**
     * Create SEO optimisers table
     *
     * @return bool Success status
     */
    private function createSeoOptimisersTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SEO_OPTIMISERS);
        $charsetCollate = $this->getCharsetCollate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            postId BIGINT UNSIGNED NOT NULL,
            overallScore DECIMAL(5,2) NOT NULL,
            analysisDate DATETIME NOT NULL,
            INDEX idx_postId (postId)
        ) $charsetCollate;";
        
        return $this->executeQuery($sql);
    }
    
    /**
     * Create SEO contexts table
     *
     * @return bool Success status
     */
    private function createSeoContextsTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SEO_CONTEXTS);
        $charsetCollate = $this->getCharsetCollate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            analysisId BIGINT UNSIGNED NOT NULL,
            contextName VARCHAR(255) NOT NULL,                
            contextKey VARCHAR(50) NOT NULL,
            weight DECIMAL(3,2) NOT NULL,
            score DECIMAL(5,2) NOT NULL,
            UNIQUE KEY uq_analysis_context (analysisId, contextKey)
        ) $charsetCollate;";
        
        return $this->executeQuery($sql);
    }
    
    /**
     * Create SEO factors table
     *
     * @return bool Success status
     */
    private function createSeoFactorsTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SEO_FACTORS);
        $charsetCollate = $this->getCharsetCollate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            contextId BIGINT UNSIGNED NOT NULL,
            factorName VARCHAR(255) NOT NULL,
            factorKey VARCHAR(50) NOT NULL,
            description TEXT DEFAULT NULL,
            weight DECIMAL(3,2) NOT NULL,
            score DECIMAL(5,2) NOT NULL,
            fetchedData LONGTEXT DEFAULT NULL,
            UNIQUE KEY uq_context_factor (contextId, factorKey)
        ) $charsetCollate;";
        
        return $this->executeQuery($sql);
    }
    
    /**
     * Create SEO operations table
     *
     * @return bool Success status
     */
    private function createSeoOperationsTable(): bool
    {
        $tableName = $this->getTableName(DatabaseTablesManager::DATABASE_SEO_OPERATIONS);
        $charsetCollate = $this->getCharsetCollate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $tableName (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            factorId BIGINT UNSIGNED NOT NULL,
            operationName VARCHAR(255) NOT NULL,
            operationKey VARCHAR(100) NOT NULL,
            weight DECIMAL(3,2) NOT NULL,
            score DECIMAL(5,2) NOT NULL,
            value LONGTEXT DEFAULT NULL,
            suggestions LONGTEXT DEFAULT NULL,
            UNIQUE KEY uq_factor_operation (factorId, operationKey)
        ) $charsetCollate;";
        
        return $this->executeQuery($sql);
    }
    
    /**
     * Drop a table
     *
     * @param string $tableName Table name without prefix
     * @return bool Success status
     */
    private function dropTable(string $tableName): bool
    {
        $tableName = $this->getTableName($tableName);
        $sql = "DROP TABLE IF EXISTS $tableName;";
        return $this->executeQuery($sql);
    }
    
    /**
     * Populate collectors table
     *
     * @param string $tableName The table name with prefix
     */
    private function populateCollectorsTable(string $tableName): void
    {
        if (!$this->tableIsEmpty($tableName)) {
            return;
        }

        $collectors = ['Database', 'WordPress', 'Extendify', 'RankingCoach'];

        foreach ($collectors as $index => $collectorItem) {
            $priority = $index + 1;
            $data = [
                'collector' => $collectorItem,
                'settings' => null,
                'className' => $collectorItem,
                'priority' => $priority,
                'active' => 1,
            ];
            
            // Use DatabaseManager's insert method instead of direct db access
            $this->dbManager->insert(
                DatabaseTablesManager::DATABASE_SETUP_COLLECTORS,
                $data
            );
        }
    }
    
    /**
     * Populate steps table
     *
     * @param string $tableName The table name with prefix
     */
    private function populateStepsTable(string $tableName): void
    {
        if (!$this->tableIsEmpty($tableName)) {
            return;
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
            $last = $index === $countSteps;
            $data = [
                'step' => $stepName,
                'requirements' => $requirements,
                'priority' => $index,
                'isFinalStep' => $last ? 1 : 0,
                'active' => 1,
                'completed' => 0
            ];
            
            // Use DatabaseManager's insert method instead of direct db access
            $this->dbManager->insert(
                DatabaseTablesManager::DATABASE_SETUP_STEPS,
                $data
            );
        }
    }
    
    /**
     * Populate questions table
     *
     * @param string $tableName The table name with prefix
     */
    private function populateQuestionsTable(string $tableName): void
    {
        if (!$this->tableIsEmpty($tableName)) {
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
            // Get the step ID using DatabaseManager's getRow method
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
                $sequence = $index + 1;
                $data = [
                    'parentId' => null,
                    'stepId' => $stepId,
                    'question' => $question,
                    'sequence' => $sequence,
                    'aiContext' => null,
                    'isAiGenerated' => false,
                ];
                
                // Use DatabaseManager's insert method instead of direct db access
                $this->dbManager->insert(
                    DatabaseTablesManager::DATABASE_SETUP_QUESTIONS,
                    $data
                );
            }
        }
    }
    
    /**
     * Populate setup table
     *
     * @param string $tableName The table name with prefix
     */
    private function populateSetupTable(string $tableName): void
    {
        if (!$this->tableIsEmpty($tableName)) {
            return;
        }

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
        
        foreach ($allRequirements as $requirement) {
            $value = null;
            $entityAlias = strtolower(str_replace('business', '', $requirement));
            $data = [
                'setupRequirement' => $requirement,
                'entityAlias' => $entityAlias,
                'value' => $value,
            ];
            
            // Use DatabaseManager's insert method instead of direct db access
            $this->dbManager->insert(
                DatabaseTablesManager::DATABASE_SETUP,
                $data
            );
        }
    }

    /**
     * Whether a table currently holds no rows — the populate methods only seed
     * empty tables, so a re-run of this migration cannot duplicate seed data.
     *
     * @param string $tableName The table name with prefix
     * @return bool
     */
    private function tableIsEmpty(string $tableName): bool
    {
        $rows = $this->dbManager->queryRaw("SELECT 1 FROM `$tableName` LIMIT 1");
        return empty($rows);
    }
}
