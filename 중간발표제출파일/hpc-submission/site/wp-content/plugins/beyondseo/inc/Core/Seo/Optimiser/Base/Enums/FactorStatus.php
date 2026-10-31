<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums;

if (!defined('ABSPATH')) { exit; }

/**
 * Class representing the status of a factor in SEO optimization.
 */
class FactorStatus
{
    /**
     * Represents a completed factor.
     * This is used when the score is 1, indicating that the factor is fully optimized.
     */
    public const COMPLETED = 'completed';
    
    /**
     * Represents an incomplete factor.
     * This is used when the score is between 0 and 1, indicating that the factor is partially optimized.
     */
    public const INCOMPLETE = 'incomplete';
    
    /**
     * Represents a missing factor.
     * This is used when the score is 0, indicating that the factor is not present or not applicable.
     */
    public const MISSING = 'missing';

    /**
     * Get all available factor statuses
     *
     * @return array<string>
     */
    public static function getAll(): array
    {
        return [
            self::COMPLETED,
            self::INCOMPLETE,
            self::MISSING,
        ];
    }

    /**
     * Check if the given value is a valid factor status
     *
     * @param string $value
     * @return bool
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::getAll(), true);
    }

    /**
     * Get the status of a factor based on its score.
     * @param float $score
     * @return string
     */
    public static function fromScore(float $score): string
    {
        if ($score === 1.0) {
            return self::COMPLETED;
        }
        
        if ($score === 0.0) {
            return self::MISSING;
        }
        
        return self::INCOMPLETE;
    }
}
