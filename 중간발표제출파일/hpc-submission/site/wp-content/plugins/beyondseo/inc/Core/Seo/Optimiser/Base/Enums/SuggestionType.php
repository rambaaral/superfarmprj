<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\Seo\Optimiser\Base\Enums;

if (!defined('ABSPATH')) { exit; }

/**
 * Class SuggestionType
 *
 * This class defines various suggestion types that can be encountered during SEO analysis.
 */
class SuggestionType
{
    public const INFO = 'info';
    public const IMPLEMENTATION = 'implementation';
    public const OPTIMIZATION = 'optimization';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const NOTICE = 'notice';

    /**
     * Get all available suggestion types
     *
     * @return array<string>
     */
    public static function getAll(): array
    {
        return [
            self::INFO,
            self::IMPLEMENTATION,
            self::OPTIMIZATION,
            self::WARNING,
            self::ERROR,
            self::NOTICE,
        ];
    }

    /**
     * Check if the given value is a valid suggestion type
     *
     * @param string $value
     * @return bool
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::getAll(), true);
    }

    /**
     * Get the description for a suggestion type
     *
     * @param string $type
     * @return string|null
     */
    public static function getDescription(string $type): ?string
    {
        return match ($type) {
            self::INFO => __('Informational suggestion', 'beyondseo'),
            self::IMPLEMENTATION => __('Implementation suggestion', 'beyondseo'),
            self::OPTIMIZATION => __('Optimization suggestion', 'beyondseo'),
            self::WARNING => __('Warning suggestion', 'beyondseo'),
            self::ERROR => __('Error suggestion', 'beyondseo'),
            self::NOTICE => __('Notice suggestion', 'beyondseo'),
            default => null,
        };
    }
}
