<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Helpers;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;

class RequirementHelper
{
    /**
     * Map of requirement names to their entity aliases.
     */
    public const ENTITY_ALIAS_MAP = [
        'businessEmailAddress' => 'emailaddress',
        'businessWebsiteUrl'   => 'websiteurl',
        'businessName'         => 'name',
        'businessDescription'  => 'description',
        'businessAddress'      => 'address',
        'businessServiceArea'  => 'servicearea',
        'businessKeywords'     => 'keywords',
        'businessCategories'   => 'categories',
        'businessGeoAddress'   => 'geoaddress',
    ];

    /**
     * Update multiple requirements at once.
     * 
     * @param array $requirements Associative array of requirement name => value
     */
    public static function updateRequirements(array $requirements): void
    {
        foreach ($requirements as $name => $val) {
            self::updateRequirement($name, $val);
        }
    }

    /**
     * Update a single requirement in the database.
     *
     * @param string $name Requirement name
     * @param mixed $val Requirement value
     * @throws \JsonException
     */
    public static function updateRequirement(string $name, $val): void
    {
        if (!isset(self::ENTITY_ALIAS_MAP[$name])) {
            return;
        }

        $alias = self::ENTITY_ALIAS_MAP[$name];

        // Convert object to array if needed
        if (is_object($val)) {
            $val = json_decode(json_encode($val, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        }

        // Decode JSON string for complex requirements if provided as serialized JSON
        if (is_string($val) && in_array($name, ['businessKeywords', 'businessCategories', 'businessGeoAddress'], true)) {
            $trimmed = trim($val);
            if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $val = $decoded;
                }
            }
        }

        // Clean category items (preserving both category IDs and custom/localized category names)
        if ($name === 'businessCategories' && is_array($val)) {
            $cleaned = [];
            foreach ($val as $cat) {
                if (is_int($cat) || (is_string($cat) && ctype_digit($cat))) {
                    $cleaned[] = (int) $cat;
                } elseif (is_string($cat) && trim($cat) !== '') {
                    $cleaned[] = trim($cat);
                } elseif (is_array($cat) && !empty($cat['name']) && is_string($cat['name'])) {
                    $cleaned[] = trim($cat['name']);
                }
            }
            $val = array_values($cleaned);
        }

        // Format value for database
        if (is_array($val) || is_object($val)) {
            $dbValue = json_encode($val, JSON_THROW_ON_ERROR);
        } elseif (is_bool($val)) {
            $dbValue = $val ? 'y' : 'n';
        } else {
            $dbValue = sanitize_text_field((string)$val);
        }

        // Hardcoded rule for service area from legacy logic
        if ($name === 'businessServiceArea') {
            $dbValue = 'y';
        }

        if ($dbValue === '') {
            return;
        }

        $db = DatabaseManager::getInstance();
        $existing = $db->table(DatabaseTablesManager::DATABASE_SETUP)
            ->select(['id'])
            ->where('setupRequirement', $name)
            ->first();

        if ($existing) {
            $db->update(
                DatabaseTablesManager::DATABASE_SETUP,
                ['value' => $dbValue, 'entityAlias' => $alias],
                ['setupRequirement' => $name]
            );
        } else {
            $db->insert(
                DatabaseTablesManager::DATABASE_SETUP,
                [
                    'setupRequirement' => $name,
                    'entityAlias'      => $alias,
                    'value'            => $dbValue,
                ]
            );
        }

        // Refresh steps completion status
        self::refreshStepsCompletion();
    }

    /**
     * Refresh the completion status of all setup steps based on current requirements.
     */
    public static function refreshStepsCompletion(): void
    {
        $db = DatabaseManager::getInstance();
        
        // 1. Get all requirements and their values
        $requirementsRows = $db->table(DatabaseTablesManager::DATABASE_SETUP)
            ->select(['setupRequirement', 'value'])
            ->get();

        $satisfiedRequirements = [];
        if (is_array($requirementsRows)) {
            foreach ($requirementsRows as $reqRow) {
                $reqRow = (array) $reqRow;
                $reqName = $reqRow['setupRequirement'] ?? '';
                $reqValue = $reqRow['value'] ?? null;
                
                if ($reqValue !== null && $reqValue !== '') {
                    $decoded = json_decode((string)$reqValue, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        if (!empty($decoded)) {
                            $satisfiedRequirements[$reqName] = $decoded;
                        }
                    } else {
                        $satisfiedRequirements[$reqName] = $reqValue;
                    }
                }
            }
        }

        // 2. Get all steps
        $dbSteps = $db->table(DatabaseTablesManager::DATABASE_SETUP_STEPS)
            ->select(['id', 'requirements', 'completed'])
            ->get();

        if (is_array($dbSteps)) {
            foreach ($dbSteps as $stepRow) {
                $stepRow = (array) $stepRow;
                $stepId = (int) $stepRow['id'];
                $requirementsStr = $stepRow['requirements'] ?? '';
                $stepRequirements = array_filter(array_map('trim', explode(',', $requirementsStr)));

                if (empty($stepRequirements)) {
                    continue;
                }

                $allSatisfied = true;
                foreach ($stepRequirements as $req) {
                    if (!isset($satisfiedRequirements[$req])) {
                        $allSatisfied = false;
                        break;
                    }
                }

                if ($allSatisfied && (int)($stepRow['completed'] ?? 0) === 0) {
                    $db->update(
                        DatabaseTablesManager::DATABASE_SETUP_STEPS,
                        ['completed' => 1],
                        ['id' => $stepId]
                    );
                }
            }
        }
    }
}
