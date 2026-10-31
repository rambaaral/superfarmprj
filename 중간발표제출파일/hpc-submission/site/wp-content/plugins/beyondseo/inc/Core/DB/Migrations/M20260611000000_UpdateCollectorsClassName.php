<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\DB\AbstractMigration;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

class M20260611000000_UpdateCollectorsClassName extends AbstractMigration
{
    private const COLLECTOR_PREFIXES = [
        'Database',
        'WordPress',
        'Extendify',
        'RankingCoach',
    ];

    public function up(): bool
    {
        if (!$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_SETUP_COLLECTORS)) {
            return true;
        }

        $success = true;

        foreach (self::COLLECTOR_PREFIXES as $prefix) {
            $result = $this->dbManager->update(
                DatabaseTablesManager::DATABASE_SETUP_COLLECTORS,
                ['className' => $prefix],
                ['collector' => $prefix]
            );

            if ($result === false) {
                $success = false;
            }
        }

        return $success;
    }

    public function down(): bool
    {
        // WARNING: Rollback to legacy Symfony class names will fail if the app/ folder has been removed
        if (!$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_SETUP_COLLECTORS)) {
            return true;
        }

        $legacyBase = 'BeyondSEO\\Domain\\Integrations\\WordPress\\Setup\\Entities\\Flows\\Collectors\\Data\\';
        $success = true;

        foreach (self::COLLECTOR_PREFIXES as $prefix) {
            $result = $this->dbManager->update(
                DatabaseTablesManager::DATABASE_SETUP_COLLECTORS,
                ['className' => $legacyBase . $prefix . 'DataCollector'],
                ['collector' => $prefix]
            );

            if ($result === false) {
                $success = false;
            }
        }

        return $success;
    }

    public function getDescription(): string
    {
        return 'Update collectors className from legacy Symfony FQCNs to short class prefixes';
    }
}
