<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\AutoSetup\Onboarding\Collectors;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

class CollectorManager
{
    private const COLLECTOR_NAMESPACE = 'RankingCoach\\Inc\\Core\\AutoSetup\\Onboarding\\Collectors\\';

    private const ALL_REQUIREMENTS = [
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

    public function loadCollectors(): array
    {
        $rows = DatabaseManager::getInstance()
            ->table(DatabaseTablesManager::DATABASE_SETUP_COLLECTORS)
            ->select(['*'])
            ->orderBy('priority', 'ASC')
            ->get();

        return is_array($rows) ? $rows : [];
    }

    public function instantiateCollector(object $row): ?AbstractCollector
    {
        $className = self::COLLECTOR_NAMESPACE . $row->className . 'DataCollector';

        if (!class_exists($className)) {
            return null;
        }

        $settings = [];
        if (!empty($row->settings)) {
            $decoded = json_decode($row->settings, true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }

        $id = isset($row->id) ? (int) $row->id : null;

        return new $className($id, $settings);
    }

    public function collect(array $requirementNames = self::ALL_REQUIREMENTS): array
    {
        $collected = [];
        $collectors = $this->loadCollectors();

        foreach ($collectors as $row) {
            if (!(bool) $row->active) {
                continue;
            }

            $instance = $this->instantiateCollector($row);
            if ($instance === null) {
                continue;
            }

            foreach ($requirementNames as $requirementName) {
                $value = $instance->{$requirementName}();
                if ($value !== null && $instance->saveCollectedData) {
                    $collected[$requirementName] = $value;
                }
            }
        }

        return $collected;
    }

    public function persist(array $collected): void
    {
        $db = DatabaseManager::getInstance();
        foreach ($collected as $requirementName => $value) {
            $db->update(
                DatabaseTablesManager::DATABASE_SETUP,
                ['value' => $value],
                ['setupRequirement' => $requirementName]
            );
        }
    }

    public function run(array $requirementNames = self::ALL_REQUIREMENTS): array
    {
        $collected = $this->collect($requirementNames);
        $this->persist($collected);
        return $collected;
    }
}
