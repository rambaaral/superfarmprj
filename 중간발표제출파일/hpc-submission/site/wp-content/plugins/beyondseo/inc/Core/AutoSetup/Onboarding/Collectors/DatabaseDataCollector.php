<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\AutoSetup\Onboarding\Collectors;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

class DatabaseDataCollector extends AbstractCollector
{
    public string $collector = 'Database';
    public bool $saveCollectedData = false;

    private array $requirementValues = [];

    public function __construct(?int $id = null, array $settings = [])
    {
        parent::__construct($id, $settings);
        $this->requirementValues = $this->loadRequirements();
    }

    private function loadRequirements(): array
    {
        $rows = DatabaseManager::getInstance()
            ->table(DatabaseTablesManager::DATABASE_SETUP)
            ->select(['setupRequirement', 'value'])
            ->get();

        $map = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $row = is_object($row) ? (array) $row : $row;
                if (isset($row['setupRequirement'])) {
                    $map[$row['setupRequirement']] = $row['value'];
                }
            }
        }
        return $map;
    }

    public function businessEmailAddress(): ?string
    {
        return $this->requirementValues['businessEmailAddress'] ?? null;
    }

    public function businessWebsiteUrl(): ?string
    {
        return $this->requirementValues['businessWebsiteUrl'] ?? null;
    }

    public function businessName(): ?string
    {
        return $this->requirementValues['businessName'] ?? null;
    }

    public function businessDescription(): ?string
    {
        return $this->requirementValues['businessDescription'] ?? null;
    }

    public function businessAddress(): ?string
    {
        return $this->requirementValues['businessAddress'] ?? null;
    }

    public function businessGeoAddress(): ?string
    {
        return $this->requirementValues['businessGeoAddress'] ?? null;
    }

    public function businessServiceArea(): ?string
    {
        return $this->requirementValues['businessServiceArea'] ?? null;
    }

    public function businessKeywords(): ?string
    {
        return $this->requirementValues['businessKeywords'] ?? null;
    }

    public function businessCategories(): ?string
    {
        return $this->requirementValues['businessCategories'] ?? null;
    }

    public function businessSpecificDescription(): ?string
    {
        return $this->requirementValues['businessSpecificDescription'] ?? null;
    }
}
